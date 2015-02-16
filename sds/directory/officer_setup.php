<?php
require_once('../sds.php');
sdsRequireGroup('ADMINISTRATORS');

if(isset($_REQUEST['order'])) {
  $neworder = explode(';',trim(maybeStripslashes($_REQUEST['order']),':'));

  $transres = sdsQuery("BEGIN");
  if(!$transres)
    contactTech("Could not start transaction");
  pg_free_result($transres);

  $updateorder = array();
  foreach($neworder as $officerid) {
    if(strpos($officerid,':') === false)
      continue;
    list($type,$number) = explode(':',$officerid,2);
    $number = (int) $number;
    $position = trim(maybeStripslashes($_REQUEST['position'][$officerid]));
    $position_text = trim(maybeStripslashes($_REQUEST['position_text'][$officerid]));
    $username = trim(maybeStripslashes($_REQUEST['username'][$officerid]));

    if($position === '' or $username === '')
      continue;

    $createnew = true;
    $position_esc = pg_escape_string($position);
    $position_text_esc = pg_escape_string($position_text);
    $username_esc = pg_escape_string($username);
    if($type === 'officer') {
      $query = <<<ENDQUERY
SELECT 1 FROM officers
WHERE officerid=$number AND username='$username_esc' AND
      position='$position_esc' AND position_text='$position_text_esc' AND removed IS NULL
ENDQUERY;
      $result = sdsQuery($query);
      if(!$result) {
	contactTech("Could not search officers",false);
	if(!sdsQuery("ROLLBACK"))
	  contactTech("Could not rollback");
	sdsIncludeFooter();
	exit;
      }
      if(pg_num_rows($result) == 1) {
	$updateorder[] = $number;
	$createnew = false;
      }
      pg_free_result($result);
    }
    if($createnew) {
      if($username !== 'NOBODY') {
	$query = "SELECT 1 FROM sds_users WHERE username='$username_esc'";
	$result = sdsQuery($query);
	if(!$result) {
	  contactTech("Could not search users",false);
	  if(!sdsQuery("ROLLBACK"))
	    contactTech("Could not rollback");
	  sdsIncludeFooter();
	  exit;
	}
	if(pg_num_rows($result) != 1) {
	  pg_free_result($result);
	  sdsIncludeHeader("Officer Managemant");
	  echo "<h2 class='error'>Invalid username</h2>\n";
	  echo "<p>The user ",htmlspecialchars($username),
	    " is not an active user.</p>\n";
	  if(!sdsQuery("ROLLBACK"))
	    contactTech("Could not rollback");
	  sdsIncludeFooter();
	  exit;
	}
	pg_free_result($result);
      }
      $query = <<<ENDQUERY
INSERT INTO officers (username,       position, position_text)
VALUES               ('$username_esc','$position_esc', '$position_text_esc')
RETURNING officerid
ENDQUERY;
      $result = sdsQuery($query);
      if(!$result) {
	contactTech("Could not create officer",false);
	if(!sdsQuery("ROLLBACK"))
	  contactTech("Could not rollback");
	sdsIncludeFooter();
	exit;
      }
      list($number) = pg_fetch_array($result);
      pg_free_result($result);
      $updateorder[] = $number;
    }
  }

  // fix weird entries
  $query = "UPDATE officers SET ordering=0 WHERE removed IS NULL AND ordering IS NULL;";
  // mark current entries by ordering = null
  foreach($updateorder as $officerid) {
    $query .= "UPDATE officers SET ordering=null WHERE officerid=$officerid;";
  }
  // remove unmarked entries
  $query .= "UPDATE officers SET removed=now(), ordering=null WHERE removed IS NULL AND ordering IS NOT NULL;";
  // set the new order
  $ordering = 0;
  foreach($updateorder as $officerid) {
    $query .= "UPDATE officers SET ordering=$ordering WHERE officerid=$officerid;";
    $ordering++;
  }
  $result = sdsQuery($query);
  if(!$result) {
    contactTech("Could not update list",false);
    if(!sdsQuery("ROLLBACK"))
      contactTech("Could not rollback");
    sdsIncludeFooter();
    exit;
  }

  $transres = sdsQuery("COMMIT");
  if(!$transres) {
    contactTech("Could not commit",false);
    if(!sdsQuery("ROLLBACK"))
      contactTech("Could not rollback");
    sdsIncludeFooter();
    exit;
  }
  pg_free_result($transres);

  header("Location: " . SDS_BASE_URL .
	 sdsLink("directory/officers.php"));
  exit;
}

sdsIncludeHeader("Student Officer Management","",
		 "<script type='text/javascript' src='../sds/dragorder.js'></script>",
		 "onload='officerdragdrop=dragdropSetup(\"officerlist\",\"orderReturn\")'");

$query = <<<ENDQUERY
SELECT officerid,position,position_text,username FROM officers
WHERE removed IS NULL ORDER BY ordering,officerid
ENDQUERY;

$result = sdsQuery($query);
if(!$result)
  contactTech("Could not find officers");

?>

<h2 id='javascriptWarning' class='error'>This page requires JavaScript.</h2>

<p>Drag officers into the desired order. To delete an entry, leave either of
  the fields blank. To create an entry explicitly indicating the absence of an
  officer, enter the username 'NOBODY'.</p>

<form action="officer_setup.php" method="post">
  <div id='officerlist' class="dragregion">
    <div class='posIndicator'></div>
<?php
while($data = pg_fetch_object($result)) {
  echo "    <div id='officer:",$data->officerid,"' class='dragitem'>\n";
  echo "      Title:<input type='text' name='position_text[officer:",
    $data->officerid,
    "]' value='",htmlspecialchars($data->position_text,ENT_QUOTES),"' />\n";
  echo "      Position Identifier:<input type='text' name='position[officer:",
    $data->officerid,
    "]' value='",htmlspecialchars($data->position,ENT_QUOTES),"' />\n";
  echo "      Username:<input type='text' name='username[officer:",
    $data->officerid,
    "]' value='",htmlspecialchars($data->username,ENT_QUOTES),"' />\n";
  echo "    </div>\n";
  echo "    <div class='posIndicator'></div>\n";
}
?>
  </div>

  <input type="button" value="New Officer"
         onclick="addItem(officerdragdrop,'officertemplate')" />

  <input type="hidden" id="orderReturn" name="order" value="" />
  <div style="height:30px"></div>
  <input type="submit" value="Update" />
</form>

<div id='officertemplate' class='dragtemplate'>
  Title:<input type='text' name='position_text[]' />
  Position Identifier:<input type='text' name='position[]' />
  Username:<input type='text' name='username[]' />
</div>

<?php
sdsIncludeFooter();
