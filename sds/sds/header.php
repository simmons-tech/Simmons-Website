<?php
if(realpath($_SERVER['SCRIPT_FILENAME']) === realpath(__FILE__))
  exit;

$ingroup = false;
function navGroup($text) {
  global $ingroup;
  if($ingroup) {
    echo "    </ul>\n  </li>\n";
    $ingroup = false;
  }
  if(isset($text)) {
    echo "  <li>",$text,"\n    <ul>\n";
    $ingroup = true;
  }
}

function navLink($text, $url, $parameters = "") {
  if (preg_match ("/^http/", $url))
    $link = sdsLink($url, $parameters);
  else
    $link = sdsLink(SDS_BASE_URL . $url, $parameters);

  echo "      <li><a href='",$link,"'>",$text,"</a></li>\n";
}

require_once(SDS_BASE . "/sds/setupremind.php");

?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN"
        "http://www.w3.org/TR/html4/loose.dtd">
<html>

<head>
  <title><?php echo $sdsPageHeadTitle ?></title>
  <meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
  <link rel="stylesheet" href="https://simmons.mit.edu/simmons.css" type="text/css" /> 
  
  <script type="text/javascript">

  var _gaq = _gaq || [];
  _gaq.push(['_setAccount', 'UA-29172658-1']);
  _gaq.push(['_trackPageview']);

  (function() {
    var ga = document.createElement('script'); ga.type = 'text/javascript'; ga.async = true;
    ga.src = ('https:' == document.location.protocol ? 'https://ssl' : 'http://www') + '.google-analytics.com/ga.js';
    var s = document.getElementsByTagName('script')[0]; s.parentNode.insertBefore(ga, s);
  })();

  </script> 
  <?php echo $sdsHeadHTML ?>
</head>
<body<?php if(isset($sdsBodyAttrs)) { echo ' ',$sdsBodyAttrs; } ?>>

<div class="titlebar">
<table width="100%">
<tr>
  <td>
    <h1><?php echo $sdsPageHtmlTitle ?></h1>
  </td>

  <td style="text-align:right">
<?php 
$crm = count($session->data['reminders']);
if($crm > 0 and !sdsIsShowingReminders()){
  $plural = "";
  if($crm > 1)
    $plural = "s";

  echo "You have <b><a href='",sdsLink(SDS_BASE_URL . "directory/update.php"),
    "'>",$crm," reminder",$plural,"</b></a> | ";
}
if(strlen($session->username)) {
  echo "Current Login: <a href='",
    sdsLink(SDS_BASE_URL ."login/certs/login.php"),"'>",
    htmlspecialchars($session->username),"</a>";
}
?>
  </td>
</tr>
</table>
</div>

<?php
if($crm > 0 and sdsIsShowingReminders()) {
?>
<!-- This is a customized version of code from the website cited below -->
<!-- *********************************************************
     * You may use this code for free on any web page provided that 
     * these comment lines and the following credit remain in the code.
     * Floating Div from http://www.javascript-fx.com
     ********************************************************  -->
<div id="divBottomRight"
      style="padding: 2pt 2pt 2pt 12pt;
             background: url(<?php echo SDS_BASE_URL ?>/sds/bg.png);
             width:200px; position:absolute; font-size: small">

  <b><u>Reminders:</u></b>
  <ul style="margin-left: 0">
<?php
  foreach ($session->data['reminders'] as $rname => $rmsg){
    echo "    <li>",$rmsg,"</li>\n";
  }
?>
  </ul>
</div>

<script type="text/javascript">
var ns = (navigator.appName.indexOf("Netscape") != -1);
var d = document;
var px = document.layers ? "" : "px";
function JSFX_FloatDiv(id, sx, sy)
{
	var el=d.getElementById?d.getElementById(id):d.all?d.all[id]:d.layers[id];
	window[id + "_obj"] = el;
	if(d.layers)el.style=el;
        el.nominalSy = el.nominalCy = sy;
        el.nominalSx = sx;
	el.cx = el.sx = sx;el.cy = el.sy = sy;
	el.sP=function(x,y){this.style.left=x+px;this.style.top=y+px;};
	el.flt=function()
	{
		var pX, pY;
                this.sy = this.nominalSy-this.offsetHeight;
                this.sx = this.nominalSx - this.offsetWidth;
		pX = (this.sx >= 0) ? 0 : ns ? innerWidth : 
		document.documentElement && document.documentElement.clientWidth ? 
		document.documentElement.clientWidth : document.body.clientWidth;
		pY = ns ? pageYOffset : document.documentElement && document.documentElement.scrollTop ? 
		document.documentElement.scrollTop : document.body.scrollTop;
		if(this.sy<0) 
		pY += ns ? innerHeight : document.documentElement && document.documentElement.clientHeight ? 
		document.documentElement.clientHeight : document.body.clientHeight;
		this.cx += (pX + this.sx - this.cx)/4;this.cy += (pY + this.sy - this.cy)/4;
if(!this.init)
		{
			this.init=true;
			this.cx = pX+this.sx;
			this.cy = pY+this.sy;
		}		
this.sP(this.cx, this.cy);
		setTimeout(this.id + "_obj.flt()", 40);
	}
	return el;
}
JSFX_FloatDiv("divBottomRight", -45, -45).flt();

</script>
<?php
}
?>
<div class="pageBody">

<table>
<tr>
<td style="vertical-align:top">

<ul class="navbar">
<?php

##
## SIMMONS DB GROUP
##

navGroup("SIMMONS DB");
navLink("Home", SDS_HOME_URL);
if(!empty($session->groups['USERS'])) {
  //navLink("Directory", "https://simmons.mit.edu/directory");
  navLink("Directory", "directory/");
  navLink("Student Officers","directory/officers.php");
  navLink("Medlinks","directory/medlinks.php");
  navLink("GRTs","directory/grt.php");
  navLink("Mailing Lists", "groups/view_mailing_lists.php");
  navLink("Votes and Polls", "polls/polls.php");
  navLink("Lotteries", "lotteries/");
  navLink("About the DB","users/about.php"); 
}

##
## GOVTRACKER
##

if(!empty($session->groups['USERS'])) {
  navGroup("GOVTRACKER");
  navLink("Lounge Expenses","loungeexpense/index.php");
  navLink("Lounge Event Proposals","loungeexpense/proposals.php");
  navLink("House Finances","govtracker/fin-ledger.php");
  navLink("House Meetings","govtracker/index.php");
  navLink("Submit a Proposal","govtracker/submitproposal.php");
  if(!empty($session->groups['HOUSE-COMM-LEADERSHIP'])){
     navLink("Meeting Presentation","govtracker/downloadagenda.php");
  }
}


##
## PERSONAL INFO GROUP
##

if(!empty($session->groups['USERS'])) {
  navGroup("PERSONAL INFO");
  navLink("My Profile", "directory/update.php");
  navLink("Guest List", "users/guestlist.php");
  navLink("Lounge Membership", "users/loungesignup.php");
  navLink("Login Password", "users/password.php");
  navLink("Printer Use","users/printeruse.php");
  navLink("Rooming Lottery","users/lottery.php");
}

##
## PACKAGES GROUP
##

if(!empty($session->groups['USERS']) or !empty($session->groups['DESK'])) {
  navGroup('PACKAGES');
  if(!empty($session->groups['USERS'])) {
    navLink("My Waiting Packages","packages/mypackages.php");
  }
  if(!empty($session->groups['DESK'])) {
    navLink("Package Registration","packages/checkin.php");
    navLink("Package Pickup","packages/pickup.php");
    navLink("All Waiting Packages","packages/viewpackages.php");
  }
}

##
## DESK MOVIES GROUP
##

if(!empty($session->groups['USERS']) or !empty($session->groups['DESK'])) {
  navGroup("MOVIES");
  navLink("List Desk Movies", "simmovies/list.php");
  if(!empty($session->groups['USERS'])) {
    navLink("My Loans", "simmovies/myloans.php");
  }
  if(!empty($session->groups['DESK']) or
     !empty($session->groups['MOVIEADMINS'])) {
    navLink("Movie Check In","simmovies/checkin.php");
  }
  if(!empty($session->groups['MOVIEADMINS'])) {
    navLink("Current Loans","simmovies/allloans.php");
    navLink("Manage Movies","simmovies/list.php","showall=1");
  }
}

##
## LIBRARY GROUP
##

if(!empty($session->groups['USERS'])) {
  navGroup("LIBRARY");
  navLink("Catalog", "http://www.librarything.com/catalog/simmons_hall");
  navLink("User's Guide (PDF)", "LIB_SIMMONS%20USER%20GUIDE%20SIMPLIFIED.pdf");
}

##
## ADMINISTRATORS GROUP
##

if(!empty($session->groups['ADMINISTRATORS'])) {
  navGroup("ADMINISTRATORS");
  navLink("ACL Control Panel", "administrators/");
  navLink("Be Another User", "administrators/sudo.php");
  navLink("Simmons DB Options","administrators/options.php");
  navLink("Refresh Group Cache", "sds/updateGroupMembershipCache.php");
  navLink("Refresh Mailing Lists", "groups/refresh_mailing_lists.php");
  navLink("phpPgAdmin", "https://simmons.mit.edu/phpPgAdmin/");
}

##
## DESK GROUP
##

if(!empty($session->groups['DESK'])) {
  navGroup("DESK");
  navLink("Full Directory Listing", "desk/full_directory.php");
  navLink("Search Guest List", "desk/guestlist.php");
  if(!empty($session->groups['DESK-CAPTAINS'])) {
    navLink("Guest List History","desk/guestlisthistory.php");
  }
}

##
## ROOMING GROUP
##

if(!empty($session->groups['RAC']) or
   !empty($session->groups['ADMINISTRATORS'])) {
  navGroup("ROOMING");
  navLink("Add Directory Entry", "rac/add.php");
  navLink("Modify/Remove Entry", "rac/use_directory.php");
  navLink("Download Directory (csv)", "rac/csv.php");
  navLink("Batch Update (csv)", "rac/batchupdate.php");
  navLink("Clear Rooms","rac/clearrooms.php");
  navLink("Rooming Lottery", "rac/lottery.php");
  navLink("Room Status Summary", "rac/roomstatus.php");
  navLink("Room History", "rac/roomhistory.php");
}

##
## LOUNGE ADMIN GROUP
##

if(!empty($session->groups['LOUNGE-CHAIRS']) or
   !empty($session->groups['FINANCIAL-ADMINS'])) {
  navGroup("LOUNGE ADMIN");
  if(!empty($session->groups['LOUNGE-CHAIRS'])) {
    navLink("Lounge Management","lounges/");
    navLink("Lounge Membership","lounges/showmembership.php");
  }
  if(!empty($session->groups['FINANCIAL-ADMINS'])) {
    navLink("Lounge Allocations","lounges/editallocations.php");
  }
}

##
## WIKI GROUP
##

if(!empty($session->groups['USERS'])) {
  navGroup("WIKI");
  navLink("Simmons Wiki","http://simmons.mit.edu/wiki/");
  navLink("Access Control","groups/wikiaccess.php");
  navLink("Account Creation","users/wiki_account.php");
}

navGroup(null);

?>
</ul>
</td>

<td style="vertical-align:top;width:100%">
