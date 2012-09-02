<?php
require_once("../sds.php");

# require administrators
sdsRequireGroup("ADMINISTRATORS");

sdsIncludeHeader("SDB ACL Control Panel");

function userRemoveForm($userdisp,$groupdisp) {
?>
<form action="groupedit.php" method="post">
  <?php echo sdsForm() ?> 

  <input type="hidden" name="user" value="<?php echo $userdisp ?>" />
  <input type="hidden" name="group" value="<?php echo $groupdisp ?>" />
  <input type="hidden" name="action" value="remove" />
  <input type="submit" value="Remove from group" />
</form>
<p>Note that removals will not take effect until the next group refresh.</p>
<?php
}

function groupRemoveForm($subdisp,$superdisp,$what = 'subgroup') {
?>
<form action="groupedit_group.php" method="post">
  <?php echo sdsForm() ?> 
  <input type="hidden" name="subgroup" value="<?php echo $subdisp ?>" />
  <input type="hidden" name="supergroup" value="<?php echo $superdisp ?>" />
  <input type="hidden" name="action" value="remove" />
  <input type="submit" value="Remove <?php echo $what ?>" /><br />
</form>
<p>Note that removals will not take effect until the next group refresh.</p>
<?php
}

function userInfo($user_esc,$disp,$preamble = '') {
  $query = "SELECT active,hosts_allow FROM sds_users_all WHERE username='$user_esc'";
  $result = sdsQuery($query);
  if ($result and pg_num_rows($result) == 1) {
    $data = pg_fetch_object($result);
    pg_free_result($result);

    $fullname = sdsGetFullName($user_esc);
    if($fullname===$user_esc) { $fullname=''; }

    echo "      <h3>$preamble$disp",
      $fullname?" ($fullname)":'',":</h3>";
    echo "      <p>User is ", $data->active=='t'?
      'active'.($data->hosts_allow==='%'?'':' from addresses matching '.
		htmlspecialchars($data->hosts_allow))
      : 'inactive', "</p>";
    return true;
  }
  if($result) pg_free_result($result);
  return false;
}

function groupInfo($group_esc,$disp,$preamble = '') {
  $query = "SELECT description,adhoc,contact FROM sds_groups WHERE groupname='$group_esc' AND active";
  $result = sdsQuery($query);
  if($result) {
    if (pg_num_rows($result)==1) {
      $data = pg_fetch_object($result);
      pg_free_result($result);
      echo "      <h3>$preamble$disp</h3>\n";
      if($data->contact) {
	echo '      <p>Contact: <a href="mailto:',
	  htmlspecialchars($data->contact),'">',
	  htmlspecialchars($data->contact),"</a></p>\n";
      }
      if($data->description) { echo "      <p>$data->description</p>\n"; }

      $query = "SELECT sql,hosts_allow FROM sds_automated_groups WHERE groupname='$group_esc'";
      $autoresult = sdsQuery($query);
      if($autoresult) {
	if(pg_num_rows($autoresult)==1) {
	  $autodata = pg_fetch_array($autoresult);
	  echo "<p>Group SQL",
	    $autodata['hosts_allow']==='%'?'':' ('.
	    htmlspecialchars($autodata['hosts_allow']).')',
	    ":<br /><code>",htmlspecialchars($autodata['sql']),"</code></p>\n";
	}
	pg_free_result($autoresult);
      }

      global $adhoc;
      $adhoc = $data->adhoc;
      return true;
    }
    pg_free_result($result);
  }
  return false;
}

$user = getStringArg('user', $session->username);
$user_esc = pg_escape_string($user);
$user_disp = htmlspecialchars($user);

$usergroup = getStringArg('usergroup');
$usergroup_esc = pg_escape_string($usergroup);
$usergroup_disp = htmlspecialchars($usergroup);

$group = getStringArg('group', 'ADMINISTRATORS');
$group_esc = pg_escape_string($group);
$group_disp = htmlspecialchars($group);

$supergroup = getStringArg('supergroup');
$supergroup_esc = pg_escape_string($supergroup);
$supergroup_disp = htmlspecialchars($supergroup);

$groupuser = getStringArg('groupuser');
$groupuser_esc = pg_escape_string($groupuser);
$groupuser_disp = htmlspecialchars($groupuser);
?>
<table>
  <tr>
    <td valign="top">
      <h2>Active Users:</h2>
      <form name="activeusers" action="index.php" method="get">
        <?php echo sdsForm() ?>

        <input type="hidden" name="group" value="<?php echo $group_disp ?>" />
        <input type="hidden" name="supergroup" value="<?php echo $supergroup_disp ?>" />
        <input type="hidden" name="groupuser" value="<?php echo $groupuser_disp ?>" />
        <select size="16" name="user" onchange="activeusers.submit();">
<?php
$result = sdsQuery("SELECT username FROM sds_users ORDER BY username");
if($result) {
  while($data = pg_fetch_object($result)) {
    echo "<option",$data->username===$user?' selected="selected"':'',">",
      htmlspecialchars($data->username),"</option>\n";
  }
  pg_free_result($result);
}
?>
        </select>

        <noscript><input type="submit" value="Details" /></noscript>
      </form>
    </td>
    <td valign="top">
<?php

if(userInfo($user_esc,$user_disp,'Properties of user ')) {
?>

      <p>Manual Group Membership:
         (<a href="<?php echo sdsLink("addgroup.php","user=$user_disp") ?>">Add to Group</a>)</p>
      <form name="usergroupform" action="index.php" method="get">
        <?php echo sdsForm() ?>

        <input type="hidden" name="user" value="<?php echo $user_disp ?>" />
        <input type="hidden" name="group" value="<?php echo $group_disp ?>" />
        <input type="hidden" name="supergroup" value="<?php echo $supergroup_disp ?>" />
        <input type="hidden" name="groupuser" value="<?php echo $groupuser_disp ?>" />
        <select size="4" name="usergroup" onchange="usergroupform.submit();">
<?php
  $query = "SELECT groupname FROM sds_users_in_groups JOIN sds_groups_public USING (groupname) WHERE username='$user_esc' ORDER BY groupname";
  $result = sdsQuery($query);

  if($result) {
    while($data = pg_fetch_object($result)) {
      echo "<option",$usergroup===$data->groupname ?
	' selected="selected"':'',">",htmlspecialchars($data->groupname),
	"</option>\n";
    }
    pg_free_result($result);
  }
?>
        </select>
        <noscript><input type="submit" value="Details" /></noscript>
      </form>

      <p>Other Group Membership:</p>
      <form name="usergroupother" action="index.php" method="get">
        <?php echo sdsForm() ?>

        <input type="hidden" name="user" value="<?php echo $user_disp ?>" />
        <input type="hidden" name="group" value="<?php echo $group_disp ?>" />
        <input type="hidden" name="supergroup" value="<?php echo $supergroup_disp ?>" />
        <input type="hidden" name="groupuser" value="<?php echo $groupuser_disp ?>" />
        <select size="4" name="usergroup" onchange="usergroupother.submit();">
<?php
  $query = <<<ENDQUERY
SELECT groupname
FROM sds_group_membership_cache
     LEFT JOIN sds_users_in_groups USING (username,groupname)
     LEFT JOIN sds_groups USING (groupname)
WHERE username='$user_esc' AND active AND
      (sds_users_in_groups.hosts_allow IS NULL OR adhoc)
ORDER BY groupname
ENDQUERY;
  $result = sdsQuery($query);

  if($result) {
    while($data = pg_fetch_object($result)) {
      echo "<option",$usergroup===$data->groupname ? 
	' selected="selected"':'',">",htmlspecialchars($data->groupname),
	"</option>\n";
    }
    pg_free_result($result);
  }

?>
        </select>
        <noscript><input type="submit" value="Details" /></noscript>
      </form>
<?php
}
?>
    </td>
  </tr>
  <tr>
    <td valign="top">
      <h2>Inactive Users:</h2>
      <form name="inactiveusers" action="index.php" method="get">
        <?php echo sdsForm() ?>

        <input type="hidden" name="group" value="<?php echo $group_disp ?>" />
        <input type="hidden" name="supergroup" value="<?php echo $supergroup_disp ?>" />
        <input type="hidden" name="groupuser" value="<?php echo $groupuser_disp ?>" />
        <select size="6" name="user" onchange="inactiveusers.submit();">
<?php
$result = sdsQuery("SELECT username FROM sds_users_all WHERE NOT active ORDER BY username");
if($result) {
  while($data = pg_fetch_object($result)) {
    echo "<option",$data->username===$user?' selected="selected"':'',">",
      htmlspecialchars($data->username),"</option>\n";
  }
  pg_free_result($result);
}
?>
        </select>

        <noscript><input type="submit" value="Details" /></noscript>
      </form>
    </td>
    <td>
<?php
# group information
if($usergroup) {
  if(groupInfo($usergroup_esc,$usergroup_disp)) {

    $query = "SELECT 1 FROM sds_users_in_groups WHERE groupname='$usergroup_esc' AND username='$user_esc'";
    $manualresult = sdsQuery($query);
    $manualgroup = ($manualresult && pg_num_rows($manualresult));
    if($manualresult) pg_free_result($manualresult);

    $query = "SELECT hosts_allow FROM sds_group_membership_cache WHERE groupname='$usergroup_esc' AND username='$user_esc'";
    $hostsresult = sdsQuery($query);
    if($hostsresult and pg_num_rows($hostsresult)) {
      list($grouphosts) = pg_fetch_array($hostsresult);
    } else {
      $grouphosts = '"nothing??"';
    }
    if($hostsresult) pg_free_result($hostsresult);

    echo "      <p>$user_disp is ",
      $manualgroup?'manually':'automatically',
      " in this group",$grouphosts==='%'?
      '':" at addresses matching ".htmlspecialchars($grouphosts),"</p>\n";

    if($adhoc==='t') {
?>
      <p style="font-style: italic">This is an adhoc group.  You cannot
        manually remove a user from an adhoc group.  Usually, this represents
        a mailing list.  Use
        <a href="<?php echo sdsLink('../groups/view_mailing_lists.php') ?>">Mailing List management</a>
        to remove the user.
      </p>
<?php
    } elseif($manualgroup) {
      userRemoveForm($user_disp,$usergroup_disp);
    }
  }
}
?>
    </td>
  </tr>
</table>
<hr />
<h2 id="groupstart">Groups:</h2>
<form name="groupform" action="index.php#groupstart" method="get">
  <?php echo sdsForm() ?>

  <input type="hidden" name="user" value="<?php echo $user_disp ?>" />
  <input type="hidden" name="usergroup" value="<?php echo $usergroup_disp ?>" />
  <select size="6" name="group" onchange="groupform.submit()">
<?php
$result = sdsQuery("SELECT groupname FROM sds_groups WHERE active ORDER BY groupname");
if($result) {
  while($data = pg_fetch_object($result)) {
    echo "<option",$data->groupname===$group?' selected="selected"':'',
      ">",htmlspecialchars($data->groupname),"</option>\n";
  }
  pg_free_result($result);
}
?>
  </select>
</form>

<?php
if(groupInfo($group_esc,$group_disp,'Properties of group ')) {
  if($adhoc==='t') { echo "<p>Adhoc group</p>\n"; }
?>

<table>
  <tr>
    <td>
      <p>Supergroups:
<?php if($adhoc !== 't') { ?>
         (<a href="<?php echo sdsLink("addgroup_group.php","subgroup=$group_disp") ?>">Add Supergroup</a>)
<?php } ?>
      </p>
      <form name="supergroupform" action="index.php#groupstart" method="get">
        <?php echo sdsForm() ?>

        <input type="hidden" name="user" value="<?php echo $user_disp ?>" />
        <input type="hidden" name="usergroup" value="<?php echo $usergroup_disp ?>" />
        <input type="hidden" name="group" value="<?php echo $group_disp ?>" />
        <select size="4" name="supergroup" onchange="supergroupform.submit();">

<?php
  $query = "SELECT supergroup FROM sds_groups_in_groups WHERE subgroup='$group_esc' ORDER BY supergroup";
  $result = sdsQuery($query);

  if($result) {
    while($data = pg_fetch_object($result)) {
      echo "          <option",$supergroup===$data->supergroup ?
	' selected="selected"':'',">",htmlspecialchars($data->supergroup),
	"</option>\n";
    }
    pg_free_result($result);
  }
?>
        </select>
        <noscript><input type="submit" value="Details" /></noscript>
      </form>
    </td>

    <td>
      <p>Subgroups:
<?php if($adhoc !== 't') { ?>
         (<a href="<?php echo sdsLink("addgroup_group.php","supergroup=$group_disp") ?>">Add Subgroup</a>)
<?php } ?>
      </p>
      <form name="subgroupform" action="index.php#groupstart" method="get">
        <?php echo sdsForm() ?>

        <input type="hidden" name="user" value="<?php echo $user_disp ?>" />
        <input type="hidden" name="usergroup" value="<?php echo $usergroup_disp ?>" />
        <input type="hidden" name="group" value="<?php echo $group_disp ?>" />
        <select size="4" name="groupuser" onchange="subgroupform.submit();">
<?php
  $query = "SELECT subgroup FROM sds_groups_in_groups WHERE supergroup='$group_esc' ORDER BY subgroup";
  $result = sdsQuery($query);

  if($result) {
    while($data = pg_fetch_object($result)) {
      echo "          <option",$groupuser===$data->subgroup ? 
	' selected="selected"':'',">",htmlspecialchars($data->subgroup),
	"</option>\n";
    }
    pg_free_result($result);
  }
?>
        </select>
        <noscript><input type="submit" value="Details" /></noscript>
      </form>
    </td>
  </tr>

  <tr>
    <td>
      <p>Manual Members:
<?php if($adhoc !== 't') { ?>
         (<a href="<?php echo sdsLink("addgroup.php","group=$group_disp") ?>">Add Member</a>)
<?php } ?>
      </p>
      <form name="groupmemberform" action="index.php#groupstart" method="get">
        <?php echo sdsForm() ?>

        <input type="hidden" name="user" value="<?php echo $user_disp ?>" />
        <input type="hidden" name="usergroup" value="<?php echo $usergroup_disp ?>" />
        <input type="hidden" name="group" value="<?php echo $group_disp ?>" />
        <select size="4" name="groupuser" onchange="groupmemberform.submit();">
<?php
  $query = "SELECT username FROM sds_users_in_groups WHERE groupname='$group_esc' ORDER BY username";
  $result = sdsQuery($query);

  if($result) {
    while($data = pg_fetch_object($result)) {
      echo "          <option",$groupuser===$data->username ? 
	' selected="selected"':'',">",htmlspecialchars($data->username),
	"</option>\n";
    }
    pg_free_result($result);
  }
?>
        </select>
        <noscript><input type="submit" value="Details" /></noscript>
      </form>
    </td>
    <td>
      <p>Other Members:</p>
      <form name="groupuserother" action="index.php#groupstart" method="get">
        <?php echo sdsForm() ?>

        <input type="hidden" name="user" value="<?php echo $user_disp ?>" />
        <input type="hidden" name="usergroup" value="<?php echo $usergroup_disp ?>" />
        <input type="hidden" name="group" value="<?php echo $group_disp ?>" />
        <select size="4" name="groupuser" onchange="groupuserother.submit();">
<?php
  $query = <<<ENDQUERY
SELECT username
FROM sds_group_membership_cache
     LEFT JOIN sds_users_in_groups USING (username,groupname)
WHERE groupname='$group_esc' AND sds_users_in_groups.hosts_allow IS NULL
ORDER BY username
ENDQUERY;
  $result = sdsQuery($query);

  if($result) {
    while($data = pg_fetch_object($result)) {
      echo "          <option",$groupuser===$data->username ? 
	' selected="selected"':'',">",htmlspecialchars($data->username),
	"</option>\n";
    }
    pg_free_result($result);
  }
?>
        </select>
        <noscript><input type="submit" value="Details" /></noscript>
      </form>
    </td>
  </tr>
</table>
<?php

  if($groupuser) {
    if(userInfo($groupuser_esc,$groupuser_disp)) {

      $query = "SELECT 1 FROM sds_users_in_groups WHERE groupname='$group_esc' AND username='$groupuser_esc'";
      $manualresult = sdsQuery($query);
      $manualgroup = ($manualresult && pg_num_rows($manualresult));
      if($manualresult) pg_free_result($manualresult);

      $query = "SELECT hosts_allow FROM sds_group_membership_cache WHERE groupname='$group_esc' AND username='$groupuser_esc'";
      $hostsresult = sdsQuery($query);
      if($hostsresult and pg_num_rows($hostsresult)) {
	list($grouphosts) = pg_fetch_array($hostsresult);
      } else {
	$grouphosts = '"nothing??"';
      }
      if($hostsresult) pg_free_result($hostsresult);

      echo "<p>$groupuser_disp is ",
	$manualgroup?'manually':'automatically',
	" in $group_disp",$grouphosts==='%'?
	'':" at addresses matching ".htmlspecialchars($grouphosts),"</p>\n";

      if($adhoc==='t') {
?>
      <p style="font-style: italic">This is an adhoc group.  You cannot
        manually remove a user from an adhoc group.  Usually, this represents
        a mailing list.  Use
        <a href="<?php echo sdsLink('../groups/view_mailing_lists.php') ?>">Mailing List management</a>
        to remove the user.
      </p>
<?php
      } elseif($manualgroup) {
	userRemoveForm($groupuser_disp,$group_disp);
      }
    } else {
      $superadhoc = $adhoc;
      if(groupInfo($groupuser_esc,$groupuser_disp)) {
	$query = "SELECT hosts_allow FROM sds_groups_in_groups WHERE subgroup='$groupuser_esc' AND supergroup='$group_esc'";
	$hostsresult = sdsQuery($query);
	if($hostsresult and pg_num_rows($hostsresult)) {
	  list($grouphosts) = pg_fetch_array($hostsresult);
	} else {
	  $grouphosts = '"nothing??"';
	}
	if($hostsresult) pg_free_result($hostsresult);

	echo "<p>$groupuser_disp is in $group_disp",$grouphosts==='%'?
	  '':" at addresses matching ".htmlspecialchars($grouphosts),"</p>\n";

	if($superadhoc==='t') {
?>
<p style="font-style: italic">This is an adhoc group.  You cannot
  manually remove a user from an adhoc group.  Usually, this represents
  a mailing list.  Use
  <a href="<?php echo sdsLink('../groups/view_mailing_lists.php') ?>">Mailing
   List management</a> to remove the user.
</p>
<?php
        } else {
	  groupRemoveForm($groupuser_disp,$group_disp);
        }
      }
    }
  } elseif($supergroup) {
    if(groupInfo($supergroup_esc,$supergroup_disp)) {

      $query = "SELECT hosts_allow FROM sds_groups_in_groups WHERE subgroup='$group_esc' AND supergroup='$supergroup_esc'";
      $hostsresult = sdsQuery($query);
      if($hostsresult and pg_num_rows($hostsresult)) {
	list($grouphosts) = pg_fetch_array($hostsresult);
      } else {
	$grouphosts = '"nothing??"';
      }
      if($hostsresult) pg_free_result($hostsresult);

      echo "<p>$group_disp is in $supergroup_disp",$grouphosts==='%'?
	  '':" at addresses matching ".htmlspecialchars($grouphosts),"</p>\n";

      if($adhoc==='t') {
?>
<p style="font-style: italic">This is an adhoc group.  You cannot
  manually remove a user from an adhoc group.  Usually, this represents
  a mailing list.  Use
  <a href="<?php echo sdsLink('../groups/view_mailing_lists.php') ?>">Mailing
   List management</a> to remove the user.
</p>
<?php
      } else {
	groupRemoveForm($group_disp,$supergroup_disp,'supergroup');
      }
    }
  }
}

sdsIncludeFooter();
