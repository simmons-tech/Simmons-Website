<?php
require_once("../sds.php");
require_once("groupedit.inc.php");
require_once("../sds/groupMembership.inc.php");
sdsRequireGroup("USERS");

$validNamePattern = "/^[a-z0-9\-]+$/";

#####################################################
### Load up the variables with appropriate values  
unset($newPassword,$confirmNewPassword);

if (isset($_REQUEST['continue'])) {
  // We have been to this form before -- load up the values from the POST
  if(empty($_REQUEST['create'])) {
    $targetListname_esc = sdsSanitizeString($_REQUEST["target"]);
    $targetListname_disp =
      htmlspecialchars(maybeStripslashes($_REQUEST["target"]),ENT_QUOTES);
  } else {
    $targetListname_esc = '';
    $targetListname_disp = '';
  }

  $listname = maybeStripslashes($_REQUEST["listname"]);
  $newPassword = maybeStripslashes($_REQUEST["newPassword"]);
  $confirmNewPassword = maybeStripslashes($_REQUEST["confirmNewPassword"]);
  $subjectPrefix = maybeStripslashes($_REQUEST["subjectPrefix"]);
  $private = !empty($_REQUEST["private"]) and
    strtolower($_REQUEST["private"] != 'false');
  $moderated = !empty($_REQUEST["moderated"]) and
    strtolower($_REQUEST["moderated"] != 'false');
  $mandatory = !empty($_REQUEST["mandatory"]) and
    strtolower($_REQUEST["mandatory"] != 'false');
  $description = maybeStripslashes($_REQUEST["description"]);
  $aliases = maybeStripslashes($_REQUEST["aliases"]);
  $superlists = maybeStripslashes($_REQUEST["superlists"]);
  $memberUsers = maybeStripslashes($_REQUEST["memberUsers"]);
  $memberGroups = maybeStripslashes($_REQUEST["memberGroups"]);
  $ownerUsers = maybeStripslashes($_REQUEST["ownerUsers"]);
  $ownerGroups = maybeStripslashes($_REQUEST["ownerGroups"]);
} elseif(isset($_REQUEST["target"]) and empty($_REQUEST['create'])) {
  // load the target list from the database
  $targetListname_esc = sdsSanitizeString($_REQUEST["target"]);
  $targetListname_disp =
    htmlspecialchars(maybeStripslashes($_REQUEST["target"]),ENT_QUOTES);

  $query = "SELECT * FROM mailman_lists WHERE listname='$targetListname_esc' AND NOT deleted";
  $result = sdsQuery($query);
  iF(!$result)
    contactTech("Could not search lists");
  if(pg_num_rows($result) == 0) {
    sdsErrorPage("Unknown Listname.", "Sorry, we couldn't find the mailing list $targetListname_disp.");
    exit;
  }
  $row = pg_fetch_object($result);
  $listname = $row->listname;
  $subjectPrefix = $row->subject_prefix;
  $private = ($row->private == 't');
  $moderated = ($row->moderated == 't');
  $mandatory = ($row->mandatory == 't');
  $description = $row->description;
  pg_free_result($result);

  $aliasQuery = "SELECT alias FROM mailman_aliases WHERE listname='$targetListname_esc' ORDER BY alias";
  $aliasResult = sdsQuery($aliasQuery);
  if(!$aliasResult)
    contactTech("Could not search aliases");
  $aliasArray = array();
  while($aliasRow = pg_fetch_object($aliasResult)) {
    $aliasArray[] = $aliasRow->alias;
  }
  $aliases = join(",", $aliasArray);
  pg_free_result($aliasResult);

  $superlistQuery = "SELECT superlist FROM mailman_superlists WHERE listname='$targetListname_esc' ORDER BY superlist";
  $superlistResult = sdsQuery($superlistQuery);
  if(!$superlistResult)
    contactTech("Could not search superlists");
  $superlistArray = array();
  while($superlistRow = pg_fetch_object($superlistResult)) {
    $superlistArray[] = $superlistRow->superlist;
  }
  $superlists = join(",", $superlistArray);
  pg_free_result($superlistResult);

  $groupExplosion = groupedit_group2str($row->groupname);
  $memberUsers = $groupExplosion['users'];
  $memberGroups = $groupExplosion['groups'];

  $ownerExplosion = groupedit_group2str($row->ownergroup);
  $ownerUsers = $ownerExplosion['users'];
  $ownerGroups = $ownerExplosion['groups'];

} elseif(!empty($_REQUEST['create'])) {
  // use the default (empty) values
  $targetListname_esc = '';
  $targetListname_disp = '';
  $listname = '';
  $memberGroups = '';
  $memberUsers = '';
  $ownerGroups = '';
  $ownerUsers = $session->username;
  $aliases = '';
  $superlists = '';
  $subjectPrefix = '';
  $private = false;
  $moderated = false;
  $mandatory = false;
  $description = '';
} else {
  // can't view, update, or delete without a target.
  header("Location: " . SDS_BASE_URL .
	 sdsLink("groups/view_mailing_lists.php"));
  exit;
}

##################################################
### Check permissions
###
### If you are trying to update or delete an existing lists, you need to be
### in that list's owner group.
###'
$existing_ownergroup = '';
if ($targetListname_esc) {
  $query = "SELECT ownergroup FROM mailman_lists WHERE listname='$targetListname_esc'";
  $result = sdsQuery($query);
  if(!$result)
    contactTech("Could not search groups");
  if (pg_num_rows($result) == 0) {
    sdsErrorPage("Unknown Listname.", "Sorry, we couldn't find the mailing list $targetListname_disp.");
    exit;
  }

  $object = pg_fetch_object($result);
  $existing_ownergroup = $object->ownergroup;
  pg_free_result($result);
}

if (!empty($_REQUEST['update']) or !empty($_REQUEST['delete'])) {
  sdsRequireGroup($existing_ownergroup);
}
  
##################################################
### Decide which mode we're in #'
$editing = (!empty($_REQUEST['create']) or !empty($_REQUEST['update']));

##################################################
### If we are creating or updating, we'll need to validate
### all the fields.  #'
$errors = array();
if ($editing and isset($_REQUEST['continue'])) {
  $error = FALSE;
  if ($listname === '') {
    $errors['listname'] = "Please enter a list name.";
    $error = TRUE;
  } elseif(!preg_match($validNamePattern, $listname)) {
    $errors['listname'] = "Only lowercase letters, numbers, and dashes may be used in list names.";
    $error = TRUE;
    // From here until the end of the elseif chain and then wherever !$error
    // $listname is known to contain only alphanumerics and dashes, and is
    // therefore safe to output and use in queries
  } elseif($listname != $targetListname_esc) {
    $query = "SELECT 1 FROM mailman_lists WHERE listname='$listname';";
    $result = sdsQuery($query);
    if(!$result)
      contactTech("Could not search lists");
    if (pg_num_rows($result) > 0) {
      $errors['listname'] = "A list with the name <tt>$listname</tt> already exists.  Please pick another name.";
      $error = TRUE;
    }
    pg_free_result($result);

    $query = "SELECT listname FROM mailman_aliases WHERE alias='$listname';";
    $result = sdsQuery($query);
    if(!$result)
      contactTech("Could not search aliases");
    if (pg_num_rows($result) > 0) {
      $row = pg_fetch_object($result);
      $errors['listname'] = "The mailing list <tt>".
	htmlspecialchars($row->listname).
	"</tt> already uses <tt>$listname</tt> as an alias.  Please pick another name.";
      $error = TRUE;
    }
    pg_free_result($result);
  }

  $aliases = trim($aliases);
  if ($aliases !== '') {
    $aliasArray = split(",", $aliases);
  } else {
    $aliasArray = array();
  }
  foreach ($aliasArray as $alias) {
    $alias = trim($alias);
    if($alias === '') {
      $errors['aliases'] = "Empty aliases are not allowed.";
      $error = TRUE;
    } elseif(!preg_match($validNamePattern, $alias)) {
      $errors['aliases'] = "Only lowercase letters, numbers, and dashes may be used in aliases.";
      $error = TRUE;
    } elseif($alias === $listname) {
      $errors['aliases'] = "Can't alias a list to itself.";
      $error = TRUE;
    }

    $alias_esc = pg_escape_string($alias);
    $query = "SELECT 1 FROM mailman_lists WHERE listname='$alias_esc' AND listname != '$targetListname_esc'";
    $result = sdsQuery($query);
    if(!$result)
      contactTech("Could not search lists");
    if (pg_num_rows($result) > 0) {
      $errors['aliases'] = "Cannot use ".htmlspecialchars($alias).
	" as an alias, because a list with that name already exists.";
      $error = TRUE;
    }
    pg_free_result($result);

    $query = "SELECT listname FROM mailman_aliases WHERE alias='$alias_esc' AND listname != '$targetListname_esc'";
    $result = sdsQuery($query);
    if(!$result)
      contactTech("Could not search aliases");
    if (pg_num_rows($result) > 0) {
      $errors['aliases'] = "Cannot use ".htmlspecialchars($alias).
	" as an alias, because another list is already using that alias.";
      $error = TRUE;
    }
    pg_free_result($result);
  }

  $superlists = trim($superlists);
  if ($superlists !== '') {
    $superlistArray = split(",", $superlists);
  } else {
    $superlistArray = array();
  }
  foreach ($superlistArray as $superlist) {
    $superlist = trim($superlist);
    if($superlist === '') {
      $errors['superlists'] = "Empty superlists are not allowed.";
      $error = TRUE;
    }
  }

  $memberGroups = trim($memberGroups);
  $memberUsers = trim($memberUsers);
  if ($memberGroups === '' and $memberUsers === '') {
    $errors['members'] = "Please specify at lease one username or groupname.";
    $error = TRUE;
  } else {
    $errorCatch = array();
    $memberGroupsArray = groupedit_str2groups($memberGroups, ',', $errorCatch);
    $memberUsersArray = groupedit_str2users($memberUsers, ',', $errorCatch);
    if (count($errorCatch) > 0) {
      $errors['members'] = "The following entries weren't valid: " .
	htmlspecialchars(join($errorCatch, ", "));
      $error = TRUE;
    } else {
      $groupname = groupedit_build_group('', $memberUsersArray,
					 $memberGroupsArray,'127.0.0.1',true,
					 "mailman:members:$listname");
    }
  }

  $ownerGroups = trim($ownerGroups);
  $ownerUsers = trim($ownerUsers);
  if ($ownerGroups === '' and $ownerUsers === '') {
    $errors['owners'] = "Please specify at lease one username or groupname.";
    $error = TRUE;
  } else {
    $errorCatch = array();
    $ownerGroupsArray = groupedit_str2groups($ownerGroups, ',', $errorCatch);
    $ownerUsersArray = groupedit_str2users($ownerUsers, ',', $errorCatch);
    if (count($errorCatch) > 0) {
      $errors['owners'] = "The following entries weren't valid: " .
	htmlspecialchars(join($errorCatch, ", "));
      $error = TRUE;
    } else {
      $ownergroup = groupedit_build_group('', $ownerUsersArray,
					  $ownerGroupsArray, '%', true,
					  "mailman:owners:$listname");
    }
  }

  if (!empty($_REQUEST['create']) and $newPassword === '') {
    $errors['newPassword'] = "Please enter a password.";
    $error = TRUE;
  }

  if ($newPassword !== $confirmNewPassword) {
    $errors['newPassword'] = "Password and confirmation password don't match.";
    $error = TRUE;
  }

####################################################
### Now that we've validated, we can take any appropriate actions #'
  if(!$error) {
    // we've passed error checking, let's actually create the list

    // these are the relevant fields
    $updater = array('listname' => $listname,
		     'creator' => $session->username,
		     'description' => $description,
		     'subject_prefix' => $subjectPrefix,
		     'groupname' => $groupname,
		     'ownergroup' => $ownergroup,
		     'private' => ($private ? 'true' : 'false')
		     );
    if(!empty($session->groups["ADMINISTRATORS"])) {
      $updater["moderated"] = ($moderated ? 'true' : 'false');
      $updater["mandatory"] = ($mandatory ? 'true' : 'false');
    }

    // next we'll encrypt the password
    if ($newPassword !== '') {
      $updater["password"] = sdsEncrypt($newPassword);
    }

    $transres = sdsQuery("BEGIN");
    if(!$transres)
      contactTech("Could not start transaction");
    pg_free_result($transres);

    // execute the changes
    if (!empty($_REQUEST['create'])) {
      $query = "INSERT INTO mailman_lists " . sqlArrayInsert($updater);
      $result = sdsQuery($query);
      if(!$result or pg_affected_rows($result) != 1) {
	contactTech("Could not create list",false);
	if(!sdsQuery("ROLLBACK"))
	  contactTech("Could not rollback");
	sdsIncludeFooter();
	exit;
      }
      pg_free_result($result);
    } elseif(!empty($_REQUEST['update'])) {
      $query = "UPDATE mailman_lists SET " . sqlArrayUpdate($updater) .
	" WHERE listname='$targetListname_esc'";
      $result = sdsQuery($query);
      if(!$result or pg_affected_rows($result) != 1) {
	contactTech("Could not update list",false);
	if(!sdsQuery("ROLLBACK"))
	  contactTech("Could not rollback");
	sdsIncludeFooter();
	exit;
      }
      pg_free_result($result);

      $query =
	"DELETE FROM mailman_aliases WHERE listname='$targetListname_esc'";
      $result = sdsQuery($query);
      if(!$result) {
	contactTech("Could not clear aliases",false);
	if(!sdsQuery("ROLLBACK"))
	  contactTech("Could not rollback");
	sdsIncludeFooter();
	exit;
      }
      pg_free_result($result);

      $query =
	"DELETE FROM mailman_superlists WHERE listname='$targetListname_esc'";
      $result = sdsQuery($query);
      if(!$result) {
	contactTech("Could not clear superlists",false);
	if(!sdsQuery("ROLLBACK"))
	  contactTech("Could not rollback");
	sdsIncludeFooter();
	exit;
      }
      pg_free_result($result);
    }

    if(!refreshGroupMembership($groupname, false, false) or
       !refreshGroupMembership($ownergroup, false, false)) {
      if(!sdsQuery("ROLLBACK"))
	contactTech("Could not rollback");
      sdsIncludeFooter();
      exit;
    }

    $insertedAliases = array();
    foreach ($aliasArray as $alias) {
      $alias = trim($alias);
      if (in_array($alias, $insertedAliases)) {
	continue;
      }
      $insertedAliases[] = $alias;
      $aliasUpdater = array('listname' => $listname,
			    'alias' => $alias
			    );
      $query = "INSERT INTO mailman_aliases " . sqlArrayInsert($aliasUpdater);
      $result = sdsQuery($query);
      if(!$result or pg_affected_rows($result) != 1) {
	contactTech("Could not create alias",false);
	if(!sdsQuery("ROLLBACK"))
	  contactTech("Could not rollback");
	sdsIncludeFooter();
	exit;
      }
      pg_free_result($result);
    }

    $insertedSuperlists = array();
    foreach ($superlistArray as $superlist) {
      $superlist = trim($superlist);
      if (in_array($superlist, $insertedSuperlists)) {
	continue;
      }
      $insertedSuperlists[] = $superlist;
      $superlistUpdater = array('listname' => $listname,
				'superlist' => $superlist
			    );
      $query = "INSERT INTO mailman_superlists " .
	sqlArrayInsert($superlistUpdater);
      $result = sdsQuery($query);
      if(!$result or pg_affected_rows($result) != 1) {
	contactTech("Could not record superlist",false);
	if(!sdsQuery("ROLLBACK"))
	  contactTech("Could not rollback");
	sdsIncludeFooter();
	exit;
      }
      pg_free_result($result);
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
      sdsSetReminder("updateListCache",
		     "You may want to refresh the mailing lists.");
    }
    header("Location: " . sdsLink($_SERVER["PHP_SELF"],
				  "target=".urlencode($listname),true));
    exit;
  }
} elseif(!empty($_REQUEST['delete']) and isset($_REQUEST['continue'])) {
  $query = <<<ENDQUERY
DELETE FROM mailman_aliases WHERE listname='$targetListname_esc';
DELETE FROM mailman_optout WHERE listname='$targetListname_esc';
UPDATE mailman_lists SET groupname=null,ownergroup=null,deleted=true
  WHERE listname='$targetListname_esc';
ENDQUERY;
  $result = sdsQuery($query);
  if(!$result)
    contactTech("Could not delete group");
  pg_free_result($result);

  header("Location: ".sdsLink(SDS_BASE_URL."groups/view_mailing_lists.php"));
  exit;
}


####################################################
### If we haven't taken care of the action already, let's show the form.
// display the form
sdsIncludeHeader("Mailing Lists", "Mailing Lists", groupedit_head());

echo "<form action='",$_SERVER["PHP_SELF"],"' method='post'>\n";
echo sdsForm();
if ($targetListname_esc !== '') {
  echo "  <input type='hidden' name='target' value='$targetListname_disp' />\n";
}
if (!empty($_REQUEST['update'])) {
  echo "  <input type='hidden' name='update' value='1' />\n";
}
if (!empty($_REQUEST['create'])) {
  echo "  <input type='hidden' name='create' value='1' />\n";
}
if (!empty($_REQUEST['delete'])) {
  echo "  <input type='hidden' name='delete' value='1' />\n";
  echo "  <span class='alert'>Are you sure you want to delete this mailing list?</span><br />\n";
}

function nextRowColor() {
  static $rowToggle = false;
  $rowToggle = ! $rowToggle;
  return $rowToggle ? 'evenrow' : 'oddrow';
}

echo "  <table class='maillist'>\n";

if ($targetListname_disp !== '') {
  $c = nextRowColor();
  echo "    <tr class='$c'>\n";
  echo "      <td>List name:</td><td>$targetListname_disp</td>\n";
  echo "    </tr>\n";
}

if ($editing) {
  $c = nextRowColor();
  echo "    <tr class='$c'>\n";

  echo "      <td>New List name:</td>\n";
  echo "      <td>\n";
  echo "        <input type='text' name='listname' size='32' value='",
    htmlspecialchars($listname,ENT_QUOTES),"' />\n";
  echo "      </td>\n";
  if(isset($errors['listname']))
    echo "      <td class='error'>{$errors['listname']}</td>\n";
  echo "    </tr>\n";

  $c = nextRowColor();
  echo "    <tr class='$c'>\n";
  echo "      <td>Mailman List Password:</td>\n";
  echo "      <td>\n";
  echo "        <input type='password' name='newPassword' size='32' />\n";
  echo "      </td>\n";
  if(isset($errors['newPassword']))
    echo "      <td class='error'>{$errors['newPassword']}</td>\n";
  echo "    </tr>\n";

  $c = nextRowColor();
  echo "    <tr class='$c'>\n";
  echo "      <td>Confirm Password:</td>\n";
  echo "      <td>\n";
  echo "        <input type='password' name='confirmNewPassword' size='32' />\n";
  echo "      </td>\n";
  if(isset($errors['confirmNewPassword']))
    echo "      <td class='error'>{$errors['confirmNewPassword']}</td>\n";
  echo "    </tr>\n";
}

$c = nextRowColor();
echo "    <tr class='$c'>\n";
echo "      <td>Members</td>\n";
echo "      <td>\n";
echo "        Groups:&nbsp;";
if ($editing) {
  echo "<input type='text' name='memberGroups' id='memberGroups' size='40' value='",
    htmlspecialchars($memberGroups,ENT_QUOTES),"' />\n";
  echo groupedit_groups('memberGroups'),"\n";
} else {
  echo htmlspecialchars($memberGroups),"\n";
}
echo "        <br />\n";

echo "        Users:&nbsp;";
if ($editing) {
  echo "<input type='text' name='memberUsers' id='memberUsers' size='40' value='",
    htmlspecialchars($memberUsers,ENT_QUOTES),"' />\n";
  echo groupedit_users('memberUsers'),"\n";
} else {
  echo htmlspecialchars($memberUsers),"\n";
}
echo "      </td>\n";
if(isset($errors['members']))
  echo "      <td class='error'>{$errors['members']}</td>\n";
echo "    </tr>\n";

$c = nextRowColor();
echo "    <tr class='$c'>\n";
echo "      <td>Owners</td>\n";
echo "      <td>\n";
echo "        Groups:&nbsp;";
if ($editing) {
  echo "<input type='text' name='ownerGroups' id='ownerGroups' size='40' value='",
    htmlspecialchars($ownerGroups,ENT_QUOTES),"' />\n";
  echo groupedit_groups('ownerGroups'),"\n";
} else {
  echo htmlspecialchars($ownerGroups),"\n";
}
echo "        <br />\n";

echo "        Users:&nbsp;";
if ($editing) {
  echo "<input type='text' name='ownerUsers' id='ownerUsers' size='40' value='",
    htmlspecialchars($ownerUsers,ENT_QUOTES),"' />\n";
  echo groupedit_users('ownerUsers'),"\n";
} else {
  echo htmlspecialchars($ownerUsers),"\n";
}
echo "      </td>\n";
if(isset($errors['owners']))
  echo "      <td class='error'>{$errors['owners']}</td>\n";
echo "    </tr>\n";

$c = nextRowColor();
echo "    <tr class='$c'>\n";
echo "      <td>Aliases:";
if ($editing) echo "<br />(comma separated list)";
echo "</td>\n";
echo "      <td>\n";
if ($editing) {
  echo "        <input type='text' name='aliases' size='32' value='",
    htmlspecialchars($aliases,ENT_QUOTES),"' />\n";
} else {
  echo "        ",htmlspecialchars($aliases),"\n";
}
echo "      </td>\n";
if(isset($errors['aliases']))
  echo "      <td class='error'>{$errors['aliases']}</td>\n";
echo "    </tr>\n";

$c = nextRowColor();
echo "    <tr class='$c'>\n";
echo "      <td>Superlists:<br />(usually moira lists)";
if ($editing) echo "<br />(comma separated list)";
echo "</td>\n";
echo "      <td>\n";
if ($editing) {
  echo "        <input type='text' name='superlists' size='32' value='",
    htmlspecialchars($superlists,ENT_QUOTES),"' />\n";
} else {
  echo "        ",htmlspecialchars($superlists),"\n";
}
echo "      </td>\n";
if(isset($errors['superlists']))
  echo "      <td class='error'>{$errors['superlists']}</td>\n";
echo "    </tr>\n";

$c = nextRowColor();
echo "    <tr class='$c'>\n";
echo "      <td>Subject Prefix:</td>\n";
echo "      <td>\n";
if ($editing) {
  echo "        <input type='text' name='subjectPrefix' size='32' value='",
    htmlspecialchars($subjectPrefix,ENT_QUOTES),"' />\n";
} else {
  echo "        ",htmlspecialchars($subjectPrefix),"\n";
}
echo "      </td>\n";
if(isset($errors['subjectPrefix']))
  echo "      <td class='error'>{$errors['subjectPrefix']}</td>\n";
echo "    </tr>\n";

$c = nextRowColor();
echo "    <tr class='$c'>\n";
echo "      <td>Configuration:</td>\n";
echo "      <td>\n";
echo "        <ul>\n";
  
if ($editing) {
  echo '          <li><input type="checkbox" name="private"';
  if ($private) {
    echo ' checked="checked"';
  }
  echo " />Private list</li>\n";

} else {
  echo "          <li>",($private ? "private" : "public"),"</li>\n";
}

// only administrators can create mandatory lists and moderated lists.
if ($editing and !empty($session->groups["ADMINISTRATORS"])) {
  echo '          <li><input type="checkbox" name="moderated"';
  if ($moderated) {
    echo ' checked="checked"';
  }
  echo " />Moderated</li>\n";

  echo '          <li><input type="checkbox" name="mandatory"';
  if ($mandatory) {
    echo ' checked="checked"';
  }
  echo " />Mandatory</li>\n";
} else {
  echo "          <li>",($moderated ? "moderated" : "unmoderated"),"</li>\n";
  echo "          <li>",($mandatory ? "mandatory" : "not mandatory"),"</li>\n";
}
echo "        </ul>\n";
echo "      </td>\n";
echo "    </tr>\n";

$c = nextRowColor();
echo "    <tr class='$c'>\n";
echo "      <td>Description:</td>\n";
echo "      <td>\n";
if ($editing) {
  echo '        <textarea name="description" rows="3" cols="32">',
    htmlspecialchars($description),"</textarea>\n";
} else {
  echo "        ",htmlspecialchars($description),"\n";
}
echo "      </td>\n";
if(isset($errors['description']))
  echo "      <td class='error'>{$errors['description']}</td>\n";
echo "    </tr>\n";

echo "    <tr>\n";
echo "      <td></td>\n";
echo "      <td>";
if(!empty($_REQUEST['create'])) {
  echo '<input type="submit" name="continue" value="Create Mailing List" />';
} elseif(!empty($_REQUEST['update'])) {
  echo '<input type="submit" name="continue" value="Update Mailing List" />';
} elseif(!empty($_REQUEST['delete'])) {
  echo "<input type='submit' name='continue' value='Yes, Delete $targetListname_disp' />";
} elseif(!empty($session->groups["ADMINISTRATORS"]) or
	 ($existing_ownergroup !== '' and
	  !empty($session->groups[$existing_ownergroup]))) {
  echo "\n        <input type='submit' name='update' value='Edit This List' />\n";
  echo "        <input type='submit' name='delete' value='Delete This List' />\n      ";
}
echo "</td>\n";
echo "    </tr>\n";

echo "  </table>\n";
echo "</form>\n";

sdsIncludeFooter();
