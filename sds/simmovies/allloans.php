<?php
require_once('../sds.php');
sdsRequireGroup("MOVIEADMINS");
require_once('../sds/ordering.inc.php');

sdsIncludeHeader("Current Loans");

$sortby = getSortby($_REQUEST['sortby'],4,6,'movie_loan_admin_sortby');

$orderbyarray =
array (# type asc,desc
       "typename ASC",
       "typename DESC",
       # box asc,desc
       "to_number(substring(box_number from '^[[:digit:]]+'),'9999999999') ASC, box_number ASC",
       "to_number(substring(box_number from '^[[:digit:]]+'),'9999999999') DESC, box_number DESC",
       # title asc,desc
       "title ASC",
       "title DESC",
       # loanee asc,desc
       "lastname ASC,firstname ASC,title ASC",
       "lastname DESC,firstname DESC,title ASC",
       # loan date asc,desc
       "date_trunc('minute',checkout) ASC,title ASC",
       "date_trunc('minute',checkout) DESC,title ASC",
       # due date asc,desc
       "date_trunc('minute',due_stamp) ASC,title ASC",
       "date_trunc('minute',due_stamp) DESC,title ASC"
       );

$date_format = 'FMDD Mon HH24:MI';

function showRecord($record) {
  global $rowparity;

  $rowparity = 1-$rowparity;
  $rowclass = $rowparity ? 'oddrow' : 'evenrow';
  if($record['overdue'] === 't') {
    $rowclass = 'overdue';
  }
  echo "  <tr class='",$rowclass,"'>\n";
  echo "    <td>",htmlspecialchars($record['typename']),"</td>\n";
  echo "    <td>",htmlspecialchars($record['box_number']),"</td>\n";
  echo "    <td class='titlecell'>",htmlspecialchars($record['title']),
    "</td>\n";
  echo "    <td>",
    htmlspecialchars($record['firstname']." ".$record['lastname']),"</td>\n";
  echo "    <td>",htmlspecialchars($record['checkout_str']),"</td>\n";
  echo "    <td>",htmlspecialchars($record['due_date']),"</td>\n";
  echo "  </tr>\n";
}

$query = <<<ENDQUERY
SELECT title,firstname,lastname,checkout_str,overdue,typename, box_number,
       CASE WHEN real_duration = interval '0' THEN 'Never' ELSE
            to_char(due_stamp,'$date_format') END AS due_date
FROM (SELECT info.title,firstname,lastname,
             checkout,to_char(checkout,'$date_format') AS checkout_str,
             real_duration,
             CASE WHEN real_duration = interval '0'
                       THEN now()+interval '5 years'
                  ELSE checkout+real_duration END AS due_stamp,
             CASE WHEN real_duration = interval '0' THEN false ELSE
               checkout+real_duration<now() END AS overdue,
             typename,box_number
      FROM (SELECT title,username,checkout,
                   COALESCE(item_loan_duration,loan_duration) AS real_duration,
                   typename,box_number
	    FROM movie_loans JOIN movie_instances USING (instanceid)
                             JOIN movie_items USING (movieid)
                             JOIN movie_types USING (typeid)
	    WHERE checkin IS NULL AND NOT hidden) AS info
      LEFT JOIN directory USING (username)
     ) AS info
WHERE overdue
ORDER BY $orderbyarray[$sortby],
         to_number(substring(box_number FROM '^[[:digit:]]+'),'9999999999'),
         box_number
ENDQUERY;
$result = sdsQuery($query);
if(!$result)
  contactTech("Could not search loans");

?>
<table class="movielist">
  <caption>Overdue Loans</caption>
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
  showRecord($record);
}
echo "</table>\n";
pg_free_result($result);

echo "<hr />\n";

$query = <<<ENDQUERY
SELECT title,firstname,lastname,checkout_str,overdue,typename, box_number,
       CASE WHEN real_duration = interval '0' THEN 'Never' ELSE
            to_char(due_stamp,'$date_format') END AS due_date
FROM (SELECT info.title,firstname,lastname,
             checkout,to_char(checkout,'$date_format') AS checkout_str,
             real_duration,
             CASE WHEN real_duration = interval '0'
                       THEN now()+interval '5 years'
                  ELSE checkout+real_duration end AS due_stamp,
             CASE WHEN real_duration = interval '0' THEN false ELSE
               checkout+real_duration<now() END AS overdue,
             typename,box_number
      FROM (SELECT title,username,checkout,
                   COALESCE(item_loan_duration,loan_duration) AS real_duration,
                   typename,box_number
	    FROM movie_loans JOIN movie_instances USING (instanceid)
                             JOIN movie_items USING (movieid)
                             JOIN movie_types USING (typeid)
	    WHERE checkin IS NULL AND NOT hidden) AS info
      LEFT JOIN directory USING (username)
     ) AS info
WHERE NOT overdue
ORDER BY $orderbyarray[$sortby],
         to_number(substring(box_number FROM '^[[:digit:]]+'),'9999999999'),
         box_number
ENDQUERY;
$result = sdsQuery($query);
if(!$result)
  contactTech("Could not search loans");

?>
<table class="movielist">
  <caption>Other Loans</caption>
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
  showRecord($record);
}
echo "</table>\n";
pg_free_result($result);

sdsIncludeFooter();
