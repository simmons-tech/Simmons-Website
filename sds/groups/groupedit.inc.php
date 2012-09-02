<?php
if(realpath($_SERVER['SCRIPT_FILENAME']) === realpath(__FILE__))
  exit;
require_once(dirname(__FILE__) . "/../sds.php");

################################
## Returns appropriate javascript for the header.
## This value should be passed to sdsIncludeHeader, eg:
##   sdsIncludeHeader("My Title", "My Title", groupedit_head());
function groupedit_head() {
  return <<<EOF
  <script type="text/javascript">
    <!-- Script hiding
    function groupedit_users(usersfield_id,w,h) {
      groupedit_general('usersfield_id', usersfield_id, w, h);
    }

    function groupedit_groups(groupsfield_id,w,h) {
      groupedit_general('groupsfield_id', groupsfield_id, w, h);
    }

    function groupedit_general(field_type, field_id,w,h) {
      var PopupWindow=null;
      settings='width='+ w + ',height='+ h + ',location=no,directories=no, ' +
	' menubar=no,toolbar=yes,status=no,scrollbars=yes,resizable=yes,' +
	' dependent=yes';
      PopupWindow=window.open('groupedit_popup.php?' + field_type + '='
			      + field_id,'groupedit',settings);
      PopupWindow.focus();
    }
    // end script hiding -->
  </script> 
EOF;
}

#################################
## Returns HTML for showing poping up a users field editor
## usage:
##   echo "<input type='text' name='myname' id='myid'>";
##   echo groupedit_user('myid');
function groupedit_users($usersfield_id) {
  return "<a href=\"javascript:groupedit_users('$usersfield_id', 600, 600)\">add users</a>";
}

#################################
## Returns HTML for showing poping up a groups field editor
## usage:
##   echo "<input type='text' name='myname' id='myid'>";
##   echo groupedit_groups('myid');
function groupedit_groups($groupsfield_id) {
  return "<a href=\"javascript:groupedit_groups('$groupsfield_id', 600, 600)\">add groups</a>";
}

#################################
## Build adhoc group
##
## $preferredName is used for editing a group; it keeps the old name rather than creating a new one.
## $users is an array of usernames (prevalidated, no dups)
## $groups is an array of usernames (prevalidated, no dups)
## $useSpecificer is a string used in building the group name.  Example: "mailman:mynewmailinglist:members"
##
## This function actually creates the group in the database
##
## Returns the name of the group
function groupedit_build_group($preferredName,$users,$groups,
			       $hosts_allow='%',$adhoc=false,
			       $adhocUseSpecifier='unspecifiedUse',
			       $usetrans=true) {

## if we only include one group, then there's no need for an adhoc group #'
  if (count($users)==0 && count($groups)==1 && $adhoc) {
    reset($groups);
    return current($groups);
  }

  if ($adhoc) {
    if (strlen($preferredName) == 0) {
      $groupname = "adhoc:" . $adhocUseSpecifier . ":" . date("Ymd.Hms");
    } else {
      $query =
	"SELECT 1 FROM sds_groups WHERE active AND groupname='".
	pg_escape_string($preferredName)."'";
      $result = sdsQuery($query);
      if(!$result) {
	contactTech("Internal error &mdash; couldn't make name for group",
		    false);
	return null;
      }
      if (pg_num_rows($result) == 0) {
	$groupname = $preferredName;
      } else {
	$data = pg_fetch_object($result);
	if ($data->adhoc === 't') {
	  $groupname = $preferredName;
	} else {
	  $groupname = "adhoc:" . $adhocUseSpecifier . ":" . date("Ymd.Hms");
	}
      }
      pg_free_result($result);
    }
  } else {
    if (strlen($preferredName) > 0) {
      $groupname = $preferredName;
    } else {
      contactTech("Internal error &mdash; couldn't make name for group",false);
      return null;
    }
  } 

  $groupname_esc = pg_escape_string($groupname);
  $query = "SELECT active FROM sds_groups WHERE groupname='$groupname_esc'";
  $result = sdsQuery($query);
  if(!$result) {
    contactTech("Internal error &mdash; couldn't make name for group",false);
    return null;
  }

  if($usetrans) {
    $transres = sdsQuery("BEGIN");
    if(!$transres) {
      contactTech("Could not start transaction",false);
      return null;
    }
    pg_free_result($transres);
  }

  if (pg_num_rows($result) == 0) {
    // create new group
    $adhocVal = ($adhoc ? 'true' : 'false');
    $query = "INSERT INTO sds_groups (groupname, adhoc) VALUES ('$groupname_esc', '$adhocVal')";
    $createresult = sdsQuery($query);
    if(!$createresult) {
      contactTech("Internal error &mdash; couldn't make name for group",false);
      if($usetrans and !sdsQuery("ROLLBACK"))
	contactTech("Could not rollback",false);
      return null;
    }
    pg_free_result($createresult);
  } else {
    $activerecord = pg_fetch_array($result);
    if($activerecord['active'] !== 't') {
      // reactivate the group
      $adhocVal = ($adhoc ? 'true' : 'false');
      $query = "UPDATE sds_groups SET adhoc=$adhocVal,active=true WHERE groupname='$groupname_esc'";
      $createresult = sdsQuery($query);
      if(!$createresult) {
	contactTech("Internal error &mdash; couldn't make name for group",
		    false);
	if($usetrans and !sdsQuery("ROLLBACK"))
	  conatctTech("Could not rollback",false);
	return null;
      }
    pg_free_result($createresult);
    }
  }
  pg_free_result($result);

  // delete any remnants
  $query = <<<ENDQUERY
DELETE FROM sds_groups_in_groups WHERE supergroup='$groupname_esc';
DELETE FROM sds_users_in_groups WHERE groupname='$groupname_esc';
ENDQUERY;
  $deleteresult = sdsQuery($query);
  if(!$deleteresult) {
    contactTech("Internal error &mdash; couldn't clear group", false);
    if($usetrans and !sdsQuery("ROLLBACK"))
      conatctTech("Could not rollback",false);
    return null;
  }
  pg_free_result($deleteresult);

  // insert new values
  $hosts_allow_esc = pg_escape_string($hosts_allow);
  foreach($users as $username) {
    $username_esc = pg_escape_string($username);
    $query = "INSERT INTO sds_users_in_groups (username,groupname,hosts_allow)"
      . " VALUES ('$username_esc', '$groupname_esc', '$hosts_allow_esc')";
    $addresult = sdsQuery($query);
    if(!$addresult) {
      contactTech("Internal error &mdash; couldn't add to group",false);
      if($usetrans and !sdsQuery("ROLLBACK"))
	conatctTech("Could not rollback",false);
      return null;
    }
    pg_free_result($addresult);
  }

  foreach($groups as $subgroup) {
    $subgroup_esc = pg_escape_string($subgroup);
    $query =
      "INSERT INTO sds_groups_in_groups (subgroup,supergroup,hosts_allow) "
      . "VALUES ('$subgroup_esc', '$groupname_esc', '$hosts_allow_esc')";
    $addresult = sdsQuery($query);
    if(!$addresult) {
      contactTech("Internal error &mdash; couldn't add to group",false);
      if($usetrans and !sdsQuery("ROLLBACK"))
	conatctTech("Could not rollback",false);
      return null;
    }
    pg_free_result($addresult);
  }

  if($usetrans) {
    $transres = sdsQuery("COMMIT");
    if(!$transres) {
      contactTech("Could not commit",false);
      if(!sdsQuery("ROLLBACK"))
	conatctTech("Could not rollback",false);
      return null;
    }
    pg_free_result($transres);
  }

  return $groupname;
}

#################################
## Convert a string-list of groups to an array of groups
function groupedit_str2groups($groupstring, $glue=',' , &$errors) {
  $groupstring = trim($groupstring);
  if (strlen($groupstring)==0) return array();
  $candidates = explode($glue, $groupstring);
    
  $groups = array();
  foreach($candidates as $candidate) {
    $candidate = trim($candidate);
    if (in_array($candidate, $groups)) continue;

    $query = "SELECT 1 FROM sds_groups_public WHERE groupname='".
      pg_escape_string($candidate)."'";
    $result = sdsQuery($query);
    if(!$result)
      contactTech("Can't query groups");
    if (pg_num_rows($result) == 0) {
      $errors[] = $candidate;
      pg_free_result($result);
      continue;
    }
    pg_free_result($result);

    $groups[] = $candidate;
  }

  if (count($errors) > 0) {
    return false;
  }

  return $groups;
}


#################################
## Convert a string-list of usernames to an array of usernames
function groupedit_str2users($userstring, $glue=',' , &$errors) {
  $userstring = trim($userstring);
  if (strlen($userstring)==0) return array();
  $candidates = explode($glue, $userstring);

  $users = array();
  foreach($candidates as $candidate) {
    $candidate = trim($candidate);
    if (in_array($candidate, $users)) continue;

    // only selects active users 
    $query = "SELECT 1 FROM sds_users WHERE username='".
      pg_escape_string($candidate)."'";
    $result = sdsQuery($query);
    if(!$result)
      contactTech("Can't query users");
    if (pg_num_rows($result) == 0) {
      $errors[] = $candidate;
      pg_free_result($result);
      continue;
    }
    pg_free_result($result);

    $users[] = $candidate;
  }

  if (count($errors) > 0) {
    return false;
  }

  return $users;
}

###############################
## Convert a adhoc groupname to a an array
## ('users' => usernamestring, 'groups' => groupnamestring)
function groupedit_group2str($groupname, $glue=',' ) {

  $groupname_esc = pg_escape_string($groupname);
  $query = "SELECT adhoc FROM sds_groups WHERE groupname='$groupname_esc'";
  $result = sdsQuery($query);
  if(!$result)
    contactTech("Could not search groups");
  if (pg_num_rows($result) == 0) {
    return false;
  }
  $row = pg_fetch_object($result);

  if ($row->adhoc === 'f') {
    return array('users' => '', 'groups'=>$groupname);
  }
  pg_free_result($result);

  $query = "SELECT subgroup FROM sds_groups_in_groups WHERE supergroup='$groupname_esc' ORDER BY subgroup";
  $result = sdsQuery($query);
  if(!$result)
    contactTech("Could not search subgroups");
  $groupsArray = array();
  while($row = pg_fetch_object($result)) {
    $groupsArray[] = $row->subgroup;
  }
  pg_free_result($result);
  $groups = join(", ", $groupsArray);

  $query = "SELECT username FROM sds_users_in_groups WHERE groupname='$groupname_esc' ORDER BY username";
  $result = sdsQuery($query);
  if(!$result)
    contactTech("Could not search members");
  $usersArray = array();
  while($row = pg_fetch_object($result)) {
    $usersArray[] = $row->username;
  }
  pg_free_result($result);
  $users = join(", ", $usersArray);

  return array('users' => $users, 'groups' => $groups);
}
