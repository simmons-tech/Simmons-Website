<?php
require_once('../sds.php');
require_once('user-administration-tools.inc.php');
sdsRequireGroup('RAC');

sdsIncludeHeader("Clear Room Assignments");

if(!empty($_REQUEST['doit'])) {
  $query = "SELECT username FROM active_directory WHERE room IS NOT NULL AND type = 'U'";
  $result = sdsQuery($query);
  if(!$result)
    contactTech("Could not search directory");
  while($record = pg_fetch_object($result)) {
    if(clearRoom($record->username) === null) {
      echo "<p class='error'>Failed to clear user: ",
	htmlspecialchars($record->username),"</p>\n";
    }
  }
  pg_free_result($result);

  echo "<h2>Rooms cleared</h2>\n";
  echo "<p>You probably want to go add some new assignments now</p>\n";
} else {
?>
<p>Use this form to clear all undergraduate room assignments</p>
<p>WARNING: This cannot be undone</p>
<p>If you are working from a spreadsheet, you may find the
  <a href="<?php echo sdsLink('batchupdate.php') ?>">batch update</a> page
  more convenient</p>

<form action="" method="post">
  <input type="submit" name="doit" value="Clear rooms" />
</form>
<?php
}
sdsIncludeFooter();
