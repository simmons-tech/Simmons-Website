<?php
require_once("../sds.php");
sdsRequireGroup("HOUSE-COMM-LEADERSHIP");

# find an agenda which is closed (but not finished)
$query = <<<ENDQUERY
SELECT meetingtitle,
       to_char(meetingdate,'FMMonth FMDD, YYYY') AS meetingdatestr
FROM gov_agendas
WHERE status = 'closed'
ENDQUERY;
$result = sdsQuery($query);
if(pg_num_rows($result) != 1) {
  sdsIncludeHeader("GovTracker","Simmons Government Online");
?>
<h3>No meeting information</h3>
<p style="font-weight:bold">The House Chair has not yet closed the agenda
  for the upcoming meeting.</p>

<?php
  include("gt-footer.php");
  sdsIncludeFooter();
  return;
} else {
  $data = pg_fetch_object($result);
# we want a frameset
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Frameset//EN"
        "http://www.w3.org/TR/html4/frameset.dtd">
<html>
<head>
  <title>GovTracker - <?php echo htmlspecialchars($data->meetingtitle." on ".
						  $data->meetingdatestr) ?></title>
  <meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1" />
  <link rel="stylesheet" href="https://simmons.mit.edu/simmons.css" type="text/css" /> 
</head>

<frameset cols="20%, 80%">
  <frame name="toc" src="<?php echo sdsLink('download-toc.php') ?>" />
  <frame name="mainpage" src="<?php echo sdsLink('download-main.php','page=intro') ?>" />
  <noframes>
    You need frames for GovTracker!
  </noframes>
</frameset>
</html>
<?php
}
pg_free_result($result);
