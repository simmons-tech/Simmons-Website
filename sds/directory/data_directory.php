var everyone = [<?php
require_once("../sds.php");
require_once("directory.inc.php");

header('Content-Type: application/json');

$query = "SELECT username,lastname,firstname,title,room,year,type FROM public_active_directory";

$result = sdsQuery($query);
  if(!$result) {
    echo 'Cannot search :(';
    return null;
  }

//echo pg_num_rows($result)  . ' results :(';

function convert_type($type) {
    if ($type == 'U')
        return 'Undergraduate';
    else if ($type == 'GRT')
        return 'GRT';
    else if ($type == 'AHM')
        return 'Associate House Master';
    else if ($type == 'HM')
        return 'Housemaster';
    else if ($type == 'MGR')
        return 'House Manager';
    else if ($type == 'VS')
        return 'Visiting Scholar';
    else if ($type == 'OTHER')
        return 'Other';
    else
        return '';
}

if ($data = pg_fetch_row($result)) {
    if ($data[3] == null)
        $data[3] = '';
    if ($data[5] == null)
        $data[5] = '';
    $data[6] = convert_type($data[6]);
    if ($data[4])
        echo json_encode(array($data[1], $data[2], '', $data[0], $data[4], $data[5], '', $data[6], $data[0] . '@mit.edu', ''));
}

while($data = pg_fetch_row($result)) {
    if ($data[3] == null)
        $data[3] = '';
    if ($data[5] == null)
        $data[5] = '';
    $data[6] = convert_type($data[6]);
    if ($data[4])
    echo ',' . json_encode(array($data[1], $data[2], '', $data[0], $data[4], $data[5], '', $data[6], $data[0] . '@mit.edu', $data[3]));
}
?>];
