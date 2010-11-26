<?php

/*
携帯/iPhone用ページ

*/

chdir("../");

require("config/config.php");
require($CONFIG['authScriptMobile']);
require($CONFIG['loggerClass']);
require($CONFIG['accountSettingFile']);


header("Content-Type: text/html; charset=".BASE_ENCODING);
header("Cache-Control: no-cache, must-revalidate");
header("Pragma: no-cache");

echo "<html lang=\"ja\">\n";
echo "<head>\n";
echo "<meta http-equiv=\"Content-type\" content=\"text/html; charset=".BASE_ENCODING."\" />\n";
echo "<meta http-equiv=\"Cache-Control\" content=\"no-cache\" />\n";
echo "<meta http-equiv=\"Pragma\" content=\"no-cache\" />\n";
echo "<meta http-equiv=\"Expires\" content=\"0\" />\n";
echo "<meta name=\"viewport\" content=\"width=320,user-scalable=no,maximum-scale=1\" />\n";
echo "<title>Uguisu:mobile</title>\n";
echo "<link rel=\"stylesheet\" type=\"text/css\" href=\"m.css\" />\n";
echo "</head>\n";
echo "<body>\n";


$colorId = 0;
//
// ■階層1:アカウント
//
require($CONFIG['accountListClass']);
UguisuAccountList::initialize();

if(isset($_GET['account']) && isset($ACCOUNT[$_GET['account']])){
	$account=$_GET['account'];
}else{
	$account="";
}

if(!$account){
	//(1)アカウント指定なし。選択メニューを表示する。
	UguisuAccountList::updateCount(); //このタイミングで件数を更新
	echo "<table id=\"listTable\">\n";
	foreach($ACCOUNT as $a => $record){
		$nextUrl = "./?account=".$a."#category";
		
		echo "<tr onClick=\"location.href='".$nextUrl."';\" class=\"color".($colorId++%2)."\">";
		echo "<td>";
		echo "<a href=\"".$nextUrl."\">".$a."</a> ";
		$aryCount = UguisuAccountList::getMailCountTotal($a);
		
		if(
			isset($record['strong-unread']) &&
			$record['strong-unread'] &&
			$aryCount['unread'] > 0
		){
			echo "(<strong style=\"color:#f00;\">".$aryCount['unread']."</strong>)";
		}else{
			echo "(".$aryCount['unread'].")";
		}

		echo "</td>";
		echo "<td class=\"nexticon\">&gt;</td>";
		echo "</tr>\n";
		
	}
	echo "</table>\n";
	
}else{
	
	echo "<table id=\"selector\">\n";
	//アカウントリストをプルダウンで表示
	echo "<form action=\"./\" method=\"get\">\n";
	echo "<tr>";
	echo "<td>";
	echo "<select name=\"account\">\n";
	foreach($ACCOUNT as $a => $record){
		if($account == $a) $selected = "selected=\"selected\" "; else $selected = "";
		echo "<option value=\"".$a."\" ".$selected.">".$a."</option>\n";
	}
	echo "</select>\n";
	echo "</td>\n";
	echo "<td class=\"nextbutton\">";
	echo "<input type=\"submit\" value=\"移動\" />\n";
	echo "</td>";
	echo "</tr>";
	echo "</form>\n";
	
	//
	// ■階層2:カテゴリ
	//
	
	//カテゴリリストを表示
	require($CONFIG['accountClass']);    //HERE(1)に移したほうが効率的か?
	UguisuAccount::initialize($account);	
	
	$categoryList = makeCategoryList($account);
	
	if(isset($_GET['category']) && isset($categoryList[$_GET['category']])){
		$category=$_GET['category'];
	}else{
		$category="";
	}
	
	if($category == ""){
		echo "</table>\n";  //selector
		
		UguisuAccount::downloadMail(); //カテゴリ選択前のタイミングで、メールダウンロードをしてしまう
		
		//カテゴリ選択なし。リストを表示
		echo "<a name=\"category\"></a>\n";
		echo "<table id=\"listTable\">\n";
		foreach($categoryList as $c => $record){
			$nextUrl = "./?account=".$account."&category=".$c."#mail";
			echo "<tr onClick=\"location.href='".$nextUrl."';\" class=\"color".($colorId++%2)."\">";
			echo "<td>";
			echo "<a href=\"".$nextUrl."\">";
			echo $record['name']."(";
			
			if(
				isset($ACCOUNT[$account]['strong-unread']) && 
				$ACCOUNT[$account]['strong-unread'] &&
				0 < $record['unread']
			){
				echo "<strong style=\"color:#f00;\">".$record['unread']."</strong>";
			}else{
				echo $record['unread'];
			}
			echo "<small>/".$record['fullcount']."</small>)</a><br />\n";
			echo "</td>";
			echo "<td class=\"nexticon\">&gt;</td>";
			echo "</tr>\n";
		}
		echo "</table>\n";
	}else{
		//カテゴリ選択あり。プルダウンを表示
		echo "<form action=\"./\" method=\"get\">\n";
		echo "<tr>";
		echo "<td>";
		echo "<select name=\"category\">\n";
		foreach($categoryList as $c => $record){
			if($category == $c) $selected = "selected=\"selected\" "; else $selected = "";
			echo "<option value=\"".$c."\" ".$selected.">".$record['name']."(".$record['unread'].")"."</option>\n";
		}
		echo "</select>\n";
		echo "</td>";
		echo "<td class=\"nextbutton\">";
		echo "<input type=\"submit\" value=\"移動\" />\n";
		echo "<input type=\"hidden\" name=\"account\" value=\"".$account."\" />\n";
		echo "</td>";
		echo "</tr>\n";
		echo "</form>\n";
		
		//
		// ■階層3:メールリスト
		//
		
		if(isset($_GET['mail_id'])){
			$mail_id=rawurldecode($_GET['mail_id']);
			$urlencode_mail_id = rawurlencode($mail_id);
		}else{
			$mail_id="";
		}
		
		require($CONFIG['mailListClass']);
		UguisuMailList::initialize($account);
		
		
		$page = 1;
		$mailList=UguisuMailList::getMailList($page,$category);
		
		if(!$mail_id){
			echo "</table>\n";  //selector
			
			//メール選択なし。リストを表示
			echo "<a name=\"mail\"></a>\n";
			echo "<table id=\"listTable\">\n";
			foreach($mailList as $id => $record){
				$nextUrl = "./?account=".$account."&category=".$category."&mail_id=".rawurlencode($record['mail_id'])."#view";
				echo "<tr onClick=\"location.href='".$nextUrl."';\" class=\"color".($colorId++%2)."\">";
				
				echo "<td class=\"unreadMark\">";
								
				if(!$record['readed']){
					echo "★";
				}else{
					echo "&nbsp;";
				}
				echo "</td>";
				echo "<td>";
				echo "<a href=\"".$nextUrl."\">\n";
				//echo makeMailTitle($record);
				
				echo "<small>\n";
				echo dateHumanReadable($record['etime']);
				echo " | ";
				echo  htmlspecialchars(mb_strimwidth($record['h_from_fancy'],0,$CONFIG['mailListFromMaxLength'],$CONFIG['mailListCutoffMarker']));
				echo "</small><br />";
				echo htmlspecialchars(mb_strimwidth($record['subject'],0,$CONFIG['mailListSubjectMaxLength'],$CONFIG['mailListCutoffMarker']));
				echo "</a>\n";
				echo "</td>";
				echo "<td class=\"nexticon\">&gt;</td>";
				echo "</tr>\n";
			}
			echo "</table>\n";
		}else{
			//メール選択あり。プルダウンを表示
			
			echo "<form action=\"./\" method=\"get\">\n";
			echo "<tr>";
			echo "<td>";
			echo "<select name=\"mail_id\" style=\"max-width:100%\">\n";
			foreach($mailList as $id => $record){
				if($mail_id == $record['mail_id']) $selected = "selected=\"selected\" "; else $selected = "";
				echo "<option value=\"". rawurlencode($record['mail_id'])."\" ".$selected.">";
				echo makeMailTitle($record);
				echo "</option>\n";
			}
			echo "</select>\n";
			echo "</td>";
			echo "<td class=\"nextbutton\">";
			echo "<input type=\"submit\" value=\"移動\" />\n";
			echo "<input type=\"hidden\" name=\"account\" value=\"".$account."\" />\n";
			echo "<input type=\"hidden\" name=\"category\" value=\"".$category."\" />\n";
			echo "</td>";
			echo "</tr>\n";
			echo "</form>\n";
			
			echo "</table>\n"; //selector
			
			echo "<a name=\"view\"></a>\n";
			
			
			require($CONFIG['mailViewClass']);
			UguisuMailView::initialize($account,$mail_id);
			
			echo "<p id=\"header\">";
			echo "Su:".htmlspecialchars(UguisuMailView::get("subject"))."<br />\n";
			echo "To:".htmlspecialchars(UguisuMailView::get("h_to"))."<br />\n";
			echo "Cc:".htmlspecialchars(UguisuMailView::get("h_cc"))."<br />\n";
			echo "Fr:".htmlspecialchars(UguisuMailView::get("h_from"))."<br />\n";
			echo "Da:".date('Y-m-d H:i:s',UguisuMailView::get("etime"))."<br />\n";
			if(UguisuMailView::get("attach")){ //添付ファイルが存在するなら
				$attachFileList    = explode("\t",UguisuMailView::get("attach"));
				$attachContentType = explode("\t",UguisuMailView::get("attach_content_type"));
				foreach($attachFileList as $partIndex => $fileName){
					echo "At:";
					echo "<a href=\"../pane/download-attach-file.php?account=".$account."&mail_id=".$urlencode_mail_id."&partIndex=".$partIndex."\">";
					echo htmlspecialchars($fileName);
					echo "</a>";
					echo " (".htmlspecialchars($attachContentType[$partIndex]).")\n";
				}
			}
			echo "</p>\n";
			echo "<p id=\"bodytext\">";
			echo nl2br(autoLink(htmlspecialchars(UguisuMailView::get("bodytext"))));
			echo "</p>\n";
			
		}
		
	}
	



}


function makeCategoryList($account){
	global $CONFIG;
	global $ACCOUNT;
	//ビルトインカテゴリを表示
	$aryOut = array();
	foreach($CONFIG['built-in-category'] as $category => $builtInCategory){
		$mailCount = UguisuAccountList::getMailCount($account,$category);
		$aryOut[$category] = array(
			'name'      => $builtInCategory['name'],
			'unread'    => $mailCount['unread'],
			'fullcount' => $mailCount['fullcount'],
		);
	}
	
	//アカウントカテゴリを表示
	if(isset($ACCOUNT[$account]['category'])){
		foreach($ACCOUNT[$account]['category'] as $category => $item){
			$mailCount = UguisuAccountList::getMailCount($account,$category);
			$aryOut[$category] = array(
				'name'      => $item['name'],
				'unread'    => $mailCount['unread'],
				'fullcount' => $mailCount['fullcount'],
			);
		}
	}
	return $aryOut;
}

function makeMailTitle($record){
	global $CONFIG;
	$buffer = "";
	if(!$record['readed']){
		$buffer.= "★";
	}else{
		$buffer.= "　";
	}
	//$strToday = date('Y-m-d',time()); //少々非効率
	//$buffer.= "[";
	//$buffer.=dateHumanReadable($record['etime']);
	//$buffer .= "]";
	
	$buffer .= htmlspecialchars(mb_strimwidth($record['h_from_fancy'],0,$CONFIG['mailListFromMaxLength'],$CONFIG['mailListCutoffMarker']));
	$buffer .= " | ";
	$buffer .= htmlspecialchars(mb_strimwidth($record['subject'],0,$CONFIG['mailListSubjectMaxLength'],$CONFIG['mailListCutoffMarker']));
	return $buffer;
}

$STR_TODAY = date('Y-m-d',time());
/**
 * 日付を読みやすく表示
 */
function dateHumanReadable($d){
	global $STR_TODAY;
	$d = date('Y-m-d',$d);
	if($d==$STR_TODAY){
		return date('H:i',$d);
	}
	return $d;
}	
	

/**
 * 自動リンク
 */
function autoLink($text){
	$text = ereg_replace(
		"(https?|ftp)(://[[:alnum:]\+\$\;\?\.%,!#~*/:@&=_-]+)",
		"<a class=\"linkthumb\" href=\"\\1\\2\" target=\"_blank\">\\1\\2</a>",
		$text
	);
	return $text;
}
echo "<div id=\"footer\">";	
echo "<a href=\"./\">Top</a>\n";
echo "</div>\n";
?>