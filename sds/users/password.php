<?php
require_once("../sds.php");
sdsRequireGroup("USERS");

$username_disp = htmlspecialchars($session->username);
$username_esc = pg_escape_string($session->username);

sdsIncludeHeader("Password for $username_disp");

$query = "SELECT 1 FROM sds_users WHERE password IS NOT NULL AND username='$username_esc'";
$result = sdsQuery($query);
if(!$result)
  contactTech("Could not find password status");
$haspasswd = (pg_num_rows($result) == 1);
pg_free_result($result);

$passwdMessage = '';
if(!empty($_REQUEST["submit"])) {

  if($haspasswd and
     !verifyPasswordClear($session->username,
			  maybeStripslashes($_REQUEST['old_passwd']))) {
    $passwdMessage = "<p class='error'>Error setting password: Current password incorrect</p>\n";;
  } elseif($_REQUEST["new_passwd"] !== $_REQUEST["cfm_passwd"]) {
    $passwdMessage = "<p class='error'>Error setting password: Passwords don't match</p>\n";
  } else {
    if(!setPassword($session->username,
		    maybeStripslashes($_REQUEST['new_passwd'])))
      contactTech("Could not set password");

    $haspasswd = (strlen($_REQUEST['new_passwd']) != 0);
    if($haspasswd) {
      $passwdMessage = <<<EOF
<p class='success'>Password updated successfully!</p>
<p>You can now log in to the Simmons DB using the password you just
  entered.</p>
<hr />
EOF;
    } else {
      $passwdMessage = "<p class='success'>Passwod Cleared</p>\n";
    }
  }
}

?>

<form action="password.php" method="post">
<?php echo sdsForm() ?>

<table>
  <tr>
    <td align="right">Current password:</td>
    <td>
<?php
if($haspasswd) {
  echo '<input name="old_passwd" type="password" size="16" />';
} else {
  echo "<pre><b>[ none defined ]</b></pre>";
}
?>
    </td>
  </tr>
  <tr>
    <td align="right">New password:</td>
    <td><input name="new_passwd" type="password" size="16" /></td>
  </tr>
  <tr>
    <td align="right">Re-type password:</td>
    <td><input name="cfm_passwd" type="password" size="16" /></td>
  </tr>
  <tr>
    <td colspan="2" align="center">
      <input name="submit" type="submit" value="Update Password" />
    </td>
  </tr>
</table>
</form>

<?php echo $passwdMessage ?>

<p>The Simmons DB is set up to use <a href="http://ca.mit.edu/">MIT
  Certificates</a> to authenticate logins, but you can also set up the system
  to let you log in with a password as an alternative.</p>

<p>To change your password, enter your current password, and then a new
  password for your login (twice).</p>

<p>To disable password-access to your Simmons DB account, <u>leave blank</u>
  both "New password" and "Re-type password."</p>

<p>Trouble? Contact
  <a href="mailto:simmons-tech@mit.edu">simmons-tech@mit.edu</a>.</p>

<?php
sdsIncludeFooter();
