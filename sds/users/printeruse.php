<?php
require_once("../sds.php");
sdsRequireGroup("USERS");
sdsIncludeHeader("Simmons DB", "Summary of Printer Use");

$username_esc = pg_escape_string($session->username);
$query = "SELECT to_char(date,'FMDD FMMonth YYYY') AS datestr,pages FROM printer_use WHERE username='$username_esc' ORDER BY date DESC";
$result = sdsQuery($query);
if(!$result)
  contactTech("Could not find printer use");
?>
<p>Weeks end at approximately 3:30 AM on the date listed.</p>
<table class="printeruse">
  <tr class='evenrow'>
    <th>Week Ending</th>
    <th>Pages Printed</th>
  </tr>
<?php
$oddrow = false;
while($data = pg_fetch_object($result)) {
  $oddrow = !$oddrow;
  echo "  <tr class='",$oddrow?'oddrow':'evenrow',"'>\n";
  echo "    <td>",htmlspecialchars($data->datestr),"</td>\n";
  echo "    <td>",htmlspecialchars($data->pages),"</td>\n";
  echo "  </tr>\n";
}
pg_free_result($result);
echo "</table>";

sdsIncludeFooter();
