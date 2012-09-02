<?php 
$techemail="simmons-tech@mit.EDU"; // this is where the approval messages go

require_once("../sds.php");
sdsRequireGroup("USERS");

sdsIncludeHeader("Create Lottery","",
		 '<script type="text/javascript" src="../polls/pollscript.php"></script>');

# read an argument for redisplaying the form
function getArg($argname,$default = "") {
  return isset($_REQUEST[$argname]) ?
    htmlspecialchars(maybeStripslashes($_REQUEST[$argname])) : $default;
}

if(empty($_REQUEST['create'])) {
  $months = array("January","February","March","April","May","June",
		  "July","August","September","October","November","December");

  $lotteryname = getArg('lotteryname');
  $description = getArg('description');
  $groupname = getArg('groupname','RESIDENTS');
  $viewable = getArg('viewable') ? 1 : 0;
  $openlottery = getArg('openlottery','now');

  $thetime=localtime(time(),true);
  $thehour=$thetime["tm_hour"];
  $themin=$thetime["tm_min"];
  $themday=$thetime["tm_mday"];
  $themonth=$thetime["tm_mon"];
  $theyear=$thetime["tm_year"]+1900;

  $oday = (int) getArg('oday',$themday);
  $omonth = (int) getArg('omonth',$themonth);
  $oyear = (int) getArg('oyear',$theyear);
  $ohour = (int) getArg('ohour',$thehour);
  $omin = (int) getArg('omin',$themin);

  $closelottery = getArg('closelottery','manual');

  // let default close date be 2 weeks into the future
  $thetime=localtime(time()+86400*14,true);
  $thehour=$thetime["tm_hour"];
  $themin=$thetime["tm_min"];
  $themday=$thetime["tm_mday"];
  $themonth=$thetime["tm_mon"];
  $theyear=$thetime["tm_year"]+1900;

  $cday = (int) getArg('cday',$themday);
  $cmonth = (int) getArg('cmonth',$themonth);
  $cyear = (int) getArg('cyear',$theyear);
  $chour = (int) getArg('chour',$thehour);
  $cmin = (int) getArg('cmin',$themin);

?>
<p>Welcome. Please fill out the form below to create a lottery.  Double-check
  your settings  as you will not be able to modify them later. <i>Lotteries
  will <u>not</u> appear until an  administrator has approved them.</i></p>

<form action="" method="post">
<?php echo sdsForm() ?>
  <table class="pollcreate">
    <tr>
      <td class="label">Lottery Name:</td>
      <td><input type="text" name="lotteryname" size="32" value="<?php echo $lotteryname ?>" /></td>
    </tr>
    <tr>
      <td class="label">Lottery Description:</td>
      <td><textarea name="description" rows="3" cols="40"><?php echo $description ?></textarea></td>
    </tr>
    <tr>
      <td class="label">Group:</td>
      <td><select name="groupname" onchange="updateDescription(this);">
<?php

  $query = "SELECT groupname,description FROM sds_groups_public ORDER BY groupname";
  $result = sdsQuery($query);
  if(!$result) {
    echo "</select></td></tr></table></form>\n";
    contactTech("Could not search groups");
  }
  $selectedDescription = '';
  while($row = pg_fetch_array($result)) {
    if($row["groupname"] === $groupname) {
      $selected = ' selected="selected"';
      $selectedDescription = htmlspecialchars($row['description']);
    } else {
      $selected="";
    }
    $groupname_disp = htmlspecialchars($row['groupname'],ENT_QUOTES);
    echo "          <option",$selected," value='",$groupname_disp,"'>",
      $groupname_disp,"</option>\n";
  }

  pg_free_result($result);

?>
        </select>
        <div id="groupDescriber"><?php echo $selectedDescription ?></div>
      </td>
    </tr>
    <tr>
      <td class="label">Viewability:</td>
      <td>
        <label>
          <input type="radio" name="viewable" value="0"<?php echo $viewable?"":' checked="checked"' ?> />
          Users cannot see lottery results.
        </label><br />
        <label>
          <input type="radio" name="viewable" value="1"<?php echo $viewable?' checked="checked"':"" ?> />
          Users can see lottery results after the lottery is closed.
        </label>
      </td>
    </tr>
    <tr>
      <td class="label">Open lottery:</td>
      <td>
        <label>
          <input type="radio" name="openlottery" value="now"<?php echo $openlottery==='now'?' checked="checked"':'' ?> />
          Now
        </label><br />
        <label>
          <input type="radio" name="openlottery" value="onat"<?php echo $openlottery==='now'?"":' checked="checked"' ?> />
          On
        </label>
        <input type="text" name="oday" size="2" maxlength="2" value="<?php echo $oday ?>" onchange="selectOnat('openlottery');" />
        <select name="omonth" onchange="selectOnat('openlottery');">
<?php

    for($i=0;$i<12;$i++) {
      if($i == $omonth) {
        $selected = ' selected="selected"';
      } else {
        $selected = "";
      }
      echo '          <option',$selected,' value="',$i,'">',$months[$i],
        "</option>\n";
    }

?>
        </select>
        <input type="text" name="oyear" size="4" maxlength="4" value="<?php echo $oyear ?>" onchange="selectOnat('openlottery');" />
        at
        <input type="text" name="ohour" size="2" maxlength="2" value="<?php echo $ohour ?>" onchange="selectOnat('openlottery');" />:<input type="text" name="omin" size="2" maxlength="2" value="<?php echo $omin ?>" onchange="selectOnat('openlottery');" />
        (24-hour)
      </td>
    </tr>
    <tr>
      <td class="label">Close lottery:</td>
      <td>
        <label>
          <input type="radio" name="closelottery" value="manual"<?php echo $closelottery==='manual'?' checked="checked"':"" ?> />
          Manually
        </label><br />
        <label>
          <input type="radio" name="closelottery" value="onat"<?php echo $closelottery==='manual'?"":' checked="checked"' ?> />
          On
        </label>
        <input type="text" name="cday" size="2" maxlength="2" value="<?php echo $cday ?>" onchange="selectOnat('closelottery');" />
        <select name="cmonth" onchange="selectOnat('closelottery');">
<?php

    for($i=0;$i<12;$i++) {
      if($i == $cmonth) {
        $selected = ' selected="selected"';
      } else {
        $selected = "";
      }
      echo '          <option',$selected,' value="',$i,'">',$months[$i],
        "</option>\n";
    }

?>
        </select>
        <input type="text" name="cyear" size="4" maxlength="4" value="<?php echo $cyear ?>" onchange="selectOnat('closelottery');" />
        at
        <input type="text" name="chour" size="2" maxlength="2" value="<?php echo $chour ?>" onchange="selectOnat('closelottery');" />:<input type="text" name="cmin" size="2" maxlength="2" value="<?php echo $cmin ?>" onchange="selectOnat('closelottery');" />
        (24-hour)
      </td>
    </tr>
    <tr><td>&nbsp;</td><td>&nbsp;</td></tr>
    <tr>
      <td>&nbsp;</td>
      <td><input type="submit" name="create" value="Create Lottery" /></td>
    </tr>
  </table>
</form>
<?php

} else {
  // do stuff and if everything's okay create the lottery
  $complaint = null;

  if($_REQUEST['openlottery'] === "onat") {
    $open_date =
      sprintf("%04d-%02d-%02d %02d:%02d",
	      $_REQUEST['oyear'],$_REQUEST['omonth']+1,$_REQUEST['oday'],
	      $_REQUEST['ohour'],$_REQUEST['omin']);
    $result = sdsQueryTest("SELECT timestamp '$open_date' > now()");
    if($result) {
      list($response) = pg_fetch_array($result);
      pg_free_result($result);
      if($response !== 't') {
	$complaint = "Open time should be in the future (or select 'Now')";
      }
    } else {
      $complaint = "Bad open time";
    }
  } else {
    $open_date = null;
  }
  if(empty($complaint) and $_REQUEST['closelottery'] === "onat") {
    $close_date =
      sprintf("%04d-%02d-%02d %02d:%02d",
	      $_REQUEST['cyear'],$_REQUEST['cmonth']+1,$_REQUEST['cday'],
	      $_REQUEST['chour'],$_REQUEST['cmin']);
    if(isset($open_date)) {
      $query = "SELECT timestamp '$close_date' > timestamp '$open_date'";
    } else {
      $query = "SELECT timestamp '$close_date' > now()";
    }
    $result = sdsQueryTest($query);
    if($result) {
      list($response) = pg_fetch_array($result);
      pg_free_result($result);
      if($response !== 't') {
	if(isset($open_date)) {
	  $complaint = "Close time should be after open time";
	} else {
	  $complaint = "Close time should be in the future";
	}
      }
    } else {
      $complaint = "Bad close time";
    }
  } else {
    $close_date = null;
  }
  if(empty($complaint)) {
    if(strlen($_REQUEST['lotteryname'])) {
      $lotteryname = maybeStripslashes($_REQUEST['lotteryname']);
    } else {
      $complaint = "Please provide a lottery name";
    }
  }
  if(empty($complaint)) {
    if(strlen($_REQUEST['description'])) {
      $description = maybeStripslashes($_REQUEST['description']);
    } else {
      $complaint = "Please provide a description";
    }
  }
  if(empty($complaint)) {
    $groupname = maybeStripslashes($_REQUEST['groupname']);
    $query = "SELECT 1 FROM sds_groups_public WHERE groupname='$groupname'";
    $result = sdsQuery($query);
    if(!$result)
      contactTech("Could not search groups");
    if(pg_num_rows($result) == 0) {
      $complaint = "Bad groupname";
    }
    pg_free_result($result);
  }
  $viewable = $_REQUEST['viewable'] ? 'true' : 'false';

  if(isset($complaint)) {
    echo "<p class='error'>",$complaint,"</p>\n";
    sdsIncludeFooter();
    exit;
  }
# everything is ok
  $owner = $session->username;

  $lotteryname_esc = pg_escape_string($lotteryname);
  $description_esc = pg_escape_string($description);
  $groupname_esc = pg_escape_string($groupname);
  $owner_esc = pg_escape_string($owner);
  $viewable_esc = $viewable;

  if(isset($open_date)) {
    $open_date_esc = "timestamp '$open_date'";
  } else {
    $open_date_esc = 'null';
  }
  if(isset($close_date)) {
    $close_date_esc = "timestamp '$close_date'";
  } else {
    $close_date_esc = 'null';
  }

  $query = <<<ENDQUERY
INSERT INTO lotteries
       (lotteryname,       description,       groupname,
        owner,       viewable,     open_date,     close_date)
VALUES ('$lotteryname_esc','$description_esc','$groupname_esc',
        '$owner_esc',$viewable_esc,$open_date_esc,$close_date_esc)
ENDQUERY;

  $result = sdsQuery($query);
  if(!$result or pg_affected_rows($result) != 1)
    contactTech("Could not create lottery");
  pg_free_result($result);

  echo "<p>Lottery created. It will appear after it has been approved by an administrator.</p>\n";
  echo "<p><a href='".sdsLink("./")."'>[ Back to Lotteries ]</a></p>\n";

  $lotteryname_mail = $lotteryname;
  $owner_mail = $owner;
  $groupname_mail = $groupname;
  $viewable_mail = $viewable ? 'Yes' : 'No';
  $open_date_mail = isset($open_date) ? $open_date : 'immediately';
  $close_date_mail = isset($close_date) ? $close_date : 'manual';
  $description_mail = $description;

  $approvemessage=<<<EOF
New lottery requires approval before people can enter. Please visit
http://simmons.mit.edu/sds/lotteries/ to approve or delete this lottery.

Name: $lotteryname_mail
Owner: $owner_mail
Group: $groupname_mail
Viewable: $viewable_mail
Open date: $open_date_mail
Close date: $close_date_mail

Description:
$description_mail

EOF;
  if(SDB_DATABASE === 'sdb') {
      if(!mail($techemail,"Lottery requires approval: $lotteryname",
	       $approvemessage,
	       "From: \"Simmons Lotteries\" <lotteries@simmons.mit.edu>\r\nReply-to: simmons-tech@mit.edu"))
	contactTech("Could not send email");
  } else {
    echo "If this were the REAL DB, the following email would have been sent:\n";
    echo "<pre>",$approvemessage,"</pre>\n";
  }
}

sdsIncludeFooter();
