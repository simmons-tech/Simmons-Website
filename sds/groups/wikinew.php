<?php
require_once('../sds.php');
sdsRequireGroup('ADMINISTRATORS');

$prefix = getStringArg('prefix');
$errors = array();
if($prefix !== '') {
  if(!preg_match('/:$/',$prefix))
    $errors[] = "Prefix should end with a colon";

  $prefix_esc = pg_escape_string($prefix);
  $query = <<<ENDQUERY
SELECT 1 FROM wiki_permissions_sds
WHERE lower(wiki_prefix) = lower('$prefix_esc')
ENDQUERY;
  $result = sdsQuery($query);
  if(!$result)
    contactTech("Could not search prefixes");

  if(pg_num_rows($result) != 0)
    $errors[] = "Already a controlled area";
  pg_free_result($result);

  if(count($errors) == 0) {
    $query =
      "INSERT INTO wiki_permissions_sds (wiki_prefix) VALUES ('$prefix_esc')";
    $result = sdsQuery($query);
    if(!$result or pg_affected_rows($result) != 1)
      contactTech("Could not create entry");
    pg_free_result($result);

    header("Location: " . SDS_BASE_URL .
	   sdsLink('groups/wikiedit.php',"prefix=".urlencode($prefix),true));
    exit;
  }
}

sdsIncludeHeader("Create Controlled Wiki Area");

foreach($errors as $complaint)
  echo "<p class='error'>",$complaint,"</p>\n";

echo "<form action='",$_SERVER['PHP_SELF'],"' method='post'>\n";
echo sdsForm();
echo "Prefix: <input type='text' name='prefix' size='30' value='",
  htmlspecialchars($prefix,ENT_QUOTES),"' />\n";
echo "<input type='submit' value='Create' />\n";
echo "</form>\n";

sdsIncludeFooter();
