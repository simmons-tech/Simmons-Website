<?php
require "../../vendor/OpenIDConnectClient.php";
require "../../sds/config.php";
require "../../sds.php";

$deskip = sdsGetStrOption('desk-ip');
if($_SERVER["REMOTE_ADDR"] === $deskip) {
  header("Location: " . SDS_AUTO_LOGIN_URL);
  exit;
}
try {
  $oidc = new OpenIDConnectClient(getenv(OPENID_URL),
                                  getenv(OPENID_CLIENT_ID),
                                  getenv(OPENID_CLIENT_SECRET));
  $oidc->addScope('openid');
  $oidc->addScope('profile');
  $oidc->addAuthParam('url=' . $_REQUEST['url']);
  if (isset($_REQUEST['url']))
    $_SESSION['url'] = $_REQUEST['url'];
  $oidc->authenticate();
  $_REQUEST['url'] = $_SESSION['url'];
  unset($_SESSION['url']);
  $username = $oidc->requestUserInfo('preferred_username');
} catch (OpenIDConnectClientException $e) {
  header("Location: " . SDS_AUTO_LOGIN_URL . '&error=' . $e->getMessage());
  exit;
}
if(!isset($session) or $username !== $session->username) {
  $session = createSession($username);
}
header("Location: " . $_REQUEST['url']);
exit;
?>
