<?php
require_once("../sds.php");
sdsRequireGroup("MOVIEADMINS");

sdsIncludeHeader("Edit Movie Types");

if(isset($_REQUEST['update']) and is_array($_REQUEST['update'])) {
  list($typeid) = array_keys($_REQUEST['update']);
  if(preg_match('/\D/',$typeid)) {
    echo "<h2 class='error'>Invalid type ID</h2>\n";
    sdsIncludeFooter();
    exit;
  }

  if(!strlen($_REQUEST["typename"][$typeid])) {
    echo "<h2 class='error'>Please provide a type name</h2>\n";
    sdsIncludeFooter();
    exit;
  }
  $typename =
    "'".sdsSanitizeString($_REQUEST["typename"][$typeid])."'";

  if(strlen($_REQUEST["loan_duration"][$typeid])) {
    $loan_duration = "interval '" .
      sdsSanitizeString($_REQUEST["loan_duration"][$typeid]) . "'";
    $result = sdsQueryTest("SELECT $loan_duration");
    if(!$result) {
      echo "<h2 class='error'>Invalid loan duration</h2>\n";
      sdsIncludeFooter();
      exit;
    }
    pg_free_result($result);
  } else {
    $loan_duration = "interval '0'";
  }

  $active = $_REQUEST["active"][$typeid] ? "true" : "false";

  $query = "UPDATE movie_types SET typename=$typename,loan_duration=$loan_duration,active=$active WHERE typeid=$typeid";
  $result = sdsQuery($query);
  if(!$result or pg_affected_rows($result) != 1)
    contactTech("Could not update type");
  pg_free_result($result);

  echo "<h2>Database Updated</h2>\n";
} elseif(isset($_REQUEST['add_new'])) {
  if(!strlen($_REQUEST["typename_new"])) {
    echo "<h2 class='error'>Please provide a type name</h2>\n";
    sdsIncludeFooter();
    exit;
  }
  $typename = "'".sdsSanitizeString($_REQUEST["typename_new"])."'";

  if(strlen($_REQUEST["loan_duration_new"])) {
    $loan_duration = "interval '" .
      sdsSanitizeString($_REQUEST["loan_duration_new"]) . "'";
    $result = sdsQueryTest("SELECT $loan_duration");
    if(!$result) {
      echo "<h2 class='error'>Invalid loan duration</h2>\n";
      sdsIncludeFooter();
      exit;
    }
    pg_free_result($result);
  } else {
    $loan_duration = "interval '0'";
  }

  $active = $_REQUEST["active_new"] ? "true" : "false";

  $query = "INSERT INTO movie_types (typename,loan_duration,active) VALUES ($typename,$loan_duration,$active)";
  $result = sdsQuery($query);
  if(!$result or pg_affected_rows($result) != 1)
    contactTech("Could not create type");
  pg_free_result($result);

  echo "<h2>Database Updated</h2>\n";
}

$query = "SELECT typeid,typename,loan_duration,active FROM movie_types ORDER BY typeid";
$result = sdsQuery($query);
if(!$result)
  contactTech("Could not find types");
?>
<form action="typeedit.php" method="post">
<?php echo sdsForm() ?>
  <table>
    <tr>
      <th>ID</th>
      <th>Item Type</th>
      <th>Loan Duration<br />(0 for forever)</th>
      <th>Active</th>
      <th>Update</th>
    </tr>
<?php
while($type = pg_fetch_array($result)) {
  echo "    <tr>\n";
  echo "      <td>",$type['typeid'],"</td>\n";
  echo "      <td><input type='text' name='typename[",$type['typeid'],
    "]' value='",htmlspecialchars($type['typename'],ENT_QUOTES),"' /></td>\n";
  echo "      <td><input type='text' name='loan_duration[",$type['typeid'],
    "]' value='",htmlspecialchars($type['loan_duration'],ENT_QUOTES),
    "' /></td>\n";
  echo '      <td><input type="checkbox" name="active[',$type['typeid'],
    $type['active']==='t'?']" checked="checked"':']"'," /></td>\n";
  echo "      <td><input type='submit' name='update[",$type['typeid'],
    "]' value='Update' /></td>\n";
  echo "    </tr>\n";
}
?>
    <tr>
      <td></td>
      <td><input type="text" name="typename_new" /></td>
      <td><input type="text" name="loan_duration_new" /></td>
      <td><input type="checkbox" name="active_new" checked="checked" /></td>
      <td><input type="submit" name="add_new" value="Add New Type" /></td>
    </tr>
  </table>
</form>
<p><a href="<?php echo sdsLink("list.php","showall=1") ?>">Return to movie list</a></p>

<?php
sdsIncludeFooter();
