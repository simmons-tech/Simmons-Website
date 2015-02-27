<?php
require_once("../sds.php");
sdsRequireGroup("HOUSE-COMM-LEADERSHIP");

# find an agenda which is closed (but not finished)
$query = <<<ENDQUERY
SELECT agendaid,meetingtitle,prefacetext,hchairannounce,presannounce,
       committeereps,
       to_char(meetingdate,'FMMonth FMDD, YYYY') AS meetingdatestr
FROM gov_agendas
WHERE status='closed'
ENDQUERY;
$result = sdsQuery($query);
if(!$result)
  contactTech("Could not search agendas");
if(pg_num_rows($result) != 1) {
  pg_free_result($result);
  sdsIncludeHeader("GovTracker","Simmons Government Online");
?>
<h3>No meeting information</h3>
<p style="font-weight:bold">The House Chair has not yet closed the agenda for
  the upcoming meeting.</p>

<?php
  include("gt-footer.php");
  sdsIncludeFooter();
  return;
}
$mtgdata = pg_fetch_object($result);
pg_free_result($result);
$aid = $mtgdata->agendaid;

?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN"
        "http://www.w3.org/TR/html4/loose.dtd">
<html>
<head>
  <meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1" />
  <link rel="stylesheet" href="simmons-gt.css" type="text/css" />
</head>
<body class="pagebody">
<?php
# what type of page are we displaying?

if($_REQUEST['page'] === "intro") {
  echo "  <h1>",htmlspecialchars($mtgdata->meetingtitle),"</h1>\n";
  echo "  <h3>",htmlspecialchars($mtgdata->meetingdatestr),"</h3>\n";
  echo "  <p>",nl2br(htmlspecialchars($mtgdata->prefacetext)),"</p>\n";
} elseif($_REQUEST['page'] === "hca") {
?>
  <h1>House Chair's Announcements</h1>
  <ul>
<?php
#'
  echo "    <li>",str_replace("\n","</li>\n    <li>",$mtgdata->hchairannounce);
?>
</li>
  </ul>
<?php
} elseif($_REQUEST['page'] === "tresrep") {
  require_once('fin-util.inc.php');

  echo "  <h1>Treasurer's Report</h1>\n";
  accountsummary();

# SQL has fixed point math, so do as much computation as possible there
# This assumes at least one subaccount exists
  $query = <<<ENDQUERY
SELECT acctid,shortname,
       SUM(subtotal) AS balance,
       SUM(CASE WHEN isclosed THEN subtotal ELSE -allocationamt END)
                                                               AS unallocated
FROM (SELECT acctid,(closedby IS NOT NULL OR NOT isallocation) AS isclosed,
             allocationamt,
             CAST(SUM(CASE WHEN voidedby IS NULL THEN COALESCE(amount,0)
                           ELSE 0 END) AS decimal(10,2)) AS subtotal
      FROM gov_fin_subaccounts LEFT JOIN gov_fin_ledger USING (acctid,subid)
      GROUP BY acctid,subid,isclosed,allocationamt) AS stuff
     JOIN gov_fin_accounts USING (acctid)
GROUP BY acctid,shortname
ORDER BY acctid
ENDQUERY;
  $result = sdsQuery($query);
  if(!$result)
    contactTech("Could not calculate account balances");
  echo "<table class='fin-ledger grid'>\n";
  echo "  <tr>\n";
  echo "    <td></td>\n";
  while($record = pg_fetch_object($result)) {
    echo "    <th>",htmlspecialchars($record->shortname),"</th>\n";
  }
  echo "  </tr>\n";
  echo "  <tr>\n";
  echo "    <td>Balance</td>\n";
  pg_result_seek($result,0);
  while($record = pg_fetch_object($result)) {
    echo "    <td class='",moneyclass($record->balance),"'>",
      htmlspecialchars($record->balance),"</td>\n";
  }
  echo "  </tr>\n";
  echo "  <tr>\n";
  echo "    <td>Unallocated</td>\n";
  pg_result_seek($result,0);
  while($record = pg_fetch_object($result)) {
    echo "    <td class='",moneyclass($record->unallocated),"'>",
      htmlspecialchars($record->unallocated),"</td>\n";
  }
  echo "  </tr>\n";
  echo "</table>\n";

  $query = <<<ENDQUERY
SELECT gov_fin_subaccounts.name,
       CAST(allocationamt +
            SUM(CASE WHEN voidedby IS NULL THEN COALESCE(amount,0) ELSE 0 END)
                                               AS decimal(10,2)) AS remaining
FROM gov_fin_subaccounts LEFT JOIN gov_fin_ledger USING (subid)
WHERE closedby IS NULL AND isallocation
GROUP BY subid,gov_fin_subaccounts.name,allocationamt,created
ORDER BY created DESC,subid ASC
ENDQUERY;
  $result = sdsQuery($query);
  if(!$result)
    contactTech("Could not seach subaccounts");

?>
<h2>Open Subaccounts</h2>
<table class='fin-ledger'>
  <tr>
    <th>Description</th>
    <th>Remaining Funds</th>
  </tr>
<?php
  $rowclass = 'oddrow';
  while($subdata = pg_fetch_object($result)) {
    $rowclass = $rowclass === 'oddrow' ? 'evenrow' : 'oddrow';
    echo "  <tr class='",$rowclass,"'>\n";
    echo "    <td>",htmlspecialchars($subdata->name),"</td>\n";

    echo "    <td class='",moneyclass($subdata->remaining),"'>",
      htmlspecialchars($subdata->remaining),"</td>\n";

    echo "  </tr>\n";
  }
  pg_free_result($result);
  echo "</table>\n";

} elseif($_REQUEST['page'] === "presa") {
?>
  <h1>President's Announcements</h1>
  <ul>
<?php
#'
  echo "    <li>",str_replace("\n","</li>\n    <li>",$mtgdata->presannounce);
?>
</li>
  </ul>
<?php
} elseif($_REQUEST['page'] === "cr") {
?>
  <h1>Committee Reports</h1>
  <ul>
<?php
  echo "    <li>",str_replace("\n","</li>\n    <li>",$mtgdata->committeereps);
?>
</li>
  </ul>
<?php
} elseif($_REQUEST['page'] === "finish") {
  $query = <<<ENDQUERY
UPDATE gov_agendas SET status='completed' WHERE agendaid='$aid';
UPDATE gov_proposals SET decision=null,agendaid=null,agendaorder=null
WHERE agendaid='$aid' AND decision = 'TABLED';
ENDQUERY;
  $result = sdsQuery($query);
  if(!$result)
    contactTech("Could not adjourn meeting");
  pg_free_result($result);
?>
  <h1>Meeting Adjourned</h1>
  <p>Meeting data saved: Meeting status changed to Completed, and all TABLED
    proposals moved to open agenda</p>
<?php
} elseif($_REQUEST['page'] === "proposal") {
  $propid = (int) $_REQUEST['id'];
  $query = <<<ENDQUERY
SELECT title,author,coauthors,type,description,finalfunds,finalfulltext,
       specialnotes,decision
FROM gov_active_proposals
WHERE propid='$propid' AND agendaid='$aid'
ENDQUERY;
  $result = sdsQuery($query);
  if(!$result)
    contactTech("Could not search proposals");

  if(pg_num_rows($result) != 1) {
    echo "  <h2 class='error'>No proposal records match your query.</h2>\n";
  } else {
    $data = pg_fetch_object($result);

?>
  <h2>#<?php echo $propid ?>: <?php echo htmlspecialchars($data->title) ?></h2>
  <h3>by <?php echo sdsGetFullname($data->author),
     $data->coauthors?(" and ".htmlspecialchars($data->coauthors)):"" ?>:
    <i><?php
    if($data->type === "amendment") { echo "Constitutional Amendment"; }
    elseif($data->type === "fundrequest") { echo "Request for House Funds"; }
    elseif($data->type === "policy") { echo "Official Policy or House Opinion"; }
    elseif($data->type === "announcement") { echo "Announcement/Discussion"; }
?>
</i></h3>
<?php
    if($data->specialnotes)
      echo htmlspecialchars($data->specialnotes);
?>
  <p>Summary: <?php echo htmlspecialchars($data->description) ?></p>
<?php
    if($data->type === "fundrequest") {
?>
  <p><form action="amendfunds.php" method="post">
    <input type="hidden" name="pid" value="<?php echo $propid ?>" />
    Funds: $<input type="text" name="amendfunds" value="<?php echo $data->finalfunds ?>" size="7" />
<?php
      if(!isset($data->decision)) {
?>
    <input type="submit" value="Amend Funding" />
<?php
      }
?>
  </form></p>
<?php
    }
?>
  <p><form action="amendtext.php" method="post">
    <input type="hidden" name="pid" value="<?php echo $propid ?>" />
    Full text:<br />
    <textarea name="amendtext" style="width:90%" rows="8"><?php echo htmlspecialchars($data->finalfulltext) ?></textarea><br />
<?php
    if(!isset($data->decision)) {
?>
    <input type="submit" value="Amend Full Text" />
<?php
    }
?>
  </form></p>

<?php
    if(isset($data->decision)) {
      echo "  <p>Decision: <b>",$data->decision,"</b></p>\n";
    } else {
?>
  <p><form action="submitdecision.php" method="post">
    <input type="hidden" name="pid" value="<?php echo $propid ?>" />
    Decision:
      <input type="submit" name="decisiontype" value="TABLED" />
<?php
      if($data->type === "announcement") {
?>
      <input type="submit" name="decisiontype" value="DISCUSSED" />
<?php
      } else {
?>
      <input type="submit" name="decisiontype" value="APPROVED" />
      <input type="submit" name="decisiontype" value="REJECTED" />
      <input type="submit" name="decisiontype" value="MOVED TO FULL FORUM" />
<?php
      }
?>
  </form></p>
<?php
    }
  }
}
?>
</body>
</html>
