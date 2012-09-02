<?php
require_once("../sds.php");
sdsIncludeHeader("GovTracker","Simmons Government Online");

sdsRequireGroup("USERS");
?>

<h2>House Committee Management</h2>
<h3>Members of the House Committee</h3>
<p><a href="<?php echo sdsLink('viewagenda.php') ?>" class="govaction">View Upcoming Meeting Agenda</a>
   <a href="<?php echo sdsLink('viewunassigned.php') ?>" class="govaction">View Open Proposals</a>
   <a href="<?php echo sdsLink('viewdeleted.php') ?>" class="govaction">View Deleted Proposals</a>
   <a href="<?php echo sdsLink('submitproposal.php') ?>" class="govaction">Submit Proposal</a>
</p>

<?php
if(!empty($session->groups['HOUSE-COMM-LEADERSHIP'])) {
?>
<h3>Committee Administrators</h3>
<p><a href="<?php echo sdsLink('newagenda.php') ?>" class="govaction">Create Agenda</a>
   <a href="<?php echo sdsLink('submitagenda.php') ?>" class="govaction">Submit Agenda</a>
   <a href="<?php echo sdsLink('downloadagenda.php') ?>" class="govaction">View Meeting Presentation</a>
</p>

<?php
} // end leaders only block
?>
<hr />

<h3>Policy Search</h3>
<p>You can search for proposals by any words in their title, summary, or full
  text.  If you know the proposal number, you can go immediately to that
  proposal.</p>
<form action="viewproposal.php" method="get">
  <?php echo sdsForm() ?>
  <label>Proposal Number: <input type="text" name="pid" size="3" /></label> 
  <input type="submit" value="Go">
</form>

<form action="search.php" method="get">
  <?php echo sdsForm() ?>
  <label>Query:  <input type="text" name="stext" size="30" /></label>
  <input type="submit" value="Search">
</form>

<hr />
<h3>Past Meetings</h3>
<p style="font-style:italic">Note that proposals are listed according to the
  most recent meeting in which they were voted upon.  Also, proposals that were
  tabled but have not yet been untabled can only be seen above at View Open
  Proposals.</p>

<?php
# find agendas which are finished, in reverse chrono order
$query = <<<ENDQUERY
SELECT agendaid,meetingtitle,
       to_char(meetingdate,'FMMonth FMDD, YYYY') AS meetingdatestr,
       CASE WHEN EXTRACT('month' FROM meetingdate) < 7 THEN 'Spring '
            ELSE 'Fall ' END
         || EXTRACT('year' FROM meetingdate) AS term
FROM gov_agendas
WHERE status = 'completed'
ORDER BY meetingdate DESC
ENDQUERY;
$result = sdsQuery($query);
if(!$result)
  contactTech("Could not find agendas");
if(pg_num_rows($result) == 0) {
  echo "<p>No meeting information</p>\n";
} else {
  print "<ul>\n";
  $lastterm = '';
  while($data = pg_fetch_object($result)) {
    if($data->term !== $lastterm) {
      if($lastterm !== '')
	echo "    </ul>\n  </li>\n";
      echo "  <li>",htmlspecialchars($data->term),"\n    <ul>\n";
      $lastterm = $data->term;
    }
    echo "      <li><a href='",
      sdsLink('viewoldagenda.php',"aid=$data->agendaid"), "'>",
      htmlspecialchars($data->meetingtitle)," &mdash; ",
      htmlspecialchars($data->meetingdatestr),"</a></li>\n";
  }
  echo "    </ul>\n";
  echo "  </li>\n";
  echo "</ul>\n";
}

include("gt-footer.php");
sdsIncludeFooter();
