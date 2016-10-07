<?php
require_once('../sds.php');
sdsRequireGroup("LOUNGE-CHAIRS");

sdsIncludeHeader("Remove User From Lounge");

$username = $_REQUEST['username'];

  $username_esc = pg_escape_string($username);

  $query = "SELECT 1 FROM sds_users WHERE username='$username_esc'";
  $result = sdsQuery($query);
  if(!$result) {
    contactTech("Could not search users",false);
    return null;
  }
  if(pg_num_rows($result) != 1) {
    pg_free_result($result);
    echo "<p class='error'>",htmlspecialchars($username),
      " is not an active user</p>\n";
    return false;
  }
  pg_free_result($result);
  # one query = one transaction, so rollback occurs if something fails
  $query = "UPDATE directory SET lounge=null,loungevalue=null WHERE username='$username_esc';";
  $result = sdsQuery($query);
  if(!$result) {
    contactTech("Could not disable user",false);
    return null;
  }
  pg_free_result($result);
 echo '<p>' . $username_esc . ' has been removed from the lounge.</p>\n'; 
echo '<p>Return to <a href="',sdsLink("./"),
  "\">Lounge Administration</a></p>\n";
sdsIncludeFooter();

?>
