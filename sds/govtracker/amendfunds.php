<?php
require_once("../sds.php");
sdsRequireGroup("HOUSE-COMM-LEADERSHIP");

$propid = (int) $_REQUEST['pid'];
$funds = maybeStripslashes($_REQUEST['amendfunds']);
if(preg_match('/^\d+(?:\.(?:\d\d)?)?$/',$funds)) {

  $log_esc = pg_escape_string("<li>Funding amount amended to $funds on " .
			      date("F j, Y, g:i a"));
  $query = "UPDATE gov_proposals SET finalfunds='$funds',record='$log_esc'||record WHERE propid='$propid' AND decision IS NULL";
  $result = sdsQuery($query);
  if(!$result)
    contactTech("Could not update funds");
  pg_free_result($result);
}
header("Location: " . SDS_BASE_URL .
       sdsLink('govtracker/download-main.php',
	       "page=proposal&id=$propid",true));
