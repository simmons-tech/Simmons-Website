<?php
require_once('../sds.php');
sdsRequireGroup("MOVIEADMINS");

sdsIncludeHeader("Edit Movies");

if(!strlen($_REQUEST['movieid']) or preg_match('/\D/',$_REQUEST['movieid'])) {
  echo "<h2 class='error'>No movie specified</h2>\n";
  sdsIncludeFooter();
  exit;
}
$movieid = (int) $_REQUEST['movieid'];

if(empty($_REQUEST['do_update'])) {
# get types
  $query = "SELECT typeid,typename FROM movie_types WHERE active ORDER BY typeid";
  $result = sdsQuery($query);
  if(!$result)
    contactTech("Could not find item types");
  $types = array();
  while($type = pg_fetch_array($result)) {
    $types[$type['typeid']] = $type['typename'];
  }
  pg_free_result($result);

  if(empty($_REQUEST['add_instance'])) {
# get record info
    $query = "SELECT typeid,title,num_disks,link,item_loan_duration FROM movie_items WHERE movieid=$movieid";
    $result = sdsQuery($query);
    if(!$result)
      contactTech("Could not search movies");
    if(pg_num_rows($result) != 1) {
      echo "<h2 class='error'>That movie does not seem to exist</h2>\n";
      sdsIncludeFooter();
      exit;
    }
    $record = pg_fetch_array($result);
    pg_free_result($result);

# fetch instance information
    $query = <<<ENDQUERY
SELECT instanceid,box_number,hidden,deleted
FROM movie_instances
WHERE movieid=$movieid AND NOT deleted
ORDER BY to_number(substring(box_number FROM '^[[:digit:]]+'),'9999999999'),
         box_number
ENDQUERY;
    $result = sdsQuery($query);
    if(!$result)
      contactTech("Could not find instances");
    $instances = pg_fetch_all($result);
    pg_free_result($result);
    $instanceids = array();
    if(!$instances) { $instances = array(); }
    foreach($instances as $instance) {
      $instanceids[] = $instance['instanceid'];
    }
  } else {
# add an instance
    $record = array();
    foreach(array('typeid','title','num_disks',
		  'link','item_loan_duration') as $field) {
      $record[$field] = maybeStripslashes($_REQUEST[$field]);
    }

    $instances = array();
    foreach(array_keys((array) $_REQUEST['box_number']) as $inst_id) {
      $instances[$inst_id] =
	array('instanceid' => $inst_id,
	      'box_number' =>
	      maybeStripslashes($_REQUEST["box_number"][$inst_id]),
	      'hidden'     => isset($_REQUEST["hidden"][$inst_id])?'t':'f',
	      'deleted'    => isset($_REQUEST["deleted"][$inst_id])?'t':'f');
    }
    $newid = 'a';
    while(isset($instances[$newud])) { ++$newid; }
    $instances[$newid] = array('instanceid' => $newid,
			       'box_number' => null,
			       'hidden'     => 'f',
			       'deleted'    => 'f');
  }
?>

<h2>Updating record for <?php echo htmlspecialchars($record['title']) ?></h2>
<form action="edit.php" method="post">
<?php echo sdsForm() ?>
  <input type="hidden" name="movieid" value="<?php echo $movieid ?>" />
  <table>
    <tr>
      <td style="text-align: right"><label for="title">Title:</label></td>
      <td>
        <input id="title" name="title" type="text" size="50" value="<?php echo htmlspecialchars($record['title']) ?>" />
      </td>
    </tr>
    <tr>
      <td style="text-align: right"><label for="typeid">Type:</label></td>
      <td><select id="typeid" name="typeid">
<?php
  foreach($types as $typenum => $type) {
    echo "        <option value='",$typenum,"'",
      $typenum==$record['typeid']?' selected="selected"':'',
      ">",htmlspecialchars($type),"</option>\n";
  }
?>
      </select></td>
    </tr>
    <tr>
      <td style="text-align: right"><label for="num_disks">Disks:</label></td>
      <td>
        <input id="num_disks" name="num_disks" type="text" size="2" value="<?php echo htmlspecialchars($record['num_disks']) ?>" />
      </td>
    </tr>
    <tr>
      <td style="text-align: right"><label for="link">Link:</label></td>
      <td>
        <input id="link" name="link" type="text" size="50" value="<?php echo htmlspecialchars($record['link']) ?>" />
      </td>
    </tr>
    <tr>
      <td style="text-align: right"><label for="item_loan_duration">Loan duration:</label></td>
      <td>
        <input id="item_loan_duration" name="item_loan_duration" type="text" value="<?php echo htmlspecialchars($record['item_loan_duration']) ?>" />
        Input 0 for forever.
      </td>
    </tr>
  </table>
  <table>
    <tr>
      <th>Copy #</th>
      <th colspan="2">Status</th>
<?php
  foreach($instances as $instance) {
?>
    </tr>
    <tr>
      <td style="text-align: right">
        <input type="text" name="box_number[<?php
               echo htmlspecialchars($instance['instanceid']) ?>]" size="10"
               value="<?php echo htmlspecialchars($instance['box_number']) ?>" />
      </td>
      <td>
        <label>
          <input type="checkbox" name="hidden[<?php
             echo htmlspecialchars($instance['instanceid']) ?>]"<?php
             echo $instance['hidden']==='t'?' checked="checked"':'' ?> />Hidden
        </label>
      </td>
      <td>
        <label>
          <input type="checkbox" name="deleted[<?php
             echo $instance['instanceid'] ?>]"<?php
             echo $instance['deleted']==='t'?' checked="checked"':'' ?> />Delete
        </label>
      </td>
<?php
  }
?>
    </tr>
    <tr>
      <td><input name="do_update" type="submit" value="Update Record" /></td>
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
    contactTech("Could not find types");
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
  $query = "SELECT movieid FROM movie_items WHERE title=$title AND typeid=$typeid AND movieid!=$movieid";
  $result = sdsQuery($query);
  if(!$result)
    contactTech("Could not search items");
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

# copies
  $query = "SELECT instanceid FROM movie_instances WHERE movieid=$movieid AND NOT deleted";
  $result = sdsQuery($query);
  $realinstances = array();
  while($inst = pg_fetch_array($result)) {
    $realinstances[$inst['instanceid']] = 1;
  }
  pg_free_result($result);
  $instanceids = array_keys((array) $_REQUEST['box_number']);
  $instancechanges = array();
  $instanceupdates = array();
  $instanceinserts = array();
  $instancedeletes = array();
  foreach($instanceids as $instanceid) {
    if(isset($realinstances[$instanceid])) {
      if(!empty($_REQUEST["deleted"][$instanceid])) {
	$instancedeletes[] = $instanceid;
      } else {
	$instanceupdates[] = $instanceid;
      }
    } else {
      if(!empty($_REQUEST["deleted"][$instanceid])) {
	# created and deleted in one transaction - ignore
      } else {
	$instanceinserts[] = $instanceid;
      }
    }
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
UPDATE movie_items
SET typeid=$typeid,title=$title,num_disks=$num_disks,
    link=$link,item_loan_duration=$item_loan_duration
WHERE movieid=$movieid
ENDQUERY;
  $result = sdsQuery($query);
  if(!$result or pg_affected_rows($result) != 1)
    contactTech("Could not update item");
  pg_free_result($result);

  $currentuser = pg_escape_string($session->username);
# update instances
  foreach($instanceupdates as $instance) {
    $box_number = $instancechanges[$instance]['box_number'];
    $hidden = $instancechanges[$instance]['hidden'] ? "true" : "false";
    $query = "UPDATE movie_instances SET box_number=$box_number,hidden=$hidden WHERE instanceid=$instance";
    $result = sdsQuery($query);
    if(!$result or pg_affected_rows($result) != 1)
      contactTech("Could not update instances");
    pg_free_result($result);
  }

# insert instances
  foreach($instanceinserts as $instance) {
    $box_number = $instancechanges[$instance]['box_number'];
    $hidden = $instancechanges[$instance]['hidden'] ? "false" : "false";
    $query = "INSERT INTO movie_instances (movieid,box_number,hidden) VALUES ($movieid,$box_number,$hidden)";
    $result = sdsQuery($query);
    if(!$result or pg_affected_rows($result) != 1)
      contactTech("Could not create instance");
    pg_free_result($result);
  }

# delete instances
  foreach($instancedeletes as $instance) {
    $query = "UPDATE movie_instances SET hidden=true,deleted=true WHERE instanceid=$instance";
    $result = sdsQuery($query);
    if(!$result or pg_affected_rows($result) != 1)
      contactTech("Could not delete instance");
    pg_free_result($result);
  }

  $session->data['movie_itemtype'] = $typeid;
  $session->saveData();

  echo "<h2>Database Updated</h2>\n";
  echo "<p><a href='",sdsLink("list.php","showall=1"),
    "'>Back to movie listing</a></p>\n";
}

sdsIncludeFooter();
