#!/usr/bin/php
<?php

/*
24時間以内に受信したメールの要約を
特定のメールアドレスに送信するバッチスクリプト。

携帯電話のメールアドレスに向けて送信することを想定している。
cronで、1日周期で自動実行されることを想定している。

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

require($CONFIG['mailListClass']);
require($CONFIG['mailSendClass']);
require($CONFIG['smtpClass']);
UguisuMailSend::initialize();

$report = "";
foreach($ACCOUNT as $account => $record){
	
	//アカウント設定ファイルで許可されていない場合は実行しない
	if(!$record['report-summary']) continue;
	
	UguisuMailList::initialize($account);
	$mailList = UguisuMailList::getMailListToday();
	
	if(count($mailList)){
		$report .= "[".$account."]\n";
		foreach($mailList as $mail){
			$report .= "(".$mail['h_from_fancy'].") ";
			$report .= mb_strimwidth($mail['subject'],0,$SUMMARY_REPORT_CONFIG['maxLength'],"...");
			$report .= "\n";
		}
		$report .= "\n";
	}
}

if($report){
	$report = $SUMMARY_REPORT_CONFIG['header']."\n".$report;
	print($report);
	
	$MAIL = array(
		'account'    => $SUMMARY_REPORT_CONFIG['fromAccount'],
		'to'         => $SUMMARY_REPORT_CONFIG['toAddress'],
		'from'       => $SUMMARY_REPORT_CONFIG['fromAccount'],
		//'reply-to'   => $SUMMARY_REPORT_CONFIG['from'],
		//'references' => $result['h_message_id'],
		'subject'    => $SUMMARY_REPORT_CONFIG['subject'],
		'body'       => $report,
	);
		Logger::printDebugMessagePre();
	
	list($result,$aryErr) = UguisuMailSend::sendMail($MAIL);
	if(!count($aryErr) && $result){
		//送信完了
		print(UguisuMailSend::getSmtpLog());
	}else{
		print "[ERROR]";
		print_r($aryErr);
	}
	
	Logger::output($CONFIG['debugLevel']);

}

?>