<?php
if(realpath($_SERVER['SCRIPT_FILENAME']) === realpath(__FILE__))
  exit;
# functions to simplify ordering of tables

# make a <th> with sorting code
#
# makeSortTH("Lastname",0,$sortby)
# makeSortTH("Lastname",0,$sortby,"foo=bar",'class="stuff"',1)
function makeSortTH($title,$num,$sortby,$scriptargs = "",$th_extra = "",
		    $default_order = 0) {
  if(strlen($th_extra))
    $th_extra = ' '.$th_extra;
  echo '<th',$th_extra,'>';
  makeSortCode($title,$num,$sortby,$scriptargs,$default_order);
  echo "</th>\n";
}

# make bare sorting code
#
# makeSortCode("Lastname",0,$sortby)
# makeSortCode("Lastname",0,$sortby,"foo=bar",1)
function makeSortCode($title,$num,$sortby,$scriptargs="",$default_order=0) {
  $sortorder = $sortby - 2*$num;
  $sortkey = $sortorder==0 || $sortorder==1;
  if(!$sortkey)
    $sortorder = 1-$default_order;
  # giving no script in the href indicates current page
  echo '<a href="', sdsLink('',($scriptargs?$scriptargs.'&amp;':'').
			    'sortby='.($num*2+(1-$sortorder))), '">',
    $title,'</a>';
  if($sortkey)
    echo $sortorder?'&uarr;':'&darr;';
}

# find the corect index to sort by
# 0 = field 0, asc
# 1 = field 0, desc
# 2 = field 1, asc etc.
#
# $sortby = getSortby($_REQUEST['sortby'],0,5,'packages_sortby')
function getSortby(&$request_sortby,$default,$numfields,$data_field = '') {
  global $session;
  if(isset($request_sortby)) {
    $sortby = (int)$request_sortby;
  } elseif($data_field and isset($session->data[$data_field])) {
    $sortby = (int)$session->data[$data_field];
  } else {
    $sortby = $default;
  }
  if($sortby < 0 || $sortby >= 2*$numfields) {
    $sortby = $default;
  }
  if($data_field) {
    $session->data[$data_field] = $sortby;
    $session->saveData();
  }
  return $sortby;
}
