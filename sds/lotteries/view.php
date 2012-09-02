<?php 
require_once("../sds.php");
sdsRequireGroup("USERS");

sdsIncludeHeader("View Lottery");

$username = $session->username;
$username_esc = pg_escape_string($username);
$lottery = (int) $_REQUEST['lottery'];

$query = <<<ENDQUERY
SELECT lotteryname,description,groupname,owner,viewable,
       (open_date<now() AND (close_date IS NULL OR close_date>now())) AS open
FROM lotteries
WHERE lotteryid = $lottery AND approved AND NOT deleted
ENDQUERY;
$result = sdsQuery($query);
if(!$result)
  contactTech("Could not search lotteries");

if(pg_num_rows($result) != 1) {
  echo "<h2 class='error'>No such lottery</h2>\n";
  sdsIncludeFooter();
  exit;
}

$lotteryrecord = pg_fetch_array($result);
pg_free_result($result);

$canview = false;

# voters can view if viewable
if($lotteryrecord["viewable"] === 't' and
   !empty($session->groups[$lotteryrecord["groupname"]]))
  $canview = true;

# admin and owner can view
if($username === $lotteryrecord['owner'] or
   !empty($session->groups['ADMINISTRATORS']))
  $canview = true;

# but not if the lottery is over
if($lotteryrecord['open'] === 't')
  $canview = false;

if($canview) {
  echo "<h2>Results for lottery: ",
    htmlspecialchars($lotteryrecord['lotteryname']),"</h2>\n";
  echo "<p class='polldescription'>",
    htmlspecialchars($lotteryrecord["description"]),"</p>\n";

  $query = "SELECT rank,username,email FROM lottery_entries LEFT JOIN directory USING (username) WHERE lotteryid=$lottery ORDER BY rank";
  $result = sdsQuery($query);
  if(!$result)
    contactTech("Could not search lottery entries");

  if(pg_num_rows($result) == 0) {
    echo "<p>Sorry, no one entered this lottery.</p>\n";
  } else {
    if(pg_fetch_object($result)->rank == 0) {
      # Lottery hasn't run.  Do that.
      pg_result_seek($result,0);

      $entries = array();
      while($record = pg_fetch_object($result)) {
	$entries[] = $record->username;
      }
      pg_free_result($result);

      shuffle($entries);
      for($i=0;$i<count($entries);$i++) {
	$entry_esc = pg_escape_string($entries[$i]);
	$query = "UPDATE lottery_entries SET rank=$i+1 WHERE lotteryid=$lottery AND username='$entry_esc' AND rank IS NULL";
	$result = sdsQuery($query);
	if(!$result or pg_affected_rows($result) != 1)
	  contactTech("Running lottery failed");
	pg_free_result($result);
      }

      # And pull the result set again
      $query = "SELECT rank,username,email FROM lottery_entries LEFT JOIN directory USING (username) WHERE lotteryid=$lottery ORDER BY rank";
      $result = sdsQuery($query);
      if(!$result)
	contactTech("Could not search lottery entries");
    }
    pg_result_seek($result,0);

    echo "<table class='lotteryresults'>\n";
    while($record = pg_fetch_object($result)) {
      echo "  <tr>\n";
      echo "    <td>",$record->rank,"</td>\n";
      echo "    <td>",sdsGetFullName($record->username)," (<a href='mailto:",
	htmlspecialchars($record->email),"'>",
	htmlspecialchars($record->email),"</a>)</td>\n";
      echo "  </tr>\n";
    }
    echo "</table>\n";
    pg_free_result($result);
  }
} else {
  # Cannot view, say why
  if($username !== $lotteryrecord['owner'] and
     empty($session->groups[$lotteryrecord['groupname']]) and
     empty($session->groups['ADMINISTRATORS'])) {
    echo "<p>You do not have the priveleges to view this lottery.</p>\n";
  } elseif($lotteryrecord['open'] === 't') {
    echo "<p>This lottery is not over yet.</p>\n";
  } else {
    echo "<p>You are not permitted to view this lottery.</p>\n";
  }
}

echo  "<p><a href='".sdsLink("./","")."'>[ Back to Lotteries ]</a></p>\n";

sdsIncludeFooter();
