<?php
# add or remove users from groups
# This script will not work on on adhoc groups, as they should be modified
# through the mailing list pages.
require_once('../sds.php');
sdsRequireGroup('ADMINISTRATORS');

$user = getStringArg('user');
$group = getStringArg('group');
$user_esc = pg_escape_string($user);
$group_esc = pg_escape_string($group);
$user_disp = htmlspecialchars($user);
$group_disp = htmlspecialchars($group);

# check that the group is not adhoc
$query = "SELECT 1 FROM sds_groups_public WHERE groupname='$group_esc'";
$result = sdsQuery($query);
if(!$result or pg_num_rows($result)==0) {
  sdsErrorPage("Group Modification Aborted",
	       "$group_disp either does not exist or is an adhoc group. Adhoc groups should be modified through thte mailing list page.");
}
pg_free_result($result);
if($_REQUEST['action']==='remove') {
# check the user is in the group
  $query = "SELECT 1 FROM sds_users_in_groups WHERE username='$user_esc' AND groupname='$group_esc'";
  $result = sdsQuery($query);
  if(!$result or pg_num_rows($result) != 1) {
    sdsErrorPage("Remove Aborted",
		 "$user_disp does not appear to be manually in $group_disp");
  }
  pg_free_result($result);
# do the delete
  $query = "DELETE FROM sds_users_in_groups WHERE username='$user_esc' AND groupname='$group_esc'";
  $result = sdsQuery($query);
  if(!$result or pg_affected_rows($result) != 1) {
    sdsErrorPage("Remove Error",
		 "Error removing $user_disp from $group_disp");
  }
  pg_free_result($result);
  header("Location: " . SDS_BASE_URL .
	 sdsLink("administrators/index.php",
		 "user=$user_disp&group=$group_disp",true));
  exit;
} elseif($_REQUEST['action']==='add') {
  $hosts_allow = maybeStripslashes($_REQUEST['hosts_allow']);
  if(!strlen($hosts_allow)) { $hosts_allow = '%'; }
# check the user is not in the group
  $query = "SELECT 1 FROM sds_users_in_groups WHERE username='$user_esc' AND groupname='$group_esc'";
  $result = sdsQuery($query);
  if(!$result or pg_num_rows($result) != 0) {
    sdsErrorPage("Add Aborted",
		 "$user_disp is already manually in $group_disp");
  }
  pg_free_result($result);
# do the insert
  $insertarray = array('username' => $user,
		       'groupname' => $group,
		       'hosts_allow' => $hosts_allow);
  $query = "INSERT INTO sds_users_in_groups " . sqlArrayInsert($insertarray);
  $result = sdsQuery($query);
  if(!$result or pg_affected_rows($result) != 1) {
    sdsErrorPage("Add Error",
		 "Error adding $user_disp to $group_disp");
  }
  pg_free_result($result);
  header("Location: " . SDS_BASE_URL .
	 sdsLink("administrators/index.php",
		 "user=$user_disp&usergroup=$group_disp&group=$group_disp&groupuser=$user_disp",true));
  exit;
}
# If we made it here, action was not 'add' or 'remove'
sdsErrorPage("Group Edit Error","Invalid action");
