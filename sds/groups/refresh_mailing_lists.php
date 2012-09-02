<?php
require_once("../sds.php");
sdsRequireGroup('ADMINISTRATORS');

sdsClearReminder("updateListCache");
sdsIncludeHeader("Refreshing mailing lists");
echo "<pre>";
$result = system("sudo /var/www/sds/util/mailman/config.pl");
echo "</pre>";
if (! $result) {
  echo "config.pl failed, sorry.";
  sdsIncludeFooter();
  exit;
}

echo "<pre>";
$result = system("sudo /var/www/sds/util/mailman/members.pl");
echo "</pre>";
if (! $result) {
  echo "members.pl failed, sorry.";
  sdsIncludeFooter();
  exit;
}

echo "Done refreshing mailing lists.  Thanks for playing!";
sdsIncludeFooter();
