<?php
if(realpath($_SERVER['SCRIPT_FILENAME']) === realpath(__FILE__))
  exit;

function moneyclass($amount) {
  if($amount > 0) { return "money pos"; }
  if($amount < 0) { return "money neg"; }
  return "money";
}


function accountsummary($acctid = null,$title = null) {
  if(isset($acctid)) {
    $acctrestrict = "acctid = '$acctid'";
  } else {
    $acctrestrict = 'TRUE';
  }

# SQL has fixed point math, so do as much computation as possible there
# This assumes at least one subaccount exists
  $query = <<<ENDQUERY
SELECT SUM(subtotal) AS balance,
       SUM(CASE WHEN isclosed THEN subtotal ELSE -allocationamt END)
                                                               AS unallocated
FROM (SELECT (closedby IS NOT NULL OR NOT isallocation) AS isclosed,
             allocationamt,
             CAST(SUM(CASE WHEN voidedby IS NULL THEN COALESCE(amount,0)
                           ELSE 0 END) AS decimal(10,2)) AS subtotal
      FROM gov_fin_subaccounts LEFT JOIN gov_fin_ledger USING (acctid,subid)
      WHERE $acctrestrict
      GROUP BY subid,isclosed,allocationamt) AS stuff
ENDQUERY;
  $result = sdsQuery($query);
  if(!$result or pg_num_rows($result) != 1)
    contactTech("Could not calculate balance");
  $data = pg_fetch_object($result);
  pg_free_result($result);

  if(isset($title))
    echo "<h1 style='text-align:center'>",htmlspecialchars($title),"</h1>\n";
?>
<table class="fin-total">
  <tr>
    <td>Balance:</td>
    <td class="<?php echo moneyclass($data->balance) ?>"><?php echo $data->balance ?></td>
  </tr>
  <tr>
    <td>Unallocated:</td>
    <td class="<?php echo moneyclass($data->unallocated) ?>"><?php echo $data->unallocated ?></td>
  </tr>
</table>

<?php
}
