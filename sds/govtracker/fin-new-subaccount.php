<?php
require_once("../sds.php");
sdsRequireGroup('FINANCIAL-ADMINS');
require_once('fin-util.inc.php');

$acctid = (int) $_REQUEST['acctid'];
$query = <<<ENDQUERY
SELECT name,SUM(subtotal) AS balance,
       SUM(CASE WHEN isclosed THEN subtotal ELSE -allocationamt END)
                                                               AS unallocated
FROM gov_fin_accounts LEFT JOIN
     (SELECT acctid,(closedby IS NOT NULL OR NOT isallocation) AS isclosed,
             allocationamt,
             CAST(SUM(CASE WHEN voidedby IS NULL THEN COALESCE(amount,0)
                           ELSE 0 END) AS decimal(10,2)) AS subtotal
      FROM gov_fin_subaccounts LEFT JOIN gov_fin_ledger USING (acctid,subid)
      WHERE acctid='$acctid'
      GROUP BY acctid,subid,isclosed,allocationamt) AS stuff USING (acctid)
WHERE acctid='$acctid'
GROUP BY name
ENDQUERY;
$result = sdsQuery($query);
if(!$result)
  contactTech("Could not search accounts");
if(pg_num_rows($result) != 1) {
  sdsIncludeHeader("GovTracker","Simmons Finances Online");
  echo "<h2 class='error'>No such account</h2>\n";
  include('gt-footer.php');
  sdsIncludeFooter();
  exit;
}
$acctdata = pg_fetch_object($result);
pg_free_result($result);

$complaints = array();
$values = array('name' => '','shortname' => '','allocationamt' => '');
if(isset($_REQUEST['name'])) {
  $values['name'] = getStringArg('name');
  if($values['name'] === '')
    $complaints[] = 'Please provide a subaccount name';

  $values['shortname'] = getStringArg('shortname');
  if($values['shortname'] === '')
    $complaints[] = 'Please provide a short name';

  $values['allocationamt'] = getStringArg('allocationamt');
  if(!preg_match('/^\d+(?:\.(?:\d\d)?)?$/',$values['allocationamt']))
    $complaints[] = 'Allocation amount does not look like a money amount';

  if(count($complaints) == 0) {
    $values['acctid'] = $acctid;
    $values['created'] = 'now';
    $values['byuser'] = $session->username;
    $query = "INSERT INTO gov_fin_subaccounts " . sqlArrayInsert($values);
    $result = sdsQuery($query);
    if(!$result or pg_affected_rows($result) != 1)
      contactTech("Could not create subaccount");
    pg_free_result($result);
    header("Location: " . SDS_BASE_URL .
	   sdsLink('govtracker/fin-subacct-summary.php',
		   "acctid=$acctid",true));
    exit;
  }
}

sdsIncludeHeader("GovTracker","Simmons Finances Online");

foreach($complaints as $complaint) {
  echo "<p class='error'>",$complaint,"</p>\n";
}

echo "<form action='fin-new-subaccount.php' method='post'>\n";
echo sdsForm();
echo "<input type='hidden' name='acctid' value='",$acctid,"' />\n";
?>

<table class='fin-create'>
  <tr>
    <td>Submitter:</td>
    <td><?php echo htmlspecialchars($session->username) ?></td>
  </tr>
  <tr>
    <td>Parent Account:</td>
<?php
echo "    <td>",htmlspecialchars($acctdata->name),"<br />\n";
echo "      <span style='font-size:small'>\n";
echo "        This account has a balance of <span class='",
  moneyclass($acctdata->balance),"'>",htmlspecialchars($acctdata->balance),
  "</span>\n";
echo "        and has <span class='",moneyclass($acctdata->unallocated),"'>",
  htmlspecialchars($acctdata->unallocated),"</span> unallocated.\n";
?>
      </span>
    </td>
  </tr>
  <tr>
    <td>Subaccount Title:</td>
    <td><input type='text' name='name' size='45' value='<?php echo htmlspecialchars($values['name']) ?>' /></td>
  </tr>
  <tr>
    <td>Short Name:</td>
    <td><input type='text' name='shortname' size='20' value='<?php echo htmlspecialchars($values['shortname']) ?>' /></td>
  </tr>
  <tr>
    <td>Allocation:</td>
    <td>$<input type='text' name='allocationamt' size='10' value='<?php echo htmlspecialchars($values['allocationamt']) ?>' /></td>
  </tr>
</table>

<p>All data will be publicly viewable.</p>

<input type='submit' value='Create Subaccount' />
</form>

<?php
include('gt-footer.php');
sdsIncludeFooter();
