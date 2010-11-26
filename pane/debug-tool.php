<?php
chdir("../");

require("config/config.php");
require($CONFIG['authScript']);
require($CONFIG['loggerClass']);
require($CONFIG['accountSettingFile']);
require($CONFIG['accountListClass']);

UguisuAccountList::initialize();

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
<title>Uguisu: Debug tool</title>
<style>
/*
html,body{
	margin:0;
	padding:0;
}

html{
	overflow-y:auto;
}
*/
a{
	text-decoration:none;
	color:#007;
}
a:hover{
	text-decoration:underline;
}

</style>
</head>
<body>
<h2>Debug tool</h2>
<p>デバッグツールです。通常、使用する必要はありません。</p>
<?php

foreach($ACCOUNT as $account => $record){

	echo "<h3>".$account."</h3>\n";
	echo " | <a href=\"account-info.php?account=".$account."\" target=\"paneBottom\">";
	echo "データベースの状態";
	echo "</a> ";
	echo " | <a href=\"utility.php?mode=popServerStats&account=".$account."\" target=\"paneBottom\">";
	echo "POP3サーバの状態";
	echo "</a> ";
	echo " | <a href=\"utility.php?mode=deleteMailData&account=".$account."\" target=\"paneBottom\">";
	echo "メールレコード全削除(危険!)";
	echo "</a> ";
	echo " | <a href=\"utility.php?mode=dataInitialize&account=".$account."\" target=\"paneBottom\">";
	echo "データディレクトリ削除(危険!)";
	echo "</a> ";
}
?>


<?php Logger::output($CONFIG['debugLevel']); ?>

</body>
</html>
