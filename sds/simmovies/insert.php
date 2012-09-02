<?php
require_once('../sds.php');
sdsRequireGroup("MOVIEADMINS");

sdsIncludeHeader("Add Movies");

if(empty($_REQUEST['do_insert'])) {
# get types
  $query = "SELECT typeid,typename FROM movie_types where active ORDER BY typeid";
  $result = sdsQuery($query);
  if(!$result)
    contactTech("Could not find types");
  $types = array();
  while($type = pg_fetch_array($result)) {
    $types[$type['typeid']] = $type['typename'];
  }
  pg_free_result($result);

# current type
  unset($itemtype);
  if(isset($_REQUEST['typeid']) && !preg_match("/\D/",$_REQUEST['typeid'])) {
    $itemtype = (int) $_REQUEST['typeid'];
  } elseif(isset($session->data['movie_itemtype'])) {
    $itemtype = $session->data['movie_itemtype'];
  }
  if(isset($itemtype)) {
    $query = "SELECT 1 FROM movie_types WHERE active AND typeid=$itemtype";
    $result = sdsQuery($query);
    if(pg_num_rows($result)!=1) {
      unset($itemtype);
    }
  }
  if(!isset($itemtype)) {
    $query = "SELECT typeid FROM movie_types WHERE active ORDER BY typeid LIMIT 1";
    $result = sdsQuery($query);
    if(!$result)
      contactTech("Could not find types");
    if(pg_num_rows($result)!=1) {
      echo "<h2 class='error'>Error: Could not find any types!</h2>\n";
      sdsIncludeFooter();
      exit;
    }
    list($itemtype) = pg_fetch_row($result);
    pg_free_result($result);
  }
  $session->data['movie_itemtype'] = $itemtype;
  $session->saveData();

# add an instance
  $record = array();
  foreach(array('typeid','title','num_disks',
		'link','item_loan_duration') as $field) {
    $record[$field] = getStringArg($_REQUEST[$field]);
  }

  $instances = array();
  foreach(array_keys((array) @$_REQUEST['box_number']) as $inst_id) {
    $instances[$inst_id] =
      array('instanceid' => $inst_id,
	    'box_number' =>
	    maybeStripslashes($_REQUEST["box_number"][$inst_id]),
	    'hidden'     => isset($_REQUEST["hidden"][$inst_id])?'t':'f');
  }
  $newid = 'a';
  while(isset($instances[$newid])) { ++$newid; }
  $instances[$newid] = array('instanceid' => $newid,
			     'box_number' => null,
			     'hidden'     => 'f');

?>

<h2>Insert Movie</h2>
<form action="insert.php" method="post">
<?php echo sdsForm() ?>
  <table>
    <tr>
      <td style="text-align: right"><label for="title">Title:</label></td>
      <td><input id="title" name="title" type="text" size="50" value="<?php echo htmlspecialchars($record['title']) ?>" /></td>
    </tr>
    <tr>
      <td style="text-align: right"><label for="typeid">Type:</label></td>
      <td><select id="typeid" name="typeid">
<?php
  foreach($types as $typenum => $type) {
    echo "        <option value='",$typenum,"'",
      $typenum==$itemtype ? ' selected="selected"' : '',">",
    htmlspecialchars($type),"</option>\n";
  }
?>
      </select></td>
    </tr>
    <tr>
      <td style="text-align: right"><label for="num_disks">Disks:</label></td>
      <td><input id="num_disks" name="num_disks" type="text" size="2" value="<?php echo $record['num_disks'] ?>" /></td>
    </tr>
    <tr>
      <td style="text-align: right"><label for="link">Link:</label></td>
      <td><input id="link" name="link" type="text" size="50" value="<?php echo htmlspecialchars($record['link']) ?>" /></td>
    </tr>
    <tr>
      <td style="text-align: right"><label for="item_loan_duration">Loan duration:</label></td>
      <td>
        <input id="item_loan_duration" name="item_loan_duration" type="text" value="<?php echo htmlspecialchars($record['item_loan_duration']) ?>" />
        Insert 0 for forever.
      </td>
    </tr>
    <tr>
      <th>Copy #</th>
      <th style="text-align: left">Status</th>
    </tr>
<?php
  foreach($instances as $instance) {
?>
    <tr>
      <td style="text-align: right">
        <input type="text" name="box_number[<?php echo htmlspecialchars($instance['instanceid']) ?>]" size="10" value="<?php echo htmlspecialchars($instance['box_number']) ?>" />
      </td>
      <td>
        <label>
          <input type="checkbox" name="hidden[<?php
             echo htmlspecialchars($instance['instanceid']) ?>]"<?php
             echo $instance['hidden']==='t'?' checked="checked"':'' ?> />Hidden
        </label>
      </td>
    </tr>
<?php
  }
?>
    <tr>
      <td><input name="do_insert" type="submit" value="Insert" /></td>
      <td colspan="2">
        <input name="add_instance" type="submit" value="Add A Copy" />
      </td>
    </tr>
  </table>
</form>

<?php
} else {
  if(!strlen($_REQUEST['typeid']) or preg_match('/\D/',$_REQUEST['typeid'])) {
    echo "<h2 class='error'>Invalid type</h2>\n";
    sdsIncludeFooter();
    exit;
  }
  $typeid = (int) $_REQUEST['typeid'];

  $query = "SELECT 1 FROM movie_types WHERE typeid=$typeid AND active";
  $result = sdsQuery($query);
  if(!$result)
    contactTech("Could not search types");
  if(pg_num_rows($result)!=1) {
    echo "<h2 class='error'>Invalid type</h2>\n";
    sdsIncludeFooter();
    exit;
  }
  pg_free_result($result);

  if(!strlen($_REQUEST['title'])) {
    echo "<h2 class='error'>Please supply a title</h2>\n";
    sdsIncludeFooter();
    exit;
  }
  $title = "'".sdsSanitizeString($_REQUEST['title'])."'";
  $query = "SELECT movieid FROM movie_items WHERE title=$title AND typeid=$typeid";
  $result = sdsQuery($query);
  if(!$result)
    contactTech("Could not search movies");
  if(pg_num_rows($result) != 0) {
    list($movieid) = pg_fetch_array($result);
    echo "<h2>A movie of that title and type already exists!</h2>\n";
    echo "<p><a href='",sdsLink("edit.php","movieid=$movieid"),
      "'>Edit that record</a></p>\n";
    sdsIncludeFooter();
    exit;
  }
  pg_free_result($result);

  $num_disks = 'null';
  if(!empty($_REQUEST['num_disks']) and
     !preg_match('/\D/',$_REQUEST['num_disks'])) {
    $num_disks = (int) $_REQUEST['num_disks'];
  }

  $link = 'null';
  if(strlen($_REQUEST['link'])) {
    $link = "'".sdsSanitizeString($_REQUEST['link'])."'";
  }

  $item_loan_duration = 'null';
  if(strlen($_REQUEST['item_loan_duration'])) {
    $item_loan_duration = "interval '" .
      sdsSanitizeString($_REQUEST['item_loan_duration']) . "'";
    $result = sdsQueryTest("SELECT $item_loan_duration");
    if(!$result) {
      echo "<h2 class='error'>Invalid loan duration</h2>\n";
      sdsIncludeFooter();
      exit;
    }
    pg_free_result($result);
  }

# instances
  $instancechanges = array();

  foreach(array_keys((array) $_REQUEST['box_number']) as $instanceid) {
    if(!strlen($_REQUEST["box_number"][$instanceid])) {
      $instancechanges[$instanceid]['box_number'] = 'null';
    } else {
      $instancechanges[$instanceid]['box_number'] =
	"'".sdsSanitizeString(trim($_REQUEST["box_number"][$instanceid]))."'";
    }
    $instancechanges[$instanceid]['hidden'] =
      isset($_REQUEST["hidden"][$instanceid]);
  }

# update main record
  $query = <<<ENDQUERY
INSERT INTO movie_items
       (typeid, title, num_disks, link, item_loan_duration)
VALUES ($typeid,$title,$num_disks,$link,$item_loan_duration)
RETURNING movieid
ENDQUERY;
  $result = sdsQuery($query);
  if(!$result or pg_num_rows($result) != 1)
    contactTech("Item creation falied");
  list($movieid) = pg_fetch_array($result);
  pg_free_result($result);

# insert instances
  foreach($instancechanges as $changes) {
    $box_number = $changes['box_number'];
    $hidden = $changes['hidden'] ? "true" : "false";
    $query = "INSERT INTO movie_instances (movieid,box_number,hidden) VALUES ($movieid,$box_number,$hidden)";
    $result = sdsQuery($query);
    if(!$result or pg_affected_rows($result) != 1)
      contactTech("Could not insert instances");
    pg_free_result($result);
  }

  $session->data['movie_itemtype'] = $typeid;
  $session->saveData();

  echo "<h2>Database Updated</h2>\n";
  echo "<p><a href='",sdsLink("list.php","showall=1"),
    "'>Back to movie listing</a></p>\n";
}

sdsIncludeFooter();
