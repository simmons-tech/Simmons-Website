<?php
header('Content-type: text/javascript');
?>
var months = new Array(<?php
for($i=0;$i<12;$i++) {
  echo "'",strftime("%B",strtotime("+$i months",mktime(0,0,0,1,15))),"'";
  if($i<11) echo ',';
}
		       ?>);

var curStart;
var curEnd;

function daterestrict(section,part) {
  if(section == 'start') {
    var timeNow = new Date();
    var nextWeek = new Date();
    nextWeek.setDate(timeNow.getDate()+7);
    curStart = dateBetween((document.getElementsByName('startmonth'))[0],
			   (document.getElementsByName('startday'))[0],
			   (document.getElementsByName('starthour'))[0],
			   (document.getElementsByName('startmin'))[0],
			   document.getElementById('startmonthselect'),
			   document.getElementById('startdayselect'),
			   document.getElementById('starthourselect'),
			   document.getElementById('startminselect'),
			   document.getElementById('startmonthconst'),
			   document.getElementById('startdayconst'),
			   document.getElementById('starthourconst'),
			   document.getElementById('startminconst'),
			   timeNow,nextWeek,part);
    part = 'all';
  }
  var nextDay = new Date();
  nextDay.setTime(curStart.getTime());
  nextDay.setDate(nextDay.getDate()+1);
  curEnd = dateBetween((document.getElementsByName('endmonth'))[0],
		       (document.getElementsByName('endday'))[0],
		       (document.getElementsByName('endhour'))[0],
		       (document.getElementsByName('endmin'))[0],
		       document.getElementById('endmonthselect'),
		       document.getElementById('enddayselect'),
		       document.getElementById('endhourselect'),
		       document.getElementById('endminselect'),
		       document.getElementById('endmonthconst'),
		       document.getElementById('enddayconst'),
		       document.getElementById('endhourconst'),
		       document.getElementById('endminconst'),
		       curStart,nextDay,part);
}

function dateBetween(monthselect,dayselect,hourselect,minselect,
		     monthdispselect,daydispselect,hourdispselect,
		     mindispselect,
		     monthdispconst,daydispconst,hourdispconst,mindispconst,
		     start,end,modified) {
  var timeLater;

  var curMonth = monthselect.options[monthselect.selectedIndex].value;
  var curDay = dayselect.options[dayselect.selectedIndex].value;
  var curHour = hourselect.options[hourselect.selectedIndex].value;
  var curMin = minselect.options[minselect.selectedIndex].value;

  switch(modified) {
  case 'all':
    var curMonth = monthselect.options[monthselect.selectedIndex].value;
    while(monthselect.length > 0) {
      monthselect.remove(0);
    }
    var monthselectOpt = document.createElement('option');
    monthselectOpt.value = start.getMonth() + 1;
    monthselectOpt.text = months[start.getMonth()];
    monthselect.add(monthselectOpt,null);
    if(end.getMonth() == start.getMonth()) {
      monthselect.selectedIndex = 0;
      monthdispconst.innerHTML = months[start.getMonth()];
      monthdispselect.style.display="none";
      monthdispconst.style.display="table-cell";
    } else {
      monthselectOpt = document.createElement('option');
      monthselectOpt.value = end.getMonth() + 1;
      monthselectOpt.text = months[end.getMonth()];
      monthselect.add(monthselectOpt,null);
      if(curMonth == end.getMonth()+1) {
	monthselect.selectedIndex = 1;
      } else {
	starmonth.selectedIndex = 0;
      }
      monthdispconst.style.display="none";
      monthdispselect.style.display="table-cell";
    }
    curMonth = monthselect.options[monthselect.selectedIndex].value;
  case 'month':
    while(dayselect.length > 0) {
      dayselect.remove(0);
    }
    timeLater = new Date();
    timeLater.setDate(1);
    if(timeLater.getMonth() != curMonth-1) {
      timeLater.setMonth(timeLater.getMonth()+1);
    }
    timeLater.setHours(23);
    timeLater.setMinutes(59);
    timeLater.setSeconds(59);
    var dayselectOpt;
    for(day=1;;day++) {
      timeLater.setDate(day);
      if(timeLater.getMonth() != curMonth-1) {
	break;
      }
      if(timeLater > start) {
	dayselectOpt = document.createElement('option');
	dayselectOpt.value = day;
	dayselectOpt.text = day;
	dayselect.add(dayselectOpt,null);
      }
      if(timeLater > end) {
	break;
      }
    }
    if(dayselect.length == 1) {
      dayselect.selectedIndex = 0;
      daydispconst.innerHTML = dayselect.options[0].text;
      daydispselect.style.display="none";
      daydispconst.style.display="table-cell";
    } else {
      dayselect.selectedIndex = 0;
      for(i=0;i<dayselect.length;i++) {
	if(curDay == dayselect.options[i].value) {
	  dayselect.selectedIndex = i;
	  break;
	}
      }
      daydispconst.style.display="none";
      daydispselect.style.display="table-cell";
    }
    curDay = dayselect.options[dayselect.selectedIndex].value;
  case 'day':
    while(hourselect.length > 0) {
      hourselect.remove(0);
    }
    timeLater = new Date();
    timeLater.setDate(1);
    if(timeLater.getMonth() != curMonth-1) {
      timeLater.setMonth(timeLater.getMonth()+1);
    }
    timeLater.setDate(curDay);
    timeLater.setMinutes(59);
    timeLater.setSeconds(59);
    var hourselectOpt;
    for(hour=0;;hour++) {
      timeLater.setHours(hour);
      if(timeLater.getDate() != curDay) {
	break;
      }
      if(timeLater > start) {
	hourselectOpt = document.createElement('option');
	hourselectOpt.value = hour;
	if(hour < 10) {
	  hourselectOpt.text = '0' + hour;
	} else {
	  hourselectOpt.text = hour;
	}
	hourselect.add(hourselectOpt,null);
      }
      if(timeLater > end) {
	break;
      }
    }
    if(hourselect.length == 1) {
      hourselect.selectedIndex = 0;
      hourdispconst.innerHTML = hourselect.options[0].text;
      hourdispselect.style.display="none";
      hourdispconst.style.display="table-cell";
    } else {
      hourselect.selectedIndex = 0;
      for(i=0;i<hourselect.length;i++) {
	if(curHour == hourselect.options[i].value) {
	  hourselect.selectedIndex = i;
	  break;
	}
      }
      hourdispconst.style.display="none";
      hourdispselect.style.display="table-cell";
    }
    curHour = hourselect.options[hourselect.selectedIndex].value;
  case 'hour':
    while(minselect.length > 0) {
      minselect.remove(0);
    }
    timeLater = new Date();
    timeLater.setDate(1);
    if(timeLater.getMonth() != curMonth-1) {
      timeLater.setMonth(timeLater.getMonth()+1);
    }
    timeLater.setDate(curDay);
    timeLater.setHours(curHour);
    timeLater.setSeconds(59);
    var minselectOpt;
    for(min=0;;min+=15) {
      timeLater.setMinutes(min+14);
      if(timeLater.getHours() != curHour) {
	break;
      }
      if(timeLater > start) {
	minselectOpt = document.createElement('option');
	minselectOpt.value = min;
	if(min < 10) {
	  minselectOpt.text = '0' + min;
	} else {
	  minselectOpt.text = min;
	}
	minselect.add(minselectOpt,null);
      }
      if(timeLater > end) {
	break;
      }
    }
    if(minselect.length == 1) {
      minselect.selectedIndex = 0;
      mindispconst.innerHTML = minselect.options[0].text;
      mindispselect.style.display="none";
      mindispconst.style.display="table-cell";
    } else {
      minselect.selectedIndex = 0;
      for(i=0;i<minselect.length;i++) {
	if(curMin == minselect.options[i].value) {
	  minselect.selectedIndex = i;
	  break;
	}
      }
      mindispconst.style.display="none";
      mindispselect.style.display="table-cell";
    }
    curMin = minselect.options[minselect.selectedIndex].value;
  }
  var finalDate = new Date();
  finalDate.setDate(1);
  if(finalDate.getMonth() != curMonth-1) {
    finalDate.setMonth(finalDate.getMonth()+1);
  }
  finalDate.setDate(curDay);
  finalDate.setHours(curHour);
  finalDate.setMinutes(curMin);
  return finalDate;
}

daterestrict('start','all');
