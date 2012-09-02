<?php
require_once('../sds.php');
sdsRequireGroup("USERS");

if(!isset($_REQUEST['eventid'])) {
  sdsIncludeHeader("Cancel Event");
  echo "<h2 class='error'>No event specified</h2>\n";
  include('gt-footer.php');
  sdsIncludeFooter();
  exit;
}

$eid = (int) $_REQUEST['eventid'];
$username_esc = pg_escape_string($session->username);

$query = "SELECT description FROM lounge_expenses WHERE expenseid='$eid' AND usersub = '$username_esc' AND termsold=0 AND NOT finished";
$result = sdsQuery($query);
if(!$result)
  contactTech("Could not search events");

if(pg_num_rows($result) != 1) {
  sdsIncludeHeader("Cancel Event");
  echo "<p class='error'>You cannot cancel this expense, as you are not it's owner.</p>\n";
  include('gt-footer.php');
  sdsIncludeFooter();
  exit;
}

list($description) = pg_fetch_array($result);
pg_free_result($result);

if(isset($_REQUEST['issure'])) {
  if(strtolower($_REQUEST['issure']) !== 'yes') {
    # they changed their mind
    header("Location: " . SDS_BASE_URL .
	   sdsLink("loungeexpense/proposals.php"));
    exit;
  }
  $query = "UPDATE lounge_expenses SET datesubmitted = now(),finished=true,canceled=true WHERE expenseid='$eid'";

  $result = sdsQuery($query);
  if(!$result or pg_affected_rows($result) != 1)
    contactTech("Could not cancel event");
  pg_free_result($result);

  require_once('loungeexpense.inc.php');
  if(!validate_expense($eid)) {
    sdsIncludeHeader("Cancel Event");
    echo "<p class='error'>Your event was canceled, but somehow this was\n";
    echo "  not OK with the event validator. Please contact\n";
    echo "  <a href='mailto:simmons-tech@mit.edu'>simmons-tech@mit.edu</a>\n";
    echo "</p>\n";
    include('gt-footer.php');
    sdsIncludeFooter();
    exit;
  }

  # everything is good, send them to the events page
  header("Location: " . SDS_BASE_URL . sdsLink("loungeexpense/"));
  exit;
}

# ask for confirmation

sdsIncludeHeader("Cancel Event");

echo "<p>",nl2br(htmlspecialchars($description)),"</p>\n";
?>
<form action="cancelexpense.php" method="post">
<? echo sdsForm() ?>
  <input type="hidden" name="eventid" value="<?php echo $eid ?>" />
  <p>Are you sure you want to cancel this event?</p>
  <input type="submit" name="issure" value="Yes" />
  <input type="submit" name="issure" value="No" />
</form>

<?php
include('gt-footer.php');
sdsIncludeFooter();
