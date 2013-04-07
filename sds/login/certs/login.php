<?php

## login.php
##
## dramage 2002.09
## bonawitz 2004.12.13 : major cleanup
##
## interface for user session management.  


## This script takes the following GET commands:
##
## url : the URL to go to when login is done
##
## auto : attempt to automatically log in (via certificates)
## certificate : attempt login via MIT certificate
## continue : continue with the current session
##
## if no GET commands are specified, or if auto is specified, but automatic login cannot
## be completed, the user is presented with a login page.


## Note that athena username/password and SimmonsDB username/password logins are
## requested using the form in ../certs/login.php, but are processed using the login in
## ../nocerts/password-based-login.php .  This is because Apache seems to have some
## issues with client certificates requests preventing the use of the POST method.
## It would be insecure to use GET for passwords, because then the passwords would
## be part of the URL, and as such would be recorded in the apache logs and various
## other places where cleartext passwords have no business being.  Thus, the solution
## is to only request client certificates for pages in the certs directory, and to
## post passwords to a different script in the nocerts directory.  
##
## Remember: you can not POST to files in the certs directory, and you should not
## GET passwords to files in the nocerts directory!

## sds configurations:
$sdsNoAuthentication = true;
$sdsLoginSquash = true;

## require main lib, without auto-authentication + error squashing
require_once("../../sds.php");

## where is the user headed after she logs in?
## $sdsToURL can be set by the sdsLoginPage() function
if(empty($sdsToURL)) {
  if(!empty($_REQUEST['url'])) {
    $sdsToURL = maybeStripslashes($_REQUEST["url"]);
    // Microsoft-reported security problem:
    $sdsToURL = htmlspecialchars($sdsToURL);
  } else {
    $sdsToURL = SDS_HOME_URL;
  }
}

$sdsToArgs = '';
if(preg_match('/(.*)\?(.*)/',$sdsToURL,$matches)) {
  $sdsToURL = $matches[1];
  $sdsToArgs = htmlspecialchars($matches[2]);
}

## find out how the user is currently logged in
$session = continueSession();

#############################################
## if the user is logged in as someone other than GUEST, then they've
## already achieved auto-login. #'
if(!empty($_REQUEST["auto"]) and isset($session) and
   strlen($session->username) and ($session->username !== "GUEST")) {
  header("Location: " . sdsLink($sdsToURL,$sdsToArgs,true));
  exit;
}

##############################################
## check if the user just wants to be who they are.
if(!empty($_REQUEST["continue"])) {
  header("Location: " . sdsLink($sdsToURL,$sdsToArgs,true));
  exit;
}

##############################################
## next, we'll inspect the user's certificate if not at desk
unset($certificate_username);
$deskip = sdsGetStrOption('desk-ip');
if($_SERVER["REMOTE_ADDR"] !== $deskip and
   isset($_SERVER['SSL_CLIENT_S_DN']) and
   strlen($_SERVER["SSL_CLIENT_S_DN"])) {
  ## certificate found.  get certifate username
  $certificate_username = preg_replace("/\@MIT\.EDU$/", "",
				       $_SERVER["SSL_CLIENT_S_DN_Email"]);

  ## Given a certificate username, we can login if the appropriate GET
  ## commands tell us to
  if(!empty($_REQUEST["auto"]) or !empty($_REQUEST["certificate"])) {
    if(!isset($session) or $certificate_username !== $session->username) {
      $session = createSession($certificate_username);
    }
    header("Location: " . sdsLink($sdsToURL,$sdsToArgs,true));
    exit;
  }
}


############################################
## if we couldn't log via certificates,
## then we're going to have to ask the user for a password no matter what, so 
## we should start drawing up the form now.
 
if (!$session)  $session = createSession("GUEST");
sdsIncludeHeader("Simmons DB", "Welcome to Simmons DB");

if(!empty($_REQUEST["url"])) {
?>
<p><b>Simmons DB was unable to automatically identify you.</b>
  You can continue on to '<?php echo sdsLink($sdsToURL,$sdsToArgs) ?>'
  as a GUEST, which may or may not work. Alternatively, you can tell Simmons DB
  who you are by installing your <a href='http://ca.mit.edu/'>MIT
  certificate</a>, or by logging in with a password below:</p>
<?php
}

#######################################
### PASSWORD-FREE LOGIN OPTIONS
echo "<form action='../certs/login.php' method='get'>\n";
echo "<input type='hidden' name='url' value='",
  sdsLink($sdsToURL,$sdsToArgs),"' />\n";
echo sdsForm();

echo "<table>\n";
if (strlen($session->username)) {
  echo "  <tr>\n";
  echo "    <td>You are currently logged in as ",
    htmlspecialchars($session->username),".</td>\n";
  echo '    <td><input name="continue" type="submit" value="Continue as ',
    htmlspecialchars($session->username),"\" /> </td>\n";
  echo "  </tr>\n";
}

if(!empty($certificate_username) and
   $certificate_username !== $session->username) {
  echo "  <tr>\n";
  echo "    <td>Your MIT certificate identifies you as ",
    htmlspecialchars($certificate_username),".</td>\n";
  echo '    <td><input name="certificate" type="submit" value="Login as ',
    htmlspecialchars($certificate_username),"\" /> </td>\n";
  echo "  </tr>\n";
}

echo "</table>\n";
echo "</form>\n";
echo "<br />\n";


$sdsErrorBlock = isset($sdsErrorText) ?
  "      <p align='center' class='error'><?php echo $sdsErrorText ?></p>\n" :
  '';
########################################
### USERNAME + PASSWORD LOGIN OPTIONS
?>
<form action='../nocerts/password-based-login.php' method='post'>
<input type='hidden' name='url' value="<?php echo sdsLink($sdsToURL,$sdsToArgs) ?>" />
<?php echo sdsForm() ?>

<!-- Simmons username+password -->
<p>You can also log in with a username/password pair.  Note that this is
<b>not your Athena password</b>!  If you have logged in successfully via
certificate, then you can
<a href='<?php echo sdsLink(SDS_BASE_URL . "users/password.php")?>'>set
up your password</a>.
Contact <a href='mailto:simmons-tech@mit.edu'>simmons-tech@mit.edu</a>
for details.</p>

<table>

  <tr>
    <td align='right'>Username:</td>
    <td><input name='sds_username' type='text' size='12' /></td>
  </tr>

  <tr>
    <td align='right'>Password:</td>
    <td><input name='sds_password' type='password' size='12' /></td>
  </tr>

  <tr>
    <td></td>
    <td>
<?php echo $sdsErrorBlock ?>
      <input name='sds' type='submit' value='Login' />
    </td>
  </tr>

</table>
</form>

<!-- Athena username+password -->
<p>You can now also login using your athena username/password pair.
Please enter them below to login</p>

<form action='../nocerts/password-based-login.php' method='post'>
<input type='hidden' name='url' value="<?php echo sdsLink($sdsToURL,$sdsToArgs) ?>" />
<?php echo sdsForm() ?>
<table>

  <tr>
    <td align='right'>Username:</td>
    <td><input name='athena_username' type='text' size='12' /></td>
  </tr>

  <tr>
    <td align='right'>Password:</td>
    <td><input name='athena_password' type='password' size='12' /></td>
  </tr>

  <tr>
    <td></td>
    <td>
<?php echo $sdsErrorBlock ?>
      <input name='athena' type='submit' value='Login via Athena' />
    </td>
  </tr>

</table>
</form>

<?php
sdsIncludeFooter();
