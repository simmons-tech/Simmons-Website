<?php
require_once('../sds.php');
sdsRequireGroup('DESK');

sdsIncludeHeader('Package Registration');

$oldtime = '6 hours';
$ignoreinterval = 60*60*6; # 6 hours

$currentuser = pg_escape_string($session->username);

if(isset($_REQUEST['ignoreold'])) {
# ignore old entry warnings for a while
# theoretically $ignoreinterval, but more likely session will expire first.
# Oh well.
  $session->data['packages_ignoreold'] = time();
}
if(isset($_REQUEST['dropold'])) {
  $query = "DELETE FROM packages_checkin WHERE deskworker='$currentuser' AND now()-entry_time > INTERVAL '$oldtime'";
  $result = sdsQuery($query);
  if(!$result)
    contactTech("Could not drop packages");
  pg_free_result($result);
}
if(isset($_REQUEST['dropall'])) {
  $query = "DELETE FROM packages_checkin WHERE deskworker='$currentuser'";
  $result = sdsQuery($query);
  if(!$result)
    contactTech("Could not drop packages");
  pg_free_result($result);
}
if(isset($_REQUEST['register'])) {
# register the packages
  $package_count = array();
  $perishable = array();

  $query = "SELECT checkinid,recipient,bin,pkg_count,perishable FROM packages_checkin WHERE deskworker='$currentuser'";
  $result = sdsQuery($query);
  if(!$result)
    contactTech("Could not search pending packages");

  $transres = sdsQuery("BEGIN");
  if(!$transres)
    contactTech("Could not start transaction");
  pg_free_result($transres);

  while($package = pg_fetch_array($result)) {
    $query =
      "INSERT INTO packages (recipient,bin,checkin,checkin_by,perishable) VALUES ('" .
      pg_escape_string($package['recipient']) . "','" .
      pg_escape_string($package['bin']) . "',now(),'$currentuser','" .
      $package['perishable'] . "')";
    for($i=0;$i<$package['pkg_count'];++$i) {
      $insert = sdsQuery($query);
      if(!$insert or pg_affected_rows($insert) != 1) {
	contactTech("Could not register packages",false);
	if(!sdsQuery("ROLLBACK"))
	  contactTech("Could not rollback");
	sdsIncludeFooter();
	exit;
      }
      pg_free_result($insert);
    }

    $query = "DELETE FROM packages_checkin WHERE checkinid=" .
      $package['checkinid'];
    $delete = sdsQuery($query);
    if(!$delete or pg_affected_rows($delete) != 1) {
      contactTech("Could not delete pending packages",false);
      if(!sdsQuery("ROLLBACK"))
	contactTech("Could not rollback");
      sdsIncludeFooter();
      exit;
    }
    pg_free_result($delete);

    if(!isset($package_count[$package['recipient']])) {
      $package_count[$package['recipient']] = 0;
      $perishable[$package['recipient']] = 0;
    }
    $package_count[$package['recipient']] += $package['pkg_count'];
    if($package['perishable']=='t') {
      $perishable[$package['recipient']] += $package['pkg_count'];
    }
  }
  pg_free_result($result);

  $transres = sdsQuery("COMMIT");
  if(!$transres) {
    contactTech("Could not commit",false);
    if(!sdsQuery("ROLLBACK"))
      contactTech("Could not rollback");
    sdsIncludeFooter();
    exit;
  }

  # send mail to recipients
  foreach($package_count as $username => $num_packages) {
    $query = "SELECT email FROM directory WHERE username='".
      pg_escape_string($username)."'";
    $result = sdsQuery($query);
    if(!$result)
      contactTech("Could not search email addresses");

    $subject =
      ($perishable[$username]?"URGENT: Perishable":'New')." Packages at Desk";
    $body =
      "You have $num_packages new package".($num_packages==1?'':'s').
      " at desk" . ($perishable[$username]?
		    ", at least one of which is perishable":'').
      ". Please come and pick ".($num_packages==1?'it':'them')." up ".
      ($perishable[$username]?"ASAP.":"at your earliest convenience.");

    if(SDB_DATABASE === 'sdb') {
      if(!($record = pg_fetch_array($result)) or !$record['email'] or
	 !mail($record['email'],$subject,$body,
	       "From: Simmons Package DB <simmons-tech@mit.edu>\r\n".
	       "Reply-To: sim-desk@mit.edu")) {
	contactTech("Failed to send mail to ".htmlspecialchars($username),
		    false);
      }
    } elseif(!($record = pg_fetch_array($result)) or !$record['email']) {
      echo "<h2 class='error'>Would have failed to send mail to ",
	htmlspecialchars($username)," if this were not a sandbox.</h2>\n";
    }
    pg_free_result($result);
  }
}

# prompt if packages were queued a long time ago and not submited
if(!isset($session->data['packages_ignoreold']) or
   time() - $session->data['packages_ignoreold'] > $ignoreinterval) {
  $query = "SELECT 1 FROM packages_checkin WHERE deskworker='$currentuser' AND now()-entry_time > INTERVAL '$oldtime' LIMIT 1";
  $result = sdsQuery($query);
  if(!$result)
    contactTech("Could not search pending packages");
  if(pg_num_rows($result)>0) {
?>

<h2>
  You entered packages over <?php echo $oldtime ?> ago and have not submitted
  them yet.
</h2>

<form action="checkin.php" method="post">
  <?php echo sdsForm() ?>
  <table style="width: 100%">
    <tr>
      <td>
        <input type="submit" name="ignoreold" value="Continue, keeping old entries" />
      </td>
      <td>
        <input type="submit" name="dropold" value="Drop old entries" />
      </td>
      <td>
        <input type="submit" name="dropall" value="Drop all unsubmitted entries" />
      </td>
    </tr>
  </table>
</form>

<?php
   sdsIncludeFooter();
   exit;
  }
  pg_free_result($result);
}

?>
<form action="checkinedit.php" method="get">
  <?php echo sdsForm() ?>
  <input type="submit" value="Add Packages" tabindex="1" />
</form>

<?php

$query = "SELECT checkinid,recipient,bin,pkg_count,perishable FROM packages_checkin WHERE deskworker='$currentuser' ORDER BY recipient,bin";
$result = sdsQuery($query);
if(!$result)
  contactTech("Could not search pending packages");

if(pg_num_rows($result)>0) {
?>

<table class="packagetable">
  <tr>
    <th>Recipient</th>
    <th>Bin</th>
    <th>Pkgs</th>
    <th>Perishable</th>
    <th>Actions</th>
  </tr>
<?php
  $currentrecipient = '';
  while($package = pg_fetch_array($result)) {
    echo "  <tr>\n";
    if($currentrecipient === $package['recipient']) {
      echo "    <td></td>\n";
    } else {
      echo "    <td>", sdsGetFullName($package['recipient']),"</td>\n";
    }
?>
    <td><?php echo htmlspecialchars($package['bin']) ?></td>
    <td><?php echo $package['pkg_count'] ?></td>
    <td><?php echo $package['perishable']==='t'?'Yes':'No' ?></td>
    <td>
      <form action="checkinedit.php" method="post">
        <?php echo sdsForm() ?>
        <input type="hidden" name="checkinid" value="<?php echo $package['checkinid'] ?>" />
        <input type="submit" name="edit" value="Edit" />
        <input type="submit" name="delete" value="Delete" />
      </form>
    </td>
  </tr>
<?php
  $currentrecipient = $package['recipient'];
  }
?>
</table>
<form action="checkin.php" method="post">
  <?php echo sdsForm() ?>
  <input type="submit" name="register" value="Register Packages" />
  <input type="submit" name="dropall" value="Delete All" />
</form>

<?php
} else {
  echo "<h2>No Pending Package Submissions</h2>\n";
}
pg_free_result($result);

sdsIncludeFooter();
