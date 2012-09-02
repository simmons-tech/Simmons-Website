<?php

require_once("../sds.php");

sdsRequireGroup("USERS");
sdsIncludeHeader("Simmons Hall Rooming Lottery");

?>

<table class="rooming">
  <tr>
    <th>Pick</th>
    <th>Name</th>
    <th>Username</th>
    <th>Block</th>
    <th>Room</th>
    <th>Year</th>
  </tr>

<?php

$query = "SELECT username,rooming.room,firstname,lastname,block_num,year,adjusted_pick FROM rooming JOIN directory USING (username) ORDER BY adjusted_pick";
$result = sdsQuery($query);
if(!$result)
  contactTech("Could not read lottery");

while($data = pg_fetch_object($result)) {
  echo "  <tr>\n";
  echo "    <td>",$data->adjusted_pick,"</td>\n";
  echo "    <td class='name'>",
    htmlspecialchars($data->lastname . ", " . $data->firstname),"</td>\n";
  echo "    <td>",htmlspecialchars($data->username),"</td>\n";
  echo "    <td>",$data->block_num,"</td>\n";
  echo "    <td>",$data->room,"</td>\n";
  echo "    <td>",$data->year,"</td>\n";
  echo "  </tr>\n";
}
pg_free_result($result);

echo "</table>\n";

echo "<hr />\n";

echo "<table class='rooming'>\n";
echo "  <caption>Rooms that are still open.</caption>\n";

echo "  <tr>\n";

$query = <<<ENDQUERY
SELECT room FROM rooms LEFT JOIN rooming USING (room)
GROUP BY room,type
HAVING (type='Single' AND count(username) < 1) OR
       (type='Double' AND count(username) < 2)
ENDQUERY;
$result = sdsQuery($query);
if(!$result)
  contactTech("Could not search rooms");
for($i=0;$data = pg_fetch_object($result);$i++) {
  if ($i % 9 == 0) {
    echo "  </tr>\n  <tr>\n";
  }
  echo "    <td>",$data->room,"</td>\n";
}
pg_free_result($result);

echo "  </tr>\n";
echo "</table>\n";

$query = <<<ENDQUERY
SELECT room,size FROM rooms 
WHERE type='Single' AND NOT frosh AND
      room NOT IN (SELECT room FROM rooming WHERE room IS NOT NULL)
ORDER BY size DESC
ENDQUERY;
$result = sdsQuery($query);
if(!$result)
  contactTech("Could not search rooms");

?>
<table class="rooming">
  <caption>The following are open singles and their room sizes</caption>
  <tr>
    <th>Room</th>
    <th>Size</th>
  </tr>
<?php
while($data = pg_fetch_object($result)) {
  echo "  <tr>\n";
  echo "    <td>",$data->room,"</td>\n";
  echo "    <td>",$data->size,"</td>\n";
  echo "  </tr>\n";
}
pg_free_result($result);

echo "</table>\n";	

$query = <<<ENDQUERY
SELECT room,size FROM rooms
WHERE type='Double' AND NOT frosh AND
      room NOT IN (SELECT room FROM rooming WHERE room IS NOT NULL)
ORDER BY size DESC
ENDQUERY;
$result = sdsQuery($query);
if(!$result)
  contactTech("Could not search rooms");

?>
<table class="rooming">
  <caption>The following are open doubles and their room sizes</caption>
  <tr>
    <th>Room</th>
    <th>Size</th>
  </tr>
<?php
while($data = pg_fetch_object($result)) {
  echo "  <tr>\n";
  echo "    <td>",$data->room,"</td>\n";
  echo "    <td>",$data->size,"</td>\n";
  echo "  </tr>\n";
}
pg_free_result($result);

echo "</table>\n";

sdsIncludeFooter();
