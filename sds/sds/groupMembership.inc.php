<?php 
if(realpath($_SERVER['SCRIPT_FILENAME']) === realpath(__FILE__))
  exit;
require_once(dirname(__FILE__) . "/../sds.php");

// ================== FUNCTIONS =========================
// Takes two hosts_allows specifications (eg, '18.96.%' and '%')
// and returns the more specific of the two.
function chooseMostSpecific($a, $b) {
  $aRegExp = preg_replace('/%/', '.*', preg_quote($a));
  $bRegExp = preg_replace('/%/', '.*', preg_quote($b));
  if(preg_match("/$aRegExp/", $b)) return $b;
  if(preg_match("/$bRegExp/", $a)) return $a;
  return 'XXXX';
}

// Takes two hosts_allows specifications (eg, '18.96.%' and '%')
// and returns the more general of the two.
function chooseMostGeneral($a, $b) {
  if($a === 'XXXX') return $b;
  if($b === 'XXXX') return $a;
  $aRegExp = preg_replace('/%/', '.*', preg_quote($a));
  $bRegExp = preg_replace('/%/', '.*', preg_quote($b));
  if(preg_match("/$aRegExp/", $b)) return $a;
  if(preg_match("/$bRegExp/", $a)) return $b;
  return 'XXXX';
}

// this function adds a new entry to the cache, if a more general entry
// does not already exist.  
//
// It does NOT remove existing less-general entries; such an occurrence would 
// be rare and still correct, just somewhat less efficient.  It's not worth
// the code complexity to try to deal with it.  
function addToCache($username, $groupname, $proposedHostsAllow) {

  if($proposedHostsAllow === 'XXXX') return false;

  $username_esc = pg_escape_string($username);
  $groupname_esc = pg_escape_string($groupname);
  $hostsallow_esc = pg_escape_string($proposedHostsAllow);

  // find any records already in the cache
  $query = "SELECT hosts_allow FROM sds_group_membership_cache WHERE username='$username_esc' AND groupname='$groupname_esc'";
  $result = sdsQuery($query);
  if(!$result) {
    contactTech("Could not search membership cache",false);
    return null;
  }

  // see if any records already in the cache are more general than this one.
  while($data = pg_fetch_object($result)) {
    $existingHostsAllow = $data->hosts_allow;

    $mostGeneral = chooseMostGeneral($proposedHostsAllow, $existingHostsAllow);
    if ($mostGeneral === $existingHostsAllow) {
      // an existing record was more general.  We don't need to add anything
      // to the cache.
      return false;
    }
  }
  pg_free_result($result);

  // we didn't find a more general record.  Let's add this one.
  $query = "INSERT INTO sds_group_membership_cache (username, groupname, hosts_allow) VALUES ('$username_esc', '$groupname_esc', '$hostsallow_esc')";
  $result = sdsQuery($query);
  if(!$result or pg_affected_rows($result) != 1) {
    contactTech("Could not add cache entry",false);
    return null;
  }
  pg_free_result($result);
  return true;
}

// $query will return username,groupname of the users changed, ordered by group
// $type will be either "created" or "removed", depending on the change type.
function notify_membership_change($query, $type) {

  $result = sdsQuery($query);
  if(!$result) {
    contactTech("Could not find membership changes",false);
    return null;
  }

  $currentgroup = "";
  while($data = pg_fetch_object($result)) {
    if($data->groupname !== $currentgroup) {
      // new group!

      if($currentgroup !== '') {
	// there's an existing group.  dispatch notifications.
	if(!notify_membership_change_helper($members, $currentgroup, $type))
	  return null;
      }

      $members = array();
      $currentgroup = $data->groupname;
    }

    $members[] = $data->username;
  }
  if($currentgroup !== '') {
    if(!notify_membership_change_helper($members, $currentgroup, $type))
      return null;
  }
  return true;
}

// $usernames is an array of usernames that have changed
// $groupname is the group name that changed
// $type is either "created" or "removed", depending on the change type
function notify_membership_change_helper($usernames, $groupname, $type) {

  if (SDB_DATABASE !== "sdb") {
    // we're in somebody's sandbox.  Don't confuse the outsiders with
    // notification emails.
    // echo "<br />If we were in sdb instead of ", SDB_DATABASE, 
    //   ", I'd be sending an email right now.";
    return true;
  }

  $groupname_esc = pg_escape_string($groupname);
  $type_esc = pg_escape_string($type);

  $query = "SELECT mail_subject,mail_message,recipient_groupname FROM sds_group_notifications WHERE groupname='$groupname_esc' AND changetype='$type_esc'";
  $result = sdsQuery($query);
  if(!$result) {
    contactTech("Could not read notifications",false);
    return null;
  }
  while($data = pg_fetch_object($result)) {

    $mail_message = $data->mail_message;
    $recipient_groupname_esc = pg_escape_string($data->recipient_groupname);

    $recip_query = <<<ENDQUERY
SELECT email,COALESCE(title||' ','')||firstname||' '||lastname AS name
FROM sds_group_membership_cache JOIN directory USING (username)
WHERE groupname='$recipient_groupname_esc'
ENDQUERY;
    $recip_result = sdsQuery($recip_query);
    if(!$recip_result) {
      contactTech("Could not search group membership",false);
      return null;
    }
    if(pg_num_rows($recip_result) > 0) {
      $recipient_emails = array();
      $recipient_names = array();

      while($recip_data = pg_fetch_object($recip_result)) {
	$recipient_emails[] = $recip_data->email;
	$recipient_names[] = $recip_data->name;
      }

      $mail_to = implode(", ", $recipient_emails);
      $mail_subject = $data->mail_subject;

      $mail_recips = implode(" & ", $recipient_names);
      $mail_message = str_replace("%recipient%", $mail_recips, $mail_message);
    } else {
      $mail_to = "simmons-tech@mit.edu";
      $mail_subject =
	"SimmonsDB failed delivery of a group notification message.";
      $mail_message = "The SimmonsDB tried to send the below email to the group {$data->recipient_groupname}, but couldn't find anyone in that group.\n\n"
                      . "SUBJECT: $mail_subject\n\n"
                      . $mail_message;
    }
    pg_free_result($recip_result);

    $changes_array = array();
    foreach($usernames as $username) {
      $username_esc = pg_escape_string($username);
      $username_query = "SELECT COALESCE(title||' ','')||firstname||' '||lastname AS name,room FROM directory WHERE username='$username_esc'";
      $username_result = sdsQuery($username_query);
      if(!$username_result) {
	contactTech("Could not fetch users",false);
	return null;
      }
      if(pg_num_rows($username_result) == 1) {
	$username_data = pg_fetch_object($username_result);
        $changes_array[] = "$username_data->name (username $username, room {$username_data->room})";
      } else {
	$changes_array[] = $username;
      }
      $changes = implode("\n", $changes_array);

      $mail_message = str_replace("%changes%", $changes, $mail_message);

      if(!mail($mail_to, $mail_subject, $mail_message,
	       "From: Simmons DB <simmons-tech@mit.edu>\r\n" .
	       "Reply-To: simmons-tech@mit.edu"))
	contactTech("Could not send notification message",false);
    }
  }
  pg_free_result($result);
  return true;
}


// Refresh the Group Membership Cache
//
// if no groupname is given, then a complete refresh of the entire group
// membership cache is performed.  This should happen regularly, but it takes
// a little while so users might not want to wait for it.
//
// if $groupname is given, then only that group will be refreshed.
// In addition, only a shallow refresh is performed.  No members are removed,
// and indirectly included members may not get added (because subgroups won't
// get updated)
//
// if $transaction is true, the refresh is wrapped in a BEGIN/COMMIT pair.
function refreshGroupMembership($groupname="", $echoStatus=true,
				$transaction=true) {

  if(strlen($groupname)) {
    $grouprestrict = " WHERE groupname='" . pg_escape_string($groupname) . "'";
    $supergrouprestrict =
      " WHERE supergroup='" . pg_escape_string($groupname) . "'";
    $quick = true;
  } else {
    $grouprestrict = "";
    $supergrouprestrict = "";
    $quick = false;
  }

  // ----------
  // Clean up the membership cache database (or this might be slow).
  // Also, can't vacuum in a transaction
  if(!$quick and !$transaction) {
    $result = sdsQuery("VACUUM ANALYZE sds_group_membership_cache");
    if(!$result) {
      contactTech("Can't vacuum",false);
      return null;
    }
    pg_free_result($result);
  }
    
  // == BEGIN TRANSACTION ==
  if($transaction) {
    $result = sdsQuery("BEGIN");
    if(!$result) {
      contactTech("Could not start transaction",false);
      return null;
    }
    pg_free_result($result);
  }

  // make a copy of the membership cache, before changes
  $query = "CREATE TEMP TABLE old_sds_group_membership_cache AS SELECT * FROM sds_group_membership_cache";
  $result = sdsQuery($query);
  if(!$result) {
    contactTech("Could not copy cache",false);
    if($transaction and !sdsQuery("ROLLBACK"))
      contactTech("Could not rollback",false);
    return null;
  }
  pg_free_result($result);


  // ----------
  // Clear the existing cache
  if(!$quick) {
    $query = "DELETE FROM sds_group_membership_cache";
    $result = sdsQuery($query);
    if(!$result) {
      contactTech("Could not clear cache",false);
      if($transaction and !sdsQuery("ROLLBACK"))
	contactTech("Could not rollback",false);
      return null;
    }
    pg_free_result($result);
  }

  if($echoStatus) {
    $groupsProcessed = 0;
    $pass = 0;
    echo "<br />\n";
    echo "PASS $pass<br />\n";
    echo "users";
    flush();
  }

  // ---------
  // process direct membership (sds_users_in_groups)

  // select everything from users_in_groups, along with the users' native
  // hosts_allow from sds_users
  // sds_users only contains active users
  $query = <<<ENDQUERY
SELECT username, groupname, sds_users_in_groups.hosts_allow AS group_ha,
       sds_users.hosts_allow AS user_ha
FROM sds_users_in_groups JOIN sds_users USING (username) $grouprestrict
ENDQUERY;

  $result = sdsQuery($query);
  if(!$result) {
    contactTech("Could not search sds_users_in_groups",false);
    if($transaction and !sdsQuery("ROLLBACK"))
      contactTech("Could not rollback",false);
    return null;
  }
  while($data = pg_fetch_object($result)) {

    // intersect the hosts allow
    $hosts_allow = chooseMostSpecific($data->user_ha, $data->group_ha);
    if(addToCache($data->username, $data->groupname, $hosts_allow)===null)
      return null;

    if($echoStatus) {
      $groupsProcessed++;
      if($groupsProcessed % 10 == 0) {
	echo ($groupsProcessed);
      } else {
	echo ".";
      }
      flush();
    }
  }


  // ------------
  // keep cycling through automated membership and group_in_group membership
  // until we can't make any more additions
  do {
    if($echoStatus) {
      $groupsProcessed = 0;
      $pass++;
      echo "<br />\n";
      echo "PASS $pass<br />\n";
      echo "auto";
      flush();
    }

    $changesMade = false;
    // ---------------------
    // process automated membership

    // get the automated group definitions
    $query = "SELECT groupname,sql,hosts_allow FROM sds_automated_groups $grouprestrict";
    $agResult = sdsQuery($query);
    if(!$agResult) {
      contactTech("Could not process automated groups",false);
      if($transaction and !sdsQuery("ROLLBACK"))
	contactTech("Could not rollback",false);
      return null;
    }

    while($agData = pg_fetch_object($agResult)) {

      // get the users chosen by the automated group
      $agsResult = sdsQuery($agData->sql);
      if(!$agsResult) {
	contactTech("Could not run automation",false);
	if($transaction and !sdsQuery("ROLLBACK"))
	  contactTech("Could not rollback",false);
	return null;
      }
      while($agsData = pg_fetch_object($agsResult)) {

	// find the user's hosts_allow in the sds_users table, and see if its
	// more specific than what is permitted by the group definition
	$query = "SELECT hosts_allow FROM sds_users WHERE username='" .
	  pg_escape_string($agsData->username) . "'";
	$result = sdsQuery($query);
	if(!$result) {
	  contactTech("Could not search users",false);
	  if($transaction and !sdsQuery("ROLLBACK"))
	    contactTech("Could not rollback",false);
	  return null;
	}
	if(pg_num_rows($result) == 1) {
	  $data = pg_fetch_object($result);
	  $proposedHostsAllow =
	    chooseMostSpecific($data->hosts_allow, $agData->hosts_allow);

	  $ans = addToCache($agsData->username, $agData->groupname, 
			    $proposedHostsAllow);
	  if(!isset($ans))
	    return null;
	  $changesMade = $changesMade || $ans;
	}
	pg_free_result($result);
      }
      pg_free_result($agsResult);

      if ($echoStatus) {
	$groupsProcessed++;
	if($groupsProcessed % 10 == 0) {
	  echo ($groupsProcessed);
	} else {
	  echo ".";
	}
	flush();
      }
    }
    pg_free_result($agResult);

    // ---------------------
    // process groups_in_groups membership

    if ($echoStatus) {
      echo "groups";
      flush();
    }

    // get the gig definitions
    $query = "SELECT supergroup,subgroup,hosts_allow FROM sds_groups_in_groups $supergrouprestrict";
    $gigResult = sdsQuery($query);
    if(!$gigResult) {
      contactTech("Could not process sds_groups_in_groups",false);
      if($transaction and !sdsQuery("ROLLBACK"))
	contactTech("Could not rollback",false);
      return null;
    }
    while($gigData = pg_fetch_object($gigResult)) {

      // select all users known to be in the subgroiup
      $subquery = "SELECT username,hosts_allow FROM sds_group_membership_cache WHERE groupname='" . pg_escape_string($gigData->subgroup) . "'";
      $subResult = sdsQuery($subquery);
      while($subData = pg_fetch_object($subResult)) {
	$proposedHostsAllow =
	  chooseMostSpecific($subData->hosts_allow, $gigData->hosts_allow);
	$ans = addToCache($subData->username, $gigData->supergroup,
			  $proposedHostsAllow);
	if(!isset($ans))
	  return null;
	$changesMade = $changesMade || $ans;
      }
      pg_free_result($subResult);

      if($echoStatus) {
	$groupsProcessed++;
	if ($groupsProcessed % 10 == 0) {
	  echo ($groupsProcessed);
	} else {
	  echo ".";
	}
	flush();
      }
    }
    pg_free_result($gigResult);
  } while($changesMade and !$quick);  

  // compare the new cache with the old cache, issue change notifications
  $created_query = "SELECT username, groupname FROM sds_group_membership_cache EXCEPT SELECT username, groupname FROM old_sds_group_membership_cache ORDER BY groupname, username";
  if(!notify_membership_change($created_query, "created")) {
    if($transaction and !sdsQuery("ROLLBACK"))
      contactTech("Could not rollback",false);
    return null;
  }

  $removed_query = "SELECT username, groupname FROM old_sds_group_membership_cache EXCEPT SELECT username, groupname FROM sds_group_membership_cache ORDER BY groupname, username";
  if(!notify_membership_change($removed_query, "removed")) {
    if($transaction and !sdsQuery("ROLLBACK"))
      contactTech("Could not rollback",false);
    return null;
  }

  // get rid of the temp table
  $result = sdsQuery("DROP TABLE old_sds_group_membership_cache");
  if(!$result) {
    contactTech("Could not drop old cache",false);
    if($transaction and !sdsQuery("ROLLBACK"))
      contactTech("Could not rollback",false);
    return null;
  }
  pg_free_result($result);

  if($transaction) {
    $result = sdsQuery("COMMIT");
    if(!$result) {
      contactTech("Could not commit",false);
      if($transaction and !sdsQuery("ROLLBACK"))
	contactTech("Could not rollback",false);
      return null;
    }
    pg_free_result($result);
  }

  if(!$quick) {
    $result = sdsQuery("VACUUM ANALYZE sds_group_membership_cache");
    if(!$result) {
      contactTech("Can't vacuum",false);
      return null;
    }
    pg_free_result($result);
  }

  if($echoStatus) {
    echo "<br />\n";
  }

  return true;
}    


function cleanupAdhocGroups() {
  // first, let's find out where in the database adhoc groups might be used.
  $query = 'SELECT "table",group_field FROM sds_groups_adhoc_locations';
  $result = sdsQuery($query);
  if(!$result) {
    contactTech("Could not locate adhoc groups",false);
    return null;
  }

  // now we'll build the non-in-use query
  $query = "SELECT groupname FROM sds_groups WHERE adhoc";
  while($row = pg_fetch_object($result)) {
    $query .= "\nEXCEPT SELECT {$row->group_field} FROM {$row->table}";
  }
  pg_free_result($result);

  $result = sdsQuery($query);
  if(!$result) {
    contactTech("Could not find unused adhocs",false);
    return null;
  }
  while($row = pg_fetch_object($result)) {
    $groupname_esc = pg_escape_string($row->groupname);

    $query = <<<ENDQUERY
DELETE FROM sds_group_membership_cache WHERE groupname='$groupname_esc';
DELETE FROM sds_groups_in_groups WHERE supergroup='$groupname_esc';
DELETE FROM sds_users_in_groups WHERE groupname='$groupname_esc';
DELETE FROM sds_automated_groups WHERE groupname='$groupname_esc';
DELETE FROM sds_groups WHERE groupname='$groupname_esc';
ENDQUERY;

    $delresult = sdsQuery($query);
    if(!$delresult) {
      contactTech("Could not delete adhoc group",false);
      return null;
    }
    pg_free_result($delresult);
  }
  pg_free_result($result);
}
