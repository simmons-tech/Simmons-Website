<?php
require_once("../sds.php");
sdsRequireGroup("HOUSE-COMM-LEADERSHIP");

# find an agenda which is closed (but not finished)
$query="SELECT agendaid FROM gov_agendas WHERE status = 'closed'";
$result = sdsQuery($query);
if(!$result)
  contactTech("Could not search agendas");

if(pg_num_rows($result) != 1) {
  sdsIncludeHeader("GovTracker","Simmons Government Online");
?>
<h3>No meeting information</h3>
<p style="font-weight:bold">The House Chair has not yet closed the agenda for
  the upcoming meeting.</p>

<?php
  include("gt-footer.php");
  sdsIncludeFooter();
  return;
} else {
  list($aid) = pg_fetch_array($result);
# we want a list of items on the agenda
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN"
        "http://www.w3.org/TR/html4/loose.dtd">
<html>
<head>
  <meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1" />
  <link rel="stylesheet" href="simmons-gt.css" type="text/css" />
</head>
<body>
<ul class="navbar">
  <li>Free Food!
    <ul>
      <li><a target="mainpage" href="<?php echo sdsLink('download-main.php','page=intro') ?>">Welcome</a></li>
    </ul>
  </li>
  <li>Announcements
    <ul>
      <li><a target="mainpage" href="<?php echo sdsLink('download-main.php','page=hca') ?>">House Chair's Announcements</a></li>
      <li><a target="mainpage" href="<?php echo sdsLink('download-main.php','page=tresrep') ?>">Treasurer's Report</a></li>
      <li><a target="mainpage" href="<?php echo sdsLink('download-main.php','page=presa') ?>">President's Announcements</a></li>
      <li><a target="mainpage" href="<?php echo sdsLink('download-main.php','page=cr')#'" ?>">Committee Reports</a></li>
    </ul>
  </li>
  <li>Proposals
    <ul>
<?php
#list the name of each proposal

  $query = <<<ENDQUERY
SELECT propid,title
FROM gov_active_proposals
WHERE agendaid='$aid'
ORDER BY agendaorder ASC, datesub ASC
ENDQUERY;

  $propresult = sdsQuery($query);
  if(!$propresult)
    contactTech("Could not search proposals");

  while($data = pg_fetch_object($propresult)) {
    echo "      <li><a target='mainpage' href='",
      sdsLink('download-main.php',"page=proposal&amp;id=$data->propid"),"'>",
      htmlspecialchars($data->title),"</a></li>\n";
  }
  pg_free_result($propresult);
?>
    </ul>
  </li>
  <li>Adjourn
    <ul>
      <li><a target="mainpage" href="<?php echo sdsLink('download-main.php','page=finish') ?>">End Meeting</a></li>
    </ul>
  </li>
</ul>

</body>
</html>
<?php
} # end existing agenda
pg_free_result($result);
