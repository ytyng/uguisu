<?php

/*
メールをダウンロードするかを判断するクラス。
前回ダウンロード時刻より一定時間経過しているようであれば
ダウンロードを開始する。

また、GETクエリで force=1 が指定された場合は
前回ダウンロード時刻を無視してダウンロードする。

最初はインジケータを表示し、自ページを mode=execute をつけてリロードする。
mode=execute がついている場合は、メールのダウンロードを行い、
完了後mail-listにリダイレクトする。
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

//GETクエリを作成
$entityGetQuery="account=".htmlspecialchars($account);
if(isset($_GET['category'])) $entityGetQuery .= "&category=".htmlspecialchars($_GET['category']);
if(isset($_GET['filter']))   $entityGetQuery .= "&filter="  .htmlspecialchars($_GET['filter']);

if(!isset($_GET['mode'])) $_GET['mode'] = "";
if($_GET['mode'] == "execute"){
	header("Content-Type: text/html; charset=".BASE_ENCODING);
	header("Cache-Control: no-cache, must-revalidate");
	header("Pragma: no-cache");
	//実際にダウンロードを開始
	UguisuAccount::downloadMail();
	
	if($CONFIG['debugLevel'] >= 2){
		echo "<a href=\"mail-list.php?".$entityGetQuery."\">Next</a><br />\n";
		Logger::printDebugMessagePre();
		echo "<a href=\"mail-list.php?".$entityGetQuery."\">Next</a><br />\n";
		exit("ok");
	}
	header("Location: mail-list.php?".$entityGetQuery);
	exit();
	
}



if(isset($_GET['force']) && $_GET['force']){
	//force=1が指定されている場合は強制読込
}else{
	//メールの最終ダウンロード日時を見て、設定時間以上経過している場合は再ダウンロード。
	Logger::debug(__FILE__,"UguisuAccount::\$latestDownloadTime=".UguisuAccount::$latestDownloadTime);
	if( UguisuAccount::$latestDownloadTime + UguisuAccount::$accountRecord['reload-time'] > time()){
		//設定時間以上の経過が無いのでメールリストへリダイレクト
		header("Location: mail-list.php?".$entityGetQuery);
		exit();
	}
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
<title>Uguisu: Mail download</title>
<META http-equiv="Refresh" content="0;URL=./mail-download.php?mode=execute&<?php echo $entityGetQuery; ?>" />

</head>
<body>
メールをダウンロード中です。しばらくお待ちください。<br />
<img src="../image/wait.gif" /><br />
<br />
<a href="mail-list.php?<?php echo $entityGetQuery;  ?>">中止</a>
<?php Logger::output($CONFIG['debugLevel']); ?>
</body>
</html>
