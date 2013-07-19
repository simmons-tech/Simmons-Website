<?php
    $url="https://raw.github.com/simmons-tech/constitution/master/constitution.html";
    if($url!="")
        echo file_get_contents($url);
?>