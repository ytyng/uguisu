<?php

/*
Uguisu メールリスト スレッド表示テスト

@GET:
account  対象アカウント
page     表示ページ
category カテゴリ
filter   フィルタ
q        検索文字列

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
if(!isset($_GET['category'])) $_GET['category'] = 0;
$category = (int)$_GET['category'];

//GETクエリを作成
$entityGetQuery="account=".htmlspecialchars($account);
if(isset($_GET['category'])) $entityGetQuery .= "&category=".htmlspecialchars($_GET['category']);


UguisuAccount::initialize($account);

require($CONFIG['mailListClass']);
UguisuMailList::initialize($account);



$mailListThread=UguisuMailList::getMailThreadList($category);

if($CONFIG['threadReverse']) $mailListThread=array_reverse($mailListThread);

//print_r($mailListThread);

header("Content-Type: text/html; charset=".BASE_ENCODING);
header("Cache-Control: no-cache, must-revalidate");
header("Pragma: no-cache");

?><!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=<?php echo BASE_ENCODING; ?>" />
<meta http-equiv="Cache-Control" content="no-cache" />
<meta http-equiv="Pragma" content="no-cache" />
<meta http-equiv="Expires" content="0" />
<title>Uguisu: Mail list</title>
<link rel="stylesheet" type="text/css" href="../css/default.css" />
<style>
table#mail-list{
	margin:0;
	width:100%;
	font-size:80%;
	border-collapse:collapse;
}
table#mail-list tr.current{
	background:#ddf;
}

table#mail-list td{
	padding:0.1em 0.5em;
	border:solid #bbb;
	border-width: 0 0 1px 0;
}

table#mail-list td span.unread{
	color:#773;
}
table#mail-list td span.attach{
	color:#485;
}

table#mail-list tr.child{
	color:#aaa;
}
table#mail-list tr.child a{
	color:#aaa;
}
</style>

<script type="text/JavaScript">

/**
 * メールリンクがクリックされたら
 */
function mailClick(i){
	markCurrent(i);
	unlead2readed(i);
}

/**
 * カレント行を着色
 */
function markCurrent(i){
	var id = 0;
	while(o=document.getElementById("RECORD_"+id)){
		if(i == id){
			o.className="current";
		}else{
			o.className="";
		}
		id++;
	}
}

/**
 * 既読マークを除去する。実際のDBデータの変更は、mail-view.phpで行う。
 */
function unlead2readed(i){
	if(o=document.getElementById("RECORD_"+i+"_UNLEAD")){
		o.style.display="none";
		parent.paneLeft.reload(); //件数が変わるのでリロード予約
	}
	
}

/**
 * 移動リンクを表示
 */
function showMoveLinks(){
	var id = 0;
	while(o=document.getElementById("RECORD_"+id+"_MOVELINK")){
		if(o.style.display=="none"){
			o.style.display="";
		}else{
			break;
		}
		id++;
	}
}

/**
 * メールを移動
 */
function moveMail(id,mail_id){
	u ="utility.php?";
	u += "mode=moveMail&";
	u += "account=<?php echo $account; ?>&";
	u += "mail_id="+mail_id+"&";
	u += "category="+document.getElementById("categoryTo").value+"&";
	document.getElementById("RECORD_"+id).style.display="none";
	parent.paneLeft.reload(); //件数が変わるのでリロード予約
	parent.paneBottom.location.href=u;
}



</script>

</head>
<body>

<div id="header">

<a href="mail-download.php?<?php echo $entityGetQuery; ?>&force=1" title="メールを受信">受信</a> | 
<a href="../write/?account=<?php echo $account; ?>" target="_blank" title="メールを作成">作成</a> | 
<a href="mail-list.php?<?php echo $entityGetQuery; ?>" title="スレッド表示を解除">通常表示</a> | 
<?php
if($category==$CONFIG['trashBox']){
	echo "<a href=\"utility.php?mode=emptyTrash&account=".$account."\" target=\"paneBottom\" title=\"ごみ箱を空にする\">ごみ空</a> | \n";
}else{
	echo "<a href=\"utility.php?mode=autoTrash&account=".$account."&category=".$category."\" target=\"paneBottom\" title=\"過去メールをごみ箱に移動\">ごみ移</a> | \n";
}
?>
<a href="utility.php?mode=allReaded&account=<?php echo $account; ?>&category=<?php echo $category; ?>" target="paneBottom" title="カテゴリ内のすべてのメールを既読にします">全既読化</a> | 
<div id="header-right">
<strong><?php echo $account; ?></strong>
</div>

</div>

<table id="mail-list"><tbody>
<?php

$strToday = date('Y-m-d',time());

$id = 0;

foreach($mailListThread as $threadId => $thread){
	
	$mailList = $thread['record'];
	
	if($CONFIG['threadReverse']) $mailList = array_reverse($mailList);
	
	$parent=true;
	
	foreach($mailList as $record){
		
		if($parent){
			echo "<tr id=\"RECORD_".$id."\">";
		}else{
			echo "<tr id=\"RECORD_".$id."\"  class=\"child\">";
		}
		//日付
		echo "<td title=\"".date('Y-m-d H:i',$record['etime'])."\">";
		$d = date('Y-m-d',$record['etime']);
		if($d==$strToday){
			echo date('H:i',$record['etime']);
		}else{
			echo $d;
		}
		echo "</td>\n";
		
		//差出人
		echo "<td title=\"".htmlspecialchars($record['h_from'])."\">";
		echo htmlspecialchars(mb_strimwidth($record['h_from_fancy'],0,$CONFIG['mailListFromMaxLength'],$CONFIG['mailListCutoffMarker']));
		echo "</td>\n";
		
		//未読サイン
		echo "<td>";
		if(!$record['readed']){
			echo "<span id=\"RECORD_".$id."_UNLEAD\" class=\"unread\" title=\"未読\">★</span>\n";
		}
		echo "</td>\n";
			
		//件名
		echo "<td>";
		
		if(!$parent){
			echo " ↑ ";
		}
		//echo "<a href=\"mail-view.php?account=".htmlspecialchars($account)."&mail_id=".rawurlencode($record['mail_id'])."\" target=\"paneBottom\">";
		//echo htmlspecialchars(mb_strimwidth($record['subject'],0,$CONFIG['mailListSubjectMaxLength'],$CONFIG['mailListCutoffMarker']));
		//echo "</a>";
		//echo "</td>\n";
		echo "<a ";
		echo "href=\"mail-view.php?account=".htmlspecialchars($account)."&mail_id=".rawurlencode($record['mail_id'])."\" ";
		echo "onClick=\"mailClick(".$id.");\" ";
		echo "target=\"paneBottom\">";
		echo htmlspecialchars(mb_strimwidth($record['subject'],0,$CONFIG['mailListSubjectMaxLength'],$CONFIG['mailListCutoffMarker']));
		echo "</a>";
		echo "</td>\n";
		
		
		//添付
		echo "<td>";
		if($record['attach']){
			echo "<span class=\"attach\" title=\"添付\">●</span>\n";
		}
		echo"</td>\n";
		
		if($parent){
			$parent = false;
		}
		$id++;
	}
}

?>
</tbody></table>



<?php Logger::output($CONFIG['debugLevel']); ?>

</body>
</html>
