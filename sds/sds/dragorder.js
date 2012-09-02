/* Code for reordering items using a drag-and-drop interface */

var newID = 0;

function dragdropSetup(dragareaID,orderReturnID) {
  var dragdropstate = new Object();
  dragdropstate.posIndicators = new Array();

  var javascriptWarning = document.getElementById('javascriptWarning');
  if(javascriptWarning)
    javascriptWarning.style.display = 'none';

  dragdropstate.dragarea = document.getElementById(dragareaID);
  dragdropstate.orderReturn = document.getElementById(orderReturnID);

  if(dragdropstate.dragarea) {
    var nodes = dragdropstate.dragarea.childNodes;
    for(var i=0;i<nodes.length;i++) {
      if(nodes[i].className == 'dragitem')
        nodes[i].onmousedown = function (e) {grabItem(e,dragdropstate)};
      if(nodes[i].className == 'posIndicator')
        dragdropstate.posIndicators.push(nodes[i]);
    }
    setReturnString(dragdropstate);
    findYposes(dragdropstate);
  }

  return dragdropstate;
}

function setReturnString(dragdropstate) {
  if(dragdropstate.orderReturn && dragdropstate.dragarea) {
    dragdropstate.orderReturn.value = "";
    var nodes = dragdropstate.dragarea.childNodes;
    for(var i=0;i<nodes.length;i++) {
      if(nodes[i].className != 'dragitem')
        continue;
      dragdropstate.orderReturn.value += ';' + nodes[i].id;
    }
  }
}

function findYposes(dragdropstate) {
  dragdropstate.indicatorYpos = new Array();
  for(var i=0;i<dragdropstate.posIndicators.length;i++) {
    // object position code adapted from
    // http://www.quirksmode.org/js/findpos.html
    var curObj = dragdropstate.posIndicators[i];
    dragdropstate.indicatorYpos[i] = 0;
    do {
      dragdropstate.indicatorYpos[i] += curObj.offsetTop;
    } while(curObj = curObj.offsetParent);
  }
}

function grabItem(event,dragdropstate) {
  event = event || window.event;
  if(dragdropstate.grabbedItem)
    cancelMove(dragdropstate);

  if(event.target.nodeName == 'INPUT') return;

  dragdropstate.grabbedItem = event.currentTarget || event.srcElement;
  dragdropstate.grabbedItem.style.backgroundColor = '#ffdddd';

  dragdropstate.dragarea.onmouseover = function (e) {setDrop(e,dragdropstate)};
  dragdropstate.dragarea.onmouseout =
	 function (e) {setNoDrop(e,dragdropstate)};
  setDrop(null,dragdropstate);

  dragdropstate.dragarea.onmouseup = function (e) {dropItem(e,dragdropstate)};
  document.onmouseup = function (e) {cancelMove(e,dragdropstate)};

  if(event.preventDefault)
    event.preventDefault();
  event.returnValue = false; // ie
  return false;
}

function showDropPos(event,dragdropstate) {
  event = event || window.event;
  // event position code adapted from
  // http://www.webreference.com/programming/javascript/mk/column2/
  var ypos = event.pageY ||
             event.clientY + document.body.scrollTop - document.body.clientTop;
  var newClosest;
  var closestDist = Number.POSITIVE_INFINITY;
  for(var i=0;i<dragdropstate.posIndicators.length;i++) {
    var thisdist = Math.abs(ypos - dragdropstate.indicatorYpos[i]);
    if(thisdist < closestDist) {
      newClosest = dragdropstate.posIndicators[i];
      closestDist = thisdist;
    }
  }
  if(newClosest != dragdropstate.closestIndicator) {
    if(dragdropstate.closestIndicator)
      dragdropstate.closestIndicator.style.visibility = 'hidden';
    dragdropstate.closestIndicator = newClosest;
    dragdropstate.closestIndicator.style.visibility = 'visible';
  }
}

function setDrop(event,dragdropstate) {
  dragdropstate.dragarea.onmousemove =
	 function (e) {showDropPos(e,dragdropstate)};
}

function setNoDrop(event,dragdropstate) {
  event = event || window.event;
  if(event) {
    var moveto = event.toElement;
    if(moveto) {
      do {
        if(moveto == dragdropstate.dragarea)
          return;
      } while(moveto = moveto.parentNode);
    }
  }

  if(dragdropstate.closestIndicator) {
    dragdropstate.closestIndicator.style.visibility = 'hidden';
    dragdropstate.closestIndicator = null;
  }
  dragdropstate.dragarea.onmousemove = null;
}

function cancelMove(event,dragdropstate) {
  event = event || window.event;
  if(dragdropstate.grabbedItem) {
    dragdropstate.grabbedItem.style.backgroundColor = '#dddddd';
    dragdropstate.grabbedItem = null;
  }

  dragdropstate.dragarea.onmouseover = null;
  dragdropstate.dragarea.onmouseout = null;
  setNoDrop(null,dragdropstate);

  dragdropstate.dragarea.onmouseup = null;
  document.onmouseup = null;

  if(event) {
    if(event.preventDefault)
      event.preventDefault();
    event.returnValue = false; // ie
    return false;
  }
}

function dropItem(event,dragdropstate) {
  if(!dragdropstate.closestIndicator)
    return;
  // reseting state will be handeled by propagation to cancelMove
  var precedingIndicator = dragdropstate.grabbedItem.previousSibling;
  while(precedingIndicator.className != 'posIndicator') {
    precedingIndicator = precedingIndicator.previousSibling;
  }
  dragdropstate.grabbedItem.parentNode.
    insertBefore(dragdropstate.grabbedItem,dragdropstate.closestIndicator);
  dragdropstate.grabbedItem.parentNode.
    insertBefore(precedingIndicator,dragdropstate.grabbedItem);
  findYposes(dragdropstate);
  setReturnString(dragdropstate);
}

function addItem(dragdropstate,templateID) {
  if(!dragdropstate) return;

  var template = document.getElementById(templateID);
  if(!template) return;
  var newnode = template.cloneNode(true);
  newnode.className = 'dragitem';
  newnode.id = 'newdrag:' + newID;
  newID++;

  var fillNames = new Array(newnode);
  while(fillNames.length > 0) {
    var curItem = fillNames.shift();
    if(curItem.name)
      curItem.name = curItem.name.replace('[]','[' + newnode.id + ']');
    for(var i=0;i<curItem.childNodes.length;i++)
      fillNames.push(curItem.childNodes[i]);
  }

  var newSeparator = document.createElement('div');
  newSeparator.className = 'posIndicator';

  dragdropstate.dragarea.appendChild(newnode);
  dragdropstate.dragarea.appendChild(newSeparator);

  dragdropstate.posIndicators.push(newSeparator);
  newnode.onmousedown = function (e) {grabItem(e,dragdropstate)};

  setReturnString(dragdropstate);
  findYposes(dragdropstate);

  return newnode;
}
