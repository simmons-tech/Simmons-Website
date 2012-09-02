<?php
require_once('../sds.php');
sdsRequireGroup('DESK');

$currentuser = pg_escape_string($session->username);

unset($to_checkin);

unset($checkinid);
unset($recipient);
if(isset($_REQUEST['checkinid']) and strlen($_REQUEST['checkinid']) and
   !preg_match('/\D/',$_REQUEST['checkinid'])) {
  $checkinid = $_REQUEST['checkinid'];
  $query = "SELECT recipient FROM packages_checkin WHERE checkinid=$checkinid AND deskworker='$currentuser'";
  $result = sdsQuery($query);
  if(!$result)
    contactTech("Could not search pending packages");
  if(pg_num_rows($result)==1) {
    list($recipient) = pg_fetch_array($result);
    $recipient_esc = pg_escape_string($recipient);
  } else {
    unset($checkinid);
  }
  pg_free_result($result);
}
if(isset($checkinid) and isset($_REQUEST['delete'])) {
  $query = "DELETE FROM packages_checkin WHERE checkinid=$checkinid";
  $result = sdsQuery($query);
  if(!$result or pg_affected_rows($result)!=1)
    contactTech("Could not delete package");
  pg_free_result($result);
  $to_checkin = 1;
}
if(!isset($recipient) and isset($_REQUEST['recipient'])) {
  $recipient = maybeStripslashes($_REQUEST['recipient']);
  $recipient_esc = pg_escape_string($recipient);

# make sure they live here
  $query = "SELECT 1 FROM sds_group_membership_cache WHERE username='$recipient_esc' AND groupname='PACKAGE-RECIPIENTS'";
  $result = sdsQuery($query);
  if(!$result)
    contactTech("Could not check residence");
  if(pg_num_rows($result) == 0) {
    sdsIncludeHeader('Package Registration');
    echo "<h2 class='error'>",sdsGetFullName($recipient),
      " is not allowed to receive packages.</h2>\n";
    sdsIncludeFooter();
    exit;
  }
  pg_free_result($result);
}
if(isset($recipient) and
   (isset($_REQUEST['do_update']) or isset($_REQUEST['add_new']))) {
  $to_checkin = 1;
  $query = "SELECT checkinid FROM packages_checkin WHERE deskworker='$currentuser' AND recipient='$recipient_esc'";
  $result = sdsQuery($query);
  if(!$result)
    contactTech("Could not search pending packages");
  while($package = pg_fetch_array($result)) {
    $id = $package['checkinid'];

    $bin = sdsSanitizeString($_REQUEST['bin'][$id]);
    if(!strlen($bin)) {
      sdsIncludeHeader('Package Registration');
      echo "<h2 class='error'>Please give a bin.</h2>\n";
      sdsIncludeFooter();
      exit;
    }
    $pkg_count = $_REQUEST['pkg_count'][$id];
    if(!strlen($pkg_count) or preg_match('/\D/',$pkg_count)) {
      sdsIncludeHeader('Package Registration');
      echo "<h2 class='error'>Number of packages should be a number.</h2>\n";
      sdsIncludeFooter();
      exit;
    }
    $query = "UPDATE packages_checkin SET bin='$bin',pkg_count=$pkg_count,entry_time=now(),perishable=".(isset($_REQUEST['perishable'][$id])?"true":"false")." WHERE checkinid=$id";
    $update = sdsQuery($query);
    if(!$result or pg_affected_rows($update) != 1)
      contactTech("Could not update pending packages");
    pg_free_result($update);
  }

  $do_add = 1;

  $bin = sdsSanitizeString($_REQUEST["bin_new"]);
  if(!strlen($bin)) { unset($do_add); }
  $pkg_count = $_REQUEST["pkg_count_new"];
  if(!strlen($pkg_count) or preg_match('/\D/',$pkg_count)) { unset($do_add); }

  if(isset($do_add)) {
    $query = "INSERT INTO packages_checkin (recipient,bin,pkg_count,deskworker,entry_time,perishable) VALUES ('$recipient_esc','$bin',$pkg_count,'$currentuser',now(),".(isset($_REQUEST["perishable_new"])?"true":"false").")";
    $result = sdsQuery($query);
    if(!$result or pg_affected_rows($result) != 1)
      contctTech("Could not update pending packages");
    pg_free_result($result);
  }
  if(isset($_REQUEST['add_new'])) { unset($to_checkin); }
}

if(isset($to_checkin)) {
  header("Location: " . SDS_BASE_URL . sdsLink("packages/checkin.php"));
  exit;
}

sdsIncludeHeader('Package Registration');

if(isset($recipient)) {
  $query = "SELECT checkinid,bin,pkg_count,perishable FROM packages_checkin WHERE deskworker='$currentuser' AND recipient='$recipient_esc' ORDER BY bin";
  $result = sdsQuery($query);
  if(!$result)
    contactTech("Could not search pending packages");

  echo "<h2>Packages for ",sdsGetFullName($recipient),"</h2>\n";
  echo "<form action='checkinedit.php' method='post'>\n";
  echo sdsForm();
  echo "  <input type='hidden' name='recipient' value='",
    htmlspecialchars($recipient,ENT_QUOTES),"' />\n";
  echo "  <table>\n";
  echo "    <tr>\n";
  echo "      <th>Bin</th>\n";
  echo "      <th>Pkgs</th>\n";
  echo "      <th>Perishable</th>\n";
  echo "    </tr>\n";
  while($package = pg_fetch_array($result)) {
    $id = $package['checkinid'];
    echo "    <tr>\n";
    echo "      <td><input type='text' name='bin[$id]' value='",
      htmlspecialchars($package['bin'],ENT_QUOTES),"' size='7' /></td>\n";
    echo "      <td><input type='text' name='pkg_count[$id]' value='",
      $package['pkg_count'],"' size='7' /></td>\n";
    echo "      <td><label><input type='checkbox' name='perishable[$id]'",
      $package['perishable']=='t'?' checked="checked"':'',
      " />Perishable</label></td>\n";
    echo "    </tr>\n";
  }
?>
    <tr style="vertical-align: top">
      <td><input type="text" name="bin_new" size="7" /></td>
      <td><!-- This puts the real submit button first so <Enter> will work -->
        <input type="text" name="pkg_count_new" size="7" value="1"/><br />
        <input type="submit" name="do_update" value="Update" />
      </td>
      <td>
        <label><input type="checkbox" name="perishable_new" />Perishable</label>
      </td>
      <td><input type="submit" name="add_new" value="Add" /></td>
    </tr>
  </table>
</form>
<?php
} else {
  require_once('../directory/directory.inc.php');
  if($finduser = doDirectorySearch()) {
# done a search: show results
    showDirectorySearchResults($finduser,"checkinedit.php","recipient");
  }
  echo "<p>Packages for:</p>\n";
  showDirectorySearchForm("checkinedit.php");
}
sdsIncludeFooter();
