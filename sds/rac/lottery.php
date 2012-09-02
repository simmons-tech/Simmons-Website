<?php
require_once("../sds.php");
require_once("user-administration-tools.inc.php");

sdsRequireGroup("RAC");
sdsIncludeHeader("Simmons Hall Rooming Lottery");

#Check to see if we are calling ourself. If so, check to see what we want to do
$sdsAction = getStringArg('sdsAction');
if($sdsAction === "ADD") { doADD($_REQUEST); }
elseif($sdsAction === "BLOCK") { doBLOCK($_REQUEST); }
elseif($sdsAction === "SQUAT") { doSQUAT($_REQUEST); }
elseif($sdsAction === "PICK") { doPICK($_REQUEST); }
elseif($sdsAction === "ROOMS") { doROOMS($_REQUEST); }
elseif($sdsAction === "SWAP") { doSWAP($_REQUEST); }
elseif($sdsAction === "MERGE") { doMERGE($_REQUEST); }
elseif($sdsAction === "CLEAR") { doCLEAR($_REQUEST); }
else { doDEFAULT(); }

sdsIncludeFooter();
exit;

# Default action, this is for when the page is called directly, or from
# another page.
function doDEFAULT() {
#Print out the basic HTML Form
?>

<form action="lottery.php" method="post">
<?php echo sdsForm() ?>

<h1>The following options are currently available:</h1>
<hr />
<h2>Room Setup (Do this first!)</h2>
<ul style="list-style-type:none">
  <li>To pick which rooms are to be lotteried off:
    <input type="submit" name="sdsAction" value="ROOMS" /></li>
</ul>

<h2>Setting up the lottery order</h2>
<ul style="list-style-type:none">
  <li>To view or setup the lottery order:
    <input type="submit" name="sdsAction" value="ADD" /></li>
  <li>To view or configure blocks:
    <input type="submit" name="sdsAction" value="BLOCK" /></li>
  <li>To set who is squatting:
    <input type="submit" name="sdsAction" value="SQUAT" /></li>
</ul>

<h2>Assigning rooms</h2>
<ul style="list-style-type:none">
  <li>To input what room has been selected:
    <input type="submit" name="sdsAction" value="PICK" /></li>
</ul>

<h2>Swapping rooms</h2>
<ul style="list-style-type:none">
  <li>To swap rooms choices:
    <input type="submit" name="sdsAction" value="SWAP" /></li>
</ul>

<h2>Merging Results</h2>
<ul style="list-style-type:none">
  <li>To merge the lottery results into the database:
    <input type="submit" name="sdsAction" value="MERGE" /><br />
    <span style="font-size:small;font-weight:bold">Note that this will
      overwrite the existing database, so if it's the spring lottery, don't
      merge your results until after the spring semester is over!</span></li>
</ul>

<h2>To clear the lottery results and start over</h2>
<ul style="list-style-type:none">
  <li>To reset the lottery completely:
    <input type="submit" name="sdsAction" value="CLEAR" /><br />
    <span style="font-size:small;font-weight:bold">Use with caution &mdash;
      this is not the "undo" function!</span></li>
</ul>
</form>
<?php

}

function doCLEAR($array) {

  if(@$array['submit'] === "Clear") {
    $query = "DELETE FROM rooming";
    $result = sdsQuery($query);
    if(!$result)
      contactTech("Could not reset lottery");
    pg_free_result($result);
    echo "<h2>The lottery has been reset.</h2>\n";
  } else {
?>
<h2>Are you sure? Click here if you're really sure, as this will be
  irrevocable.</h2>
<form action="lottery.php" method="post">
<?php echo sdsForm() #' ?>
  <input type="hidden" name="sdsAction" value="CLEAR" />
  <input type="submit" name="submit" value="Clear" />
</form>
<p>Or just go back:</p>
<?php
  }
  echo "<form action='lottery.php' method='get'>\n";
  echo sdsForm();
  echo "  <input type='submit' value='Go Back' />\n";
  echo "</form>\n";
}

function doMERGE($array) {

  if(@$array['submit'] === "Merge") {
    $query = <<<ENDQUERY
SELECT username
FROM active_directory
WHERE type='U' AND
      username NOT IN (SELECT username
                       FROM rooming
                       WHERE room IS NOT NULL AND room != 'SKIP')
ENDQUERY;
    $result = sdsQuery($query);
    if(!$result)
      contactTech("Could not query active directory");

    # run this as a transaction and roll back if something fails
    $transres = sdsQuery("BEGIN");
    if(!$transres)
      contactTech("Cold not start transaction");
    pg_free_result($transres);

    while($row = pg_fetch_array($result)) {
      if(!disableUser($row['username'])) {
	$transres = sdsQuery("ROLLBACK");
	if(!$transres)
	  contactTech("Could not rollback. DIRECTORY MAY BE CORRUPTED");
	pg_free_result($transres);
	sdsIncludeFooter();
	exit;
      }	
    }
    pg_free_result($result);

    $query = <<<ENDQUERY
SELECT username, room
FROM rooming
WHERE room IS NOT NULL AND room != 'SKIP'
ENDQUERY;
    $result = sdsQuery($query);
    if(!$result) {
      contactTech("Could not read lottery",false);
      $transres = sdsQuery("ROLLBACK");
      if(!$transres)
	contactTech("Could not rollback. DIRECTORY MAY BE CORRUPTED");
      pg_free_result($transres);
      sdsIncludeFooter();
      exit;
    }

    while($row = pg_fetch_object($result)) {
      if(!clearRoom($row->username, $row->room)) {
	$transres = sdsQuery("ROLLBACK");
	if(!$transres)
	  contactTech("Could not rollback. DIRECTORY MAY BE CORRUPTED");
	pg_free_result($transres);
	sdsIncludeFooter();
	exit;
      }	
    }
    pg_result_seek($result,0);
    while($row = pg_fetch_object($result)) {
      if(!moveRoom($row->username, $row->room)) {
	$transres = sdsQuery("ROLLBACK");
	if(!$transres)
	  contactTech("Could not rollback. DIRECTORY MAY BE CORRUPTED");
	pg_free_result($transres);
	sdsIncludeFooter();
	exit;
      }
    }
    $transres = sdsQuery("COMMIT");
    if(!$transres) {
      contactTech("Could not commit directory",false);
      $transres = sdsQuery("ROLLBACK");
      if(!$transres)
	contactTech("Could not rollback. DIRECTORY MAY BE CORRUPTED");
      pg_free_result($transres);
      sdsIncludeFooter();
      exit;
    }
    pg_free_result($transres);
    pg_free_result($result);

    echo "<h2>All selected users in the lottery have been merged into the main database.</h2>\n";
  } else {
?>
<h2>Are you sure? Click here if you're really sure as this will be
  irrevocable.</h2>
<form action="lottery.php" method="post">
<?php echo sdsForm() #' ?>
  <input type='hidden' name='sdsAction' value='MERGE' />
  <input type='submit' name='submit' value='Merge' />
</form>
<p>Or just go back:</p>
<?php
  }
  echo "<form action='lottery.php' method='get'>\n";
  echo sdsForm();
  echo "  <input type='submit' value='Go Back' />\n";
  echo "</form>\n";
}

function doSWAP($array) {

  if(@$array["submit"] === "Swap") {

    $firstperson_esc = sdsSanitizeString(@$array['firstPerson']);
    $secondperson_esc = sdsSanitizeString(@$array['secondPerson']);

    $query = "SELECT username, room FROM rooming WHERE (username='$firstperson_esc' OR username='$secondperson_esc') AND room IS NOT NULL AND room != 'SKIP'";
    $result = sdsQuery($query);
    if(!$result)
      contactTech("Could not search lottery");
    if(pg_num_rows($result) != 2) {
      echo "<h2 class='error'>Could not find users!</h2>\n";
      sdsIncludeFooter();
      exit;
    }
    $rowone = pg_fetch_object($result);
    $rowtwo = pg_fetch_object($result);
    pg_free_result($result);

    # make swap one transaction
    $query = "UPDATE rooming set room='" . pg_escape_string($rowone->room) .
      "' WHERE username='" . pg_escape_string($rowtwo->username) . "';";
    $query .= "UPDATE rooming set room='" . pg_escape_string($rowtwo->room) .
      "' WHERE username='" . pg_escape_string($rowone->username) . "';";
    $result = sdsQuery($query);
    if(!$result)
      contactTech("Could not perform swap");
    pg_free_result($result);
    echo "<h2>",htmlspecialchars($rowtwo->username)," swapped to ",
      htmlspecialchars($rowone->room),", ",htmlspecialchars($rowone->username),
      " swapped to ",htmlspecialchars($rowtwo->room),".</h2>\n";

  }

  $query = <<<ENDQUERY
SELECT username, rooming.room,firstname,lastname
FROM rooming JOIN directory USING (username)
WHERE rooming.room IS NOT NULL AND rooming.room != 'SKIP'
ORDER BY lastname ASC, firstname ASC
ENDQUERY;
  $result = sdsQuery($query);
  if(!$result)
    contactTech("Could not search lottery");

  $options = "";

  while($row = pg_fetch_object($result)) {
    $options .= "    <option value='".htmlspecialchars($row->username)."'>".
      htmlspecialchars($row->lastname).", ".htmlspecialchars($row->firstname).
      " (".htmlspecialchars($row->username).") [".htmlspecialchars($row->room).
      "]</option>\n";
  }

?>

<h2>Please select who's rooms to swap.</h2>

<form action="lottery.php" method="post">
<?php echo sdsForm() #' ?>

  <input type="hidden" name="sdsAction" value="SWAP" />
  <select name="firstPerson">
<?php echo $options ?>
  </select>
  &lt;===&gt;
  <select name='secondPerson'>
<?php echo $options ?>
  </select><br />
  <input type="submit" name="submit" value="Swap" />
</form>
<form action="lottery.php" method="get">
<?php echo sdsForm() ?>
  <input type="submit" value="Go Back" />
</form>

<?php

}

# This function describes the actions of when you want actually pick a room in
# the lottery.
function doPICK($array) {

  if (@$array["submit"] == "Pick") {
    // The function is being entered into after a pick has been made, so enter
    // the pick in the db

    $room_esc = sdsSanitizeString(@$array['room']);
    $room_disp = htmlspecialchars(maybeStripslashes(@$array['room']));

    if($room_esc !== 'SKIP') {
      // Make sure it's a valid pick first
      $query = "SELECT type='Double' AS isdouble FROM rooms WHERE room='$room_esc' AND NOT frosh AND (type='Single' OR type='Double')";
      $result = sdsQuery($query);
      if(!$result)
	contactTech("Could not search rooms");
      if(pg_num_rows($result) != 1) {
	echo "<h2 class='error'>",$room_disp," is not a choosable room.</h2>\n";
	sdsIncludeFooter();
	exit;
      }
      $record = pg_fetch_array($result);
      pg_free_result($result);
      $isdouble = ($record['isdouble'] === 't');

      // Check occupancy
      $query = "SELECT count(*) FROM rooming WHERE room='$room_esc'";
      $result = sdsQuery($query);
      if(!$result or pg_num_rows($result) != 1)
	contactTech("Could not search lottery");
      list($occupancy) = pg_fetch_array($result);
      pg_free_result($result);

      if($occupancy - $isdouble > 0) {
	echo "<h2 class='error'>",$room_disp,
	  " has already reached it's maximum capacity!</h2>\n";
	sdsIncludeFooter();
	exit;
      }
    }

    $query = "UPDATE rooming SET room='$room_esc' WHERE username='" .
      sdsSanitizeString(@$array["pickuser"])."'";
    $result = sdsQuery($query);
    if(!$result or pg_affected_rows($result) != 1)
      contactTech("Could not enter choice");
    pg_free_result($result);
    echo "<h2>User ",htmlspecialchars(maybeStripslashes(@$array["pickuser"])),
      " picked room ",$room_disp,"</h2>\n";

    if($isdouble and $occupancy == 0) {
      # The room is a double and has only one occupant, so allow them to bring
      # up someone with them.
?>

<h2>This is a double, if they want to bring someone with them, please select
  them from the list below.</h2>
<form action="lottery.php" method="post">
<?php echo sdsForm() ?>
  <input type="hidden" name="sdsAction" value="PICK" />
  <select name="pickuser">
<?php
      $query = <<<ENDQUERY
SELECT username,firstname,lastname
FROM rooming JOIN directory USING (username)
WHERE rooming.room IS NULL
ORDER BY lastname ASC, firstname ASC
ENDQUERY;
      $result = sdsquery($query);
      if(!$result) {
	echo "</select></form>\n";
	contactTech("Could not search lottery");
      }
      while($record = pg_fetch_object($result)) {
	echo "    <option value='",htmlspecialchars($record->username),"'>",
	  htmlspecialchars($record->lastname),", ",
	  htmlspecialchars($record->firstname)," (",
	  htmlspecialchars($record->username),")</option>\n";
      }
?>
  </select>
  <input type="hidden" name="room" value="<?php echo $room_disp ?>" />

  <input type="submit" name="submit" value="Pick" />
</form>
<h2>Or you may continue on with the normal lottery here:</h2>
<?php
    }
  } elseif(@$array['submit'] === "Unskip" or @$array['submit'] === "Unpick") {
    $query = "UPDATE rooming SET room=NULL WHERE username='".
      sdsSanitizeString(@$array['username'])."'";
    $result = sdsQuery($query);
    if(!$result or pg_affected_rows($result) != 1)
      contactTech("Could not unpick");
    echo "<p>To remove more choices, go to the <a href='",
      sdsLink("lottery.php","sdsAction=SQUAT"), "'>Unsquatting Page</a></p>\n";
  }

   # Now that picking people is taken care of, look at going into the main
   # part, and see who you want to pick next
   # Find the next user
  $query = <<<ENDQUERY
SELECT username,adjusted_pick,firstname,lastname
FROM rooming JOIN directory USING (username)
WHERE rooming.room IS NULL AND adjusted_pick IS NOT NULL
ORDER BY adjusted_pick ASC LIMIT 1
ENDQUERY;
  $result = sdsQuery($query);
  if(!$result)
    contactTech("Could not search lottery");
  $nextuser = pg_num_rows($result) == 1 ? pg_fetch_array($result) : false;
  pg_free_result($result);

  # If there is another user to go then bring up the selection form
  if($nextuser) {

    $username_disp = htmlspecialchars($nextuser['username']);

    # Find the available rooms
    $query = <<<ENDQUERY
SELECT room FROM rooms LEFT JOIN rooming USING (room)
WHERE NOT frosh
GROUP BY room,type
HAVING ((type='Double' AND count(username) < 2) OR
        (type='Single' AND count(username) < 1))
ORDER BY to_number(room,'9999'), room
ENDQUERY;
    $result = sdsQuery($query);

?>
<form action="lottery.php" method="post">
<?php echo sdsForm() ?>

<table class='rooming'>
  <tr>
    <th>Pick</th>
    <th>Name</th>
    <th>Room</th>
  </tr>
  <tr>
    <td><?php echo htmlspecialchars($nextuser['adjusted_pick']) ?></td>
    <td><?php echo htmlspecialchars($nextuser['lastname'] . ", " .
				    $nextuser['firstname']) ?></td>
    <td><select name="room">
<?php
    while($record = pg_fetch_object($result)) {
      echo "      <option>",htmlspecialchars($record->room),"</option>\n";
    }
?>
    </select></td>
  </tr>
</table>
<input type="hidden" name="sdsAction" value="PICK" />
<input type="hidden" name="pickuser" value="<?php echo $username_disp ?>" />
<input type="submit" name="submit" value="Pick" />
</form>

<form action="lottery.php" method="post">
<?php echo sdsForm() ?>

  <input type="hidden" name="sdsAction" value="PICK" />
  <input type="hidden" name="submit" value="Pick" />
  <input type="hidden" name="room" value="SKIP" />
  <input type="hidden" name="pickuser" value="<?php echo $username_disp ?>"  />
  <input type="submit" value="Skip" />
</form>

<?php
  } else {
    # If not, the lottery is ended
    echo "<h2>The lottery is over</h2>\n";
  }
  if(@$array["submit"] === "Pick" and @$array['room'] !== "SKIP") {
    $query = "SELECT firstname,lastname FROM directory WHERE username='" .
      sdsSanitizeString(@$array['pickuser']) . "'";
    $result = sdsQuery($query);
    if(!$result or pg_num_rows($result) != 1)
      contactTech("Could not search directory");
    $lastuser = pg_fetch_array($result);
    pg_free_result($result);
    echo "<h2>Remove the last pick (",htmlspecialchars($lastuser['lastname']),
      ", ",$lastuser['firstname']," (",htmlspecialchars(@$array['pickuser']),
      ") picked ",htmlspecialchars(@$array['room']),"):</h2>\n";
?>

<form action='lottery.php' method='post'>
<?php echo sdsForm() ?>

<input type='hidden' name='sdsAction' value='PICK' />
<input type='hidden' name='username' value='<?php echo htmlspecialchars(@$array['pickuser']) ?>' />
<input type='submit' name='submit' value='Unpick' />
</form>
<?php
  }

  $query = <<<ENDQUERY
SELECT username,firstname,lastname
FROM rooming JOIN directory USING (username)
WHERE rooming.room='SKIP'
ORDER BY adjusted_pick ASC
ENDQUERY;
  $result = sdsQuery($query);
  if(!$result)
    contactTech("Could not search lottery");
  if(pg_num_rows($result)) {
    echo "<h2>Go back to skipped person:</h2>\n";
    echo "<form action='lottery.php' method='post'>\n";
    echo sdsForm();
    echo "  <input type='hidden' name='sdsAction' value='PICK' />";
    echo "  <select name='username'>";
    while($row = pg_fetch_object($result)) {
      echo "    <option value='",htmlspecialchars($row->username),"'>",
	htmlspecialchars($row->lastname),", ",
	htmlspecialchars($row->firstname)," (",
	htmlspecialchars($row->username),")</option>\n";
    }
    echo "  </select>\n";
    echo "  <input type='submit' name='submit' value='Unskip' />\n";
    echo "</form>\n";
  }

  echo "<form action='lottery.php' method='get'>\n";
  echo sdsForm();
  echo "  <input type='submit' value='Go Back' />\n";
  echo "</form>\n";
}

# Function to handle picking which rooms to have in the lottery
function doROOMS($array) {

  require_once('../sds/ordering.inc.php');

  if(@$array["submit"] === "Update") {
    $query = "SELECT room FROM rooms WHERE type='Single' OR type='Double'";
    $result = sdsQuery($query);
    if(!$result)
      contactTech("Could not find rooms");
    while($roomrecord = pg_fetch_array($result)) {
      $updatequery = "UPDATE rooms SET frosh=" .
	(@$array[$roomrecord['room']]?'false':'true') .
	" WHERE room='" . pg_escape_string($roomrecord['room']) . "'";
      $updateresult = sdsQuery($updatequery);
      if(!$updateresult or pg_affected_rows($updateresult) != 1)
	contactTech("Could not update room status");
      pg_free_result($updateresult);
    }
    pg_free_result($result);
  }

  $sortby = getSortby($array['sortby'],2,3,'rooming_lottery_sortby');
  $orderby_array = array("to_number(room,'9999') ASC, room ASC",
			 "to_number(room,'9999') DESC, room DESC",
			 'type ASC, size ASC, room ASC',
			 'type DESC, size DESC, room ASC',
			 'size ASC, room ASC',
			 'size DESC, room ASC');

  $query = "SELECT room,type,size,frosh FROM rooms WHERE (type='Double' OR type='Single') ORDER BY ".$orderby_array[$sortby];


  $result = sdsQuery($query);
  if(!$result)
    contactTech("Could not find rooms");

  echo "<form action='lottery.php' method='post'>\n";
  echo sdsForm();

  echo "<input type='hidden' name='sdsAction' value='ROOMS' />\n";
  echo "<table class='rooming'>\n";
  echo "  <tr>\n";
  makeSortTH("Room",0,$sortby,"sdsAction=ROOMS");
  makeSortTH("Type",1,$sortby,"sdsAction=ROOMS");
  makeSortTH("Size",2,$sortby,"sdsAction=ROOMS");
  echo "<th>Included</th>\n";
  echo "  </tr>\n";

  $included = 0;
  $excluded = 0;
  while($row = pg_fetch_object($result)) {
    echo "  <tr>\n";
    echo "    <td class='room'>",htmlspecialchars($row->room),"</td>\n";
    echo "    <td>",htmlspecialchars($row->type),"</td>\n";
    echo "    <td>",htmlspecialchars($row->size),"</td>\n";
    echo "    <td><input type='checkbox' name='",
      htmlspecialchars($row->room), "'";
    if($row->frosh==='f') { echo "checked "; $included++; }
    else { $excluded++; }
    echo " /></td>\n";
    echo "  </tr>\n";
  }
  echo "</table>\n";
  echo "<p>Included room count: ",$included,".  Excluded room count: ",
    $excluded,".</p>\n";
  echo "<input type='submit' name='submit' value='Update' />\n";
  echo "</form>\n";
  echo "<form action='lottery.php' method='get'>\n";
  echo sdsForm();
  echo "  <input type='submit' value='Go Back'>\n";
  echo "</form>\n";	
}

# Function to Add someone to the lottery, after they have been drawn from the
# hat.
function doADD($array) {

  // Get the current pick number
  $query = "SELECT lottery_pick FROM rooming ORDER BY lottery_pick DESC LIMIT 1";
  $result = sdsQuery($query);
  if(!$result)
    contactTech("Could not query lottery");
  if(pg_num_rows($result) == 1) {
    list($lastpick) = pg_fetch_array($result);
  } else {
    $lastpick = 0;
  }
  pg_free_result($result);

  if(@$array["submit"] === "Select") {
    $username_esc = sdsSanitizeString(@$array['username']);
    $username_disp = htmlspecialchars(maybeStripslashes(@$array['username']));
    $query = "SELECT 1 FROM public_active_directory WHERE username='$username_esc'";
    $result = sdsQuery($query);
    if(!$result)
      contactTech("Could not query directory");
    if(pg_num_rows($result) != 1) {
      echo "<h2 class='error'>",$username_disp," is not a valid user.</h2>\n";
      sdsIncludeFooter();
      exit;
    }
    pg_free_result($result);

    $query = "INSERT INTO rooming (lottery_pick, username) VALUES ('" .
      ($lastpick+1) . "', '$username_esc')";
    $result = sdsQuery($query);
    if(!$result or pg_affected_rows($result) != 1)
      contactTech("Could not enter user");
    pg_free_result($result);
    echo "<p>",$username_disp," is now pick ",$lastpick+1,"</p>\n";

  } elseif(@$array["submit"] === "Remove last pick") {
    $query = "DELETE FROM rooming WHERE lottery_pick='$lastpick'";
    $result = sdsQuery($query);
    if(!$result or pg_affected_rows($result) != 1)
      contactTech("Could not remove user");
    pg_free_result($result);
    echo "<p>Removed pick ",$lastpick,"</p>\n";
  }

  echo "<h2>Enter name of person for pick number ",$lastpick+1,"</h2>\n";

  $query = <<<ENDQUERY
SELECT username,firstname,lastname
FROM public_active_directory
WHERE type='U' AND username NOT IN (SELECT username FROM rooming)
ORDER BY lastname ASC, firstname ASC
ENDQUERY;
  $result = sdsQuery($query);
  if(!$result)
    contactTech("Could not search directory");

?>
<form action="lottery.php" method="post">
<?php echo sdsForm() ?>

<input type="hidden" name="sdsAction" value="ADD" />
<select name="username">
<?php
  while($data = pg_fetch_object($result)) {
    echo "  <option value='",htmlspecialchars($data->username),"'>",
    htmlspecialchars($data->lastname),", ",htmlspecialchars($data->firstname),
    " (",htmlspecialchars($data->username ),")</option>\n";
  }
  pg_free_result($result);

?>
</select>
<input type="submit" name="submit" value="Select" />
<input type="submit" name="submit" value="Remove last pick" />
</form>
<form action="lottery.php" method="get">
<?php echo sdsForm() ?>

<input type="submit" value="Go Back" />
</form>
<?php
}

#Function to handle squatting.  Also can be used to unset a room pick
function doSQUAT($array) {

  if(@$array['submit'] === "Submit") {

    $query = "SELECT username,room FROM rooming";
    $result = sdsQuery($query);
    if(!$result)
      contactTech("Could not search lottery");

    while($data = pg_fetch_object($result)) {
      if(@$array[$data->username]) {
	$username_esc = pg_escape_string($data->username);
	if(isset($data->room)) {
	  $updatequery =
	    "UPDATE rooming SET room=NULL WHERE username='$username_esc'";
	  $updateresult = sdsQuery($updatequery);
	  if(!$updateresult or pg_affected_rows($updateresult) != 1)
	    contactTech("Could not unsquat user");
	  pg_free_result($updateresult);
	} else {
	  $updatequery = <<<ENDQUERY
UPDATE rooming SET room=public_active_directory.room
FROM public_active_directory
WHERE rooming.username = '$username_esc' AND
      public_active_directory.username = '$username_esc'
ENDQUERY;
	  $updateresult = sdsQuery($updatequery);
	  if(!$updateresult or pg_affected_rows($updateresult) != 1)
	    contactTech("Could not squat user");
	  pg_free_result($updateresult);
	}
      }
    }
    pg_free_result($result);
  }

  $query = <<<ENDQUERY
SELECT username,firstname,lastname,rooming.room
FROM rooming JOIN public_active_directory USING (username)
ORDER BY lastname ASC, firstname ASC
ENDQUERY;
  $result = sdsQuery($query);
  if(!$result)
    contactTech("Could not search lottery");

?>
<form action="lottery.php" method="post">
<?php echo sdsForm() ?>
  <input type="hidden" name="sdsAction" value="SQUAT" />
  <table class="rooming">
    <tr>
      <th>Name</th>
      <th>Username</th>
      <th>Squat</th>
      <th>UnSquat</th>
    </tr>
<?php

   while($data = pg_fetch_object($result)) {
     echo "    <tr>\n";
     echo "      <td class='name'>",htmlspecialchars($data->lastname),", ",
       htmlspecialchars($data->firstname),"</td>\n";
     echo "      <td class='name'>",htmlspecialchars($data->username),
       "</td>\n";
     echo "      <td>";
     if(isset($data->room)) { echo "</td>\n      <td>"; }
     echo "<input type='checkbox' name='",htmlspecialchars($data->username),
     "' />";
     if(!isset($data->room)) { echo "</td>\n      <td>"; }
     echo "</td>\n";
     echo "    </tr>\n";
   }
?>

  </table>
  <input type="submit" name="submit" value="Submit" />
</form>
<form action="lottery.php" method="get">
<?php echo sdsForm() ?>
  <input type="submit" value="Go Back">
</form>
<?php
}

function doBLOCK($array) {

  if(@$array['submit'] === "Update") {
    $query = "SELECT block_num FROM rooming WHERE block_num IS NOT NULL ORDER BY block_num DESC LIMIT 1";
    $result = sdsQuery($query);
    if(!$result)
      contactTech("Could not search lottery");
    if(pg_num_rows($result) == 1) {
      list($maxblock) = pg_fetch_array($result);
    } else {
      $maxblock = 0;
    }
    pg_free_result($result);

    $query = "SELECT username,block_num FROM rooming";
    $result = sdsQuery($query);
    if(!$result)
      contactTech("Could not search lottery");

    while($data = pg_fetch_object($result)) {
      if(!empty($array[$data->username])) {
	if(isset($data->block_num)) {
	  $query = "UPDATE rooming SET block_num=NULL WHERE username='" .
	    pg_escape_string($data->username) . "'";
	} else {
	  $query = "UPDATE rooming SET block_num=" . ($maxblock+1) .
	    " WHERE username='" . pg_escape_string($data->username) . "'";
	}
	$updateresult = sdsQuery($query);
	if(!$updateresult or pg_affected_rows($updateresult) != 1)
	  contactTech("Blocking failed");
	pg_free_result($updateresult);
      }
    }
    pg_free_result($result);
  } elseif(@$array['submit'] === "Generate List") {

    $query = "UPDATE rooming SET adjusted_pick = 100 * lottery_pick - 1";
    $result = sdsQuery($query);
    if(!$result)
      contactTech("Could not reset lottery order");
    pg_free_result($result);

    $query = <<<ENDQUERY
UPDATE rooming SET adjusted_pick = floor(100 * block_pick)
FROM (SELECT block_num,avg(lottery_pick) AS block_pick
      FROM rooming AS blockcheck
      GROUP BY block_num) AS blockinfo
WHERE rooming.block_num IS NOT NULL AND
      rooming.block_num = blockinfo.block_num
ENDQUERY;
#    $query = "SELECT DISTINCT block_num FROM rooming WHERE block_num IS NOT NULL ORDER BY block_num ASC;";
    $result = sdsQuery($query);
    if(!$result)
      contactTech("Could not assign lottery picks");
    pg_free_result($result);

    $query = "SELECT username,lastname,firstname,block_num FROM rooming JOIN public_active_directory USING (username) ORDER BY adjusted_pick ASC, lottery_pick ASC";
    $result = sdsQuery($query);
    if(!$result)
      contactTech("Could not read lottery");

?>
<table class="rooming">
  <tr>
    <th>Pick</th>
    <th>Name</th>
    <th>Block</th>
  </tr>
<?php
    $i = 0;
    while($data = pg_fetch_object($result)) {
      $i++;
      $updatequery = "UPDATE rooming SET adjusted_pick=$i WHERE username='" .
	pg_escape_string($data->username) . "'";
      $updateresult = sdsQuery($updatequery);
      if(!$updateresult or pg_affected_rows($updateresult) != 1) {
	echo "</table>\n";
	contactTech("Could not assign lottery order");
      }
      pg_free_result($updateresult);
      echo "  <tr>\n";
      echo "    <td>",$i,"</td>\n";
      echo "    <td>",htmlspecialchars($data->lastname ),", ",
	htmlspecialchars($data->firstname),"</td>\n";
      echo "    <td>",$data->block_num,"</td>\n";
      echo "  </tr>\n";
    }
    echo "</table>\n";
  }

  $query = "SELECT username,firstname,lastname,lottery_pick,block_num FROM public_active_directory JOIN rooming USING (username) ORDER BY lastname,firstname";
  $result = sdsQuery($query);
  if(!$result)
    contactTech("Could not search lottery");

?>
<form action="lottery.php" method="post">
<?php echo sdsForm() ?>

<table class='rooming'>
  <tr>
    <th class="name">Name</th>
    <th>Raw Pick</th>
    <th>Block Num</th>
    <th>Block</th>
    <th>Unblock</th>
  </tr>
<?php
  while($data = pg_fetch_object($result)) {
    echo "  <tr>\n";
    echo "    <td class='name'>",htmlspecialchars($data->lastname),", ",
      htmlspecialchars($data->firstname),"</td>\n";
    echo "    <td>",$data->lottery_pick,"</td>\n";
    echo "    <td>",$data->block_num,"</td>\n";
    echo "    <td>";
    if(isset($data->block_num)) { echo "</td>\n    <td>"; }
    echo "<input type='checkbox' name='",htmlspecialchars($data->username),
    "' />";
    if(!isset($data->block_num)) { echo "</td>\n    <td>"; }
    echo "</td>\n";
    echo "  </tr>\n";
  }
?>
</table>
<input type="hidden" name="sdsAction" value="BLOCK" />
<input type="submit" name="submit" value="Update" />
<input type="submit" name="submit" value="Generate List" />
</form>
<form action="lottery.php" method="get">
<?php echo sdsForm() ?>

<input type="submit" value="Go Back" />
</form>

<?php
}
