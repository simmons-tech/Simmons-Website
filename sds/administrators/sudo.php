<?php
require_once("../sds.php");

# require administrators
sdsRequireGroup("ADMINISTRATORS");

if(!empty($_POST["be"]) and !empty($_POST['user'])) {
  $un = $session->username;
  $session = createSession($_POST["user"]);
  sdsSetReminder("sudo","You are <i>".htmlspecialchars($un).
		 "</i>, impersonating <i>".
		 htmlspecialchars($session->username)."</i>.");
  header("Location: " . sdsLink(SDS_HOME_URL));
  exit;
}

sdsIncludeHeader("SDB Sudo");

if (@sdsGetReminder("sudo") !== null) {
  echo "<h2>You are already impersonating another user.  Please re-login as\nyourself and then login as another user</h2>";
} else {

?>

<p>
  Choose a user whose identity you would assume.  The system will treat your
  session as if you were them.
</p>

<h2>Active Users:</h2>

<form action="<?php echo $_SERVER['PHP_SELF'] ?>" method="post">
<?php echo sdsForm() ?>

  <table>

    <tr>
      <td>

        <select size="6" name="user">
<?php
  $result = sdsQuery("SELECT username FROM sds_users ORDER BY username");
  if($result) { 
    while($data=pg_fetch_array($result)) {
      echo "          <option>",htmlspecialchars($data['username']),
	"</option>\n";
    }
    pg_free_result($result);
  }
?>
        </select>

      </td>
    </tr>

    <tr>
      <td align="center">

        <input name="be" type="submit" value="BE">

      </td>
    </tr>
  </table>

</form>


<?php
}
sdsIncludeFooter();
