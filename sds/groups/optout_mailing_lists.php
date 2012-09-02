<?php
require_once("../sds.php");
sdsRequireGroup("USERS");

if (!isset($_REQUEST["listname"])) {
  sdsErrorPage("No target list specified.");
  exit;
}

if (!isset($_REQUEST["value"])) {
  sdsErrorPage("Didn't specify opt-out vs. opt-in");
  exit;
}

$listname = maybeStripslashes($_REQUEST['listname']);
$listname_esc = sdsSanitizeString($listname);
$query = "SELECT mandatory FROM mailman_lists WHERE listname='$listname_esc' AND NOT deleted";
$result = sdsQuery($query);
if(!$result)
  contactTech("Could not search lists");
if (pg_num_rows($result) == 0) {
  sdsErrorPage("No such list.");
}

$data = pg_fetch_object($result);
if ($data->mandatory == 't') {
  sdsErrorPage("Can't opt-out of a mandatory list.");
}
pg_free_result($result);

$username_esc = pg_escape_string($session->username);
if ($_REQUEST["value"] === 'out') {
  $query = "SELECT 1 FROM mailman_optout WHERE listname='$listname_esc' AND username='$username_esc'";
  $result = sdsQuery($query);
  if(!$result)
    contactTech("Could not search optouts");
  if (pg_num_rows($result) == 0) {
    $query = "INSERT INTO mailman_optout (listname, username) VALUES ('$listname_esc', '$username_esc')";
    $ins_result = sdsQuery($query);
    if(!$ins_result or pg_affected_rows($ins_result) != 1) {
      sdsErrorPage("Opt Out Failed","Please inform Simmons Tech");
    }
    pg_free_result($ins_result);
  }
  pg_free_result($result);
} else {
  $query = "DELETE FROM mailman_optout WHERE listname='$listname_esc' AND username='$username_esc'";
  $result = sdsQuery($query);
  if(!$result or pg_affected_rows($result) != 1) {
    sdsErrorPage("Opt In Failed","Please inform Simmons Tech");
  }
  pg_free_result($result);
}

header("Location: " . SDS_BASE_URL . sdsLink("groups/view_mailing_lists.php"));
