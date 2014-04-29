<?php
require_once("../sds.php");
sdsRequireGroup("USERS","DESK");
require_once('../sds/ordering.inc.php');

sdsIncludeHeader("Simmons Movies");

$sortby = getSortby($_REQUEST['sortby'],2,4,'movie_list_sortby');

$orderbyarray =
array (# box asc,desc
       "to_number(substring(box_number FROM '^[[:digit:]]+'),'9999999999') ASC,box_number ASC,title ASC",
       "to_number(substring(box_number FROM '^[[:digit:]]+'),'9999999999') DESC,box_number DESC,title ASC",
       # title asc,desc
       "title ASC,minbox ASC",
       "title DESC,minbox ASC",
       # num disks asc,desc
       "num_disks ASC,minbox ASC",
       "num_disks DESC,minbox ASC",
       # status asc,desc
       "available DESC,out DESC,hid_available DESC,hid_out DESC,title ASC",
       "available ASC,out ASC,hid_available ASC,hid_out ASC,title ASC",
       );

$showhidden = isset($_REQUEST['showall']) and $session->groups['MOVIEADMINS'];
$showallarg = $showhidden ? 'showall=1' : '';
$hiddenclause = $showhidden ? "AND NOT deleted" : "AND NOT hidden";

$orderbytitle = ($sortby==2 or $sortby==3);
$groupcopies = ($sortby>1 and $sortby<6);

# Only allow itemtype to be set to a number
unset($itemtype);
if(isset($_REQUEST['type']) and !preg_match("/\D/",$_REQUEST['type'])) {
  $itemtype = (int) $_REQUEST['type'];
} elseif(isset($session->data['movie_itemtype'])) {
  $itemtype = $session->data['movie_itemtype'];
}
if(isset($itemtype)) {
  $query = "SELECT 1 FROM movie_types WHERE active AND typeid=$itemtype";
  $result = sdsQuery($query);
  if(!$result)
    contactTech("Could not find types");
  if(pg_num_rows($result)!=1) {
    unset($itemtype);
  }
  pg_free_result($result);
}
if(!isset($itemtype)) {
  $query = "SELECT typeid FROM movie_types WHERE active ORDER BY typeid LIMIT 1";
  $result = sdsQuery($query);
  if(!$result)
    contactTech("Could not search types");
  if(pg_num_rows($result)!=1) {
    echo "<h2>Error: Could not find any types!</h2>\n";
    if($showhidden) {
      echo "<p><a href='",sdsLink("typeedit.php"),"'>Edit Types</a></p>\n";
    }
    sdsIncludeFooter();
    exit;
  }
  list($itemtype) = pg_fetch_row($result);
  pg_free_result($result);
}
$session->data['movie_itemtype'] = $itemtype;
$session->saveData();

$query = "SELECT typeid,typename FROM movie_types WHERE active ORDER BY typeid";
$result = sdsQuery($query);
if(!$result)
  contactTech("Could not find types");

if(pg_num_rows($result) > 1 or $showhidden) {
  echo "<form name='itemtype' action='list.php' method='get'>\n";
  if($showhidden) {
    echo "<input type='hidden' name='showall' value='1' />\n";
  }
  echo sdsForm();
  
  echo "  <p>Contact the entertainment chairs at <a href='mailto:simmons-entertainment@mit.edu'>simmons-entertainment@mit.edu</a> with any questions or errors with the listing.</p>";
  
  echo "  <label>Item type:\n";
  echo "    <select name='type' onchange='itemtype.submit();'>\n";
  while($iteminfo = pg_fetch_array($result)) {
    echo "      <option ",
      $iteminfo['typeid']==$itemtype?'selected="selected" ':'',
      "value='",$iteminfo['typeid'],"'>",
      htmlspecialchars($iteminfo['typename']),"</option>\n";
  }
  pg_free_result($result);
  echo "    </select>\n";
  if($showhidden) {
    echo "    <a href='",sdsLink("typeedit.php"),"'>Edit Types</a>\n";
  }
  echo "  </label>\n";
  echo "  <noscript><input type='submit' value='Submit' /></noscript>\n";
  echo "</form>\n";
}

if($showhidden) {
  echo "<h2><a href='",sdsLink("insert.php"),"'>Add Movies</a></h2>\n";
}

function getletter($arr) { return $arr['letter']; }

if($orderbytitle) {
  $query = <<<ENDQUERY
SELECT upper(substring(title FROM 1 FOR 1)) AS letter
FROM movie_items JOIN movie_instances USING (movieid)
WHERE typeid=$itemtype $hiddenclause
GROUP BY letter
ENDQUERY;
  $result = sdsQuery($query);
  $letters = pg_fetch_all($result);
  $letters = array_map('getletter',(array) $letters);
  pg_free_result($result);

  echo "<table style='margin-left:auto;margin-right:auto;'>\n";
  echo "  <tr>\n";
  if(preg_grep('/^\d/',$letters)) {
    echo "    <td><a href='#letter_num'>#</a></td>\n";
  } else {
    echo "    <td>#</td>\n";
  }
  for($letter='A';$letter!='AA';++$letter) {
    if(in_array($letter,$letters)) {
      echo "    <td><a href='#letter",$letter,"'>",$letter,"</a></td>\n";
    } else {
      echo "    <td>",$letter,"</td>\n";
    }
  }
  echo "  </tr>\n";
  echo "</table>\n";
}

$hiddenfields = $showhidden ? <<<ENDCLAUSE
       count(CASE WHEN hidden AND NOT deleted AND NOT checked_out THEN 1 END)
         AS hid_available,
       count(CASE WHEN hidden AND NOT deleted AND checked_out THEN 1 END)
         AS hid_out,
ENDCLAUSE
: '0 AS hid_available, 0 AS hid_out,';
$groupbyextra = $groupcopies ? "" : ",box_number";
$query = <<<ENDQUERY
SELECT movieid,title,num_disks,min(box_number) AS minbox,
       count(CASE WHEN NOT hidden AND NOT checked_out THEN 1 END) AS available,
       count(CASE WHEN NOT hidden AND checked_out THEN 1 END) AS out,
       $hiddenfields
       link
FROM movie_items JOIN movie_instances USING (movieid)
WHERE typeid=$itemtype
GROUP BY movieid,title,num_disks,link$groupbyextra
ORDER BY $orderbyarray[$sortby]
ENDQUERY;
$listresult = sdsQuery($query);
if(!$listresult)
  contactTech("Could not find items");

function printAvailable($record,$multiitem) {
  echo "    <td>", $multiitem ? $record['available'] : '',
    "</td><td class='statuscell'>Available</td>\n";
}
function printOut($record,$multiitem) {
  echo "    <td>", $multiitem ? $record['out'] : '',
    "</td><td class='statuscell'>Checked Out</td>\n";
}
function printHid_Available($record,$multiitem) {
  echo "    <td>", $multiitem ? $record['hid_available'] : '',
    "</td><td class='statuscell'>Hidden Available</td>\n";
}
function printHid_Out($record,$multiitem) {
  echo "    <td>", $multiitem ? $record['hid_out'] : '',
    "</td><td class='statuscell'>Hidden Checked Out</td>\n";
}

function printFirst($record,$multiitem) {
  if($record['available']>0) { printAvailable($record,$multiitem); }
  elseif($record['out']>0) { printOut($record,$multiitem); }
  elseif($record['hid_available']>0)
    { printHid_Available($record,$multiitem); }
  else { printHid_Out($record,$multiitem); }
}
function printRest($record,$multiitem,$rowclass) {
  $count=0;
  if($record['available']>0) { ++$count; }
  if($record['out']>0 and ++$count>=2) {
    echo "  <tr class='",$rowclass,"'>\n";
    printOut($record,$multiitem);
    echo "  </tr>\n";
  }
  if($record['hid_available']>0 and ++$count>=2) {
    echo "  <tr class='",$rowclass,"'>\n";
    printHid_Available($record,$multiitem);
    echo "  </tr>\n";
  }
  if($record['hid_out']>0 and ++$count>=2) {
    echo "  <tr class='",$rowclass,"'>\n";
    printHid_Out($record,$multiitem);
    echo "  </tr>\n";
  }
}


echo "<table class='movielist'>\n";
echo "  <colgroup width='0*' />\n";
echo "  <colgroup />\n";
echo "  <colgroup span='6' width='0*' />\n";
echo "  <tr>\n";
makeSortTH("Box",0,$sortby,$showallarg);
makeSortTH("Title",1,$sortby,$showallarg);
makeSortTH("# Disks",2,$sortby,$showallarg);
makeSortTH("Status",3,$sortby,$showallarg,' colspan="2"');
if(!empty($session->groups['DESK']) or
   !empty($session->groups['MOVIEADMINS'])) {
  echo "    <th>Check Out</th>\n";
}
if($showhidden) {
  echo "    <th>Edit</th>\n";
  echo "    <th>History</th>\n";
}
echo "  </tr>\n";
$rowparity = 1;
$currentletter = '';
while($record = pg_fetch_array($listresult)) {
  if($record['available']>0 or $record['out']>0 or
     $record['hid_available']>0 or $record['hid_out']>0) {
    $multiitem = $record['available']+$record['out']+
      $record['hid_available']+$record['hid_out']>1;
    $rowparity = 1-$rowparity;
    $rowclass = $rowparity ? 'oddrow' : 'evenrow';
    if($record['available']==0 and $record['out']==0) {
      $rowclass .= ' hidden';
    }
    $rowspan = ($record['available']>0) + ($record['out']>0)+
      ($record['hid_available']>0) + ($record['hid_out']>0);
    if($orderbytitle) {
      if($currentletter !== ucfirst(substr($record['title'],0,1)) and
	 preg_match('/[A-Z]/',ucfirst(substr($record['title'],0,1)))) {
	$currentletter = ucfirst(substr($record['title'],0,1));
	echo "  <tr id='letter",$currentletter,"' class='",$rowclass,"'>\n";
      } elseif($currentletter !== '#' and
		preg_match("/^\d/",$record['title'])) {
	$currentletter = '#';
	echo "  <tr id='letter_num' class='",$rowclass,"'>\n";
      } else {
	echo "  <tr class='",$rowclass,"'>\n";
      }
    } else {
      echo "  <tr class='",$rowclass,"'>\n";
    }

    if($groupcopies) {
      $query = "SELECT box_number FROM movie_instances WHERE movieid=" . 
	$record['movieid'] . " " . $hiddenclause .
	" ORDER BY to_number(substring(box_number FROM '^[[:digit:]]+'),'9999999999'),box_number";
      $boxresult = sdsQuery($query);
      if(!$boxresult) {
	echo "</tr></table>\n";
	contactTech("Could not find boxes");
      }
      $boxes = '';
      while(list($boxnum) = pg_fetch_array($boxresult)) {
	$boxes .= $boxnum . ',';
      }
      pg_free_result($boxresult);
      $boxes = rtrim($boxes,',');
      echo "    <td rowspan='",$rowspan,"'>",
	htmlspecialchars($boxes),"</td>\n";
    } else {
      echo "    <td rowspan='",$rowspan,"'>",
	htmlspecialchars($record['minbox']),"</td>\n";
    }
    echo "    <td class='titlecell' rowspan='",$rowspan,"'>";
    if($record['link']) {
      echo '<a href="',htmlspecialchars($record['link']),'" target="_blank">',
	htmlspecialchars($record['title']),"</a></td>\n";
    } else {
      echo htmlspecialchars($record['title']),"</td>\n";
    }
    echo "    <td rowspan='",$rowspan,"'>",$record['num_disks'],"</td>\n";
    printFirst($record,$multiitem);
    if(!empty($session->groups['DESK']) or
       !empty($session->groups['MOVIEADMINS'])) {
      echo "    <td rowspan='",$rowspan,"'>";
      if($record['available']>0) {
	echo "<a href='",
	  sdsLink("checkout.php","movieid=".$record['movieid']),
	  "'>Check Out</a>";
      }
      echo "</td>\n";
    }
    if($showhidden) {
      echo "    <td rowspan='",$rowspan,"'><a href='",
	sdsLink("edit.php","movieid=".$record['movieid']),
	"'>Edit</a></td>\n";
      echo "    <td rowspan='",$rowspan,"'><a href='",
	sdsLink("itemhistory.php","movieid=".$record['movieid']),
	"'>History</a></td>\n";
    }
    echo "  </tr>\n";
    printRest($record,$multiitem,$rowclass);
  }
}
pg_free_result($listresult);
echo "</table>\n";

sdsIncludeFooter();
