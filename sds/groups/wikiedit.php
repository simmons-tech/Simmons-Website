<?php
require_once("../sds.php");
require_once("groupedit.inc.php");
require_once("../sds/groupMembership.inc.php");
sdsRequireGroup("USERS");

$prefix = getStringArg('prefix');
if($prefix === '') {
  header("Location: " . SDS_BASE_URL . 'groups/wikiaccess.php');
  exit;
}
$prefix_esc = pg_escape_string($prefix);

if(empty($session->groups['ADMINISTRATORS'])) {
  $query = <<<ENDQUERY
SELECT admin_groupname
FROM wiki_permissions_sds
WHERE wiki_prefix = '$prefix_esc'
ENDQUERY;
  $result = sdsQuery($query);
  if(!$result)
    contactTech("Could not find group permissions");
  list($admingroup) = pg_fetch_array($result);
  pg_free_result($result);
  if(empty($session->groups[$admingroup])) {
    sdsIncludeHeader('Wiki Permission Editing');
    echo "<h2 class='error'>Permission Denied</h2>\n";
    sdsIncludeFooter();
    exit;
  }
}

if(!empty($_REQUEST['update'])) {
  // We have been to this form before -- load up the values from the POST
  $readUsers = getStringArg('readUsers');
  $readGroups = getStringArg('readGroups');
  $writeUsers = getStringArg('writeUsers');
  $writeGroups = getStringArg('writeGroups');
  $adminUsers = getStringArg('adminUsers');
  $adminGroups = getStringArg('adminGroups');

  $writeWikiusers = getStringArg('writeWikiusers');
  $readWikiusers = getStringArg('readWikiusers');

  $errors = array();

  function makeGroup($type,$groups,$users,&$errors) {
    global $prefix;
    if($groups === '' and $users === '') {
      return null;
    } else {
      $errorCatch = array();
      $groupsArray = groupedit_str2groups($groups, ',', $errorCatch);
      $usersArray = groupedit_str2users($users, ',', $errorCatch);
      if(count($errorCatch) > 0) {
	$errors[$type] = "The following entries weren't valid: " .
	  htmlspecialchars(join($errorCatch, ", "));
      } else {
	return groupedit_build_group('', $usersArray,
				     $groupsArray,'%',true,
				     "wiki:$type:$prefix");
      }
    }
  }

  $readgroup = makeGroup('read',$readGroups,$readUsers,$errors);
  $writegroup = makeGroup('write',$writeGroups,$writeUsers,$errors);
  $admingroup = makeGroup('admin',$adminGroups,$adminUsers,$errors);

  $writeWikiList = explode("\n",$writeWikiusers);
  $readWikiList = explode("\n",$readWikiusers);

####################################################
### Now that we've validated, we can take any appropriate actions #'
  if(count($errors) == 0) {
    $updater = array('read_groupname' => $readgroup,
		     'write_groupname' => $writegroup,
		     'admin_groupname' => $admingroup
		     );

    $transres = sdsQuery("BEGIN");
    if(!$transres)
      contactTech("Could not start transaction");
    pg_free_result($transres);

    // execute the changes
    $query = "UPDATE wiki_permissions_sds SET " . sqlArrayUpdate($updater) .
      " WHERE wiki_prefix = '$prefix_esc'";
    $result = sdsQuery($query);
    if(!$result or pg_affected_rows($result) != 1) {
      contactTech("Could not update permissions",false);
      if(!sdsQuery("ROLLBACK"))
	contactTech("Could not rollback");
      sdsIncludeFooter();
      exit;
    }
    pg_free_result($result);

    $query =
      "DELETE FROM wiki_permissions_wikiuser WHERE wiki_prefix='$prefix_esc'";
    $result = sdsQuery($query);
    if(!$result) {
      contactTech("Could not clear wikiusers",false);
      if(!sdsQuery("ROLLBACK"))
	contactTech("Could not rollback");
      sdsIncludeFooter();
      exit;
    }
    pg_free_result($result);

    if((isset($admingroup) and
	!refreshGroupMembership($admingroup, false, false)) or
       (isset($writegroup) and
	!refreshGroupMembership($writegroup, false, false)) or
       (isset($readgroup) and
	!refreshGroupMembership($readgroup, false, false))) {
      if(!sdsQuery("ROLLBACK"))
	contactTech("Could not rollback");
      sdsIncludeFooter();
      exit;
    }

    $insertedUsers = array();
    function addUserPerm($user,$perm) {
      global $insertedUsers;
      global $prefix;
      $user = trim($user);
      if(!empty($insertedUsers[$user]))
	return;
      $insertedUsers[$user] = 1;
      $userUpdater = array('wiki_prefix' => $prefix,
			   'wiki_username' => $user,
			   'access' => $perm
			   );
      $query = "INSERT INTO wiki_permissions_wikiuser " .
	sqlArrayInsert($userUpdater);
      $result = sdsQuery($query);
      if(!$result or pg_affected_rows($result) != 1) {
	contactTech("Could not create user permission",false);
	if(!sdsQuery("ROLLBACK"))
	  contactTech("Could not rollback");
	sdsIncludeFooter();
	exit;
      }
      pg_free_result($result);
    }

    foreach($writeWikiList as $user) {
      addUserPerm($user,'write');
    }
    foreach($readWikiList as $user) {
      addUserPerm($user,'read');
    }

    $transres = sdsQuery("COMMIT");
    if(!$transres) {
      contactTech("Could not commit",false);
      if(!sdsQuery("ROLLBACK"))
	contactTech("Could not rollback");
      sdsIncludeFooter();
      exit;
    }

    if(!empty($session->groups["ADMINISTRATORS"])) {
      // Others might like to, but only admins can
      sdsSetReminder("updateGroupCache",
		     "You may want to refresh the group cache.");
    }
    header("Location: " . sdsLink($_SERVER["PHP_SELF"],
				  "prefix=".urlencode($prefix),true));
    exit;
  }

} else { # not updating
  // load the info from the database
  $query = <<<ENDQUERY
SELECT read_groupname,write_groupname,admin_groupname
FROM wiki_permissions_sds
WHERE wiki_prefix = '$prefix_esc'
ENDQUERY;
  $result = sdsQuery($query);
  if(!$result)
    contactTech("Could not search group permissions");
  $groups = pg_fetch_object($result);
  pg_free_result($result);

  $groupExplosion = groupedit_group2str($groups->read_groupname);
  $readUsers = $groupExplosion['users'];
  $readGroups = $groupExplosion['groups'];
  $groupExplosion = groupedit_group2str($groups->write_groupname);
  $writeUsers = $groupExplosion['users'];
  $writeGroups = $groupExplosion['groups'];
  $groupExplosion = groupedit_group2str($groups->admin_groupname);
  $adminUsers = $groupExplosion['users'];
  $adminGroups = $groupExplosion['groups'];

  $query = <<<ENDQUERY
SELECT wiki_username
FROM wiki_permissions_wikiuser
WHERE wiki_prefix = '$prefix_esc' AND access = 'write'
ORDER BY wiki_username
ENDQUERY;
  $result = sdsQuery($query);
  if(!$result)
    contactTech("Could not search wikiuser permissions");

  $writeWikiusers = '';
  while($record = pg_fetch_object($result))
    $writeWikiusers .= $record->wiki_username . "\n";
  pg_free_result($result);

  $query = <<<ENDQUERY
SELECT wiki_username
FROM wiki_permissions_wikiuser
WHERE wiki_prefix = '$prefix_esc' AND access = 'read'
ORDER BY wiki_username
ENDQUERY;
  $result = sdsQuery($query);
  if(!$result)
    contactTech("Could not search wikiuser permissions");

  $readWikiusers = '';
  while($record = pg_fetch_object($result))
    $readWikiusers .= $record->wiki_username . "\n";
  pg_free_result($result);
}

####################################################
### If we haven't taken care of the action already, let's show the form.
// display the form

$editing = !empty($_REQUEST['edit']) or !empty($_REQUEST['update']);

$prefix_disp = htmlspecialchars($prefix,ENT_QUOTES);

sdsIncludeHeader("Wiki Permission Editing", '', groupedit_head());

echo "<form action='",$_SERVER["PHP_SELF"],"' method='post'>\n";
echo sdsForm();
echo "  <input type='hidden' name='prefix' value='",$prefix_disp,"' />\n";

function nextRowColor() {
  static $rowToggle = false;
  $rowToggle = ! $rowToggle;
  return $rowToggle ? 'evenrow' : 'oddrow';
}

echo "  <table class='maillist'>\n";

$c = nextRowColor();
echo "    <tr class='",$c,"'>\n";
echo "      <td>Prefix:</td><td>",$prefix_disp,"</td>\n";
echo "    </tr>\n";

$c = nextRowColor();
echo "    <tr class='",$c,"'>\n";
echo "      <th colspan='3'>Permissions for Simmons DB accounts<br />\n";
echo "        <span class='detail'>(EVERYONE means the whole world)</span></th>\n";
echo "    </tr>\n";

$c = nextRowColor();
echo "    <tr class='",$c,"'>\n";
echo "      <td>Administrators:</td>\n";
echo "      <td>\n";
echo "        Groups:&nbsp;";
if ($editing) {
  echo "<input type='text' name='adminGroups' id='adminGroups' size='40' value='",
    htmlspecialchars($adminGroups,ENT_QUOTES),"' />\n";
  echo groupedit_groups('adminGroups'),"\n";
} else {
  echo htmlspecialchars($adminGroups),"\n";
}
echo "        <br />\n";

echo "        Users:&nbsp;";
if ($editing) {
  echo "<input type='text' name='adminUsers' id='adminUsers' size='40' value='",
    htmlspecialchars($adminUsers,ENT_QUOTES),"' />\n";
  echo groupedit_users('adminUsers'),"\n";
} else {
  echo htmlspecialchars($adminUsers),"\n";
}
echo "      </td>\n";
if(isset($errors['admin']))
  echo "      <td class='error'>",$errors['admin'],"</td>\n";
echo "    </tr>\n";

$c = nextRowColor();
echo "    <tr class='",$c,"'>\n";
echo "      <td>Writers: <span class='detail'>(Implies reader)</span></td>\n";
echo "      <td>\n";
echo "        Groups:&nbsp;";
if ($editing) {
  echo "<input type='text' name='writeGroups' id='writeGroups' size='40' value='",
    htmlspecialchars($writeGroups,ENT_QUOTES),"' />\n";
  echo groupedit_groups('writeGroups'),"\n";
} else {
  echo htmlspecialchars($writeGroups),"\n";
}
echo "        <br />\n";

echo "        Users:&nbsp;";
if ($editing) {
  echo "<input type='text' name='writeUsers' id='writeUsers' size='40' value='",
    htmlspecialchars($writeUsers,ENT_QUOTES),"' />\n";
  echo groupedit_users('writeUsers'),"\n";
} else {
  echo htmlspecialchars($writeUsers),"\n";
}
echo "      </td>\n";
if(isset($errors['write']))
  echo "      <td class='error'>",$errors['write'],"</td>\n";
echo "    </tr>\n";

$c = nextRowColor();
echo "    <tr class='",$c,"'>\n";
echo "      <td>Readers:</td>\n";
echo "      <td>\n";
echo "        Groups:&nbsp;";
if ($editing) {
  echo "<input type='text' name='readGroups' id='readGroups' size='40' value='",
    htmlspecialchars($readGroups,ENT_QUOTES),"' />\n";
  echo groupedit_groups('readGroups'),"\n";
} else {
  echo htmlspecialchars($readGroups),"\n";
}
echo "        <br />\n";

echo "        Users:&nbsp;";
if ($editing) {
  echo "<input type='text' name='readUsers' id='readUsers' size='40' value='",
    htmlspecialchars($readUsers,ENT_QUOTES),"' />\n";
  echo groupedit_users('readUsers'),"\n";
} else {
  echo htmlspecialchars($readUsers),"\n";
}
echo "      </td>\n";
if(isset($errors['read']))
  echo "      <td class='error'>",$errors['read'],"</td>\n";
echo "    </tr>\n";

$c = nextRowColor();
echo "    <tr class='",$c,"'>\n";
echo "      <th colspan='3'>Additional permissions for Wiki Usernames</th>\n";
echo "    </tr>\n";

$c = nextRowColor();
echo "    <tr class='$c'>\n";
echo "      <td>Writers: <span class='detail'>(Implies reader)";
if ($editing) echo "<br />(one per line)";
echo "</span></td>\n";
echo "      <td>\n";
if ($editing) {
  echo "        <textarea name='writeWikiusers' rows='10' cols='30'>",
    htmlspecialchars($writeWikiusers),"</textarea>\n";
} else {
  echo nl2br(htmlspecialchars($writeWikiusers)),"\n";
}
echo "      </td>\n";
if(isset($errors['writeWikiUsers']))
  echo "      <td class='error'>",$errors['writeWikiusers'],"</td>\n";
echo "    </tr>\n";

$c = nextRowColor();
echo "    <tr class='$c'>\n";
echo "      <td>Readers:";
if ($editing) echo " <span class='detail'><br />(one per line)</span>";
echo "</td>\n";
echo "      <td>\n";
if ($editing) {
  echo "        <textarea name='readWikiusers' rows='10' cols='30'>",
    htmlspecialchars($readWikiusers),"</textarea>\n";
} else {
  echo nl2br(htmlspecialchars($readWikiusers)),"\n";
}
echo "      </td>\n";
if(isset($errors['readWikiUsers']))
  echo "      <td class='error'>",$errors['readWikiusers'],"</td>\n";
echo "    </tr>\n";

echo "    <tr>\n";
echo "      <td></td>\n";
echo "      <td>";
if(!$editing) {
  echo '<input type="submit" name="edit" value="Edit" />';
} else {
  echo '<input type="submit" name="update" value="Update" />';
}
echo "</td>\n";
echo "    </tr>\n";

echo "  </table>\n";
echo "</form>\n";

echo "<p>Back to <a href='",sdsLink('wikiaccess.php'),">Wiki Access</a></p>\n";

sdsIncludeFooter();
