<?php

/*
添付ファイルをダウンロード

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


//アーカイブモード
$archive = false;
//$archiveQuery="";
if(isset($_GET['archive']) && $_GET['archive']){
	$archive = true;
	//$archiveQuery="archive=1&";
}

$account = $_GET['account'];
UguisuAccount::initialize($account);

if(!isset($_GET['mail_id'])) exit("[ERROR] No set mail_id.");
$mail_id = rawurldecode($_GET['mail_id']);
require($CONFIG['mailViewClass']);
UguisuMailView::initialize($account,$mail_id,$archive);

$attachFileList    = explode("\t",UguisuMailView::get("attach"));
$attachContentType = explode("\t",UguisuMailView::get("attach_content_type"));

if(!isset($_GET['partIndex'])) exit("[ERROR] No set partIndex.");
if(!isset($attachFileList[$_GET['partIndex']])) exit("[ERROR] No exist part.");

$fileName    = $attachFileList[$_GET['partIndex']];
$contentType = $attachContentType[$_GET['partIndex']];
$filePath    = $CONFIG['dataDirectory']."/".$account."/".$CONFIG['attachDirectory']."/".UguisuAccount::sanitizeMailId($mail_id);
$filePath   .= "/".mb_convert_encoding($fileName,$CONFIG['fileSystemEncoding'],mb_internal_encoding());

if(!is_file($filePath)) exit("[ERROR] File not exist. ".$filePath);

header("Content-Type: ".$contentType);

if(strpos($_SERVER['HTTP_USER_AGENT'],"MSIE")!==false){
	//IEはWindows-31Jでダウンロード
	$encodedFilename = mb_convert_encoding($fileName,"SJIS-win",mb_internal_encoding());
}else{
	$encodedFilename = mb_encode_mimeheader($fileName);
}
	
header("Content-Disposition: attachment; filename=".$encodedFilename);

echo file_get_contents($filePath);


?>