<?php
require_once('../sds.php');
sdsRequireGroup('USERS');

sdsIncludeHeader("Student Officers");

if(!empty($session->groups['ADMINISTRATORS']))
  echo "<div><a href='officer_setup.php'>Edit list</a></div>\n";

$query = <<<ENDQUERY
SELECT position_text,username,
       COALESCE(COALESCE(title||' ','')||firstname||' '||lastname,
                username) AS name,
       room,phone,email
FROM officers LEFT JOIN directory USING (username)
WHERE removed IS NULL
ORDER BY ordering
ENDQUERY;

$result = sdsQuery($query);
if(!$result)
  contactTech("Could not list officers");

?>

<table class="grttable">
  <tr>
    <th>Position</th>
    <th>Name</th>
    <th>Room</th>
    <th>Email</th>
  </tr>
<?php

$position = '';
$oddrow = true;
while($record = pg_fetch_array($result)) {
  if($record['position_text'] !== $position) {
    $position = $record['position_text'];
    $oddrow = ! $oddrow;
  }
  echo "  <tr class='",$oddrow?'oddrow':'evenrow',"'>\n";
  echo "    <td>",htmlspecialchars($record['position_text']),"</td>\n";
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
