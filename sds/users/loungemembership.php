<?php
require_once("../sds.php");
sdsRequireGroup("USERS");

$username_esc = $session->username;

# get lounge membership
$query = "SELECT lounge FROM directory WHERE username='$username_esc'";
$result = sdsQuery($query);
if(!$result or pg_num_rows($result) != 1)
  contactTech("Could not search dirctory");
list($lounge) = pg_fetch_array($result);
pg_free_result($result);

if(strlen($lounge) == 0) {
  sdsErrorPage("No Lounge Membership",
	       "You are not in a lounge, so you can't get your lounge's membership");
}

$lounge_esc = pg_escape_string($lounge);

sdsIncludeHeader("Lounge Membership");

$query = "SELECT description,url,contact,contact2 FROM lounges WHERE lounge='$lounge_esc'";
$result = sdsQuery($query);
if(!$result or pg_num_rows($result) != 1)
  contactTech("Could not find lounge");
$loungedata = pg_fetch_object($result);
pg_free_result($result);

echo "<h2 style='text-align:center'>";
if(isset($loungedata->url))
  echo "<a href='",htmlspecialchars($loungedata->url,ENT_QUOTES),"'>";
echo $loungedata->description;
if(isset($loungedata->url))
  echo "</a>";
echo "</h2>\n";

echo "<p style='text-align:center'>Representative",
  (isset($loungedata->contact2)?'s':''),": <a href='",
  sdsLink('../directory/entry.php',
	  'username='.htmlspecialchars($loungedata->contact)),"'>",
  sdsGetFullName($loungedata->contact),"</a>";
if(isset($loungedata->contact2))
  echo " and <a href='",
    sdsLink('../directory/entry.php',
	    'username='.htmlspecialchars($loungedata->contact2)),"'>",
    sdsGetFullName($loungedata->contact2),"</a>";
echo "</p>\n";

# get other lounge members
$query = "SELECT firstname,lastname,title,username FROM public_active_directory WHERE lounge='$lounge_esc' ORDER BY lastname";
$result = sdsQuery($query);
if(!$result)
  contactTech("Could not get lounge membership");

echo "<table style='margin-left:auto;margin-right:auto'>\n";

while($data = pg_fetch_object($result)) {
  echo "  <tr>\n";
  echo "    <td>",
    htmlspecialchars($data->title." ".$data->firstname." ".$data->lastname),
    "</td>\n";
  echo "    <td>(<a href='",
    sdsLink('../directory/entry.php',
	    "username=".htmlspecialchars($data->username)),"'>",
    htmlspecialchars($data->username),"</a>)</td>\n";
  echo "  </tr>\n";
}

echo "</table>\n";

sdsIncludeFooter();
