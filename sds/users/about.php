<?php
require_once("../sds.php");
sdsRequireGroup("USERS");
sdsIncludeHeader("About the Simmons DB");

?>
<h2>The Simmons DB</h2>
<p><span style="font-weight:bold;font-style:italic">Original authors:</span>
  dramage, 2002; with bonawitz, dheera, psaindon<br />
<span style="font-weight:bold;font-style:italic">GovTracker:</span>
  advay, 2006</p>
<table class="about">
  <tr>
    <td rowspan="2">
      <span class="title">
        <a href="mailto:simmons-tech@mit.edu">Administrators
          (IT Committee)</a>:</span>
      <ul class="names">
<?php
# get the members of the ADMINISTRATORS group
$query = "SELECT username,lastname,firstname FROM sds_group_membership_cache JOIN directory USING (username) WHERE groupname='ADMINISTRATORS' ORDER BY lastname ASC";
$result = sdsQuery($query);
if(!$result)
  contactTech("Can't find Tech!");
$chair = sdsGetStrOption('itchair');
while($ruser = pg_fetch_object($result)) {
  echo "        <li>",
    htmlspecialchars($ruser->firstname . " " . $ruser->lastname);
  if($ruser->username === $chair)
    echo " (Committee Chair)";
  echo "</li>\n";
}
?>
      </ul>
      <p style="font-style:italic">
        Want to help make the Simmons DB better?<br />
        Then join the <a href="mailto:simmons-tech@mit.edu">IT Committee!</a>
      </p>
    </td>
    <td rowspan="2">
      <span class="title">
        <a href="mailto:simmons-moderators@mit.edu">Moderators</a>:</span>
      <ul class="names">
<?php
# get the members of the MODERATORS group
$query = "SELECT lastname,firstname FROM sds_group_membership_cache JOIN directory USING (username) WHERE groupname='MODERATORS' ORDER BY lastname ASC";
$result = sdsQuery($query);
if(!$result)
  contactTech("Can't find moderators");
while($ruser = pg_fetch_object($result)) {
  echo "        <li>",
    htmlspecialchars($ruser->firstname." ".$ruser->lastname),"</li>\n";
}
?>
      </ul>
    </td>
    <td>
      <span class="title">GovTracker House Committee Editors:</span>
      <ul class="names">
<?php
# get the members of the HOUSE-COMM-LEADERSHIP group
$query = "SELECT lastname,firstname FROM sds_group_membership_cache JOIN directory USING (username) WHERE groupname='HOUSE-COMM-LEADERSHIP' ORDER BY lastname ASC";
$result = sdsQuery($query);
if(!$result)
  contactTech("Can't find house leaders");
while($ruser = pg_fetch_object($result)) {
  echo "        <li>",
    htmlspecialchars($ruser->firstname . " " . $ruser->lastname),"</li>\n";
}
?>
      </ul>
    </td>
  </tr>
  <tr>
    <td>
      <span class="title">GovTracker Financial Editors:</span>
      <ul class="names">
<?php
# get the members of the FINANCIAL-ADMINS group
$query = "SELECT lastname,firstname FROM sds_group_membership_cache JOIN directory USING (username) WHERE groupname='FINANCIAL-ADMINS' ORDER BY lastname ASC";
$result = sdsQuery($query);
if(!$result)
  contactTech("Can't find financial admins");
while($ruser = pg_fetch_object($result)) {
  echo "        <li>",
    htmlspecialchars($ruser->firstname . " " . $ruser->lastname),"</li>\n";;
}
?>
      </ul>
    </td>
  </tr>
  <tr>
    <td colspan="3" style="padding-top: 4ex">
      <span class="title">Technical specs:</span><br />
PHP <?php echo phpversion() ?>;
<?php                                                
$query = "SELECT split_part(version(),' on ',1)";
$result = sdsQuery($query);
if(!$result or pg_num_rows($result) != 1)
  contactTech("Can't get postgres version number");
list($version) = pg_fetch_array($result);
echo $version;
?>;  
Current Database: <?php echo pg_dbname($SDB) ?>
    </td>
  </tr>
</table>
<?php

sdsIncludeFooter();
