<?php 
require_once("../sds.php");
sdsRequireGroup("USERS");

if(isset($_REQUEST['issure']) and strtolower($_REQUEST['issure']) === "no") {
  header("Location: ". SDS_BASE_URL . sdsLink("polls/polls.php"));
  exit;
}

sdsIncludeHeader("Administrate Poll");

$poll = (int) $_REQUEST['poll'];

$query="SELECT pollname,owner,approved,approved AND open_date < now() AND COALESCE(close_date > now(),true) AS open FROM polls WHERE pollid = '$poll' AND NOT deleted";
$result = sdsQuery($query);
if(!$result)
  contactTech("Could not search polls");
if(pg_num_rows($result) != 1)
  sdsErrorPage('No Such Poll',
	       "The requested poll was not found in the Simmons DB");
$pollrecord = pg_fetch_array($result);
pg_free_result($result);

$pollname_disp = htmlspecialchars($pollrecord['pollname']);

$backlink = "<p><a href='" . sdsLink("polls.php","") .
"'>[ Back to Polls ]</a></p>\n";

if($session->groups['ADMINISTRATORS'] or
   $session->username === $pollrecord["owner"]) {

  $action = maybeStripslashes($_REQUEST['action']);
  if($action === "approve" and $session->groups['ADMINISTRATORS']) {
    if($pollrecord['approved'] !== 't') {
      # set approved and set open date if poll is "On approval"
      $query = "UPDATE polls SET approved=true,open_date=COALESCE(open_date,now()) WHERE pollid='$poll'";
      $result = sdsQuery($query);
      if(!$result or pg_affected_rows($result) != 1)
	contactTech("Could not update poll");
      pg_free_result($result);

      echo "<p>Poll approved.</p>\n",$backlink;
    } else {
      echo "<p class='error'>Poll has already been approved.</p>\n",$backlink;
    }

  } elseif($action === "delete") {
    if(isset($_REQUEST['issure']) and
       strtolower($_REQUEST['issure']) === "yes") {

      $query = "UPDATE polls SET deleted=true WHERE pollid='$poll'";
      $result = sdsQuery($query);
      if(!$result or pg_affected_rows($result) != 1)
	contctTech("Could not delete poll");
      pg_free_result($result);

      echo "<p>Poll deleted.</p>\n",$backlink,"\n";

    } else {
?>
<p>Are you sure you want to delete poll <b><?php echo $pollname_disp ?></b>?</p>
<form action="admin.php" method="post">
<?php echo sdsForm() ?>
<input type="hidden" name="poll" value="<?php echo $poll ?>" />
<input type="hidden" name="action" value="delete" />
  <table>
    <tr>
      <td><input type="submit" name="issure" value="No" /></td>
      <td><input type="submit" name="issure" value="Yes" /></td>
    </tr>
  </table>
</form>
<?php
    }
  } elseif($action === "close" and
	   $pollrecord['open'] === 't') {

    if(isset($_REQUEST['issure']) and
       strtolower($_REQUEST['issure']) === "yes") {

      $query = "UPDATE polls SET close_date = now() WHERE pollid = '$poll'";
      $result = sdsQuery($query);
      if(!$result or pg_affected_rows($result) != 1)
	contactTech("Could not close poll");
      pg_free_result($result);

      echo "<p>Poll closed</p>\n",$backlink;

    } else {
?>
<p>Are you sure you want to close poll <b><?php echo $pollname_disp ?></b>?
  This action cannot be undone.</p>
<form action="admin.php" method="post">
<?php echo sdsForm() ?>
<input type="hidden" name="poll" value="<?php echo $poll ?>" />
<input type="hidden" name="action" value="close" />
  <table>
    <tr>
      <td><input type="submit" name="issure" value="No" /></td>
      <td><input type="submit" name="issure" value="Yes" /></td>
    </tr>
  </table>
</form>
<?php
    }
  } elseif($action === "makeunviewable" and
	   $pollrecord['open'] !== 't') {

    $query = "UPDATE polls SET viewable = false WHERE pollid = '$poll'";
    $result = sdsQuery($query);
    if(!$result or pg_affected_rows($result) != 1)
      contactTech("Could not change poll viewability");
    pg_free_result($result);

    echo "<p>Poll <b>",$pollname_disp,"</b> is now unviewable.</p>\n",
      $backlink;

  } elseif($action === "makeviewable" and
	   $pollrecord['open'] !== 't') {

    $query = "UPDATE polls SET viewable = true WHERE pollid = '$poll';";
    $result = sdsQuery($query);
    if(!$result or pg_affected_rows($result) != 1)
      contactTech("Could not change poll viewability");
    pg_free_result($result);

    echo "<p>Poll <b>",$pollname_disp,"</b> is now viewable.</p>\n",
      $backlink;

  } else {

    echo "<p class='error'>",htmlspecialchars($action),
      ": Invalid or unsupported action.</p>\n";

  }
} else {
  echo "<p class='error'>You do not have the priveleges to admininstrate this poll.</p>\n";
}

sdsIncludeFooter();
