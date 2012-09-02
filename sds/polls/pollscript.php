<?php
require_once('../sds.php');
sdsRequireGroup('USERS');

header("Content-type: text/javascript");

$query = "SELECT groupname,description FROM sds_groups_public ORDER BY groupname";

$result = sdsQuery($query);
if(!$result) {
  echo "alert('ERROR: Could not search groups. Please contact simmons-tech@mit.edu')";
  exit;
}

echo "var groups = {\n";

while($record = pg_fetch_array($result)) {
  echo '  "',htmlspecialchars($record['groupname']),'":"',
    htmlspecialchars($record['description']),"\",\n";
}
pg_free_result($result);
echo '}';
?>

function updateDescription(selecter) {
  document.getElementById("groupDescriber").innerHTML = groups[selecter.value];
}

function selectOnat(name) {
  var elements = document.getElementsByName(name);
  for(var i=0;i<elements.length;i++) {
    elements[i].checked = (elements[i].value == 'onat');
  }
}
