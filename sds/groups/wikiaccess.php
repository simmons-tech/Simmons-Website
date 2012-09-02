<?php
require_once('../sds.php');
sdsRequireGroup('USERS');
sdsIncludeHeader("Wiki Permissions");

$query = <<<ENDQUERY
SELECT wiki_prefix,read_groupname,write_groupname,admin_groupname
FROM wiki_permissions_sds
ORDER BY wiki_prefix
ENDQUERY;
$result = sdsQuery($query);
if(!$result)
  contactTech("Could not list wiki areas");

?>

<p>Simmons Tech makes <b>NO GUARANTEE</b> that wiki access control is
  foolproof.  Anything you put on the Simmons Wiki is posted at your own
  risk.</p>

<table class="wiki-perms">
  <tr>
    <th rowspan="2">Prefix</th>
    <th colspan="3">Access</th>
  </tr>
  <tr>
    <th>Read</th>
    <th>Write</th>
    <th>Admin</th>
  </tr>
<?php

while($record = pg_fetch_object($result)) {
  $admin = false;
  $write = false;
  $read = false;
  
  if(isset($record->admin_groupname) and
     !empty($session->groups[$record->admin_groupname]))
    $admin = true;
  if(isset($record->write_groupname) and
     !empty($session->groups[$record->write_groupname]))
    $write = $read = true;
  if(isset($record->read_groupname) and
     !empty($session->groups[$record->read_groupname]))
    $read = true;

  $admin_str = '';
  if(!empty($session->groups['ADMINISTRATORS']))
    $admin_str = 'AS DB ADMIN';
  if($admin)
    $admin_str = 'EDIT';

  if($read or $admin_str !== '') {
    echo "  <tr>\n";
    echo "    <td class='prefix'>",
      htmlspecialchars($record->wiki_prefix),"</td>\n";
    echo "    <td>",$read  ? 'X' : '',"</td>\n";
    echo "    <td>",$write ? 'X' : '',"</td>\n";
    echo "    <td>";
    if($admin_str !== '')
      echo "<a href='",
	sdsLink('wikiedit.php','prefix='.
		htmlspecialchars($record->wiki_prefix,ENT_QUOTES)),"'>",
	$admin_str,"</a>";
    echo "</td>\n";
    echo "  </tr>\n";
  }
}
pg_free_result($result);
echo "</table>\n";

if(!empty($session->groups['ADMINISTRATORS'])) {
  echo "<p>Create a <a href='",sdsLink('wikinew.php'),
    "'>new controlled area</a></p>\n";
}

?>

<p>To request an access controlled portion of the Simmons Wiki, send email to
  <a href="mailto:simmons-tech@mit.edu">simmons-tech@mit.edu</a> with the
  page name prefix you would like and the intended use of the area.</p>

<?php
sdsIncludeFooter();
