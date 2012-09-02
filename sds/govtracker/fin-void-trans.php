<?php
require_once("../sds.php");
sdsRequireGroup("FINANCIAL-ADMINS");

$username_esc = pg_escape_string($session->username);
$transid = $_REQUEST['transid'];

$query = <<<ENDQUERY
UPDATE gov_fin_ledger
SET voidedby = '$username_esc'
WHERE tid='$transid' AND voidedby IS NULL
RETURNING subid
ENDQUERY;
$result = sdsQuery($query);
if(!$result)
  contactTech("Could not void transaction");
if(pg_num_rows($result) != 1) {
  sdsIncludeHeader("GovTracker","Simmons Finances Online");
  echo "<h2 class='error'>Transaction is not voidable</h2>\n";
  include('gt-footer.php');
  sdsIncludeFooter();
  exit;
}
list($subid) = pg_fetch_array($result);
pg_free_result($result);
header('Location: ' . SDS_BASE_URL .
       sdsLink('govtracker/fin-subaccount.php',"subid=$subid",true));
