<?php 
require_once("../sds.php");
require_once("user-administration-tools.inc.php");
sdsRequireGroup("RAC");

//====================================================================
//          LOAD TABLE
//====================================================================  
function loadTable() {
  if (!$_FILES['batchfile'] or
      $_FILES['batchfile']['error'] or
      $_FILES['batchfile']['size'] == 0) {
    sdsIncludeHeader("Simmons RAC Batch Update - Process");
    echo "Sorry, file upload failed.";
    @unlink($_FILES['batchfile']['tmp_name']);
    sdsIncludeFooter();
    exit();
  }

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

  if(strlen($separator) != 1) {
    sdsIncludeHeader("Simmons RAC Batch Update - Process");
    echo "<h2 class='error'>Field separator can only be one character</h2>\n";
    sdsIncludeFooter();
    exit;
  }

  $handle = fopen($_FILES['batchfile']['tmp_name'], 'r');
  $table = array();
  while(($data = fgetcsv($handle, 10000, $separator)) !== false) {
    $table[] = $data;
  }
  fclose($handle);
  unlink($_FILES['batchfile']['tmp_name']);
  return $table;
}

//====================================================================
//          GUESS INTERPRETATION
//====================================================================  
function guessInterpretation($headerRow) {
  $interpretation = array();
  foreach($headerRow as $index => $name) {
    if(stristr($name, "user") and !isset($interpretation["username"])) {
      $interpretation["username"] = $index;
      continue;
    }

    if(stristr($name, "last") and !isset($interpretation["lastname"])) {
      $interpretation["lastname"] = $index;
      continue;
    }

    if(stristr($name, "first") and !isset($interpretation["firstname"])) {
      $interpretation["firstname"] = $index;
      continue;
    }

    if(stristr($name, "title") and !isset($interpretation["title"])) {
      $interpretation["title"] = $index;
      continue;
    }

    if((stristr($name, "year") or stristr($name, "class")) and
       !isset($interpretation["year"])) {
      $interpretation["year"] = $index;
      continue;
    }

    if (stristr($name, "room") and !isset($interpretation["room"])) {
      $interpretation["room"] = $index;
      continue;
    }

    if (stristr($name, "phone") and !isset($interpretation["phone"])) {
      $interpretation["phone"] = $index;
      continue;
    }

    if(stristr($name, "mail") and !isset($interpretation["email"])) {
      $interpretation["email"] = $index;
      continue;
    }

    if(stristr($name, "type") and !isset($interpretation["type"])) {
      $interpretation["type"] = $index;
      continue;
    }

    if(stristr($name, "hidden") and !isset($interpretation["hidden"])) {
      $interpretation["hidden"] = $index;
      continue;
    }
  }
  $interpretation = array_flip($interpretation);

  $onblankdefaults = array("username"  => "ignore",
			   "lastname"  => "ignore",
			   "firstname" => "ignore",
			   "title"     => "set",
			   "year"      => "ignore",
			   "room"      => "set",
			   "phone"     => "set",
			   "email"     => "ignore",
			   "type"      => "ignore",
			   "hidden"    => "ignore",
			   ""          => "ignore");

  for($i=0;$i<count($headerRow);$i++) {
    if(!isset($interpretation[$i])) $interpretation[$i] = "";
    $interpretation[$i] = array($interpretation[$i],
				$onblankdefaults[$interpretation[$i]]);
  }

  return $interpretation;
}

//====================================================================
//          CONFIRM INTERPRETATION
//====================================================================  
function confirmInterpretation($table,$interpretation=null, $note="") {
  $encodedTable = base64_encode(serialize($table));

  $headerRow = $table[0];
  unset($table[0]);

  // interpretation is {column -> content}

  if(isset($interpretation)) {
    $hasHeader = isset($_REQUEST['hasHeader']);
    $defaultAction = maybeStripslashes($_REQUEST['defaultAction']);
  } else {
    $interpretation = guessInterpretation($headerRow);
    $hasHeader = true;
    $defaultAction = 'ignore';
  }

  $actions = array('ignore' => 'Left unchanged',
		   'disable' => 'Disabled',
		   'disable-under' => 'Disabled if undergrad',
		   'clear-under' => 'Clear room if undergrad');
  if(!isset($actions[$defaultAction]))
    $defaultAction = 'ignore';

  sdsIncludeHeader("Simmons RAC Batch Update - Table Interpretation");

?>
<form action='batchupdate-process.php' method='post'>
<?php echo sdsForm() ?>

<?php
  if($note) {
    echo "<p style='color:red;font-weight:bold'>",$note,"</p>\n";
  }
?>
<input type='submit' name='confirmedInterpretation'
       value='Confirm Below Interpretation' /><br />
<label>Users not in the uploaded file should be:
  <select name='defaultAction'>
<?php
  foreach($actions as $action => $desc) {
    echo "    <option value='",$action,"'",
      $action===$defaultAction?' selected="selected"':'',
      ">",$desc,"</option>\n";
  }
?>
  </select>
</label><br />

<label style="font-weight:bold">
  <input type='checkbox' name='hasHeader'<?php
                   echo $hasHeader?' checked="checked"':'' ?> />The italicized
  row is a header, and should not be included as part of the data</label>

<input type='hidden' name='table' value="<?php echo $encodedTable ?>" />

<table class="batchupdate">

  <tr>
<?php
  $options = array("username","lastname","firstname","title","year",
		   "room","phone","email","type","hidden");
  $onblankopts = array("ignore"  => "Ignore",
		       "set"     => "Set Blank",
		       "disable" => "Disable User");
  foreach($headerRow as $index => $cell) {
    echo "    <td><select name='interpretation[",$index,"]' size='7'>\n";
    foreach($options as $option) {
      echo "      <option";
      if($interpretation[$index][0] === $option) {
	echo ' selected="selected"';
      }
      echo ">",$option,"</option>\n";
    }

    echo "      <option";
    if($interpretation[$index][0] === "") {
      echo ' selected="selected"';
    }
    echo ">(ignore)</option>\n";

    echo "    </select></td>\n";
  }
  echo "  </tr>\n";
  echo "  <tr>\n";
  foreach($headerRow as $index => $cell) {
    echo "    <td>On Blank:<br /><select name='onblank[",$index,"]'>\n";
    foreach($onblankopts as $opt => $descr) {
      echo "      <option value='",$opt,"'",
	($interpretation[$index][1] === $opt ? ' selected="selected"' : ''),
	">",$descr,"</option>\n";
    }
    echo "    </select></td>\n";
  }
  echo "  </tr>\n";

  // original header
  echo "  <tr>\n";
  foreach($headerRow as $cell) {
    echo "    <th>",htmlspecialchars($cell),"</th>\n";
  }
  echo "  </tr>\n";

  // table body  
  foreach($table as $row) {
    echo "  <tr>\n";
    foreach($row as $cell) {
      echo "    <td>",htmlspecialchars($cell),"</td>\n";
    }
    echo "  </tr>\n";
  }
  echo "</table>\n";

  echo "</form>\n";

  sdsIncludeFooter();
  exit();
}

//====================================================================
//          CHECK INTERPRETATION
//====================================================================
function checkInterpretation($table) {
  if(isset($_REQUEST['interpretation']) and
     is_array($_REQUEST['interpretation']) and
     isset($_REQUEST['onblank']) and
     is_array($_REQUEST['onblank'])) {
    foreach($_REQUEST['interpretation'] as $key => $val) {
      $interpretation[$key] =
	array(maybeStripslashes($val),
	      maybeStripslashes($_REQUEST['onblank'][$key]));
    }
  } elseif(isset($_REQUEST['encodedInterpretation'])) {
    $interpretation = unserialize(base64_decode($_REQUEST['encodedInterpretation']));
  } else {
    confirmInterpretation($table);
  }

  $seen = array();

  foreach($interpretation as $value) {
    if($value[0] === '(ignore)')
      continue;
    if(isset($seen[$value[0]]))
      confirmInterpretation($table,$interpretation,
			    $value[0]." can only be specified once");
    $seen[$value[0]] = true;

    if($value[0] === 'username') {
    } elseif($value[0] === "lastname") {
      if($value[1] === 'set')
	confirmInterpretation($table,$interpretation,
			      "Lastname cannot be set blank");
    } elseif($value[0] === "firstname") {
      if($value[1] === 'set')
	confirmInterpretation($table,$interpretation,
			      "Firstname cannot be set blank");
    } elseif($value[0] === "title") {
    } elseif($value[0] === "year") {
    } elseif($value[0] === "room") {
    } elseif($value[0] === "phone") {
    } elseif($value[0] === "email") {
      if($value[1] === 'set')
	confirmInterpretation($table,$interpretation,
			      "Email cannot be set blank");
    } elseif($value[0] === "type") {
      if($value[1] === 'set')
	confirmInterpretation($table,$interpretation,
			      "Type cannot be set blank");
    } elseif($value[0] === "hidden") {
      if($value[1] === 'set')
	confirmInterpretation($table,$interpretation,
			      "Hidden cannot be set blank");
    }
  }

  if(!$seen['username'])
    confirmInterpretation($table,$interpretation,
			  "Username must be given");

  return $interpretation;
}

//====================================================================
//          PREPARE OPERATIONS
//====================================================================  
function prepareOperations($table, $interpretation) {

  if(!empty($_REQUEST['hasHeader'])) {
    unset($table[0]);
  }

  $yesno = array('1' => true,
		 '0' => false,
		 'y' => true,
		 'n' => false);

  $actions = array('ignore' => 'Left unchanged',
		   'disable' => 'Disabled',
		   'disable-under' => 'Disabled if undergrad',
		   'clear-under' => 'Clear room if undergrad');
  $defaultAction = maybeStripslashes($_REQUEST['defaultAction']);
  if(!isset($actions[$defaultAction]))
    $defaultAction = 'ignore';

  $add = array();
  $enable = array();
  $disable = array();
  $clearRoom = array();
  $move = array();
  $error = array();
  $ignore = array();

  $seenuser = array();

  foreach($table as $row) {
    $todo = array();
    $disableuser = false;
    $assigned = false;
    foreach($row as $index => $cell) {
      if($interpretation[$index][0] !== '(ignore)') {
	if(trim($cell) !== "") {
	  $todo[$interpretation[$index][0]] = trim($cell);
	} elseif($interpretation[$index][1] === 'disable') {
	  $disableuser = true;
	} elseif($interpretation[$index][1] === 'set') {
	  $todo[$interpretation[$index][0]] = '';
	}
      }
    }

    if(strlen($todo['username']) == 0) {
      $todo['why'] = "No username given";
      $error[] = $todo;
      continue;
    } else {
      $seenuser[$todo['username']] = true;
    }

    if($disableuser) {
      $query = "SELECT immortal FROM sds_users WHERE username='" .
	pg_escape_string($todo['username']) . "'";
      $result = sdsQuery($query);
      if(!$result)
	contactTech("Could not search users");
      if(pg_num_rows($result) == 1) {
	list($immortal) = pg_fetch_array($result);
	pg_free_result($result);
	if($immortal !== 't') {
	  $disable[] = $todo;
	  continue;
	}
      } else {
	pg_free_result($result);
	$todo['why'] = "User would be disabled, but does not exist";
	$ignore[] = $todo;
	continue;
      }
    }

    $query = "SELECT active,lastname FROM sds_users_all LEFT JOIN directory USING (username) WHERE username='" .
      pg_escape_string($todo['username']) . "'";
    $result = sdsQuery($query);
    if(!$result)
      contactTech("Cannot search users");
    if(pg_num_rows($result) == 0) {
      pg_free_result($result);
      if(empty($todo['lastname']) or empty($todo['firstname'])) {
	$todo['why'] = "User doesn't exist, and a name was not provided.";
	$error[] = $todo;
	continue;
      }
      if(!$assigned) {
	$add[] = $todo;
	$assigned = true;
      }
    } else {
      $record = pg_fetch_array($result);
      $active = ($record['active'] === 't');
      $indirectory = isset($record['lastname']);
      pg_free_result($result);
      if(!$indirectory and
	 (empty($todo['lastname']) or empty($todo['firstname']))) {
	$todo['why'] = "User has no directory entry, and a name was not provided.";
	$error[] = $todo;
	continue;
      }
      if(!$active and !$assigned) {
	$enable[] = $todo;
	$assigned = true;
      }
    }

    if(isset($todo['type'])) {
      $query = "SELECT 1 FROM user_types WHERE type='".
	pg_escape_string($todo['type'])."' AND active";
      $result = sdsQuery($query);
      if(!$result)
	contactTech("Could not search types");
      $count = pg_num_rows($result);
      pg_free_result($result);

      if($count == 0) {
        $todo['why'] = "Invalid type";
        $error[] = $todo;
        continue;
      }
    }

    if(isset($todo['hidden'])) {
      if(isset($yesno[strtolower(substr($todo['hidden'],0,1))])) {
	$todo['hidden'] = $yesno[strtolower(substr($todo['hidden'],0,1))];
      } else {
	$todo['why'] = "Can't interpret hidden flag";
	$error[] = $todo;
	continue;
      }
    }

    if(isset($todo['room'])) {
      if($todo['room'] !== '') {
	$query = "SELECT 1 FROM rooms WHERE room='" .
	  pg_escape_string($todo['room']) . "'";
	$result = sdsQuery($query);
	if(!$result)
	  contactTech("Could not search rooms");
	$count = pg_num_rows($result);
	pg_free_result($result);

	if($count == 0) {
	  $todo['why'] = "Invalid room number";
	  $error[] = $todo;
	  continue;
	}
	$query = "SELECT 1 FROM directory WHERE username='" .
	  pg_escape_string($todo['username']) . "' AND room='" .
	  pg_escape_string($todo['room']) . "'";
	$result = sdsQuery($query);
	if(!$result)
	  contactTech("Could not search directory");
	$stayput = pg_num_rows($result);
	pg_free_result($result);
	if(!$stayput and !$assigned) {
	  $move[] = $todo;
	  $assigned = true;
	}
      } else {
	$query = "SELECT 1 FROM directory WHERE username='".
	  pg_escape_string($todo['username'])."' AND room IS NOT NULL";
	$result = sdsQuery($query);
	if(!$result)
	  contactTech("Could not search directory");
	$hasroom = pg_num_rows($result);
	pg_free_result($result);
	if($hasroom and !$assigned) {
	  $clearRoom[] = $todo;
	  $assigned = true;
	}
      }
    }

# nothing fancy, so mark as a generic action
    if(!$assigned)
      $update[] = $todo;
  }

  if($defaultAction !== 'ignore') {
    $query = "SELECT username,lastname,firstname FROM sds_users LEFT JOIN directory USING (username) WHERE NOT immortal";
    if(preg_match('/\-under$/',$defaultAction))
      $query .= " AND type='U'";

    $result = sdsQuery($query);
    if(!$result)
      contactTech("Could not search users");

    while($record = pg_fetch_array($result)) {
      if($seenuser[$record['username']])
	continue;
      if(preg_match('/disable\-/',$defaultAction)) {
	$disable[] = $record;
      } else {
	$clearRoom[] = $record;
      }
    }
    pg_free_result($result);
  }

  return array("add" => $add, "enable" => $enable, "disable" => $disable,
	       "clearRoom" => $clearRoom, "move" => $move, "error" => $error,
	       "update" => $update, "ignore" => $ignore);
}

//====================================================================
//          CONFIRM OPERATIONS
//====================================================================  
function confirmOperations($table, $interpretation, $operations) {
  $encodedTable = base64_encode(serialize($table));
  $encodedInterpretation = base64_encode(serialize($interpretation));

  sdsIncludeHeader("Simmons RAC Batch Update - Confirm Operations");

  $add = $operations["add"];
  $enable = $operations["enable"];
  $disable = $operations["disable"];
  $clearRoom = $operations["clearRoom"];
  $move = $operations['move'];
  $error = $operations["error"];
  $update = $operations['update'];
  $ignore = $operations["ignore"];

  function showOperations($operations,$showwhy=false) {
    $fields = array("username","lastname","firstname","title","year","room",
		    "email","type","hidden");
    $boolfields = array("hidden");
    if($showwhy)
      $fields[] = 'why';

    echo "<table class='batchupdate'>\n";
    echo "  <tr>\n";
    foreach($fields as $field) {
      echo "    <th>",ucfirst($field),"</th>\n";
    }
    echo "  </tr>\n";

    foreach($operations as $op) {
      echo "  <tr>\n";
      foreach($fields as $field) {
	echo "    <td>";
	if(isset($op[$field])) {
	  if(in_array($field,$boolfields,true)) {
	    echo $op['field']?'Yes':'No';
	  } else {
	    echo htmlspecialchars($op[$field]);
	  }
	}
	echo "</td>\n";
      }
      echo "  </tr>\n";
    }
    echo "</table>";
  }

## SHOW ERROR MESSAGES, IF THERE ARE ANY

  if(count($error) > 0) {
    echo "<p class='error'>Sorry, the batch update has been aborted because\n";
    echo "  the following operations could not be completed.  Please fix\n";
    echo "  the spreadsheet and try again.</p>\n";
    showOperations($error,true);
    sdsIncludeFooter();
    exit();
  }

  // interpretation
  echo "<form action='batchupdate-process.php' method='post'>\n";
  echo sdsForm();
  echo "<input type='hidden' name='table' value='$encodedTable' />\n";
  echo "<input type='hidden' name='encodedInterpretation' value='$encodedInterpretation' />\n";
  echo "<input type='hidden' name='hasHeader' value='{$_REQUEST['hasHeader']}' />\n";
  echo "<input type='hidden' name='defaultAction' value='{$_REQUEST['defaultAction']}' />\n";
  echo "<input type='hidden' name='confirmedInterpretation' value='Confirm Below Interpretation' />\n";
  echo "<input type='submit' name='confirmedOperations' value='Confirm Below Operations' />\n";

  if(count($add) > 0) {
    echo "<h2>The following users will be <span style='color:blue'>added</span> to the system:</h2>\n";
    showOperations($add);
  }

  if(count($enable) > 0) {
    echo "<h2>The following <span style='color:blue'>disabled</span> users will be <span style='color:blue'>re-enabled</span>:</h2>\n";
    showOperations($enable);
  }

  if(count($disable) > 0) {
    echo "<h2>The following users will be <span style='color:red'>disabled</span>:</h2>\n";
    showOperations($disable);
  }

  if(count($clearRoom) > 0) {
    echo "<h2>The following users will <span style='color:blue'>become roomless</span>:</h2>\n";
    showOperations($clearRoom);
  }

  if(count($move) > 0) {
    echo "<b>The following users are just <span style='color:blue'>moving</span> to a new room:</h2>\n";
    showOperations($move);
  }

  if(count($update) > 0) {
    echo "<h2>The following users are just having information <span style='color:blue'>updated</span>:</h2>\n";
    showOperations($update);
  }

  if(count($ignore) > 0) {
    echo "<b>The following are being <span style='color:red'>ignored</span>:</h2>";
    showOperations($ignore,true);
  }
  echo "</form>";
    
  sdsIncludeFooter();
  exit();
}

//====================================================================
//          DO OPERATIONS
//====================================================================  
function doOperations($table, $interpretation, $operations) {
  $add = $operations["add"];
  $enable = $operations["enable"];
  $disable = $operations["disable"];
  $clearRoom = $operations["clearRoom"];
  $move = $operations['move'];
  $error = $operations["error"];
  $update = $operations['update'];
  $ignore = $operations["ignore"];

  $result = sdsQuery("BEGIN");
  if(!$result)
    contactTech("Could not start transaction");
  pg_free_result($result);

  $warnings = array();
  foreach($add as $e) {
    if(!addUser($e['username'],
		$e['email'] ? $e['email'] : ($e['username'] . "@mit.edu"),
		$e['lastname'],
		$e['firstname'],
		$e['title'],
		$e['type'] ? $e['type'] : 'U',
		$e['room'],
		$e['year'],
		false,
		$e['hidden'],
		$warnings)) {
      $result = sdsQuery("ROLLBACK");
      if(!$result)
	contactTech("Could not roll back transaction");
      pg_free_result($result);
      sdsIncludeHeader("Batch Update Error");
      echo "<h2 class='error'>Update Failed</h2>\n";
      sdsIncludeFooter();
      exit;
    }
  }

  foreach($enable as $e) {
    if(!enableUser($e['username']) ) {
      $result = sdsQuery("ROLLBACK");
      if(!$result)
	contactTech("Could not roll back transaction");
      pg_free_result($result);
      sdsIncludeHeader("Batch Update Error");
      echo "<h2 class='error'>Update Failed</h2>\n";
      sdsIncludeFooter();
      exit;
    }
  }

  foreach($disable as $e) {
    if(!disableUser($e['username'])) {
      $result = sdsQuery("ROLLBACK");
      if(!$result)
	contactTech("Could not roll back transaction");
      pg_free_result($result);
      sdsIncludeHeader("Batch Update Error");
      echo "<h2 class='error'>Update Failed</h2>\n";
      sdsIncludeFooter();
      exit;
    }
  }

  foreach(array_merge($clearRoom,$move) as $e) {
    if(!clearRoom($e['username'])) {
      $result = sdsQuery("ROLLBACK");
      if(!$result)
	contactTech("Could not roll back transaction");
      pg_free_result($result);
      sdsIncludeHeader("Batch Update Error");
      echo "<h2 class='error'>Update Failed</h2>\n";
      sdsIncludeFooter();
      exit;
    }
  }

  foreach(array_merge($move,$clearRoom,$enable,$update) as $e) {
    if(!modifyUser($e['username'], $e)) {
      $result = sdsQuery("ROLLBACK");
      if(!$result)
	contactTech("Could not roll back transaction");
      pg_free_result($result);
      sdsIncludeHeader("Batch Update Error");
      echo "<h2 class='error'>Update Failed</h2>\n";
      sdsIncludeFooter();
      exit;
    }
  }

  $result = sdsQuery("COMMIT");
  if(!$result) {
    $result = sdsQuery("ROLLBACK");
    if(!$result)
      contactTech("Could not roll back transaction");
    pg_free_result($result);
    sdsIncludeHeader("Batch Update Error");
    echo "<h2 class='error'>Update Failed</h2>\n";
    sdsIncludeFooter();
    exit;
  }

  sdsIncludeHeader("Batch Update - Completed");
  echo "<h2>The batch update is complete.</h2>\n";
  foreach($warnings as $complaint)
    echo "<p class='error'>",$complaint,"</p>\n";
  echo "<p>Go <a href='" . sdsLink("../home.php") . "'>home?</a></p>\n";

  sdsIncludeFooter();
  exit;
}

//====================================================================
//          MAIN LOGIC
//====================================================================  
if(!$_REQUEST['table']) {
  $table = loadTable();
} else {
  $table = unserialize(base64_decode($_REQUEST['table']));
}

if(!$_REQUEST['confirmedInterpretation']) {
  confirmInterpretation($table);
}

$interpretation = checkInterpretation($table);
  
$operations = prepareOperations($table, $interpretation);
if(!$_REQUEST['confirmedOperations']) {
  confirmOperations($table, $interpretation, $operations);
}

doOperations($table, $interpretation, $operations);
