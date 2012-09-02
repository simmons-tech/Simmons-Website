<?php
require_once('../sds.php');
sdsRequireGroup('USERS');
sdsIncludeHeader("Simmons GRTs");

$username_esc = pg_escape_string($session->username);

$query = "SELECT grt FROM rooms JOIN directory USING (room) WHERE username='$username_esc'";
$result = sdsQuery($query);
if(!$result)
  contactTech("Cannot search directory");
if(pg_num_rows($result) == 1) {
  list($grt) = pg_fetch_array($result);
} else {
  $grt = '';
}
pg_free_result($result);

$query = <<<ENDQUERY
SELECT username,COALESCE(title||' ','')||firstname||' '||lastname AS name,
       room,phone,email,grt
FROM active_directory JOIN rooms USING (room)
WHERE active_directory.type='GRT'
ORDER BY grt,username
ENDQUERY;

$result = sdsQuery($query);
if(!$result)
  contactTech("Could not search directory");

?>

<table class="grttable">
  <tr>
    <th>Name</th>
    <th>Room</th>
    <th>Phone</th>
    <th>Email</th>
  </tr>
<?php

$room = '';
$oddrow = true;
while($record = pg_fetch_array($result)) {
  if($record['room'] !== $room) {
    $room = $record['room'];
    $oddrow = ! $oddrow;
  }
  echo "  <tr class='",$oddrow?'oddrow':'evenrow',
    $grt===$record['grt']?' mysection':'',"'>\n";
  echo "    <td><a href='",
    sdsLink('entry.php','username='.urlencode($record['username'])),"'>",
    htmlspecialchars($record['name']),"</a></td>\n";
  echo "    <td>",htmlspecialchars($record['room']),"</td>\n";
  echo "    <td>",htmlspecialchars($record['phone']),"</td>\n";
  echo "    <td><a href='mailto:",
    htmlspecialchars($record['email'],ENT_QUOTES),"'>",
    htmlspecialchars($record['email']),"</a></td>\n";
  echo "  </tr>\n";
}
pg_free_result($result);
echo "</table>\n";

sdsIncludeFooter();
