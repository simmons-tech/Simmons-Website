<?php
# Displays all transactions in each of the main accounts
require_once("../sds.php");
sdsRequireGroup("USERS");
require_once('fin-util.inc.php');
require_once('../sds/ordering.inc.php');
sdsIncludeHeader("GovTracker","Simmons Finances Online");

$acctname = 'All Accounts';
$acctrestrict = 'TRUE';
$transaction_limit = 'LIMIT 10';
$orderby = 'submitted DESC';
$acctid = null;
if(!empty($_REQUEST['acctid'])) {
  $acctid = (int) $_REQUEST['acctid'];

  $query = "SELECT name FROM gov_fin_accounts WHERE acctid='$acctid'";
  $result = sdsQuery($query);
  if(!$result)
    contactTech("Could not search accounts");

  if(pg_num_rows($result) != 1) {
    echo "<p class='error'>Unknown account.</p>\n";
    include('gt-footer.php');
    sdsIncludeFooter();
    exit;
  }
  list($acctname) = pg_fetch_array($result);
  pg_free_result($result);

  $acctrestrict = "acctid = '$acctid'";
  $transaction_limit = '';

  $sortby = getSortby($_REQUEST['sortby'],1,4,'fin_trans_sortby');
  $orderbyarray = array('submitted ASC,tid ASC',
			'submitted DESC,tid DESC',
			'gov_fin_ledger.name ASC,tid ASC',
			'gov_fin_ledger.name DESC,tid DESC',
			'gov_fin_subaccounts.shortname ASC,submitted ASC,tid ASC',
			'gov_fin_subaccounts.shortname DESC,submitted DESC,tid DESC',
			'amount ASC,tid ASC',
			'amount DESC,tid DESC');
  $orderby = $orderbyarray[$sortby];
}

accountsummary($acctid,$acctname);

$query = "SELECT acctid,name FROM gov_fin_accounts ORDER BY acctid";
$result = sdsQuery($query);
if(!$result)
  contacTech("Could not search accounts");

echo "<ul class='fin-account-list'>\n";
if(isset($acctid)) {
  echo "  <li><a href='",sdsLink('fin-ledger.php'),"'>All Accounts</a></li>\n";
} else {
  echo "  <li class='current'>All Accounts</li>\n";
}
while($data = pg_fetch_object($result)) {
  if(isset($acctid) and $acctid == $data->acctid) {
    echo "  <li class='current'>",htmlspecialchars($data->name),"</li>\n";
  } else {
    echo "  <li><a href='",sdsLink('fin-ledger.php',"acctid=$data->acctid"),
      "'>",htmlspecialchars($data->name),"</a></li>\n";
  }
}
echo "</ul>\n";
echo "<hr />\n";

$query = <<<ENDQUERY
SELECT acctid,name,SUM(subtotal) AS balance,
       SUM(CASE WHEN isclosed THEN subtotal ELSE -allocationamt END)
                                                               AS unallocated
FROM gov_fin_accounts LEFT JOIN
     (SELECT acctid,(closedby IS NOT NULL OR NOT isallocation) AS isclosed,
             allocationamt,
             CAST(SUM(CASE WHEN voidedby IS NULL THEN COALESCE(amount,0)
                           ELSE 0 END) AS decimal(10,2)) AS subtotal
      FROM gov_fin_subaccounts LEFT JOIN gov_fin_ledger USING (acctid,subid)
      WHERE $acctrestrict
      GROUP BY acctid,subid,isclosed,allocationamt) AS stuff USING (acctid)
WHERE $acctrestrict
GROUP BY acctid,name
ORDER BY acctid
ENDQUERY;
$result = sdsQuery($query);
if(!$result)
  contactTech("Could not search accounts");

while($data = pg_fetch_object($result)) {
  $query = "SELECT count(*) FROM gov_fin_ledger WHERE acctid='$data->acctid'";
  $countres = sdsQuery($query);
  if(!$countres or pg_num_rows($countres) != 1)
    contactTech("Could not count transactions");
  list($transcount) = pg_fetch_array($countres);
  pg_free_result($countres);

  $query = <<<ENDQUERY
SELECT tid,subid,gov_fin_ledger.name AS transname,
       to_char(submitted,'YY-MM-DD FMHH:MI am') AS submittedstr,
       gov_fin_ledger.byuser,amount,voidedby,
       gov_fin_subaccounts.shortname AS subname,
       closedby IS NOT NULL AS subclosed
FROM gov_fin_ledger JOIN gov_fin_subaccounts USING (acctid,subid)
WHERE acctid='$data->acctid'
ORDER BY $orderby $transaction_limit
ENDQUERY;
  $trans_result = sdsQuery($query);
  if(!$trans_result)
    contactTech("Could not search transactions");

echo "<h2>",htmlspecialchars($data->name),"</h2>\n";
echo "<div>\n";
echo "  <span class='fin-link'><a href='",
  sdsLink('fin-subacct-summary.php',"acctid=$data->acctid"),
  "'>View Subaccounts</a></span>\n";
if(!empty($session->groups['FINANCIAL-ADMINS'])) {
  echo "  <span class='fin-link'><a href='",
    sdsLink('fin-new-subaccount.php',"acctid=$data->acctid"),
    "'>New Subaccount</a></span>\n";
  echo "  <span class='fin-link'><a href='",
    sdsLink('fin-new-trans.php',"acctid=$data->acctid"),
    "'>New Transaction</a></span>\n";
}
?>
</div>

<table class="fin-acct-total">
  <tr>
    <td>Balance:</td>
    <td class="<?php echo moneyclass($data->balance) ?>"><?php echo $data->balance ?></td>
  </tr>
  <tr>
    <td>Unallocated:</td>
    <td class="<?php echo moneyclass($data->unallocated) ?>"><?php echo $data->unallocated ?></td>
  </tr>
</table>

<table class="fin-ledger">
  <tr>
<?php
  if(isset($acctid)) {
    makeSortTH('Submitted',0,$sortby,"acctid=$acctid","",1);
    makeSortTH('Description',1,$sortby,"acctid=$acctid","",0);
    makeSortTH('Subaccount',2,$sortby,"acctid=$acctid","",0);
    makeSortTH('Amount',3,$sortby,"acctid=$acctid","",1);
  } else {
?>
    <th>Submitted</th>
    <th>Description</th>
    <th>Subaccount</th>
    <th>Amount</th>
<?php
  }
  if(!empty($session->groups['FINANCIAL-ADMINS']))
    echo "    <th>Actions</th>\n";
  echo "  </tr>\n";

  $rowclass = 'oddrow';
  $rownum = 0;
  while($transdata = pg_fetch_object($trans_result)) {
    $rowclass = $rowclass === 'oddrow' ? 'evenrow' : 'oddrow';
    $rownum++;

    echo "  <tr class='",$rowclass, isset($transdata->voidedby)?' void':'',"'",
      $rownum==11?" id='rest'":"",">\n";
    echo "    <td><span style='white-space:nowrap'>",
      htmlspecialchars($transdata->submittedstr),
      "</span> <span style='font-size:x-small;white-space:nowrap'>by ",
      htmlspecialchars($transdata->byuser),"</span></td>\n";

    echo "    <td>",htmlspecialchars($transdata->transname);
    if(isset($transdata->voidedby)) {
      echo "(voided by ",htmlspecialchars($transdata->voidedby),")";
    }
    echo "</td>\n";

    echo "    <td><a href='",
      sdsLink('fin-subaccount.php',"subid=$transdata->subid"),"'>",
      htmlspecialchars($transdata->subname),"</a>";
    if($transdata->subclosed === 't')
      echo " <img src='lock.gif' alt='closed' />";
    echo "</td>\n";

    echo "    <td class='",moneyclass($transdata->amount),"'>",
      $transdata->amount,"</td>\n";

    if(!empty($session->groups['FINANCIAL-ADMINS'])) {
      echo "    <td>\n";
      if(!isset($transdata->voidedby)) {
	echo "      <span class='fin-link'><a href='",
	  sdsLink('fin-void-trans.php',"transid=$transdata->tid"),
	  "'>Void</a></span>\n";
      }
      echo "    </td>\n";
    }
    echo "  </tr>\n";
  }

  if($rownum < $transcount) {
    echo "  <tr>\n";
    echo "    <td colspan='4' class='older'><a href='",
      sdsLink('fin-ledger.php',"acctid=$data->acctid"),"#rest'>And ",
      $transcount-$rownum," older transactions...</a></td>\n";
    echo "  </tr>\n";
  }
  pg_free_result($trans_result);
  echo "</table>\n";
}
pg_free_result($result);

include("gt-footer.php");
sdsIncludeFooter();
