<?php
require_once("../sds.php");
sdsRequireGroup("HOUSE-COMM-LEADERSHIP");
sdsIncludeHeader("GovTracker","Simmons Government Online");


$query = "SELECT 1 FROM gov_agendas WHERE status='open' OR status='closed'";
$result = sdsQuery($query);
if(!$result)
  contactTech("Could not search agendas");

if(pg_num_rows($result)) {
  echo "<p class='error'>There is already an agenda for an upcoming meeting</p>\n";
  include('gt-footer.php');
  sdsIncludeFooter();
}
pg_free_result($result);

function validateDate($date) {
  $date_esc = pg_escape_string($date);
  $dateres = sdsQueryTest("SELECT CAST('$date_esc' AS date)");
  if($dateres) {
    pg_free_result($dateres);
    return $date;
  } else {
    // Try adding a year
    if(strpos($date_esc,'/') !== false) {
      $year_add = $date . '/' . date('Y');
    } elseif(strpos($date_esc,'-') !== false) {
      $year_add = $date . '-' . date('Y');
    } else {
      $year_add = $date . ' ' . date('Y');
    }
    $date_esc = pg_escape_string($year_add);
    $dateres = sdsQueryTest("SELECT CAST('$date_esc' AS date)");
    if($dateres) {
      pg_free_result($dateres);
      return $year_add;
    } else {
      return false;
    }
  }
}

echo "<h2>Create new Agenda</h2>\n";

# new submission?
$insert = array();
$errors = array();

$insert['usersub'] = $session->username;

$insert['meetingtitle'] = getStringArg('meetingtitle');
if($insert['meetingtitle'] === '')
  $errors[] = "Please provide a title";

$insert['meetingdate'] = getStringArg('meetingdate');
if($insert['meetingdate'] === '') {
  $errors[] = "Please provide a meeting date";
} else {
  $ans = validateDate($insert['meetingdate']);
  if($ans === false) {
    $errors[] = "Could not parse meeting date";
  } else {
    $insert['meetingdate'] = $ans;
  }
}

$insert['closingdate'] = getStringArg('closingdate');
if($insert['closingdate'] === '') {
  $errors[] = "Please provide a closing date";
} else {
  $ans = validateDate($insert['closingdate']);
  if($ans === false) {
    $errors[] = "Could not parse closing date";
  } else {
    $insert['closingdate'] = $ans;
  }
}

$insert['prefacetext'] = getStringArg('prefacetext');

if(!empty($_REQUEST['submit']) and count($errors) == 0) {
  $query="INSERT INTO gov_agendas " . sqlArrayInsert($insert);
  $result = sdsQuery($query);
  if(!$result)
    contactTech("Could not create agenda");
  pg_free_result($result);

  echo "<p class='success'>Your new agenda submission has been received.</p>\n";

} else {
  if(!empty($_REQUEST['submit'])) {
    foreach($errors as $error) {
      echo "<p class='error'>",$error,"</p>\n";
    }
  }

?>

<form action="newagenda.php" method="post">
<?php echo sdsForm() ?>

<table class="proposaldetail">
  <tr>
    <td>Primary Author:</td>
    <td><?php echo sdsGetFullname($session->username) ?></td>
  </tr>

  <tr>
    <td>Meeting Title:</td>
    <td><input type="text" name="meetingtitle" size="45" value="<?php echo htmlspecialchars($insert['meetingtitle']) ?>" /><br />
      <span class="inputdetail">Keep the title concise and to the point.  This
        will appear on the summary agenda sent out to the House before the
        meeting.</span></td>
  </tr>

  <tr>
    <td>Meeting Date:</td>
    <td><input name="meetingdate" type="text" size="20" value="<?php echo htmlspecialchars($insert['meetingdate']) ?>" /></td>
  </tr>

  <tr>
    <td>Closing Date for Agenda:</td>
    <td><input name="closingdate" type="text" size="20" value="<?php echo htmlspecialchars($insert['closingdate']) ?>" /></td>
  </tr>

  <tr>
    <td>Preface Text:</td>
    <td><textarea name="prefacetext" rows="2" cols="45"><?php echo htmlspecialchars($insert['prefacetext']) ?></textarea><br />
      <span class="inputdetail">Do not use HTML tags.</span></td>
  </tr>
</table>

<p>This agenda will be publicly viewable on the Simmons DB and during House
  Committee meetings.  You cannot undo this action after clicking
  "Create Agenda".</p>
<input type="submit" name="submit" value="Create Agenda" />
<input type="reset" value="Clear Form" />
</form>

<?php
}

include("gt-footer.php");
sdsIncludeFooter();
