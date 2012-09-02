<?php
require_once("../sds.php");
sdsRequireGroup("HOUSE-COMM-LEADERSHIP");

$propid = (int) $_REQUEST['pid'];
$text = maybeStripslashes($_REQUEST['amendtext']);
$text_esc = pg_escape_string($text);
$log_esc = pg_escape_string("<li>Full text amended on " .
			    date("F j, Y, g:i a") . " to: <small>" .
			    nl2br(htmlspecialchars($text))."</small></li>");
$query = "UPDATE gov_proposals SET finalfulltext='$text_esc', record='$log_esc'||record WHERE propid='$propid' AND decision IS NULL";
$result = sdsQuery($query);
if(!$result)
  contactTech("Could not amend text");
pg_free_result($result);
header("Location: " . SDS_BASE_URL .
       sdsLink('govtracker/download-main.php',
	       "page=proposal&id=$propid",true));
