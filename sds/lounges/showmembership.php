<?php
require_once('../sds.php');
sdsRequireGroup("LOUNGE-CHAIRS");

sdsIncludeHeader("Lounge Membership");

$query = "SELECT loungeid,description,membership,allocation,predalloc FROM lounge_summary_report ORDER BY loungeid";
$loungeresult = sdsQuery($query);
if(!$loungeresult)
  contactTech("Could not search lounges");

?>
<table class="loungeinfo">
  <col width="0*" />
  <col>
  <col width="0*" />
  <col width="0*" />
  <col width="0*" />
  <tr>
    <th>ID</th>
    <th>Name</th>
    <th>Membership</th>
    <th style="white-space:normal">Predicted Allocation</th>
    <th style="white-space:normal">Actual Allocation</th>
  </tr>
<?php

$evenrow = false;
while($lounge = pg_fetch_array($loungeresult)) {
  $evenrow = ! $evenrow;

  unset($ans);
  if(!preg_match('/^lounge-(.*)$/',$lounge['loungeid'],$ans))
    contactTech("Malformed lounge ID");
  $loungeid = htmlspecialchars($ans[1]);

  echo "  <tr class='",($evenrow?'evenrow':'oddrow'),"'>\n";
  echo '    <td><a href="#',$loungeid,'">',$loungeid,"</a></td>\n";
  echo '    <td><a href="#',$loungeid,'">',
    htmlspecialchars($lounge['description']),"</a></td>\n";
  echo "    <td class='membership'>",$lounge['membership'],"</td>\n";
  echo "    <td class='money'>",$lounge['predalloc'],"</td>\n";
  echo "    <td class='money'>",$lounge['allocation'],"</td>\n";
  echo "  </tr>\n";
}
echo "</table>\n\n";
echo "<hr />\n\n";

pg_result_seek($loungeresult,0);

while($lounge = pg_fetch_array($loungeresult)) {
  $lounge_esc = pg_escape_string($lounge['loungeid']);
  $query = "SELECT username,firstname,lastname FROM active_directory WHERE lounge='$lounge_esc' ORDER BY lastname,firstname";
  $memberresult = sdsQuery($query);
  if(!$memberresult)
    contactTech("Could not find lounge members");

  unset($ans);
  if(!preg_match('/^lounge-(.*)$/',$lounge['loungeid'],$ans))
    contactTech("Malformed lounge ID");
  $loungeid = htmlspecialchars($ans[1]);

  echo '<h2 id="',$loungeid,'">',$loungeid," &mdash; ",
    htmlspecialchars($lounge['description'])," (",pg_num_rows($memberresult),
    " members)</h2>\n";
  echo "<ul>\n";
  while($member = pg_fetch_array($memberresult)) {
    echo "  <li>",
      htmlspecialchars($member['firstname']." ".$member['lastname']),
      " (",htmlspecialchars($member['username']),")</li>\n";
  }
  pg_free_result($memberresult);
  echo "</ul>\n\n";
}
pg_free_result($loungeresult);

sdsIncludeFooter();


