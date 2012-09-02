<?php
require_once("../sds.php");
sdsIncludeHeader("GovTracker","Simmons Government Online");

sdsRequireGroup("HOUSE-COMM-LEADERSHIP");

echo "<h2>Submit (Close) Agenda</h2>\n";

echo "<h4>Do not use HTML tags.</h4>\n";

$query = <<<ENDQUERY
SELECT agendaid,meetingtitle,
       to_char(meetingdate,'FMMonth FMDD, YYYY') AS meetingdatestr,
       to_char(closingdate,'FMMonth FMDD, YYYY') AS closingdatestr
FROM gov_agendas
WHERE status = 'open'
ENDQUERY;
$result = sdsQuery($query);
if(!$result)
  contactTech("Could not search agendas");
if(pg_num_rows($result) != 1) {
?>
<h3>No meeting information</h3>
<p>The House Chair has not yet opened the agenda for the upcoming meeting.</p>

<?php
} else {
  $data = pg_fetch_object($result);

# new submission?
  if(!empty($_REQUEST["submit"])) {

    $update = array();
    $update['hchairannounce'] =
      trim(maybeStripslashes($_REQUEST['hchairannounce']));
    $update['presannounce'] =
      trim(maybeStripslashes($_REQUEST['presannounce']));
    $update['committeereps'] =
      trim(maybeStripslashes($_REQUEST['committeereps']));
    $update['closedate'] = 'now';
    $update['usersub'] = $session->username;
    $update['datesub'] = 'now';
    $update['status'] = 'closed';

    $query = "UPDATE gov_agendas SET " . sqlArrayUpdate($update) .
      " WHERE agendaid = $data->agendaid";
    $updateresult = sdsQuery($query);
    if(!$updateresult or pg_affected_rows($updateresult) != 1)
      contactTech("Could not close agenda");
    pg_free_result($updateresult);

    echo "<p class='success'>Your closed agenda submission has been received.</p>\n";
  } else {

?>
<form action="submitagenda.php" method="post">
<?php echo sdsForm() ?>

<input type="hidden" name="agendaid" value="<?php echo $data->agendaid ?>" />
<table class="proposaldetail">
  <tr>
    <td>Primary Author:</td>
    <td><?php echo sdsGetFullname($session->username) ?></td>
  </tr>

  <tr>
    <td>Meeting Title:</td>
    <td><?php echo htmlspecialchars($data->meetingtitle) ?></td>
  </tr>

  <tr>
    <td>Meeting Date:</td>
    <td><?php echo htmlspecialchars($data->meetingdatestr) ?></td>
  </tr>

  <tr>
    <td>Closing Date for Agenda:</td>
    <td><?php echo htmlspecialchars($data->closingdatestr) ?></td>
  </tr>

  <tr>
    <td>House Chair's Announcements:</td>
    <td><textarea name="hchairannounce" rows="15" cols="45"></textarea><br />
      <span class="inputdetail">Do not use HTML tags.</span></td>
  </tr>
  <tr>
    <td>President's Announcements:</td>
    <td><textarea name="presannounce" rows="15" cols="45"></textarea><br />
      <span class="inputdetail">Do not use HTML tags.</span></td>
  </tr>
  <tr>
    <td>Committee Reports:</td>
    <td><textarea name="committeereps" rows="15" cols="45"></textarea><br />
      <span class="inputdetail">Do not use HTML tags.</span></td>
  </tr>
</table>

<p>This agenda will be publicly viewable on the Simmons DB and during House
  Committee meetings.  You cannot undo this action after clicking
  "Close Agenda".</p>

<input type="submit" name="submit" value="Close Agenda" />
<input type="reset" value="Clear Form" />
</form>
<?php
  }
}
pg_free_result($result);

include("gt-footer.php");
sdsIncludeFooter();
