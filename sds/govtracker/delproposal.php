<?php
require_once("../sds.php");
sdsIncludeHeader("GovTracker","Simmons Government Online");

sdsRequireGroup("HOUSE-COMM-LEADERSHIP");

$propid = (int) $_REQUEST['pid'];

$query = <<<ENDQUERY
SELECT title,agendaid,status
FROM gov_active_proposals LEFT JOIN gov_agendas USING (agendaid)
WHERE propid=$propid AND decision IS NULL
ENDQUERY;
$result = sdsQuery($query);
if(!$result)
  contactTech("Could not search proposals");

if(pg_num_rows($result) == 0) {
?>
<h2>Proposal Cannot be Deleted</h2>

<p>The proposal already has an assigned House Decision &mdash you can't delete
  it now.</p>

<?php
#'
} else {
  $data = pg_fetch_object($result);
  $reason = getStringArg('reason');

  if(isset($data->status) and $data->status !== 'open') {
?>
<h2>Proposal Cannot be Deleted</h2>

<p>The proposal is on a closed agenda &mdash you can't delete it now.</p>

<?php
#'
  } elseif($reason !== '') {

    $username_esc = pg_escape_string($session->username);
    $query = '';
    if(isset($data->agendaid))
      $query = "UPDATE gov_agendas SET usersub='$username_esc',datesub='now' WHERE agendaid='$data->agendaid';";

    $reason_esc = pg_escape_string($reason);
    $log_esc = pg_escape_string("<li>Deleted by $username_esc on ".
				date('j F Y \a\t H:i')."</li>");
    $query .= <<<ENDQUERY
UPDATE gov_proposals
SET agendaid=null,agendaorder=null,deletedby='$username_esc',
    deletereason='$reason_esc',record='$log_esc'||record
WHERE propid='$propid';
ENDQUERY;

    $delresult = sdsQuery($query);
    if(!$delresult or pg_affected_rows($delresult) != 1)
      contactTech("Could not delete proposal");
    pg_free_result($delresult);
?>

<h2>Proposal Deleted</h2>

<p>The proposal has been deleted (your username has been recorded).</p>

<h3>Oops! I didn't mean to do that!</h3>
<p>Don't worry &mdash; the content of the proposal has not been lost.  Simply
  e-mail <a href="mailto:simmons-tech@mit.edu">simmons-tech@mit.edu</a> and
  tell them to "Undelete Proposition ID# <?php echo $propid ?>. (set deletedby
  = null)"  Be sure to include that ID.</p>

<?php

  } else {
?>
<form action="delproposal.php" method="post">
<?php echo sdsForm() ?>

<input name="pid" type="hidden" value="<?php echo $propid ?>" />
<h2>Deleting proposal #<?php echo $propid,': ',htmlspecialchars($data->title) ?></h2>
<p>Go back if you do not wish to delete.</p>
<p>Enter a valid reason for deleting this proposal:<br />
  <textarea name="reason" rows="4" cols="60"></textarea>
</p>
<p>Note that deleting a proposal does not erase its history.  The proposal
  will still be visibile to all residents on the
  <a href="<?php echo sdsLink('viewdeleted.php') ?>">Deleted Proposals</a>
  page.</p>
<input type="submit" value="Delete" />
</form>
<?
  }
}
pg_free_result($result);

include("gt-footer.php");
sdsIncludeFooter();
