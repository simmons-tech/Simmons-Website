<?php
if(realpath($_SERVER['SCRIPT_FILENAME']) === realpath(__FILE__))
  exit;
## sds.php
##
## configuration and utilities

##
## the behavior of sds.php can be affected by several configuration
## variables set in the including php.  by default they are all false:
##
## $sdsNoAuthentication = true;
## -- don't call authenticobbler.php:continueSession()
##
## $sdsLoginSquash = true;
## -- don't redirect to login page when sdsLogin called
##'


##
## installation variables: edit as appropriate
##

require_once(dirname(__FILE__) . "/sds/config.php");

##
## database initialization
##

$SDB = pg_pconnect("dbname=" . SDB_DATABASE . " " .
                   array_shift(file(SDB_PASSWORD_FILE)));
if(!$SDB) {
  sdsErrorPage("Database Gone Fishin", 'PostgreSQL seems to be on vacation.  Email<i><a href="mailto:simmons-tech@mit.edu">simmons-tech@mit.edu</a></i> and let them know of this terrible situation.');
  exit;
}


##
## authentication library
##

require_once(SDS_BASE . "/sds/authenticobbler.php");

# create session, unless $sdsNoAuthentication has been set
if(empty($sdsNoAuthentication)) {
  $session = continueSession();
  if (!$session) {
#   $session = createSession("GUEST");
    header("Location: " . SDS_AUTO_LOGIN_URL . "&url=" .
           urlencode('https://simmons.mit.edu' . $_SERVER['REQUEST_URI']));
    exit;
  }
}




##
## utility functions
##

## query the database and log if the query gave an error
## sends email to tech if this is the real DB
## (yeah, this will be annoying if there is a bug, but it is important to
## fix these things)

## make sure you check the return value!!

function sdsQuery($query) {
  global $SDB;
  ob_start();
  $result = pg_query($SDB,$query);
  if($result) {
    ob_end_clean();
    return $result;
  }

  # uh oh
  $complaint = "\n" . date('r') . ": requested page: " .
    $_SERVER['PHP_SELF']."\n";
  $complaint .= "query: " . $query . "\n";
  $complaint .= ob_get_clean();
  if(file_put_contents(SDS_QUERY_LOG,$complaint,FILE_APPEND) === false and
     !mail('simmons-tech@mit.edu','Log error',
	   "Could not log message to ".SDS_QUERY_LOG.":\n$complaint",
	   "From: root@simmons.mit.edu\r\nReply-to: simmons-tech@mit.edu")) {
    echo "<h2 class='error'>Simmons DB Internal Error</h2>\n";
    echo "<p class='error'>Please email the following information to <a href='mailto:simmons-tech@mit.edu'>simmons-tech@mit.edu</a>:\n";
    echo "<pre>",$complaint,"</pre>\n";
  }
  if(SDB_DATABASE === 'sdb' and
     !mail('simmons-tech@mit.edu','Query Error',$complaint,
	   "From: root@simmons.mit.edu\r\nReply-to: simmons-tech@mit.edu")) {
    echo "<h2 class='error'>Simmons DB Internal Error</h2>\n";
    echo "<p class='error'>Please email the following information to <a href='mailto:simmons-tech@mit.edu'>simmons-tech@mit.edu</a>:\n";
    echo "<pre>",$complaint,"</pre>\n";
  }
  return false;
}

## query the database when an error is not a problem
## (i.e. when using postgres to validate a time format)

function sdsQueryTest($query) {
  global $SDB;
  return @pg_query($SDB,$query);
}



## generate link url with session information
##
function sdsLink($toURL, $arguments = "",$isheader=false) {
  global $session;
  if(isset($_COOKIE['sid']) or !isset($session))
    return "$toURL" . (strlen($arguments) ? "?$arguments" : "");
  else
    return "$toURL?sid=$session->sid"
           . (strlen($arguments) ? (($isheader?'&':'&amp;').$arguments) : "");
}

## option retrieval functions
## get an integer optional
function sdsGetIntOption($optName) {
  $gioquery = "SELECT value FROM options WHERE name='$optName';";
  $giores = sdsQuery($gioquery);
  if(!$giores)
    return null;
  if(pg_num_rows($giores) != 1) {
    pg_free_result($giores);
    return null;
  }
  $giodata = pg_fetch_array($giores);
  pg_free_result($giores);
  return $giodata['value'];
}

## get a string option
function sdsGetStrOption($optName){
  $gioquery = "SELECT value_string FROM options WHERE name='$optName';";
  $giores = sdsQuery($gioquery);
  if(!$giores)
    return null;
  if(pg_num_rows($giores) != 1) {
    pg_free_result($giores);
    return null;
  }
  $giodata = pg_fetch_array($giores);
  pg_free_result($giores);
  return $giodata['value_string'];
}


## utility functions for things we need to retrieve
## from the DB very often
function sdsGetFullName($usname){
  $fnq = sdsQuery("SELECT COALESCE(title||' ','')||firstname || ' ' || lastname AS fullname FROM directory WHERE username = '".pg_escape_string($usname)."'");
  if(!$fnq)
    return $usname;
  if (pg_num_rows($fnq) != 1) {
# can't find name, just return username #'
    pg_free_result($fnq);
    return $usname;
  }
  $fnr = pg_fetch_array($fnq);
  pg_free_result($fnq);
  return $fnr['fullname'];
}

## prints a message to contact tech and exits
function contactTech($reason,$exit = true) {
  global $sdsHeaderIncluded;
  if(!$sdsHeaderIncluded)
    sdsIncludeHeader("Internal Error");
  echo "<h2 class='error'>ERROR: ",$reason,
    ".\nPlease contact <a href='mailto:simmons-tech@mit.edu'>simmons-tech@mit.edu</a>.</h2>\n";
  if($exit) {
    sdsIncludeFooter();
    exit;
  }
}


## session reminders functions
## add a reminder
function sdsSetReminder($remName, $remMsg){
  global $session;
  $session->data['reminders'][$remName]=$remMsg;
  $session->saveData();
  return $remName;
}

function sdsClearReminder($remName){
  global $session;
  unset($session->data['reminders'][$remName]);
  $session->saveData();
  return $remName;
}

function sdsClearReminders()
{
   global $session;
   $session->data['reminders'] = array();
   return 0;
}

function sdsGetReminders(){
  global $session;
  return $session->data['reminders'];
}

function sdsGetReminder($remName){
  global $session;
  return $session->data['reminders'][$remName];
}

function sdsShowReminders()
{
  global $session;
  $qr = sdsQuery("UPDATE directory SET showreminders=TRUE WHERE username='".
		 pg_escape_string($session->username)."'");
  if(!$qr)
    return null;
  $retval = (pg_affeted_rows($qr) == 1);
  pg_free_result($qr);

  return $retval;
}

function sdsHideReminders()
{
  global $session;
  $qr = sdsQuery("UPDATE directory SET showreminders=FALSE WHERE username='".
		 pg_escape_string($session->username)."'");
  if(!$qr)
    return null;
  $retval = (pg_affeted_rows($qr) == 1);
  pg_free_result($qr);

  return $retval;
}

function sdsIsShowingReminders()
{
  global $session;
  $qr = sdsQuery("SELECT showreminders FROM directory WHERE username='".
		 pg_escape_string($session->username)."'");
  if(!$qr)
    return null;
  if (pg_num_rows($qr)!=1) {

# always show reminders to unrecognizable users
    pg_free_result($qr);
    return true;
  }
  $qrd = pg_fetch_array($qr);
  pg_free_result($qr);

  return ($qrd['showreminders'] === 't');
}


##
## encryption library
##

## Encrypt some text using.
## If a $key string is given, that public key will be used.  Otherwise, the
## SimmonsDB public key will be used. 
##
## Note that the SimmonsDB private key is only available to root, making 
## this a good way of storing things in the database that only root should 
## ever see, but a bad way of storing things that anyone would ever want 
## to access from php.
##
## Keypairs can be generated using /var/www/sds/util/simdb_keygen.pl
##
function sdsEncrypt($toEncrypt, $key="") {
  $encrypt_script = "/var/www/sds/util/simdb_encrypt.pl";
  $to_script = 0;
  $from_script = 1;
  $descriptor_spec = array( 
    $to_script => array("pipe", "r"),
    $from_script => array("pipe", "w")
  );
  $process = proc_open($encrypt_script, $descriptor_spec, $pipes);
  if (! is_resource($process)) {
    echo "ENCRYPTION FAILURE: couldn't open $encrypt_script";
    exit;
  }

  // stream_set_blocking($pipes[$to_script], FALSE);
  // stream_set_blocking($pipes[$from_script], FALSE);

  if (strlen($key)) {
    fwrite($pipes[$to_script], "KEY:\n");
    fwrite($pipes[$to_script], $key);
    fwrite($pipes[$to_script], "\n\nPLAINTEXT:\n");
  }
  fwrite($pipes[$to_script], $toEncrypt);
  fwrite($pipes[$to_script], "\n");
  fclose($pipes[$to_script]);

  $encrypted = '';
  while (! feof($pipes[$from_script])) {
    $encrypted .= fgets($pipes[$from_script], 1024);
  }
  fclose($pipes[$from_script]);

  $return_value = proc_close($process);
  
  if ($return_value != 0) {
    echo "ENCRYPTION FAILURE: $encrypt_script exited with code $return_value";
    exit;
  }

  return $encrypted;
}

## Decrypt some text using the private key in the given string.
##
## Keypairs can be generated using /var/www/sds/util/simdb_keygen.pl
##
function sdsDecrypt($toDecrypt, $key) {
  $decrypt_script = "/var/www/sds/util/simdb_decrypt.pl";
  $to_script = 0;
  $from_script = 1;
  $descriptor_spec = array( 
    $to_script => array("pipe", "r"),
    $from_script => array("pipe", "w")
  );
  $process = proc_open($decrypt_script, $descriptor_spec, $pipes);
  if (! is_resource($process)) {
    echo "DECRYPTION FAILURE: couldn't open $decrypt_script";
    exit;
  }

  // stream_set_blocking($pipes[$to_script], FALSE);
  // stream_set_blocking($pipes[$from_script], FALSE);

  fwrite($pipes[$to_script], "KEY:\n");
  fwrite($pipes[$to_script], $key);
  fwrite($pipes[$to_script], "\nCIPHERTEXT:\n");
  fwrite($pipes[$to_script], $toDecrypt);
  fclose($pipes[$to_script]);

  while (! feof($pipes[$from_script])) {
    $decrypted .= fgets($pipes[$from_script], 1024);
  }
  fclose($pipes[$from_script]);

  $return_value = proc_close($process);
  
  if ($return_value != 0) {
    echo "DECRYPTION FAILURE: $decrypt_script exited with code $return_value";
    exit;
  }

  return $decrypted;
}



## generate hidden form fields with session information
##
function sdsForm() {
  global $session;
  if(!empty($_COOKIE['sid']) or empty($session))
    return "";
  else
    return "<input type='hidden' name='sid' value='$session->sid'>\n";
}


## insert header
##
##
function sdsIncludeHeader ($sdsPageHeadTitle, $sdsPageHtmlTitle = "",
			   $sdsHeadHTML = "",$sdsBodyAttrs = null) {
  // Prevent recursion on errors
  static $headerRun = false;
  if($headerRun)
    return;
  $headerRun = true;

  global $session, $sdsHeaderIncluded;
  if(!$session)
    $session = new Session();

  // so footer knows to close table
  $sdsHeaderIncluded = true;

  if (strlen($sdsPageHtmlTitle) == 0)
    $sdsPageHtmlTitle = $sdsPageHeadTitle;

  include(SDS_BASE . "/sds/header.php");
}

## insert footer
##
function sdsIncludeFooter () {
  global $sdsHeaderIncluded;

  // If there is an error, this can go recursive
  static $sdsFooterIncluded = false;
  if($sdsFooterIncluded)
    return;
  $sdsFooterIncluded = true;

  include(SDS_BASE . "/sds/footer.php");
}

## redirect to login page
##
function sdsLoginPage ($toURL = "", $errorText = "") {
  global $sdsToURL;
  global $sdsErrorText, $sdsLoginSquash;

  $sdsToURL = $toURL;
  $sdsErrorText = $errorText;

  if (!$sdsLoginSquash) {
    include(SDS_BASE . "/login/certs/login.php");
    exit;
  }
}

## redirect to error page with given error text
##
function sdsErrorPage ($errorTitle, $errorText='') {
  global $sdsErrorTitle, $sdsErrorText;

  $sdsErrorTitle = $errorTitle;
  $sdsErrorText = $errorText;

  include(SDS_BASE . "/sds/error.php");
  exit;
}


## require that the current user belong to the given group.
function sdsRequireGroup() {
  global $session;

  $groups = func_get_args();
  $allow = 0;
  foreach($groups as $group) {
    if(!empty($session->groups[$group])) { $allow = 1; break; }
  }
  if (!$allow && !$session->groups['ADMINISTRATORS']) {
    $login = SDS_LOGIN_URL;
    sdsErrorPage("Forbidden", "You do not have the proper privileges to access this page.  Remember that the GUEST account is only enabled from within Simmons, and that it is not fully priveledged.  If you have a certificate or have set up a password, you can try to <a href='$login'>log in</a> again.");
  }
}




## sql insertion helper
## 
## takes an array $_B and a list (a, b, c).
## returns "(a, b, c) VALUES ($_B[a], $_B[b], $_B[c])"
function sqlArrayInsert ($array, $fields = null) {
  if ($fields) {
    $out = '("'.join('", "', $fields).'")';

    foreach ($fields as $field)
      $myfields[] = isset($array[$field]) ?
        "'" . pg_escape_string($array[$field]) . "'" : 'null';

    $out .= " VALUES (" . join(", ", $myfields) . ")";

    return $out;
  } else {
    foreach ($array as $field => $value) {
      $outfields[] = $field;
      $outvalues[] =
	isset($value) ? "'" . pg_escape_string($value) . "'" : 'null';
    }
    $out = '("' . join('", "', $outfields) .'") VALUES (' .
      join(", ", $outvalues) . ")";

    return $out;
  }
}

## sql update helper 
##
## similar to sqlArrayInsert except output as for SQL UPDATE statement.
function sqlArrayUpdate ($array, $fields = null) {
  if ($fields) {
    foreach ($fields as $field)
      $outa[] = "\"$field\" = " .
        (isset($array[$field]) ?
         "'" . pg_escape_string($array[$field]) . "'" : 'null');

    return join(", ", $outa);
  } else {
    foreach ($array as $field => $value)
      $outa[] = "\"$field\" = " .
      (isset($value) ? "'" . pg_escape_string($value) . "'" : 'null');

    return join(", ", $outa);
  }
}

## generates html for form hidden inputs from an array mapping fields to values
function hiddenInputs($maintainfields) {
  $accum = "";
  foreach ($maintainfields as $field => $value) {
    $accum .= '<input name="'.htmlspecialchars($field).
      '" type="hidden" value="'.htmlspecialchars($value).'">';
  }
  return $accum;
}

## generates url fragment encoding get options for an array mapping
## fields to values fragment is in the form:
## "&field0=value0&field1=value1&field2=value2"
function urlOptions($maintainfields) {
  $accum = "";
  foreach ($maintainfields as $field => $value) {
    $accum .= "&amp;".urlencode($field)."=".urlencode($value);
  }
  return $accum;
}
  



## 
## user input validation functions
##

## sanitize user input, allowing "nice" html to pass through unscathed
##
function sdsSanitizeHTML ($input) {
  $tag_list = Array(true, "b", "a", "i", "img", "strong", "em", "p", "u",
                          "big", "small", "pre", "code");
  $rm_tags_with_content = Array("script", "style", "applet", "embed");
  $self_closing_tags = Array("img", "br", "hr", "input");
  $force_tag_closing = true;
  $rm_attnames = Array( "|.*|" => Array( "|target|i", "|^on.*|i")  );
  $bad_attvals = Array( "|.*|" => Array(
             "/^src|background|href|action/i"
               => Array( Array( "/^([\'\"])\s*\S+script\s*:.*([\'\"])/si" ),
                         Array( "\\1http://veryfunny.com/\\2" ) ),
             "/^style/i"
               => Array( Array( "/expression/si",
                                "/url\(([\'\"])\s*https*:.*([\'\"])\)/si",
                                "/url\(([\'\"])\s*\S+script:.*([\'\"])\)/si" ),
                         Array( "idiocy",
                                "url(\\1http://veryfunny.com/\\2)",
                                "url(\\1http://veryfynny.com/\\2)" ) ) ) );

  require_once(SDS_BASE . "/sds/htmlfilter.php");
  $sanitized = sanitize($input, $tag_list, $rm_tags_with_content,
                        $self_closing_tags, $force_tag_closing,
                        $rm_attnames, $bad_attvals, Array());

  return preg_replace("/\s*<!--.*?-->\s*/", "", $sanitized);

  # return strip_tags($input,"<a><b><i><u><em><p><code><pre><big><small><br>");
}

## Remove the magic quotes if they are there.
## (Hopefully they won't be eventually) #'
function maybeStripslashes($string) {
  if(get_magic_quotes_gpc()) {
    return stripslashes($string);
  } else {
    return $string;
  }
}

## Call pg_escape_string after possibly calling stripslashes to
## undo the stupid magic quotes
function sdsSanitizeString($string) {
  if(get_magic_quotes_gpc()) {
    return pg_escape_string(stripslashes($string));
  } else {
    return pg_escape_string($string);
  }
}

## Get an argument as a string, with leading and trailing whitespace stripped
## Also, does not give a warning if no such argument was passed, just returns
## $default
##
## note that an empty string can still be explicitly passed, and will be
## returned (rather than $default)
##
## If you don't want the trim maybeStripslashes, you can use @$_REQUEST instead
function getStringArg($name,$default = '') {
  if(!isset($_REQUEST[$name]))
    return $default;
  return trim(maybeStripslashes($_REQUEST[$name]));
}


## PHP compat 5.2.0
# not a complete implementation, just good enough
if(!function_exists('array_fill_keys')) {
  function array_fill_keys($keys,$value) {
    array_combine($keys,array_fill(0,count($keys),$value));
  }
}
