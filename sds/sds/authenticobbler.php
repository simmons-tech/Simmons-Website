<?php
if(realpath($_SERVER['SCRIPT_FILENAME']) === realpath(__FILE__))
  exit;
## authenticobbler.php
##
## authenticate user and inialize a Session object


## load current session using cookie / cgi specifier
##
function continueSession() {
  $session = null;
  # load session named in session cookie
  if(isset($_COOKIE['sid']) and strlen($_COOKIE["sid"])) {
    # save cookie contents
    $sid = $_COOKIE["sid"];

    # clear the session cookie
    setcookie ("sid", "", 0, SDS_COOKIE_PATH);

    # load the named session, dying on error
    $session = loadSession($sid);

    # reset on success
    setcookie ("sid", $sid, 0, SDS_COOKIE_PATH);
  }

  # load cgi-named session
  else if(isset($_REQUEST['sid']) and strlen($_REQUEST["sid"])) {
    $session = loadSession($_REQUEST["sid"]);
  }

  return $session;
}



##
## session management functions
##

function loadSession ($sid) {
  return new Session ($sid);
}

function createSession ($username) {
  $session = new Session ("", $username);

  # save session cookie
  setcookie ("sid", $session->sid, 0, SDS_COOKIE_PATH);

  return $session;
}

function deleteSession($sid) {
  $sid_esc = pg_escape_string($sid);
  $result = sdsQuery("DELETE FROM sds_sessions WHERE sid='$sid_esc'");
  if(!$result)
    contactTech("Could not remove session");
  pg_free_result($result);
}

function existsSession ($sid) {
  $sid_esc = pg_escape_string($sid);
  $result = sdsQuery("SELECT 1 FROM sds_sessions WHERE sid='$sid_esc'");
  if(!$result)
    contactTech("Could not search sessions");
  $retval = (pg_num_rows($result) > 0);
  pg_free_result($result);
  return $retval;
}




##
## user session class
##
## Usage:
##   $session = new Session ("12345");
##     loads session with id 12345
##
##   $session = new Session ("", "dramage");
##     creates new session for dramage
##
##   $session = new Session ();
##     creates new guest session
##
##   $session->loadData();
##     re-loads the contents of the $session->data array from
##     the db (put whatever php structures you want in it)
##
##   $session->saveData();
##     saves the $session->data array to the db.  serializes,
##     so nested structure is preserverd.
##

class Session {
  var $sid;
  var $username;
  var $remote_addr;
  var $data;
  var $groups;

  ## create a new session object
  ## 
  ## if $sid is non-null, we attempt to load the named session.
  ## if $sid is null we create a new session for the given username
  function Session ($sid = '', $username = 'GUEST', $persistent = false) {

    $username_esc = pg_escape_string($username);
    $persistent = (bool) $persistent;

    # where from
    $this->remote_addr = $_SERVER["REMOTE_ADDR"];

    # null session - create a new one
    if ($sid === '') {
      # save who it claims we are
      $this->username = $username;

      // sds_users only contains active users
      $query = "SELECT username FROM sds_users WHERE username='$username_esc'";
      $result = sdsQuery($query);
      if(!$result)
	contactTech("Could not search users");

      if(pg_num_rows($result) > 0) {
        # save new session id
        $this->sid = uniqid(rand(100,10000));

	$query =
	  "INSERT INTO sds_sessions (sid, username, remote_addr) VALUES ('" .
	  pg_escape_string($this->sid) . "', '" .
	  pg_escape_string($this->username) . "', '" .
	  pg_escape_string($this->remote_addr) . "')";
	$result = sdsQuery($query);
	if(!$result or pg_affected_rows($result) != 1)
	  contactTech("Could not create session");
	pg_free_result($result);
      } else {
        sdsErrorPage("Invalid Login",
		     "'".htmlspecialchars($username)."' not a valid user");
      }
    } else {
      # non-null session - load username from db
      # save session id
      $this->sid = $sid;

      $query = "SELECT username, remote_addr FROM sds_sessions WHERE sid = '" .
	pg_escape_string($this->sid) . "'";
      $result = sdsQuery($query);
      if(!$result)
	contactTech("Could not search sessions");
      if (pg_num_rows($result) > 0) {
	$data = pg_fetch_object($result);
        $this->username = $data->username;
        if($this->remote_addr !== $data->remote_addr) {
          sdsErrorPage("Authentication Error",
                       "Who said you could change your IP address?
                        <a href='".SDS_LOGIN_URL."'>Log in</a> again.");
	}
      } else {
        # unable to load - go to error page
	sdsErrorPage("Authentication Error",
		     "Your session has expired. Try reloading. If that fails, you can try to <a href='".SDS_LOGIN_URL."'>log in</a> again.");
      }
    }
    $sid_esc = pg_escape_string($this->sid);

    # update the expiration date
    $query = ("UPDATE sds_sessions SET expires = now() + INTERVAL '60 minutes' WHERE sid = '$sid_esc'"); 
    $result = sdsQuery($query);
    if(!$result or pg_affected_rows($result) != 1)
      contactTech("Could not update session");
    # find out what groups we belong to
    $this->getGroups();

    # load data
    $this->loadData();
  }

  ## query database for group membership
  ##
  ## populates the $this->groups[] array with booleans.
  ## e.g. if ($this->groups[ADMINISTRATORS]) { echo "i'm an admin!"; }
  function getGroups() {
    $username_esc = pg_escape_string($this->username);
    $remote_esc = pg_escape_string($this->remote_addr);
    $query = "SELECT groupname FROM sds_group_membership_cache WHERE username='$username_esc' AND '$remote_esc' LIKE hosts_allow";
    $result = sdsQuery($query);
    if(!$result)
      contactTech("Could not find group membership");

    while($data = pg_fetch_object($result)) {
      $this->groups[$data->groupname] = true;
    }

    pg_free_result($result);
  }


  ## load session data from the database
  ##
  function loadData() {
    $sid_esc = pg_escape_string($this->sid);

    $query = "SELECT data FROM sds_sessions WHERE sid='$sid_esc'";
    $result = sdsQuery($query);
    if(!$result)
      contactTech("Could not load session data");

    # session found, load data
    if(pg_num_rows($result) > 0) {
      $rset = pg_fetch_object($result);
      if (strlen($rset->data))
        $this->data = unserialize($rset->data);
    } else {
      $this->data = array();
    }
    pg_free_result($result);
  }

  ## save data to the database
  ##
  function saveData() {
    $sid_esc = pg_escape_string($this->sid);
    $data_esc = pg_escape_string(serialize($this->data));
    $query = "UPDATE sds_sessions SET data = '$data_esc' WHERE sid='$sid_esc'";
    $result = sdsQuery($query);
    if(!$result or pg_affected_rows($result) != 1)
      contactTech("Could not save session data");
    pg_free_result($result);
  }
}



## check the given username, CLEARTEXT password combination.
## 
## returns hashed password for valid pairs, false otherwise.
## uses md5 hashing algorithm to compare to datbase-stored values
function verifyPasswordClear($username, $password) {

  # no blank passwords
  # == is intentional, want null to fail
  if($password == '')
    return false;

  $username_esc = pg_escape_string($username);

  $query = "SELECT salt FROM sds_users WHERE username='$username_esc'";
  $result = sdsQuery($query);
  if(!$result)
    contactTech("Could not find salt");
  if(pg_num_rows($result) != 1) {
    pg_free_result($result);
    return false;
  }
  list($salt) = pg_fetch_array($result);
  pg_free_result($result);

  $hash = md5($salt . $password);
  return verifyPassword($username,$hash) ? $hash : false;
}


## check the given username, HASHED password combination.
## 
## returns true for valid pairs, false otherwise.
## uses md5 hashing algorithm to compare to datbase-stored values
function verifyPassword($username, $password) {
  # no blank passwords
  if ($password == '')
    return false;

  $username_esc = pg_escape_string($username);

  $query = "SELECT password FROM sds_users WHERE username='$username_esc'";
  $result = sdsQuery($query);
  if(!$result)
    contactTech("Could not find password");
  if(pg_num_rows($result) != 1) {
    pg_free_result($result);
    return false;
  }
  list($correctpass) = pg_fetch_array($result);
  pg_free_result($result);

  return($password === $correctpass);
}

# set the user password to the given cleartext
# returns true on success
function setPassword($username,$password) {
  $username_esc = pg_escape_string($username);
  if($password == '') {
    $query = "UPDATE sds_users_all SET salt=null,password=null WHERE username='$username_esc'";
  } else {
    $salt = '';
    for($i=0;$i<8;$i++) {
      $salt .= chr(mt_rand(32,126));
    }
    $hash = md5($salt . $password);
    $query = "UPDATE sds_users_all SET salt='" . pg_escape_string($salt) .
      "',password='" . pg_escape_string($hash) .
      "' WHERE username='$username_esc'";
  }
  $result = sdsQuery($query);
  if(!$result or pg_affected_rows($result) != 1)
    return null;
  pg_free_result($result);
  return true;
}
