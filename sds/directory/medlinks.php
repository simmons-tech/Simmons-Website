<?php
require_once('../sds.php');
sdsRequireGroup('USERS');

sdsIncludeHeader("Medlinks");

if(!empty($session->groups['ADMINISTRATORS']))
  echo "<div><a href='medlinks_setup.php'>Edit list</a></div>\n";

$query = <<<ENDQUERY
SELECT username,
       COALESCE(COALESCE(title||' ','')||firstname||' '||lastname,
                username) AS name,
       room,phone,email
FROM medlinks LEFT JOIN directory USING (username)
WHERE removed IS NULL
ORDER BY ordering
ENDQUERY;

$result = sdsQuery($query);
if(!$result)
  contactTech("Could not list medlinks");

?>

<table class="grttable">
  <tr>
    <th>Name</th>
    <th>Room</th>
    <th>Email</th>
  </tr>
<?php

$oddrow = true;
while($record = pg_fetch_array($result)) {
  $oddrow = ! $oddrow;
  echo "  <tr class='",$oddrow?'oddrow':'evenrow',"'>\n";
  if($record['name'] === 'NOBODY') {
    echo "    <td>Vacant</td>\n";
  } else {
    echo "    <td><a href='",
      sdsLink('entry.php','username='.urlencode($record['username'])),"'>",
      htmlspecialchars($record['name']),"</a></td>\n";
  }
  echo "    <td>",htmlspecialchars($record['room']),"</td>\n";
  echo "    <td><a href='mailto:",
    htmlspecialchars($record['email'],ENT_QUOTES),"'>",
    htmlspecialchars($record['email']),"</a></td>\n";
  echo "  </tr>\n";
}
pg_free_result($result);
echo "</table>\n";

sdsIncludeFooter();
