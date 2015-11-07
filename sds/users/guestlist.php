<?php
require_once("../sds.php");

sdsRequireGroup("USERS");

$sdsFields = sdsForm();

$username = $session->username;
$username_esc = pg_escape_string($username);

function monthOpts($default = null) {
  if(!isset($default)) $default = strftime("%m");
  for($i=1;$i<=12;$i++) {
    echo '<option value="',$i,'"',
      $default==$i?' selected="selected"':'',">",
      strftime("%B",mktime(0,0,0,$i,15)),"</options>\n";
  }
}
function dayOpts($default = null) {
  if(!isset($default)) $default = strftime("%d");
  for($i=1;$i<=31;$i++) {
    echo '<option value="',$i,'"',
      $default==$i?' selected="selected"':'',">",$i,"</options>\n";
  }
}
function hourOpts($default = null) {
  if(!isset($default)) $default = strftime("%H");
  for($i=0;$i<24;$i++) {
    printf("<option value='%d'%s>%02d</options>\n",
	   $i,$default==$i?' selected="selected"':'',$i);
  }
}
function minOpts($default = null) {
  if(!isset($default)) $default = ((int) (strftime("%M")/15))*15;
  for($i=0;$i<60;$i+=15) {
    printf("<option value='%d'%s>%02d</options>\n",
	   $i,$default==$i?' selected="selected"':'',$i);
  }
}

function addGuest($username,$guest,$expir_sql) {
  $username_esc = pg_escape_string($username);
  $guest_esc = pg_escape_string($guest);
  $query = <<<ENDQUERY
SELECT 1
FROM guest_list
WHERE username='$username_esc' AND guest='$guest_esc' AND current
ENDQUERY;
  $result = sdsQuery($query);
  if(!$result)
    contactTech("Could not search guest list");
# do not add duplicate records
  if(pg_num_rows($result)==0) {
# limit to 25 people per guest list
    $query = "SELECT COUNT(*) FROM guest_list WHERE username='$username_esc' AND current";
    $countresult = sdsQuery($query);
    if(!$countresult)
      contactTech("Could not count guests");
    list($count) = pg_fetch_array($countresult);
    pg_free_result($countresult);
    if($count >= 25) { return false; }

# Please contact ambhave@mit.edu if you have any questions about the following:
    if (strstr(strtolower($guest_esc), 'ferrante')) {
      mail('jessig@mit.edu','Simmons DB: Guest Flagged: ' . $guest_esc,
        "The following Simmons guest was flagged:\r\n\r\nGuest: ".$guest_esc."\r\nGuest Of: ".$username_esc,
        "From: ambhave@mit.edu\r\nReply-to: ambhave@mit.edu");
    }


    $query = <<<ENDQUERY
INSERT INTO guest_list
       (username,       guest,       date_added,date_invalid,current,onetime)
VALUES ('$username_esc','$guest_esc',now(),     $expir_sql,  true,   false)
ENDQUERY;
    $editresult = sdsQuery($query);
    if(!$result or pg_affected_rows($editresult) != 1)
      contactTech("Could not add guest");
    pg_free_result($editresult);
  }
  pg_free_result($result);
  return true;
}

function renewGuest($username,$guestlistid,$expir_sql) {
  $username_esc = pg_escape_string($username);
  $query = <<<ENDQUERY
SELECT guest,date_invalid < now() AS invalid,
       date_invalid < now() + interval '1 day' AS renew
FROM guest_list
WHERE username='$username_esc' AND guestlistid=$guestlistid AND current
ENDQUERY;
  $result = sdsQuery($query);
  if(!$result)
    contactTech("Could not search guest list");
# do not add duplicate records
  $record = pg_fetch_array($result);
  pg_free_result($result);
  if($record) {
    if($record['renew'] === 't') {
      if($record['invalid'] === 't') {
# Entry has expired. Set it to not current and add a new one.
	$query = "UPDATE guest_list SET current=false WHERE guestlistid=$guestlistid;";
	$guest_esc = pg_escape_string($record['guest']);
	$query .= <<<ENDQUERY
INSERT INTO guest_list
       (username,       guest,       date_added,date_invalid,current,onetime)
VALUES ('$username_esc','$guest_esc',now(),     $expir_sql,  true,   false);
ENDQUERY;
        $result = sdsQuery($query);
	if(!$result)
	  contactTech("Could not renew guest ".
		      htmlspecialchars($record['guest']));
	pg_free_result($result);
      } else {
# reset for the next month
	$query = "UPDATE guest_list SET date_invalid = $expir_sql WHERE guestlistid=$guestlistid";
	$result = sdsQuery($query);
	if(!$result or pg_affected_rows($result) != 1)
	  contactTech("Could not renew guest ".
		      htmlspecialchars($record['guest']));
	pg_free_result($result);
      }
    }
    return true;
  }
  return false;
}

function deleteGuest($username,$guestlistid) {
  $username_esc = pg_escape_string($username);
  $query = "SELECT guest,date_invalid < now() AS invalid FROM guest_list WHERE username='$username_esc' AND guestlistid='$guestlistid' AND current";
  $result = sdsQuery($query);
  if(!$result)
    contactTech("Could not search guest list");
  $record = pg_fetch_array($result);
  pg_free_result($result);
  if($record) {
    $query = "UPDATE guest_list SET current=false" .
      ($record['invalid']==='t' ? "" : ",date_invalid=now()") .
      " WHERE guestlistid=$guestlistid";
    $result = sdsQuery($query);
    if(!$result or pg_affected_rows($result) != 1)
      contactTech("Could not delete guest ".
		  htmlspecialchars($record['guest']));
    pg_free_result($result);
    return true;
  }
  return false;
}

function addOneTimeGuest($username,$guest,$starttime,$endtime) {
  $username_esc = pg_escape_string($username);
  $guest_esc = pg_escape_string($guest);
  $query = <<<ENDQUERY
SELECT guestlistid
FROM guest_list
WHERE username='$username_esc' AND guest='$guest_esc' AND onetime AND
      date_invalid > now()
ENDQUERY;
  $result = sdsQuery($query);
  if(!$result)
    contactTech("Could not search guest list");
# do not add duplicate records
  if(pg_num_rows($result)==0) {
    $query = <<<ENDQUERY
INSERT INTO guest_list
       (username,       guest,       date_added,
        date_invalid,        current,onetime)
VALUES ('$username_esc','$guest_esc',timestamp '$starttime',
        timestamp '$endtime',false,  true)
ENDQUERY;
    $editresult = sdsQuery($query);
    if(!$editresult or pg_affected_rows($editresult) != 1)
      contactTech("Could not add guest ".
		  htmlspecialchars($guest));
    pg_free_result($editresult);
  }
  pg_free_result($result);
  return true;
}

# this should only be called before the start time of the event
function deleteOneTimeGuest($username,$guestlistid) {
  $username_esc = pg_escape_string($username);
  $query = "SELECT guest FROM guest_list WHERE username='$username_esc' AND guestlistid='$guestlistid' AND onetime AND date_invalid > now()";
  $result = sdsQuery($query);
  if(!$result)
    contactTech("Could not search guest list");
  $record = pg_fetch_array($result);
  pg_free_result($result);
  if($record) {
    $query = "DELETE FROM guest_list WHERE guestlistid=$guestlistid";
    $result = sdsQuery($query);
    if(!$result or pg_affected_rows($result) != 1)
      contactTech("Failed to delete entry for " .
		  htmlspecialchars($record['guest']));
    pg_free_result($result);
    return true;
  }
  return false;
}

function makePostgresDate($month,$day,$hour,$min) {
  $date = pg_escape_string((strftime("%Y") +
			    ($month < (int) strftime("%m") ? 1 : 0)) . '-' .
			   $month . '-' . $day . ' ' . $hour . ':' . $min);
  $result = sdsQueryTest("SELECT timestamp '$date'");
  if(!$result) { return false; }
  pg_free_result($result);
  return $date;
}

# find (and set) chosen expiration type
$expupdate = false; // Flag to update current expiration dates later
if($newexp = getStringArg('expiration_mode')) {
  if($newexp === 'month' or
     $newexp === 'year') {
    $query = "UPDATE directory SET guest_list_expiration='$newexp' WHERE username='$username_esc'";
    $result = sdsQuery($query);
    if(!$result or pg_affected_rows($result) != 1)
      contactTech("Expiration update failed");
    pg_free_result($result);
    $expupdate = true;
  }
}

$expiration = 'month';
$query = "SELECT guest_list_expiration FROM directory WHERE username='$username_esc'";
$result = sdsQuery($query);
if(!$result)
  contactTech("Could not find guest list expiration mode");
if(pg_num_rows($result) == 1)
  list($expiration) = pg_fetch_array($result);
pg_free_result($result);

if($expiration === 'year') {
  if(mktime(0,0,0,6,10,date('Y')) < time())
    $yearend = gmmktime(0,0,0,6,10,date('Y')+1);
  else
    $yearend = gmmktime(0,0,0,6,10,date('Y'));
  $expiration_sql = "timestamp 'epoch' + interval '$yearend seconds' + interval '1 day'";
} else { # default = month
  $expiration = 'month';
  $expiration_sql = "date_trunc('month', now()) + interval '1 month 1 day'";
}

if($expupdate) {
  # only update current entries that are not in their renewal period (or
  # expired) (current implies !onetime)
  $query = <<<ENDQUERY
UPDATE guest_list
SET date_invalid = $expiration_sql
WHERE username='$username_esc' AND current AND
      date_invalid > now() + interval '1 day'
ENDQUERY;
  $result = sdsQuery($query);
  if(!$result)
    contactTech("Could not adjust expiration times");
  pg_free_result($result);
}

$complaint = '';
$complaint_onetime='';

if(empty($_REQUEST['issure']) and isset($_REQUEST['guestlistid'])) {
  $goners = array();
  foreach(array_keys((array) $_REQUEST['guestlistid']) as $guestlistid) {
    if(strlen($guestlistid) and !preg_match('/\D/',$guestlistid)) {
      if(isset($_REQUEST['delete'][$guestlistid])) {
	$query = "SELECT guest FROM guest_list WHERE username='$username_esc' AND guestlistid=$guestlistid";
	$result = sdsQuery($query);
	if(!$result)
	  contactTech("Could not search guest list");
	if($record = pg_fetch_object($result)) {
	  $goners[] = $record->guest;
	}
	pg_free_result($result);
      }
    }
  }

  if(count($goners)) {
    sdsIncludeHeader("Guest List");
    echo "<p>Are you sure you want to delete the following entries?</p>\n";
    echo "<ul>\n";
    foreach($goners as $goner) {
      echo "  <li>",htmlspecialchars($goner),"</li>\n";
    }
    echo "</ul>\n";
    echo "<form action='guestlist.php' method='post'>\n";
    echo sdsForm();

    foreach(array_keys((array) $_REQUEST['guestlistid']) as $id)
      echo "  <input type='hidden' name='guestlistid[", htmlspecialchars($id),
      "]' value='1' />\n";
    foreach(array_keys((array) $_REQUEST['delete']) as $id)
      echo "  <input type='hidden' name='delete[", htmlspecialchars($id),
      "]' value='1' />\n";
    if(!empty($_REQUEST['newguest']))
      echo "  <input type='hidden' name='newguest' value='",
	htmlspecialchars($_REQUEST['newguest']), "' />\n";

    echo "  <input type='submit' name='issure' value='Yes' />\n";
    echo "  <input type='submit' name='issure' value='No' />\n";
    echo "</form>\n";
    sdsIncludeFooter();
    exit;
  }
}

if(!empty($_REQUEST['issure']) and $_REQUEST['issure'] !== 'Yes')
  unset($_REQUEST);


if(isset($_REQUEST['guestlistid'])) {
  foreach(array_keys((array) $_REQUEST['guestlistid']) as $guestlistid) {
    if(strlen($guestlistid) and !preg_match('/\D/',$guestlistid)) {
      if(isset($_REQUEST['delete'][$guestlistid])) {
	if(!deleteGuest($username,$guestlistid)) {
	  $complaint .= "<p class='error'>Attempt to delete a person not on your guest list.</p>\n";
	}
      } else {
	if(!renewGuest($username,$guestlistid,$expiration_sql)) {
	  $complaint .= "<p class='error'>Attempt to renew a person not on your guest list.</p>\n";
	}
      }
    }
  }
}
if(preg_match('/\S/',@$_REQUEST['newguest'])) {
  if(!addGuest($username,getStringArg('newguest'),$expiration_sql)) {
    $complaint .= "<p class='error'>You sure have a lot of friends.  Unfortunately, 25 is the max for your Guest List.</p>";
  }
}

$query = <<<ENDQUERY
SELECT date_added < now() AS active
FROM guest_list
WHERE username='$username_esc' AND date_invalid > now() AND onetime
LIMIT 1
ENDQUERY;
$result = sdsQuery($query);
if(!$result)
  contactTech("Could not search guest list");
$times = array('active' => 'f');
if(pg_num_rows($result) == 1) {
  $times = pg_fetch_array($result);
}
pg_free_result($result);

$onetime_added = true;

if($times['active'] !== 't') {
  if(isset($_REQUEST['onetimeid'])) {
    foreach(array_keys((array) $_REQUEST['onetimeid']) as $guestlistid) {
      if(strlen($guestlistid) and !preg_match('/\D/',$guestlistid)) {
	if(isset($_REQUEST['delete'][$guestlistid])) {
	  if(!deleteOneTimeGuest($username,$guestlistid)) {
	    $complaint_onetime .= "<p class='error'>Attempt to delete a person not on your guest list.</p>\n";
	  }
	}
      }
    }
  }
  $starttime = makePostgresDate((int) @$_REQUEST['startmonth'],
				(int) @$_REQUEST['startday'],
				(int) @$_REQUEST['starthour'],
				(int) @$_REQUEST['startmin']);
  $endtime = makePostgresDate((int) @$_REQUEST['endmonth'],
			      (int) @$_REQUEST['endday'],
			      (int) @$_REQUEST['endhour'],
			      (int) @$_REQUEST['endmin']);
  if($starttime and $endtime) {
    if($starttime === $endtime) {
      $onetime_added = false;
      $complaint_onetime .=
	"<p class='error'>Please give different event start and end times</p>\n";
    } else {
      $query = <<<ENDQUERY
SELECT timestamp '$starttime' < now() + interval '1 week' AND
       timestamp '$starttime' < timestamp '$endtime' AND
       timestamp '$endtime' <
               timestamp '$starttime' + interval '24 hours 1 second' AND
       timestamp '$starttime' > now() - interval '15 minutes' AS valid
ENDQUERY;
      $result = sdsQuery($query);
      if(!$result)
	contactTech("Could not check timestamps");
      list($valid) = pg_fetch_array($result);
      pg_free_result($result);
      if($valid === 't') {
	$query = <<<ENDQUERY
SELECT date_added != timestamp '$starttime' OR
       date_invalid != timestamp '$endtime' AS timechange
FROM guest_list WHERE username='$username_esc' AND date_invalid > now() AND
     onetime
ENDQUERY;
	$result = sdsQuery($query);
	if(!$result)
	  contactTech("Can't compare times");
	if(pg_num_rows($result)) {
	  list($timechange) = pg_fetch_array($result);
	  if($timechange === 't') {
	    $query = "UPDATE guest_list SET date_added = timestamp '$starttime', date_invalid = timestamp '$endtime' WHERE username='$username_esc' AND onetime AND date_invalid > now()";
	    $editresult = sdsQuery($query);
	    if(!$editresult or pg_affected_rows($editresult) != 1)
	      contactTech("Could not change times");
	    pg_free_result($editresult);
	  }
	}
	pg_free_result($result);
	if(preg_match('/\S/',$_REQUEST['newonetime'])) {
	  addOneTimeGuest($username,
			  trim(maybeStripslashes($_REQUEST['newonetime'])),
			  $starttime,$endtime);
	}
      } else {
	$onetime_added = false;
	$complaint_onetime .= "<h2 class='error'>Invalid event times</h2>\n";
      }
    }
  }
}

# Query for one-time list, needed to produce style sheet
$query = <<<ENDQUERY
SELECT EXTRACT(MONTH FROM date_added) AS startmonth,
       EXTRACT(DAY FROM date_added) AS startday,
       EXTRACT(HOUR FROM date_added) AS starthour,
       EXTRACT(MINUTE FROM date_added) AS startmin,
       EXTRACT(MONTH FROM date_invalid) AS endmonth,
       EXTRACT(DAY FROM date_invalid) AS endday,
       EXTRACT(HOUR FROM date_invalid) AS endhour,
       EXTRACT(MINUTE FROM date_invalid) AS endmin,
       date_added < now() AS active
FROM guest_list
WHERE username='$username_esc' AND date_invalid > now() AND onetime
LIMIT 1
ENDQUERY;
$result = sdsQuery($query);
if(!$result)
  contactTech("Could not find times");
$times = array('active'     => null,
	       'startmonth' => null,
	       'startday'   => null,
	       'starthour'  => null,
	       'startmin'   => null,
	       'endmonth' => null,
	       'endday'   => null,
	       'endhour'  => null,
	       'endmin'   => null);
if(pg_num_rows($result) == 1) {
  $times = pg_fetch_array($result);
}
pg_free_result($result);

sdsIncludeHeader("Guest List", "Guest List for $username", <<<ENDHEAD
<meta http-eqiv="Content-Script-Type" content="text/javascript" />
<style type="text/css">
td.month {
 width: 7em;
 text-align: right;
}
td.day,td.min {
 width: 3em;
 text-align: left;
}
td.hour {
 width: 3em;
 text-align: right;
}

ENDHEAD
. ($times['active']=='t' ? <<<HEADACTIVE
td.select { display: none; }
</style>
HEADACTIVE
: <<<HEADINACTIVE
td.const { display: none; }
</style>
HEADINACTIVE
   ));

if($complaint) { echo $complaint; }

# Check if this user is a resident
if(!$session->groups['RESIDENTS']) {
?>
<p class="error">
  You do not appear to be a resident of Simmons Hall. Your guest list CANNOT be
  used to verify visitors wishing to enter the building. If you believe this to
  be an error, please contact <a href="mailto:simmons-tech@mit.edu">Simmons
  Tech</a>.
</p>
<?php
}

?>
<h2>Renewal Mode</h2>
<form action="" method="post">
<?php echo sdsForm() ?>
  <label><input type="radio" name="expiration_mode" value="month"<?php echo $expiration==='month'?' checked="checked"':'' ?> />Monthly</label>
  <label><input type="radio" name="expiration_mode" value="year"<?php echo $expiration==='year'?' checked="checked"':'' ?> />End of Year</label>
  <br />
  <input type="submit" value="Update" />
</form>

<h2>Guest List</h2>
<?php

$query = "SELECT 1 FROM guest_list WHERE username='$username_esc' AND date_invalid < now() + interval '1 day' AND current";
$result = sdsQuery($query);
if(!$result)
  contactTech("Could not check validity");
$defaultchecked = pg_num_rows($result) ? ' checked="checked"' : '';
pg_free_result($result);

if($defaultchecked) {
?>
<p style="color: red">
  <b>Guest List Verification:</b> Your guest list requires verification.
  Please <b>uncheck the box</b> for each person you still want on your guest
  list and press <b>Update</b>.
</p>
<?php
}
?>

<form action="guestlist.php" method="post">
  <?php echo $sdsFields ?>

  <table>
    <tr bgcolor="#FFBBBB">
      <th>Remove</th>
      <th>Name (Last, First)</th>
    </tr>

<?php

$query = "SELECT guestlistid,guest FROM guest_list WHERE username='$username_esc' AND current ORDER BY guest";

$result = sdsQuery($query);
if(!$result)
  contctTech("Could not search guest list");

while($data = pg_fetch_array($result)) {

?>
    <tr>
      <td>
        <input type="hidden" name="guestlistid[<?php echo $data['guestlistid'] ?>]" value="1" />
        <input type="checkbox" name="delete[<?php echo $data['guestlistid'] ?>]"<?php echo $defaultchecked ?> /></td>
      <td><?php echo htmlspecialchars($data['guest']) ?></td>
    </tr>
<?php
}

$count = pg_num_rows($result);
pg_free_result($result);

if($count < 25) {
?>
    <tr>
      <td>New:</td>
      <td><input name="newguest" type="text" size="20" /></td>
    </tr>
<?php
}
?>
    <tr>
      <td></td>
      <td><input type="submit" value="Update" /></td>
    </tr>
  </table>
</form>

<p>
  This is your portion of the Simmons Hall electronic guest list. Your
  visitors do <u>not</u> have to be on this list in order to visit. Your
  entries here serve only as a convenience to you and to the desk workers.
  They can use this system to verify visitors without having to call you down
  to desk.
</p>
<p>
  To add a person to your guest list, type his or her full name as it would
  appear on an ID card, but with last name first (e.g. "Holl, Simon").
  Then click <b>Update</b>.
</p>

<h2 id="onetime">One-Time Guest List</h2>
<p>
  Any Simmons resident can provide a single use guest list for a large event.
  This guest list can be created no more than one week in advance and can be
  valid for no longer than 24 hours.
</p>

<?php if($complaint_onetime) { echo $complaint_onetime; } ?>
<form action="guestlist.php#onetime" method="post">
  <?php echo sdsForm() ?>
  <table>
    <tr>
      <td>Event Start:</td>
      <td id="startmonthconst" class="month const">
<?php
echo "      ",strftime("%B",mktime(0,0,0,$times['startmonth'],15)),"\n";
?>
      </td>
      <td id="startmonthselect" class="month select">
        <select name="startmonth" onchange="daterestrict('start','month')">
<?php monthOpts($times['startmonth']) ?>
        </select>
      </td>
      <td id="startdayconst" class="day const"><?php echo $times['startday'] ?></td>
      <td id="startdayselect" class="day select">
        <select name="startday" onchange="daterestrict('start','day')">
<?php dayOpts($times['startday']) ?>
        </select>
      </td>
      <td>at</td>
      <td id="starthourconst" class="hour const"><?php printf("%02d",$times['starthour']) ?></td>
      <td id="starthourselect" class="hour select">
        <select name="starthour" onchange="daterestrict('start','hour')">
<?php hourOpts($times['starthour']) ?>
        </select>
      </td>
      <td>:</td>
      <td id="startminconst" class="min const"><?php printf("%02d",$times['startmin']) ?></td>
      <td id="startminselect" class="min select">
        <select name="startmin" onchange="daterestrict('start','min')">
<?php minOpts($times['startmin']) ?>
        </select>
      </td>
    </tr>
    <tr>
      <td>Event End:</td>
      <td id="endmonthconst" class="month const">
<?php
echo "      ",strftime("%B",mktime(0,0,0,$times['endmonth'],15)),"\n";
?>
</td>
      <td id="endmonthselect" class="month select">
        <select name="endmonth" onchange="daterestrict('end','month')">
<?php monthOpts($times['endmonth']) ?>
        </select>
      </td>
      <td id="enddayconst" class="day const"><?php echo $times['endday'] ?></td>
      <td id="enddayselect" class="day select">
        <select name="endday" onchange="daterestrict('end','day')">
<?php dayOpts($times['endday']) ?>
        </select>
      </td>
      <td>at</td>
      <td id="endhourconst" class="hour const"><?php printf("%02d",$times['endhour']) ?></td>
      <td id="endhourselect" class="hour select">
        <select name="endhour" onchange="daterestrict('end','hour')">
<?php hourOpts($times['endhour']) ?>
        </select>
      </td>
      <td>:</td>
      <td id="endminconst" class="min const"><?php printf("%02d",$times['endmin']) ?></td>
      <td id="endminselect" class="min select">
        <select name="endmin" onchange="daterestrict('end','min')">
<?php minOpts($times['endmin']) ?>
        </select>
      </td>
    </tr>
  </table>

  <table>
    <tr bgcolor="#FFBBBB">
<?php
if($times['active'] !== 't') {
  echo "      <th>Remove</th>\n";
}
?>
      <th>Name (Last, First)</th>
    </tr>

<?php

$query = "SELECT guestlistid,guest FROM guest_list WHERE username='$username_esc' AND onetime AND date_invalid > now() ORDER BY guest";

$result = sdsQuery($query);
if(!$result)
  contactTech("Could not search guest list");

while($data = pg_fetch_array($result)) {
  echo "    <tr>\n";
  if($times['active'] !== 't') {
?>
      <td>
        <input type="hidden" name="onetimeid[<?php echo $data['guestlistid'] ?>]" value="1" />
        <input type="checkbox" name="delete[<?php echo $data['guestlistid'] ?>]" /></td>
<?php
  }
?>
      <td><?php echo htmlspecialchars($data['guest']) ?></td>
    </tr>
<?php
}
if($times['active'] !== 't') {
  $value_str = '';
  if(!$onetime_added)
    $value_str = ' value="'.htmlspecialchars($_REQUEST['newonetime']).'"';
?>
    <tr>
      <td>New:</td>
      <td><input name="newonetime" type="text" size="20" <?php echo $value_str ?>/></td>
    </tr>
      <td></td>
      <td><input type="submit" value="Update" /></td>
    </tr>
<?php
}
?>
  </table>
</form>

<?php
if($times['active'] !== 't') {
?>
<script type="text/javascript" src="guestlistjs.php"></script>
<?php
}
?>

<p>Trouble? Contact <a href="mailto:simmons-tech@mit.edu">simmons-tech@mit.edu</a>.</p>

<?php
sdsIncludeFooter();
