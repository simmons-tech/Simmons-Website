<?php
require_once('../sds.php');
sdsRequireGroup("DESK-CAPTAINS");

sdsIncludeHeader("Guest List History");

$minyear = (int) strftime("%Y");
$query = "SELECT EXTRACT(YEAR FROM date_added) AS year FROM guest_list ORDER BY year ASC LIMIT 1";
$result = sdsQuery($query);
if($result and pg_num_rows($result) == 1) {
  list($minyear) = pg_fetch_array($result);
}
if($result) pg_free_result($result);

function yearOpts($default = null) {
  global $minyear;
  if(!isset($default)) $default = (int) strftime("%Y");
  for($i=$minyear;$i <= (int) strftime("%Y");$i++) {
    echo '<option ',$default==$i?' selected="selected"':'',">$i</options>\n";
  }
}
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

if(isset($_REQUEST['fetch'])) {
  $starttime = gmmktime((int) $_REQUEST['starthour'],
			(int) $_REQUEST['startmin'],0,
			(int) $_REQUEST['startmonth'],
			(int) $_REQUEST['startday'],
			(int) $_REQUEST['startyear']);
  $endtime = gmmktime((int) $_REQUEST['endhour'],
		      (int) $_REQUEST['endmin'],0,
		      (int) $_REQUEST['endmonth'],
		      (int) $_REQUEST['endday'],
		      (int) $_REQUEST['endyear']);
  if($starttime <= $endtime) {
    echo "<h2>People on the Guest List between ",strftime("%X",$starttime),
      " on ",strftime("%x",$starttime)," and ",strftime("%X",$endtime)," on ",
      strftime("%x",$endtime),"</h2>\n";
    $query = <<<ENDQUERY
SELECT DISTINCT username,guest
FROM guest_list
WHERE NOT (date_added > timestamp 'epoch' + interval '$endtime seconds' OR
           date_invalid < timestamp 'epoch' + interval '$starttime seconds')
ORDER BY guest
ENDQUERY;
    $result = sdsQuery($query);
    if($result) {
?>
<table>
  <tr style="background-color: #ffbbbb">
    <th>Guest</th>
    <th>Resident</th>
  </tr>
<?php
      while($record = pg_fetch_array($result)) {
	echo "  <tr>\n";
	echo "    <td>",htmlspecialchars($record['guest']),"</td>\n";
	echo "    <td>",sdsGetFullName($record['username'])," (",
	  htmlspecialchars($record['username']),")</td>\n";
	echo "  </tr>\n";
      }
      echo "</table>\n";
      pg_free_result($result);
    }
  } else {
    echo "<h2>Please give an end time after the start time</h2>\n";
  }
} else {
?>
<h2>Show people on guest list between:</h2>
<form action="guestlisthistory.php" method="post">
  <table>
    <tr>
      <td><select name="startyear">
<?php yearOpts() ?>
      </select></td>
      <td><select name="startmonth">
<?php monthOpts() ?>
      </select></td>
      <td><select name="startday">
<?php dayOpts() ?>
      </select></td>
      <td>at</td>
      <td><select name="starthour">
<?php hourOpts() ?>
      </select></td>
      <td>:</td>
      <td><select name="startmin">
<?php minOpts() ?>
      </select></td>
    </tr>
    <tr>
      <td><select name="endyear">
<?php yearOpts() ?>
      </select></td>
      <td><select name="endmonth">
<?php monthOpts() ?>
      </select></td>
      <td><select name="endday">
<?php dayOpts() ?>
      </select></td>
      <td>at</td>
      <td><select name="endhour">
<?php hourOpts() ?>
      </select></td>
      <td>:</td>
      <td><select name="endmin">
<?php minOpts() ?>
      </select></td>
    </tr>
  </table>
  <input type="submit" name="fetch" value="Search" />
</form>

<?php
}

sdsIncludeFooter();
