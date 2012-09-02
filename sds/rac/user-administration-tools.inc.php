<?php 
if(realpath($_SERVER['SCRIPT_FILENAME']) === realpath(__FILE__))
  exit;
### THIS LIBRARY PROVIDES FUNCTIONS FOR USER: CREATION, ENABLING,
### DISABLING, AND MOVING TO A NEW ROOM

require_once(dirname(__FILE__) . "/../sds.php");
sdsRequireGroup("RAC");

function addUser($username,$email,$lastname,$firstname,$title,$type,$room,
		 $year,$immortal,$hidden,&$warnings) {

  $username_esc = pg_escape_string($username);
  $private = $hidden ? 'true' : 'false';
  $immortal_esc = $immortal ? 'true' : 'false';
  if(isset($title) and $title === '')
    $title = null;

  $phone = null;
  if(isset($room)) {
    $room_esc = pg_escape_string($room);
    $query = "SELECT phone FROM active_directory WHERE room='$room_esc'";
    $result = sdsQuery($query);
    if(!$result) {
      contactTech("Could not search directory",false);
      return null;
    }
    $usedphone = '';
    if(pg_num_rows($result) > 0) {
      list($usedphone) = pg_fetch_array($result);
    }
    pg_free_result($result);
    $usedphone_esc = pg_escape_string($usedphone);

    $query = "SELECT COALESCE(NULLIF(phone1,'$usedphone_esc'),phone2,phone1) FROM rooms WHERE room='$room_esc'";
    $result = sdsQuery($query);
    if(!$result or pg_num_rows($result) != 1) {
      contactTech("Could not search rooms",false);
      return null;
    }
    list($phone) = pg_fetch_array($result);
    pg_free_result($result);
  }

  $query = "SELECT 1 FROM sds_users_all WHERE username='$username_esc'";
  $result = sdsQuery($query);
  if(!$result) {
    contactTech("Could not search users",false);
    return null;
  }
  if(pg_num_rows($result) > 0) {
    pg_free_result($result);
    echo "<p class='error'>",htmlspecialchars($username),
      " is already in the DB</p>\n";
    return false;
  }
  pg_free_result($result);

  $query = "SELECT 1 FROM directory WHERE username='$username_esc'";
  $result = sdsQuery($query);
  if(!$result or pg_num_rows($result) > 0) {
    pg_free_result($result);
    echo "<p class='error'>",htmlspecialchars($username),
      " is already in the directory</p>\n";
    return false;
  }
  pg_free_result($result);

  require_once(dirname(__FILE__) . '/../wiki_interaction.inc.php');
  global $mysql_db;
  $user_myesc = mysql_real_escape_string(strtolower($username),$mysql_db);
  $query = "SELECT 1 FROM user WHERE LOWER(user_name) = '$user_myesc'";
  $result = mysql_query($query,$mysql_db);
  if(!$result or mysql_num_rows($result) > 0) {
    $warnings[] = <<<ENDCOMPLAINT
$username is already an existing Wiki account. Please contact
<a href='mailto:simmons-tech@mit.edu'>simmons-tech@mit.edu</a>.
ENDCOMPLAINT;
  }
  mysql_free_result($result);

  # pass commands as one query so we have a single transaction without having
  # to deal with commit/rollback
  # results are automatically rolled back if something fails.

  $query = "INSERT INTO sds_users_all (username, active, immortal) VALUES ('$username_esc', true, '$immortal_esc');";

  $directoryArray = array('username' => $username,
			  'email' => $email,
			  'firstname' => $firstname,
			  'lastname' => $lastname,
			  'title' => $title,
			  'room' => $room,
			  'year' => $year,
			  'type' => $type,
			  'private' => $private,
			  'phone' => $phone);
  $query .= "INSERT INTO directory " . sqlArrayInsert($directoryArray) . ";";

  if(isset($room))
    $query .= "INSERT INTO old_room_assignments (username,room) VALUES ('$username_esc','$room_esc');";

  $result = sdsQuery($query);
  if(!$result) {
    contactTech("Could not create user",false);
    return null;
  }
  pg_free_result($result);

  return true;
}

function enableUser($username) {
  $username_esc = pg_escape_string($username);
  $query = "SELECT 1 FROM sds_users_all WHERE username='$username_esc' AND NOT active";
  $result = sdsQuery($query);
  if(!$result) {
    contactTech("Could not search users",false);
    return null;
  }
  if(pg_num_rows($result) != 1) {
    pg_free_result($result);
    echo "<p class='error'>",htmlspecialchars($username),
      " is not a disabled user</p>\n";
    return false;
  }
  pg_free_result($result);

  $query =
    "UPDATE sds_users_all SET active=true WHERE username='$username_esc'";
  $result = sdsQuery($query);
  if(!$result or pg_affected_rows($result) != 1) {
    contactTech("Reenabling user failed",false);
    return null;
  }
  pg_free_result($result);
  return true;
}

function disableUser($username) {

  $username_esc = pg_escape_string($username);
  $query = "SELECT 1 FROM sds_users WHERE username='$username_esc'";
  $result = sdsQuery($query);
  if(!$result) {
    contactTech("Could not search users",false);
    return null;
  }
  if(pg_num_rows($result) != 1) {
    pg_free_result($result);
    echo "<p class='error'>",htmlspecialchars($username),
      " is not an active user</p>\n";
    return false;
  }
  pg_free_result($result);

  # one query = one transaction, so rollback occurs if something fails

  $query = "UPDATE sds_users_all SET active=false,immortal=false WHERE username='$username_esc';";

  $query .= "UPDATE old_room_assignments SET moveout = now() FROM directory WHERE old_room_assignments.username='$username_esc' AND directory.username='$username_esc' AND old_room_assignments.room = directory.room AND moveout IS NULL;";

  $query .= "UPDATE directory SET room=null,phone=null,lounge=null,loungevalue=null WHERE username='$username_esc';";

  $query .= "DELETE FROM sds_users_in_groups WHERE username='$username_esc';";
  $result = sdsQuery($query);
  if(!$result) {
    contactTech("Could not disable user",false);
    return null;
  }
  pg_free_result($result);

  return true;
}

# nothing will be done if the current room matches the second argument
function clearRoom($username,$newroom="") {

  $username_esc = pg_escape_string($username);
  $newroom_esc = pg_escape_string($newroom);
  $query = "SELECT 1 FROM directory WHERE username='$username_esc' AND room IS NOT NULL AND room != '$newroom_esc'";
  $result = sdsQuery($query);
  if(!$result) {
    contactTech("Could not search directory",false);
    return null;
  }
  if(pg_num_rows($result) != 1) {
    pg_free_result($result);
    return true;
  }
  pg_free_result($result);

  $query = "UPDATE old_room_assignments SET moveout = now() FROM directory WHERE old_room_assignments.username='$username_esc' AND directory.username='$username_esc' AND old_room_assignments.room = directory.room AND moveout IS NULL;";

  $query .= "UPDATE directory SET room=null,phone=null WHERE username='$username_esc';";
  $result = sdsQuery($query);
  if(!$result or pg_affected_rows($result) != 1) {
    contactTech("Could not clear room",false);
    return null;
  }
  pg_free_result($result);
  return true;
}

# if doing directory reorganization, it may be desirable to clear rooms and
# then move users, so that phone information will be assigned correctly
function moveRoom($username, $newRoom) {

  $username_esc = pg_escape_string($username);
  $query = "SELECT room FROM directory WHERE username='$username_esc'";
  $result = sdsQuery($query);
  if(!$result) {
    contactTech("Could not query directory",false);
    return null;
  }
  if(pg_num_rows($result) != 1) {
    pg_free_result($result);
    echo "<p class='error'>",htmlspecialchars($username),
      " is not in the directory.</p>\n";
    return false;
  }
  list($curRoom) = pg_fetch_array($result);
  pg_free_result($result);
  if($curRoom === $newRoom)
    return true;

  $room_esc = pg_escape_string($newRoom);

  $query = "SELECT phone FROM active_directory WHERE room='$room_esc'";
  $result = sdsQuery($query);
  if(!$result) {
    contactTech("Could not search directory",false);
    return null;
  }
  $usedphone = '';
  if(pg_num_rows($result) > 0) {
    list($usedphone) = pg_fetch_array($result);
  }
  pg_free_result($result);
  $usedphone_esc = pg_escape_string($usedphone);

  $query = "SELECT COALESCE(NULLIF(phone1,'$usedphone_esc'),phone2,phone1) FROM rooms WHERE room='$room_esc'";
  $result = sdsQuery($query);
  if(!$result or pg_num_rows($result) != 1) {
    contactTech("Could not search rooms",false);
    return null;
  }
  list($phone) = pg_fetch_array($result);
  pg_free_result($result);

  $phone_esc = pg_escape_string($phone);

  $query = '';
  if(isset($curRoom)) {
    $curRoom_esc = pg_escape_string($curRoom);
    $query .= "UPDATE old_room_assignments SET moveout = now() WHERE username='$username_esc' AND room='$curRoom_esc' AND moveout IS NULL;";
  }
  $query .= "INSERT INTO old_room_assignments (username,room) VALUES ('$username_esc','$room_esc');";

  $query .= "UPDATE directory SET room='$room_esc',phone='$phone_esc' WHERE username='$username_esc'";
  $result = sdsQuery($query);
  if(!$result or pg_affected_rows($result) != 1) {
    contactTech("Could not assign room",false);
    return null;
  }
  pg_free_result($result);
  return true;
}

function modifyUser($username, $array) {

  if(!$array) // nothing to do
    return true;

  $username_esc = pg_escape_string($username);
  # what can be changed? (also room, but handled separately)
  $fields = array('lastname','firstname','year','private','type','email',
		  'title','phone');
  if(isset($array['year']) and $array['year'] == 0)
    $array['year'] = null;
  if(isset($array['title']) and $array['title'] === '')
    $array['title'] = null;

  if(array_key_exists('room',$array)) {
    if(isset($array['room'])) {
      moveRoom($username, $array['room']);
    } else {
      clearRoom($username);
    }
  }

  $usedfields = array();
  foreach($fields as $field) {
    if(array_key_exists($field,$array)) // returns true for null value
      $usedfields[] = $field;
  }

  $query = "UPDATE directory SET " . sqlArrayUpdate($array, $usedfields)
     . " WHERE username='$username_esc'";
  $result = sdsQuery($query);
  if(!$result or pg_affected_rows($result) != 1) {
    contactTech("Could not update directory",false);
    return null;
  }

  return true;
}
