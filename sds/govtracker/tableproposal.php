<?php
require_once("../sds.php");
sdsIncludeHeader("GovTracker","Simmons Government Online");

sdsRequireGroup("HOUSE-COMM-LEADERSHIP");
$propid = (int) $_REQUEST['pid'];

$query = "SELECT agendaid FROM gov_agendas WHERE status='open'";
$result = sdsQuery($query);
if(!$result)
  contactTech("Could not search agendas");
list($aid) = pg_fetch_array($result);
pg_free_result($result);

if(!isset($aid)) {
?>
<h2 class='error'>There is no agenda currently open</h2>
<?php
} else {
  $username_esc = pg_escape_string($session->username);
  $log_esc = pg_escape_string("<li>Removed from agenda</li>");
  $query = <<<ENDQUERY
UPDATE gov_proposals
SET agendaid=null,agendaorder=null,userassign='$username_esc',
    record='$log_esc'||record
WHERE propid='$propid' AND agendaid='$aid' AND
      decision IS NULL AND deletedby IS NULL;
ENDQUERY;

  $result = sdsQuery($query);
  if(!$result)
    contactTech("Could not table proposal");
  if(pg_affected_rows($result) != 1) {
?>
<h2 class='error'>Not tabled</h2>
<p>Either the proposal does not exist, or it is ineligible for tabling.</p>
<?php
  } else {
    pg_free_result($result);
    $query = "UPDATE gov_agendas SET usersub='$username_esc',datesub=now() WHERE agendaid='$aid'";
    $result = sdsQuery($query);
    if(!$result or pg_affected_rows($result) != 1)
      contactTech("Could not update agenda");

?>

<h2>Proposal Tabled</h2>

<p>The proposal has been removed from all agendas, and placed in the
  <a href="<?php echo sdsLink('viewunassigned.php') ?>">"open" pool</a>.</p>

<h3>Oops! I didn't mean to do that!</h3>
<p>Don't worry &mdash; you can
  <a href="<?php echo sdsLink('assignproposal.php',"pid=$propid") ?>">reassign
  the proposal to the upcoming meeting</a>.</p>

<?php

include("gt-footer.php");
sdsIncludeFooter();
