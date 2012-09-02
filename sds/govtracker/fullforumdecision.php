<?php
require_once("../sds.php");
sdsRequireGroup("HOUSE-COMM-LEADERSHIP");
$propid = (int) $_REQUEST["pid"];

if($_REQUEST['decision'] === "r") {
  $decision = "REJECTED BY FULL FORUM";
} elseif($_REQUEST['decision'] === "a") {
  $decision = "APPROVED BY FULL FORUM";
} else {
  sdsIncludeHeader("Full Forum Approval");
  echo "<h2 class='error'>Illegal decision.</h2>\n";
  include('gt-footer.php');
  sdsIncludeFooter();
  return;
}

$query = "SELECT 1 FROM gov_active_proposals WHERE propid='$propid' AND decision='MOVED TO FULL FORUM'";

$result = sdsQuery($query);
if(!$result)
  contactTech("Could not search proposals");

if(pg_num_rows($result) != 1) {
  sdsIncludeHeader("Full Forum Approval");
  echo "<h2 class='error'>Full Forum decision failed</h2>\n";
  echo "<p>This proposal either does not exist or is not in full forum.</p>\n";
  include('gt-footer.php');
  sdsIncludeFooter();
  return;
}
pg_free_result($result);

$log_esc = pg_escape_string("<li>$decision on ".date("F j, Y, g:i a")."</li>");
$query = "UPDATE gov_proposals SET decision='$decision', record='$log_esc'||record WHERE propid = $propid";
$result = sdsQuery($query);
if(!$result or pg_affected_rows($result) != 1)
  contactTech("Could not decide proposal");
pg_free_result($result);

header("Location: " . SDS_BASE_URL .
       sdsLink('govtracker/viewproposal.php',"pid=$propid",true));
return;
