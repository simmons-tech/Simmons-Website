<?php
require_once("../sds.php");
sdsRequireGroup("USERS");
require_once('fin-util.inc.php');
require_once('../sds/ordering.inc.php');
sdsIncludeHeader("GovTracker","Simmons Finances Online");

$subid = (int) $_REQUEST['subid'];

$query = <<<ENDQUERY
SELECT acctid,gov_fin_subaccounts.name AS subname,allocationamt,
       isallocation,gov_fin_subaccounts.byuser,closedby,
       gov_fin_accounts.name AS acctname,
       to_char(created,'YY-MM-DD') AS createdstr,
       to_char(closedat,'YY-MM-DD') AS closedatstr,
       CAST(SUM(CASE WHEN voidedby IS NULL THEN COALESCE(amount,0) ELSE 0 END)
                                                AS decimal(10,2)) AS total,
       CAST(allocationamt +
            SUM(CASE WHEN voidedby IS NULL THEN COALESCE(amount,0) ELSE 0 END)
                                                AS decimal(10,2)) AS remaining
FROM gov_fin_subaccounts JOIN gov_fin_accounts USING (acctid)
                         LEFT JOIN gov_fin_ledger USING (acctid,subid)
WHERE subid='$subid'
GROUP BY acctid,subid,gov_fin_subaccounts.name,created,allocationamt,closedat,
         isallocation,gov_fin_subaccounts.byuser,closedby,gov_fin_accounts.name
ENDQUERY;
$result = sdsQuery($query);
if(!$result)
  contactTech("Could not search subaccounts");
if(pg_num_rows($result) != 1) {
  echo "<h2 class='error'>No such subaccount</h2>\n";
  include('gt-footer.php');
  sdsIncludeFooter();
  exit;
}
$acctdata = pg_fetch_object($result);
pg_free_result($result);

echo "<h2>Subaccount Details</h2>\n";
echo "<h3><a href='",sdsLink('fin-subacct-summary.php',"acctid=$acctdata->acctid"),
  "'>",htmlspecialchars($acctdata->acctname),"</a> &gt; ",
  htmlspecialchars($acctdata->subname),"</h3>\n";

if(!isset($acctdata->closedby) and
   !empty($session->groups['FINANCIAL-ADMINS']))
  echo "<p class='fin-link'><a href='",
    sdsLink('fin-new-trans.php',"acctid=$acctdata->acctid&amp;subid=$subid"),
  "'>Submit Transaction</a></p>\n";

echo "<p>Opened by ",sdsGetFullname($acctdata->byuser)," on ",
  htmlspecialchars($acctdata->createdstr);
if(isset($acctdata->closedby)) {
  echo "<br />\nClosed by ",sdsGetFullname($acctdata->closedby);
  if(isset($acctdata->closedatstr))
    echo " on ",htmlspecialchars($acctdata->closedatstr);
  if(!empty($session->groups['FINANCIAL-ADMINS']))
    echo " <span class='fin-link'><a href='",
      sdsLink('fin-open-subaccount.php',"subid=$subid"),"'>Reopen</a></span>";
} elseif($acctdata->isallocation === 't' and
	 !empty($session->groups['FINANCIAL-ADMINS'])) {
  echo " <span class='fin-link'><a href='",
      sdsLink('fin-close-subaccount.php',"subid=$subid"),"'>Close</a></span>";
}
echo "</p>\n";

if($acctdata->isallocation === 't') {
  echo "<table class='fin-acct-total'>\n";
  echo "  <tr>\n";
  echo "    <td>Allocation:</td>\n";
  echo "    <td class='",moneyclass($acctdata->allocationamt),"'>",
    htmlspecialchars($acctdata->allocationamt),"</td>\n";
  if(!isset($acctdata->closedby) and
     !empty($session->groups['FINANCIAL-ADMINS']))
    echo "    <td class='fin-link'><a href='",
      sdsLink('fin-allocate.php',"subid=$subid"),"'>Modify</a></td>\n";
  echo "  </tr>\n";
  if(!isset($acctdata->closedby)) {
    echo "  <tr>\n";
    echo "    <td>Remaining:</td>\n";
    echo "    <td class='",moneyclass($acctdata->remaining),"'>",
      htmlspecialchars($acctdata->remaining),"</td>\n";
    echo "  </tr>\n";
  }
  echo "</table>\n";
}

$sortby = getSortby($_REQUEST['sortby'],1,3,'fin_subtrans_sortby');

$orderbyarray = array('submitted ASC,tid ASC',
		      'submitted DESC,tid DESC',
		      'name ASC,tid ASC',
		      'name DESC,tid DESC',
		      'amount ASC,tid ASC',
		      'amount DESC,tid DESC');

$query = <<<ENDQUERY
SELECT tid,name,byuser,amount,voidedby,
       to_char(submitted,'YY-MM-DD FMHH:MI am') AS submittedstr
FROM gov_fin_ledger WHERE subid='$subid'
ORDER BY $orderbyarray[$sortby]
ENDQUERY;
$result = sdsQuery($query);
if(!$result)
  contactTech("Could not search transactions");

echo "<table class='fin-ledger'>\n";
echo "  <tr>\n";
makeSortTH('Submitted',0,$sortby,"subid=$subid","",1);
makeSortTH('Description',1,$sortby,"subid=$subid","",0);
makeSortTH('Amount',2,$sortby,"subid=$subid","",1);
if(!empty($session->groups['FINANCIAL-ADMINS']))
  echo "    <th>Actions</th>\n";
echo "  </tr>\n";
echo "  <tr>\n";
echo "    <td colspan='2' class='transtotal'>Transaction Total</td>\n";
echo "    <td class='",moneyclass($acctdata->total),"'>",
  htmlspecialchars($acctdata->total),"</td>\n";
echo "  </tr>\n";
$rowclass = 'oddrow';
while($data = pg_fetch_object($result)) {
  $rowclass= $rowclass === 'oddrow' ? 'evenrow' : 'oddrow';
  echo "  <tr class='",$rowclass,isset($data->voidedby)?' void':'',"'>\n";
  echo "    <td><span style='white-space:nowrap'>",
    htmlspecialchars($data->submittedstr),
    "</span> <span style='font-size:x-small;white-space:nowrap'>by ",
    htmlspecialchars($data->byuser),"</span></td>\n";

  echo "    <td>",htmlspecialchars($data->name);
  if(isset($data->voidedby)) {
    echo "(voided by ",htmlspecialchars($data->voidedby),")";
  }
  echo "</td>\n";

  echo "    <td class='",moneyclass($data->amount),"'>",
    htmlspecialchars($data->amount),"</td>\n";

  if(!empty($session->groups['FINANCIAL-ADMINS'])) {
    echo "    <td>\n";
    if(!isset($data->voidedby)) {
      echo " <span class='fin-link'><a href='",
	sdsLink('fin-void-trans.php',"transid=$data->tid"),
	"'>Void</a></span>";
    }
    echo "    </td>\n";
  }
  echo "  </tr>\n";
}
pg_free_result($result);
echo "</table>\n";

include('gt-footer.php');
sdsIncludeFooter();
