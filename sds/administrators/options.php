<?php
require_once('../sds.php');
sdsRequireGroup('ADMINISTRATORS');

sdsIncludeHeader('Simmons DB Options');

if(isset($_REQUEST['option'])) {
  $option_esc = sdsSanitizeString($_REQUEST['option']);
  $query = "SELECT value IS NOT NULL AS type_int FROM options WHERE name='$option_esc'";
  $result = sdsQuery($query);
  if($result and pg_num_rows($result)) {
    $record = pg_fetch_array($result);
    unset($value);
    if($record['type_int']==='t') {
      if(strlen($_REQUEST['optionvalue']) and
	 !preg_match('/\D/',$_REQUEST['optionvalue'])) {
	$value = (int) $_REQUEST['optionvalue'];
      }
    } else {
      $value = maybeStripslashes($_REQUEST['optionvalue']);
    }
    if(isset($value)) {
      $updatearray =
	array(($record['type_int']==='t'?'value':'value_string') => $value);
      $query = "UPDATE options SET " . sqlArrayUpdate($updatearray) .
	" WHERE name='$option_esc'";
      $update_result = sdsQuery($query);
      if($update_result and pg_affected_rows($update_result)==1) {
	echo "<h2>Database Updated</h2>\n";
      } else {
	echo "<h2 class='error'>Something went wrong on the update</h2>\n";
      }
      if($update_result) pg_free_result($update_result);
    }
  }
  if($result) pg_free_result($result);
}

$query = "SELECT name,documentation,value,value_string FROM options ORDER BY name";
$result = sdsQuery($query);
if(!$result) {
  echo "<p class='error'>Option selection failed</p>\n";
} else {
?>

<table class="optionstable">
  <tr>
    <th>Option</th>
    <th>Type</th>
    <th>Value</th>
  </tr>
<?php
  while($option = pg_fetch_array($result)) {
?>
  <tr>
    <td class="name"><?php echo htmlspecialchars($option['name']) ?> &mdash;
      <?php echo htmlspecialchars($option['documentation']) ?>
    </td>
    <td><?php echo isset($option['value'])?'Integer':'String' ?></td>
    <td>
      <form action="options.php" method="post">
        <?php echo sdsForm(); ?>
        <input type="hidden" name="option" value="<?php echo $option['name'] ?>" />
        <input type="text" name="optionvalue" value="<?php echo isset($option['value'])?$option['value']:htmlspecialchars($option['value_string']) ?>" />
        <input type="submit" value="Update" />
      </form>
    </td>
  </tr>
<?php
  }
  pg_free_result($result);
  echo "</table>\n";
}

sdsIncludeFooter();
