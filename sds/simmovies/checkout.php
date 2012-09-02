<?php
require_once('../sds.php');
require_once('../directory/directory.inc.php');
sdsRequireGroup('DESK',"MOVIEADMINS");

sdsIncludeHeader('Simmons Movie Checkout');

$instanceid = null;
if(!empty($_REQUEST['instanceid']) and
   !preg_match('/\D/',$_REQUEST['instanceid'])) {
  $instanceid = (int) $_REQUEST['instanceid'];
}

$movieid = null;
if(strlen($_REQUEST['movieid']) and
   !preg_match('/\D/',$_REQUEST['movieid'])) {
  $movieid = (int) $_REQUEST['movieid'];
} elseif(isset($instanceid)) {
  $query = "SELECT movieid FROM movie_instances WHERE instanceid=$instanceid AND NOT hidden";
  $result = sdsQuery($query);
  if(!$result)
    contactTech("Could not search movies");
  if(pg_num_rows($result)==1) {
    list($movieid) = pg_fetch_row($result);
  }
  pg_free_result($result);
}
if(!isset($movieid)) {
  echo "<h2 class='error'>No movie specified!</h2>\n";
  sdsIncludeFooter();
  exit;
}

if(isset($_REQUEST['changeinstance'])) {
# check out a different copy
  $query = <<<ENDQUERY
SELECT instanceid,box_number
FROM movie_instances
WHERE movieid=$movieid AND NOT checked_out AND NOT hidden
ORDER BY to_number(substring(box_number FROM '^[[:digit:]]+'),'9999999999'),
         box_number
ENDQUERY;
  $result = sdsQuery($query);
  if(!$result)
    contactTech("Could not search movies");

  echo "<h2>Select a copy:</h2>\n";
  echo "<ul>\n";
  while($record = pg_fetch_array($result)) {
    echo "  <li><a href='",
      sdsLink("checkout.php",
	      "movieid=$movieid&amp;instanceid=".$record['instanceid']),"'>",
      isset($record['box_number']) ?
      "Copy #" . htmlspecialchars($record['box_number']) :
      'Unnumbered Copy',"</a></li>\n";
  }
  echo "</ul>\n";
} else {
# normal operation
  $query = "SELECT instanceid FROM movie_instances WHERE movieid=$movieid AND NOT checked_out AND NOT hidden ORDER BY instanceid";
  $result = sdsQuery($query);
  if(!$result)
    contactTech("Could not search movies");

  if(pg_num_rows($result)==0) {
    echo "<h2 class=\"error\">There are no copies of that available!</h2>\n";
    sdsIncludeFooter();
    exit;
  }

  function getinstanceid($arr) { return $arr['instanceid']; }
  $instances = pg_fetch_all($result);
  $instances = array_map('getinstanceid',$instances);
  pg_free_result($result);
  if(!strlen($instanceid) or !in_array($instanceid,$instances)) {
    $instanceid = $instances[0];
  }

  $query = <<<ENDQUERY
SELECT title,num_disks,box_number,typename
FROM movie_items JOIN movie_instances USING (movieid)
                 JOIN movie_types USING (typeid)
WHERE instanceid=$instanceid
ENDQUERY;
  $result = sdsQuery($query);
  if(!$result or pg_num_rows($result) != 1)
    contactTech("Could not search movies");
  $movieinfo = pg_fetch_array($result);
  pg_free_result($result);

  echo "<h1>",htmlspecialchars($movieinfo['typename'])," ",
    htmlspecialchars($movieinfo['box_number']),": ",
    htmlspecialchars($movieinfo['title']),
    ($movieinfo['num_disks']>0 ? $movieinfo['num_disks']>1 ?
     (" (".$movieinfo['num_disks']." disks)") : " (1 disk)" : ""),
    "</h1>\n";

  if(!empty($_REQUEST['checkout'])) {
# check the item out!
    $checkout = sdsSanitizeString($_REQUEST['checkout']);

# one per customer
    $query = "SELECT 1 FROM movie_loans JOIN movie_instances USING (instanceid) WHERE checkin IS NULL AND NOT hidden AND username='$checkout'";
    $result = sdsQuery($query);
    if(!$result)
      contactTech("Could not search loans");
    if(pg_num_rows($result)) {
      echo "<h1 class='error'>",sdsGetFullName($checkout),
	" already has something checked out</h2>\n";
      echo "<p><a href='",sdsLink("list.php"),
	"'>Return to movie list</a></p>\n";
      sdsIncludeFooter();
      exit;
    }
    pg_free_result($result);

# residents only
    $query = "SELECT 1 FROM sds_group_membership_cache WHERE username='$checkout' AND groupname='RESIDENTS'";
    $result = sdsQuery($query);
    if(!$result)
      contactTech("Could not check residence");
    if(pg_num_rows($result) == 0) {
      echo "<h1 class='error'>",sdsGetFullName($checkout),
	" does not appear to live here.</h2>\n";
      echo "<p><a href='",sdsLink("list.php"),
	"'>Return to movie list</a></p>\n";
      sdsIncludeFooter();
      exit;
    }
    pg_free_result($result);

    $query = "INSERT INTO movie_loans (instanceid,username,checkout,checkout_by,movieid_ref) VALUES ($instanceid,'$checkout',now(),'".pg_escape_string($session->username)."',$movieid);";
    $query .= "UPDATE movie_instances SET checked_out=true WHERE instanceid=$instanceid;";
    $result = sdsQuery($query);
    if(!$result or pg_affected_rows($result) != 1)
      contactTech("Checkout failed");
    echo "<h2>Checked out to ",sdsGetFullName($checkout),"</h2>\n";
    echo "<p><a href='",sdsLink("list.php"),
      "'>Return to movie listing.</a></p>\n";
  } else {
# find out who to check out to

    if(count($instances)>1) {
      echo "<p><a href='",
	sdsLink("checkout.php","movieid=$movieid&amp;changeinstance=1"),
	"'>Select a different copy</a></p>\n";
    }

    if($finduser = doDirectorySearch()) {
# done a search: show results
      showDirectorySearchResults($finduser,"checkout.php","checkout",
				 array("movieid" => $movieid,
				       "instanceid" => $instanceid));
    }
    echo "<p>Check out to:</p>\n";
    showDirectorySearchForm("checkout.php",
			    array("movieid" => $movieid,
				  "instanceid" => $instanceid));
  }
}
sdsIncludeFooter();
