<?php
require_once("../sds.php");
sdsRequireGroup("DESK");
require_once('../sds/ordering.inc.php');

sdsIncludeHeader("Full House Directory");

$sortby = getSortby($_REQUEST['sortby'],0,8,'directory_sortby');
$orderbyarray = array(# lastname
		      "lastname ASC,firstname ASC",
		      "lastname DESC,firstname DESC",
		      # firstname
		      "firstname ASC,lastname ASC",
		      "firstname DESC,lastname DESC",
		      # title
		      "title ASC,lastname ASC,firstname ASC",
		      "title DESC,lastname DESC,firstname DESC",
		      # username
		      "username ASC",
		      "username DESC",
		      # room
		      "CASE WHEN room SIMILAR TO '%[[:digit:]]%' THEN to_number(room,'9999') ELSE 9999 END ASC,room ASC,lastname ASC,firstname ASC",
		      "CASE WHEN room SIMILAR TO '%[[:digit:]]%' THEN to_number(room,'9999') ELSE 9999 END DESC,room DESC,lastname DESC,firstname DESC",
		      # phone
		      "phone ASC,lastname ASC,firstname ASC",
		      "phone DESC,lastname DESC,firstname DESC",
		      # type
		      "type ASC,lastname ASC,firstname ASC",
		      "type DESC,lastname DESC,firstname DESC",
		      # year
		      "year ASC,lastname ASC,firstname ASC",
		      "year DESC,lastname DESC,firstname DESC"
		      );

echo "<table class='directory'>\n";
echo "  <tr class='oddrow'>\n";
makeSortTH("Last Name",0,$sortby);
makeSortTH("First Name",1,$sortby);
makeSortTH("Title",2,$sortby);
makeSortTH("Username",3,$sortby);
makeSortTH("Room",4,$sortby);
makeSortTH("Phone",5,$sortby);
makeSortTH("Type",6,$sortby);
makeSortTH("Year",7,$sortby);
echo "  </tr>\n";

$result = sdsQuery("SELECT username,lastname,firstname,title,room,phone,type,year FROM active_directory ORDER BY ".$orderbyarray[$sortby]);

if($result) {
  $oddrow = true;
  while($data = pg_fetch_array($result)) {
    $oddrow = !$oddrow;
    $class = $oddrow ? "oddrow" : "evenrow";

    echo "  <tr class='$class'>\n";
    echo "    <td>",htmlspecialchars($data['lastname']),"</td>\n";
    echo "    <td>",htmlspecialchars($data['firstname']),"</td>\n";
    echo "    <td>",htmlspecialchars($data['title']),"</td>\n";
    echo "    <td>",htmlspecialchars($data['username']),"</td>\n";
    echo "    <td>",htmlspecialchars($data['room']),"</td>\n";
    echo "    <td>",htmlspecialchars($data['phone']),"</td>\n";
    echo "    <td>",htmlspecialchars($data['type']),"</td>\n";
    echo "    <td>",htmlspecialchars($data['year']),"</td>\n";
    echo "  </tr>\n";
  }

  pg_free_result($result);
}

echo "</table>\n";

sdsIncludeFooter();
