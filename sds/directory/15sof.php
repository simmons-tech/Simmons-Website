<?php
if(realpath($_SERVER['SCRIPT_FILENAME']) === realpath(__FILE__))
  exit;
?>
<div align="center">
<h2>15 Seconds Of Frame</h2>
<p style="margin-top:0;font-style:italic"><a href="<?php echo sdsLink("directory/") ?>">Simmons Hall Resident</a> of the Moment</p>

<?php 

$query = <<<ENDQUERY
SELECT username,lastname,firstname,title,year,type,quote,favorite_category,
       favorite_value,homepage,home_city,home_state,home_country
FROM public_active_directory
WHERE trim(trailing ' \n\t' from quote) != '' OR
      (favorite_category != '' AND favorite_value != '')
ENDQUERY;

$result = sdsQuery($query);

if(!$result) {
  contactTech("Can't read directory",false);
} else {

  $data = pg_fetch_object($result, rand(0, pg_num_rows($result)-1));
  pg_free_result($result);

  # generate type text
  $type = $data->type;
  # Most entries are undergrads, so only display other types
  if($type == "U") { $type = ""; }
  else {
    $typequery = "SELECT description FROM user_types WHERE type='"
      .pg_escape_string($type)."'";
    $typeresult = sdsQuery($typequery);
    if($typeresult and pg_num_rows($typeresult) == 1) {
      # should always be true
      list($type) = pg_fetch_array($typeresult);
    }
    if($typeresult) pg_free_result($typeresult);
    $type .= "<br />";
  }

  # generate year text
  $year = '';
  if ($data->year > 0) {
    $year = '<tr><td align="right">Year:</td> <td>'.((int)$data->year).
      "</td></tr>\n";
  }

  # generate homepage text
  $homepage = '';
  if (strlen($data->homepage)) {
    $homepage = '<tr><td align="right">URL:</td> <td><a href="' .
      ((strpos($data->homepage,"://") === false) ? "http://" : '') .
    # htmlspecialchars is not really right here, but doing it correctly is
    # WAY too much work, and this should work
      htmlspecialchars($data->homepage) . '">' .
      htmlspecialchars($data->homepage) . "</a></td></tr>\n";
  }

  # generate hometown text
  $hometown = '';
  if (strlen($data->home_city)) {
    $hometown = '<tr><td align="right">Hometown:</td> <td>' .
      htmlspecialchars($data->home_city . " " . $data->home_state . " " .
		       $data->home_country) . "</td></tr>\n";
  }

  # generate quote text
  $quote = '';
  if (strlen($data->quote)) {
    $quote = '<tr><td colspan="2" width="300"><blockquote><p class="quote">' .
      nl2br(htmlspecialchars($data->quote)) .
      "</p></blockquote></td></tr>\n";
  }

  $favorite = '';
  if (strlen($data->favorite_category)) {
    $favorite = '<tr><td align="right">Favorite '.
      htmlspecialchars($data->favorite_category).":</td> <td>".
      htmlspecialchars($data->favorite_value)."</td></tr>\n";
  }


  echo "<table border='1'>\n";
  echo "  <tr>\n";
  echo "    <td colspan='2'><p align='center'>\n";
  echo '      <b><a href="',
    sdsLink("directory/entry.php","username=".urlencode($data->username)),'">',
    htmlspecialchars($data->title." ".$data->firstname." ".$data->lastname),
    "</a></b> <br />\n";
  echo  $type;
  echo "    </p></td>\n";
  echo "  </tr>\n";

  echo "  <tr>\n";
  echo "    <td>\n";
  echo "      <table width='100%'>\n";
  echo $year;
  echo $hometown;
  echo $homepage;
  echo $favorite;
  echo "      </table>\n";
  echo "    </td>\n";
  echo "  </tr>\n";
  echo $quote;

  echo "</table>\n";
}

?>

</div>
