<?php
require_once("../sds.php");
sdsIncludeHeader("GovTracker","Simmons Government Online");

sdsRequireGroup("USERS");

$propid = (int) $_REQUEST['pid'];
$query = <<<ENDQUERY
SELECT title,author,coauthors,type,description,fulltext,fundsrequestedamt,
       finalfulltext,finalfunds,decision,record,deletedby,deletereason,
       specialnotes,
       to_char(gov_proposals.datesub,'FMMonth FMDD, YYYY at FMHH:MI AM')
                                                              AS datesubstr,
       agendaid,meetingtitle,
       to_char(meetingdate,'FMMonth FMDD, YYYY') AS meetingdatestr
FROM gov_proposals LEFT JOIN gov_agendas USING (agendaid)
WHERE propid = '$propid'
ENDQUERY;

$propresult = sdsQuery($query);
if(!$propresult)
  contactTech("Could not search proposals");
?>

<h2>Proposal #<?php echo $propid ?></h2>

<table class="proposaldetail">

<?php
if(pg_num_rows($propresult) != 1) {
?>
  <tr>
    <td style="text-align: center;font-style: italic;">
      Proposal not found.
    </td>
  </tr>
<?php
} else {
  $propdata = pg_fetch_object($propresult);

?>
  <tr>
    <td>Proposal Title:</td>
    <td style='font-weight:bold'><?php echo htmlspecialchars($propdata->title) ?></td>
  </tr>

<?php
  if(isset($propdata->agendaid)) {
    echo "  <tr>\n";
    echo "    <td>Meeting:</td>\n";
    echo "    <td><a href='",
      sdsLink('viewoldagenda.php',"aid=$propdata->agendaid"),"'>",
      htmlspecialchars($propdata->meetingtitle),"</a> &mdash; ",
      htmlspecialchars($propdata->meetingdatestr),"</td>\n";
    echo "  </tr>\n";
  }
  if(isset($propdata->decision)) {
?>
  <tr>
    <td>House Decision:</td>
    <td style="font-weight:bold"><?php echo $propdata->decision ?></td>
  </tr>
<?
  } elseif(isset($propdata->deletedby)) {
?>
  <tr>
    <td colspan="2" style="font-size:large;text-align:center">
      Proposal <span style='font-weight:bold'>DELETED</span>
    </td>
  </tr>
  <tr>
    <td>Deleted by:</td>
    <td><?php echo sdsGetFullname($propdata->deletedby) ?></td>
  </tr>
  <tr>
    <td>Deletion Reason:</td>
    <td>
<?php echo nl2br(htmlspecialchars($propdata->deletereason)) ?>
    </td>
  </tr>
<?php
  }
?>
  <tr>
    <td>Primary Author:</td>
    <td><?php echo sdsGetFullname($propdata->author) ?></td>
  </tr>
  <tr>
    <td>Secondary Authors:</td>
    <td><?php echo ($propdata->coauthors?htmlspecialchars($propdata->coauthors):"none") ?></td>
  </tr>

  <tr>
    <td>Submission Date:</td>
    <td><?php echo $propdata->datesubstr ?></td>
  </tr>

  <tr>
    <td>Procedure:</td>
    <td>
<?php
  if($propdata->type === "amendment") {
    echo "      <p>This proposal is a <b>constitutional amendment</b> and requires a 2/3 approval vote.</p>\n";
  } elseif($propdata->type === "fundrequest") {
    echo "      <p>This proposal requests <b>House funds</b> in the amount of <b class='money'>",$propdata->finalfunds,"</b> and requires a majority approval.</p>\n";
  } elseif($propdata->type === "policy") {
    echo "      <p>This proposal seeks an <b>official House opinion or policy</b> and requires a majority approval.</p>\n";
  } elseif($propdata->type === "announcement") {
    echo "      <p>This proposal is <b>only an announcement</b> and requires no approval vote.</p>\n";
  }
?>
      <h4 style='margin-bottom:0'>Special procedural notes:</h4>
      <span style='padding-left:2em'>
        <?php echo ($propdata->specialnotes?htmlspecialchars($propdata->specialnotes):"<i>N/A</i>") ?>
      </span>
    </td>
  </tr>

  <tr>
    <td>Unofficial Short Summary:</td>
    <td><?php echo nl2br(htmlspecialchars($propdata->description)) ?></td>
  </tr>

  <tr>
    <td>Official Full text of Proposal:</td>
    <td><?php echo nl2br(htmlspecialchars($propdata->finalfulltext)) ?></td>
  </tr>

  <tr>
    <td>Decision History:<br />(reverse chronological order)</td>
    <td>
      <ol>
<?php echo $propdata->record ?>

      </ol>
<?php
  if(isset($propdata->fundsrequestedamt)) {
    echo "      Original Funding: <span class='money'>",
      $propdata->fundsrequestedamt,"</span><br />\n";
  } else {
    echo "      Original Funding: N/A<br />\n";
  }
?>
      Original Full text:
      <p style='padding-left:1em'>
<?php echo nl2br(htmlspecialchars($propdata->fulltext)) ?>
      </p>
    </td>
  </tr>

<?php 
# admin toolbar
  if(!empty($session->groups['HOUSE-COMM-LEADERSHIP']) and
     !isset($propdata->decision) and !isset($propdata->deletedby)) {
?>
  <tr>
    <td>Admin tools:</td>
    <td><a href="<?php echo sdsLink('delproposal.php',"pid=$propid") ?>">Delete this Proposal</a></td>
  </tr>
<?php
  }
} # end if there is a prop with this id
pg_free_result($propresult);
echo "</table>\n";

include("gt-footer.php");
sdsIncludeFooter();
