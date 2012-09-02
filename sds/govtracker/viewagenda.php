<?php
require_once("../sds.php");
sdsIncludeHeader("GovTracker","Simmons Government Online");

sdsRequireGroup("USERS");

unset($passedaid);
if(isset($_REQUEST['aid']))
  $passedaid = sdsSanitizeString($_REQUEST['aid']);

if(isset($passedaid)) {
  $where = "agendaid = '$passedaid'";
} else {
  $where = "status = 'open' OR status = 'closed'";
}
$query = <<<ENDQUERY
SELECT agendaid,meetingtitle,prefacetext,status,hchairannounce,presannounce,
       committeereps,usersub,
       to_char(meetingdate,'FMMonth FMDD, YYYY') AS meetingdatestr,
       to_char(closingdate,'FMMonth FMDD, YYYY') AS closingdatestr,
       to_char(opendate,'FMHH:MI AM on FMMonth FMDD, YYYY') AS opendatestr,
       to_char(closedate,'FMHH:MI AM on FMMonth FMDD, YYYY') AS closedatestr,
       to_char(datesub,'FMHH:MI AM on FMMonth FMDD, YYYY') AS datesubstr
FROM gov_agendas
WHERE $where
ENDQUERY;
$result = sdsQuery($query);
if(!$result)
  contactTech("Could not search agendas");
if(pg_num_rows($result) == 0) {

  echo "<h3>No meeting information</h3>\n";
  if(isset($passedaid)) {
    echo "<p class='error'>This agenda does not exist.</p>\n";
  } else {
    echo "<p style='font-weight:bold'>The House Chair has not yet opened the\n";
    echo "  agenda or submitted information for the upcoming meeting.</p>\n";
  }
} else {
  if(pg_num_rows($result) != 1)
    contactTech("There are multiple agendas open!");
  $data = pg_fetch_object($result);

  echo "<h2>",htmlspecialchars($data->meetingtitle),"</h2>\n";
  echo "<h4>Meeting Date: ",htmlspecialchars($data->meetingdatestr),"</h4>\n";
  echo "<table width='100%' style='background-color: #eeeeee; border: thin solid black'>\n";
  echo "  <tr>\n";
  echo "    <td>\n";
  echo nl2br(htmlspecialchars($data->prefacetext));
  echo "    </td>\n";
  echo "  </tr>\n";
  echo "</table>\n\n";

  echo "<p style='font-size:small'>Agenda opened at ",
    htmlspecialchars($data->opendatestr);

  # old agendas do not have closedate set, so we do not want to test status
  if(isset($data->closedatestr)) {
    echo ", closed at ",htmlspecialchars($data->closedatestr);
  } else {
    echo ", closes on ",htmlspecialchars($data->closingdatestr);
  }
  echo ".<br />\n";
  echo "<span style='font-style:italic'>Meeting info last updated by ",
    sdsGetFullname($data->usersub)," at ",htmlspecialchars($data->datesubstr),
    ".</span></p>\n";
  echo "<hr />\n";

  if($data->status === 'open') {
    # HCL has not yet decided upon the proposals
?>

<p>The House Chair has not yet closed the agenda for the upcoming meeting.</p>
<p>You may view the <a href="<?php echo sdsLink('viewunassigned.php') ?>">open
  proposals</a> that may appear on this agenda.</p>

<?php
  } else {
    # a closed agenda, has details too
?>

<h3>General Topics</h3>
<h4>House Chair's Announcements</h4>
<p><?php echo nl2br(htmlspecialchars($data->hchairannounce)) ?></p>

<h4>President's Announcements</h4>
<p><?php echo nl2br(htmlspecialchars($data->presannounce)) ?></p>

<h4>Committee Reports</h4>
<p><?php echo nl2br(htmlspecialchars($data->committeereps)) ?></p>

<?php
  }

  $aid = $data->agendaid;
  $query = <<<ENDQUERY
SELECT propid,title,type,finalfunds,description,specialnotes,author,
       coauthors,decision,
       to_char(datesub,'FMMM-FMDD-YY FMHH:MI am') AS datesubstr
FROM gov_active_proposals
WHERE agendaid = '$aid'
ORDER BY agendaorder ASC, datesub ASC
ENDQUERY;

  $propresult = sdsQuery($query);
  if(!$propresult)
    contactTech("Could not search proposals");

  if($data->status === 'completed') {
?>
<h3>Proposals that were approved, rejected, discussed, or moved to full forum</h3>
<p style='font-style:italic'>Tabled proposals are displayed under the meeting
  that they were finally decided on, or if still tabled, are displayed under
  Open Proposals.</p>
<?php
  } else {
?>
<h3>Proposals to be Presented</h3>
<?php
  }
  if($data->status === 'open') {
    echo "<h3><a href='",sdsLink('agendaorder.php'),"'>Reorder Agenda</a></h3>\n";
  }
?>
<table class="proposallist">
  <tr>
    <th>Title and Summary</th>
    <th>Type</th>
<?php
  if($data->status === 'completed') {
    echo "    <th>Decision</th>\n";
  } else {
    echo "    <th>Submitted On</th>\n";
  }
?>
    <th>Author(s)</th>
  </tr>

<?php
  if(pg_num_rows($propresult) == 0) {
?>

  <tr>
    <td colspan="5" style="text-align: center;font-style: italic;">
<?php
    if($data->status === 'open') {
# no closed agenda - HCL has not yet decided upon the proposals
?>
      The House Chair has not placed any items on the agenda yet, however the
      agenda is still open.  Please see the open proposals.
<?php
    } elseif($data->status === 'closed') {
?>
      No proposals shall be presented at this meeting.
<?php
    } else {
?>
      No proposals were voted upon at this meeting.
<?php
    }
?>
    </td>
  </tr>
<?php
  }
  $rowclass = 'oddrow';
  while($propdata = pg_fetch_object($propresult)) {
# alternate row coloring
    $rowclass = $rowclass==='oddrow'?'evenrow':'oddrow';

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

    if(!empty($session->groups['HOUSE-COMM-LEADERSHIP'])) {
      if($data->status === 'open') {
	echo "      <br />\n      Admin tools:\n";
	echo "        <a href='", sdsLink('tableproposal.php',"pid=$pid"),
	  "' class='admin'>Table this Proposal</a>\n";
	echo "        <a href='",sdsLink('delproposal.php',"pid=$pid"),
	  "' class='admin'>Delete this Proposal</a>\n";
      } elseif($data->status === 'completed' and
	       $propdata->decision === 'MOVED TO FULL FORUM') {
	echo "      <br />\n      Admin tools:\n";
	echo "        <a href='",
	  sdsLink('fullforumdecision.php',"pid=$pid&amp;decision=a"),
	  "' class='admin'>Full Forum Approval</a>\n";
	echo "        <a href='",
	  sdsLink('fullforumdecision.php',"pid=$pid&amp;decision=r"),
	  "' class='admin'>Full Forum Rejection</a>\n";
      }
    }

    echo "    </td>\n";
    echo "    <td>",$typedescr,"</td>\n";
    if($data->status === 'completed') {
      echo "    <td style='font-weight:bold'>",$propdata->decision,"</td>\n";
    } else {
      echo "    <td style='font-size:small'>",
	htmlspecialchars($propdata->datesubstr),"</td>\n";
    }
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
} # end existing agenda
pg_free_result($result);

include("gt-footer.php");
sdsIncludeFooter();
