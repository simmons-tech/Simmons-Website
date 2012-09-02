<?php
require_once('../sds.php');
sdsRequireGroup("LOUNGE-CHAIRS");

sdsIncludeHeader("Lounge Membership Reset");

echo "<p>Removing all users from lounges...</p>\n";

$query = "UPDATE directory SET lounge=null,loungevalue=null";
$result = sdsQuery($query);
if(!$result) {
  contactTech("Removal failed");
}
pg_free_result($result);

echo "<p>Done.</p>\n";
echo '<p>Return to <a href="',sdsLink("./"),
  "\">Lounge Administration</a></p>\n";
sdsIncludeFooter();
