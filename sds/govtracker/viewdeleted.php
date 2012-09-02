<?php
require_once("../sds.php");
sdsIncludeHeader("GovTracker","Simmons Government Online");

sdsRequireGroup("USERS");
?>

<h2>Deleted Proposals</h2>
<p>These proposals have been deleted by house government officials.</p>
<?php

$query = <<<ENDQUERY
SELECT propid,title,type,finalfunds,description,specialnotes,author,
       coauthors,deletedby,deletereason,
       to_char(datesub,'FMMM-FMDD-YY FMHH:MI am') AS datesubstr
FROM gov_proposals
WHERE deletedby IS NOT NULL
ORDER BY datesub DESC
ENDQUERY;

$propresult = sdsQuery($query);
if(!$propresult)
  contactTech("Could not search proposals");
?>

<table class="proposallist">
  <tr>
    <th>Title and Summary</th>
    <th>Type</h>
    <th>Submitted On</th>
    <th>Author(s)</h>
  </tr>

<?php
if(pg_num_rows($propresult) == 0) {
?>
  <tr>
    <td colspan="5" style="text-align: center;font-style: italic;">
      No proposal records match your query.
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

  echo "  <tr class='",$rowclass,"'>\n";
  echo "    <td rowspan='2'>\n";
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
  echo "    <td style='font-size:small'>",
    htmlspecialchars($propdata->datesubstr),"</td>\n";
  echo "    <td style='font-size:small'>",
    sdsGetfullname($propdata->author);
  if($propdata->coauthors) {
    echo "<br />\n      <span style='font-size:smaller'>and ",
      htmlspecialchars($propdata->coauthors),"</span>";
  }

  echo "</td>\n";
  echo "  </tr>\n";
  echo "  <tr class='",$rowclass,"'>\n";
  echo "    <td colspan='3'><b>Reason:</b> \n";
  echo nl2br(htmlspecialchars($propdata->deletereason));
  echo "\n<cite>",sdsGetFullname($propdata->deletedby),"</cite>\n";
  echo "    </td>\n";
  echo "  </tr>\n";
}
pg_free_result($propresult);
echo "</table>\n";

include("gt-footer.php");
sdsIncludeFooter();
