<?php
require_once('../sds.php');
sdsRequireGroup("LOUNGE-CHAIRS");
require_once('management.inc.php');

$id = trim(maybeStripslashes($_REQUEST['id']));
if($id === '') {
  display_error("Please provide a lounge ID.");
}
if(preg_match('/[^a-z0-9]/',$id)) {
  display_error("Please use only lowercase alphanumerics in a lounge ID.");
}
$id_esc = pg_escape_string($id);
$query = "SELECT 1 FROM lounges WHERE lounge = 'lounge-$id_esc'";
$result = sdsQuery($query);
if(!$result)
  contactTech("Could not search lounges");
if(pg_num_rows($result) != 0) {
  display_error("Lounge ID $id already in use (perhaps by a retired lounge).");
}
pg_free_result($result);

$description = trim(maybeStripslashes($_REQUEST['name']));
if($description === '') {
  display_error("Name of lounge must not be blank.");
}
$description_esc = pg_escape_string($description);

$contact = trim(maybeStripslashes($_REQUEST['contact']));
$contact_esc = pg_escape_string($contact);
if(!verify_contact($contact)) {
  display_error("First contact " . htmlspecialchars($contact).
		" is not a known user.");
}

$contact2 = trim(maybeStripslashes($_REQUEST['contact2']));
if($contact2 === '') {
  $contact2_esc = null;
} else {
  $contact2_esc = pg_escape_string($contact2);
  if(!verify_contact($contact2))
    display_error("Second contact " . htmlspecialchars($contact2) .
		  " is not a known user.");
}

$url_esc = trim(sdsSanitizeString($_REQUEST['url']));

$contact2_esc = isset($contact2_esc) ? ("'".$contact2_esc."'") : "null";
$url_esc = $url_esc === '' ? "DEFAULT" : ("'".$url_esc."'");

$result = sdsQuery("BEGIN");
if(!$result)
  contactTech("Could not start transaction");
pg_free_result($result);

$query = <<<ENDQUERY
INSERT INTO lounges
       (lounge,          description,       contact,       contact2,
        url,     active)
VALUES ('lounge-$id_esc','$description_esc','$contact_esc',$contact2_esc,
        $url_esc,TRUE)
ENDQUERY;

$result = sdsQuery($query);
if(!$result or pg_affected_rows($result) != 1) {
  if(!sdsQuery("ROLLBACK"))
    contactTech("Could not rollback",false);
  display_error("Lounge creation failed.",true);
}
pg_free_result($result);

if(!create_groups($id)) {
  if(!sdsQuery("ROLLBACK"))
    contactTech("Could not rollback");
  sdsIncludeFooter();
  exit;
}

$result = sdsQuery("COMMIT");
if(!$result) {
  contactTech("Could not commit",false);
  if(!sdsQuery("ROLLBACK"))
    contactTech("Could not rollback");
  sdsIncludeFooter();
  exit;
}
pg_free_result($result);

lounges_done('#current');
