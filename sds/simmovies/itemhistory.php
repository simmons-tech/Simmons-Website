<?php
require_once('../sds.php');
sdsRequireGroup("MOVIEADMINS");

sdsIncludeHeader("Movie History");

$date_format = 'FMDD Mon HH24:MI';

if(!strlen($_REQUEST['movieid']) or preg_match('/\D/',$_REQUEST['movieid'])) {
  echo "<h2 class='error'>No movie specified</h2>\n";
  sdsIncludeFooter();
  exit;
}
$movieid = (int) $_REQUEST['movieid'];

$query = "SELECT typename,title FROM movie_items JOIN movie_types USING (typeid) WHERE movieid=$movieid";
$result = sdsQuery($query);
if(!$result)
  contactTech("Could not search items");
if(pg_num_rows($result) != 1) {
  echo "<h2 class='error'>Can't find item</h2>\n";
  sdsIncludeFooter();
  exit;
}
$record = pg_fetch_array($result);
pg_free_result($result);

echo "<h1>",htmlspecialchars($record['typename']),": ",
  htmlspecialchars($record['title']),"</h1>\n";

$query = <<<ENDQUERY
SELECT instanceid,box_number,checked_out,hidden
FROM movie_instances
WHERE movieid=$movieid AND NOT deleted
ORDER BY to_number(substring(box_number FROM '^[[:digit:]]+'),'9999999999'),
         box_number,instanceid
ENDQUERY;
$result = sdsQuery($query);
if(!$result)
  contactTech("Could not search instances");

$instances = array();
echo "<table class='movielist'>\n";
echo "  <tr>\n";
echo "    <th>Copy</th>\n";
echo "    <th>Status</th>\n";
echo "    <th>Visibility</th>\n";
echo "  </tr>\n";
$rowparity = 1;
while($instance = pg_fetch_array($result)) {
  $rowparity = 1-$rowparity;
  $rowclass = $rowparity ? 'oddrow' : 'evenrow';
  echo "  <tr class='",$rowclass,"'>\n";
  echo "    <td><a href='#instance",$instance['instanceid'],"'>",
    isset($instance['box_number']) ?
    "Copy #" . htmlspecialchars($instance['box_number']) :
    'Unnumbered Copy',"</a></td>\n";
  echo "    <td>",$instance['checked_out']==='t' ?
    'Checked Out' : 'Available' ,"</td>\n";
  echo "    <td>",$instance['hidden']==='t' ? '' : 'Not ',"Hidden</td>\n";
  echo "  </tr>\n";
}
echo "</table>\n";

pg_result_seek($result,0);

while($instance = pg_fetch_array($result)) {
  $instanceid = $instance['instanceid'];
  echo "<hr />\n";
  echo "<table class='movielist'>\n";
  echo "<caption id='instance",$instanceid,"'>",
    isset($instance['box_number']) ?
    "Copy #" . htmlspecialchars($instance['box_number']) :
    'Unnumbered Copy',"</caption>\n";
?>
  <tr>
    <th rowspan="2">Checked out to</th>
    <th colspan="2">Checked Out</th>
    <th colspan="2">Checked In</th>
  </tr>
  <tr>
    <th>At</th><th>By</th>
    <th>At</th><th>By</th>
  </tr>
<?php

  $query = <<<ENDQUERY
SELECT username,to_char(checkout,'$date_format') AS checkout_str,
       checkout_by,to_char(checkin,'$date_format') AS checkin_str,checkin_by
FROM movie_loans WHERE instanceid=$instanceid ORDER BY checkout DESC
ENDQUERY;
  $loanresult = sdsQuery($query);
  $rowparity = 1;
  while($loan = pg_fetch_array($loanresult)) {
    $rowparity = 1-$rowparity;
    $rowclass = $rowparity ? 'oddrow' : 'evenrow';
    echo "  <tr class='",$rowclass,"'>\n";
    echo "    <td>",sdsGetFullName($loan['username']),"</td>\n";
    echo "    <td>",htmlspecialchars($loan['checkout_str']),"</td>\n";
    echo "    <td>",sdsGetFullName($loan['checkout_by']),"</td>\n";
    echo "    <td>",htmlspecialchars($loan['checkin_str']),"</td>\n";
    echo "    <td>",sdsGetFullName($loan['checkin_by']),"</td>\n";
    echo "  </tr>\n";
  }
  echo "</table>\n";
  pg_free_result($loanresult);
}
pg_free_result($result);

sdsIncludeFooter();
