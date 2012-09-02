<?php 
require_once("../sds.php");
sdsRequireGroup("USERS");

sdsIncludeHeader("Simmons Lotteries");

$username = $session->username;
$username_esc = pg_escape_string($username);

////////
// Open Lotteries
////////

if(!empty($session->groups['ADMINISTRATORS'])) {
  $restrict = "true";
} else {
  // normal people shouldn't get to see unapproved lotteries
  $restrict = "approved";
}

$query = <<<ENDQUERY
SELECT lotteryid,lotteryname,owner,
       to_char(open_date,'FMDD Mon YYYY HH24:MI') AS open_str,
       to_char(close_date,'FMDD Mon YYYY HH24:MI') AS close_str,
       approved,groupname,viewable,description
FROM lotteries
WHERE (now() >= open_date OR open_date IS NULL) AND
      (now() < close_date OR close_date IS NULL) AND
      ($restrict OR owner='$username_esc') AND NOT deleted
ORDER BY close_date,open_date
ENDQUERY;

$result = sdsQuery($query);
if(!$result)
  contactTech("Could not search lotteries");

echo "<h2>Open Lotteries</h2>\n\n";
if(pg_num_rows($result)==0) {
  echo "<p>No open lotteries available.</p>\n";
  echo "<p><a href='",sdsLink("create.php",""),
    "'>[ create lottery ]</a></p>\n";
} else {
?>
<table class="openpolls">
  <tr>
    <th>Lottery Name</th>
    <th>Owner</th>
    <th>Open</th>
    <th>Close</th>
    <th>Actions</th>
  </tr>
<?php

  while($row = pg_fetch_array($result)) {
    $lotteryid = $row['lotteryid'];
    echo "  <tr>\n";
    echo "    <td>",htmlspecialchars($row["lotteryname"]),"</td>\n";
    echo "    <td>",sdsGetFullName($row["owner"]),"</td>\n";
    if(isset($row['open_str'])) {
      echo "    <td>",htmlspecialchars($row['open_str']),"</td>\n";
    } else {
      echo "    <td>On Approval</td>\n";
    }
    if(isset($row['close_str'])) {
      echo "    <td>",htmlspecialchars($row['close_str']),"</td>\n";
    } else {
      echo "    <td>none</td>\n";
    }

    $actions = "";

    if($row['approved']==='t') {
      $entered = false;
      $enteredquery = "SELECT 1 FROM lottery_entries WHERE lotteryid=$lotteryid AND username='$username_esc'";
      $enteredresult = sdsQuery($enteredquery);
      if(!$enteredresult)
	contactTech("Could not search lottery entries");
      $entered = (pg_num_rows($enteredresult)==1);
      pg_free_result($enteredresult);

      if(!empty($session->groups[$row['groupname']])) {
	if($entered) {
	  $actions .= "      <a href='".
	    sdsLink("enter.php","lottery=$lotteryid&amp;rescind=1").
	    "'><b>[ un-enter ]</b></a>\n";
	} else {
	  $actions .= "      <a href='".
	    sdsLink("enter.php","lottery=$lotteryid").
	    "'><b>[ enter ]</b></a>\n";
	}
      }

      if($username === $row['owner'] or
	 !empty($session->groups['ADMINISTRATORS'])) {
	$actions .= "      <a href='".
	  sdsLink("admin.php","action=close&amp;lottery=$lotteryid").
	  "'>[ close ]</a>\n";
      }

    } elseif(!empty($session->groups['ADMINISTRATORS'])) {
      $actions .= "      <a href='".
        sdsLink("admin.php","action=approve&amp;lottery=$lotteryid").
        "'>[ approve ]</a>\n";
    }

    if(!empty($session->groups['ADMINISTRATORS'])) {
      $actions .= "      <a href='".
        sdsLink("admin.php","action=delete&amp;lottery=$lotteryid").
        "'>[ delete ]</a>\n";
    }

    if($username === $row['owner'] or
       !empty($session->groups['ADMINISTRATORS'])) {
      if($row['viewable']==='t') {
	$actions .= "      <a href='".
	  sdsLink("admin.php","action=makeunviewable&amp;lottery=$lotteryid").
	  "'>[ make unviewable ]</a>\n";
      } else {
	$actions .= "      <a href='".
	  sdsLink("admin.php","action=makeviewable&amp;lottery=$lotteryid").
	  "'>[ make viewable ]</a>\n";
      }
    }

    if($actions === "") { $actions = "      none\n"; }
    echo "    <td>\n";
    echo $actions;
    echo "    </td>\n";
    echo "  </tr>\n";
    echo "  <tr>\n";
    echo "    <td class='description' colspan='5'>",
      htmlspecialchars($row["description"]),"</td>\n";
    echo "  </tr>";
  }
  echo "</table>\n";
  echo "<p style='text-align: center'><a href='",sdsLink("create.php",""),
    "'>[ create lottery ]</a></p>\n";
}
pg_free_result($result);
 
////////
// Closed Lotteries
////////

$query = <<<ENDQUERY
SELECT lotteryid,lotteryname,owner,
       to_char(open_date,'FMDD Mon YYYY HH24:MI') AS open_str,
       to_char(close_date,'FMDD Mon YYYY HH24:MI') AS close_str,
       approved,groupname,viewable,description
FROM lotteries
WHERE (now() < open_date OR now() >= close_date) AND
      ($restrict OR owner='$username_esc') AND NOT deleted
ORDER BY close_date DESC,open_date DESC
ENDQUERY;

$result = sdsQuery($query);
if(!$result)
  contactTech("Could not search lotteries");

echo "\n\n\n<h2>Closed Lotteries</h2>\n\n";
if(pg_num_rows($result)==0) {
  echo "<p>No lotteries are closed.</p>\n";
} else {
?>
<table class="closedpolls">
  <tr>
    <th>Lottery Name</th>
    <th>Owner</th>
    <th>Open</th>
    <th>Close</th>
    <th>Actions</th>
  </tr>
<?php

  while($row = pg_fetch_array($result)) {
    $lotteryid = $row['lotteryid'];
    echo "  <tr>\n";
    echo "    <td>",htmlspecialchars($row["lotteryname"]),"</td>\n";
    echo "    <td>",htmlspecialchars($row["owner"]),"</td>\n";
    echo "    <td>",htmlspecialchars($row['open_str']),"</td>\n";
    echo "    <td>",htmlspecialchars($row['close_str']),"</td>\n";

    $actions = "";
    if($row['approved'] !== 't') {
      if($session->groups['ADMINISTRATORS']) {
	$actions .= "      <a href='".
	  sdsLink("admin.php","action=approve&amp;lottery=$lotteryid").
	  "'>[ approve ]</a>\n";
      }
    } elseif(($row['viewable'] === 't' and
	      !empty($session->groups[$row['groupname']])) or
	     $username === $row['owner'] or
	     !empty($session->groups['ADMINISTRATORS'])) {
      $actions .= "      <a href='".
        sdsLink("view.php","lottery=$lotteryid").
        "'><b>[ view ]</b></a>\n";
    }

    if(!empty($session->groups['ADMINISTRATORS'])) {
      $actions .= "      <a href='".
        sdsLink("admin.php","action=delete&amp;lottery=$lotteryid").
        "'>[ delete ]</a>\n";
    }

    if($username === $row['owner'] or
       !empty($session->groups['ADMINISTRATORS'])) {
      if($row['viewable']==='t') {
	$actions .= "      <a href='".
	  sdsLink("admin.php","action=makeunviewable&amp;lottery=$lotteryid").
	  "'>[ make unviewable ]</a>\n";
      } else {
	$actions .= "      <a href='".
	  sdsLink("admin.php","action=makeviewable&amp;lottery=$lotteryid").
	  "'>[ make viewable ]</a>\n";
      }
    }

    if($actions === "") { $actions = "      none\n"; }
    echo "    <td>\n";
    echo $actions;
    echo "    </td>\n";
    echo "  </tr>\n";
    echo "  <tr>\n";
    echo "    <td class='description' colspan='5'>",
      htmlspecialchars($row["description"]),"</td>\n";
    echo "  </tr>\n";
  }
 echo "</table>\n";
}
pg_free_result($result);

sdsIncludeFooter();
