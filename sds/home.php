<?php
require_once("sds.php");
sdsRequireGroup("EVERYONE");
sdsIncludeHeader("Simmons DB", "Welcome to the Simmons DB");

?>

<?php include "directory/15sof.php"; ?>

<p>
  Welcome to Simmons DB, a magical play land of mystery and fun.  If you have
  trouble with something, email
  <a href="mailto:simmons-tech@mit.edu">simmons-tech@mit.edu</a>, your
  friendly, teddy-bear-like guide to all things simmons-tech.
</p>

<p>
  <b>Important note:</b> If you're unfamiliar with the new GovTracker system
  for managaing the house's and lounges' proposals and finances, please ask an
  officer or upperclassmen before submitting anything.  In
  general, submitting data to any GovTracker system cannot be undone.
</p>

<p>
  The Simmons DB is set up to use <a href="http://ca.mit.edu/">MIT
   Certificates</a> to authenticate logins, but you can also set up the system
  to let you <a href="<?php echo sdsLink("users/password.php"); ?>">log in
  with a password</a> as an alternative.  (Note, however, that you need to be
  logged in by certificate in order to set up your password.)
</p>

<p>
  A new feature of the Simmons DB is the ability to login using your MIT
  Kerberos (email) username and password.  To do this, go to the
  <a href="<?php echo sdsLink("login/certs/login.php");?>">Simmons Login
  Page</a>
</p>

<?php 
#'
if ($session->username === 'GUEST') {
?>

<p>
  <b>The Simmons DB has been unable to identify you, so you are logged in as
  GUEST.  Most Simmons DB features will be inaccessible until you
  <a href="http://ca.mit.edu/">get a certificate</a> and
  <a href="<?php echo SDS_LOGIN_URL ?>">log in again</a>.
</p>
<?php
}

sdsIncludeFooter();
