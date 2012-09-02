<?php
require_once('../sds.php');
sdsRequireGroup("LOUNGE-CHAIRS");
require_once('management.inc.php');

if(isset($_REQUEST['signup'])) {
  $signup = $_REQUEST['signup']==='Enabled'?1:0;
  $query =
    "UPDATE options SET value=$signup WHERE name='enable-lounge-signups'";
  $result = sdsQuery($query);
  if(!$result or pg_affected_rows($result) != 1)
    display_error("Lounge signup state change failed.",true);
  pg_free_result($result);
}
if(isset($_REQUEST['value'])) {
  $value = (int) $_REQUEST['value'];
  $query =
    "UPDATE options SET value=$value WHERE name='lounge-signup-value'";
  $result = sdsQuery($query);
  if(!$result or pg_affected_rows($result) != 1)
    display_error("Lounge signup value change failed.",true);
  pg_free_result($result);
}

# All changes succeeded, redirect to the options page

lounges_done();
