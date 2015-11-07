<?php
if(realpath($_SERVER['SCRIPT_FILENAME']) === realpath(__FILE__))
  exit;
require_once(dirname(__FILE__) . "/../sds.php");

function showDirectorySearchForm($formTarget="", $maintain=array()) {
  global $session;

  if (strlen($formTarget) == 0) {
    $formTarget = SDS_BASE_URL . "directory/list.php";
  }

  echo "<form action='$formTarget' method='post' name='dirform'>\n";
  echo sdsForm() . "\n";
  echo hiddenInputs($maintain) . "\n";
?>
<!--p>Try the New Directory at <a target="_blank" href="https://simmons.mit.edu/directory">simmons.mit.edu/directory</a>!</p-->
<table>
  
  <tr>
    <td align="right">Firstname:</td>
    <td><input name="firstname" type="text" size="12" autofocus /></td>
  </tr>

  <tr>
    <td align="right">Lastname:</td>
    <td><input name="lastname" type="text" size="12" /></td>
  </tr>

  <tr>
    <td align="right">Title:</td>
    <td><input name="title" type="text" size="12" /></td>
  </tr>

  <tr>
    <td align="right">Username:</td>
    <td><input name="username" type="text" size="12" /></td>
  </tr>

  <tr>
    <td align="right">Room:</td>
    <td><input name="room" type="text" size="12" /></td>
  </tr>

  <tr>
    <td align="right">Year:</td>
    <td>
      <select name="cyear" size="1">
        <option selected="selected">[Any]</option>
<?php
  if(!empty($session->groups["DESK"]) or !empty($session->groups["RAC"]))
    $directory = "active_directory";
  else
    $directory = "public_active_directory";

  $query = "SELECT DISTINCT year FROM $directory WHERE year != 0 AND year IS NOT NULL ORDER BY year";
  $result = sdsQuery($query);
  if(!$result) {
    echo "</select></td></tr></table></form>\n";
    contactTech("Can't get years");
  }
  while($data = pg_fetch_array($result)) {
    echo "        <option>",htmlspecialchars($data['year']),"</option>\n";
  }
  pg_free_result($result);
  # this will match 0 year or null year
?>
        <option>No year</option>
      </select>
    </td>
  </tr>

  <tr>
    <td align="right">Lounge:</td>
    <td>
      <select name="lounge" size="1">
        <option selected="selected">[Any]</option>
<?php
  $query = "SELECT lounge,description FROM active_lounges ORDER BY lounge";
  $result = sdsQuery($query);
  if(!$result) {
    echo "</select></td></tr></table></form>\n";
    contactTech("Can't get lounges");
  }
  while($data = pg_fetch_array($result)) {
    echo '        <option value="',htmlspecialchars($data['lounge']),'">',
      htmlspecialchars($data['lounge']),": ",
      htmlspecialchars($data['description']),"</option>\n";
  }
  pg_free_result($result);
?>
      </select>
    </td>
  </tr>
  <tr>
    <td align="right">GRT:</td>
    <td>
      <select name="grt" size="1">
        <option selected="selected">[Any]</option>
<?php
  $query = "SELECT DISTINCT grt FROM rooms WHERE LENGTH(TRIM(grt))>0 ORDER BY grt";
  $result = sdsQuery($query);
  if(!$result) {
    echo "</select></td></tr></table></form>\n";
    contactTech("Can't get GRTs");
  }
  while($data = pg_fetch_array($result)) {
    echo "        <option>",htmlspecialchars($data['grt']),"</option>";
  }
  pg_free_result($result);
?>
      </select>
    </td>
  </tr>

  <tr>
    <td></td>
    <td>
      <input type="submit" name="search" value="Search">
    </td>
  </tr>
</table>
</form>
<p>Enter data in any or all of the above fields.  '%' matches anything -
   e.g. username 'dram%' matches 'dramage'.</p>
<?php
}


###################################
## performs a search entered into a showDirectorySearchForm()
## returns a postgres result if entries found, otherwise returns false
function doDirectorySearch() {
  global $session;
  if(!empty($session->groups["DESK"]) or !empty($session->groups["RAC"]))
    $directory = "active_directory";
  else
    $directory = "public_active_directory";

  $rooms = "";
  $clauses  = array();
  if(!empty($_REQUEST["username"])) {
    $clauses["username"]  = "username ILIKE '".
      sdsSanitizeString($_REQUEST["username"])."'";
  }

  if(!empty($_REQUEST["title"])) {
    $clauses["title"]  = "title ILIKE '".
      sdsSanitizeString($_REQUEST["title"])."'";
  }

  if(!empty($_REQUEST["firstname"])) {
    $clauses["firstname"] = "firstname ILIKE '".
      sdsSanitizeString($_REQUEST["firstname"])."%'";
  }

  if(!empty($_REQUEST["lastname"])) {
    $clauses["lastname"] = "lastname ILIKE '%".
      sdsSanitizeString($_REQUEST["lastname"])."%'";
  }

  if(!empty($_REQUEST["room"])) {
    $clauses["room"] = "room ILIKE '" .
      sdsSanitizeString($_REQUEST["room"]) . "'";
  }

  if(!empty($_REQUEST["lounge"]) and ($_REQUEST["lounge"] !== "[Any]")) {
    $clauses["lounge"] =
      "lounge = '" . sdsSanitizeString($_REQUEST["lounge"]) . "'";
  }

  if(!empty($_REQUEST["grt"]) and ($_REQUEST["grt"] !== "[Any]")) {
    $rooms = "JOIN rooms USING (room)";
    $clauses["grt"] = "rooms.grt='".sdsSanitizeString($_REQUEST["grt"])."'";
  }

  if(!empty($_REQUEST["cyear"]) and ($_REQUEST["cyear"] !== "[Any]")) {
    if ($_REQUEST["cyear"] != "No year"){
      $clauses["cyear"] = "year='".sdsSanitizeString($_REQUEST["cyear"])."'";
    } else {
      $clauses["cyear"] =
	"(year IS NULL)";
    }
  }

  # make sure we have qualifiers
  if(count($clauses) > 0)
    // selects only active users
    $qualifier = "WHERE " . implode(" AND ", $clauses);
  else
    return false;

  $query = "SELECT username,lastname,firstname,title,room,year FROM $directory $rooms $qualifier ORDER BY lastname";
  $result = sdsQuery($query);
  if(!$result) {
    contactTech("Can't search directory",false);
    return null;
  }
  if (pg_num_rows($result) == 0) {
    pg_free_result($result);
    return false;
  } else {
    return $result;
  }
}


function showDirectorySearchResults($searchResult,$userEntryPage="",
				    $usernameField="username",
				    $maintain=array(),$linkOverrideFront="",
				    $linkOverrideBack="") {
  if (strlen($userEntryPage) == 0) {
    $userEntryPage=SDS_BASE_URL . "directory/entry.php";
  }

  $retCount = pg_num_rows($searchResult);
?>
<b><?php echo $retCount ?> users matched your query.</b><br/>

<table border="1" style="empty-cells: show">
  <tr bgcolor="#FFBBBB">
    <th>Last name</th>
    <th>First name</th>
    <th>Title</th>
    <th>Username</th>
    <th>Room</th>
    <th>Year</th>
  </tr>
<?php

  $oddrow = true;
  while($data = pg_fetch_object($searchResult)) {
    $oddrow = !$oddrow;
    if ($oddrow)
      $color = "#FFEEEE";
    else
      $color = "#FFDDDD";
 
    if (strlen($linkOverrideFront) > 0 or strlen($linkOverrideBack) > 0) {
      $link = $linkOverrideFront . urlencode($data->username) .
	$linkOverrideBack;
    } else { 
      $options = $maintain;
      if (strlen($usernameField)) {
	$options[$usernameField] = $data->username;
      }
      $link = 'href="' . sdsLink($userEntryPage,urlOptions($options)) .'"';
    }

    echo "  <tr bgcolor='", $color,"'>\n";
    echo "    <td><a tabindex='1' ",$link,">",htmlspecialchars($data->lastname),
      "</a></td>\n";
    echo "    <td><a ",$link,">",htmlspecialchars($data->firstname),
      "</a></td>\n";
    echo "    <td><a ",$link,">",htmlspecialchars($data->title),"</a></td>\n";
    echo "    <td><a ",$link,">",htmlspecialchars($data->username),
      "</a></td>\n";
    echo "    <td><a ",$link,">",htmlspecialchars($data->room),"</a></td>\n";
    echo "    <td><a ",$link,">",htmlspecialchars($data->year),"</a></td>\n";
    echo "  </tr>\n";
  }

  echo "</table>";
}
