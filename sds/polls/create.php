<?php 
$techemail="simmons-tech@mit.EDU"; // this is where the approval messages go

require_once("../sds.php");
sdsRequireGroup("USERS");

sdsIncludeHeader("Create Poll","",
		 '<script type="text/javascript" src="pollscript.php"></script>');

# read an argument for redisplaying the form
function getArg($argname,$default = "") {
  return isset($_REQUEST[$argname]) ?
    htmlspecialchars(maybeStripslashes($_REQUEST[$argname])) : $default;
}

if(empty($_REQUEST['create'])) {
  $months = array("January","February","March","April","May","June",
		  "July","August","September","October","November","December");

  $pollname = getArg('pollname');
  $description = getArg('description');
  $type = getArg('type','radio');
  $groupname = getArg('groupname','USERS');
  $viewable = getArg('viewable') ? 1 : 0;
  $openpoll = getArg('openpoll','now');

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

  $closepoll = getArg('closepoll','manual');

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

  $numchoices = (int) getArg('numchoices',5);
  if(isset($_REQUEST['morechoices'])) { $numchoices += 5; }
  if(isset($_REQUEST['fewerchoices'])) { $numchoices -= 5; }
  if($numchoices < 5) { $numchoices = 5; }

  $choices = array();
  for($i=0;$i<$numchoices;$i++) {
    $choices[$i] = isset($_REQUEST['choice'][$i]) ?
      htmlspecialchars(maybeStripslashes($_REQUEST['choice'][$i]),ENT_QUOTES) :
      "";
  }

?>
<p>Welcome. Please fill out the form below to create a poll. Double-check your
  settings  as you will not be able to modify them later. <i>Polls will
  <u>not</u> appear until an  administrator has approved them.</i></p>

<form action="create.php#return" method="post">
<?php echo sdsForm() ?>
  <table class="pollcreate">
    <tr>
      <td class="label">Short Poll Name:</td>
      <td><input type="text" name="pollname" size="32" value="<?php echo $pollname ?>" /></td>
    </tr>
    <tr>
      <td class="label">Poll Description:</td>
      <td><textarea name="description" rows="3" cols="40"><?php echo $description ?></textarea></td>
    </tr>
    <tr>
      <td class="label">Type of Vote:</td>
      <td>
        <label>
          <input type="radio" name="type" value="radio"<?php echo $type==='radio'?' checked="checked"':"" ?> />
          Users may only vote for one choice
        </label><br />
        <label>
          <input type="radio" name="type" value="check"<?php echo $type==='radio'?"":' checked="checked"' ?> />
          Users may vote for multiple options
        </label>
      </td>
    </tr>
    <tr>
      <td class="label">Group:</td>
      <td>Leave it as USERS if you want all Sims to be able to vote.<br />
        <select name="groupname" onchange="updateDescription(this);">
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
          Users cannot see poll results or running tallies.
        </label><br />
        <label>
          <input type="radio" name="viewable" value="1"<?php echo $viewable?' checked="checked"':"" ?> />
          Users can see poll results after they have voted, and after the poll
          is closed.
        </label>
      </td>
    </tr>
    <tr>
      <td class="label">Open poll:</td>
      <td>
        <label>
          <input type="radio" name="openpoll" value="now"<?php echo $openpoll==='now'?' checked="checked"':'' ?> />
          Now
        </label><br />
        <label>
          <input type="radio" name="openpoll" value="onat"<?php echo $openpoll==='now'?"":' checked="checked"' ?> />
          On
        </label>
        <input type="text" name="oday" size="2" maxlength="2" value="<?php echo $oday ?>" onchange="selectOnat('openpoll');" />
        <select name="omonth" onchange="selectOnat('openpoll');">
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
        <input type="text" name="oyear" size="4" maxlength="4" value="<?php echo $oyear ?>" onchange="selectOnat('openpoll');" />
        at
        <input type="text" name="ohour" size="2" maxlength="2" value="<?php echo $ohour ?>" onchange="selectOnat('openpoll');" />:<input type="text" name="omin" size="2" maxlength="2" value="<?php echo $omin ?>" onchange="selectOnat('openpoll');" />
        (24-hour)
      </td>
    </tr>
    <tr>
      <td class="label">Close poll:</td>
      <td>
        <label>
          <input type="radio" name="closepoll" value="manual"<?php echo $closepoll==='manual'?' checked="checked"':"" ?> />
          Manually
        </label><br />
        <label>
          <input type="radio" name="closepoll" value="onat"<?php echo $closepoll==='manual'?"":' checked="checked"' ?> />
          On
        </label>
        <input type="text" name="cday" size="2" maxlength="2" value="<?php echo $cday ?>" onchange="selectOnat('closepoll');" />
        <select name="cmonth" onchange="selectOnat('closepoll');">
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
        <input type="text" name="cyear" size="4" maxlength="4" value="<?php echo $cyear ?>" onchange="selectOnat('closepoll');" />
        at
        <input type="text" name="chour" size="2" maxlength="2" value="<?php echo $chour ?>" onchange="selectOnat('closepoll');" />:<input type="text" name="cmin" size="2" maxlength="2" value="<?php echo $cmin ?>" onchange="selectOnat('closepoll');" />
        (24-hour)
      </td>
    </tr>
    <tr><td>&nbsp;</td><td>&nbsp;</td></tr>
    <tr>
      <td class="label" id="return">Choices:</td>
      <td>Enter each choice on the ballot in a separate box:<br />
        <input type="hidden" name="numchoices" value="<?php echo $numchoices ?>" />

<?php

    for($i=0;$i<$numchoices;$i++) {
      echo "        <input type='text' name='choice[",$i,
      "]' size='60' value='",$choices[$i],"' /><br />\n";
    }

?>
      </td>
    </tr>
    <tr>
      <td>&nbsp;</td>
      <td><input type="submit" name="morechoices" value="More" />
<?php if($numchoices > 5) { ?>
          <input type="submit" name="fewerchoices" value="Fewer" />
<?php } ?>
    <tr><td>&nbsp;</td><td>&nbsp;</td></tr>
    <tr>
      <td>&nbsp;</td>
      <td><input type="submit" name="create" value="Create Poll" /></td>
    </tr>
  </table>
</form>
<?php

} else {
  // do stuff and if everything's okay create the poll
  $complaint = null;

  if($_REQUEST['openpoll'] === "onat") {
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
  if(empty($complaint) and $_REQUEST['closepoll'] === "onat") {
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
    if(strlen($_REQUEST['pollname'])) {
      $pollname = maybeStripslashes($_REQUEST['pollname']);
    } else {
      $complaint = "Please provide a poll name";
    }
  }
  if(empty($complaint)) {
    if(strlen($_REQUEST['description'])) {
      $description = maybeStripslashes($_REQUEST['description']);
    } else {
      $complaint = "Please provide a description";
    }
  }
  $type = $_REQUEST['type'] === 'radio' ? 'radio' : 'check';
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

  if(empty($complaint)) {
    $choices = array();
    for($i=0;$i<$_REQUEST['numchoices'];$i++) {
      $choice = maybeStripslashes(trim($_REQUEST['choice'][$i]));
      if(strlen($choice)) {
	$choices[] = $choice;
      }
    }
    if(count($choices) < 2) {
      $complaint = "Please enter at least 2 choices";
    }
  }
  if(isset($complaint)) {
    echo "<p class='error'>",$complaint,"</p>\n";
    sdsIncludeFooter();
    exit;
  }
# everything is ok
  $owner = $session->username;

  $pollname_esc = pg_escape_string($pollname);
  $description_esc = pg_escape_string($description);
  $type_esc = pg_escape_string($type);
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

  $transres = sdsQuery("BEGIN");
  if(!$transres)
    contactTech("Could not start transaction");
  pg_free_result($transres);

  $query = <<<ENDQUERY
INSERT INTO polls
       (pollname,       description,       type,       groupname,
        owner,       viewable,     open_date,     close_date)
VALUES ('$pollname_esc','$description_esc','$type_esc','$groupname_esc',
        '$owner_esc',$viewable_esc,$open_date_esc,$close_date_esc)
RETURNING pollid
ENDQUERY;

  $result = sdsQuery($query);
  if(!$result or pg_num_rows($result) != 1) {
    contactTech("Could not create poll",false);
    if(!sdsQuery("ROLLBACK"))
      contactTech("Could not rollback");
    sdsIncludeFooter();
    exit;
  }

  list($pollid) = pg_fetch_array($result);
  pg_free_result($result);

  for($i=0;$i<count($choices);$i++) {
    $choice_esc = pg_escape_string($choices[$i]);
    $query = "INSERT INTO poll_choices (pollid,ordering,description) VALUES ($pollid,$i,'$choice_esc')";
    $result = sdsQuery($query);
    if(!$result or pg_affected_rows($result) != 1) {
      contactTech("could not create poll",false);
      if(!sdsQuery("ROLLBACK"))
	contactTech("Could not rollback");
      sdsIncludeFooter();
      exit;
    }
    pg_free_result($result);
  }

  $transres = sdsQuery("COMMIT");
  if(!$transres) {
    contactTech("Could not commit",false);
    if(!sdsQuery("ROLLBACK"))
      contactTech("Could not rollback");
    sdsIncludeFooter();
    exit;
  }
  pg_free_result($transres);

  echo "<p>Poll created. It will appear after it has been approved by an administrator.</p>\n";
  echo "<p><a href='".sdsLink("polls.php")."'>[ Back to Polls ]</a></p>\n";

  $pollname_mail = $pollname;
  $owner_mail = $owner;
  $groupname_mail = $groupname;
  $type_mail = $type;
  $viewable_mail = $viewable ? 'Yes' : 'No';
  $open_date_mail = isset($open_date) ? $open_date : 'immediately';
  $close_date_mail = isset($close_date) ? $close_date : 'manual';
  $description_mail = $description;
  $choices_mail = implode("\n",$choices);

  $approvemessage=<<<EOF
New poll requires approval before votes can be made. Please visit
http://simmons.mit.edu/sds/polls/polls.php to approve or delete this poll.

Name: $pollname_mail
Owner: $owner_mail
Group: $groupname_mail
Type: $type_mail
Viewable: $viewable_mail
Open date: $open_date_mail
Close date: $close_date_mail

Description:
$description_mail

Choices:
$choices_mail

EOF;
  if(SDB_DATABASE === 'sdb') {
      if(!mail($techemail,"Poll requires approval: $pollname",$approvemessage,
	       "From: \"Simmons Polls\" <polls@simmons.mit.edu>\r\nReply-to: simmons-tech@mit.edu"))
	contactTech("Could not send email");
  } else {
    echo "If this were the REAL DB, the following email would have been sent:\n";
    echo "<pre>",$approvemessage,"</pre>\n";
  }
}

sdsIncludeFooter();
