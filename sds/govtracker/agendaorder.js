/* Code for reordering agenda items using a drag-and-drop interface */

var orderReturn;
var proplist;
var grabbedItem;

var posIndicators = new Array();
var indicatorYpos;
var closestIndicator;

function dragdropSetup() {
  var javascriptWarning = document.getElementById('javascriptWarning');
  if(javascriptWarning)
    javascriptWarning.style.display = 'none';
  orderReturn = document.getElementById('orderReturn');

  proplist = document.getElementById('proplist');
  if(proplist) {
    var nodes = proplist.childNodes;
    for(var i=0;i<nodes.length;i++) {
      if(nodes[i].className == 'dragitem')
        nodes[i].onmousedown = grabItem;
      if(nodes[i].className == 'posIndicator')
        posIndicators.push(nodes[i]);
    }
    setReturnString();
    findYposes();
  }
}

function setReturnString() {
  if(orderReturn && proplist) {
    orderReturn.value = "";
    var nodes = proplist.childNodes;
    for(var i=0;i<nodes.length;i++) {
      if(nodes[i].className != 'dragitem')
        continue;
      orderReturn.value += ':'+(nodes[i].id.split(':'))[1];
    }
  }
}

function findYposes() {
  indicatorYpos = new Array();
  for(var i=0;i<posIndicators.length;i++) {
    // object position code adapted from
    // http://www.quirksmode.org/js/findpos.html
    var curObj = posIndicators[i];
    indicatorYpos[i] = 0;
    do {
      indicatorYpos[i] += curObj.offsetTop;
    } while(curObj = curObj.offsetParent);
  }
}

function grabItem(event) {
  event = event || window.event;
  if(grabbedItem)
    cancelMove();
  grabbedItem = event.currentTarget || event.srcElement;
  grabbedItem.style.backgroundColor = '#ffdddd';

  proplist.onmouseover = setDrop;
  proplist.onmouseout = setNoDrop;
  setDrop();

  proplist.onmouseup = dropItem;
  document.onmouseup = cancelMove;

  if(event.preventDefault)
    event.preventDefault();
  event.returnValue = false; // ie
  return false;
}

function showDropPos(event) {
  event = event || window.event;
  // event position code adapted from
  // http://www.webreference.com/programming/javascript/mk/column2/
  var ypos = event.pageY ||
             event.clientY + document.body.scrollTop - document.body.clientTop;
  var newClosest;
  var closestDist = Number.POSITIVE_INFINITY;
  for(var i=0;i<posIndicators.length;i++) {
    var thisdist = Math.abs(ypos - indicatorYpos[i]);
    if(thisdist < closestDist) {
      newClosest = posIndicators[i];
      closestDist = thisdist;
    }
  }
  if(newClosest != closestIndicator) {
    if(closestIndicator)
      closestIndicator.style.visibility = 'hidden';
    closestIndicator = newClosest;
    closestIndicator.style.visibility = 'visible';
  }
}

function setDrop() {
  proplist.onmousemove = showDropPos;
}

function setNoDrop(event) {
  event = event || window.event;
  if(event) {
    var moveto = event.toElement;
    if(moveto) {
      do {
        if(moveto == proplist)
          return;
      } while(moveto = moveto.parentNode);
    }
  }

  if(closestIndicator) {
    closestIndicator.style.visibility = 'hidden';
    closestIndicator = null;
  }
  proplist.onmousemove = null;
}

function cancelMove(event) {
  event = event || window.event;
  if(grabbedItem) {
    grabbedItem.style.backgroundColor = '#dddddd';
    grabbedItem = null;
  }

  proplist.onmouseover = null;
  proplist.onmouseout = null;
  setNoDrop();

  proplist.onmouseup = null;
  document.onmouseup = null;

  if(event) {
    if(event.preventDefault)
      event.preventDefault();
    event.returnValue = false; // ie
    return false;
  }
}

function dropItem() {
  if(!closestIndicator)
    return;
  // reseting state will be handeled by propagation to cancelMove
  var precedingIndicator = grabbedItem.previousSibling;
  while(precedingIndicator.className != 'posIndicator') {
    precedingIndicator = precedingIndicator.previousSibling;
  }
  grabbedItem.parentNode.insertBefore(grabbedItem,closestIndicator);
  grabbedItem.parentNode.insertBefore(precedingIndicator,grabbedItem);
  findYposes();
  setReturnString();
}