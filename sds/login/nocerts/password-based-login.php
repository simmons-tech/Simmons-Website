<?php
## password-based-login.php
##
## dramage 2002.09
## bonawitz 2004.12.13 : major cleanup
##
## interface for user session management.  


## This script takes the following POST commands:
##
## url : the URL to go to when login is done
##
## athena : attempt to login via athena username/password.
##          uses the POST variables athena_username and athena_password
##
## sds : attemt to login via SimmonsDB username/password
##       uses the POST variables sds_username/sds_password
##
## if no POST commands are specified the user is forwarded to ../certs/login.php to be
## presented with a login page.


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
require_once("./Snoopy.class.inc");

## where is the user headed after she logs in?
if(empty($sdsToURL)) {
  if(!empty($_REQUEST['url'])) {
    $sdsToURL = maybeStripslashes($_REQUEST["url"]);
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


###################################################
## Do an athena username/password log in
if(!empty($_REQUEST["athena"])) {
  $athena_username = maybeStripslashes($_REQUEST["athena_username"]);
  $athena_username_esc = pg_escape_string($athena_username);
  // only selects active users
  $query = "SELECT 1 FROM sds_users WHERE username='$athena_username_esc'";
  $result = sdsQuery($query);
  if(!$result)
    contactTech("Could not search users");

  if (pg_num_rows($result) < 1) {
    pg_free_result($result);
    sdsIncludeHeader("SimmonsDB", "Error Logging In");
?>
<h2>The username you entered is not in the SimmonsDB.</h2>
<h3>Please try reentering it</h3>
<p>If you think this is an error, please email
  <a href='mailto:simmons-tech@mit.edu'>simmons-tech@mit.edu</a>.</p>
<?php
    sdsIncludeFooter();
    exit;
  }

  pg_free_result($result);
  $query =
    "SELECT poserver FROM mailserver WHERE username='$athena_username_esc'";
  $result = sdsQuery($query);
  if(!$result)
    contactTech("Could not search mailservers");
  unset($MyPOServer);
  if (pg_num_rows($result)) {
    $data = pg_fetch_object($result);
    $MyPOServer = $data->poserver;
  } else {
    $pofetch = new Snoopy;
    $submit_url = "http://nic.mit.edu/cgi-bin/eudora";
    $submit_vars = array();
    $submit_vars["username"] = $athena_username;
    $submit_vars["submit3"] = "Submit";
    if ($pofetch->submittext($submit_url, $submit_vars)) {
      $Matches = strpos($pofetch->results, $athena_username);
      $LengthPos = strpos($pofetch->results, "mit.edu", $Matches);
       
      $MyPOServer =
	substr($pofetch->results,$Matches+strlen($athena_username." is: "),
	       $LengthPos-$Matches-strlen($athena_username." is: ")+7);
      $query = "INSERT INTO mailserver (username, poserver) VALUES ('$athena_username_esc', '".pg_escape_string($MyPOServer)."');";
	
      $result2 = sdsQuery($query);
      if(!$result)
	contactTech("Could not update meailserver info");
      pg_free_result($result2);
    }
  }
  pg_free_result($result);

  if(isset($MyPOServer)) {
    $mbox=@imap_open("{".$MyPOServer.":993/imap/ssl/novalidate-cert}",
		     $athena_username,
		     maybeStripslashes($_REQUEST["athena_password"]));
    if($mbox) {
      imap_close($mbox);
      $session = createSession($athena_username);
      header("Location: ".sdsLink($sdsToURL,$sdsToArgs,true));
      exit;
    }
  }
  sdsIncludeHeader("SimmonsDB", "Error Logging in");
  echo "<h2>Unable to authenticate. Please log in again.</h2>";
  sdsIncludeFooter();
  exit;
}

###########################################################
### Do a simmons username/password login
if (strlen($_REQUEST["sds"])) {
  if (strlen ($_COOKIE["sid"])) {
    if($session->sid === maybeStripslashes($_COOKIE["sid"]))
      unset($session);
    deleteSession(maybeStripslashes($_COOKIE["sid"]));
    setcookie ("sid", "", 0, SDS_COOKIE_PATH);
  }

  $sds_username = maybeStripslashes($_REQUEST["sds_username"]);

  if($sds_pass_hash =
     verifyPasswordClear($sds_username,
			 maybeStripslashes($_REQUEST["sds_password"]))) {
    $session = createSession($sds_username);

    header("Location: " . sdsLink($sdsToURL,$sdsToArgs,true));
    exit;
  } else {
    # password was wrong: try again
    sdsIncludeHeader("Simmons DB","Invalid Username/password");
    echo "<h2>Who is you?! I don't know you!</h2>\n";
    sdsIncludeFooter();
    exit;
  }
}

###########################################################
### No successful login
header("Location: " . sdsLink(SDS_BASE_URL . "login/certs/login.php"));
exit;
