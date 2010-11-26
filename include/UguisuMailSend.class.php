<?php
/*
メール送信処理を行うクラス
(実際の送信はEsmtpクラスが行う)

20090715 Dateヘッダを送信する

*/


class UguisuMailSend{

	private static $CONFIG  = array();
	private static $ACCOUNT = array();
	//public  static $accountRecord = array();
	
	private static $smtpLog = "";
	
	/**
	 * 初期化
	 */
	public static function initialize(){
		Logger::debug(__METHOD__);
		
		global $CONFIG;
		global $ACCOUNT;
		self::$CONFIG  = &$CONFIG;
		self::$ACCOUNT = &$ACCOUNT;
		
	}
	
	
	/**
	 * 返信用パラメータを取得
	 */
	public static function getReplyParameter($account,$mailId){
		Logger::debug(__METHOD__,"account=".$account.",mailId=".$mailId);
		
		//DB接続
		$accountDirectory = self::$CONFIG['dataDirectory']."/".$account;
		$dbh = new PDO(
			self::$CONFIG['pdoDriver'].":".$accountDirectory."/".self::$CONFIG['mailDatabase'],
			self::$CONFIG['pdoUser'],
			self::$CONFIG['pdoPassword']
		);		
		$dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_WARNING);
		
		$sql = "SELECT ".
			"subject, h_from, h_from_real, h_to, h_to_real, h_cc, h_message_id,  bodytext, etime ".
			"FROM maildata WHERE mail_id = :mail_id";
		$prepare = $dbh->prepare($sql);
		
		$prepare->execute(array(':mail_id'=>$mailId,));
		
		$result = $prepare->fetch();
		
		//引用本文を作成
		$referenceBody = "\n";
		$referenceBody .= "From: ".$result['h_from']."\n";
		$referenceBody .= "Date: ".date("r",$result['etime'])."\n";
		$referenceBody .= self::makeReferenceBody($result['bodytext']);
		
		//引用件名を作成
		if(!preg_match('/^Re:/i',$result['subject'])){
			$result['subject'] = "Re: ".$result['subject'];
		}
		
		//ToとCcを整理する
		$aryTo = self::trimAddressMulti($result['h_to']);
		$aryCc = self::trimAddressMulti($result['h_cc']);
		$from  = $result['h_from_real'];
		
		if($from == $account){
			//自分への送信は再送信扱い
			Logger::debug(__METHOD__,"自分への返信");
			//マッピング
			$aryReturn = array(
				'account'    => $account,
				'to'         => self::encloseAddress($result['h_to_real']),
				'cc'         => $result['h_cc'],
				'bcc'        => "",
				'from'       => self::encloseAddress($account),
				'reply-to'   => self::encloseAddress($account),
				'references' => self::encloseAddress($result['h_message_id']),
				'subject'    => $result['subject'],
				'body'       => $referenceBody,
			);			
		}else{
			foreach($aryTo as $to){
				if($to != $account){
					//自分以外のToはCcに。
					Logger::debug(__METHOD__,"ToのアドレスをCcに移行(".$to.")");
					$aryCc[] = $to;
				}
			}
			//マッピング
			$aryReturn = array(
				'account'    => $account,
				'to'         => self::encloseAddress($result['h_from_real']),
				'cc'         => join(", ",self::encloseAddress($aryCc)),
				'bcc'        => "",
				'from'       => self::encloseAddress($account),
				'reply-to'   => self::encloseAddress($account),
				'references' => self::encloseAddress($result['h_message_id']),
				'subject'    => $result['subject'],
				'body'       => $referenceBody,
			);
		}
		
		
		return $aryReturn;
	}
	
	//引用のための本文を作成する。
	//要は、各行頭に> を付加する。
	private static function makeReferenceBody($str){
		Logger::debug(__METHOD__);
		$buffer = "";
		$aryTmp = explode("\n",$str);
		foreach($aryTmp as $line){
			$buffer .= ">".$line."\n";
		}
		return $buffer;
	}
	
	
	/**
	 * メールの送信を行う
	 * @param  メールパラメータを格納した配列
	 * @return 結果ブール,エラー配列 のリスト。
	 */
	public static function sendmail($MAIL){
		Logger::debug(__METHOD__);
		$aryErr = array();
		$result = false;
		
		if(!isset($MAIL['to']))         $MAIL['to']         = "";
		if(!isset($MAIL['cc']))         $MAIL['cc']         = "";
		if(!isset($MAIL['bcc']))        $MAIL['bcc']        = "";
		if(!isset($MAIL['from']))       $MAIL['from']       = "";
		if(!isset($MAIL['reply-to']))   $MAIL['reply-to']   = "";
		if(!isset($MAIL['references'])) $MAIL['references'] = "";
		if(!isset($MAIL['body']))       $MAIL['body']       = "";
		if(!isset($MAIL['subject']))    $MAIL['subject']    = "";
		
		//========================================================== Check Error
		if(!isset(self::$ACCOUNT[$MAIL['account']])){
			$aryErr['account'] = "存在しないアカウント";
		}
		
		if(!$MAIL['to']){
			$aryErr['to'] = "必須項目です";
		}else{
			$MAIL['to'] = trim($MAIL['to']);
			if(!self::isEmailMulti($MAIL['to'])){
				$aryErr['to'] = "不正な値です";
			}
		}
		
		if($MAIL['cc']){
			$MAIL['cc'] = trim($MAIL['cc']);
			if(!self::isEmailMulti($MAIL['cc'])){
				$aryErr['cc'] = "不正な値です";
			}
		}
		
		if($MAIL['bcc']){
			$MAIL['bcc'] = trim($MAIL['bcc']);
			if(!self::isEmailMulti($MAIL['bcc'])){
				$aryErr['bcc'] = "不正な値です";
			}
		}
		
		if(!$MAIL['from']){
			$aryErr['from'] = "必須項目です";
		}else{
			$MAIL['from'] = trim($MAIL['from']);
			if(!self::isEmail($MAIL['from'])){
				$aryErr['from'] = "不正な値です";
			}
		}
		
		if($MAIL['reply-to']){
			$MAIL['reply-to'] = trim($MAIL['reply-to']);
			if(!self::isEmail($MAIL['reply-to'])){
				$aryErr['reply-to'] = "不正な値です";
			}
		}
		
		$body = "";
		//========================================================== 添付ファイル処理 20090224
		$isMultiPart = false;
		$multipartBoundary="------";
		//print_r($_FILES);
		foreach($_FILES as $i => $attach){
			//print_r($attach);
			if(
				isset($attach['name']) &&
				$attach['name'] &&
				$attach['size']
			){
				//echo $attach['name'];
				Logger::debug(__METHOD__,"attach=".$attach['name']);
				if($isMultiPart == false){
					//最初の添付ファイルを検出
					$isMultiPart = true;
					$multipartBoundary="------_".uniqid()."_MULTIPART_MIXED_";
					
					$body .= "This is a multi-part message in MIME format.\r\n";
					$body .= "--".$multipartBoundary."\r\n";
					$body .= "Content-Type: text/plain;\r\n charset=\"ISO-2022-JP\"\r\n";
					$body .= "Content-Transfer-Encoding: 7bit\r\n";
					$body .= "\r\n";
					$body .= rtrim($MAIL['body'])."\r\n";
					$body .= "\r\n";
					$body .= "\r\n";
				}
				
				//本文に添付ファイルを結合
				$fileName= mb_encode_mimeheader($attach['name']);
				$body .= "--".$multipartBoundary."\r\n";
				$body .= "Content-Type: ".$attach['type'].";\r\n name=\"".$fileName."\"\r\n";
				$body .= "Content-Disposition: attachment;\r\n filename=\"".$fileName."\"\r\n";
				$body .= "Content-Transfer-Encoding: base64\r\n";
				$body .= "\r\n";
				
				//temp添付ファイル読み込み
				$fp = fopen($attach["tmp_name"], "r");
				$buf = fread($fp, $attach["size"]); //sizeいらないかも
				fclose($fp);
				//temp添付ファイルをbase64化
				$strAttachBase64 = chunk_split(base64_encode($buf));
				$body .= $strAttachBase64;
			}else if( 				
				isset($attach['name']) &&
				$attach['name'] &&
				$attach['error']
			){
				echo("ファイルアップロードエラー:".$attach['name'].",error=".$attach['error']."<br />\n");
			}
		}
		
		if($isMultiPart){
			//マルチパートメッセージの場合はマルチパートを閉じる
			$body .= "--".$multipartBoundary."--\r\n";
			$body .= "\r\n";
			
		}else{
			//マルチパートメッセージでない場合は、本文を作成。
			$body = $MAIL['body'];
		}
		
		
		if(!count($aryErr)){ //エラーが無いのでメールを送信
			
			//========================================================== Make Ex-Header
			
			$ex_header = "";
			$ex_header .= "MIME-Version: 1.0\r\n";
			if($isMultiPart){
				$ex_header .= "Content-Type: multipart/mixed; boundary=\"".$multipartBoundary."\"\r\n";
			}else{
				$ex_header .= "Content-Type: text/plain; charset=ISO-2022-JP\r\n";
			}
			$ex_header .= "X-Mailer: Uguisu / ytyng.com\r\n";
			$ex_header .= "User-Agent: Uguisu / ytyng.com\r\n";
			$ex_header .= "Content-Transfer-Encoding: 7bit\r\n";
			$ex_header .= "To: ".$MAIL['to']."\r\n";
			if($MAIL['cc'])  $ex_header .= "Cc: ".$MAIL['cc']."\r\n";
			//if($MAIL['bcc']) $ex_header .= "Bcc: ".$MAIL['bcc']."\r\n";
			if($MAIL['from']) $ex_header .= "From: ".$MAIL['from']."\r\n";
			if($MAIL['references']) $ex_header .= "References: ".$MAIL['references']."\r\n";
			if($MAIL['reply-to']) $ex_header .= "Reply-To: ".$MAIL['reply-to']."\r\n";
			
			$ex_header .= "Date: ".date(DATE_RFC2822)."\r\n";
			
			//========================================================== Write Log
			$fhLogSend = fopen(self::$CONFIG['mailSendLog'],'a');
			fwrite($fhLogSend,
				time()."\t".
				self::remove_nl($MAIL['account'])."\t".
				self::remove_nl($MAIL['to'])."\t".
				self::remove_nl($MAIL['cc'])."\t".
				self::remove_nl($MAIL['bcc'])."\t".
				self::remove_nl($MAIL['from'])."\t".
				self::remove_nl($MAIL['reply-to'])."\t".
				self::remove_nl($MAIL['references'])."\t".
				self::remove_nl(nl2br(htmlspecialchars($body)))."\t".
				self::remove_nl($MAIL['subject'])."\t".
				"\n"
			);
			fclose($fhLogSend);
			
			
			//========================================================== Make RCPT To
			$rcpt_to = $MAIL['to'];
			if($MAIL['cc'])  $rcpt_to .= ",".$MAIL['cc'];
			if($MAIL['bcc']) $rcpt_to .= ",".$MAIL['bcc'];
			
			
			//========================================================== Send Mail
			//require_once(self::$CONFIG['smtpClass']);
			
			$accountRecord = self::$ACCOUNT[$MAIL['account']];
			
			$m = new Esmtp(
				$MAIL['account'],
				$accountRecord['smtp-server'],
				$accountRecord['smtp-port']
			);
			
			//認証情報が設定されていれば認証する。設定されていなければ認証しない。
			if(
				isset($accountRecord['smtp-authtype']) && $accountRecord['smtp-authtype'] &&
				isset($accountRecord['smtp-id']) && $accountRecord['smtp-id'] &&
				isset($accountRecord['smtp-passwd']) && $accountRecord['smtp-passwd']
			){
				$m->auth(
					$accountRecord['smtp-authtype'],
					$accountRecord['smtp-id'],
					$accountRecord['smtp-passwd']
				);
			}
			$result = $m->mb_send(
				$rcpt_to,
				$MAIL['subject'],
				$body,
				$ex_header
			);
			$m->quit();
			
			self::$smtpLog = $m->log;
			/*
			if($result){
				//header("Location: ./?mode=complete");
				include("include/complete.php");
				die();
				//echo "mail sending<BR />";
			}else{
				echo "mail sending : error!!<BR />";
			}
			echo "<PRE>".htmlspecialchars($m->log)."</PRE>";
			echo "<TEXTAREA style=\"width:100%;height:400px;\">";
			echo htmlspecialchars($body);
			echo "</TEXTAREA>\n";
			*/
		}
		
		return array($result,$aryErr);
		
		
	}	
	
	/**
	 * メールアドレスを正規化
	 */	
	public static function trimAddress($mailAddr){
		Logger::debug(__METHOD__,$mailAddr);
		$reg = "([A-Za-z0-9+_]|\\-|\\.)+@(([a-z0-9_]|\\-)+\\.)+[a-z]{2,6}";
		if(preg_match("/".$reg."/",$mailAddr,$result)){
			return $result[0];
		}else{
			Logger::debug(__METHOD__,"Trim failed.");
			return "";
		}
	}
	
	/**
	 * 複数メールアドレスを正規化
	 * 配列で返すようにした
	 */	
	public static function trimAddressMulti($mailAddr){
		Logger::debug(__METHOD__,$mailAddr);
		//$mailAddr = str_replace(';',',',$mailAddr);
		//$aryMailAddr = explode(',',$mailAddr);
		$aryMailAddr = preg_split("/[\t\n\,\;]+/",$mailAddr);
		$aryMailAddrOut = array();
		
		foreach($aryMailAddr as $value){
			$result = self::trimAddress($value);
			if($result){
				$aryMailAddrOut[]=$result;
			}else{
				Logger::debug(__METHOD__,"Regist cancel. ".$value);
			}
		}
		return $aryMailAddrOut;
		//return join(',',$aryMailAddrOut);
	}
	
	
	/**
	 * メールアドレスの正常値判断
	 */
	public static function isEmail($email){
		$reg = "([A-Za-z0-9+_]|\\-|\\.)+@(([a-z0-9_]|\\-)+\\.)+[a-z]{2,6}";
		if(!(strstr($email,'@') && strstr($email, '.'))) return false;
		if(preg_match("/^".$reg."$/i",$email)) return true;
		if(preg_match("/<".$reg.">$/i",$email)) return true;
		if(preg_match("/^\"".$reg."\"$/i",$email)) return true;
		if(preg_match("/^\".*\"\s*<".$reg.">$/i",$email)) return true;
		
		return false;
	}
	
	
	/**
	 * 複数メールアドレスの正常値判断
	 */	
	public static function isEmailMulti($email){
		//$aryEmail = explode(',',$email);
		$aryEmail = preg_split("/[\t\n\,\;]+/",$email);
		foreach($aryEmail as $str){
			if(!self::isEmail(trim($str))) return false;
		}
		return true;
	}
	
	/**
	 * 改行除去
	 */
	private  static function remove_nl($str){
		$str = str_replace("\t"," ",$str);
		$str = str_replace("\r"," ",$str);
		$str = str_replace("\n"," ",$str);
		return $str;
	}
	
	/**
	 * smtpのログを取得
	 */
	public static function getSmtpLog(){
		return self::$smtpLog;
	}
	
	/**
	 * メールアドレスを<...>で囲う。
	 * 文字列でも配列(一次元)でもOK
	 */
	private function encloseAddress($arg){
		
		if(is_array($arg)){
			foreach($arg as $i => $cell){
				$arg[$i] = "<".trim($cell,"<> ").">";
			}
			return $arg;
		}else{
			if(strpos($arg,",")!==FALSE){
				//カンマあり。分割する
				$addressList = self::encloseAddress(explode(",",$arg));
				return implode(",",$addressList);
			}else if(strpos($arg,";")!==FALSE){
				//セミコロンあり。分割する
				$addressList = self::encloseAddress(explode(";",$arg));
				return implode(";",$addressList);
			}
				
			$arg = trim($arg,"<> ");
			if($arg){
				return "<".$arg.">";
			}else{
				return "";
			}
		}
	}
}

?>
