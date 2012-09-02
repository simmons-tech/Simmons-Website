<?php
# note that this file can be executed as either
# index.php for finished, valid expenses or
# proposals.php for events in progress
require_once("../sds.php");
require_once("../sds/ordering.inc.php");
sdsRequireGroup("USERS");

# are we showing finalized or in progress events?
$finalized = (basename($_SERVER['PHP_SELF']) === 'index.php');

sdsIncludeHeader("Lounge Expense Log",($finalized?"Browse Submitted Expenses":
				       "Browse Submitted Proposals"));

$username_esc = pg_escape_string($session->username);

if($finalized) {
  echo "<h2>Current Lounge Expenses</h2>\n";
} else {
#(((
?>
<h2>Lounge Events in Progress</h2>

<a href="<?php echo sdsLink("submitexpense.php") ?>">Submit a Proposal</a>

<p>None of these proposals are currently eligible for reimbursement.  This
  is because they either a) do not have enough approvals, b) do not have
  expense amounts, or c) have spent too much compared to the number of people
  who committed to attending the event.</p>
<p>Once you make a vote on a event proposal, you cannot change your vote
  later.</p>
<p>If you are voting on an event proposal late (i.e. after it happened), and
  you cannot find it here, it may be on the main expense tracker.</p>
<?php
}

$sortby = getSortby($_REQUEST['sortby'],5,6,'lounge_expenses_sortby');

$orderbyarray = array(# lounge (actually loungeid)
                      "loungeid ASC,datesubmitted ASC",
                      "loungeid DESC,datesubmitted ASC",
                      # submitting user(name)
                      "usersub ASC,datesubmitted ASC",
                      "usersub DESC,datesubmitted ASC",
                      # submission time
                      "datesubmitted ASC",
                      "datesubmitted DESC",
                      # amount
                      "amountspent ASC,loungeid ASC,datesubmitted ASC",
                      "amountspent DESC,loungeid ASC,datesubmitted ASC",
                      # event date
                      "datespent ASC,loungeid ASC,datesubmitted ASC",
                      "datespent DESC,loungeid ASC,datesubmitted ASC",
                      # participants
                      "numparticipated ASC,loungeid ASC,datesubmitted ASC",
                      "numparticipated DESC,loungeid ASC,datesubmitted ASC"
    );

$validfield = $finalized ? 'valid' : 'NOT valid';

$query = <<<ENDQUERY
SELECT loungeid,expenseid,amountspent,finished,numparticipated,usersub,
       datespent,lounge_expenses.description AS description,canceled,
       lounges.description AS loungename,
       to_char(datesubmitted,'FMMM-FMDD-YY FMHH:MI am') AS timestamp
FROM lounge_expenses JOIN lounges ON (lounge = loungeid)
WHERE termsold = 0 AND $validfield
ORDER BY $orderbyarray[$sortby]
ENDQUERY;

$result = sdsQuery($query);
if(!$result)
  contactTech("Could not search events");

if($finalized) {
?>

<p><a href="submitexpense.php">Submit a Proposal</a>
 | <a href="proposals.php">View Events in Progress</a>
<?php
  if(!empty($session->groups['LOUNGE-CHAIRS'])) {
    echo " | <a href='archiveall.php'>Remove All Expenses</a> (no way to undo this!)\n";
  }
} else {
  echo '<p><a href="index.php">View Official Expenses</a>';
}
?>
</p>

<table class="loungeinfo">
  <tr>
<?php
makeSortTH("Lounge",0,$sortby,'','width="12%"',0);
makeSortTH("User",1,$sortby,'','width="7%"',0);
makeSortTH("Submitted On",2,$sortby,'','width="7%"',1);
makeSortTH("Amount",3,$sortby,'','width="7%"',1);
echo '<th width="60%">Event (';
makeSortCode("Date",4,$sortby,'',1);
echo ' and ';
makeSortCode("Participants",5,$sortby,'',1);
echo ")</th>\n";
echo "  </tr>\n\n";

if (pg_num_rows($result)==0) {
?>
  <tr><td colspan="5">
    <center><i>No expense records match your query.</i></center>
  </td></tr>
<?php
}

$parity = "oddrow"; # start with even
while($data = pg_fetch_object($result)) {
  # alternate row coloring
  $parity = ($parity === "oddrow" ? "evenrow" : "oddrow");

  # Get info about lounge
  $loungedesc = $data->loungename;

  # count approvals and generate lists
  $approvals = 0;

  $query = "SELECT username FROM lounge_expense_actions WHERE expenseid=$data->expenseid AND action=0 ORDER BY username";
  $userresult = sdsQuery($query);
  if(!$userresult)
    contactTech("Could not get approvals");
  $approvals += pg_num_rows($userresult);

  $usersarray = array();
  while($row = pg_fetch_array($userresult)) {
    $usersarray[] = $row['username'];
  }
  $approvallist = implode(', *',$usersarray);
  pg_free_result($userresult);
  if(strlen($approvallist))
    $approvallist = '*'.$approvallist;

  $query = "SELECT username FROM lounge_expense_actions WHERE expenseid=$data->expenseid AND action=1 ORDER BY username";
  $userresult = sdsQuery($query);
  if(!$userresult)
    contactTech("Could not get approvals");
  $approvals += pg_num_rows($userresult);

  $usersarray = $approvallist ? array($approvallist) : array();
  while($row = pg_fetch_array($userresult)) {
    $usersarray[] = $row['username'];
  }
  $approvallist = implode(', ',$usersarray);
  pg_free_result($userresult);

  if($data->finished !== 't') {
    $amountspent_disp = '(No info)';
    $timestamp_disp = '(No info)';
    $participated_disp = '(No information yet)';
  } elseif($data->canceled === 't') {
    $amountspent_disp = 'Canceled';
    $participated_disp = 'Canceled';
    $timestamp_disp = htmlspecialchars($data->timestamp);
  } else {
    $amountspent_disp = '$'.$data->amountspent;
    $timestamp_disp = htmlspecialchars($data->timestamp);
    $participated_disp = $data->numparticipated;
  }

?>
  <tr class="<?php echo $parity ?>">
    <td width="12%"><?php echo htmlspecialchars($loungedesc) ?></td>
    <td width="7%"><?php echo sdsGetFullName($data->usersub) ?></td>
    <td width="7%"><?php echo $timestamp_disp ?></td>
    <td width="7%"><?php echo $amountspent_disp ?></td>
    <td width="60%">
      <i>Date: <?php echo htmlspecialchars($data->datespent) ?>;
         Participants: <?php echo $participated_disp ?></i><br />
      <span style="font-size:small">
        <?php echo nl2br(htmlspecialchars($data->description)) ?>
      </span><br />
      <i>Approvals: <?php echo $approvals ?>
        <span style="font-size:xx-small"><?php echo $approvallist ?></span></i>
<?php

  if(!empty($session->groups[$data->loungeid])) {
    # they are in this lounge, see if they have already voted
    $query = "SELECT action FROM lounge_expense_actions WHERE expenseid=$data->expenseid AND username='$username_esc'";
    $actionresult = sdsQuery($query);
    if(!$actionresult)
      contactTech("Could not find approval status");
    if(pg_num_rows($actionresult)) {
      list($action) = pg_fetch_array($actionresult);
      echo "      <p>You <b>",($action<2?'approved':'rejected'),
	"</b> this event and <b>",($action<1?'committed':'did not commit'),
	"</b> to attending.</p>\n";
    } elseif($data->canceled !== 't') {
      # still need to vote
?>
      <form style="margin: 0pt" action="decision.php" method="post">Decide:
<?php echo sdsForm() ?>
        <select name="decisiontype" size="1">   
          <option value="0">I Approve and Commit to attending</option>
          <option value="1">I Approve and DO NOT Commit to attending</option>
          <option value="2">I Reject (and DO NOT Commit to attending)</option>
        </select>
        <input type="hidden" name="eventid" value="<?php echo $data->expenseid ?>" />
        <input type="submit" name="submitdecision" value="Submit" />
      </form>
<?php
    }
    pg_free_result($actionresult);

    # is the event finished?
    # are they also the person who submitted the expense?
    if($data->finished !== "t" and $data->usersub === $username_esc) {
?>
      <form style="margin: 0pt 0pt 0pt 0pt;" action="finalizeexpense.php" method="post">Update: 
<?php echo sdsForm() ?>
        Amount: $<input name="amt" size="5" /> Participants: <input name="attendnum" size="2" /> 
        <input type="hidden" name="eventid" value="<?php echo $data->expenseid ?>" />
        <input type="submit" name="finalizeexpense" value="Finalize Expense" />
      </form>
      <form style="margin: 0pt" action="cancelexpense.php" method="post">
        <input type="hidden" name="eventid" value="<?php echo $data->expenseid ?>" />
        <input type="submit" value="Cancel Expense" />
      </form>
<?php
    }
  }
?>
    </td>
  </tr>
<?php
}
?>
</table>
* - In addition to approving the event, this member committed to attending.
<hr />
<?php
if($finalized) {
  $query = "SELECT 1 FROM active_lounges WHERE allocation IS NOT NULL LIMIT 1";
  $result = sdsQuery($query);
  if(!$result)
    contactTech("Could not check allocation status");
  $allocated = (pg_num_rows($result) == 1);
  pg_free_result($result);

echo "<h3 id='summary'>Current Term Summary Report</h3>\n";
echo "<table class='loungeinfo'>\n";

  if($allocated) {
?>
  <tr>
    <td></td>
    <td></td>
    <td></td>
    <td></td>
    <th colspan="3" style="border-bottom-style:solid;border-color:black">Allocation</th>
  </tr>
  <tr>
    <th>Lounge</th>
    <th>Members</th>
    <th>Avg.&nbsp;Part.</th>
    <th>Events</th>
    <th>Total</th>
    <th>Used</th>
    <th>Remaining</th>
  </tr>
<?php
    $query = <<<ENDQUERY
SELECT description,membership,to_char(avgparts,'FM9990.00') AS avgparts,
       numevents,CAST(COALESCE(allocation,0) AS decimal(10,2)) AS allocation,
       totalspent,
       CAST(COALESCE(allocation,0) AS decimal(10,2))-totalspent AS remaining
FROM lounge_summary_report
ORDER BY description
ENDQUERY;
  } else {
?>
  <tr>
    <th>Lounge</th>
    <th>Members</th>
    <th>Predicted Allocation</th>
  </tr>
<?php
    $query = "SELECT description,membership,predalloc FROM lounge_summary_report ORDER BY description";
  }

  $result = sdsQuery($query);
  if(!$result) {
    echo "</table>\n";
    contactTech("Can't create lounge summary table",false);
  } else {
    $parity = "oddrow";
    while($record = pg_fetch_array($result)) {
      # alternate row coloring
      $parity = ($parity === "oddrow" ? "evenrow" : "oddrow");

      echo "  <tr class='",$parity,"'>\n";
      echo "    <td>",htmlspecialchars($record['description']),"</td>\n";
      echo "    <td class='number'>",$record['membership'],"</td>\n";
      if($allocated) {
	echo "    <td class='number'>",$record['avgparts'],"</td>\n";
	echo "    <td class='number'>",$record['numevents'],"</td>\n";
	echo "    <td class='money'>",$record['allocation'],"</td>\n";
	echo "    <td class='money'>",$record['totalspent'],"</td>\n";
	echo "    <td class='money",($record['remaining']<0?' neg':''),"'>",
	  $record['remaining'],"</td>\n";
      } else {
	echo "    <td class='money'>",$record['predalloc'],"</td>\n";
      }
      echo "  </tr>\n";
    }
    pg_free_result($result);
    echo "</table>\n";
  }
  echo "<hr />\n";
}
?>
<h3>Why?</h3>
<blockquote>
  The amended lounge bylaws require lounge representatives to both get approval
  of events by a minimum number of lounge members and to post expenses on the
  DB before being reimbursed.
</blockquote>

<?php
require("gt-footer.php");
sdsIncludeFooter();
