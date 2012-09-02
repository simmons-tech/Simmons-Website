<?php
require_once("../sds.php");
sdsIncludeHeader("GovTracker","Simmons Government Online");

sdsRequireGroup("USERS");

echo "<h2>Submit Proposal to the House</h2>\n";

$insert = array();
$errors = array();

$insert['author'] = $session->username;

$insert['title'] = getStringArg('proptitle');
if($insert['title'] === '')
  $errors[] = "Please supply a title";

$insert['coauthors'] = getStringArg('coauthors');

$proptype = getStringArg('proptype');
if(in_array($proptype,array("amendment","fundrequest",
			    "policy","announcement"),true)) {
  $insert['type'] = $proptype;
} else {
  $errors[] = "Invalid proposal type";
  $insert['type'] = "fundrequest";
}

if($insert['type'] === "fundrequest") {
  $fundrequest = getStringArg("requestfundamt");
  if($fundrequest !== '') {
    $insert['fundsrequestedamt'] = $fundrequest;
    $insert['finalfunds'] = $fundrequest;
    if(!preg_match('/^\d+(?:\.(?:\d\d)?)?$/',$fundrequest)) {
      $errors[] = "Funding amount does not look like a monetary amount";
    }
  } else {
    $errors[] = "Fund requests must specify an amount";
  }
}

$insert['specialnotes'] =
  getStringArg("propspecialnotes");


$insert['description'] = getStringArg("propdesc");
if($insert['description'] === '') {
  $errors[] = "Please supply a summary";
}

$insert['fulltext'] = getStringArg("fulltext");
$insert['finalfulltext'] = $insert['fulltext'];
if($insert['fulltext'] === '') {
  $errors[] = "Please supply proposal text";
}

if(!empty($_REQUEST['submit']) and count($errors) == 0) {
  $query = "INSERT INTO gov_proposals " . sqlArrayInsert($insert);
  $result = sdsQuery($query);
  if(!$result)
    contactTech("Proposal submission failed");
  pg_free_result($result);

  echo "<p class='success'>Your proposal submission has been received.</p>\n";

  $tauthor = $insert['author'];
  if($insert['coauthors'])
    $tauthor = $tauthor . " (with " . $insert['coauthors'] . ")";

  $mailbody = "$tauthor has created a new proposal: {$insert['title']}\n\n";
  if($insert['type'] === "amendment") {
    $mailbody .= "This proposal is a constitutional amendment and requires a 2/3 approval vote.\n\n";
  } elseif($insert['type'] === "fundrequest") {
    $mailbody .= "This proposal requests House funds in the amount of $".$insert['fundsrequestedamt']." and requires a majority approval.\n\n";
  } elseif($insert['type'] === "policy") {
    $mailbody .= "This proposal seeks an official House opinion or policy and requires a majority approval.\n\n";
  } elseif($insert['type'] === "announcement") {
    $mailbody .= "This proposal is only an announcement and requires no approval vote.\n\n";
  }

  $mailbody .= "Special procedural notes:\n";
  $mailbody .= ($insert['specialnotes']?$insert['specialnotes']:"none") . "\n\n";

  $mailbody .= "Unofficial Summary:\n" . $insert['description'] . "\n\n";

  $mailbody .= "Official Full-Text:\n" . $insert['fulltext'] . "\n\n";

  $mailbody .= "------------------------------------------------------------\n";
  $mailbody .= "This is an Automatic E-mail\n - do not reply to this address (use a Simmons discussion list instead)";

  $to = "proposals@simmons.mit.edu";
  if(SDB_DATABASE === 'sdb') {
    mail($to,"Proposed: {$insert['title']} by {$insert['author']}",$mailbody,
	 "From: \"GovTracker Automated E-mails\" <govtracker-proposals@simmons.mit.edu>\r\nReply-To: simmons-request@mit.edu")
      or contactTech("Could not send e-mail to proposals@simmons.mit.edu");
  } else {
    echo "Would have sent:\n<pre>\n$mailbody\n</pre>\n";
  }

} else {
  if(!empty($_REQUEST['submit'])) {
    foreach($errors as $error) {
      echo "<p class='error'>",$error,"</p>\n";
    }
  }

?>

<h4>Do not use HTML tags.</h4>
<form action="submitproposal.php" method="post">
<?php echo sdsForm() ?>
<table class='proposaldetail'>
  <tr>
    <td>Primary Author:</td>
    <td><?php echo sdsGetFullName($session->username) ?></td>
  </tr>
  <tr>
    <td>Secondary Authors (optional):</td>
    <td><input type="text" name="coauthors" size="40" value="<?php echo htmlspecialchars($insert['coauthors']) ?>"/><br />
      <span class='inputdetail'>If this proposal is being submitted on behalf
        of a committee, include the committee title here.</span>
    </td>
  </tr>

  <tr>
    <td>Proposal Title:</td>
    <td><input type="text" name="proptitle" size="45" value="<?php echo htmlspecialchars($insert['title']) ?>"/><br />
      <span class='inputdetail'>Keep the title concise and to the point.  If
        this proposal has been approved by a specific committee, prefix the
        title with the committee name, ex. "Tech - ." This will appear on the
        summary agenda sent out to the House before the meeting.</span>
    </td>
  </tr>

  <tr>
    <td>Procedure:</td>
    <td>
      <label><input type="radio" name="proptype" value="amendment" <?php echo $insert['type']==='amendment'?'checked="checked" ':'' ?>/>
        This proposal is a <b>constitutional amendment</b> and requires a 2/3
        approval vote.</label><br />
      <label><input type="radio" name="proptype" value="fundrequest" <?php echo $insert['type']==='fundrequest'?'checked="checked" ':'' ?>/>
        This proposal requests <b>House funds</b> in the amount of</label>
        $<input type="text" size="6" name="requestfundamt" value="<?php echo htmlspecialchars(@$insert['fundsrequestedamt']) ?>" />
        and requires a majority approval.<br />
        <span class='inputdetail'>Enter only digits and an (optional) decimal
          point in the amount field.</span><br />
      <label><input type="radio" name="proptype" value="policy" <?php echo $insert['type']==='policy'?'checked="checked" ':'' ?>/>
        This proposal seeks an <b>official House opinion or policy</b> and
        requires a majority approval.</label><br />
      <label><input type="radio" name="proptype" value="announcement" <?php echo $insert['type']==='announcement'?'checked="checked" ':'' ?>/>
        This proposal is <b>only an announcement</b> and requires no approval
        vote.</label>

      <h4 style="margin-bottom:0">Include any special procedural notes here:</h4>
      <input type="text" name="propspecialnotes" size="45" value="<?php echo htmlspecialchars($insert['specialnotes']) ?>" />
    </td>
  </tr>

  <tr>
    <td>Unofficial Summary:</td>
    <td><textarea name="propdesc" rows="16" cols="60"><?php echo htmlspecialchars($insert['description']) ?></textarea><br />
      <span class='inputdetail'>This description will be sent out with the
        agenda, and displayed on the big screen during your presentation.  Do
        not use HTML tags.</span>
    </td>
  </tr>

  <tr>
    <td>Official Full text of Proposal:</td>
    <td><textarea name="fulltext" rows="16" cols="60"><?php echo htmlspecialchars($insert['fulltext']) ?></textarea><br />
      <span class='inputdetail'>This description will be displayed on the big
        screen during your presentation.  It will be visible on the Database.
        Do not use HTML tags. <b>This is what the House Committee will vote
        on.</b> Therefore, even if the summary is exactly what the House will
        vote on, please paste the summary here.</span>
    </td>
  </tr>
</table>

<p>This proposal will be publicly viewable on the Simmons DB and during House
  Committee meetings.  You cannot undo this action after clicking
  "Submit Proposal".</p>
<input type="submit" name="submit" value="Submit Proposal" />
<input type="reset" value="Clear Form" />
</form>

<?php
}

include("gt-footer.php");
sdsIncludeFooter();
