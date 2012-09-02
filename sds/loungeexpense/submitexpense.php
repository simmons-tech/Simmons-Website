<?php
require_once("../sds.php");
sdsRequireGroup("USERS");

sdsIncludeHeader("Lounge Expense Proposal","Lounge Expense Form");

$username = pg_escape_string($session->username);

# find the lounge this user (hopefully) represents
$query = "SELECT firstname,lastname,email,lounge,description FROM active_directory JOIN active_lounges USING (lounge) WHERE username='$username'";
$result = sdsQuery($query);
if(!$result)
  contactTech("Could not get membership");
if(pg_num_rows($result) == 0) {
  # kick them off the page - they're not in a lounge at all! #'
  echo "<h2>You cannot submit a lounge event proposal since you are not in any lounge.</h2>";
  require("gt-footer.php");
  sdsIncludeFooter();
  exit;
}
$data = pg_fetch_array($result);
$lounge = $data['lounge'];
$loungedesc = $data['description'];
unset($udata);
$udata->firstname = $data['firstname'];
$udata->lastname = $data['lastname'];
$udata->email = $data['email'];
pg_free_result($result);

$lounge_esc = pg_escape_string($lounge);

?>

<h2>Submit a Lounge Expense</h2>
<a href="index.php">Return to Expense Browser</a><br />
<?php
# new submission?
if(!empty($_REQUEST["submit"])) {
  # yes, try validating the submission
  $fieldsdatespent = sdsSanitizeString($_REQUEST["spenddate"]);
  $description = maybeStripslashes($_REQUEST["desc"]);
  $fieldsdescription = pg_escape_string($description);
  if(strlen($fieldsdescription) != 0 and strlen($fieldsdatespent) != 0) {
    # all proposal input is valid, save it to the DB
    $query = <<<ENDINSERT
INSERT INTO lounge_expenses
       (loungeid,         usersub,    datepropsubmitted,datespent,
        description)
VALUES ('$lounge_esc','$username',now(),            '$fieldsdatespent',
        '$fieldsdescription')
RETURNING expenseid
ENDINSERT;

    $result = sdsQuery($query);

    if(!$result or pg_affected_rows($result) != 1) {
      contactTech("Event submission failed",false);
      require("gt-footer.php");
      sdsIncludeFooter();
      exit;
    }
    list($eid) = pg_fetch_array($result);
    pg_free_result($result);

    $approveurl = SDS_BASE_URL."loungeexpense/proposals.php";
## CHANGE CodeLastUpdate WHEN YOU UPDATE THE CODE!!! :
    $mailbody = <<<EOM
Members of $loungedesc,

** This is an automatic e-mail. **

One of your lounge representatives, $udata->firstname $udata->lastname ($udata->email),
has proposed a new lounge event to be held on $fieldsdatespent .
As a lounge member, you must either APPROVE or REJECT this event proposal.
Remember, only events that are approved by at least either 5 members or
1/3 of the total lounge membership (whichever is more) can use lounge funds,
and each event may use only the same fraction of your lounge funds as the
fraction of members that commit to attending.

You must submit your approval or rejection here:
$approveurl


Event description provided by $udata->firstname $udata->lastname :
$description

If you have questions regarding lounge policies, contact the Lounge Committee
Chair (simmons-lounges@mit.edu).
If you have technical questions regarding GovTracker for Lounges, contact the
Technology Committee Chair (simmons-tech@mit.edu).

Thank you,

Simmons Technology Committee

------------------------------------
Troubleshooting information:
CodeLastUpdate: William Throwe (wthrowe@mit.edu)
SubLoungeID: $lounge
EventID: $eid
EOM;
    $to = $lounge."@simmons.mit.edu";
    if(SDB_DATABASE === 'sdb') {
      mail($to,"New Lounge Event Proposal",$mailbody,
	   "From: \"GovTracker for Lounges\" <govtracker-lounges@simmons.mit.edu>\r\nReply-To: $to")
	or contactTech("Could not send e-mail",false);
      mail($udata->email,"Your New Lounge Event Proposal",$mailbody,
	   "From: \"GovTracker for Lounges\" <govtracker-lounges@simmons.mit.edu>\r\nReply-To: simmons-tech@mit.edu")
	or contactTech("Could not send e-mail",false);
    }
?>
<b>Your event proposal has been received.</b><br />
<h3>Next steps</h3>
<ol>
  <li>
    An automatic e-mail has been sent to your lounge mailing list
    (<?php echo $to ?>) with the following instructions for your lounge
     members:
<pre>
<?php echo htmlspecialchars($mailbody) ?>
</pre>
  </li>
  <li>
    Remember, you also have to approve/reject (and possibly commit to attend)
    just like any regular member. You can do so at the
    <a target="_blank" href="proposals.php">Events in Progress page</a>.
  </li>
  <li>
    After the event has occured (and if it was approved by enough people), you
    must enter the total amount spent and number of actual attendees at the
    <a target="_blank" href="proposals.php">Events in Progress page</a>.
  </li>
</ol>
<?php
    require("gt-footer.php");
    sdsIncludeFooter();
    exit;
  } else {
?>
<h3><b>Your event proposal submission was invalid - you had one or more blank
       fields.  All fields are mandatory.  Please reenter all information
       below and resubmit.</b></h3>
<?php
  }
}

$query = "SELECT allocation - totalspent FROM lounge_summary_report WHERE loungeid = '$lounge_esc'";
$result = sdsQuery($query);
if(!$result or pg_num_rows($result) != 1)
  contactTech("Could not query funds");

list($remainingfunds) = pg_fetch_array($result);
pg_free_result($result);

$fundclass = $remainingfunds < 0 ? "money neg" : "money";

?>

<form action="submitexpense.php" method="post">

  <p>
    Lounge representatives must file this event proposal at least two days
    before the event is supposed to take place.  After you submit an event
    proposal, members of your lounge will be able to Approve or Reject the
    expenditure.  You will only be reimbursed if a) at least 5 members or 1/3
    of the total lounge membership (whichever is larger) approve the event and
    b) you submit the actual amount spent after the event happens.
  </p>

<?php echo sdsForm() ?>
  Your Username: <?php echo $username ?><br />
  Lounge Name: <?php echo $loungedesc ?><br />
  <b>Funds Remaining: <span class="<?php echo $fundclass ?>"><?php echo $remainingfunds ?></span></b><br />
  <br />
  Description of Event:<br />
  <textarea name="desc" rows="6" cols="60"></textarea><br />
  <br />
  Date of Expense/Event (mm/dd/yyyy): <input name="spenddate" size="10" />

  <p>
    The information you provide, including your username and the date this form
    was submitted, may be publicly viewable on the Simmons DB and during House
    Committee meetings.  <b>Double check this information, you can't change it
    later!</b> You cannot undo this action after clicking "Submit Event."

    <br />

    Be sure to read the next page after submission for instructions on how to
    get approval from your lounge members.
  </p>
  <input type="submit" name="submit" value="Submit Event" />
  <input type="reset" value="Clear Form" />
</form> <!-- end prop submission form for GT-lounges -->

<?php
#'
require("gt-footer.php");
sdsIncludeFooter();
