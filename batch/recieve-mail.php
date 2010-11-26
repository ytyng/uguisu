#!/usr/bin/php
<?php

/*
メールを受信するバッチスクリプト

cronに登録して実行されることを想定している。

受信したメールの要約を、XMPPで通知する機能あり。
設定は config/config.php の下部、$XMPP_NOTIFY_CONFIG を見ること。
*/



chdir(dirname(__FILE__));
chdir("../");

//コマンドラインのみ許可。(正規の方法かは不明)
if(isset($_SERVER['REMOTE_ADDR'])) exit("[ERROR] Run in command line.");

require("config/config.php");
//require($CONFIG['authScript']);
require($CONFIG['loggerClass']);
require($CONFIG['accountSettingFile']);
require($CONFIG['accountListClass']);

UguisuAccountList::initialize();

require($CONFIG['accountClass']);

$notifyMessage = "";
foreach($ACCOUNT as $account => $record){
	if(isset($record['type']) && $record['type'] == 'imap'){
		echo "Imap. next. ".$account."\n";
		continue;
	}
	
	
	echo "Downloading ".$account."\n";
	UguisuAccount::initialize($account);
	UguisuAccount::downloadMail();
	
	$m = UguisuAccount::getNotifyMessage();
	echo $m;
	
	if(isset($record['notify']) && $record['notify'] && $m){
		$notifyMessage .= "[".$account."]\n";
		$notifyMessage .= $m;
	}
}



if(isset($XMPP_NOTIFY_CONFIG['enable']) && $XMPP_NOTIFY_CONFIG['enable'] && $notifyMessage){
	//XMPP通知を行う
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
		$conn->message($XMPP_NOTIFY_CONFIG['notify-to'],$XMPP_NOTIFY_CONFIG['header'].$notifyMessage.$XMPP_NOTIFY_CONFIG['footer']);
		$conn->disconnect();
	} catch(XMPPHP_Exception $e) {
		print("[ERROR] XMPP : ".$e->getMessage());
	}
}
echo "\nComplete. \n";
if(!$CONFIG['debugMode']){
	Logger::printDebugMessagePre();
}

?>