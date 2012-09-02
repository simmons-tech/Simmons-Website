/////////////////////////////////////////////////////
/////////////////////////////////////////////////////
//
//               Countdown Clock
//
//           Copyright 2008 mike gieson
//                www.gieson.com
//
/////////////////////////////////////////////////////
/////////////////////////////////////////////////////

// -----------------------------
// Location of the SWF file
// -----------------------------
// Should be a full URL (so it works on every page on your site).
// countdownSWF = "http://www/path/to/countdown.swf";
var countdownSWF = "http://moonshadow.mit.edu/rs/mysteryHunt/countdown.swf";

// -----------------------------
// Click Style
// -----------------------------
// Possible styles are:
//		flip
//		boring
//		digital
//		digital2
var clockKind = "digital2";

// -----------------------------
// Width and Height
// -----------------------------
// The default width and height is 265 x 45
var defaultWidth = 265;
var defaultHeight = 45;

// -----------------------------
// Backgournd Color
// -----------------------------
// If set to false, the background will be transparent (See through to the background of your page)
// Example:
// var colorBackground = false;
// 
// Otherwise set to hex color
// Example:
colorBackground = "#000000";

// -----------------------------
// Number Colors
// -----------------------------
// Will color the numbers of the clock based on a HEX value
// NOTE: The "flip" clock can not be colorized.
var colorWords = "#FFFFFF";

// -----------------------------
// Small Text Color
// -----------------------------
// Will color the small text below the clock number (e.g. DAYS   HOURS   MINUTES   SECONDS)
var colorClockText = "#FFFFFF";


// -----------------------------
// Do When Done
// -----------------------------
// When the countdown is complete, countdownComplete will get "pinged";
function countdownComplete(){
	//alert("done");
}

//////////////////////////////////////
//
//      DO NOT EDIT BELOW HERE
//
//////////////////////////////////////

var randNum=1;
var flashVersion = "6,0,0,0";

function getConfigString(){
	var Aconfigs = new Object();
	Aconfigs.clock				= clockKind;
	Aconfigs.colorBackground	= colorBackground;
	Aconfigs.colorWords			= colorWords;
	Aconfigs.colorClockText		= colorClockText;
	var retval = "";
	for(var prop in Aconfigs){
		retval += "&" + prop + "=" + Aconfigs[prop];
	}
	return retval;
}

function deadline(theDate, theWidth, theHeight, theKind, myBkgd, clockNumbers, smallText){

	var myBkgd = myBkgd || colorBackground;
	var theWidth = theWidth || defaultWidth;
	var theHeight = theHeight || defaultHeight;

	var Aconfigs = new Object();
	Aconfigs.clock				= theKind || clockKind;
	Aconfigs.colorWords			= clockNumbers || colorWords;
	Aconfigs.colorClockText		= smallText || colorClockText;
	var temp = "";
	for(var prop in Aconfigs){
		temp += "&" + prop + "=" + Aconfigs[prop];
	}

	var myConfigs = "deadline=" + theDate + temp;

	randNum++;
	if(myBkgd != false){
		bkgdColor = myBkgd
		tptBkgd_param = "";
		tptBkgd_embed = "";
	} else {
		bkgdColor = "000000";
		tptBkgd_param = '<param name="wmode" value="transparent" />';
		tptBkgd_embed = 'wmode="transparent" ';
	}
	
	flashCode = '';
	flashCode += '<object classid="clsid:D27CDB6E-AE6D-11cf-96B8-444553540000" codebase="http://download.macromedia.com/pub/shockwave/cabs/flash/swflash.cab#version='+flashVersion+'" width="'+theWidth+'" height="'+theHeight+'" id="wimpy'+randNum+'">';
	flashCode += '<param name="movie" value="'+countdownSWF+'" />';
	flashCode += '<param name="loop" value="false" />';
	flashCode += '<param name="menu" value="false" />';
	flashCode += '<param name="quality" value="high" />';
	flashCode += '<param name="flashvars" value="'+myConfigs+'" />';
	flashCode += '<param name="bgcolor" value="'+bkgdColor+'" />';
	flashCode += tptBkgd_param;
	flashCode += '<embed src="'+countdownSWF+'" width="'+theWidth+'" height="'+theHeight+'" bgcolor="'+bkgdColor+'" allowScriptAccess="always" flashvars= "'+myConfigs+'" loop="false" menu="false" quality="high" name="wimpy'+randNum+'" align="middle" allowScriptAccess="sameDomain" type="application/x-shockwave-flash" pluginspage="http://www.macromedia.com/go/getflashplayer" '+tptBkgd_embed+'/></object>';
	document.write(flashCode);
	//document.write('<textarea name="textarea" cols="30" rows="5" wrap="VIRTUAL">'+flashCode+'</textarea>');
}

function countdown(theSpanLength, theWidth, theHeight, theKind, bkgd, clockNumbers, smallText){

	var myBkgd = bkgd || colorBackground;
	var theWidth = theWidth || defaultWidth;
	var theHeight = theHeight || defaultHeight;

	var Aconfigs = new Object();
	Aconfigs.clock				= theKind || clockKind;
	Aconfigs.colorWords			= clockNumbers || colorWords;
	Aconfigs.colorClockText		= smallText || colorClockText;
	var temp = "";
	for(var prop in Aconfigs){
		temp += "&" + prop + "=" + Aconfigs[prop];
	}

	var myConfigs = "countdown=" + theSpanLength + temp;

	randNum++;
	if(myBkgd != false){
		bkgdColor = myBkgd
		tptBkgd_param = "";
		tptBkgd_embed = "";
	} else {
		bkgdColor = "000000";
		tptBkgd_param = '<param name="wmode" value="transparent" />';
		tptBkgd_embed = 'wmode="transparent" ';
	}
	
	flashCode = '';
	flashCode += '<object classid="clsid:D27CDB6E-AE6D-11cf-96B8-444553540000" codebase="http://download.macromedia.com/pub/shockwave/cabs/flash/swflash.cab#version='+flashVersion+'" width="'+theWidth+'" height="'+theHeight+'" id="wimpy'+randNum+'">';
	flashCode += '<param name="movie" value="'+countdownSWF+'" />';
	flashCode += '<param name="loop" value="false" />';
	flashCode += '<param name="menu" value="false" />';
	flashCode += '<param name="quality" value="high" />';
	flashCode += '<param name="flashvars" value="'+myConfigs+'" />';
	flashCode += '<param name="bgcolor" value="'+bkgdColor+'" />';
	flashCode += tptBkgd_param;
	flashCode += '<embed src="'+countdownSWF+'" width="'+theWidth+'" height="'+theHeight+'" bgcolor="'+bkgdColor+'" allowScriptAccess="always" flashvars= "'+myConfigs+'" loop="false" menu="false" quality="high" name="wimpy'+randNum+'" align="middle" allowScriptAccess="sameDomain" type="application/x-shockwave-flash" pluginspage="http://www.macromedia.com/go/getflashplayer" '+tptBkgd_embed+'/></object>';
	document.write(flashCode);
	//document.write('<textarea name="textarea" cols="30" rows="5" wrap="VIRTUAL">'+flashCode+'</textarea>');
}