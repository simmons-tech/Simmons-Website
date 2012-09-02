<?php
require_once("../sds.php");
sdsRequireGroup("FINANCIAL-ADMINS");

$subid = (int) $_REQUEST['subid'];
$query = "SELECT name,allocationamt FROM gov_fin_subaccounts WHERE subid='$subid' AND isallocation AND closedby IS NULL";
$result = sdsQuery($query);
if(!$result)
  contactTech("Could not search subaccounts");
if(pg_num_rows($result) != 1) {
  echo "<h2 class='error'>This subaccount cannot be modified</h2>\n";
  include('gt-footer.php');
  sdsIncludeFooter();
  exit;
}
$acctdata = pg_fetch_object($result);
pg_free_result($result);

unset($newamount);
if(isset($_REQUEST['allocationamt'])) {
  $newamount = trim($_REQUEST['allocationamt']);
  if(preg_match('/^\d+(?:\.(?:\d\d)?)?$/',$newamount)) {
    $query = "UPDATE gov_fin_subaccounts SET allocationamt = '$newamount' WHERE subid='$subid'";
    $result = sdsQuery($query);
    if(!$result or pg_affected_rows($result) != 1)
      contactTech("Could not update allocation");
    pg_free_result($result);
    header('Location: ' . SDS_BASE_URL .
	   sdsLink('govtracker/fin-subaccount.php',"subid=$subid",true));
    exit;
  }
}

sdsIncludeHeader("GovTracker","Simmons Finances Online");
?>

<h2>Subaccount Allocation Change</h2>

<h3><?php echo htmlspecialchars($acctdata->name) ?></h3>

<?php
if(isset($newamount)) {
  echo "<p class='error'>New allocation does not look like a money amount.</p>\n";
} else {
  $newamount = $acctdata->allocationamt;
}
?>

<form action='fin-allocate.php' method='post'>
<?php echo sdsForm() ?>
<input type='hidden' name='subid' value='<?php echo $subid ?>' />
<table>
  <tr>
    <td>Current allocation:</td>
    <td class='money'><?php echo htmlspecialchars($acctdata->allocationamt) ?></td>
  </tr>
  <tr>
    <td>New allocation:</td>
    <td class='money'><input type='text' name='allocationamt' size='8' value='<?php echo htmlspecialchars($newamount) ?>' /></td>
  </tr>
</table>
<input type='submit' value='Modify' />
</form>

<?php

require_once("gt-footer.php");
sdsIncludeFooter();
