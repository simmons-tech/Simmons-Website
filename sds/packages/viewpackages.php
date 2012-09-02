<?php
require_once('../sds.php');
sdsRequireGroup('DESK');
require_once('../sds/ordering.inc.php');

sdsIncludeHeader("Waiting Packages");

$sortby = getSortby($_REQUEST['sortby'],0,5,'package_sortby');

$orderbyarray =
array (# recipient asc,desc
       "lastname ASC,firstname ASC,bin ASC,perishable DESC",
       "lastname DESC,firstname DESC,bin ASC,perishable DESC",
       # bin asc,desc
       "bin ASC,lastname ASC,firstname ASC,perishable DESC",
       "bin DESC,lastname ASC,firstname ASC,perishable DESC",
       # num packages asc,desc
       "pkg_count ASC,lastname ASC,firstname ASC,bin ASC,perishable DESC",
       "pkg_count DESC,lastname ASC,firstname ASC,bin ASC,perishable DESC",
       # perishable asc,desc
       "perishable ASC,lastname ASC,firstname ASC,bin ASC",
       "perishable DESC,lastname ASC,firstname ASC,bin ASC",
       # registration asc,desc
       "date_trunc('minute',max(checkin)) ASC,lastname ASC,firstname ASC,bin ASC,perishable DESC",
       "date_trunc('minute',max(checkin)) DESC,lastname ASC,firstname ASC,bin ASC,perishable DESC"
       );

$query = <<<ENDQUERY
SELECT recipient,firstname,lastname,bin,perishable,count(*) AS pkg_count,
       to_char(max(checkin),'FMDD Mon HH24:MI') AS latest_checkin
FROM packages LEFT JOIN directory ON recipient=username
WHERE pickup IS NULL GROUP BY recipient,firstname,lastname,bin,perishable
ORDER BY $orderbyarray[$sortby]
ENDQUERY;
$result = sdsQuery($query);
if(!$result)
  contactTech("Could not search packages");

echo "\n\n";
echo "<table class='packagetable'>\n";
echo "  <tr>\n";
makeSortTH("Recipient",0,$sortby);
makeSortTH("Bin",1,$sortby);
makeSortTH("# Pkgs",2,$sortby);
makeSortTH("Perish?",3,$sortby);
makeSortTH("Last Registration",4,$sortby);
echo "    <th>Registered By</th>\n";
echo "  </tr>\n";

while($record = pg_fetch_array($result)) {
  $pickup_constraint = 'false';
  $old_constraint = '';
  $mindate = '';
  while($pickup_constraint != $old_constraint) {
    $query = "SELECT min(checkin) FROM packages WHERE recipient='" .
      pg_escape_string($record['recipient']) . "' AND bin='" .
      pg_escape_string($record['bin']) .
      "' AND (pickup IS NULL OR $pickup_constraint)";
    $dateresult = sdsQuery($query);
    if(!$dateresult)
      contactTech("Could not search package ages");
    list($mindate) = pg_fetch_array($dateresult);
    pg_free_result($dateresult);
    $old_constraint = $pickup_constraint;
    $pickup_constraint = "pickup > TIMESTAMP '$mindate'";
  }
  $query = "SELECT DISTINCT checkin_by FROM packages WHERE recipient='" .
    pg_escape_string($record['recipient']) . "' AND bin='" .
    pg_escape_string($record['bin']) .
    "' AND (pickup IS NULL OR $pickup_constraint) ORDER BY checkin_by";
  $deskworker_result = sdsQuery($query);
  if(!$deskworker_result)
    contactTech("Could not find deskworkers");
  $deskworkerlist = array();
  while(list($deskworker) = pg_fetch_array($deskworker_result)) {
    $deskworkerlist[] = sdsGetFullName($deskworker);
  }
  pg_free_result($deskworker_result);
  echo "  <tr>\n";
  echo "    <td>",
    htmlspecialchars($record['firstname'] . ' ' . $record['lastname']),
    "</td>\n";
  echo "    <td>",htmlspecialchars($record['bin']),"</td>\n";
  echo "    <td>",$record['pkg_count'],"</td>\n";
  echo "    <td>",$record['perishable']==='t'?'Yes':'No';
  echo "    <td>",htmlspecialchars($record['latest_checkin']),"</td>\n";
  echo "    <td>",htmlspecialchars(implode(', ',$deskworkerlist)),"</td>\n";
  echo "  </tr>\n";
}
echo "</table>\n";
sdsIncludeFooter();
