<?php
require_once('../sds.php');
sdsRequireGroup('ADMINISTRATORS');

$subgroup = getStringArg('subgroup');
$subgroupdisplay = htmlspecialchars($subgroup);
$subgroup_esc = pg_escape_string($subgroup);
$supergroup = getStringArg('supergroup');
$supergroupdisplay = htmlspecialchars($supergroup);
$supergroup_esc = pg_escape_string($supergroup);

if(strlen($subgroup)) {
  sdsIncludeHeader("Add Supergroup");
?>

<h2>Add group <?php echo $subgroupdisplay ?> to:</h2>
<form action="groupedit_group.php" method="post">
  <?php echo sdsForm() ?>
  <input type="hidden" name="action" value="add" />
  <input type="hidden" name="subgroup" value="<?php echo $subgroupdisplay ?>" />
  <select name="supergroup" size="10">
<?php
  $query = <<<ENDQUERY
SELECT groupname FROM sds_groups_public
WHERE groupname!='$subgroup_esc' AND groupname NOT IN
     (SELECT supergroup FROM sds_groups_in_groups WHERE subgroup='$subgroup_esc')
ORDER BY groupname
ENDQUERY;
  $result = sdsQuery($query);
  if($result) {
    while($record = pg_fetch_array($result)) {
      echo "    <option>",htmlspecialchars($record['groupname']),"</option>\n";
    }
    pg_free_result($result);
  }
?>
  </select><br />
  <label>Allowed IPs:
    <input type="text" name="hosts_allow" value="%" />
  </label><br />
  <input type="submit" value="Add to Group" />
</form>
<p>Note that additions will not take effect until the next group refresh</p>
<?php
  sdsIncludeFooter();
  exit;
} elseif(strlen($supergroup)) {
  sdsIncludeHeader("Add Subgroup");
?>
<h2>Add subgroup to <?php echo $supergroupdisplay ?>:</h2>
<form action="groupedit_group.php" method="post">
  <?php echo sdsForm() ?>
  <input type="hidden" name="action" value="add" />
  <input type="hidden" name="supergroup" value="<?php echo $supergroupdisplay ?>" />
  <select name="subgroup" size="10">
<?php
  $query = <<<ENDQUERY
SELECT groupname FROM sds_groups_public
WHERE groupname!='$supergroup_esc' AND groupname NOT IN
     (SELECT subgroup FROM sds_groups_in_groups WHERE supergroup='$supergroup_esc')
ORDER BY groupname
ENDQUERY;
  $result = sdsQuery($query);
  if($result) {
    while($record = pg_fetch_array($result)) {
      echo "    <option>",htmlspecialchars($record['groupname']),"</option>\n";
    }
    pg_free_result($result);
  }
?>
  </select><br />
  <label>Allowed IPs:
    <input type="text" name="hosts_allow" value="%" />
  </label><br />
  <input type="submit" value="Add Subgroup" /><br />
</form>
<p>Note that additions will not take effect until the next group refresh</p>
<?php
  sdsIncludeFooter();
  exit;
}

sdsErrorPage("No Groups Supplied","Please give either a subgroup to be added to a group or a supergroup to add groups to.");
