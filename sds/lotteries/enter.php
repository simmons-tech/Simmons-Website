<?php 
require_once("../sds.php");
sdsRequireGroup("USERS");

sdsIncludeHeader("Lottery Entry");

$username = $session->username;
$username_esc = pg_escape_string($username);
$lottery = (int) $_REQUEST['lottery'];

$query = <<<ENDQUERY
SELECT lotteryname,groupname
FROM lotteries
WHERE lotteryid = $lottery AND approved AND NOT deleted AND
      open_date<now() AND (close_date IS NULL OR close_date>now())
ENDQUERY;

$result = sdsQuery($query);
if(!$result)
  contactTech("Could not search lotteries");
$lotteryexists = (pg_num_rows($result) == 1);
if($lotteryexists) {
  $lotteryrecord = pg_fetch_array($result);
}
pg_free_result($result);

$query = "SELECT 1 FROM lottery_entries WHERE username='$username_esc' AND lotteryid=$lottery";
$result = sdsQuery($query);
if(!$result)
  contactTech("Cannot search lottery entries");
$entered = (pg_num_rows($result) == 1);
pg_free_result($result);

if(!$lotteryexists) {
  echo "<p>Sorry, this lottery is closed.</p>\n";
} elseif(empty($session->groups[$lotteryrecord["groupname"]])) {
  echo "<p>You do not have the priveleges to enter this lottery.</p>\n";
} elseif(!empty($_REQUEST['rescind'])) {
  if(!$entered) {
    echo "<p>Sorry, you haven't entered this lottery.</p>\n";
  } else {
    $query = "DELETE FROM lottery_entries WHERE lotteryid=$lottery AND username='$username_esc'";
    $result = sdsQuery($query);
    if(!$result or pg_affected_rows($result) != 1)
      contactTech("Could not remove entry");
    pg_free_result($result);

    echo "<p>You are no longer entered in the lottery <b>",
      htmlspecialchars($lotteryrecord['lotteryname']),"</b></p>\n";
  }
} else {
  if($entered) {
    echo "<p>Sorry, you've already entered this lottery.</p>\n";
  } else {
    $query = "INSERT INTO lottery_entries (lotteryid,username,date_entered) VALUES ($lottery,'$username_esc',now())";
    $result = sdsQuery($query);
    if(!$result or pg_affected_rows($result) != 1)
      contactTech("Could not record entry");
    pg_free_result($result);

    echo "<p>You are now entered in the lottery <b>",
      htmlspecialchars($lotteryrecord['lotteryname']),"</b></p>\n";
  }
}

echo "<p><a href='",sdsLink("./"),"'>[ Back to Lotteries ]</a></p>\n";

sdsIncludeFooter();
