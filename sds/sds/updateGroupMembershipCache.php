<?php 
require_once("../sds.php");
require_once("groupMembership.inc.php");
sdsClearReminder("updateGroupCache");  
// ==================== MAIN SCRIPT ======================
sdsIncludeHeader("Updating Membership Cache");

echo "<p>Processing... this may take a minute.</p>\n";

echo "Cleaning up Adhoc Groups:";
flush();
cleanupAdhocGroups();
echo " clean.<br/>\n";

echo "Refreshing Group Membership Cache.<br />\n";
flush();
refreshGroupMembership();

echo "Done!<br />\n";

sdsIncludeFooter();
