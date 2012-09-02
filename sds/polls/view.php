<?php 
require_once("../sds.php");
sdsRequireGroup("USERS");

sdsIncludeHeader("View Poll");

$username = $session->username;
$username_esc = pg_escape_string($username);
$poll = (int) $_REQUEST['poll'];

$query = <<<ENDQUERY
SELECT pollname,description,type,groupname,owner,viewable,
       (open_date<now() AND (close_date IS NULL OR close_date>now())) AS open
FROM polls
WHERE pollid = '$poll' AND approved AND NOT deleted
ENDQUERY;
$result = sdsQuery($query);
if(!$result)
  contactTech("Could not search polls");

if(pg_num_rows($result) != 1) {
  echo "<h2 class='error'>No such poll</h2>\n";
  sdsIncludeFooter();
  exit;
}

$pollrecord = pg_fetch_array($result);
pg_free_result($result);

$query = "SELECT 1 FROM ballots WHERE pollid = '$poll' AND username='" .
  $username_esc . "'";
$result = sdsQuery($query);
if(!$result)
  contactTech("Could not search ballots");
$alreadyvoted = pg_num_rows($result);
pg_free_result($result);

$canview = false;

# voters can view if viewable
if($pollrecord["viewable"] === 't' and
   !empty($session->groups[$pollrecord["groupname"]]))
  $canview = true;

# admin and owner can view
if(($username === $pollrecord['owner'] or
   !empty($session->groups['ADMINISTRATORS'])) and
   $pollrecord['open'] !== 't')
  $canview = true;

# but not if they have not voted yet
if($pollrecord['open'] === 't' and !$alreadyvoted)
  $canview = false;

if($canview) {
  $query = "SELECT COUNT(*) FROM ballots WHERE pollid='$poll'";
  $result = sdsQuery($query);
  if(!$result or pg_num_rows($result) != 1)
    contactTech("Cannot count ballots");
  list($numvotes) = pg_fetch_array($result);
  pg_free_result($result);

  $query = "SELECT description,votes FROM poll_choices WHERE pollid='$poll' ORDER BY ordering";
  $result = sdsQuery($query);
  if(!$result)
    contactTech("Can't read poll choices");

  echo "<h2>Results for poll: ",htmlspecialchars($pollrecord['pollname']),
    "</h2>\n";
  echo "<p class='polldescription'>",
    htmlspecialchars($pollrecord["description"]),"</p>\n";

  if($pollrecord["type"]=="radio") {
    echo "<p>Results are shown in the chart below. Each voter voted for only one choice.</p>\n";
  } else {
    echo "<p>Results are shown in the chart below by number of voters (not ballots). Each voter could vote for multiple choices.</p>\n";
  }

  if($numvotes == 0) {
    echo "<p>Nobody has voted yet.</p>\n";
  } else {
?>
<table class="pollresults">
  <tr>
    <th>Choice</th>
    <th>Voters</th>
    <th>Percent</th>
    <th>&nbsp;<!-- visual --></th>
  </tr>
<?php
    while($choicerecord = pg_fetch_array($result)) {
      $percentage = round(100 * $choicerecord['votes'] / $numvotes);
      $barlength = round(300 * $choicerecord['votes'] / $numvotes);

      echo "  <tr>\n";
      echo "    <td>",htmlspecialchars($choicerecord['description']),"</td>\n";
      echo "    <td class='number'>",$choicerecord['votes'],"&nbsp;/&nbsp;",
	$numvotes,"</td>\n";
      echo "    <td class='number'>",$percentage,"%</td>\n";
      echo "    <td class='visual'><img src='pixel.gif' width='",$barlength,
	"' height='10' /></td>\n";
      echo "  </tr>\n";

    }
    echo "</table>\n";
  }
} else {
  # Cannot view, say why
  if($username !== $pollrecord['owner'] and
     !$session->groups[$pollrecord['groupname']] and
     !$session->groups['ADMINISTRATORS']) {
    echo "<p>You do not have the priveleges to view this poll.</p>\n";
  } elseif($pollrecord['open'] === 't' and
	   !$alreadyvoted and
	   ($pollrecord['viewable'] === 't' or
	    $username = $pollrecord['owner'] or
	    $session->groups['ADMINISTRATORS'])) {
    echo "<p>You have not yet voted.</p>\n";
  } else {
    echo "<p>Results of this poll are not public.</p>\n";
  }
}

echo  "<p><a href='".sdsLink("polls.php","")."'>[ Back to Polls ]</a></p>\n";

sdsIncludeFooter();

