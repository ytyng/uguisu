<?php
chdir("../");

require("config/config.php");
require($CONFIG['authScript']);
require($CONFIG['loggerClass']);
require($CONFIG['accountSettingFile']);
require($CONFIG['accountListClass']);

UguisuAccountList::initialize();

//if(isset($_GET['count']) && $_GET['count']){
//	//メール件数を更新
//	UguisuAccountList::updateCount();
//}
UguisuAccountList::updateCount();

if(!isset($_GET['scroll'])) $_GET['scroll'] = "";


$aId = 0;

//初回起動時のタイムアウト回避
//20090706
set_time_limit($CONFIG['mailDownloadTimeout']);

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
<title>Uguisu: Account list</title>
<link rel="stylesheet" type="text/css" href="../css/default.css" />
<style>
html{
	overflow-y:auto;
}

div.account-box{
	margin:0;
	padding:0.5em;
	font-size:80%;
	border:solid #bbb;
	border-width:0 0 1px 0;
}
h2{
	margin:0;
	padding:0;
	font-size:100%;
}

ul.category{
	margin:0 0 0 1em;
	padding:0;
	list-style-type:none;
	/* line-height:120%; */
}
ul.category li{
	margin:0;
	padding:0;
	color:#999;
}
ul.category li span.icon{
	display: inline-block;
	width:1.2em;
}
ul.category li span.line{
	color:#777;
	font-size:140%;
	/* vertical-align:middle; */
}

span.count{
	font-size:75%;
	color:#444;
}

strong.unread{
	color:#f00;
}

a.current{
	background:#ddf;
}

p.memo{
	margin:0;
	font-size:80%;
	color:#565;
	/*
	text-align:right;
	border:solid #ccc;
	border-width:1px 0 1px 0;
	*/
}
</style>
<script type="text/JavaScript">
var reloadReserved = false;
var t;
function reload(){
	if(reloadReserved){
		clearInterval(t);
	}
	t = setInterval(reloadTimer,10000);
	reloadReserved = true;
}

function reloadTimer(){
	y = parseInt(document.body.scrollTop || document.documentElement.scrollTop);
	document.location.href="account-list.php?scroll="+y;
}

function scroll(Y){
	if(Y) window.scrollTo(0,Y);
}

/**
 * カレント行を着色
 */
function markCurrent(i){
	var id = 0;
	while(o=document.getElementById("A_"+id)){
		if(i == id){
			o.className="current";
		}else{
			o.className="";
		}
		id++;
	}
}

/*
function bodyOnClick(){
	if(reloadReserved){
		clearInterval(t);
		t = setInterval(reloadTimer,3000);
	}
}
onClick="bodyOnClick();"
*/

</script>
</head>
<body onLoad="scroll(<?php echo htmlspecialchars($_GET['scroll']); ?>);" >
<div id="header">
<a href="../write/" target="_blank">メール作成</a> | 
<a href="javascript:location.reload();void(0);">更新</a> | 
<a href="mail-download-full.php">全受信</a> 
</div>

<?php

//config/account-list-appendix.php を読み込み。
//webメールをフレームで表示する時とか用
if(is_file($CONFIG['accountListAppendix'])){
	include($CONFIG['accountListAppendix']);
}

function printCount($account,$category){
	global $ACCOUNT;
	$mailCount = UguisuAccountList::getMailCount($account,$category);
	echo "<span class=\"count\">(";
	if(
		isset($ACCOUNT[$account]['strong-unread']) && 
		$ACCOUNT[$account]['strong-unread'] &&
		$mailCount['unread'] > 0
	){
		echo "<strong class=\"unread\">".$mailCount['unread']."</strong>";
	}else{
		echo $mailCount['unread'];
	}
	echo "/<small>".$mailCount['fullcount']."</small>)</span>\n";
}

foreach($ACCOUNT as $account => $record){
	echo "<div class=\"account-box\">\n";
	
	if(isset($record['memo'])){
		echo "<p class=\"memo\">".$record['memo']."</p>\n";
	}
	
	echo "<h2 title=\"".$account."\">";
	echo $account;
	echo "</h2>\n";
	
	if(isset($record['type']) && $record['type'] == 'imap'){
		//imap
		$mbox = imap_open($record['imap-mailbox'], $record['imap-id'],$record['imap-passwd']);
		$folders = imap_listmailbox($mbox, $record['imap-mailbox'], "*");
		echo "<ul class=\"category\">";
		foreach ($folders as $val) {
			echo "<li>";
			echo "<span class=\"icon\">";
			echo $CONFIG['default-category-icon'];
			echo "</span>";
			echo $val;
			echo mb_convert_encoding(imap_utf7_decode($val),'UTF-8','UTF-7');
			echo "</li>\n";
		}
		echo "</ul>\n";
		imap_close($mbox);
	}else{
		//POP3
		echo "<ul class=\"category\">";
		
		//ビルトインカテゴリを表示
		foreach($CONFIG['built-in-category'] as $category => $builtInCategory){
			echo "<li>";
			echo "<span class=\"icon\">";
			if(isset($builtInCategory['icon'])){
				echo $builtInCategory['icon'];
			}else{
				echo $CONFIG['default-category-icon'];
			}
			echo "</span>";
			echo "<a href=\"mail-download.php?account=".$account."&category=".$category."\" target=\"paneTop\" ";
			echo "id=\"A_".$aId."\" onClick=\"markCurrent(".$aId.");\" >";
			echo $builtInCategory['name'];
			echo "</a> ";
			printCount($account,$category);
			echo "</li>\n";
			$aId++;
		}
		
		//アカウントカテゴリを表示
		if(isset($ACCOUNT[$account]['category'])){
			foreach($ACCOUNT[$account]['category'] as $category => $record){
				echo "<li>";
				echo "<span class=\"icon\">";
				if(isset($record['icon'])){
					echo $record['icon'];
				}else{
					echo $CONFIG['default-category-icon'];
				}
				echo "</span>";
				echo "<a href=\"mail-download.php?account=".$account."&category=".$category."\" target=\"paneTop\" ";
				echo "id=\"A_".$aId."\" onClick=\"markCurrent(".$aId.");\" >";
				echo $record['name'];
				echo "</a> ";
				printCount($account,$category);
				echo "</li>\n";
				$aId++;
			}
		}
		
		//フィルタを表示
		if(isset($ACCOUNT[$account]['filter'])){
			foreach($ACCOUNT[$account]['filter'] as $filterName => $record){
				echo "<li>";
				echo "<span class=\"icon\">";
				if(isset($record['icon'])){
					echo $record['icon'];
				}else{
					echo $CONFIG['default-category-icon'];
				}
				echo "</span>";
				echo "<a href=\"mail-download.php?account=".$account."&filter=".rawurlencode($filterName)."\"  target=\"paneTop\" ";
				echo "id=\"A_".$aId."\" onClick=\"markCurrent(".$aId.");\" >";
				echo $filterName;
				echo "</a>";
				echo "</li>\n";
				$aId++;
			}
		}
		
		//アーカイブへのリンクを表示
		$archiveDbFile = $CONFIG['dataDirectory']."/".$account."/".$CONFIG['mailDbArchve'];
		if(is_file($archiveDbFile)){
			echo "<li>";
			echo "<span class=\"icon\">";
			echo $CONFIG['mail-archive-icon'];
			echo "</span>";
			echo "<a href=\"mail-archive-list.php?account=".$account."\"  target=\"paneTop\" ";
			echo "id=\"A_".$aId."\" onClick=\"markCurrent(".$aId.");\" >";
			echo $CONFIG['mail-archive-name'];
			echo "</a>";
			echo "</li>\n";
			$aId++;
		}
		echo "</ul>\n";
		
	}
	echo "</div>\n";
}
?>

<div id="footer">
<a href="debug-tool.php" target="paneTop">Debug tool</a>
</div>
<?php Logger::output($CONFIG['debugLevel']); ?>
</body>
</html>
