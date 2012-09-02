<?php
require_once('../sds.php');
sdsRequireGroup('ADMINISTRATORS');

$user = getStringArg('user');
$userdisplay = htmlspecialchars($user);
$user_esc = pg_escape_string($user);

$group = getStringArg('group');
$groupdisplay = htmlspecialchars($group);
$group_esc = pg_escape_string($group);

if(strlen($user)) {
  sdsIncludeHeader("Add To Group");
?>

<h2>Add <?php echo $userdisplay ?> to:</h2>
<form action="groupedit.php" method="post">
  <?php echo sdsForm() ?>
  <input type="hidden" name="action" value="add" />
  <input type="hidden" name="user" value="<?php echo $userdisplay ?>" />
  <select name="group" size="10">
<?php
  $query = <<<ENDQUERY
SELECT groupname FROM sds_groups_public
WHERE groupname NOT IN
     (SELECT groupname FROM sds_users_in_groups WHERE username='$user_esc')
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
  <input type="submit" value="Add to Group" /><br />
</form>
<p>Note that additions will not take effect until the next group refresh</p>
<?php
  sdsIncludeFooter();
  exit;
} elseif(strlen($group)) {
  sdsIncludeHeader("Add Member");
?>
<h2>Add user to <?php echo $groupdisplay ?>:</h2>
<form action="groupedit.php" method="post">
  <?php echo sdsForm() ?>
  <input type="hidden" name="action" value="add" />
  <input type="hidden" name="group" value="<?php echo $groupdisplay ?>" />
  <select name="user" size="10">
<?php
  $query = <<<ENDQUERY
SELECT username FROM sds_users
WHERE username not in
     (SELECT username FROM sds_users_in_groups WHERE groupname='$group_esc')
ORDER BY username
ENDQUERY;
  $result = sdsQuery($query);
  if($result) {
    while($record = pg_fetch_array($result)) {
      echo "    <option>",htmlspecialchars($record['username']),"</option>\n";
    }
    pg_free_result($result);
  }
?>
  </select><br />
  <label>Allowed IPs:
    <input type="text" name="hosts_allow" value="%" />
  </label><br />
  <input type="submit" value="Add to Group" /><br />
</form>
<p>Note that additions will not take effect until the next group refresh</p>
<?php
  sdsIncludeFooter();
  exit;
}

sdsErrorPage("No Users or Groups Supplied","Please give either a user to be added to a group or a group to add users to.");
