<?php
require_once("../sds.php");
require_once("directory.inc.php");

# require desk group
sdsRequireGroup("EVERYONE");

# standard page look & feel
sdsIncludeHeader("Simmons Hall Directory");

showDirectorySearchForm();
sdsIncludeFooter(); 
?>
