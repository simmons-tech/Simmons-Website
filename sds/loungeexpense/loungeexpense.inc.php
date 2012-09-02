<?php
if(realpath($_SERVER['SCRIPT_FILENAME']) === realpath(__FILE__))
  exit;

# $isvalid = validate_expense(expenseid)
# sets the valid flag on the given event if it is finished and complys with
# the lounge bylaws
function validate_expense($expenseid) {
  $expenseid = (int) $expenseid; # better safe than sorry

  $query = "SELECT loungeid,finished,valid,termsold,canceled,amountspent FROM lounge_expenses WHERE expenseid=$expenseid";
  $result = sdsQuery($query);
  if(!$result) {
    contactTech("Could not search events",false);
    return null;
  }
  if(pg_num_rows($result) == 0) {
    # no such event
    pg_free_result($result);
    return false;
  }

  $data = pg_fetch_array($result);
  pg_free_result($result);

  if($data['valid'] === 't') {
    # already validated
    return true;
  }

  if($data['finished'] === 'f') {
    # cannot be valid until done
    return false;
  }

  if($data['termsold'] > 0) {
    # do not validate old entries
    return false;
  }

  if($data['canceled'] === 't') {
    # canceled events are valid
    $query =
      "UPDATE lounge_expenses SET valid=true WHERE expenseid=$expenseid";
    $result = sdsQuery($query);
    if(!$result or pg_affected_rows($result) != 1) {
      # weird error
      contactTech("Could not validate event",false);
      return null;
    }
    pg_free_result($result);
    return true;
  }

  if($data['amountspent'] === NULL) {
    # er...
    return false;
  }

  # figure out info about the lounge
  $loungeid = $data['loungeid'];
  $loungeid_esc = pg_escape_string($loungeid);
  $amountspent = $data['amountspent'];

  $query = "SELECT allocation FROM active_lounges WHERE lounge='$loungeid_esc'";
  $result = sdsQuery($query);
  if(!$result or pg_num_rows($result) != 1) {
    contactTech("Could not find lounge",false);
    return null;
  }
  list($allocation) = pg_fetch_array($result);
  pg_free_result($result);

  $query = "SELECT count(*) FROM active_directory WHERE lounge='$loungeid_esc'";
  $result = sdsQuery($query);
  if(!$result or pg_num_rows($result) != 1) {
    contactTech("Could not find membership",false);
    return null;
  }
  list($membership) = pg_fetch_array($result);
  pg_free_result($result);

  # find out info about the event
  $query = "SELECT count(*) FROM lounge_expense_actions WHERE expenseid=$expenseid AND action=0";
  $result = sdsQuery($query);
  if(!$result or pg_num_rows($result) != 1) {
    # weird error
    contactTech("Could not find commits");
    return null;
  }
  list($commits) = pg_fetch_array($result);
  pg_free_result($result);

  $query = "SELECT count(*) FROM lounge_expense_actions WHERE expenseid=$expenseid AND (action=0 OR action=1)";
  $result = sdsQuery($query);
  if(!$result or pg_num_rows($result) != 1) {
    # weird error
    contactTech("Could not find approvals",false);
    return null;
  }
  list($approvals) = pg_fetch_array($result);
  pg_free_result($result);

  # approval requirements
  if($approvals < 5) {
    return false;
  }

  if($approvals*3 < $membership) {
    return false;
  }

  # spending limits
  if($commits*$allocation/$membership < $amountspent) {
    return false;
  }

  # check for fund exhaustion
  $query = "SELECT allocation - totalspent FROM lounge_summary_report WHERE loungeid = '$loungeid_esc'";
  $result = sdsQuery($query);
  if(!$result or pg_num_rows($result) != 1) {
    contactTech("Could not read lounge summary",false);
    return null;
  }
  list($fundsleft) = pg_fetch_array($result);
  pg_free_result($result);
  if($fundsleft < $amountspent)
    return false;

  # otherwise, things look ok
  $query = "UPDATE lounge_expenses SET valid=true WHERE expenseid=$expenseid";
  $result = sdsQuery($query);
  if(!$result or pg_affected_rows($result) != 1) {
    # weird error
    contactTech("Could not validate event",false);
    return null;
  }
  pg_free_result($result);
  return true;
}
