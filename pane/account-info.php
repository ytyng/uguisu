<?php

/*
アカウント情報をデバッグする
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


//ファイルアクセスを行うため、自前でディレクトリ変数を持つ
$accountDirectory = $CONFIG['dataDirectory']."/".$account;

//		self::$dbh = new PDO(
//			self::$CONFIG['pdoDriver'].":".self::$accountDirectory."/".self::$CONFIG['mailDatabase'],


/**
 * ファイルサイズを読みやすく変換
 */
function sizeHumanReadable($bytes){
	$symbol = array('B ', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB');
	$exp = 0;
	$converted_value = 0;
	if( $bytes > 0 ){
		$exp = floor( log($bytes)/log(1024) );
		$converted_value = ( $bytes/pow(1024,floor($exp)) );
	}
	return sprintf( '%.1f'.$symbol[$exp], $converted_value );
}

/**
 * ディレクトリサイズを再帰的に取得
 * http://ameblo.jp/linking/entry-10048498375.html
 */
function getDirSize($path) {
	$total_size = 0;
	if (is_file($path)) {
		return filesize($path);
	}elseif(is_dir($path)){
		$basename = basename($path);
		if ($basename == '.' || $basename == '..') {
			return 0;
		}
		$file_list = scandir($path);
		foreach ($file_list as $file) {
			$total_size += getDirSize($path .'/'. $file);
		}
		return $total_size;
	} else {
		return 0;
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
<title>Uguisu: account-info</title>
<style>
body{
	font-size:70%;
}
table{
	font-size:100%;
}

</style>

</head>
<body>

<?php

//ファイルサイズ
echo "<table border><tbody>\n";
echo "<tr><th>".$CONFIG['mailDatabase']."</th>";
echo "<td>".sizeHumanReadable(filesize($accountDirectory."/".$CONFIG['mailDatabase']))."</td></tr>\n";
echo "<tr><th>".$CONFIG['sourceDirectory']."</th>";
echo "<td>".sizeHumanReadable(getDirSize($accountDirectory."/".$CONFIG['sourceDirectory']))."</td></tr>\n";
echo "<tr><th>".$CONFIG['attachDirectory']."</th>";
echo "<td>".sizeHumanReadable(getDirSize($accountDirectory."/".$CONFIG['attachDirectory']))."</td></tr>\n";
echo "</tbody></table>\n";

echo "<hr />\n";



$mailboxStatus = UguisuAccount::dumpMailboxStatus();
echo "<table border><tbody>\n";
echo "<tr><th>key_id</th><th>value</th></tr>\n";
foreach($mailboxStatus as $record){
	echo "<tr><td>".htmlspecialchars($record['key_id'])."</td>";
	echo "<td>".htmlspecialchars($record['value'])."</td></tr>\n";
}
echo "</tbody></table>\n";

echo "<hr />\n";

$mailCountTable = UguisuAccount::dumpMailCountTable();
echo "<table border><tbody>\n";
echo "<tr><th>category</th><th>unread</th><th>fullcount</th></tr>\n";
foreach($mailCountTable as $record){
	echo "<tr><td>".htmlspecialchars($record['category'])."</td>";
	echo "<td>".htmlspecialchars($record['unread'])."</td>";
	echo "<td>".htmlspecialchars($record['fullcount'])."</td></tr>\n";
}
echo "</tbody></table>\n";

echo "<hr />\n";

$mailDataTable = UguisuAccount::dumpMailDataTable();
echo "<table border><tbody>\n";
echo "<tr>";
echo "<th>seq_id</th>";
echo "<th>mail_id</th>";
echo "<th>subject</th>";
echo "<th>h_from</th>";
echo "<th>h_from_real</th>";
echo "<th>h_from_fancy</th>";
echo "<th>h_to</th>";
echo "<th>h_to_real</th>";
echo "<th>h_cc</th>";
echo "<th>h_message_id</th>";
echo "<th>h_references</th>";
echo "<th>bodytext</th>";
echo "<th>header</th>";
echo "<th>attach</th>";
echo "<th>attach_content_type</th>";
echo "<th>etime</th>";
echo "<th>category</th>";
echo "<th>readed</th>";
echo "</tr>\n";

foreach($mailDataTable as $record){
	echo "<tr>";
	echo "<td>".$record['seq_id']."</td>";
	echo "<td>".$record['mail_id']."</td>";
	echo "<td>".mb_strimwidth($record['subject'],0,20,"...")."</td>";
	echo "<td>".mb_strimwidth($record['h_from'],0,20,"...")."</td>";
	echo "<td>".mb_strimwidth($record['h_from_real'],0,20,"...")."</td>";
	echo "<td>".mb_strimwidth($record['h_from_fancy'],0,20,"...")."</td>";
	echo "<td>".mb_strimwidth($record['h_to'],0,20,"...")."</td>";
	echo "<td>".mb_strimwidth($record['h_to_real'],0,20,"...")."</td>";
	echo "<td>".mb_strimwidth($record['h_cc'],0,20,"...")."</td>";
	echo "<td>".$record['h_message_id']."</td>";
	echo "<td>".$record['h_references']."</td>";
	echo "<td>".mb_strimwidth($record['bodytext'],0,20,"...")."</td>";
	echo "<td>".mb_strimwidth($record['header'],0,20,"...")."</td>";
	echo "<td>".mb_strimwidth($record['attach'],0,20,"...")."</td>";
	echo "<td>".mb_strimwidth($record['attach_content_type'],0,20,"...")."</td>";
	echo "<td>".$record['etime']."</td>";
	echo "<td>".$record['category']."</td>";
	echo "<td>".$record['readed']."</td>";
}
echo "</tbody></table>\n";

echo "<hr />\n";


?>



<?php Logger::output($CONFIG['debugLevel']); ?>
</body>
</html>
