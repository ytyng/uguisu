<?php

/*
メールソースをダウンロード
*/

chdir("../");

require("config/config.php");
require($CONFIG['authScript']);
require($CONFIG['loggerClass']);
require($CONFIG['accountSettingFile']);
require($CONFIG['accountListClass']);

require($CONFIG['accountClass']);

if(!isset($_GET['account'])) exit("[ERROR] No set account.");
if(!isset($ACCOUNT[$_GET['account']])) exit("[ERROR] No exist account seting.");

$account = $_GET['account'];
UguisuAccount::initialize($account);

if(!isset($_GET['mail_id'])) exit("[ERROR] No set mail_id.");
$mail_id = rawurldecode($_GET['mail_id']);

$filePath    = $CONFIG['dataDirectory']."/".$account."/".$CONFIG['sourceDirectory']."/".UguisuAccount::sanitizeMailId($mail_id);

if(!is_file($filePath)) exit("[ERROR] File not exist. ".$filePath);

header("Content-Type: text/plain;charset=".$ACCOUNT[$_GET['account']]['default-encoding']);

echo file_get_contents($filePath);

?>