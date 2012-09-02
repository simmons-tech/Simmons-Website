<?php
require_once("../sds.php");
if (!$_REQUEST["username"]) {
  header("Location: ".SDS_BASE_URL.sdsLink("directory/index.php"));
  exit;
  # no username, instead of showing a weird error go to the query page
}

require_once("directory.inc.php");

sdsRequireGroup("EVERYONE");
sdsIncludeHeader("Simmons Hall Directory");

if(!empty($session->groups["DESK"]) or !empty($session->groups["RAC"]))
  $directory = "active_directory";
else
  $directory = "public_active_directory";

// only selects active users
$directory_query = "SELECT username,room," .
         " email,lastname,firstname,title,phone,year,type," . 
         " quote,favorite_category,favorite_value,cellphone, " .
         " homepage,home_city,home_state,home_country " .
         " FROM $directory" .
         " WHERE username='" .pg_escape_string(getStringArg("username")) . "'";
$directory_result = sdsQuery($directory_query);
if(!$directory_result) {
  sdsIncludeHeader("Error");
  contactTech("Can't search directory");
}
$directory_data = pg_fetch_object($directory_result);
pg_free_result($directory_result);

if($directory_data) {

# generate email address
  $email = $directory_data->email;

# generate type text
  $type = $directory_data->type;
# Most entries are undergrads, so only display other types
  if($type == "U") { $type = ""; }
  else {
    $typequery = "SELECT description FROM user_types WHERE type='"
      .pg_escape_string($type)."'";
    $typeresult = sdsQuery($typequery);
    if($typeresult) {
      if(pg_num_rows($typeresult) == 1) {
        # should always be true
	list($type) = pg_fetch_array($typeresult);
      }
      $type .= "<br />";
      pg_free_result($typeresult);
    }
  }

# generate room text
  $room = '';
  $grt = '';
  if (strlen($directory_data->room)) {
    $room = '<tr><td>Room:</td> <td width="75%">'.
      htmlspecialchars($directory_data->room)."</td></tr>\n";

    $rooms_query = "SELECT * FROM rooms WHERE room='" .
      pg_escape_string($directory_data->room) . "'";
    $rooms_result = sdsQuery($rooms_query);
    if(!$rooms_result)
      contactTech("Can't search rooms");
    $rooms_data = pg_fetch_object($rooms_result);
    pg_free_result($rooms_result);

    # generate grt text
    if (strlen($rooms_data->grt)) {
      $grtlink = sdsLink("list.php", "grt=".urlencode($rooms_data->grt));
      $grt = "<tr><td>GRT Section:</td> <td><a href='$grtlink'>".
	htmlspecialchars($rooms_data->grt)."</a></td></tr>\n";
    }
  }

# generate phone text
  $phone = '';
  if (strlen($directory_data->phone)) {
    $phone = "<tr><td>Phone:</td> <td>".
      htmlspecialchars($directory_data->phone)."</td></tr>\n";
  }

# generate year text
  $year = '';
  if ($directory_data->year > 0) {
    $year = "<tr><td>Year:</td> <td>".((int)$directory_data->year).
      "</td></tr>\n";
  }

# generate homepage text
  $homepage = '';
  if (strlen($directory_data->homepage)) {
    $homepage = '<tr><td>URL:</td> <td><a href="'
      . (strpos($directory_data->homepage,"://") === false ? 'http://' : '') .
      htmlspecialchars($directory_data->homepage) . '">' .
      htmlspecialchars($directory_data->homepage)."</a></td></tr>\n";
  }

# generate cellphone text
  $cellphone = '';
  if (strlen($directory_data->cellphone)) {
    $cellphone = "<tr><td>Cell:</td> <td>".
      htmlspecialchars($directory_data->cellphone)."</td></tr>\n";
  }

# generate hometown text
  $hometown = '';
  if (strlen($directory_data->home_city)) {
    $hometown = "<tr><td>Hometown:</td> <td>".
      htmlspecialchars($directory_data->home_city." ".
		       $directory_data->home_state." ".
		       $directory_data->home_country)."</td></tr>\n";
  }

# generate quote text
  $quote = '';
  if (strlen($directory_data->quote)) {
    $quote = '<tr><td colspan="2"><blockquote><p class="quote">'
      . nl2br(htmlspecialchars($directory_data->quote))
      . "</p></blockquote></td></tr>\n";
  }

  $favorite = '';
  if (strlen($directory_data->favorite_category)) {
    $favorite = '"My favorite '.
      htmlspecialchars($directory_data->favorite_category)." is ".
      htmlspecialchars($directory_data->favorite_value).".\"<br />\n";
  }


  echo "<table border='1' width='480px'>\n";
  echo "  <tr>\n";
  echo "    <td colspan='2'><p align='center'>\n";
  echo "      <em>",htmlspecialchars($directory_data->title." ".
				     $directory_data->firstname." ".
				     $directory_data->lastname),
    "</em><br />\n";
  echo $type;
  echo '      <a href="mailto:',htmlspecialchars($email),'">',
    htmlspecialchars($email),"</a> <br />\n";
  echo "      <br />\n";
  echo $favorite;
  echo "    </p></td>\n";
  echo "  </tr>\n";

  echo $room;
  echo $grt;
  echo $phone;
  echo $year;
  echo $homepage;
  echo $cellphone;
  echo $hometown;
  echo $quote;

  echo "</table>\n";

  if(!empty($session->groups["RAC"]) or
     !empty($session->groups["ADMINISTRATORS"])) {
    $modifyLink = sdsLink("../rac/modify.php","username=".
			  urlencode(maybeStripSlashes($_REQUEST["username"])));
    $removeLink = sdsLink("../rac/remove.php","username=".
			  urlencode(maybeStripSlashes($_REQUEST["username"])));
    $username_disp =
      htmlspecialchars(maybeStripSlashes($_REQUEST['username']));

?>

<br /><br />

<h2>RAC Commands</h2>
<ul>
  <li><a href="<?php echo $modifyLink ?>">Modify "<?php echo $username_disp ?>" record</a></li>
  <li><a href="<?php echo $removeLink ?>">Remove "<?php echo $username_disp ?>" record</a></li>
</ul>

<?php

  }
} else {
  echo "<h2 class='error'>Entry not found</h2>\n";
}

echo "<br /><hr /><br />\n";

showDirectorySearchForm();

sdsIncludeFooter();
