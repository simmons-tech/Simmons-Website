<?php 
require_once("../sds.php");
sdsRequireGroup("USERS");

sdsIncludeHeader("Vote");

$username = $session->username;
$username_esc = pg_escape_string($username);
$poll = (int) $_REQUEST['poll'];

$query = <<<ENDQUERY
SELECT pollname,description,type,groupname,viewable
FROM polls
WHERE pollid = '$poll' AND approved AND NOT deleted AND
      open_date<now() AND (close_date IS NULL OR close_date>now())
ENDQUERY;

$result = sdsQuery($query);
if(!$result)
  contactTech("Could not search polls");
$pollexists = (pg_num_rows($result) == 1);
if($pollexists) {
  $pollrecord = pg_fetch_array($result);
}
pg_free_result($result);

$query = "SELECT 1 FROM ballots WHERE username='$username_esc' AND pollid='$poll'";
$result = sdsQuery($query);
if(!$result)
  contactTech("Cannot search ballots");
$votedbefore = (pg_num_rows($result) == 1);
pg_free_result($result);

if(!$pollexists) {
  echo "<p>Sorry, this poll no longer exists or is closed.</p>\n";
} elseif($votedbefore) {
  echo "<p>Sorry, you have already voted in this poll. All votes are final.</p>\n";
} elseif(!$session->groups[$pollrecord["groupname"]]) {
  echo "<p>You do not have the priveleges to vote in this poll.</p>\n";
} elseif(!isset($_REQUEST['vote'])) {
  // page called for the first time, display ballot

  $type = $pollrecord['type'] === 'radio' ? 'radio' : 'checkbox';

  echo "<h2>",$pollrecord['pollname'],": ballot</h2>\n";
  echo "<p class='polldescription'>",
    htmlspecialchars($pollrecord['description']),"</p>\n";
  echo "<form action='vote.php' method='post'>\n";
  echo sdsForm();
  echo "<input type='hidden' name='poll' value='",$poll,"' />\n";
  if($type === "radio") { // radio button vote
    echo "<p>Please vote for one of the options below.</p>\n";
  } else { // check box vote
    echo "<p>Please vote by checking the boxes below.</p>\n";
  }

  $query = "SELECT ordering,description FROM poll_choices WHERE pollid='$poll' ORDER BY ordering";
  $result = sdsQuery($query);
  if(!$result)
    contactTech("Could not find choices");

  echo "<ul style='list-style-type:none'>\n";
  while($record = pg_fetch_array($result)) {
    echo "  <li><input type='",$type,"' name='choice[]' value='",
      $record['ordering'],"' />",$record['description'],"</li>\n";
  }
  echo "</ul>\n";
  echo "<input type='submit' name='vote' value='Vote' />\n";
  echo "</form>\n";

} else { // page called second time, record the ballot
  // no selections gives null
  $choices = (array) $_REQUEST['choice'];

  $choices_filt = array();
  foreach($choices as $choice) {
    if(preg_match('/\D/',$choice)) {
      echo "<p class='error'>Invalid choice</p>\n";
      sdsIncludeFooter();
      exit;
    }
    if(in_array((int) $choice,$choices_filt)) {
      echo "<p class='error'>You can't vote for the same thing multiple times.</p>\n";
      sdsIncludeFooter();
      exit;
    }
    $query = "SELECT 1 FROM poll_choices WHERE pollid='$poll' AND ordering='$choice'";
    $result = sdsQuery($query);
    if(!$result)
      contactTech("Could not search choices");
    if(pg_num_rows($result) != 1) {
      echo "<p class='error'>Invalid choice</p>\n";
      sdsIncludeFooter();
      exit;
    }
    pg_free_result($result);
    $choices_filt[] = $choice;
  }

  if($pollrecord["type"] === "radio" and count($choices_filt) != 1) {
    echo "<p>You didn't vote. Please go back and select a choice.</p>\n";
  } else {

    $transres = sdsQuery("BEGIN");
    if(!$transres)
      contactTech("Could not start transaction");
    pg_free_result($transres);

    $query = "INSERT INTO ballots (pollid,username,date_cast) VALUES ('$poll','$username_esc',now())";
    $result = sdsQuery($query);
    if(!$result or pg_affected_rows($result) != 1) {
      contactTech("Could not record vote",false);
      if(!sdsQuery("ROLLBACK"))
	contactTech("Could not rollback");
      sdsIncludeFooter();
      exit;
    }
    pg_free_result($result);
    foreach($choices_filt as $choice) {
      $query = "UPDATE poll_choices SET votes=votes+1 WHERE pollid='$poll' AND ordering='$choice'";
      $result = sdsQuery($query);
      if(!$result or pg_affected_rows($result) != 1) {
	contactTech("Could not store vote",false);
	if(!sdsQuery("ROLLBACK"))
	  contactTech("Could not rollback. YOUR VOTE MAY BE CORRUPTED");
	sdsIncludeFooter();
	exit;
      }
      pg_free_result($result);
    }

    $transres = sdsQuery("COMMIT");
    if(!$transres) {
      contactTech("Could not commit. Your vote has NOT been recorded",false);
      if(!sdsQuery("ROLLBACK"))
	contactTech("Could not rollback. YOUR VOTE MAY BE CORRPTED");
      sdsIncludeFooter();
      exit;
    }

    echo "<p>Thanks for voting!</p>\n";
    if($pollrecord['viewable'] === 't') {
      echo "<p>You may now <a href='",sdsLink("view.php","poll=$poll"),
	"'>view the current results</a> of this poll if you wish.</p>\n";
    }
  }
}

echo "<p><a href='",sdsLink("polls.php"),"'>[ Back to Polls ]</a></p>\n";

sdsIncludeFooter();
