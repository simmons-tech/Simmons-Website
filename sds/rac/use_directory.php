<?php
require_once("../sds.php");

sdsRequireGroup("RAC");
sdsIncludeHeader("Modify/Remove Directory Entry");
?>

<p>To modify or remove a directory entry, use the RAC-specific
  action links at the bottom of any <a href="<?php echo sdsLink("../directory/"); ?>">directory
  entry</a>.</p>

<?php
sdsIncludeFooter();
?>
