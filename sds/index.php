<?php
## redirect to auto-login page

require_once ("sds/config.php");

header("Location: " . SDS_AUTO_LOGIN_URL);
?>
