<?php
require_once("../sds.php");

sdsRequireGroup("RAC");

# field names are assumed not to contain any nasty characters, either for
# postgres or HTML (user submissions are compared to the list)
$allowedfields = array('username' => 'username',
		       'lastname' => 'lastname',
		       'firstname' => 'firstname',
		       'title' => 'title',
		       'year' => 'year',
		       'room' => 'room',
		       'phone' => 'phone',
		       'email' => 'email',
		       'type' => 'type',
		       'hidden' => "CASE WHEN private THEN 'Y' ELSE 'N' END");

$defaultfields = array('username','lastname','firstname','year','room',
		       'phone','type');

if(isset($_REQUEST['fields']) and is_array($_REQUEST['fields'])) {

  $separator = ',';
  if(isset($_REQUEST['separator'])) {
    if($_REQUEST['separator'] === 'other') {
      $separator = maybeStripslashes($_REQUEST['othersep']);
    } elseif($_REQUEST['separator'] === 'tab') {
      $separator = "\t";
    } else {
      $separator = maybeStripslashes($_REQUEST['separator']);
    }
  }

  $fields = array();
  $fields_esc = array();

  foreach($_REQUEST['fields'] as $field) {
    $field = maybeStripslashes($field);
    if($field === '(none)')
      continue;
    if(!isset($allowedfields[$field])) {
      sdsIncludeHeader("Directory CSV Download");
      echo "<p class='error'>",htmlspecialchars($field),
	" is not a valid field</>\n";
      sdsIncludeFooter();
      exit;
    }
    $fields[] = $field;
    $fields_esc[] = $allowedfields[$field];
  }

  $fieldstr = implode($separator,$fields);
  $fieldstr_esc = implode(',',$fields_esc);

  $date = date("Ymd");

  $query = "SELECT $fieldstr_esc FROM active_directory ORDER BY $fieldstr_esc";

  $result = sdsQuery($query);
  if(!$result) {
    echo "error: could not query directory\n";
    exit;
  }

  header("Content-Type: text/csv");
  header("Content-Disposition: attachment; filename=simmons_directory_$date.csv");

  echo $fieldstr,"\n";

  while($record = pg_fetch_row($result)) {
    echo join($separator,$record),"\n";
  }
  exit;
}

sdsIncludeHeader("Directory CSV Download");

?>

<form action="csv.php" method="post">
<?php echo sdsForm() ?>

<p>Please select the fields you would like. Fields will be returned in the
  order given here, with (none) fields skipped. The CSV file will be sorted
  by the first column, with ties broken by the second, and so on.</p>

<ul style="list-style-type:none">
<?php
$choices = array_keys($allowedfields);
$choices[] = '(none)';

for($i=0;$i<count($choices);$i++) {
  echo "  <li><select name='fields[",$i,"]'>\n";
  $default = isset($defaultfields[$i]) ? $defaultfields[$i] : '(none)';
  foreach($choices as $field) {
    echo "    <option",$field===$default?' selected="selected"':'',">",
      $field,"</option>\n";
  }
  echo "  </select></li>\n";
}
?>
</ul>
<label>Field separator:
  <select name="separator">
    <option value="," selected="selected">, (comma)</option>
    <option value="tab">Tab</option>
    <option value=":">: (colon)</option>
    <option value=";">; (semicolon)</option>
    <option value="other">Other (specify)</option>
  </select>
</label>
<input type="text" name="othersep" /><br />
<input type="submit" value="Create" />
</form>

<?php
sdsIncludeFooter();
