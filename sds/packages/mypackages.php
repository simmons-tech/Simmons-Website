<?php
require_once('../sds.php');
sdsRequireGroup('USERS');

sdsIncludeHeader("Waiting Packages");

$currentuser = pg_escape_string($session->username);

$query = <<<ENDQUERY
SELECT sum(pkg_count) AS num_packages,sum(perishable_count) AS num_perishable,
       to_char(min(latest_checkin),'FMMonth FMDDth') AS earliest_sure
FROM (SELECT count(*) AS pkg_count,
      count(NULLIF(perishable,'f')) AS perishable_count,
      max(checkin) AS latest_checkin
      FROM packages WHERE recipient='$currentuser' AND  pickup IS NULL
      GROUP BY bin) AS info
ENDQUERY;
$result = sdsQuery($query);
if(!$result)
  contactTech("Could not search packages");
$record = pg_fetch_array($result);
pg_free_result($result);

if($record['num_packages']) {
  echo "<h2>You have ",$record['num_packages']," package",
    $record['num_packages']>1?'s':''," waiting at desk,\n";
  echo $record['num_packages']>1?"   some of which have":"   which has",
    " been there since at least ",htmlspecialchars($record['earliest_sure'])
    ,".\n";
  if($record['num_perishable']) {
    echo $record['num_perishable']," of them ",
      $record['num_perishable']>1?'are':'is'," perishable.\n";
  }
  echo "</h2>\n";
} else {
  echo "<h2>You have no packages waiting at desk.</h2>\n";
}

sdsIncludeFooter();
