#!/usr/bin/php
<?php

chdir(dirname(__FILE__));
chdir("../");

//コマンドラインのみ許可。(正規の方法かは不明)
if(isset($_SERVER['REMOTE_ADDR'])) exit("[ERROR] Run in command line.");

require("config/config.php");

require_once($XMPP_NOTIFY_CONFIG['xmppClass']);
$conn = new XMPPHP_XMPP(
	$XMPP_NOTIFY_CONFIG['server'],
	$XMPP_NOTIFY_CONFIG['port'],
	$XMPP_NOTIFY_CONFIG['username'],
	$XMPP_NOTIFY_CONFIG['password'],
	$XMPP_NOTIFY_CONFIG['resource'],
	$XMPP_NOTIFY_CONFIG['domain'],
	$printlog=false,
	$loglevel=XMPPHP_Log::LEVEL_INFO
);

try {
	$conn->connect();
	$conn->processUntil('session_start');
	$conn->presence();
	$conn->message($XMPP_NOTIFY_CONFIG['notify-to'], "XMPP 通知テスト");
	$conn->disconnect();
} catch(XMPPHP_Exception $e) {
	print("[ERROR] XMPP : ".$e->getMessage());
}


?>