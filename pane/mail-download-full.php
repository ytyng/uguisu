<?php

/*
全アカウントを巡回して、メールをダウンロードする

*/


chdir("../");

if(!isset($_GET['mode'])) $_GET['mode'] = "";
if($_GET['mode'] == "execute"){

	require("config/config.php");
	require($CONFIG['authScript']);
	require($CONFIG['loggerClass']);
	require($CONFIG['accountSettingFile']);
	require($CONFIG['accountListClass']);
	
	require($CONFIG['accountClass']);
	
	foreach($ACCOUNT as $account => $record){
		UguisuAccount::initialize($account);
		UguisuAccount::downloadMail();
	}
	
	if($CONFIG['debugLevel'] >= 2){
		echo "<a href=\"account-list.php\">Next</a><br />\n";
		Logger::printDebugMessagePre();
		echo "<a href=\"account-list.php\">Next</a><br />\n";
		exit("ok");
	}else{
		header("Location: account-list.php");
	}
	exit();
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
<title>Uguisu: Mail download full</title>
<meta http-equiv="Refresh" content="0;URL=./mail-download-full.php?mode=execute" />

</head>
<body>
<div style="text-align:center;margin-top:3em;">
ダウンロード中...<br />
<img src="../image/wait-s.gif" /><br />
<br />
<a href="account-list.php">中止</a>
</div>
</body>
</html>
