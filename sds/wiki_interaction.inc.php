<?php
if(realpath($_SERVER['SCRIPT_FILENAME']) === realpath(__FILE__))
  exit;
# sets up a mysql connection for use with wiki hacking

$wikiLocalSettings = '/var/www/mediawiki-1.13.2/LocalSettings.php';

$settings = @fopen($wikiLocalSettings,'r');
if(!$settings)
  contactTech("Could not find wiki settings");

$mysql_info = array();
while(!feof($settings)) {
  $line = fgets($settings);
  unset($captures);
  if(preg_match('/^\$wgDBserver\s+= ([\'"])(.*)\1;\s*$/',$line,$captures)) {
    $mysql_info['server'] = $captures[2];
    continue;
  }
  if(preg_match('/^\$wgDBname\s+= ([\'"])(.*)\1;\s*$/',$line,$captures)) {
    $mysql_info['database'] = $captures[2];
    continue;
  }
  if(preg_match('/^\$wgDBuser\s+= ([\'"])(.*)\1;\s*$/',$line,$captures)) {
    $mysql_info['user'] = $captures[2];
    continue;
  }
  if(preg_match('/^\$wgDBpassword\s+= ([\'"])(.*)\1;\s*$/',$line,$captures)) {
    $mysql_info['password'] = $captures[2];
    continue;
  }
}
fclose($settings);
if(count($mysql_info) != 4)
  contactTech("Wiki connection information not found");

global $mysql_db;
$mysql_db = mysql_connect($mysql_info['server'],
			   $mysql_info['user'],$mysql_info['password']);

if(!$mysql_db)
  contactTech("Could not conenct to MySQL");
if(!mysql_select_db($mysql_info['database'],$mysql_db))
  contactTech("Could not connect to wiki database");

unset($mysql_info);
