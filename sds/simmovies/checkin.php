<?php
require_once('../sds.php');
sdsRequireGroup("DESK","MOVIEADMINS");

sdsIncludeHeader("Simmons Movie Check In");

$date_format = 'FMDD Mon HH24:MI';

if(empty($_REQUEST['loanid']) OR preg_match('/\D/',$_REQUEST['loanid'])) {
# nothing passed: list checked out items

  require_once('../sds/ordering.inc.php');

  $sortby = getSortby($_REQUEST['sortby'],4,6,'movie_loan_admin_sortby');

  $orderbyarray =
    array (# type asc,desc
	   "typename ASC",
	   "typename DESC",
           # box asc,desc
	   "to_number(substring(box_number from '^[[:digit:]]+'),'9999999999') ASC, box_number ASC",
	   "to_number(substring(box_number from '^[[:digit:]]+'),'9999999999') DESC, box_number DESC",
           # title asc,desc
	   "info.title ASC",
	   "info.title DESC",
           # loanee asc,desc
	   "lastname ASC,firstname ASC,info.title ASC",
	   "lastname DESC,firstname DESC,info.title ASC",
           # loan date asc,desc
	   "date_trunc('minute',checkout) ASC,info.title ASC",
	   "date_trunc('minute',checkout) DESC,info.title ASC",
           # due date asc,desc
	   "date_trunc('minute',CASE WHEN real_duration = interval '0' THEN now()+interval '5 years' ELSE checkout+real_duration END) ASC,info.title ASC",
	   "date_trunc('minute',CASE WHEN real_duration = interval '0' THEN now()+interval '5 years' ELSE checkout+real_duration END) DESC,info.title ASC",
	   );

  $query = <<<ENDQUERY
SELECT loanid,info.title,firstname,lastname,
       to_char(checkout,'$date_format') AS checkout_str,
       CASE WHEN real_duration = interval '0' THEN 'Never' ELSE
         to_char(checkout+real_duration,'$date_format') END AS due_date,
       CASE WHEN real_duration = interval '0' THEN false ELSE
         checkout+real_duration<now() END AS overdue,
       typename,box_number
FROM (SELECT loanid,title,username,checkout,
             COALESCE(item_loan_duration,loan_duration) AS real_duration,
             typename,box_number
      FROM movie_loans JOIN movie_instances USING (instanceid)
                       JOIN movie_items USING (movieid)
                       JOIN movie_types USING (typeid)
      WHERE checkin IS NULL AND NOT hidden) AS info
     LEFT JOIN directory USING (username)
ORDER BY $orderbyarray[$sortby],
	 to_number(substring(box_number FROM '^[[:digit:]]+'),'9999999999'),
	 box_number
ENDQUERY;
  $result = sdsQuery($query);
  if(!$result)
    contactTech("Could not search loans");
?>

<table class="movielist">
  <colgroup span="2" width="0*" />
  <colgroup />
  <colgroup span="3" width="0*" />
  <tr>
<?php
  makeSortTH("Type",0,$sortby);
  makeSortTH("Box",1,$sortby);
  makeSortTH("Title",2,$sortby);
  makeSortTH("Loaned To",3,$sortby);
  makeSortTH("Loan Date",4,$sortby);
  makeSortTH("Due Date",5,$sortby);
  echo "  </tr>\n";

  $rowparity = 1;
  while($record = pg_fetch_array($result)) {
    $rowparity = 1-$rowparity;
    $rowclass = $rowparity ? 'oddrow' : 'evenrow';
    if($record['overdue'] === 't') {
      $rowclass = 'overdue';
    }
    echo "  <tr class='",$rowclass,"'>\n";
    echo "    <td>",htmlspecialchars($record['typename']),"</td>\n";
    echo "    <td>",htmlspecialchars($record['box_number']),"</td>\n";
    echo '    <td class="titlecell"><a href="',
      sdsLink("checkin.php","loanid=".$record['loanid']),'">',
      htmlspecialchars($record['title']),"</a></td>\n";
    echo '    <td><a href="',
      sdsLink("checkin.php","loanid=".$record['loanid']),'">',
      htmlspecialchars($record['firstname']." ".$record['lastname']),
      "</a></td>\n";
    echo "    <td>",htmlspecialchars($record['checkout_str']),"</td>\n";
    echo "    <td>",htmlspecialchars($record['due_date']),"</td>\n";
    echo "  </tr>\n";
  }
  echo "</table>\n";
  pg_free_result($result);
} elseif(!isset($_REQUEST['do_checkin'])) {
# check in confirmation
# loanid is safe here
  $loanid = $_REQUEST['loanid'];
  $query = <<<ENDQUERY
SELECT box_number,title,username,num_disks,typename
FROM movie_loans JOIN movie_instances USING (instanceid)
                 JOIN movie_items USING (movieid)
                 JOIN movie_types USING (typeid)
WHERE loanid=$loanid AND NOT hidden AND checkin IS NULL
ENDQUERY;
  $result = sdsQuery($query);
  if(!$result)
    contactTech("Could not search loans");
  if(pg_num_rows($result) != 1) {
    echo "<h1 class='error'>That loan does not seem to exist</h1>\n";
    sdsIncludeFooter();
    exit;
  }
  $record = pg_fetch_array($result);
  echo "<h1>",htmlspecialchars($record['typename'])," ",
    htmlspecialchars($record['box_number']),": ",
    htmlspecialchars($record['title'])," &mdash; loaned to ",
    sdsGetFullName($record['username']),"</h1>\n";
  if($record['num_disks']) {
    echo "<h2>Please verify that ",$record['num_disks']," disk",
      $record['num_disks']>1 ? 's were' : ' was', " returned</h2>\n";
  }
  echo "<form action='checkin.php',method='post'>\n";
  echo sdsForm();
  echo "  <input type='hidden' name='loanid' value='",$loanid,"' />\n";
  echo "  <input type='submit' name='do_checkin' value='Check in' />\n";
  echo "</form>\n";
  pg_free_result($result);
} else {
# perform action
# loanid is safe here
  $loanid = $_REQUEST['loanid'];
  $query = "UPDATE movie_loans SET checkin=now(),checkin_by='"
    . pg_escape_string($session->username) . "' WHERE loanid=$loanid;";
  $query .= "UPDATE movie_instances SET checked_out=false FROM movie_loans WHERE movie_instances.instanceid=movie_loans.instanceid AND loanid=$loanid;";
  $result = sdsQuery($query);
  if(!$result)
    contactTech("Check in failed");
  echo "<h1>Check in complete</h1>\n";
  echo "<p><a href='",sdsLink("checkin.php"),
    "'>Return to check in list.</a></p>\n";
}

sdsIncludeFooter();
