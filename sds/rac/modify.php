<?php
require_once("../sds.php");
require_once("user-administration-tools.inc.php");

sdsRequireGroup("RAC");
sdsIncludeHeader("Modify Directory Entry");

if(!isset($_REQUEST['username'])) {
  echo "<h2 class='error'>No user specified</h2>\n";
  sdsIncludeFooter();
  exit;
}

$username = maybeStripslashes($_REQUEST['username']);
$username_esc = pg_escape_string($username);
$username_disp = htmlspecialchars($username);

$query = "SELECT 1 FROM directory WHERE username='$username_esc'";
$result = sdsQuery($query);
if(!$result)
  contactTech("Could not search directory");
if(pg_num_rows($result) != 1) {
  echo "<h2 class='error'>User ",$username_disp,
    " is not in the directory</h2>\n";
  sdsIncludeFooter();
  exit;
}
pg_free_result($result);

unset($values);
if(!empty($_REQUEST['submit'])) {

  $fields = array('lastname','firstname','year','private','type','email',
		  'title','room');

  $values = array();
  foreach($fields as $field) {
    if(isset($_REQUEST[$field]))
      $values[$field] = maybeStripslashes($_REQUEST[$field]);
  }

  $good = true;

  # check room
  if($values['room'] === '')
    $values['room'] = null;

  if(isset($values['room'])) {
    $query = "SELECT 1 FROM rooms WHERE room='".
      pg_escape_string($values['room'])."'";
    $result = sdsQuery($query);
    if(!$result)
      contactTech("Could not search rooms");
    if(pg_num_rows($result) != 1) {
      $good = false;
      echo "<p class='error'>Unknown room</p>\n";
    }
    pg_free_result($result);
  }

  # check type
  if(isset($values['type'])) {
    $query = "SELECT 1 FROM user_types WHERE type='".
      pg_escape_string($values['type'])."' AND active";
    $result = sdsQuery($query);
    if(!$result)
      contactTech("Could not search types");
    if(pg_num_rows($result) != 1) {
      $good = false;
      echo "<p class='error'>Invalid type: ",htmlspecialchars($values['type']),
	"</p>\n";
    }
    pg_free_result($result);
  }

  if($good) {
    if(modifyUser($username, $values)) {
      echo "<p>Update received!  Mailing lists will be updated within 24 hours.</p>\n";
      unset($values);
    }
  }
}

$query = "SELECT title,firstname,lastname,room,year,private,type,email FROM directory WHERE username='$username_esc'";
$result = sdsQuery($query);
if(!$result or pg_num_rows($result) != 1)
  contactTech("Could not search directory");
$userinfo = pg_fetch_array($result);
pg_free_result($result);

echo "<h2>RAC Modify: ",$username_disp,"</h2>\n";

if(isset($values)) {
  foreach($values as $key => $val) {
    $userinfo[$key] = $val;
  }
}

$userinfo_disp = array();
foreach($userinfo as $key => $val) {
  $userinfo_disp[$key] = htmlspecialchars($val);
}

?>
<p><em>This action can not be undone!</em></p>

<form action="modify.php" method="post">
<?php echo sdsForm() ?>

<input type="hidden" name="username" value="<?php echo $username_disp ?>" />

<table class="racadd">
  <tr>
    <td class="label">Title</td>
    <td><input type="text" name="title" size="12"
               value="<?php echo $userinfo_disp['title'] ?>" /></td>
  </tr>
  <tr>
    <td class="label">Firstname</td>
    <td><input type="text" name="firstname" size="12"
               value="<?php echo $userinfo_disp['firstname'] ?>" /></td>
  </tr>
  <tr>
    <td class="label">Lastname</td>
    <td><input type="text" name="lastname" size="12"
               value="<?php echo $userinfo_disp['lastname'] ?>" /></td>
  </tr>
  <tr>
    <td class="label">Year</td>
    <td><input type="text" name="year" size="4"
               value="<?php echo $userinfo_disp['year'] ?>" /></td>
  </tr>
  <tr>
    <td class="label">Room</td>
    <td><input type="text" name="room" size="4"
               value="<?php echo $userinfo_disp['room'] ?>" /></td>
  </tr>
  <tr>
    <td class="label">Email</td>
    <td><input type="text" name="email" size="30"
               value="<?php echo $userinfo_disp['email'] ?>" /></td>
  </tr>
  <tr>
    <td class="label">Type</td>
    <td><select name="type">
<?php
$default = $userinfo['type'] ? $userinfo['type'] : "U";
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
    <td class="label">Hidden</td>
    <td>
      <label>
        <input type="radio" name="private"
               value="1"<?php echo $userinfo['private']==='t'?' checked="checked"':'' ?> />Yes
      </label>
        <input type="radio" name="private"
               value="0"<?php echo $userinfo['private']==='t'?'':' checked="checked"' ?> />No
      </label>
    </td>
  </tr>

  <tr>
    <td></td>
    <td>
      <input type="submit" name="submit" value="Submit" />
      <input type="reset" value="Reset" />
    </td>
  </tr>

</table>

</form>

<?php
sdsIncludeFooter();
