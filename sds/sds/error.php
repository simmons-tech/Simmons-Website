<?php

## tell system to NOT authenticate
$sdsNoAuthentication = true;

require_once(SDS_BASE . "/sds.php");
# sdsIncludeHeader("Simmons DB Error");

echo <<<EOF
<h2>$sdsErrorTitle</h2>
<p class="error">$sdsErrorText</p>
EOF;

sdsIncludeFooter();

?>
