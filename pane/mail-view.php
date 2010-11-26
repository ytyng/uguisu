<?php

/*
Uguisu メール表示

20090907 アーカイブモードに対応
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
if(!isset($_GET['mode'])) $_GET['mode'] = "";

//アーカイブモード
$archive = false;
$archiveQuery="";
if(isset($_GET['archive']) && $_GET['archive']){
	$archive = true;
	$archiveQuery="archive=1&";
}

$account = $_GET['account'];
UguisuAccount::initialize($account);

//未読メールをクリックしたら既読メールとなるので、件数をアップデート
//if($CONFIG['mailCountAlways']) UguisuAccount::updateCount();
//遅いのでやめる

if(!isset($_GET['mail_id'])) exit("[ERROR] No set mail_id.");
$mail_id = rawurldecode($_GET['mail_id']);
$urlencode_mail_id = rawurlencode($mail_id);

require($CONFIG['mailViewClass']);
UguisuMailView::initialize($account,$mail_id,$archive);

$entitySubject  = htmlspecialchars(UguisuMailView::get("subject"));
$entityBodytext = htmlspecialchars(UguisuMailView::get("bodytext"));
$entityTo       = htmlspecialchars(UguisuMailView::get("h_to"));
$entityCc       = htmlspecialchars(UguisuMailView::get("h_cc"));
$entityFrom     = htmlspecialchars(UguisuMailView::get("h_from"));
$entityHeader   = htmlspecialchars(UguisuMailView::get("header"));
$eDate          = date('Y-m-d H:i:s',UguisuMailView::get("etime"));

$attachFileList    = explode("\t",UguisuMailView::get("attach"));
$attachContentType = explode("\t",UguisuMailView::get("attach_content_type"));


switch($_GET['mode']){
case "strip_tags":
	$bodyText = strip_tags(UguisuMailView::get("bodytext"));
	$bodyText = preg_replace("/\s*\n\s*/","\n",$bodyText);//複数改行をまとめる
	$entityBodytext = htmlspecialchars($bodyText);
	break;
}

//自動リンクする
$entityBodytext = UguisuMailView::autoLink($entityBodytext);

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
<title><?php echo $entitySubject; ?></title>
<link rel="stylesheet" type="text/css" href="../css/default.css" />
<style>

table#main-header{
	font-size:100%;
	margin-right:4em;
	border-collapse:collapse;
}
table#main-header th{
	text-align:right;
	padding:0.1em 1em;
}
table#main-header td{
	text-align:left;
	padding:0.1em 0.1em;
}

pre#bodytext{
	padding:0 0 1em 1em;
	font-size:90%;
	white-space: -moz-pre-wrap;
	white-space: -pre-wrap;
	white-space: -o-pre-wrap;
	white-space: pre-wrap;
	word-wrap: break-word;
}

pre#header-full{
	margin:0;
	padding:1em;
	font-size:90%;
	white-space: -moz-pre-wrap;
	white-space: -pre-wrap;
	white-space: -o-pre-wrap;
	white-space: pre-wrap;
	word-wrap: break-word;
}
</style>
<script type="text/JavaScript">
function flipHeader(){
	
	if(document.getElementById("header-full").style.display=="none"){
		document.getElementById("header-full").style.display="";
		document.getElementById("main-header").style.display="none";
	}else{
		document.getElementById("header-full").style.display="none";
		document.getElementById("main-header").style.display="";
	}
}

function popUp(url){
	window.open(url,'_blank','width=900,height=700,menubar=no,toolbar=no,resizable=yes,scrollbars=yes');
}

<?php /*
function replyAll(){
	url="../write/?account=<?php echo $account; ?>&reply=<?php echo $urlencode_mail_id; ?>&replyAll=1"; 
	window.open(url,'_blank','width=900,height=700,menubar=no,toolbar=no,resizable=yes,scrollbars=yes');
}

function reply(){
	url="../write/?account=<?php echo $account; ?>&reply=<?php echo $urlencode_mail_id; ?>"; 
	window.open(url,'_blank','width=900,height=700,menubar=no,toolbar=no,resizable=yes,scrollbars=yes');
}
function viewSource(){
	url="mail-view-source.php?account=<?php echo $account; ?>&mail_id=<?php echo $urlencode_mail_id; ?>";
	window.open(url,'_blank','width=900,height=700,menubar=no,toolbar=no,resizable=yes,scrollbars=yes');
}
*/ ?>

</script>
</head>
<body>

<div id="header">

<table id="main-header"></tbody>
<tr>
<th><nobr>件名</nobr></th>
<td><?php echo $entitySubject; ?></td>
</tr>
<tr>
<th><nobr>To</nobr></th>
<td><?php echo UguisuMailView::commaAddSpace($entityTo); ?></td>
</tr>
<tr>
<th><nobr>Cc</nobr></th>
<td><?php echo UguisuMailView::commaAddSpace($entityCc); ?></td>
</tr>
<tr>
<th><nobr>From</nobr></th>
<td><?php echo $entityFrom; ?></td>
</tr>
<tr>
<th><nobr>送信日</nobr></th>
<td><?php echo $eDate; ?></td>
</tr>
<?php
if(UguisuMailView::get("attach")){ //添付ファイルが存在するなら
	echo "<tr>\n";
	echo "<th>添付</th>\n";
	echo "<td>";
	foreach($attachFileList as $partIndex => $fileName){
		echo "<a href=\"download-attach-file.php?".$archiveQuery."account=".$account."&mail_id=".$urlencode_mail_id."&partIndex=".$partIndex."\">";
		echo htmlspecialchars($fileName);
		echo "</a>";
		echo "<small>(".htmlspecialchars($attachContentType[$partIndex]).")</small> ";
	}
	echo "</td>\n";
	echo "</tr>\n";
}
?>
</tbody></table>

<pre id="header-full" style="display:none;"><?php echo $entityHeader; ?>
</pre>

<div id="header-right">
<?php
$url="../write/?account=".$account."&reply=".$urlencode_mail_id."&replyAll=1";
echo "<a href=\"".$url."\" target=\"_blank\" onClick=\"javascript:popUp('".$url."');return false;\">全員に返信</a><br />\n";
$url="../write/?account=".$account."&reply=".$urlencode_mail_id;
echo "<a href=\"".$url."\" target=\"_blank\" onClick=\"javascript:popUp('".$url."');return false;\">返信</a><br />\n";

echo "<a href=\"javascript:flipHeader();void(0);\">ヘッダ+</a><br />\n";

if($ACCOUNT[$account]['save-source']){
	$url="mail-view-source.php?account=".$account."&mail_id=".$urlencode_mail_id;
	echo "<a href=\"".$url."\" target=\"_blank\" onClick=\"javascript:popUp('".$url."');return false;\">ソース</a><br />\n";
}

if($_GET['mode'] != "strip_tags"){
	echo "<a href=\"mail-view.php?".$archiveQuery."mode=strip_tags&account=".$account."&mail_id=".$urlencode_mail_id."\" >";
	echo "タグ除去";
	echo "</a><br />\n";
}
?>
</div>
</div>

<pre id="bodytext"><?php echo $entityBodytext; ?>
</pre>

<?php Logger::output($CONFIG['debugLevel']); ?>

</body>
</html>

