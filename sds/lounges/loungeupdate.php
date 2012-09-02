<?php
require_once('../sds.php');
sdsRequireGroup("LOUNGE-CHAIRS");
require_once('management.inc.php');

if(is_array($_REQUEST['name'])) {
  $lounges = array_keys($_REQUEST['name']);
  $lounge_updates = array();

  foreach($lounges as $lounge) {
# check that values are valid, but only if they have changed.
# Bad values currently in the database should probably be dealt with,
# but that would likely confuse the user and they should be rare
    $lounge_esc = pg_escape_string($lounge);
    $lounge_disp = htmlspecialchars($lounge);

    $query = "SELECT description,contact,contact2,url,allocation FROM active_lounges WHERE lounge='lounge-$lounge_esc'";
    $result = sdsQuery($query);
    if(!$result)
      contactTech("Could not query lounges");

    if(pg_num_rows($result) != 1)
      display_error("Bad lounge ID: $lounge_disp",true);

    $record = pg_fetch_array($result);
    pg_free_result($result);

    $description = trim(maybeStripslashes($_REQUEST['name'][$lounge]));
    if($description !== $record['description']) {
      if($description === '')
	display_error("Name of lounge $lounge_disp must not be blank.");
      $lounge_updates[$lounge]['description'] =
	pg_escape_string($description);
    }

    $contact = trim(maybeStripslashes($_REQUEST['contact'][$lounge]));
    if($contact !== $record['contact']) {
      if(!verify_contact($contact))
	display_error("First contact ".htmlspecialchars($contact).
		      " of lounge $lounge_disp is not a known user.");
      $lounge_updates[$lounge]['contact'] = pg_escape_string($contact);
    }

    $contact2 = trim(maybeStripslashes($_REQUEST['contact2'][$lounge]));
    if($contact2 === '') {
      $lounge_updates[$lounge]['contact2'] = null;
    } elseif($contact2 !== $record['contact2']) {
      if(!verify_contact($contact2))
	display_error("Second contact ".htmlspecialchars($contact2).
		      " of lounge $lounge_disp is not a known user.");
      $lounge_updates[$lounge]['contact2'] = pg_escape_string($contact2);
    }

    $allocation = trim(maybeStripslashes($_REQUEST['allocation'][$lounge]));
    if($allocation === '') {
      $lounge_updates[$lounge]['allocation'] = null;
    } elseif($allocation !== $record['allocation']) {
      if(!preg_match('/^\d*(?:\.(?:\d\d)?)?$/',$allocation))
	display_error("allocation ".htmlspecialchars($allocation).
		      " of lounge $lounge_disp does not look like a dollar amount.");
      $lounge_updates[$lounge]['allocation'] = pg_escape_string($allocation);
    }

    $url = trim(maybeStripslashes($_REQUEST['url'][$lounge]));
    if($url !== $record['url']) {
      $lounge_updates[$lounge]['url'] = pg_escape_string($url);
    }

    if(!empty($_REQUEST['disable'][$lounge]))
      $lounge_updates[$lounge]['disable'] = 1;
  }

# Data sould all be valid now

  foreach($lounge_updates as $lounge => $updates) {
    $lounge_esc = pg_escape_string($lounge);
    if(!empty($updates['disable'])) {
      $changes = "active = FALSE";
    } else {
      $changes = sqlArrayUpdate($updates);
    }
    $query = "UPDATE lounges SET $changes WHERE lounge = 'lounge-$lounge_esc'";
    $result = sdsQuery($query);
    if(!$result or pg_affected_rows($result) != 1)
      display_error("Update of lounge $lounge_esc failed.",true);
    pg_free_result($result);
    if(!empty($updates['disable']))
      remove_groups($lounge);
  }
}

lounges_done('#current');
