<?php
require_once("../sds.php");
require_once('../directory/directory.inc.php');

# require desk group
sdsRequireGroup("DESK");

sdsIncludeHeader("Guest List");

if(isset($_REQUEST['resident'])) {
  $resident = sdsSanitizeString($_REQUEST['resident']);
  $query = "SELECT 1 FROM sds_group_membership_cache WHERE username = '$resident' AND groupname = 'RESIDENTS'";
  $result = sdsQuery($query);
  if(!$result)
    contactTech("Can't verify residence");
  if(pg_num_rows($result)) {
    echo "<h2>Guest list for ",sdsGetFullName($resident),"</h2>\n";

    $query = "SELECT guest FROM guest_list WHERE username='$resident' AND date_added < now() AND date_invalid > now() ORDER BY guest";
    $guestresult = sdsQuery($query);
    if(!$result)
      contactTech("Can't read guest list");
    if(pg_num_rows($guestresult)) {
      echo "<table>\n";
      while($record = pg_fetch_array($guestresult)) {
	echo "  <tr>\n";
	echo "    <td>",htmlspecialchars($record['guest']),"</td>\n";
	echo "  </tr>\n";
      }
      echo "</table>\n";
    } else {
      echo "<p>No Entries</p>\n";
    }
    pg_free_result($guestresult);
  } else {
    echo "<h2 class='error'>",
      sdsGetFullName(maybeStripSlashes($_REQUEST['resident'])),
      " does not appear to live here.</h2>\n";
  }
} else {
  if($finduser = doDirectorySearch()) {
# done a search: show results
    showDirectorySearchResults($finduser,"guestlist.php","resident");
  }
  echo "<p>Show guest list for:</p>\n";
  showDirectorySearchForm("guestlist.php");
}

sdsIncludeFooter();
