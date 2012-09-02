<?php
require_once("../sds.php");
require_once("loungeexpense.inc.php");
sdsRequireGroup("USERS");
sdsIncludeHeader("Lounge Expense Proposal","Submit Decision");


$username_esc=pg_escape_string($session->username);

# find the lounge this user (hopefully) is a member of 
$eid = (int) $_REQUEST["eventid"];
$query = "SELECT loungeid FROM lounge_expenses WHERE expenseid=$eid";
$result = sdsQuery($query);
if(!$result)
  contactTech("Could not search lounge events");
if (pg_num_rows($result) < 1) {
  echo "<h2>No such event id: $eid</h2>\n";
  require("gt-footer.php");
  sdsIncludeFooter();
  exit;
}

list($loungeid) = pg_fetch_array($result);
pg_free_result($result);

if($session->groups[$loungeid]) {
  # they are in this lounge, see if they have already voted
  $query = "SELECT action FROM lounge_expense_actions WHERE expenseid=$eid AND username='$username_esc'";
  $result = sdsQuery($query);
  if(!$result)
    contactTech("Could not search event decisions");
  if(pg_num_rows($result)) {
    list($action) = pg_fetch_array($result);
    echo "<p>You already <b>",($action<2?'approved':'rejected'),
      "</b> this event and <b>",($action<1?'committed':'did not commit'),
      "</b> to attending.</p>\n";
  } elseif(isset($_REQUEST["submitdecision"])) {
    $decisiontype = (int) $_REQUEST['decisiontype'];
    if($decisiontype >= 0 and $decisiontype <= 2) {
      # all proposal input is valid, save it to the DB
      $query = "INSERT INTO lounge_expense_actions (expenseid,username,action) VALUES ($eid,'$username_esc',$decisiontype)";
      $result2 = sdsQuery($query);
      if($result2 and pg_affected_rows($result2) == 1) {
	# in case this is an old expense
	validate_expense($eid);
	echo "<h2>Done.</h2> <a href='proposals.php'>Return to proposals</a>\n";
      } else {
	contactTech("Database insert failed");
      }
      pg_free_result($result2);
    } else {
      echo "<h2 class='error'>Invalid decision</h2>\n";
    }
  } else {
    echo "<h2 class='error'>No input provided.</h2>\n";
  }
  pg_free_result($result);
} else {
  echo "<h2 class='error'>You are not a member!</h2>\n";
}

require("gt-footer.php");
sdsIncludeFooter();
