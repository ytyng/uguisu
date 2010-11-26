<?php

/*
ユーティリティクラス

$_GET['mode]でモードを指定

deleteMailData = メールデータのDBレコードを全消去(テスト用)
dataInitialize = メールボックスを完全消去(テスト用)
moveMail       = メールを移動
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

//初回起動時のタイムアウト回避
set_time_limit($CONFIG['mailDownloadTimeout']);

@header("Content-Type: text/html; charset=<?php echo BASE_ENCODING; ?>");

$message="";

if(!isset($_GET['mode'])) $_GET['mode'] ="";
switch($_GET['mode']){
case "deleteMailData":
	Logger::debug(__FILE__,"deleteMailData");
	if(!$CONFIG['debugLevel'] >= 2) break; //デバッグモードでない場合は許可しない
	UguisuAccount::deleteMailData();
	$message = $account.":全メールデータを削除しました。";
	break;
	
case "dataInitialize":
	Logger::debug(__FILE__,"dataInitialize");
	if(!$CONFIG['debugLevel'] >= 2) break; //デバッグモードでない場合は許可しない
	$dataDir = $CONFIG['dataDirectory']."/".$account;
	if(is_dir($dataDir)){
		$command="rm -r ".$dataDir;
		Logger::debug(__FILE__,$command);
		$result = shell_exec($command);
		Logger::debug(__FILE__,$result);
	}
	$message = $account.":全保存データを削除しました。";
	break;
	
case "moveMail":
	Logger::debug(__FILE__,"moveMail");
	UguisuAccount::moveMail($_GET['mail_id'],$_GET['category']);
	$message = $account.":メッセージを移動しました。";
	break;
	
case "autoTrash":
	Logger::debug(__FILE__,"autoTrash");
	UguisuAccount::autoTrash($_GET['category']);
	$message = $account.":メッセージを移動しました。\n";
	$message .= "<script type=\"text/JavaScript\" >\n";
	$message .= "parent.paneTop.location.reload();\n";
	$message .= "parent.paneLeft.reload();\n";//件数が変わるのでリロード予約
	$message .= "</script>\n";
	break;
case "emptyTrash":
	Logger::debug(__FILE__,"emptyTrash");
	UguisuAccount::emptyTrash();
	$message = $account.":ごみ箱を空にしました。";
	$message .= "<script type=\"text/JavaScript\" >\n";
	$message .= "parent.paneTop.location.reload();\n";
	$message .= "parent.paneLeft.reload();\n";//件数が変わるのでリロード予約
	$message .= "</script>\n";
	break;
case "archiveTrash":
	Logger::debug(__FILE__,"archiveTrash");
	UguisuAccount::archiveTrash();
	$message = $account.":ごみ箱をアーカイブしました。";
	$message .= "<script type=\"text/JavaScript\" >\n";
	$message .= "parent.paneTop.location.reload();\n";
	$message .= "parent.paneLeft.reload();\n";//件数が変わるのでリロード予約
	$message .= "</script>\n";
	break;	
	
case "popServerStats":
	Logger::debug(__FILE__,"popServerStats");
	list($stats,$list,$uidl) = UguisuAccount::pop3stats();
	$message .= "<table border><tbody>";
	$message .= "<tr><th>メッセージ数</th><td>".$stats[0]."</td></tr>\n";
	$message .= "<tr><th>容量</th><td>".sprintf("%.2f",$stats[1]/1000)."KB</td></tr>\n";
	$message .= "</tbody></table>";
	
	$message .= "<table border><tbody>";
	foreach($list as $k => $r){
		$message .= "<tr><th>".$k."</th><td>".sprintf("%.2f",$r/1000)."KB</td><td>".$uidl[$k]."</td></tr>\n";
	}
	$message .= "</tbody></table>";

	//print_r($list);
	//print_r($uidl);
	break;
	
case "allReaded":
	Logger::debug(__FILE__,"allReaded");
	UguisuAccount::allReaded($_GET['category']);
	$message = $account.":カテゴリ内のメールをすべて既読にしました。\n";
	$message .= "<script type=\"text/JavaScript\" >\n";
	$message .= "parent.paneTop.location.reload();\n";
	$message .= "parent.paneLeft.reload();\n";//件数が変わるのでリロード予約
	$message .= "</script>\n";
	break;

default:
	$message = "モードの指定無いため、処理を行いませんでした。";
	break;
}

header("Content-Type: text/html; charset=".BASE_ENCODING);
header("Cache-Control: no-cache, must-revalidate");
header("Pragma: no-cache");
?><!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html lang="ja">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=<?php echo BASE_ENCODING; ?>" />
<meta http-equiv="Cache-Control" content="no-cache" />
<meta http-equiv="Pragma" content="no-cache" />
<meta http-equiv="Expires" content="0" />
<title>Uguisu: utility</title>
</head>
<body>
<?php echo $message; ?>
<?php Logger::output($CONFIG['debugLevel']); ?>
</body>
</html>
