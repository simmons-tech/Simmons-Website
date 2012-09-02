<?php
require_once('../sds.php');
sdsRequireGroup("USERS");
require_once('../sds/ordering.inc.php');

sdsIncludeHeader("My Movie Loans");

$sortby = getSortby($_REQUEST['sortby'],5,4,'movie_loan_sortby');

$orderbyarray =
array (# type asc,desc
       "typename ASC,title ASC",
       "typename DESC,title ASC",
       # title asc,desc
       "title ASC,date_trunc('minute',checkout) ASC",
       "title DESC,date_trunc('minute',checkout) ASC",
       # loan date asc,desc
       "date_trunc('minute',checkout) ASC,title ASC",
       "date_trunc('minute',checkout) DESC,title ASC",
       # due/return date asc,desc
       "date_trunc('minute',CASE WHEN real_duration = interval '0' THEN now()+interval '5 years' ELSE checkout+real_duration END) ASC,title ASC",
       "date_trunc('minute',CASE WHEN real_duration = interval '0' THEN now()+interval '5 years' ELSE checkout+real_duration END) DESC,title ASC",
       );

$orderbyarray_past = $orderbyarray;
$orderbyarray_past[6] = "date_trunc('minute',checkin) ASC,title ASC";
$orderbyarray_past[7] = "date_trunc('minute',checkin) DESC,title ASC";

$date_format = 'FMDD Mon HH24:MI';

$currentuser = pg_escape_string($session->username);
$query = <<<ENDQUERY
SELECT title,to_char(checkout,'$date_format') AS checkout_str,
       CASE WHEN real_duration = interval '0' THEN 'Never' ELSE
            to_char(checkout+real_duration,'$date_format') END AS due_date,
       CASE WHEN real_duration = interval '0' THEN false ELSE
            checkout+real_duration<now() END AS overdue,typename,link
FROM (SELECT title,checkout,
             COALESCE(item_loan_duration,loan_duration) AS real_duration,
             typename,link
      FROM movie_loans JOIN movie_instances USING (instanceid)
                       JOIN movie_items USING (movieid)
                       JOIN movie_types USING (typeid)
      WHERE checkin IS NULL AND username='$currentuser' AND NOT hidden) AS info
ORDER BY $orderbyarray[$sortby]
ENDQUERY;
$result = sdsQuery($query);
if(!$result)
  contactTech("Could not search loans");
?>
<h1>Current loans</h1>
<table class="movielist">
  <colgroup span="1" width="0*" />
  <colgroup />
  <colgroup span="3" width="0*" />
  <tr>
<?php
makeSortTH("Type",0,$sortby);
makeSortTH("Title",1,$sortby);
makeSortTH("Loan Date",2,$sortby,'',1);
makeSortTH("Due Date",3,$sortby,'',1);
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
  echo "    <td class='titlecell'>";
  if($record['link']) {
    echo '<a href="',htmlspecialchars($record['link']),'" target="_blank">',
      htmlspecialchars($record['title']),"</a></td>\n";
    } else {
      echo htmlspecialchars($record['title']),"</td>\n";
    }
  echo "    <td>",htmlspecialchars($record['checkout_str']),"</td>\n";
  echo "    <td>",htmlspecialchars($record['due_date']),"</td>\n";
  echo "  </tr>\n";
}
echo "</table>\n";
pg_free_result($result);

$query = <<<ENDQUERY
SELECT title,to_char(checkout,'$date_format') AS checkout_str,
       to_char(checkin,'$date_format') AS checkin_str,typename,link
FROM movie_loans JOIN movie_instances USING (instanceid)
                 JOIN movie_items USING (movieid)
                 JOIN movie_types USING (typeid)
WHERE (checkin IS NOT NULL OR hidden) AND username='$currentuser'
ORDER BY $orderbyarray_past[$sortby]
ENDQUERY;
$result = sdsQuery($query);
if(!$result)
  contactTech("Could not search loans");
?>
<h1>Past loans</h1>
<table class="movielist">
  <colgroup span="1" width="0*" />
  <colgroup />
  <colgroup span="3" width="0*" />
  <tr>
<?php
makeSortTH("Type",0,$sortby);
makeSortTH("Title",1,$sortby);
makeSortTH("Loan Date",2,$sortby,'',1);
makeSortTH("Return Date",3,$sortby,'',1);
echo "  </tr>\n";

$rowparity = 1;
while($record = pg_fetch_array($result)) {
  $rowparity = 1-$rowparity;
  $rowclass = $rowparity ? 'oddrow' : 'evenrow';
  echo "  <tr class='",$rowclass,"'>\n";
  echo "    <td>",htmlspecialchars($record['typename']),"</td>\n";
  echo "    <td class='titlecell'>";
  if($record['link']) {
    echo '<a href="',htmlspecialchars($record['link']),'" target="_blank">',
      htmlspecialchars($record['title']),"</a></td>\n";
    } else {
      echo htmlspecialchars($record['title']),"</td>\n";
    }
  echo "    <td>",htmlspecialchars($record['checkout_str']),"</td>\n";
  echo "    <td>",htmlspecialchars($record['checkin_str']),"</td>\n";
  echo "  </tr>\n";
}
echo "</table>\n";
pg_free_result($result);

sdsIncludeFooter();
