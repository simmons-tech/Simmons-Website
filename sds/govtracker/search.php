<?php
require_once("../sds.php");
sdsIncludeHeader("GovTracker","Policy Search");

sdsRequireGroup("USERS");
$search_string = maybeStripslashes($_REQUEST['stext']);

$search_esc = "ILIKE '%".pg_escape_string($search_string)."%'";
$query = <<<ENDQUERY
SELECT propid,title,type,finalfunds,description,specialnotes,author,
       coauthors,deletedby,
       COALESCE(decision, CASE WHEN deletedby IS NULL THEN '(NONE)'
                               ELSE 'DELETED' END) AS decision
FROM gov_proposals
WHERE title $search_esc OR description $search_esc OR
      fulltext $search_esc OR finalfulltext $search_esc
ORDER BY datesub ASC
ENDQUERY;

$propresult = sdsQuery($query);
if(!$propresult)
  contactTech("Could not search proposals");

?>
<h3>Proposals with text including '<?php echo htmlspecialchars($search_string) ?>'</h3>

<table class="proposallist">
  <tr>
    <th>Title and Summary</th>
    <th>Type</th>
    <th>Decision</th>
    <th>Author(s)</th>
  </tr>

<?php
if(pg_num_rows($propresult) == 0) {
?>
  <tr>
    <td colspan="5" style="text-align: center;font-style: italic;">
      No proposals match your query.
    </td>
  </tr>
<?php
}
$rowclass = 'oddrow';
while($propdata = pg_fetch_object($propresult)) {
  $rowclass = $rowclass === 'oddrow' ? 'evenrow' : 'oddrow';

  if($propdata->type === "amendment") {
    $typedescr = "Constitutional Amendment";
  } elseif($propdata->type === "fundrequest") {
    $typedescr =
      "Funding Request (<span class='money'>$propdata->finalfunds</span>)";
  } elseif($propdata->type === "policy") {
    $typedescr = "Opinion or Policy";
  } elseif($propdata->type === "announcement") {
    $typedescr = "Announcement (no vote)";
  } else {
    $typedescr = "Special (see notes)";
  }

  $pid = $propdata->propid;

  echo "  <tr class='",$rowclass,
    isset($propdata->deletedby)?' deleted':'',"'>\n";
  echo "    <td>\n";
  echo "      <a href='",sdsLink('viewproposal.php',"pid=$pid"),
    "' class='proptitle'>",htmlspecialchars($propdata->title),"</a>\n";
  echo "      <span style='font-size:small'>\n",
    nl2br(htmlspecialchars($propdata->description)),"\n";
  if($propdata->specialnotes) {
    echo "<br /><span style='font-style:italic'>Special notes: ",
      htmlspecialchars($propdata->specialnotes),"</span>\n";
  }
  echo "      </span>\n";
  echo "    </td>\n";
  echo "    <td>",$typedescr,"</td>\n";
  echo "    <td style='font-weight:bold'>",$propdata->decision,"</td>\n";
  echo "    <td style='font-size:small'>",
    sdsGetfullname($propdata->author);
  if($propdata->coauthors) {
    echo "<br />\n      <span style='font-size:smaller'>and ",
      htmlspecialchars($propdata->coauthors),"</span>";
  }

  echo "</td>\n";
  echo "  </tr>\n";
}
pg_free_result($propresult);
echo "</table>\n";

include("gt-footer.php");
sdsIncludeFooter();
