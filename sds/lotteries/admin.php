<?php 
require_once("../sds.php");
sdsRequireGroup("USERS");

if(isset($_REQUEST['issure']) and strtolower($_REQUEST['issure']) === "no") {
  header("Location: ". SDS_BASE_URL . sdsLink("lotteries/"));
  exit;
}

sdsIncludeHeader("Administrate Lottery");

$lottery = (int) $_REQUEST['lottery'];

$query="SELECT lotteryname,owner,approved FROM lotteries WHERE lotteryid=$lottery AND NOT deleted";
$result = sdsQuery($query);
if(!$result)
  contactTech("Could not search lotteries");
if(pg_num_rows($result) != 1)
  sdsErrorPage('No Such Lottery',
	       "The requested lottery was not found in the Simmons DB");
$lotteryrecord = pg_fetch_array($result);
pg_free_result($result);

$lotteryname_disp = htmlspecialchars($lotteryrecord['lotteryname']);

$backlink = "<p><a href='".sdsLink("./","").
  "'>[ Back to Lotteries ]</a></p>\n";

if(!empty($session->groups['ADMINISTRATORS']) or
   $session->username === $lotteryrecord["owner"]) {

  $action = getStringArg('action');
  if($action === "approve" and !empty($session->groups['ADMINISTRATORS'])) {
    if($lotteryrecord['approved'] !== 't') {
      # set approved and set open date if lottery is "On approval"
      $query = "UPDATE lotteries SET approved=true,open_date=COALESCE(open_date,now()) WHERE lotteryid='$lottery'";
      $result = sdsQuery($query);
      if(!$result or pg_affected_rows($result) != 1)
	contactTech("Could not update lottery");
      pg_free_result($result);

      echo "<p>Lottery approved.</p>\n",$backlink;
    } else {
      echo "<p class='error'>Lottery has already been approved.</p>\n",
	$backlink;
    }

  } elseif($action === "close") {

    if(isset($_REQUEST['issure']) and
       strtolower($_REQUEST['issure']) === "yes") {

      $query = "UPDATE lotteries SET close_date=now() WHERE lotteryid=$lottery";
      $result = sdsQuery($query);
      if(!$result or pg_affected_rows($result) != 1)
	contactTech("Could not close lottery");
      pg_free_result($result);

      echo "<p>Lottery closed</p>\n",$backlink;

    } else {
?>
<p>Are you sure you want to close lottery <b><?php echo $lotteryname_disp ?></b>?
  This action cannot be undone.</p>
<form action="" method="post">
<?php echo sdsForm() ?>
<input type="hidden" name="lottery" value="<?php echo $lottery ?>" />
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
  } elseif($action === "makeunviewable") {

    $query = "UPDATE lotteries SET viewable=false WHERE lotteryid=$lottery";
    $result = sdsQuery($query);
    if(!$result or pg_affected_rows($result) != 1)
      contactTech("Could not change lottery viewability");
    pg_free_result($result);

    echo "<p>Lottery <b>",$lotteryname_disp,"</b> is now unviewable.</p>\n",
      $backlink;

  } elseif($action === "makeviewable") {

    $query = "UPDATE lotteries SET viewable=true WHERE lotteryid=$lottery";
    $result = sdsQuery($query);
    if(!$result or pg_affected_rows($result) != 1)
      contactTech("Could not change lottery viewability");
    pg_free_result($result);

    echo "<p>Lottery <b>",$lotteryname_disp,"</b> is now viewable.</p>\n",
      $backlink;

  } elseif($action === "delete") {
    if(isset($_REQUEST['issure']) and
       strtolower($_REQUEST['issure']) === "yes") {

      $query = "UPDATE lotteries SET deleted=true WHERE lotteryid=$lottery";
      $result = sdsQuery($query);
      if(!$result or pg_affected_rows($result) != 1)
	contctTech("Could not delete lottery");
      pg_free_result($result);

      echo "<p>Lottery deleted.</p>\n",$backlink,"\n";
    } else {
?>
<p>Are you sure you want to delete lottery <b><?php echo $lotteryname_disp ?></b>?</p>
<form action="" method="post">
<?php echo sdsForm() ?>
<input type="hidden" name="lottery" value="<?php echo $lottery ?>" />
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
  } else {

    echo "<p class='error'>",htmlspecialchars($action),
      ": Invalid or unsupported action.</p>\n";

  }
} else {
  echo "<p class='error'>You do not have the priveleges to admininstrate this lottery.</p>\n";
}

sdsIncludeFooter();
