<?php
require_once("../sds.php");

sdsRequireGroup("USERS");

$sdsFields = sdsForm();
if(@sdsGetReminder("sudo") !== null) {
  sdsIncludeHeader("Your <i>Impersonated</i> Profile");
  print "<h2>Any changes you make will affect the user you are impersonating, and not your actual profile.</h2>";
} else {
  sdsIncludeHeader("Your Profile");
}

if(isset($_POST["submit"])) {

  $fields = array('homepage', 'phone', 'cellphone',
                  'home_city', 'home_state', 'home_country',
                  'quote', 'favorite_category', 'favorite_value');

  foreach ($fields as $field)
    $_POST[$field] = sdsSanitizeHTML(maybeStripslashes($_POST[$field]));

  $update =  sqlArrayUpdate($_POST, $fields) ;
  $sr = ($_POST['rdisp']==="show") ? 'true' : 'false';
  $query = "UPDATE directory SET showreminders=$sr,$update WHERE username='".
    pg_escape_string($session->username)."'";

  $result = sdsQuery($query);
  if($result) {
    print "<h2>Update received!</h2> <br />\n";
    pg_free_result($result);
  } else {
    contactTech("Directory update failed");
  }

}

$username = pg_escape_string($session->username);
$query = "SELECT * FROM active_directory WHERE username='$username'";
$result = sdsQuery($query);

if(!$result)
     contactTech("Can't search directory");

if (pg_num_rows($result) == 1) {
  $data = pg_fetch_object($result);

  $homepage = htmlspecialchars($data->homepage);
  $cellphone = htmlspecialchars($data->cellphone);
  $home_city = htmlspecialchars($data->home_city);
  $home_state = htmlspecialchars($data->home_state);
  $home_country = htmlspecialchars($data->home_country);
  $favorite_category = htmlspecialchars($data->favorite_category);
  $favorite_value = htmlspecialchars($data->favorite_value);


  ## get phone info
  # $phone = $data->phone;
  $pq = "SELECT phone1,phone2 FROM rooms WHERE room='".
    pg_escape_string($data->room)."'";
  $pr = sdsQuery($pq);
  if(!$pr)
    contactTech("Can't check rooms");
  $phone = '';
  if (pg_num_rows($pr) == 1) {
    $pd = pg_fetch_object($pr);
    if (strlen($pd->phone2)) {
      $phone="<select name='phone'>\n".
	"<option " . ($data->phone == $pd->phone1 ? 'selected="selected"':"")
	. ">".htmlspecialchars($pd->phone1)."</option>\n".
	"<option " . ($data->phone == $pd->phone2 ? 'selected="selected"':"")
	. ">".htmlspecialchars($pd->phone2)."</option>\n".
	"</select>";
    } else {
      $phone = htmlspecialchars($data->phone) .
	"\n<input type='hidden' name='phone' value='".
	htmlspecialchars($data->phone,ENT_QUOTES)."' />\n";
    }
  }
  pg_free_result($pr);

 ## reminder display info
  $reminddisp = "<select name='rdisp'>\n".
    "<option ". ($data->showreminders == 't' ? 'selected="selected"' : "")
    . ">show</option>\n".
    "<option ". ($data->showreminders == 't' ? "" : 'selected="selected"')
    . ">hide</option>\n</select>";


##
## edit form
##
?>
<h3>Public Directory Entry</h3>

<form action="update.php" method="post">
<?php echo $sdsFields ?>

<table>
  <tr>
    <td align="right">Title:</td>
    <td><?php echo htmlspecialchars($data->title) ?></td>
  </tr>

  <tr>
    <td align="right">First Name:</td>
    <td><?php echo htmlspecialchars($data->firstname) ?></td>
  </tr>

  <tr>
    <td align="right">Last Name:</td>
    <td><?php echo htmlspecialchars($data->lastname) ?></td>
  </tr>

  <tr>
    <td align="right">Room:</td>
    <td><?php echo htmlspecialchars($data->room) ?></td>
  </tr>

  <tr>
     <td align="right">Year:</td>
     <td><?php echo (int)$data->year ?></td>
  </tr>

  <tr>
    <td align="right">Phone:</td>
    <td><?php echo $phone ?></td>
  </tr>

  <tr>
    <td align="right">Homepage:</td>
    <td><input name="homepage" type="text" size="40"
               value="<?php echo $homepage ?>" /></td>
  </tr>

  <tr>
    <td align="right">Cellphone:</td>
    <td><input name="cellphone" type="text" size="13"
               value="<?php echo $cellphone ?>" /></td>
  </tr>

  <tr>
    <td align="right">Home City:</td>
    <td><input name="home_city" type="text" size="40"
               value="<?php echo $home_city ?>" /></td>
  </tr>

  <tr>
    <td align="right">State:</td>
    <td><input name="home_state" type="text" size="40"
               value="<?php echo $home_state ?>" /></td>
  </tr>

  <tr>
    <td align="right">Country:</td>
    <td><input name="home_country" type="text" size="40"
               value="<?php echo $home_country ?>" /></td>
  </tr>

  <tr>
    <td align="right">Quote:</td>
    <td>
      <textarea name="quote" type="text" rows="4" cols="38"><?php echo htmlspecialchars($data->quote) ?></textarea>
    </td>
  </tr>

  <tr>
    <td align="right">My Favorite</td>
    <td><input name="favorite_category" type="text" size="16"
               value="<?php echo $favorite_category ?>" />
        is <input name="favorite_value" type="text" size="20"
                  value="<?php echo $favorite_value ?>" /> .
    </td>
  </tr>

  <tr>
    <td align="right">e.g.:</td>
    <td><u>Color</u> is <u>Blue</u></td>
  </tr>

  <tr>
    <td align="right">Reminders:</td>
    <td>When I have reminders, I want to <?php echo $reminddisp ?> the yellow
        reminders box.</td>
  </tr>

  <tr>
    <td></td>
    <td><br />
      <input type="submit" name="submit" value="Save it away, boss!" />
      <input type="reset" value="Undo changes" />
    </td>
  </tr>
</table>

</form>
<h3>Automatic Reminders</h3>

<?php
  if(count(sdsGetReminders()) == 0) {
    echo "<b><i>You do not currently have any reminders.</i></b>";
  } else {
    echo "<ul>";
    foreach (sdsGetReminders() as $ek => $em) {
      echo "<li>",$em,"</li>\n";
    }
    echo "</ul>";
  }
} else {
#unable to find student


##
## Student not found error
##

?>
<h2>No directory entry found</h2>
<p>
  You were not found in the directory.  Please contact
  <a href="mailto:simmons-tech@mit.edu">simmons-tech@mit.edu</a>.
</p>

<?php
}
pg_free_result($result);

sdsIncludeFooter();
