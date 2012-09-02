<?php
require_once("../sds.php");
sdsRequireGroup("USERS");
sdsIncludeHeader("GovTracker","Simmons Finances Online");
require_once('fin-util.inc.php');
require_once('../sds/ordering.inc.php');

$acctid = (int) $_REQUEST['acctid'];
$query = "SELECT name FROM gov_fin_accounts WHERE acctid='$acctid'";
$result = sdsQuery($query);
if(!$result)
  contactTech("Could not search accounts");
if(pg_num_rows($result) != 1) {
  echo "<h2 class='error'>No such account</h2>\n";
  include('gt-footer.php');
  sdsIncludeFooter();
  exit;
}
list($acctname) = pg_fetch_array($result);
pg_free_result($result);

accountsummary($acctid,$acctname);

$query = "SELECT acctid,name FROM gov_fin_accounts ORDER BY acctid";
$result = sdsQuery($query);
if(!$result)
  contactTech("Could not search accounts");

echo "<ul class='fin-account-list'>\n";
echo "  <li><a href='",sdsLink('fin-ledger.php',"acctid=$acctid"),
  "'>Accounts Ledger</a></li>\n";
while($data = pg_fetch_object($result)) {
  if($acctid == $data->acctid) {
    echo "  <li class='current'>",htmlspecialchars($data->name),"</li>\n";
  } else {
    echo "  <li><a href='",
      sdsLink('fin-subacct-summary.php',"acctid=$data->acctid"),
      "'>",htmlspecialchars($data->name),"</a></li>\n";
  }
}
pg_free_result($result);
echo "</ul>\n";
echo "<hr />\n";



$sortby = getSortby($_REQUEST['sortby'],1,5,'fin_subacct_sortby');
$orderbyarray = array('created ASC,subid ASC',
		      'created DESC,subid DESC',
		      'name ASC,subid ASC',
		      'name DESC,subid DESC',
		      'allocationamt ASC,subid ASC',
		      'allocationamt DESC,subid ASC',
		      'spent ASC,subid ASC',
		      'spent DESC,subid ASC',
		      'remaining ASC,subid ASC',
		      'remaining DESC,subid ASC');

$query = <<<ENDQUERY
SELECT subid,gov_fin_subaccounts.name,allocationamt,closedby,
       to_char(created,'YY-MM-DD') AS createdstr,
       CAST(-SUM(CASE WHEN voidedby IS NULL THEN COALESCE(amount,0) ELSE 0 END)
                                                 AS decimal(10,2)) AS spent,
       CAST(CASE WHEN closedby IS NULL
                     THEN allocationamt+SUM(CASE WHEN voidedby IS NULL
                                                     THEN COALESCE(amount,0)
                                                 ELSE 0 END)
                 ELSE 0 END AS decimal(10,2)) AS remaining
FROM gov_fin_subaccounts LEFT JOIN gov_fin_ledger USING (acctid,subid)
WHERE acctid='$acctid' AND isallocation
GROUP BY subid,gov_fin_subaccounts.name,allocationamt,closedby,created
ORDER BY $orderbyarray[$sortby]
ENDQUERY;
$result = sdsQuery($query);
if(!$result)
  contactTech("Could not seach subaccounts");

if(!empty($session->groups['FINANCIAL-ADMINS'])) {
  echo "<div>\n";
  echo "  <span class='fin-link'><a href='",
    sdsLink('fin-new-subaccount.php',"acctid=$acctid"),
    "'>Create New Subaccount</a></span>\n";
  echo "  <span class='fin-link'><a href='",
    sdsLink('fin-new-trans.php',"acctid=$acctid"),
    "'>New Transaction</a></span>\n";
  echo "</div>\n";
}

echo "<table class='fin-ledger'>\n";
echo "  <tr>\n";
echo "    <th colspan='5'>Allocations</th>\n";
echo "  </tr>\n";
echo "  <tr>\n";
makeSortTH('Date',0,$sortby,"acctid=$acctid","",1);
makeSortTH('Description',1,$sortby,"acctid=$acctid","",0);
makeSortTH('Allocated',2,$sortby,"acctid=$acctid","",0);
makeSortTH('Spent',3,$sortby,"acctid=$acctid","",0);
makeSortTH('Remaining',4,$sortby,"acctid=$acctid","",1);
if(!empty($session->groups['FINANCIAL-ADMINS']))
  echo "<th>Actions</th>\n";
echo "  </tr>\n";

$rowclass = 'oddrow';
while($data = pg_fetch_object($result)) {
  $rowclass = $rowclass === 'oddrow' ? 'evenrow' : 'oddrow';
  echo "  <tr class='",$rowclass,"'>\n";
  echo "    <td style='white-space:nowrap'>",
      htmlspecialchars($data->createdstr),"</td>\n";

  echo "    <td><a href='",
    sdsLink('fin-subaccount.php',"subid=$data->subid"),"'>",
    htmlspecialchars($data->name),"</a>";
  if(isset($data->closedby))
    echo " <img src='lock.gif' alt='closed' />";
  echo "</td>\n";

  echo "    <td class='money'>",htmlspecialchars($data->allocationamt),"</td>";

  echo "    <td class='money'>",htmlspecialchars($data->spent),"</td>\n";

  echo "    <td class='",moneyclass($data->remaining),"'>",
    htmlspecialchars($data->remaining),"</td>\n";

  if(!empty($session->groups['FINANCIAL-ADMINS'])) {
    echo "    <td>\n";
    if(isset($data->closedby)) {
      echo "      <span class='fin-link'><a href='",
	sdsLink('fin-open-subaccount.php',"subid=$data->subid"),
	"'>Reopen</a></span>\n";
    } else {
      echo "      <span class='fin-link'><a href='",
	sdsLink('fin-close-subaccount.php',"subid=$data->subid"),
	"'>Close</a></span>\n";
    }
    echo "    </td>\n";
  }
  echo "  </tr>\n";
}
pg_free_result($result);

echo "  <tr>\n";
echo "    <th colspan='5'>Other Subaccounts</th>\n";
echo "  </tr>\n";

$query = <<<ENDQUERY
SELECT subid,gov_fin_subaccounts.name,
       CAST(SUM(CASE WHEN voidedby IS NULL THEN COALESCE(amount,0) ELSE 0 END)
                                                  AS decimal(10,2)) AS balance
FROM gov_fin_subaccounts LEFT JOIN gov_fin_ledger USING (acctid,subid)
WHERE acctid='$acctid' AND NOT isallocation
GROUP BY subid,gov_fin_subaccounts.name
ORDER BY subid
ENDQUERY;
$result = sdsQuery($query);
if(!$result)
  contactTech("Could not search subaccounts");

$rowclass = 'oddrow';
while($data = pg_fetch_object($result)) {
  $rowclass = $rowclass === 'oddrow' ? 'evenrow' : 'oddrow';
  echo "  <tr class='",$rowclass,"'>\n";
  echo "    <td colspan='4'><a href='",
    sdsLink('fin-subaccount.php',"subid=$data->subid"),"'>",
    htmlspecialchars($data->name),"</a>";
  echo "    <td class='",moneyclass($data->balance),"'>",
    htmlspecialchars($data->balance),"</td>\n";

  if(!empty($session->groups['FINANCIAL-ADMINS'])) {
    echo "    <td>\n";
    echo "    </td>\n";
  }
  echo "  </tr>\n";
}
pg_free_result($result);
echo "</table>\n";

include('gt-footer.php');
sdsIncludeFooter();
