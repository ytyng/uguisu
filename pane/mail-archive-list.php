<?php

/*
Uguisu メールアーカイブ検索ツール

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

if(!isset($_GET['q']))        $_GET['q'] = "";
if(!isset($_GET['category'])) $_GET['category'] = 0;
if(!isset($_GET['page']))     $_GET['page'] = 1;
if(!isset($_GET['type']))     $_GET['type'] = "";
$account  = $_GET['account'];
$page     = (int)$_GET['page'];
$category = (int)$_GET['category'];



//GETクエリを作成
$entityGetQuery="account=".htmlspecialchars($account);
if(isset($_GET['category'])) $entityGetQuery .= "&category=".htmlspecialchars($_GET['category']);
if(isset($_GET['filter']))   $entityGetQuery .= "&filter="  .htmlspecialchars($_GET['filter']);
if(isset($_GET['q']))        $entityGetQuery .= "&q="       .htmlspecialchars($_GET['q']);
if(isset($_GET['type']))     $entityGetQuery .= "&type="    .htmlspecialchars($_GET['type']);


UguisuAccount::initialize($account); //UguisuAccountクラスは使うかな。使わないかも。

require($CONFIG['mailArchiveClass']);
UguisuMailArchive::initialize($account);

//フリーワード検索
if(isset($_GET['q']) && $_GET['q']){
	if(!isset($CONFIG['quickSearchList'][$_GET['type']])) exit("[ERROR] Illigal type.");
	UguisuMailArchive::setSearchWord($_GET['type'],"contain",$_GET['q']);
}

//フィルタ
/*
if(isset($_GET['filter']) && isset($ACCOUNT[$account]['filter'][$_GET['filter']])){
	$filterRecord = $ACCOUNT[$account]['filter'][$_GET['filter']];
	UguisuMailArchive::setSearchWord($filterRecord['key'],$filterRecord['mode'],$filterRecord['value']);
	$category = $filterRecord['category'];
}
*/
$mailList=UguisuMailArchive::getMailList($page,$category);







header("Content-Type: text/html; charset=".BASE_ENCODING);
header("Cache-Control: no-cache, must-revalidate");
header("Pragma: no-cache");
//header("Content-Language: ja");
//<meta http-equiv="Content-Language" content="ja" />
?><!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html lang="ja">
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
span#archive-title{
	color:#832;
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

/**
 * ポップアップウインドウ
 */
function popUp(url){
	window.open(url,'_blank','width=900,height=700,menubar=no,toolbar=no,resizable=yes,scrollbars=yes');
}
</script>

</head>
<body>

<div id="header">

<form action="./mail-list.php" method="get">
<select name="type">
<?php
foreach($CONFIG['quickSearchList'] as $k => $v){
	if($_GET['type'] == $k) $selected = "selected=\"selected\" "; else $selected ="";
	echo "<option value=\"".$k."\" ".$selected.">".$v."</option>\n";
}
?>
</select>


<input type="text" name="q" value="<?php echo htmlspecialchars($_GET['q']); ?>" />
<input type="submit" value="検索" />
<input type="hidden" name="account" value="<?php echo $account; ?>" />
<input type="hidden" name="category" value="<?php echo $category; ?>" />
</form>

 <span id="archive-title"><?php echo $CONFIG['mail-archive-name']; ?></span>

<div id="header-right">
<strong><?php echo $account; ?></strong>
</div>

</div>

<table id="mail-list"><tbody>
<?php

$strToday = date('Y-m-d',time());

foreach($mailList as $id => $record){
	
	echo "<tr id=\"RECORD_".$id."\">";
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
	echo "<a ";
	echo "href=\"mail-view.php?archive=1&account=".htmlspecialchars($account)."&mail_id=".rawurlencode($record['mail_id'])."\" ";
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
	
	//移動
	echo "<td>";
	echo "<a ";
	echo "id=\"RECORD_".$id."_MOVELINK\" ";
	echo "style=\"display:none;\" ";
	echo "title=\"移動\" ";
	echo "href=\"javascript:moveMail(".$id.",'".rawurlencode(rawurlencode($record['mail_id']))."');void(0);\" ";
	echo">→</a>";
	echo "</td>\n";
	
}
?>
</tbody></table>

<div id="footer">
<?php
if(count($mailList)>=$CONFIG['mailVolumeParPage']){
	echo "<a href=\"mail-list.php?".$entityGetQuery."&page=".($_GET['page']+1)."\">次ページ</a> ";
}
?>
</div>


<?php Logger::output($CONFIG['debugLevel']); ?>

</body>
</html>
