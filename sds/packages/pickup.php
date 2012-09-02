<?php
require_once('../sds.php');
sdsRequireGroup('DESK');

sdsIncludeHeader("Package Pickup");

if(isset($_REQUEST['recipient'])) {
  # have a user selected
  $recipient = maybeStripslashes($_REQUEST['recipient']);
  $recipient_esc = pg_escape_string($recipient);
  if(isset($_REQUEST['do_pickup'])) {
    # actual checkout
    $to_pickup = array();

    foreach(array_keys($_REQUEST['pickup']) as $fieldnum) {
      $binname = sdsSanitizeString($_REQUEST['bin'][$fieldnum]);
      if(!strlen($_REQUEST['pickup'][$fieldnum]) or
	 preg_match('/\D/',$_REQUEST['pickup'][$fieldnum])) {
	echo "<h2 class='error'>Invalid number of packages to pick up.</h2>\n";
	sdsIncludeFooter();
	exit;
      }
      $perish = $_REQUEST['perish'][$fieldnum]?'t':'f';
      $pkgs = (int) $_REQUEST['pickup'][$fieldnum];

# finalize older records first, as we cannot actually distinguish
# between different packages in the same bin
      $query = <<<ENDQUERY
SELECT packageid FROM packages
WHERE recipient='$recipient_esc' AND bin='$binname' AND perishable='$perish'
      AND pickup IS NULL
ORDER BY checkin LIMIT $pkgs
ENDQUERY;
      $result = sdsQuery($query);
      if(!$result)
	contactTech("Could not search packages");
      if(pg_num_rows($result) != $pkgs) {
	echo "<h2 class='error'>There do not seem to be that many packages available.</h2>\n";
	sdsIncludeFooter();
	exit;
      }
      while($package = pg_fetch_array($result)) {
	$to_pickup[] = $package['packageid'];
      }
      pg_free_result($result);
    }

    $currentuser = pg_escape_string($session->username);

    $transres = sdsQuery("BEGIN");
    if(!$transres)
      contactTech("Could not start transaction");
    pg_free_result($transres);

    foreach($to_pickup as $packageid) {
      $query = "UPDATE packages set pickup=now(),pickup_by='$currentuser' WHERE packageid=$packageid";
      $result = sdsQuery($query);
      if(!$result or pg_affected_rows($result) != 1) {
	contactTech("Could not pick up packages",false);
	if(!sdsQuery("ROLLBACK"))
	  contactTech("Could not rollback");
	sdsIncludeFooter();
	exit;
      }
      pg_free_result($result);
    }

    $transres = sdsQuery("COMMIT");
    if(!$transres) {
      contactTech("Could not commit",false);
      if(!sdsQuery("ROLLBACK"))
	contactTech("Could not rollback");
      sdsIncludeFooter();
      exit;
    }
    pg_free_result($transres);

    echo "<h2>Picked up ",count($to_pickup)," packages for ",
      sdsGetFullName($recipient),"</h2>\n";
    echo "<p><a href='",sdsLink("pickup.php"),"'>Return to package pickup</a></p>\n";
  } else {
# present options for which packages to pick up. Default to all.
    $query = <<<ENDQUERY
SELECT bin,perishable,count(*) AS pkg_count
FROM packages
WHERE recipient='$recipient_esc' AND pickup IS NULL
GROUP BY bin,perishable ORDER BY bin,perishable
ENDQUERY;
    $result = sdsQuery($query);
    if(!$result)
      contactTech("Could not search packages");
    if(pg_num_rows($result)) {
?>
<h2>Packages Available for <?php echo sdsGetFullName($recipient) ?></h2>
<form action="pickup.php" method="post">
  <?php echo sdsForm() ?>
  <input type="hidden" name="recipient" value="<?php echo htmlspecialchars($recipient) ?>" />
  <table class="packagetable">
    <tr>
      <th>Check In</th>
      <th>Bin</th>
      <th>Perishable</th>
      <th>Available</th>
      <th>Pick up</th>
    </tr>
<?php
     $binnum = 0;
      while($record = pg_fetch_array($result)) {
	$pickup_constraint = 'false';
	$old_constraint = '';
	$mindate = '';
	$bin_esc = pg_escape_string($record['bin']);
	while($pickup_constraint != $old_constraint) {
	  $query = "SELECT MIN(checkin) FROM packages WHERE recipient='$recipient_esc' AND bin='$bin_esc' AND (pickup IS NULL OR $pickup_constraint)";
	  $dateresult = sdsQuery($query);
	  if(!$dateresult)
	    contactTech("Could not search package ages");
	  list($mindate) = pg_fetch_array($dateresult);
	  pg_free_result($dateresult);
	  $old_constraint = $pickup_constraint;
	  $pickup_constraint = "pickup > TIMESTAMP '$mindate'";
	}
 	$query = <<<ENDQUERY
SELECT DISTINCT checkin_str
FROM (SELECT to_char(checkin,'FMDD Mon HH24:MI') AS checkin_str
      FROM packages
      WHERE recipient='$recipient_esc' AND bin='$bin_esc'
            AND (pickup IS NULL OR $pickup_constraint)
      ORDER BY checkin) AS info
ENDQUERY;
	$checkinresult = sdsQuery($query);
	$checkindatelist = array();
	while(list($checkindate) = pg_fetch_array($checkinresult)) {
	  $checkindatelist[] = htmlspecialchars($checkindate);
	}
	$checkindatestring = implode($checkindatelist,'<br />');
        echo "    <tr>\n";
	echo "      <td>",$checkindatestring,"</td>\n";
	echo "      <td>",htmlspecialchars($record['bin']),"</td>\n";
	echo "      <td>",$record['perishable']=='t'?'Yes':'No',"</td>\n";
	echo "      <td>",$record['pkg_count'],"</td>\n";
	echo "      <td>\n";
	echo "        <input type='hidden' name='perish[$binnum]' value='",
	  $record['perishable']=='t'?'1':'0',"' />\n";
	echo "        <input type='hidden' name='bin[$binnum]' value='",
	  htmlspecialchars($record['bin'],ENT_QUOTES),"' />\n";
        echo "        <select name='pickup[$binnum]'>\n";
	for($i=0;$i<$record['pkg_count'];++$i) {
	  echo "          <option>",$i,"</option>\n";
	}
	echo "          <option selected='selected'>",$record['pkg_count'],
	  "</option>\n";
	echo "        </select>\n";
        echo "      </td>\n";
	echo "    </tr>\n";
	++$binnum;
      }
?>
    <tr>
      <td></td>
      <td></td>
      <td><input type="submit" name="do_pickup" value="Pickup" /></td>
    </tr>
  </table>
</form>
<?php
    } else {
      echo "<h2>No packages are available for ",sdsGetFullName($recipient),
	"</h2>\n";
    }
    pg_free_result($result);
  }
} else {
# select a user who wants packages
  require_once('../directory/directory.inc.php');
  if($finduser = doDirectorySearch()) {
# done a search: show results
    showDirectorySearchResults($finduser,"pickup.php","recipient");
  }
  echo "<p>Pickup packages for:</p>\n";
  showDirectorySearchForm("pickup.php");
}
sdsIncludeFooter();
