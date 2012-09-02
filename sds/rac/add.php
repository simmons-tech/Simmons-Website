<?php
require_once("../sds.php");
require_once("user-administration-tools.inc.php");

sdsRequireGroup("RAC");
sdsIncludeHeader("Add Directory Entry");

$newuser = array();
$fields = array('username','lastname','firstname','title','room','year',
		'type','immortal','hidden');
foreach($fields as $field) {
  $newuser[$field] = getStringArg($field);
}

if(isset($_REQUEST['submit'])) {
  $allgood = true;
  if(strlen($newuser['username']) == 0) {
    $allgood = false;
    echo "<p class='error'>Username must not be blank</p>\n";
  }
  if(strlen($newuser['lastname']) == 0) {
    $allgood = false;
    echo "<p class='error'>Lastname must not be blank</p>\n";
  }
  if(strlen($newuser['firstname']) == 0) {
    $allgood = false;
    echo "<p class='error'>Firstname must not be blank</p>\n";
  }
  if(empty($newuser['year']) and $newuser['type'] === 'U') {
    $allgood = false;
    echo "<p class='error'>Undergraduates must have a class year</p>\n";
  }

  if(preg_match('/\D/',$newuser['year'])) {
    $allgood = false;
    echo "<p class='error'>Year should be an integer or blank.</p>\n";
  } elseif($newuser['year'] == 0) {
    $newuser['year'] = null;
  }

  if(strlen($newuser['room'])) {
    $query = "SELECT 1 FROM rooms WHERE room = '" .
      pg_escape_string($newuser['room']) . "'";
    $result = sdsQuery($query);
    if(!$result)
      contactTech("Could not search rooms");

    if(pg_num_rows($result) != 1) {
      $allgood = false;
      echo "<p class='error'>Unknown room</p>\n";
    }
    pg_free_result($result);
  } else {
    $newuser['room'] = null;
  }

  $query = "SELECT 1 FROM user_types WHERE type='" .
    pg_escape_string($newuser['type']) . "' AND active";
  $result = sdsQuery($query);
  if(!$result)
    contactTech("Could not search types");
  if(pg_num_rows($result) != 1) {
    $allgood = false;
    echo "<p class='error'>Unknown type</p>\n";
  }
  pg_free_result($result);

  if(strpos($newuser['username'],'@')) {
    $newuser['email'] = $newuser['username'];
  } else {
    $newuser['email'] = $newuser['username'] . "@mit.edu";
  }

  $newuser['immortal'] = (bool) $newuser['immortal'];
  $newuser['hidden'] = (bool) $newuser['hidden'];

  if($allgood) {
    $warnings = array();
    if(addUser($newuser['username'],$newuser['email'],$newuser['lastname'],
	       $newuser['firstname'],$newuser['title'],$newuser['type'],
	       $newuser['room'], $newuser['year'],$newuser['immortal'],
	       $newuser['hidden'],$warnings)) {
      echo "<p>Entry added for \"",htmlspecialchars($newuser['username']),
	"\"!  Mailing lists will be updated within 24 hours.</p>\n";

      foreach($warnings as $complaint)
	echo "<p class='error'>",$complaint,"</p>\n";
      # make the form display blank
      unset($newuser);
    }
  }
} elseif(isset($_REQUEST['reenable'])) {
  $username = maybeStripslashes($_REQUEST['username']);
  if(enableUser($username)) {
    echo "<p>",htmlspecialchars($username)," reenabled</p>\n";
  }
  unset($newuser);
}

if(empty($newuser))
  $newuser = array_fill_keys($fields,'');

?>

<form action="add.php" method="post">
<?php echo sdsForm() ?>

<table class="racadd">
  <tr>
    <td class="label">Username</td>
    <td><input type="text" name="username" size="12"
               value="<?php echo $newuser['username'] ?>" /></td>
  </tr>

  <tr>
    <td class="label">Title</td>
    <td><input type="text" name="title" size="12"
               value="<?php echo $newuser['title'] ?>" /></td>
  </tr>

  <tr>
    <td class="label">Firstname</td>
    <td><input type="text" name="firstname" size="12"
               value="<?php echo $newuser['firstname'] ?>" /></td>
  </tr>

  <tr>
    <td class="label">Lastname</td>
    <td><input type="text" name="lastname" size="12"
               value="<?php echo $newuser['lastname'] ?>" /></td>
  </tr>

  <tr>
    <td class="label">Room</td>
    <td><input type="text" name="room" size="4"
               value="<?php echo $newuser['room'] ?>" /></td>
  </tr>

  <tr>
    <td class="label">Year</td>
    <td><input type="text" name="year" size="4"
               value="<?php echo $newuser['year'] ?>" /></td>
  </tr>

  <tr>
    <td class="label">Type</td>
    <td><select name="type">
<?php
$default = $newuser['type'] ? $newuser['type'] : "U";
$query = "SELECT type,description FROM user_types WHERE active ORDER BY type";
$result = sdsQuery($query);
if(!$result)
  contactTech("Cannot find types");
while($record = pg_fetch_array($result)) {
  echo "      <option value='",htmlspecialchars($record['type']),"' ",
    $record['type']===$default?' selected="selected"':'',">",
    htmlspecialchars($record['type'])," (",
    htmlspecialchars($record['description']),")</option>\n";
}
pg_free_result($result);
?>
    </select></td>
  </tr>

  <tr>
    <td class="label">Immortal (rarely true)</td> 
    <td><input type="checkbox" name="immortal" value="immortal"
            <?php echo $newuser['immortal']?' checked="checked"':'' ?> /></td>
  </tr>

  <tr>
    <td class="label">Hidden (rarely true)</td> 
    <td><input type="checkbox" name="hidden" value="hidden"
            <?php echo $newuser['hidden']?' checked="checked"':'' ?> /></td>
  </tr>

  <tr>
    <td></td>
    <td><input type="submit" name="submit" value="Add Entry"></td>
  </tr>

  <tr>
    <td></td>
    <td><input type="reset" value="Clear Form" /></td>
  </tr>
</table>

</form>

<hr />

<h2>Reenable an old user</h2>

<form action="add.php" mathod="post">
  <label>Username:<input type="text" name="username" /></label>
  <input type="submit" name="reenable" value="Reenable" />
</form>

<?php

sdsIncludeFooter();
