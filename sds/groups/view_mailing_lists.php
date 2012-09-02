<?php
require_once("../sds.php");
sdsRequireGroup("USERS");

function show_mailing_lists($selectionQuery, $allowOptOut, $caption) {
  global $username_esc;

  $result = sdsQuery($selectionQuery);
  if(!$result)
    contactTech("Could not search lists");
  if (pg_num_rows($result) == 0) {
    pg_free_result($result);
    return;
  }

  echo "<hr />\n";
  echo "<b>",$caption,"</b><br /><br />\n";

  echo "<table>\n";

  while($row = pg_fetch_object($result)) {
    $listname_esc = pg_escape_string($row->listname);
    echo "  <tr>\n";

    echo "    <td style='text-align:right'>\n";
    echo "      <a href='", sdsLink("manage_mailing_lists.php",
				    "target=".urlencode($row->listname)), "'>";
    echo "<b>",htmlspecialchars($row->listname),"</b></a>:\n";
    echo "    </td>\n";

    echo "    <td>\n";
    $aliasQuery = "SELECT alias FROM mailman_aliases WHERE listname='$listname_esc' ORDER BY alias";
    $aliasResult = sdsQuery($aliasQuery);
    if(!$aliasResult)
      contactTech("Could not search aliases");
    $aliases = array();
    while($aliasRow = pg_fetch_object($aliasResult)) {
      $aliases[] = $aliasRow->alias;
    }
    pg_free_result($aliasResult);
    $aliasStr = join(",", $aliases);
    if (strlen($aliasStr)) {
      echo "(<i>also known as: ",htmlspecialchars($aliasStr),"</i>)\n";
    }

    echo htmlspecialchars($row->description),"\n";
    if ($row->mandatory === 't') {
      // echo " [MANDATORY]";
    } elseif ($allowOptOut) {
      $optOutQuery = "SELECT 1 FROM mailman_optout WHERE username='$username_esc' AND listname='$listname_esc'";
      $optOutResult = sdsQuery($optOutQuery);
      if(!$optOutResult)
	contactTech("Could not find optout status");
      if (pg_num_rows($optOutResult) > 0) {
	echo " [OPTED OUT] (<a href='",
	  sdsLink("optout_mailing_lists.php",
		  "listname=".urlencode($row->listname)."&amp;value=in"),
	  "'>opt-in</a>)\n";
      } else {
	echo " (<a href='",
	  sdsLink("optout_mailing_lists.php",
		  "listname=".urlencode($row->listname)."&amp;value=out"),
	  "'>opt-out</a>)\n";
      }
    }
    echo "    </td>\n";

    echo "  </tr>\n";
  }
  echo "</table>\n"; 
  echo "<br />\n";
}

// display the form
sdsIncludeHeader("Mailing Lists");

$username_esc = pg_escape_string($session->username);
$username_disp = htmlspecialchars($session->username);

$allListsQuery =
  "SELECT * FROM mailman_lists WHERE NOT deleted ORDER BY listname";
$memberAndAdminListsQuery = <<<ENDQUERY
SELECT *
FROM mailman_lists
WHERE NOT deleted
  AND groupname IN (SELECT groupname FROM sds_group_membership_cache
                    WHERE username='$username_esc')
 AND ownergroup IN (SELECT groupname FROM sds_group_membership_cache
                    WHERE username='$username_esc')
ORDER BY listname
ENDQUERY;
$memberOrAdminListsQuery = <<<ENDQUERY
SELECT *
FROM mailman_lists
WHERE NOT deleted
  AND (groupname IN (SELECT groupname FROM sds_group_membership_cache
                     WHERE username='$username_esc')
   OR ownergroup IN (SELECT groupname FROM sds_group_membership_cache
                     WHERE username='$username_esc'))
ORDER BY listname
ENDQUERY;
$memberListsQuery = <<<ENDQUERY
SELECT *
FROM mailman_lists
WHERE NOT deleted
  AND groupname IN (SELECT groupname FROM sds_group_membership_cache
                    WHERE username='$username_esc')
ORDER BY listname
ENDQUERY;
$adminListsQuery = <<<ENDQUERY
SELECT *
FROM mailman_lists
WHERE NOT deleted
  AND ownergroup IN (SELECT groupname FROM sds_group_membership_cache
                     WHERE username='$username_esc')
ORDER BY listname
ENDQUERY;
$memberOnlyListsQuery = "SELECT * FROM ($memberOrAdminListsQuery) AS memberOrAdmin EXCEPT ($adminListsQuery) ORDER BY listname";
$adminOnlyListsQuery = "SELECT * FROM ($memberOrAdminListsQuery) AS memberOrAdmin EXCEPT ($memberListsQuery) ORDER BY listname";
$otherListsQuery = "SELECT * FROM mailman_lists WHERE NOT deleted EXCEPT ($memberOrAdminListsQuery) ORDER BY listname";

echo "<p>Some Simmons Hall mailing lists are hosted on the Simmons server.\n";
echo "   These lists are auto-magically maintained by Simmons DB.</p>\n";

show_mailing_lists($memberAndAdminListsQuery, true,
		   "You ($username_disp) are <span style='color:blue'>subscribed</span>, with <span style='color:blue'>administrator rights</span>, to:"); 
show_mailing_lists($memberOnlyListsQuery, true,
		   "You ($username_disp) are <span style='color:blue'>subscribed</span> to the following mailing lists:"); 
show_mailing_lists($adminOnlyListsQuery, false,
		   "You ($username_disp) are an <span style='color:blue'>administrator</span> (but not subscribed to) the following mailing lists:"); 

if(!empty($session->groups["ADMINISTRATORS"])) {
  show_mailing_lists($otherListsQuery, false,
		     "As an ADMINISTRATOR, you may also choose from other lists on this server");
}

echo "<hr /><b>You may also <a href='",
  sdsLink("manage_mailing_lists.php", "create=1"),
  "'>create a new list.</a></b><br /><br />\n";
 
sdsIncludeFooter();
