<?php
require_once("../sds.php");
sdsIncludeHeader("GovTracker","Simmons Government Online");

sdsRequireGroup("HOUSE-COMM-LEADERSHIP");

$propid = (int) $_REQUEST['pid'];

$query = <<<ENDQUERY
SELECT agendaid,meetingtitle,
       to_char(meetingdate,'FMMonth FMDD, YYYY') AS meetingdatestr
FROM gov_agendas
WHERE status = 'open'
ENDQUERY;

$aresult = sdsQuery($query);
if(!$aresult)
  contactTech("Could not search agendas");
if(pg_num_rows($aresult) != 1) {
?>
<h2>Cannot Assign Proposal to Agenda</h2>
<h3>No meeting information</h3>
<p style="font-weight:bold">The House Chair has not yet opened the agenda or
  submitted information for the upcoming meeting.</p>

<?php
} else {
  $adata = pg_fetch_object($aresult);
  $aid = $adata->agendaid;

  $query = "SELECT propid,title FROM gov_proposals WHERE propid='$propid' AND agendaid IS NULL AND deletedby IS NULL";
  $presult = sdsQuery($query);
  if(!$presult)
    contactTech("Could not search proposals");
  if(pg_num_rows($presult) != 1) {
?>
<h2>Cannot Assign Proposal to Agenda</h2>
<h3>Not a valid proposal</h3>
<p style="font-weight:bold">This propsal either does not exist or is ineligible
  for assignment to the upcoming meeting.</p>

<?php
  } else {
    $pdata = pg_fetch_object($presult);

    $username_esc = pg_escape_string($session->username);
    $log_esc = pg_escape_string("<li>Assigned to $adata->meetingtitle &mdash; $adata->meetingdatestr</li>");
    $query = <<<ENDQUERY
UPDATE gov_proposals
SET agendaid='$aid',userassign='$username_esc',record='$log_esc'||record,
    agendaorder=COALESCE(maxorder+1,0)
FROM (SELECT MAX(agendaorder) AS maxorder FROM gov_proposals
      WHERE agendaid='$aid') AS others
WHERE gov_proposals.propid='$propid';
ENDQUERY;

    $query .= "UPDATE gov_agendas SET usersub='$username_esc',datesub=now() WHERE agendaid='$aid';"; 

    $result = sdsQuery($query);
    if(!$result)
      contactTech("Could not assign proposal");
    pg_free_result($result);

?>

<h2>Proposal Assigned to Agenda</h2>

<p>Proposal
  #<?php echo $pdata->propid,': "',htmlspecialchars($pdata->title) ?>"
  has been assigned to the agenda
  "<?php echo htmlspecialchars($adata->meetingtitle) ?>" scheculed for
  <?php echo htmlspecialchars($adata->meetingdatestr) ?>.</p>

<h3>Oops! I didn't mean to do that!</h3>
<p>Don't worry &mdash; you can defer the proposal to another meeting by
  <a href="<?php echo sdsLink('tableproposal.php',"pid=$propid") ?>">tabling
  the proposal</a>.</p>

<?php
  }
  pg_free_result($presult);
}
pg_free_result($aresult);

include("gt-footer.php");
sdsIncludeFooter();
