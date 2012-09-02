<?php
# add or remove groups from groups

# This script will not work on on adhoc groups, as they should be modified
# through the mailing list pages.
require_once('../sds.php');
sdsRequireGroup('ADMINISTRATORS');

$subgroup = getStringArg('subgroup');
$subgroup_esc = pg_escape_string($subgroup);
$supergroup = getStringArg('supergroup');
$supergroup_esc = pg_escape_string($supergroup);
$subgroup_disp = htmlspecialchars($subgroup);
$supergroup_disp = htmlspecialchars($supergroup);

# check that the groups are not adhoc
$query = "SELECT 1 FROM sds_groups_public WHERE groupname='$supergroup_esc'";
$result = sdsQuery($query);
if(!$result or pg_num_rows($result)==0) {
  sdsErrorPage("Group Modification Aborted",
	       "$supergroup_disp either does not exist or is an adhoc group. Adhoc groups should be modified through thte mailing list page.");
}
pg_free_result($result);
$query = "SELECT 1 FROM sds_groups_public WHERE groupname='$subgroup_esc'";
$result = sdsQuery($query);
if(!$result or pg_num_rows($result)==0) {
  sdsErrorPage("Group Modification Aborted",
	       "$subgroup_disp either does not exist or is an adhoc group. Adhoc groups should be modified through thte mailing list page.");
}
pg_free_result($result);

if($_REQUEST['action']==='remove') {
# check the subgroup is in the supergroup
  $query = "SELECT 1 FROM sds_groups_in_groups WHERE subgroup='$subgroup_esc' AND supergroup='$supergroup_esc'";
  $result = sdsQuery($query);
  if(!$result or pg_num_rows($result) != 1) {
    sdsErrorPage("Remove Aborted",
		 "$subgroup_disp does not appear to be in $supergroup_disp");
  }
  pg_free_result($result);
# do the delete
  $query = "DELETE FROM sds_groups_in_groups WHERE subgroup='$subgroup_esc' AND supergroup='$supergroup_esc'";
  $result = sdsQuery($query);
  if(!$result or pg_affected_rows($result) != 1) {
    sdsErrorPage("Remove Error",
		 "Error removing $subgroup_disp from $supergroup_disp");
  }
  pg_free_result($result);
  header("Location: " . SDS_BASE_URL .
	 sdsLink("administrators/index.php","group=$supergroup_disp",true).
	 '#groupstart');
  exit;
} elseif($_REQUEST['action']==='add') {
  $hosts_allow = maybeStripslashes($_REQUEST['hosts_allow']);
  if(!strlen($hosts_allow)) { $hosts_allow = '%'; }
# check the subgroup is not in the supergroup
  $query = "SELECT 1 FROM sds_groups_in_groups WHERE subgroup='$subgroup_esc' AND supergroup='$supergroup_esc'";
  $result = sdsQuery($query);
  if(!$result or pg_num_rows($result) != 0) {
    sdsErrorPage("Add Aborted",
		 "$subgroup_disp is already in $supergroup_disp");
  }
  pg_free_result($result);
# do the insert
  $insertarray = array('subgroup' => $subgroup,
		       'supergroup' => $supergroup,
		       'hosts_allow' => $hosts_allow);
  $query = "INSERT INTO sds_groups_in_groups " . sqlArrayInsert($insertarray);
  $result = sdsQuery($query);
  if(!$result or pg_affected_rows($result) != 1) {
    sdsErrorPage("Add Error",
		 "Error adding $subgroup_disp to $supergroup_disp");
  }
  pg_free_result($result);
  header("Location: " . SDS_BASE_URL .
	 sdsLink("administrators/index.php",
		 "group=$supergroup_disp&groupuser=$subgroup_disp",true).
	 '#groupstart');
  exit;
}
# If we made it here, action was not 'add' or 'remove'
sdsErrorPage("Group Edit Error","Invalid action");
