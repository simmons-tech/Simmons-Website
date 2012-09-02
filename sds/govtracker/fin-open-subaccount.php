<?php
require_once("../sds.php");
sdsRequireGroup("FINANCIAL-ADMINS");

$username_esc = pg_escape_string($session->username);
$subid = (int) $_REQUEST['subid'];
$query = "UPDATE gov_fin_subaccounts SET closedby = null WHERE subid='$subid' AND isallocation AND closedby IS NOT NULL";
$result = sdsQuery($query);
if(!$result)
  contactTech("Could not reopen allocation");
if(pg_affected_rows($result) != 1) {
  sdsIncludeHeader("GovTracker","Simmons Finances Online");
  echo "<h2 class='error'>Account is not openable</h2>\n";
  include('gt-footer.php');
  sdsIncludeFooter();
  exit;
}
pg_free_result($result);
header('Location: ' . SDS_BASE_URL .
       sdsLink('govtracker/fin-subaccount.php',"subid=$subid",true));
