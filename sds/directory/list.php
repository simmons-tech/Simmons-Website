<?php
require_once("../sds.php");
require_once("directory.inc.php");

sdsRequireGroup("EVERYONE");

$searchunhappy=1; # can later change to 0

$searchResult = doDirectorySearch();
if ($searchResult) {
  $searchunhappy=0; # unhappy

  if (pg_num_rows($searchResult) == 1) {
    $data = pg_fetch_object($searchResult);
    header("Location: ".SDS_BASE_URL.'directory/'.
	   sdsLink("entry.php", "username=".urlencode($data->username),true));
    exit;
  }
}

sdsIncludeHeader("Simmons Hall Directory");
if($searchunhappy==0) {
  showDirectorySearchResults($searchResult);
} else {
  print "Simmons Directory is very unhappy. No results found.\n";
}
print "<br /><hr /><br />";
showDirectorySearchForm();
sdsIncludeFooter(); 
