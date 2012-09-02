<?php
require_once('../sds.php');
sdsRequireGroup('USERS');
require_once('../wiki_interaction.inc.php');

sdsIncludeHeader("Wiki Account Creation");

$user_myesc =
  mysql_real_escape_string(strtolower($session->username),$mysql_db);
$query = "SELECT 1 FROM user WHERE LOWER(user_name) = '$user_myesc'";
$result = mysql_query($query,$mysql_db);

if(!$result or mysql_num_rows($result) > 0) {
  echo "<p>You already have a wiki account. You can continue to the\n";
  echo "  <a href='http://simmons.mit.edu/wiki/'>Simmons Wiki</a>.</p>\n";
  sdsIncludeFooter();
  exit;
}
mysql_free_result($result);

if(isset($_REQUEST['create'])) {

  $randpass = '';
  for($i=0;$i<10;$i++) {
    $randpass .= chr(mt_rand(33,126));
  }

  $username_esc = pg_escape_string($session->username);
  $query = "SELECT email FROM directory WHERE username = '$username_esc'";
  $result = sdsQuery($query);
  if(!$result)
    contactTech("Could not search directory");
  list($email) = pg_fetch_array($result);
  pg_free_result($result);
  if(empty($email)) {
    // guess
    $email = $session->username . '@mit.edu';
  }

# This code may change when MediaWiki is upgraded.

  $user_myesc = mysql_real_escape_string($session->username,$mysql_db);
  $email_myesc = mysql_real_escape_string($email,$mysql_db);
  $password_myesc = mysql_real_escape_string($randpass,$mysql_db);

  # hopefully just setting most fields blank will make MediaWiki set defaults
  $query = <<<ENDQUERY
INSERT INTO user
       (user_name,    user_email,    user_newpassword,
        user_real_name,user_password,user_options,user_touched,user_token)
VALUES ('$user_myesc','$email_myesc','$password_myesc',
        '',            '',           '',          '',          '')
ENDQUERY;
  $result = mysql_query($query,$mysql_db);
  if(!$result or mysql_affected_rows($mysql_db) != 1)
    contactTech("Wiki account creation failed");

?>
<h2>Account created</h2>
<p>Your account has been assigned the temporary password
  <code><?php echo htmlspecialchars($randpass) ?></code> which you should now
  change. Please continue to the
  <a href='http://simmons.mit.edu/wiki/'>Simmons Wiki</a>.</p>

<?php
  sdsIncludeFooter();
  exit;
}

?>
<h2>Wiki Account Creation</h2>
<p>You do not yet have an account on the Simmons Wiki.</p>

<form action='wiki_account.php' method='post'>
<?php echo sdsForm() ?>
<submit name='create' value='Create an Account' />
</form>

<?php
sdsIncludeFooter();
