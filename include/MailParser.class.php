<?php

/*
電子メールパーサクラス

ytyng.com


メールソースは、
+全体ヘッダー
+マルチパート1
| +マルチパートヘッダ1
| +マルチパート本文1
+マルチパート2
  +マルチパートヘッダ2
  +マルチパート本文2

基本的にこのような構造になっている。

20090507
添付テキストファイルの文字コードを自動判定するよう修正

20090514
添付テキストファイル名のヘッダが分割されていて、インデントにタブが使われている場合、
ファイル名にタブが入ってしまう問題を修正

20100428
ヘッダーにダブルクォーテーションが入ってなくてもちゃんと解析するよう修正
BASE64で本文がエンコードされていても正しく表示されるよう修正
*/



class MailParser{
	
	const REGEX_CONTENT_TYPE = "/charset=[\"\']?([\w\-\_]+)[\"\']?/i";
	
	public  $debugMessage = "";
	
	public  $contentType  = "";
	public  $boundary     = "";
	public  $is_multipart = false; //マルチパートメールか否か
	
	public  $strHeader = ""; //ヘッダを文字列で保存しておく
	
	private $aryHeaderBuffer = array(); //ヘッダを配列で格納していく。
	public  $aryHeader       = array(); //ヘッダ名をキーとする連想配列
	
	
	public  $strBody     = ""; //本文テキスト
	public  $strBodyTemp = ""; //本文テキスト、一時保存用
	
	public  $aryBody = array(); //マルチパート配列。
	//$aryBody[] の中に、['body'](string) およびヘッダーが格納される
	
	//デフォルトエンコーディング、setDefaultEncodingで上書き、
	//メールヘッダ解析時にさらに上書きされ、本文のデコーディングに使われる
	private $defaultEncoding = "iso-2022-jp";
	
	public  $timeStamp = 0; //メール受信タイムスタンプ
	
	/**
	 * コンストラクタ
	 */
	function __construct(){
		$this->debug(__METHOD__);
	}
	
	/**
	 * デストラクタ
	 */
	function __destruct(){
		
	}
	
	/**
	 * デバッグ書き込み
	 */
	private function debug($method,$message = ""){
		$this->debugMessage .= "[".$method."] ".$message."\n";
	}
	
	/**
	 * デバッグ表示
	 */
	public function printDebugMessage(){
		echo "<pre>".htmlspecialchars($this->debugMessage)."</pre>";
	}
	
	/**
	 * メールソースをパースする
	 */
	public function parse($mailSource){
		
		$this->debug(__METHOD__);
		
		$mailSource = str_replace("\r","",$mailSource);
		$mailLines = explode("\n",$mailSource);
		$is_body = false;
		$multipartCounter = -1; //マルチパートの場合の配列インデックス
		$multipart_is_body = false; //マルチパートの場合の本文かどうか判定フラグ
		
		//$ContentTransferEncoding = "";//Content-Transfer-Encodingヘッダを格納する
		
		/*
		$aryRegMultipartHeader = array(
			'filename'                  => "/name=\"{0,1}([^\"]+)\"{0,1}/i",
			'Content-Type'              => "/Content-Type:\s*([^;\s]+)/i",
			'Content-Transfer-Encoding' => "/Content-Transfer-Encoding:\s*(\S+)/i",
			'Content-Disposition'       => "/Content-Disposition:\s*([^;\s]+)/i",
		);
		*/
		
		foreach($mailLines as $i => $line){
			if($is_body){ //本文読み込み
				
				if($this->is_multipart && $this->boundary){
					if(strstr($line,"--".$this->boundary)){ //マルチパート区切りなら
						
						if($multipartCounter != -1){
							//プレーンテキストのマルチパートは、本文に追加する
							//$this->aryBody[$multipartCounter]['body']
							if($this->aryBody[$multipartCounter]['content-type'] == "text/plain"){
								//プレインテキストなら、表示用本文に追加
								
								//ファイル名をチェック。
								if($this->aryBody[$multipartCounter]['filenameIsDefault']){
									//ファイル名が存在しない : ベタ書きされたメール本文。ヘッダーの文字エンコーディングを使ってデコード
									$a = $this->mailBodyDecode(
										$this->aryBody[$multipartCounter]['body'],
										$this->aryBody[$multipartCounter]['encoding'],
										$this->aryBody[$multipartCounter]['aryHeader']['content-transfer-encoding']
									);
								
								}else{
									//ファイル名が存在する : 元はテキストファイル。
									//20090507 ヘッダの文字エンコーディングは信用せずに、エンコーディングの自動検出を試みる
									$a = $this->mailBodyDecode(
										$this->aryBody[$multipartCounter]['body'],
										"auto",
										$this->aryBody[$multipartCounter]['aryHeader']['content-transfer-encoding']
									);
									
									//ついでに本文にファイル名を追加
									$this->strBody .= "[ ".$this->aryBody[$multipartCounter]['filename']." ]\n";
								}
								
								$this->strBody .= $a."\n";
							
							}else if($this->aryBody[$multipartCounter]['content-type'] == "multipart/alternative"){
								//さらにマルチパートなら再帰的に解析
								$mailParser = new MailParser();
								$mailParser->parse($this->aryBody[$multipartCounter]['body']);
								$this->strBody .= $mailParser->strBody;
							
							}
						}
						
						if(strstr($line,"--".$this->boundary."--")){
							//マルチパート終了ならループを抜ける
							//print_r($this->aryBody);die();//DEBUG
							break;
						}
						
						$multipartCounter++;
						$this->aryBody[$multipartCounter] = array();
						$this->aryBody[$multipartCounter]['body'] = ''; 
						$this->aryBody[$multipartCounter]['aryHeaderBuffer'] = array();
						$this->aryBody[$multipartCounter]['aryHeader'] = array();
						//$this->aryBody[$multipartCounter]['isPlainText'] = false;
						$this->aryBody[$multipartCounter]['encoding'] = "";
						$this->aryBody[$multipartCounter]['filename'] = "multipart-".$multipartCounter;
						$this->aryBody[$multipartCounter]['filenameIsDefault'] = true;
						$this->aryBody[$multipartCounter]['content-type'] = "";
						//['aryHeader']['content-type'] には ヘッダのすべてが、
						//['content-type']には短い形式のコンテントタイプ(例:image/jpeg のみ)が入る。
						//lower-case になる。
						
						//$this->aryBody[$multipartCounter]['Content-Type'] = ''; 
						$multipart_is_body = false;
						
					}else if($multipartCounter < 0){
						//マルチパートメールの場合、boundaryが出てくるまでループ
						continue;
						
					}else if($multipart_is_body){
						//本文の場合 本文変数に格納
						$this->aryBody[$multipartCounter]['body'] .= $line."\n";
						
						
						/*
						if(isset($this->aryBody[$multipartCounter]['Content-Transfer-Encoding']) &&
							$this->aryBody[$multipartCounter]['Content-Transfer-Encoding']){
							$this->aryBody[$multipartCounter]['body'] .=
								$this->mailBodyDecode($line,$this->aryBody[$multipartCounter]['Content-Transfer-Encoding']) ."\n";
						}else{
							$this->aryBody[$multipartCounter]['body'] .= $this->mailBodyDecode($line,$this->endoding) ."\n";
						}
						*/
						
					}else if(trim($line)==""){
						//空行の場合、以降を本文とみなす
						$multipart_is_body = true;
						$this->parseMultipartHeaderArray($multipartCounter);//ヘッダーバッファに追加したヘッダを解析する
					}else{
						//ヘッダの場合
						//バッファに追加していく
						$line = mb_decode_mimeheader($line);
						if(preg_match("/^[\w\-]+\:\s/",$line)){
							//インデントが無く、コロンが含まれる行の場合は新規ヘッダ行
							$this->aryBody[$multipartCounter]['aryHeaderBuffer'][] = trim($line);
						}else if(preg_match("/^\s/",$line)){
							//インデント行の場合は前の行と結合
							$this->aryBody[$multipartCounter]['aryHeaderBuffer'][count($this->aryBody[$multipartCounter]['aryHeaderBuffer'])-1] .= trim($line);
						}else{
							$this->debug(__METHOD__,"意味不明なヘッダ: ".$line);
						}
						/*
						foreach($aryRegMultipartHeader as $keyAryRegMultipartHeader => $valueAryRegMultipartHeader){
							if(preg_match($valueAryRegMultipartHeader,$line,$aryResult)){
								$this->aryBody[$multipartCounter][$keyAryRegMultipartHeader] = $aryResult[1];
							}
						}
						*/
					}
					
				}else{
					//本文変数に格納 デコードする
					//$a = $this->mailBodyDecode($line,$this->defaultEncoding,$this->aryHeader['content-transfer-encoding']);
					//$this->strBody .= $a."\n";
					$this->strBodyTemp .= $line."\n";
					
				}
				
			}else{ //ヘッダー読み込み
				if(!$line){ //空行の場合、以降を本文とみなす
					$is_body = true;
					
					$this->parseHeaderArray();//ヘッダーバッファに追加したヘッダを解析する
					
					continue;
				}
				
				$this->strHeader.=$line."\n";
				
				//ヘッダーバッファ配列に追加していく
				$line = mb_decode_mimeheader($line);
				if(preg_match("/^[\w\-]+\:\s/",$line)){
					//インデントが無く、コロンが含まれる行の場合は新規ヘッダ行
					$this->aryHeaderBuffer[] = $line;
				}else if(preg_match("/^\s/",$line)){
					//インデント行の場合は前の行と結合
					$this->aryHeaderBuffer[count($this->aryHeaderBuffer)-1] .= trim($line);
				}else{
					$this->debug(__METHOD__,"意味不明なヘッダ: ".$line);
				}
				
			}
		}
		if($this->strBodyTemp){
			$a = $this->mailBodyDecode($this->strBodyTemp,$this->defaultEncoding,
				$this->aryHeader['content-transfer-encoding']);
			$this->strBodyTemp = "";
			$this->strBody = $a ."\n".$this->strBody;
		}
		$this->strBody = trim($this->strBody)."\n";
		$this->debug(__METHOD__,"end.");
	}
	
	
	/**
	 * ヘッダーバッファ配列を解析
	 */
	private function parseHeaderArray(){
		
		$this->debug(__METHOD__);
		
		foreach($this->aryHeaderBuffer as $line){
			//echo $line."<br />";
			list($key,$value) = explode(":",$line,2);
			$key = trim($key);
			$key = strtolower($key);
			$value = trim($value);
			$this->debug(__METHOD__,"key=".$key.",value=".$value);
			if(isset($this->aryHeader[$key])){
				$this->debug(__METHOD__,"ヘッダ重複は無視 ".$line);
			}else{
				$this->aryHeader[$key]=$value; //ヘッダ連想配列に格納
			}
		}
		
		//content-typeを調査
		if(isset($this->aryHeader['content-type'])){
			if(preg_match("/(multipart\/\w*);\s*boundary=[\"\']?([^\"\'\s]+)[\"\']?/i",$this->aryHeader['content-type'],$aryResult)){
				//マルチパートメールなら
				$this->contentType = $aryResult[1];
				$this->is_multipart = true;
				$this->boundary = $aryResult[2];
				$this->debug(__METHOD__,"マルチパートメール。 boundary=".$this->boundary);
			#}else if(preg_match("/charset=\"?(.*)\"?/i",$this->aryHeader["content-type"],$aryResult)){
			}else if(preg_match(self::REGEX_CONTENT_TYPE,$this->aryHeader["content-type"],$aryResult)){
				//エンコーディングを取得
				$this->defaultEncoding=trim($aryResult[1]);
				$this->debug(__METHOD__,"エンコーディング ".$this->defaultEncoding);
			}
		}
		
		//メール受信日時
		$timeStamp=0;
		if(isset($this->aryHeader['date'])){
			$timeStamp = strtotime($this->aryHeader['date']);
			if($timeStamp){
				$this->debug(__METHOD__,"Dateヘッダより時刻パースに成功。".date("Y-m-d H:i:s",$timeStamp));
			}
		}
		if(!$timeStamp){
			if(isset($this->aryHeader['received'])){
				$a = explode(";",$this->aryHeader['received']);
				$timeStamp = strtotime($a[count($a)-1]);
				if($timeStamp){
					$this->debug(__METHOD__,"Recievedヘッダより時刻パースに成功。".date("Y-m-d H:i:s",$timeStamp));
				}
			}
		}
		if(!$timeStamp){
			$this->debug(__METHOD__,"時刻パースに失敗。メール受信日は現在時刻とする。");
			$timeStamp = time();
		}
		$this->timeStamp = $timeStamp;
		
		//content-transfer-encoding
		if(isset($this->aryHeader['content-transfer-encoding'])){
			//content-transfer-encoding が存在する。問題なし
		}else{
			$this->debug(__METHOD__,"content-transfer-encodingヘッダがありません。");
			$this->aryHeader['content-transfer-encoding'] = "";
		}
		
		//print_r($this->aryHeader);
		
	}
	
	/**
	 * マルチパートヘッダバッファ配列を解析
	 */
	private function parseMultipartHeaderArray($multipartCounter){
		$this->debug(__METHOD__,"multipartCounter=".$multipartCounter);
		foreach($this->aryBody[$multipartCounter]['aryHeaderBuffer'] as $line){
			list($key,$value) = explode(":",$line,2);
			$key = trim($key);
			$key = strtolower($key);
			$value = trim($value);
			if(isset($this->aryBody[$multipartCounter]['aryHeader'][$key])){
				$this->debug(__METHOD__,"ヘッダ重複は無視 ".$line);
			}else{
				$this->aryBody[$multipartCounter]['aryHeader'][$key]=$value; //ヘッダ連想配列に格納
			}
		}
		
		//content-typeを調査
		if(isset($this->aryBody[$multipartCounter]['aryHeader']['content-type'])){
			$this->debug(__METHOD__,"Content-Typeヘッダを解析 ".$this->aryBody[$multipartCounter]['aryHeader']['content-type']);
			
			if(preg_match("/^([\w\/]+)/i",$this->aryBody[$multipartCounter]['aryHeader']['content-type'],$aryResult)){
				//content-typeを取得
				$this->aryBody[$multipartCounter]['content-type'] = strtolower($aryResult[1]);
				$this->debug(__METHOD__,"マルチパート(".$multipartCounter."):content-type=".$this->aryBody[$multipartCounter]['content-type']);
			}
			
			/*
			if(preg_match("/^text\/plain/i",$this->aryBody[$multipartCounter]['aryHeader']['content-type'])){
				$this->debug(__METHOD__,"マルチパート(".$multipartCounter.") プレーンテキストを検出");
				$this->aryBody[$multipartCounter]['isPlainText'] = true;
			}
			*/
			
			//デフォルトファイル名に拡張子を付与
			if($this->aryBody[$multipartCounter]['content-type']=="text/plain"){
				$this->aryBody[$multipartCounter]['filename'].=".txt";
			}
			if($this->aryBody[$multipartCounter]['content-type']=="text/html"){
				$this->aryBody[$multipartCounter]['filename'].=".html";
			}
			
			if(preg_match(self::REGEX_CONTENT_TYPE,$this->aryBody[$multipartCounter]['aryHeader']['content-type'],$aryResult)){
				//エンコーディングを取得
				$this->aryBody[$multipartCounter]['encoding']=$aryResult[1];
				$this->debug(__METHOD__,
					"マルチパート(".$multipartCounter."):エンコーディング=".
					$this->aryBody[$multipartCounter]['encoding']
				);
			}
			
			if(preg_match("/name=\"?([^\"]+)\"?/i",$this->aryBody[$multipartCounter]['aryHeader']['content-type'],$aryResult)){
				//ファイル名を取得
				$this->aryBody[$multipartCounter]['filename']=trim($aryResult[1]);
				$this->aryBody[$multipartCounter]['filename']=str_replace("\t","",$this->aryBody[$multipartCounter]['filename']);
				$this->debug(__METHOD__,
					"マルチパート(".$multipartCounter."):ファイル名=".
					$this->aryBody[$multipartCounter]['filename']
				);
				$this->aryBody[$multipartCounter]['filenameIsDefault'] = false;
			}
		}
		
		//content-transfer-encoding
		if(isset($this->aryBody[$multipartCounter]['aryHeader']['content-transfer-encoding'])){
			//content-transfer-encoding が存在する。
		}else{
			$this->debug(__METHOD__,"マルチパート(".$multipartCounter.") content-transfer-encodingヘッダがありません。");
			$this->aryBody[$multipartCounter]['aryHeader']['content-transfer-encoding'] = "";
		}
		
		//content-dispositionからファイル名を取得。
		if(isset($this->aryBody[$multipartCounter]['aryHeader']['content-disposition'])){
			$this->debug(__METHOD__,"Content-Dispositionヘッダを解析 ".$this->aryBody[$multipartCounter]['aryHeader']['content-disposition']);
			if(preg_match("/filename\*?=\"?([^\"]+)\"?/i",$this->aryBody[$multipartCounter]['aryHeader']['content-disposition'],$aryResult)){
				//ファイル名を取得 content-typeのファイル名を上書きする
				$this->aryBody[$multipartCounter]['filename']=trim(rawurldecode($aryResult[1]));
				$this->aryBody[$multipartCounter]['filename']=str_replace("\t","",$this->aryBody[$multipartCounter]['filename']);
				$this->debug(__METHOD__,
					"マルチパート(".$multipartCounter."):ファイル名=".
					$this->aryBody[$multipartCounter]['filename']
				);
			}else if(preg_match("/filename\*\d+\*=([^\']+)\'/i",$this->aryBody[$multipartCounter]['aryHeader']['content-disposition'],$aryResult)){
				//分割ファイル名の場合
				//例:  inline; filename*0*=UTF-8''%E6%97%A5%E6%9C%AC%E8%AA%9E%20%73%70%61%63%65%20%66%69; filename*1*=%6C%65%20%6E%61%6D%65%2E%6A%70%67
				$e = $aryResult[1];
				$a = $this->aryBody[$multipartCounter]['aryHeader']['content-disposition'];
				$a = preg_replace('/inline\;/i',"",$a);
				$a = preg_replace('/filename\*\d+\*=[^\']+\'\'/i',"",$a);
				$a = preg_replace('/filename\*\d+\*=/i',"",$a);
				$a = preg_replace('/\s/',"",$a);
				$a = str_replace(";","",$a);
				$this->debug(__METHOD__,"content-dispositionからファイル名を取得(1): ".$a);
				$a = rawurldecode($a);
				$a = mb_convert_encoding($a,mb_internal_encoding(),$e);
				$this->debug(__METHOD__,"content-dispositionからファイル名を取得(2): ".$a);
				if($a){
					$this->aryBody[$multipartCounter]['filename']=$a;
					$this->aryBody[$multipartCounter]['filename']=str_replace("\t","",$this->aryBody[$multipartCounter]['filename']);
				}
			}
			$this->aryBody[$multipartCounter]['filenameIsDefault'] = false;
		}
	}
	
	/**
	 * デフォルトエンコーディングを上書き
	 */
	public function setDefaultEncoding($encoding){
		$this->debug(__METHOD__,"encoding=".$encoding);
		$this->defaultEncoding=$encoding;
	}
	
	/**
	 * メール本文をデコードする
	 */
	private function mailBodyDecode($str,$charSet,$transferEncoding){
		// $charSet          : iso-2022-jp, utf-8 など
		// $transferEncoding : 7bit ,quoted-printable など
		
		$this->debug(__METHOD__,"charSet=".$charSet.",transferEncoding=".$transferEncoding);
		
		$transferEncoding = strtolower($transferEncoding);
		
		if($transferEncoding == "quoted-printable"){
			//$this->debug(__METHOD__,"quoted-printable mode.");
			$str = imap_qprint($str);
			//$str = mb_convert_encoding($str,mb_internal_encoding(),$charSet);
			////文字コードの自動判別を試みる。化けても、添付ファイルをダウンロードすれば良い話なので、気にしない。
			//$str = mb_convert_encoding($str,mb_internal_encoding());
			//return $str;
		}else if($transferEncoding == "base64"){
			$str = base64_decode($str);
			//$str = mb_convert_encoding($str,mb_internal_encoding(),$charSet);
			////文字コードの自動判別を試みる。化けても、添付ファイルをダウンロードすれば良い話なので、気にしない。
			//$str = mb_convert_encoding($str,mb_internal_encoding());
			//return $str;
		}
		
		if($charSet == "auto"){
			//自動判定を試みる
			$encoding = mb_detect_encoding($str,"SJIS-win,SJIS,eucJP-win,UTF-8,JIS,ASCII");
			//$this->debug(__METHOD__,"encoding=".$encoding);
			//die($encoding);
			//$str = mb_convert_encoding($str,mb_internal_encoding(),$charSet);
			$str = mb_convert_encoding($str,mb_internal_encoding(),$encoding);
		}else if($charSet){
			$str = mb_convert_encoding($str,mb_internal_encoding(),$charSet);
		}else{
			$str = mb_convert_encoding($str,mb_internal_encoding());
		}
		return $str;
		
	}
	
	/**
	 * 本文を取得
	 */
	public function getBodyText(){
		return $this->strBody;
	}
	
	/**
	 * ヘッダー値を取得
	 */
	public function getHeader($key){
		$this->debug(__METHOD__,"key=".$key);
		$key = strtolower($key);
		if(isset($this->aryHeader[$key])){
			return $this->aryHeader[$key];
		}else{
			$this->debug(__METHOD__,"ヘッダーが存在しません。");
			return "";
		}
	}
	
	/**
	 * 全ヘッダを文字列で取得
	 */
	public function getHeaderFull(){
		return $this->strHeader;
	}
	
	/**
	 * 受信日時タイムスタンプを取得
	 */
	public function getTimeStamp(){
		return $this->timeStamp;
	}	
	
	
	/**
	 * マルチパートメールか否か
	 */
	public function isMultiPart(){
		return $this->is_multipart;
	}
	
	/**
	 * マルチパートメールのファイルバイナリを取得
	 */
	public function getMultipartFileBinary($partIndex){
		$this->debug(__METHOD__,"partIndex=".$partIndex);
		if($this->is_multipart && isset($this->aryBody[$partIndex])){
			
			$b = $this->aryBody[$partIndex]['body'];
			
			if(strtolower($this->aryBody[$partIndex]['aryHeader']['content-transfer-encoding']) == "base64"){
				//BASE64でエンコードされているのなら、デコードする
				$b = base64_decode($b);
			}
			
			if(strtolower($this->aryBody[$partIndex]['aryHeader']['content-transfer-encoding']) == "quoted-printable"){
				//quoted-printableでエンコードされているのなら、デコードする
				//こんなケースあるのだろうか。
				$b = imap_qprint($b);
			}
			return $b;
			
			/*
			結局、コンテントタイプは何であろうと関係ない。
			if(preg_match("/^text\//i",$this->aryBody[$partIndex]['content-type'])){
				//テキスト形式ならそのまま返す
				//TODO: エンコーディング変換は不要か?
				return $b;
			}
			
			$this->debug(__METHOD__,"エンコーディング形式が不明のため、そのまま返します。");
			return $b;
			*/
		}
	}
	
	/**
	 * Message-Idを取得
	 */
	public function getMessageId(){
		$messageId = $this->getHeader('message-id');
		if(!$messageId) $messageId = $this->getHeader('message_id');
		return $this->trimMessageId($messageId);
	}
	
	/**
	 * Referencesを取得
	 */
	public function getReferences(){
		return $this->trimMessageId($this->getHeader('references'));
		
	}
	
	/**
	 * Message-Idをトリム
	 * 複数のMessage-IdはTSVにする
	 */
	public function trimMessageId($str){
		$str = preg_replace("/['\"]?>[\s,]*<['\"]?/","\t",$str);
		$str =  trim($str," <>\"\';:");
		return $str;
	}
	
}


?>
