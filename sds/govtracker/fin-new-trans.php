<?php
require_once("../sds.php");
sdsRequireGroup("FINANCIAL-ADMINS");
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
$values = array('subid' => null,'name' => '','amount' => '');

if(isset($_REQUEST['subid'])) {
  $values['subid'] = (int) $_REQUEST['subid'];
  $query = "SELECT 1 FROM gov_fin_subaccounts WHERE subid='".$values['subid'].
    "' AND acctid='$acctid' AND closedby IS NULL";
  $result = sdsQuery($query);
  if(!$result)
    contactTech("Could not search subaccounts");
  if(pg_num_rows($result) != 1) {
    unset($values['subid']);
    $complaints[] = 'Bad subaccount.';
  }
}
if(!isset($values['subid'])) {
  $query = "SELECT subid FROM gov_fin_subaccounts WHERE acctid='$acctid' AND NOT isallocation ORDER BY subid LIMIT 1";
  $result = sdsQuery($query);
  if(!$result or pg_num_rows($result) != 1)
    contactTech("Could not find default subaccount");
  list($values['subid']) = pg_fetch_array($result);
  pg_free_result($result);
}

if(isset($_REQUEST['name'])) {
  $values['name'] = trim(maybeStripslashes($_REQUEST['name']));
  if($values['name'] === '')
    $complaints[] = 'Please provide a description';

  $values['amount'] = trim(maybeStripslashes($_REQUEST['amount']));
  if(!preg_match('/^-?\d+(?:\.(?:\d\d)?)?$/',$values['amount']))
    $complaints[] = 'Amount does not look like a money amount';

  if(count($complaints) == 0) {
    $values['acctid'] = $acctid;
    $values['submitted'] = 'now';
    $values['byuser'] = $session->username;
    $query = "INSERT INTO gov_fin_ledger " . sqlArrayInsert($values);
    $result = sdsQuery($query);
    if(!$result or pg_affected_rows($result) != 1)
      contactTech("Could not create transaction");
    pg_free_result($result);
    header("Location: " . SDS_BASE_URL .
	   sdsLink('govtracker/fin-subaccount.php',
		   "subid=".$values['subid'],true));
    exit;
  }
}

sdsIncludeHeader("GovTracker","Simmons Finances Online",
		 "<script type='text/javascript' src='fin-new-trans.js'></script>",
		 "onload='init()'");

foreach($complaints as $complaint) {
  echo "<p class='error'>",$complaint,"</p>\n";
}

$query = <<<ENDQUERY
SELECT subid,gov_fin_subaccounts.name,allocationamt,isallocation,
       CAST(allocationamt +
            SUM(CASE WHEN voidedby IS NULL THEN COALESCE(amount,0) ELSE 0 END)
                                              AS decimal(10,2)) AS remaining
FROM gov_fin_subaccounts LEFT JOIN gov_fin_ledger USING (acctid,subid)
WHERE acctid='$acctid' AND closedby IS NULL
GROUP BY subid,gov_fin_subaccounts.name,allocationamt,isallocation
ORDER BY isallocation,gov_fin_subaccounts.name
ENDQUERY;
$subresult = sdsQuery($query);
if(!$subresult)
  contactTech("Could not search subaccounts");

echo "<form action='fin-new-trans.php' method='post'>\n";
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
    <td>Subaccount</td>
    <td><select name='subid' id='subid-select'>
<?php
while($subdata = pg_fetch_object($subresult)) {
  echo "      <option value='",$subdata->subid,
    $subdata->subid==$values['subid']?"' selected='selected'>":"'>",
    htmlspecialchars($subdata->name),"</option>\n";
}
?>
    </select><br />
    <span id='fin-sub-descr'>
<?php
pg_result_seek($subresult,0);
while($subdata = pg_fetch_object($subresult)) {
  echo "      <span id='sub-descr:",$subdata->subid,
    "' style='font-size:small'>\n";
  if($subdata->isallocation === 't') {
    echo "        This allocation is for <span class='money'>",
      htmlspecialchars($subdata->allocationamt), "</span>\n";
    echo "        and has <span class='",moneyclass($subdata->remaining),"'>",
      htmlspecialchars($subdata->remaining),"</span> remaining.\n";
  } else {
    echo "        This account has <span class='",
      moneyclass($acctdata->unallocated),"'>",
      htmlspecialchars($acctdata->unallocated),"</span> unallocated.\n";
  }
  echo "      </span>\n";
}
pg_free_result($subresult);
?>
    </span></td>
  </tr>
  <tr>
    <td>Description:</td>
    <td><input type='text' name='name' size='45' value='<?php echo htmlspecialchars($values['name']) ?>' /></td>
  </tr>
  <tr>
    <td>Amount:</td>
    <td>$<input type='text' name='amount' size='10' value='<?php echo htmlspecialchars($values['amount']) ?>' /><br />
      <span style='font-size:small'>
        Minus sign for expense, none for income.
      </span>
    </td>
  </tr>
</table>

<p>All data will be publicly viewable.</p>

<input type='submit' value='Create Transaction' />
</form>

<?php
include('gt-footer.php');
sdsIncludeFooter();
