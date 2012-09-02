<?php
require_once("../sds.php");
sdsRequireGroup("RAC");
sdsIncludeHeader("Room Status");

function display_rooms($condition) {
  $query = <<<ENDQUERY
SELECT room,rooms.type,count(username) AS occupancy
FROM rooms LEFT JOIN directory USING (room)
GROUP BY room,rooms.type
HAVING $condition
ORDER BY CASE WHEN room SIMILAR TO '%[[:digit:]]%' THEN to_number(room,'9999')
              ELSE 9999 END ASC,
         room ASC
ENDQUERY;

  $result = sdsQuery($query);
  if(!$result)
    contactTech("Could not search rooms");
?>
<table>
  <tr>
    <th>Room</th>
    <th>Type</th>
    <th>Occupancy</th>
  </tr>
<?php
  while($data = pg_fetch_object($result)) {
    echo "  <tr>\n";
    echo "    <td><a href='",
      sdsLink('../directory/list.php',"room=$data->room"), "'>",
      $data->room, "</a></td>\n";
    echo "    <td>", $data->type, "</td>\n";
    echo "    <td>", $data->occupancy, "</td>\n";
    echo "  </tr>\n";
  }
  echo "</table>\n";
  pg_free_result($result);
}

echo "<h2>Overfull Rooms</h2>\n";
$condition = <<<ENDCOND
count(username) > CASE rooms.type WHEN 'Single' THEN 1
                                  WHEN 'Double' THEN 2
                                  ELSE 10 END
ENDCOND;
display_rooms($condition);

echo "<h2>Underfull Rooms</h2>\n";
$condition = <<<ENDCOND
count(username) < CASE rooms.type WHEN 'Single' THEN 1
                                  WHEN 'Double' THEN 2
                                  ELSE 0 END
ENDCOND;
display_rooms($condition);

sdsIncludeFooter();
