<?php
require_once("../sds.php");
sdsRequireGroup("USERS");

$username_esc = pg_escape_string($session->username);

sdsIncludeHeader("Lounge Signup");

# should users be allowed to join lounges?
$ENABLE_LOUNGE_SIGNUPS = sdsGetIntOption('enable-lounge-signups');

## a lounge has been selected
# must verify user is not attempting to re-signup
$query = "SELECT lounge FROM directory WHERE username='$username_esc'";
$result = sdsQuery($query);
if(!$result or pg_num_rows($result) != 1)
  contactTech("Could not search directory");
list($lounge) = pg_fetch_array($result);
pg_free_result($result);

if($ENABLE_LOUNGE_SIGNUPS and isset($_REQUEST["atria"]) and
    !isset($lounge)) {
  $lounge = maybeStripslashes($_REQUEST['atria']);
  $lounge_esc = pg_escape_string($lounge);
  $query = "SELECT 1 FROM active_lounges WHERE lounge='$lounge_esc'";
  $result = sdsQuery($query);
  if(!$result)
    contactTech("Could not search lounges");
  if(pg_num_rows($result) != 1) {
    echo "<h2 class='error'>Unknown lounge</h2>\n";
    sdsIncludeFooter();
    exit;
  }
  pg_free_result($result);

  $query = "UPDATE directory SET lounge='$lounge_esc', loungevalue=value FROM options WHERE name='lounge-signup-value' AND username='$username_esc'";
  $result = sdsQuery($query);
  if(!$result or pg_affected_rows($result) != 1)
    contactTech("Could not sign up");
  pg_free_result($result);
}

## just showing some data
?>

<h1 style="text-align:center">Quit Lounging Around! Sign Up for a Lounge!</h1>
<p>Lounge social groups are subsections of Simmons Hall that receive a portion
  of house funds to run trips, throw housewide events, and buy stuff to make
  our lounges &quot;better places to live and learn.&quot; What do you want the
  lounge nearest you to do with its funding? Sign up and let your voice be
  heard!</p>

<?php

if(isset($lounge)) {
  $lounge_esc = pg_escape_string($lounge);
  $query = <<<ENDQUERY
SELECT active_lounges.description,url,contact,contact2,
       lounge_summary_report.allocation,predalloc,
       (lounge_summary_report.allocation-totalspent) AS remaining,
       (COALESCE(d1.title||' ','')||d1.firstname||' '||d1.lastname) AS name1,
       (COALESCE(d2.title||' ','')||d2.firstname||' '||d2.lastname) AS name2,
       d1.email AS email1,d2.email AS email2
FROM active_lounges JOIN lounge_summary_report
                      ON active_lounges.lounge=lounge_summary_report.loungeid
                    JOIN directory AS d1 ON active_lounges.contact=d1.username
               LEFT JOIN directory AS d2 ON active_lounges.contact2=d2.username
WHERE active_lounges.lounge='$lounge_esc'
ENDQUERY;
  $result = sdsQuery($query);
  if(!$result or pg_num_rows($result) != 1)
    contactTech("Could not get lounge info");
  $data = pg_fetch_object($result);
  pg_free_result($result);

  $desc = htmlspecialchars($data->description);
  if(isset($data->url))
    $desc = "<a href='".htmlspecialchars($data->url,ENT_QUOTES)."'>$desc</a>";

  echo "<h2 style='text-align:center'>You have signed up with ",$desc,"</h2>\n";
  if(isset($data->allocation)) {
    echo "<h3 style='text-align:center'>Total Allocation:\n";
    echo "  <span class='money'>",$data->allocation,"</span></h3>\n";
    echo "<h3 style='text-align:center'>Remaining Funds:\n";
    echo "  <span class='money",$data->remaining<0?' neg':'',"'>",
      $data->remaining,"</span></h3>\n";
  } else {
?>
<h3 style='text-align:center'><?php echo $desc ?>
  has not been allocated any funds yet, but it's predicted allocation is*
  <span class='money'><?php echo $data->predalloc #' ?></span></h3>

<p style="text-size:small">*This is the predicted allocation based solely on
  the number of members signed up, and the time at which they signed up.
  The amount is $0 if you do not have 10 members.</p>
<?php
  }

  echo "<p style='text-align:center'>First Contact: ",$data->name1,
    " (<a href='mailto:",htmlspecialchars($data->email1),"'>",
    htmlspecialchars($data->contact),"</a>)</p>\n";
  if(isset($data->contact2))
    echo "<p style='text-align:center'>Second Contact: ",$data->name2,
      " (<a href='mailto:",htmlspecialchars($data->email2),"'>",
      htmlspecialchars($data->contact2),"</a>)</p>\n";

  echo "<h2 style='text-align:center'><a href='",
    sdsLink('loungemembership.php'),"'>See who else has signed up!</a></h2>\n";
} elseif($ENABLE_LOUNGE_SIGNUPS) {

?>

<p>Please choose the lounge social group from the list below that you would
  like to join. You may only choose one group. You might want to take a look at
  the details laid out in the
  <a href="http://simmons.mit.edu/loungebylaws.html">lounge
  bylaws</a> before you sign up. More information about lounge signups will be provided during the first month of school.</p>

<form action="loungesignup.php" method="post">
<?php echo sdsForm() ?>

<table>
<?php

  $query = <<<ENDQUERY
SELECT active_lounges.lounge,description,url,contact,contact2,
       (COALESCE(d1.title||' ','')||d1.firstname||' '||d1.lastname) AS name1,
       (COALESCE(d2.title||' ','')||d2.firstname||' '||d2.lastname) AS name2,
       d1.email AS email1,d2.email AS email2
FROM active_lounges JOIN directory AS d1 ON active_lounges.contact=d1.username
               LEFT JOIN directory AS d2 ON active_lounges.contact2=d2.username
ORDER BY description
ENDQUERY;
  $result = sdsQuery($query);
  if(!$result)
    contactTech("Could not find lounges");

  while($data = pg_fetch_object($result)) {

    $desc = htmlspecialchars($data->description);
    if(isset($data->url))
      $desc = "<a href='".htmlspecialchars($data->url,ENT_QUOTES).
	"' target='loungewindow'>$desc</a>";

?>
  <tr>
    <td><input type="radio" name="atria" value="<?php echo htmlspecialchars($data->lounge) ?>" /></td>
    <td style="text-weight:bold"><?php echo $desc ?></td>
  </tr>
  <tr>
    <td></td>
<?php
    echo "    <td>Contacts: ",htmlspecialchars($data->name1),
      " (<a href='mailto:",htmlspecialchars($data->email1,ENT_QUOTES),"'>",
      htmlspecialchars($data->contact),"</a>)";

    if(isset($data->contact2))
      echo " and ",htmlspecialchars($data->name2),
	" (<a href='mailto:",htmlspecialchars($data->email2,ENT_QUOTES),"'>",
	htmlspecialchars($data->contact2),"</a>)";

    echo "</td>\n";
    echo "  </tr>\n";
  }
?>

</table>

<p style="text-align:center">
  <input type="submit" value="I Want to Join this Lounge!" /><br />
  (and I agree to follow the rules laid out in the lounge bylaws)</p>
</form>

<p>Lounges are currently eligible for an extra
  <span class="money"><?php echo sdsGetIntOption('lounge-signup-value') ?></span>
  in funding for each new signup, so don't delay!
  (<a href="http://simmons.mit.edu/loungebylaws.html">see the
    lounge bylaws for details</a>)</p>

<?php #'
} else {
  echo "<p>Sorry, lounge signups have ended. You cannot join a lounge this term.</p>\n";
}

sdsIncludeFooter();
