<?php
require_once('../sds.php');
sdsRequireGroup("FINANCIAL-ADMINS");
sdsIncludeHeader("Lounge Allocations");

if(isset($_REQUEST['clear'])) {
  $query = "UPDATE lounges SET allocation=null WHERE active";
  $result = sdsQuery($query);
  if(!$result)
    contactTech("Could not clear allocations");
  pg_free_result($result);
} elseif(isset($_REQUEST['allocation']) and
	 is_array($_REQUEST['allocation'])) {
  foreach($_REQUEST['allocation'] as $lounge => $allocation) {
    $lounge_esc = sdsSanitizeString($lounge);
    $query = "SELECT 1 FROM active_lounges WHERE lounge='lounge-$lounge_esc'";
    $result = sdsQuery($query);
    if(!$result)
      contactTech("Could not search lounges");

    if(pg_num_rows($result) != 1) {
      echo "<p class='error'>Unknown lounge",htmlspecialchars($lounge),
	"</p>\n";
    } elseif($allocation === '') {
      $query = "UPDATE lounges SET allocation=null WHERE lounge='lounge-$lounge_esc'";
      $result = sdsQuery($query);
      if(!$result or pg_affected_rows($result) != 1)
	contactTech("Could not update allocation");
      pg_free_result($result);
    } elseif(!preg_match('/^\d+(?:\.(?:\d\d)?)?$/',$allocation)) {
      echo "<p class='error'>Allocation of ",htmlspecialchars($allocation),
	" for lounge ",htmlspecialchars($lounge),
	" does not look like a dollar amount.</p>\n";
    } else {
      $query = "UPDATE lounges SET allocation='$allocation' WHERE lounge='lounge-$lounge_esc'";
      $result = sdsQuery($query);
      if(!$result or pg_affected_rows($result) != 1)
	contactTech("Could not update allocation");
      pg_free_result($result);
    }
  }
}

$query = "SELECT loungeid,description,predalloc,allocation FROM lounge_summary_report ORDER BY loungeid";
$result = sdsQuery($query); 
if(!$result)
  contactTech("Could not find lounge allocations");

?>
<form action="editallocations.php" method="post">
<table class="loungeinfo">
  <tr>
    <th>Lounge ID</th>
    <th>Name</th>
    <th>Predicted Allocation</th>
    <th>Allocation</th>
  </tr>
<?php

$parity = 'oddrow';
while($record = pg_fetch_array($result)) {
  $parity = $parity === 'oddrow' ? 'evenrow' : 'oddrow';
  unset($ans);
  if(!preg_match('/^lounge-(.*)$/',$record['loungeid'],$ans))
    contactTech("Malformed lounge ID");
  $loungeid = htmlspecialchars($ans[1],ENT_QUOTES);

  echo "  <tr class='",$parity,"'>\n";
  echo "    <td>",$loungeid,"</td>\n";
  echo "    <td>",htmlspecialchars($record['description']),"</td>\n";
  echo "    <td class='money'>",$record['predalloc'],"</td>\n";
  echo "    <td class='money'><input type='text' name='allocation[",$loungeid,
    "]' value='",$record['allocation'],"' size='7' /></td>\n";
  echo "  </tr>\n";
}
?>
  <tr>
    <td></td>
    <td></td>
    <td></td>
    <td style="text-align:right"><input type="submit" value="Update" /></td>
  </tr>
</table>
<p><input type="submit" name="clear" value="Clear All" /> &mdash; This will
  cause the <a href="<?php echo sdsLink('../loungeexpense/') ?>#summary">Lounge
  Summary Report</a> to display predicted allocations.</p>
</form>

<?php
sdsIncludeFooter();
