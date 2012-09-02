<?php
require_once("../sds.php");
sdsRequireGroup("RAC");
sdsIncludeHeader("Room History");

function display_history($cond) {
  $query = <<<ENDQUERY
SELECT username,room,
       to_char(movein,'YYYY Month DD') AS movein_formatted,
       to_char(moveout,'YYYY Month DD') AS moveout_formatted
FROM old_room_assignments
WHERE $cond
ORDER BY moveout DESC NULLS FIRST, movein DESC
ENDQUERY;
  $result = sdsQuery($query);
  if(!$result)
    contactTech("Could not read history");
?>
<table class="directory">
  <tr>
    <th>Resident</th>
    <th>Username</th>
    <th>Room</th>
    <th>Move In</th>
    <th>Move Out</th>
  </tr>
<?php
  while($data = pg_fetch_object($result)) {
    echo "  <tr>\n";
    echo "    <td>",sdsGetFullName($data->username),"</td>\n";
    echo "    <td>",$data->username,"</td>\n";
    echo "    <td>",$data->room,"</td>\n";
    echo "    <td>",$data->movein_formatted,"</td>\n";
    echo "    <td>",$data->moveout_formatted,"</td>\n";
    echo "  </tr>\n";
  }
  pg_free_result($result);
}

if(isset($_REQUEST['room'])) {
  $room_esc = sdsSanitizeString($_REQUEST['room']);
  display_history("room = '$room_esc'");
} elseif(isset($_REQUEST['username'])) {
  $username_esc = sdsSanitizeString($_REQUEST['username']);
  display_history("username = '$username_esc'");
} else {
?>
<form action="" method="POST">
  Room:
  <input type="text" name="room" />
  <input type="submit" value="Search by Room" />
</form>
<form action="" method="POST">
  Username:
  <input type="text" name="username" />
  <input type="submit" value="Search by Username" />
</form>
<?php
}

sdsIncludeFooter();
