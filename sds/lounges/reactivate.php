<?php
require_once('../sds.php');
sdsRequireGroup("LOUNGE-CHAIRS");
require_once('management.inc.php');

if(isset($_REQUEST['lounge'])) {
  $lounge = maybeStripslashes($_REQUEST['lounge']);

# check that values are valid, as contacts may have disappeared since
# the lounge was deativated (of course, active lounges can have contacts
# disappear, but that is hard to deal with)
  $lounge_esc = pg_escape_string($lounge);
  $lounge_disp = htmlspecialchars($lounge);

  $complaint = array();

  $query = "SELECT contact,contact2 FROM lounges WHERE lounge='lounge-$lounge_esc' AND NOT active";
  $result = sdsQuery($query);
  if(!$result)
    contactTech("Could not search lounges");
  if(pg_num_rows($result) != 1)
    display_error("Bad lounge ID: $lounge_disp",true);

  $record = pg_fetch_array($result);
  pg_free_result($result);

  if(isset($_REQUEST['contact'])) {
    $contact = trim(maybeStripslashes($_REQUEST['contact']));
  } else {
    $contact = $record['contact'];
  }
  if(!verify_contact($contact))
    $complaint[] = "first";

  if(isset($_REQUEST['contact2'])) {
    $contact2 = trim(maybeStripslashes($_REQUEST['contact2']));
  } else {
    $contact2 = $record['contact2'];
  }
  if($contact2 === '') {
    $contact2 = null;
  } elseif(!verify_contact($contact2)) {
    $complaint[] = "second";
  }

  if(count($complaint)) {
    sdsIncludeHeader("Lounge Reactivation");
    echo "<h2>Some contacts are no longer valid</h2>\n";
    echo "<p>Please update the ",implode(' and ',$complaint)," contact",
      (count($complaint)==1?'':'s'),".</p>\n";
?>
<form action="reactivate.php" method="post">
<?php echo sdsForm() ?>
<input type="hidden" name="lounge" value="<?php echo $lounge_disp ?>" />
  <table>
    <tr>
      <td>First Contact (required):</td>
      <td><input type="text" name="contact" size="12" value="<?php echo htmlspecialchars($contact) ?>" /></td>
    </tr>
    <tr>
      <td>Second Contact:</td>
      <td><input type="text" name="contact2" size="12" value="<?php echo htmlspecialchars($contact2) ?>" /></td>
    </tr>
  </table>
  <input type="submit" value="Update" />
</form>

<?php
    sdsIncludeFooter();
    exit;
  }
# Data sould all be valid now

  $result = sdsQuery("BEGIN");
  if(!$result)
    contactTech("Could not start transaction");
  pg_free_result($result);

  $change_array = array('contact' => $contact,
			'contact2' => $contact2,
			'active' => 'true');
  $query = "UPDATE lounges SET " . sqlArrayUpdate($change_array) .
    " WHERE lounge = 'lounge-$lounge_esc'";
  $result = sdsQuery($query);
  if(!$result or pg_affected_rows($result) != 1) {
    display_warning("Reactivation of lounge $lounge_disp failed.",true);
    if(!sdsQuery("ROLLBACK"))
      contactTech("Could not rollback");
    sdsIncludeFooter();
    exit;
  }
  pg_free_result($result);
  if(!create_groups($lounge)) {
    if(!sdsQuery("ROLLBACK"))
      contactTech("Could not rollback");
    sdsIncludeFooter();
    exit;
  }

  $result = sdsQuery("COMMIT");
  if(!$result) {
    contactTech("Could not commit",false);
    if(!sdsQuery("ROLLBACK"))
      contactTech("Could not rollback");
    sdsIncludeFooter();
    exit;
  }
  pg_free_result($result);
}

lounges_done('#current');
