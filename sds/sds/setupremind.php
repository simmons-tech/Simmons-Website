<?php
if(realpath($_SERVER['SCRIPT_FILENAME']) === realpath(__FILE__))
  exit;
if(!isset($session->data['reminders']))
  $session->data['reminders'] = array();
if($session->username !== "GUEST") {

  $username_esc = pg_escape_string($session->username);
# sets up common reminders

#  1.  join a lounge reminder
  $query = "SELECT lounge FROM directory WHERE username='$username_esc'";
  $result = sdsQuery($query);
  if(!$result)
    contactTech("Could not search directory");
  list($lounge) = pg_fetch_array($result);
  $lounge_esc = pg_escape_string($lounge);
  pg_free_result($result);
  if(!isset($lounge) and sdsGetIntOption('enable-lounge-signups')) {
    sdsSetReminder("noLounge",
		   "You can still join a lounge!  Check out the <a href='" .
		   sdsLink(SDS_BASE_URL . '/users/loungesignup.php') .
		   "'>lounge membership page</a> for details");
  } else { sdsClearReminder("noLounge"); }

#  2.  vote on a lounge proposal reminder

  if(isset($lounge)) {
    $query = <<<ENDQUERY
SELECT count(*) FROM lounge_expenses
WHERE loungeid='$lounge_esc' AND termsold=0 AND NOT canceled AND NOT valid AND
      NOT EXISTS
        (SELECT 1 FROM lounge_expense_actions
         WHERE lounge_expense_actions.expenseid=lounge_expenses.expenseid AND
               username='$username_esc')
ENDQUERY;
    $result = sdsQuery($query);
    if(!$result or pg_num_rows($result) != 1)
      contactTech("Could not search lounge expenses");
    list($loungeCount) = pg_fetch_array($result);
    pg_free_result($result);
    if($loungeCount > 0) {
      sdsSetReminder('loungeCount',
		     "You need to respond to <a href='" . 
		     sdsLink(SDS_BASE_URL . '/loungeexpense/proposals.php') .
		     "'><b>$loungeCount lounge proposal" .
		     ($loungeCount==1?'':'s') . "</b></a>.");
    } else { sdsClearReminder('loungeCount'); }
  }
  
  if(isset($lounge)) {
    $query = <<<ENDQUERY
SELECT count(*) FROM lounge_expenses
WHERE loungeid='$lounge_esc' AND termsold=0 AND NOT canceled AND valid AND
      NOT EXISTS
        (SELECT 1 FROM lounge_expense_actions
         WHERE lounge_expense_actions.expenseid=lounge_expenses.expenseid AND
               username='$username_esc')
ENDQUERY;
    $result = sdsQuery($query);
    if(!$result or pg_num_rows($result) != 1)
      contactTech("Could not search lounge expenses");
    list($loungeCount) = pg_fetch_array($result);
    pg_free_result($result);
    if($loungeCount > 0) {
      sdsSetReminder('loungeCountExpenses',
		     "You need to respond to <a href='" . 
		     sdsLink(SDS_BASE_URL . '/loungeexpense/index.php') .
		     "'><b>$loungeCount lounge expense" .
		     ($loungeCount==1?'':'s') . "</b></a>.");
    } else { sdsClearReminder('loungeCountExpenses'); }
  }
} else {
# clear all reminders, and remind the guest to login
  sdsClearReminders();
  sdsSetReminder('guestlogin',"Please log in.");
}
