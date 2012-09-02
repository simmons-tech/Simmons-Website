<?php
require_once("../sds.php");
require_once("user-administration-tools.inc.php");

sdsRequireGroup("RAC");
sdsIncludeHeader("Disable A User");

if(!isset($_REQUEST['username'])) {
  echo "<h2 class='error'>No user specified</h2>\n";
  sdsIncludeFooter();
  exit;
}

$username = maybeStripslashes($_REQUEST['username']);
$username_esc = pg_escape_string($username);
$username_disp = htmlspecialchars($username);

$query = "SELECT 1 FROM sds_users WHERE username='$username_esc'";
$result = sdsQuery($query);
if(!$result)
  contactTech("Could not search users");
if(pg_num_rows($result) != 1) {
  echo "<h2 class='error'>User ",$username_disp,
    " is not an active user.</h2>\n";
  sdsIncludeFooter();
  exit;
}
pg_free_result($result);

echo "<h2>RAC Disable: ",$username_disp,"</h2>\n";

if(!empty($_REQUEST['confirm'])) {
  if(disableUser($username))
    echo "<p>The user ",$username_disp,
      " has been disabled. Groups and Mailing Lists will update within 24 hours.</p>\n";
} else {

  $query = "SELECT firstname,lastname,COALESCE(room,'(none)') AS room,year FROM directory WHERE username='$username_esc'";
  $result = sdsQuery($query);
  if(!$result)
    contactTech("Could not search directory");
  if(pg_num_rows($result) != 1) {
    $userinfo = array('firstname' => '???',
		      'lastname' => '???',
		      'room' => '(none)',
		      'year' => '???');
  } else {
    $userinfo = pg_fetch_array($result);
  }
  pg_free_result($result);

  $userinfo_disp = array();
  foreach($userinfo as $key => $val) {
    $userinfo_disp[$key] = htmlspecialchars($val);
  }

?>

<form action="remove.php" method="post">
<?php echo sdsForm() ?>

<input type="hidden" name="username" value="<?php echo $username_disp ?>" />

<table class="racadd">
  <tr>
    <td class="label">Firstname</td>
    <td><?php echo $userinfo_disp['firstname'] ?></td>
  </tr>
  <tr>
    <td class="label">Lastname</td>
    <td><?php echo $userinfo_disp['lastname'] ?></td>
  </tr>
  <tr>
    <td class="label">Room</td>
    <td><?php echo $userinfo_disp['room'] ?></td>
  </tr>
  <tr>
    <td class="label">Year</td>
    <td><?php echo $userinfo_disp['year'] ?></td>
  </tr>

  <tr>
    <td></td>
    <td>
      <input type="submit" name="confirm" value="Disable this user" />
    </td>
  </tr>

</table>

</form>

<?php
}

sdsIncludeFooter();
