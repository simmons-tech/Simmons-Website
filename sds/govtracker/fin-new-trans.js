var descriptions;
var selecter;

function showselected() {
  for(var i=0;i<descriptions.length;i++) {
    descriptions[i].style.display =
      descriptions[i].subid == selecter.value ? 'inline' : 'none';
  }
}

function init() {
  var description_holder = document.getElementById('fin-sub-descr');
  var curdesc = description_holder.firstChild;
  descriptions = new Array();
  do {
    if(curdesc.nodeName.toLowerCase() == 'span') {
      curdesc.subid = (curdesc.id.split(':',2))[1];
      descriptions.push(curdesc);
    }
  } while((curdesc = curdesc.nextSibling) != null);

  selecter = document.getElementById('subid-select');
  selecter.onchange = showselected;
  showselected();
}
