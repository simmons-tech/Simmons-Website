<?php
require_once("../sds.php");
require_once("loungeexpense.inc.php");
sdsRequireGroup("USERS");
sdsIncludeHeader("Lounge Expense Proposal","Submit Final Expense");

$username=$session->username;

# find the lounge this user (hopefully) represents
$eid = (int) $_REQUEST["eventid"];
$query =
  "SELECT usersub FROM lounge_expenses WHERE expenseid=$eid AND NOT finished";
$result = sdsQuery($query);
if(!$result)
  contactTech("Could not search events");
if(pg_num_rows($result) < 1) {
  print "<h2>No such event id: $eid</h2>\n";
  require("gt-footer.php");
  sdsIncludeFooter();
  exit;
}
list($usersub) = pg_fetch_array($result);
pg_free_result($result);
if($usersub !== $username) {
  echo "<h2>You cannot finalize expenses for this event.</h2>\n";
  require("gt-footer.php");
  sdsIncludeFooter();
  exit;
}

if($_REQUEST["finalizeexpense"]) {
  # yes, try validating the submission
    $famt = maybeStripslashes($_REQUEST['amt']);
  if(!preg_match('/^\d+(?:\.(?:\d\d)?)?$/',$famt)) {
    echo "<p>",htmlspecialchars($famt),
      " does not look like a dollar amount.</p>\n";
    require("gt-footer.php");
    sdsIncludeFooter();
    exit;
  }
  $fnum = (int) $_REQUEST['attendnum'];

  # all proposal input is valid, save it to the DB
  $query = "UPDATE lounge_expenses SET finished = true, datesubmitted = now(), numparticipated = $fnum, amountspent = '$famt' WHERE expenseid = $eid";
  $result = sdsQuery($query);
  if($result and pg_affected_rows($result) == 1) {
    echo "<h2>Done.</h2>\n";
    if(!validate_expense($eid)) {
      echo "<p>NOTE: This event does not currently comply with the lounge bylaws and cannot be reimbursed.</p>\n";
    }
    echo "<p><a href='index.php'>Return to expenses</a></p>\n"; 
  } else {
    contactTech("Finalization failed");
  }
  pg_free_result($result);
} else {
  echo "<h2 class='error'>No request.</h2>\n";
}

require("gt-footer.php");
sdsIncludeFooter();
