<?php
if(realpath($_SERVER['SCRIPT_FILENAME']) === realpath(__FILE__))
  exit;
# file containing encrypted password for lounge lists
$lounge_pass_file = '/var/www/sds/lounge_password';

# messages to be sent on lounge signups/removals
$create_subject = "[SimmonsDB] lounge-^lounge^: Students Joining";
$create_message = <<<EOM
Dear %recipient%,

This is an automated message.  According to the SimmonsDB, the following
students have just JOINED lounge-^lounge^:

%changes%

      Thanks,
      Simmons Tech
EOM;
$remove_subject = "[SimmonsDB] lounge-^lounge^: Students Leaving";
$remove_message = <<<EOM
Dear %recipient%,

This is an automated message.  According to the SimmonsDB, the following
students have just LEFT lounge-^lounge^:

%changes%

      Thanks,
      Simmons Tech
EOM;

$warnings_displayed = false;

# print an error message with header/footer and exit
function display_error($message,$contact = false) {
  global $warnings_displayed;
  if(!$warnings_displayed)
    sdsIncludeHeader("Lounge Management Error");
  echo '<p class="error">',$message,"</p>\n";
  if($contact)
    echo "<p>Please contact <a href='mailto:simmons-tech@mit.edu'>simmons-tech@mit.edu</a></p>\n";
  sdsIncludeFooter();
  exit;
}

# print an error message with header/footer without exiting
function display_warning($message,$contact = false) {
  global $warnings_displayed;
  if(!$warnings_displayed)
    sdsIncludeHeader("Lounge Management Error");
  echo '<p class="error">',$message,"</p>\n";
  if($contact)
    echo "<p>Please contact <a href='mailto:simmons-tech@mit.edu'>simmons-tech@mit.edu</a></p>\n";
  $warnings_displayed = true;
}

# redirect back to the main lounge page, assuming there were no warnings
function lounges_done($fragment = "") {
  global $warnings_displayed;
  if($warnings_displayed) {
    echo '<p>Return to <a href="',SDS_BASE_URL,sdsLink("lounges/"),$fragment,
      "\">Lounge Management</a></p>\n";
    sdsIncludeFooter();
  } else {
    header("Location: " . SDS_BASE_URL . sdsLink("lounges/") . $fragment);
  }
  exit;
}

# check that an entered contact is in the directory and active
function verify_contact($contact) {
  $query = "SELECT 1 FROM active_directory WHERE username='" .
    pg_escape_string($contact) . "'";
  $result = sdsQuery($query);
  if(!$result)
    contactTech("Could not search users");
  $answer = (pg_num_rows($result) == 1);
  pg_free_result($result);
  return $answer;
}

# create groups and mailing lists for a lounge
# does NOT use transactions, as it is intended for this to be part of a larger
# process
function create_groups($lounge) {
  require_once(dirname(__FILE__) . '/../groups/groupedit.inc.php');
  $lounge_esc = pg_escape_string($lounge);

  # create the group
  $query = "SELECT active FROM sds_groups WHERE groupname='lounge-$lounge_esc'";
  $result = sdsQuery($query);
  if(!$result) {
    contactTech("Could not search groups",false);
    return null;
  }
  $groupexists = (pg_num_rows($result) != 0);
  $groupactive = 0;
  if($groupexists) {
    $groupactiverecord = pg_fetch_array($result);
    $groupactive = ($groupactiverecord['active'] === 't');
  }
  pg_free_result($result);
  if($groupactive) {
    display_warning("Group lounge-" . htmlspecialchars($lounge) .
		    " already exists. Skipping...");
  } else {
    if($groupexists) {
      $query = "UPDATE sds_groups SET description='Lounge group',active=true WHERE groupname='lounge-$lounge_esc'";
    } else {
      $query = "INSERT INTO sds_groups (groupname,description) VALUES ('lounge-$lounge_esc','Lounge group')";
    }
    $result = sdsQuery($query);
    if(!$result or pg_affected_rows($result) != 1) {
      display_warning("Creation of group for lounge " .
		      htmlspecialchars($lounge) . " failed.",true);
      return null;
    }
    pg_free_result($result);
  }

  # automate the group
  $query = "SELECT 1 FROM sds_automated_groups WHERE groupname='lounge-$lounge_esc'";
  $result = sdsQuery($query);
  if(!$result) {
    contactTech("Could not check group automation",false);
    return null;
  }

  $groupautomated = (pg_num_rows($result) != 0);
  pg_free_result($result);
  if($groupautomated) {
    display_warning("Group lounge-" . htmlspecialchars($lounge) .
		    " is already automated. Skipping...");
  } else {
    $query =
      "INSERT INTO sds_automated_groups (groupname,sql) VALUES ('lounge-" .
      $lounge_esc . "','" .
      pg_escape_string("SELECT username FROM active_directory WHERE lounge='lounge-$lounge_esc'") . "')";
    $result = sdsQuery($query);
    if(!$result or pg_affected_rows($result) != 1) {
      display_warning("Automation of group for lounge " .
		      htmlspecialchars($lounge) . " failed.",true);
      return null;
    }
    pg_free_result($result);
  }

  # create the mailing list
  $query = "SELECT deleted FROM mailman_lists WHERE listname='lounge-$lounge_esc'";
  $result = sdsQuery($query);
  if(!$result) {
    contactTech("Could not check mailing lists",false);
    return null;
  }
  $listexists = (pg_num_rows($result) != 0);
  $listactive = 0;
  if($listexists) {
    $deletedrecord = pg_fetch_array($result);
    $listactive = ($deletedrecord['deleted'] !== 't');
  }
  pg_free_result($result);

  $query = "SELECT 1 FROM mailman_aliases WHERE alias='lounge-$lounge_esc'";
  $result = sdsQuery($query);
  if(!$result) {
    contactTech("Could not search aliases",false);
    return null;
  }
  $aliasexists = (pg_num_rows($result) != 0);
  pg_free_result($result);

  if($listactive) {
    display_warning("Mailing list lounge-" . htmlspecialchars($lounge) .
		    " already exists. Skipping...");
  } elseif($aliasexists) {
    display_warning("Mailing list lounge-" . htmlspecialchars($lounge) .
		    " is an alias for another list. Skipping...",true);
  } else {
    # this just returns 'lounge-whatever' at the moment, but should be
    #  consistant with other mailing list creation pages (and it could
    # change eventually)
    $mailman_groupname =
      groupedit_build_group("",array(),array("lounge-$lounge_esc"),
			    '127.0.0.1', true,
			    "mailman:members:lounge-$lounge_esc",false);
    $mailman_ownergroup =
      groupedit_build_group("",array(),array("ADMINISTRATORS"),'%', true,
			    "mailman:owners:lounge-$lounge_esc",false);

    if(!$mailman_groupname or !$mailman_ownergroup)
      return null;

    global $lounge_pass_file;
    $encrypted_password = file_get_contents($lounge_pass_file);
    if(!$encrypted_password) {
      display_warning("Can't find mailing list password information.",true);
      return null;
    }
    $encrypted_password = pg_escape_string($encrypted_password);

    global $session;
    $username_esc = pg_escape_string($session->username);
    if($listexists) {
      $query = <<<ENDQUERY
UPDATE mailman_lists
SET description='lounge-$lounge_esc',subject_prefix='[Lounge]',
    groupname='$mailman_groupname',ownergroup='$mailman_ownergroup',
    private=true,moderated=false,mandatory=false,
    password='$encrypted_password',creator='$username_esc',deleted=false
WHERE listname='lounge-$lounge_esc'
ENDQUERY;
    } else {
      $query = <<<ENDQUERY
INSERT INTO mailman_lists
       (listname,            description,         subject_prefix,
        groupname,           ownergroup,           private,moderated,mandatory,
        password,             creator)
VALUES ('lounge-$lounge_esc','lounge-$lounge_esc','[Lounge]',
        '$mailman_groupname','$mailman_ownergroup',true,   false,    false,
        '$encrypted_password','$username_esc')
ENDQUERY;
    }

    $result = sdsQuery($query);
    if(!$result or pg_affected_rows($result) != 1) {
      display_warning("Creation of mailing list lounge-" .
		      htmlspecialchars($lounge) . " failed.",true);
      return null;
    }
    pg_free_result($result);
  }

  # add to all-lounges
  $query = "SELECT 1 FROM sds_groups_in_groups WHERE subgroup='lounge-$lounge_esc' AND supergroup='all-lounges'";
  $result = sdsQuery($query);
  if(!$result) {
    contactTech("Could not check supergroups",false);
    return null;
  }
  $inall = (pg_num_rows($result) != 0);
  pg_free_result($result);
  if($inall) {
    display_warning("Group lounge-" . htmlspecialchars($lounge) .
		    " is already in all-lounges group. Skipping...");
  } else {
    $query =
      "INSERT INTO sds_groups_in_groups (supergroup,subgroup) VALUES ('all-lounges','lounge-$lounge_esc')";
    $result = sdsQuery($query);
    if(!$result or pg_affected_rows($result) != 1) {
      display_warning("Adding lounge-" . htmlspecialchars($lounge) .
		      " to all-lounges failed.",true);
      return null;
    }
    pg_free_result($result);
  }

  # add notifications
  $query = "SELECT 1 FROM sds_group_notifications WHERE groupname = 'lounge-$lounge_esc' AND changetype = 'removed' AND recipient_groupname = 'SIMMONS-LOUNGES'";
  $result = sdsQuery($query);
  if(!$result) {
    contactTech("Could not check group notifications",false);
    return null;
  }
  $removenote = (pg_num_rows($result) != 0);
  pg_free_result($result);
  if($removenote) {
    display_warning("Group lounge-" . htmlspecialchars($lounge) .
		    " already has removal notifications. Skipping...");
  } else {
    global $remove_subject;
    global $remove_message;
    $subject = str_replace('^lounge^',$lounge_esc,$remove_subject);
    $message = str_replace('^lounge^',$lounge_esc,$remove_message);
    $query = <<<ENDQUERY
INSERT INTO sds_group_notifications
       (groupname,           changetype,recipient_groupname,mail_message,
        mail_subject)
VALUES ('lounge-$lounge_esc','removed', 'SIMMONS-LOUNGES', '$message',
        '$subject')
ENDQUERY;
    $result = sdsQuery($query);
    if(!$result or pg_affected_rows($result) != 1) {
      display_warning("Adding lounge-" . htmlspecialchars($lounge) .
		      " removal notifications failed.",true);
      return null;
    }
    pg_free_result($result);
  }

  $query = "SELECT 1 FROM sds_group_notifications WHERE groupname = 'lounge-$lounge_esc' AND changetype = 'created' AND recipient_groupname = 'SIMMONS-LOUNGES'";
  $result = sdsQuery($query);
  if(!$result) {
    contactTech("Could not check group notifications",false);
    return null;
  }
  $createnote = (pg_num_rows($result) != 0);
  pg_free_result($result);
  if($createnote) {
    display_warning("Group lounge-" . htmlspecialchars($lounge) .
		    " already has addition notifications. Skipping...");
  } else {
    global $create_subject;
    global $create_message;
    $subject = str_replace('^lounge^',$lounge_esc,$create_subject);
    $message = str_replace('^lounge^',$lounge_esc,$create_message);
    $query = <<<ENDQUERY
INSERT INTO sds_group_notifications
       (groupname,           changetype,recipient_groupname,mail_message,
        mail_subject)
VALUES ('lounge-$lounge_esc','created', 'SIMMONS-LOUNGES', '$message',
        '$subject')
ENDQUERY;
    $result = sdsQuery($query);
    if(!$result or pg_affected_rows($result) != 1) {
      display_warning("Adding lounge-" . htmlspecialchars($lounge) .
		      " addition notifications failed.",true);
      return null;
    }
    pg_free_result($result);
  }
  return true;
}

# remove groups and mailing lists for a lounge
function remove_groups($lounge) {
  require_once(dirname(__FILE__) . '/../groups/groupedit.inc.php');
  $lounge_esc = pg_escape_string($lounge);

  # remove members
  $query = "DELETE FROM sds_users_in_groups WHERE groupname='lounge-$lounge_esc'";
  $result = sdsQuery($query);
  if(!$result) {
    display_warning("Removing lounge-" . htmlspecialchars($lounge) .
		    " members failed (uig). Attempting to continue...", true);
  } else {
    pg_free_result($result);
  }
  $query = "UPDATE directory SET lounge=null,loungevalue=null WHERE lounge='lounge-$lounge_esc'";
  $result = sdsQuery($query);
  if(!$result) {
    display_warning("Removing lounge-" . htmlspecialchars($lounge) .
		    " members failed (dir). Attempting to continue...", true);
  } else {
    pg_free_result($result);
  }

  # remove notifications
  $query =
    "DELETE FROM sds_group_notifications WHERE groupname='lounge-$lounge_esc'";
  $result = sdsQuery($query);
  if(!$result or pg_affected_rows($result) != 2)
    display_warning("Removing lounge-" . htmlspecialchars($lounge) .
		    " notifications failed. Attempting to continue...", true);
  if($result) pg_free_result($result);

  # remove from all-lounges
  $query =
    "DELETE FROM sds_groups_in_groups WHERE supergroup='all-lounges' AND subgroup='lounge-$lounge_esc'";
  $result = sdsQuery($query);
  if(!$result or pg_affected_rows($result) != 1)
    display_warning("Removing lounge-" . htmlspecialchars($lounge) .
		    " from all-lounges failed. Attempting to continue...",
		    true);
  if($result) pg_free_result($result);

  # "delete" the mailing list
  $query = "UPDATE mailman_lists SET groupname=null,ownergroup=null,deleted=true WHERE listname='lounge-$lounge_esc'";
  $result = sdsQuery($query);
  if(!$result or pg_affected_rows($result) != 1)
    display_warning("Removal of mailing list for lounge-" .
		    htmlspecialchars($lounge) .
		    " failed. Attempting to continue...",true);
  if($result) pg_free_result($result);

  # unautomate the group
  $query =
    "DELETE FROM sds_automated_groups WHERE groupname='lounge-$lounge_esc'";
  $result = sdsQuery($query);
  if(!$result or pg_affected_rows($result) != 1)
    display_warning("Unautomation of group for lounge-" .
		    htmlspecialchars($lounge) .
		    " failed. Attempting to continue...",true);
  if($result) pg_free_result($result);

  # finally, delete the group
  $query = "UPDATE sds_groups SET active=false WHERE groupname='lounge-$lounge_esc'";
  $result = sdsQuery($query);
  if(!$result or pg_affected_rows($result) != 1)
    display_warning("Deletion of group for lounge-" .
		    htmlspecialchars($lounge) . " failed.",true);
  if($result) pg_free_result($result);
  return true;
}
