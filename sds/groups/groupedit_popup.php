<?php
require_once("../sds.php");
require_once("../directory/directory.inc.php");
sdsRequireGroup("USERS");

// because we're just in a popup, we're going to forego the header and footer.
?>

<html>
  <head>
    <meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1" />
    <link rel="stylesheet" href="https://simmons.mit.edu/simmons.css" type="text/css" />

    <script type="text/javascript">
      <!-- script hiding
      function strltrim() {
	//Match spaces at beginning of text and replace with a null string
	return this.replace(/^\s+/,'');
      }

      function strrtrim() {
	//Match spaces at end of text and replace with a null string
	return this.replace(/\s+$/,'');
      }

      function strtrim() {
	//Match spaces at beginning and end of text and replace
	//with null strings
	return this.replace(/^\s+/,'').replace(/\s+$/,'');
      }

      String.prototype.ltrim = strltrim;
      String.prototype.rtrim = strrtrim;
      String.prototype.trim = strtrim;

      function appendJoin(field, glue, toAdd) {
	field.value = field.value.trim();
	if (0 < field.value.length) {
	  field.value += glue;
	}
	field.value += toAdd;
      }

<?php
$usersid = maybeStripslashes(@$_REQUEST['usersfield_id']);
if (strlen($usersid)) {
  echo "      function addUser(username) {\n";
  echo "        var glue = ', ';\n";
  echo "        appendJoin(window.opener.document.getElementById('$usersid'), glue, username);\n";
  echo "      }\n\n";
}

$groupsid = maybeStripslashes(@$_REQUEST['groupsfield_id']);
if (strlen($groupsid)) {
  echo "      function addGroup(groupname) {\n";
  echo "        var glue = ', ';\n";
  echo "        appendJoin(window.opener.document.getElementById('$groupsid'), glue, groupname);\n";
  echo "      }\n\n";
}
?>
      // end script hiding -->
    </script>
  </head>

  <body>
<?php
if ($groupsid) {
  echo "    <center><b>Click on groups below to include them.  Close this window when you're done.</b></center>\n";

  echo "    <table>\n";
  $query = "SELECT groupname,description FROM sds_groups_public ORDER BY groupname";
  $result = sdsQuery($query);
  if(!$result)
    contactTech("Could not search groups");
  while($row = pg_fetch_object($result)) {
    echo "      <tr>\n";
    echo "        <td align='right'><b><a href=\"javascript:addGroup('",
      htmlspecialchars($row->groupname,ENT_QUOTES),"')\">",
      htmlspecialchars($row->groupname),"</a></b>: </td>\n";
    echo "        <td>",htmlspecialchars($row->description),"</td>\n";
    echo "      </tr>\n";
  }
  pg_free_result($result);
  echo "    </table>\n";
}
if ($usersid) {
  $maintain = array('usersfield_id' => $usersid);
  if(isset($_REQUEST['search'])) {
    $searchResult = doDirectorySearch();
    if (!$searchResult) {
      echo "    <b>No matches were found.<br>\n";
      showDirectorySearchForm("groupedit_popup.php", $maintain);
    } else {
      echo "    <center><b>Click on users below to include them.  Close this window when you're done.</b></center>\n";
      $linkOverrideFront = "href=\"javascript:addUser('";
      $linkOverrideBack = "')\"";
      showDirectorySearchResults($searchResult, "", "", array(),
				 $linkOverrideFront, $linkOverrideBack);
    }
  } else {
    echo "    <center><b>Search for users to include using the form below.  Close this window when you're done.</b></center>\n";  
    showDirectorySearchForm("groupedit_popup.php", $maintain);
  }
}
?>
  </body>
</html>
