<?php
require_once("../sds.php");
sdsRequireGroup("LOUNGE-CHAIRS");
sdsIncludeHeader("Lounge Expense Archival","Archiving...");

?>
<h2>Archiving all entries...</h2>
<?php

$query = "UPDATE lounge_expenses SET termsold = termsold + 1";
$result = sdsQuery($query);
if($result) {
  pg_free_result($result);
} else {
  contactTech("Lounge Archival Failure",false);
}

?>
<p><b>DO NOT REFRESH THIS PAGE!</b></p>
<p>If any error messages are displayed above, e-mail
  <a href="mailto:simmons-tech@mit.edu">simmons-tech@mit.edu</a>.</p>
<p>But, if there aren't any error messages, you're done!</p>
<p>When you go to the front page of the Lounge Expense tracker, there
  shouldn't be any entries.  All entries have been "pushed back" one term.
  <!-- So what used to be on the front page can now be accessed by filtering
  for expenses 1 term ago.--></p>

<?php
require_once("gt-footer.php");
sdsIncludeFooter();
