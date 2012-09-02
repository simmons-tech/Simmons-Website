<?php
require_once("../sds.php");
sdsRequireGroup("HOUSE-COMM-LEADERSHIP");

$propid = (int) $_REQUEST['pid'];

$decision = maybeStripslashes($_REQUEST['decisiontype']);
$decision_esc = pg_escape_string($decision);

$log_esc = pg_escape_string("<li>$decision on " . date("F j, Y, g:i a"));
$query = "UPDATE gov_proposals SET decision='$decision_esc', record='$log_esc'||record WHERE propid='$propid' AND decision IS NULL";
$result = sdsQuery($query);
if(!$result)
  contactTech("Could not set decision");
pg_free_result($result);

header("Location: " . SDS_BASE_URL .
       sdsLink('govtracker/download-main.php',
	       "page=proposal&id=$propid",true));
