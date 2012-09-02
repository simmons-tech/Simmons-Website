<?php
require_once("../../sds.php");

$options = "auto=1";
if ($_REQUEST["url"]) {
  $options .= "&url=" . rawurlencode(maybeStripslashes($_REQUEST["url"]));
}

header("Location: " . sdsLink(SDS_BASE_URL . "login/certs/login.php",
			      $options,true));
