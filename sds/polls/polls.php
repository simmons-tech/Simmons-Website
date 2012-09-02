<?php 
require_once("../sds.php");
sdsRequireGroup("USERS");

sdsIncludeHeader("Simmons Polls");

$username = $session->username;
$username_esc = pg_escape_string($username);

////////
// Open Polls
////////

if(!empty($session->groups['ADMINISTRATORS'])) {
  $restrict = "true";
} else {
  // normal people shouldn't get to see unapproved polls
  $restrict = "approved";
}

$query = <<<ENDQUERY
SELECT pollid,pollname,owner,
       to_char(open_date,'FMDD Mon YYYY HH24:MI') AS open_str,
       to_char(close_date,'FMDD Mon YYYY HH24:MI') AS close_str,
       approved,groupname,viewable,description
FROM polls
WHERE (now() >= open_date OR open_date IS NULL) AND
      (now() < close_date OR close_date IS NULL) AND
      ($restrict OR owner='$username_esc') AND NOT deleted
ORDER BY close_date,open_date
ENDQUERY;

$result = sdsQuery($query);
if(!$result)
  contactTech("Could not search polls");

echo "<h2>Open Polls</h2>\n\n";
if(pg_num_rows($result)==0) {
  echo "<p>No open polls available.</p>\n";
  echo "<p><a href='",sdsLink("create.php",""),"'>[ create poll ]</a></p>\n";
} else {
?>
<table class="openpolls">
  <tr>
    <th>Poll Name</th>
    <th>Owner</th>
    <th>Open</th>
    <th>Close</th>
    <th>Actions</th>
  </tr>
<?php

  while($row = pg_fetch_array($result)) {
    $pollid = $row['pollid'];
    echo "  <tr>\n";
    echo "    <td>",htmlspecialchars($row["pollname"]),"</td>\n";
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
      $voted = false;
      $votedquery = "SELECT 1 FROM ballots WHERE pollid=$pollid AND username='$username_esc'";
      $voteresult = sdsQuery($votedquery);
      if(!$voteresult)
	contactTech("Could not search ballots");
      $voted = (pg_num_rows($voteresult)==1);
      pg_free_result($voteresult);

      if($session->groups[$row['groupname']]) {
	if($voted) {
	  if($row['viewable']==='t') {
	    $actions .= "      <a href='".sdsLink("view.php","poll=$pollid").
	      "'>[ view ]</a>\n";
	  }
	} else {
	  $actions .= "      <a href='".sdsLink("vote.php","poll=$pollid").
	    "'><b>[ vote ]</b></a>\n";
	}
      }

      if($username === $row['owner'] or
	 !empty($session->groups['ADMINISTRATORS'])) {
	$actions .= "      <a href='".
	  sdsLink("admin.php","action=close&amp;poll=$pollid").
	  "'>[ close ]</a>\n";
      }
    } elseif($session->groups['ADMINISTRATORS']) {
      $actions .= "      <a href='".
        sdsLink("admin.php","action=approve&amp;poll=$pollid").
        "'>[ approve ]</a>\n";
    }

    if($username === $row['owner'] or
       !empty($session->groups['ADMINISTRATORS'])) {
      $actions .= "      <a href='".
        sdsLink("admin.php","action=delete&amp;poll=$pollid").
        "'>[ delete ]</a>\n";
      if($row['approved']!=='t') {
	if($row['viewable']==='t') {
	  $actions .= "      <a href='".
	    sdsLink("admin.php","action=makeunviewable&amp;poll=$pollid").
	    "'>[ make unviewable ]</a>\n";
	} else {
	  $actions .= "      <a href='".
	    sdsLink("admin.php","action=makeviewable&amp;poll=$pollid").
	    "'>[ make viewable ]</a>\n";
	}
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
    "'>[ create poll ]</a></p>\n";
}
pg_free_result($result);
 
////////
// Closed Polls
////////

if(!empty($session->groups['ADMINISTRATORS'])) {
  $restrict = "true";
} else {
  // normal people shouldn't get to see unapproved polls
  $restrict="(approved AND viewable)";
}

$query = <<<ENDQUERY
SELECT pollid,pollname,owner,
       to_char(open_date,'FMDD Mon YYYY HH24:MI') AS open_str,
       to_char(close_date,'FMDD Mon YYYY HH24:MI') AS close_str,
       approved,groupname,viewable,description
FROM polls
WHERE (now() < open_date OR now() >= close_date) AND
      ($restrict OR owner='$username_esc') AND NOT deleted
ORDER BY close_date DESC,open_date DESC
ENDQUERY;

$result = sdsQuery($query);
if(!$result)
  contactTech("Could not search polls");

echo "\n\n\n<h2>Closed Polls</h2>\n\n";
if(pg_num_rows($result)==0) {
  echo "<p>No polls are closed.</p>\n";
} else {
?>
<table class="closedpolls">
  <tr>
    <th>Poll Name</th>
    <th>Owner</th>
    <th>Open</th>
    <th>Close</th>
    <th>Actions</th>
  </tr>
<?php

  while($row = pg_fetch_array($result)) {
    $pollid = $row['pollid'];
    echo "  <tr>\n";
    echo "    <td>",htmlspecialchars($row["pollname"]),"</td>\n";
    echo "    <td>",htmlspecialchars($row["owner"]),"</td>\n";
    echo "    <td>",htmlspecialchars($row['open_str']),"</td>\n";
    echo "    <td>",htmlspecialchars($row['close_str']),"</td>\n";

    $actions = "";
    if($row['approved'] !== 't') {
      if($session->groups['ADMINISTRATORS']) {
	$actions .= "      <a href='".
	  sdsLink("admin.php","action=approve&amp;poll=$pollid").
	  "'>[ approve ]</a>\n";
      }
    } elseif(($row['viewable'] === 't' and
	      !empty($session->groups[$row['groupname']])) or
	     $username === $row['owner'] or
	     !empty($session->groups['ADMINISTRATORS'])) {
      $actions .= "      <a href='".
        sdsLink("view.php","poll=$pollid").
        "'><b>[ view ]</b></a>\n";
    }

    if($username === $row['owner'] or
       !empty($session->groups['ADMINISTRATORS'])) {
      $actions .= "      <a href='".
        sdsLink("admin.php","action=delete&amp;poll=$pollid").
        "'>[ delete ]</a>\n";
      if($row['viewable']==='t') {
	$actions .= "      <a href='".
	  sdsLink("admin.php","action=makeunviewable&amp;poll=$pollid").
	  "'>[ make unviewable ]</a>\n";
      } else {
	$actions .= "      <a href='".
	  sdsLink("admin.php","action=makeviewable&amp;poll=$pollid").
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
