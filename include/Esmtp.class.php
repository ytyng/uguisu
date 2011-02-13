<?PHP
/*
smtp class.
ytyng.com 2008

Beta!
for Japan.

SMTPサーバに接続し、メールを送信する。
DIGEST-MD5 / CRAM-MD5 開発中です。

20090225
ESMTPではなく、SMTPで認証なし送信を可能とした。

20090331
RCPT TO する際、<>をtrimするようにした。

20090331
addCrForBareLf()を追加。改行コードをCRLFに統一する。

20090401
拡張ヘッダは、シングルバイト以外をエンコードする

20090501
addCrForBareLf()をドロップ。
本文は1行づつwriteするようにした。

*/

class Esmtp{
	
	private $server;   //サーバURL。コンストラクト時に作成
	private $port;     //ポート番号。コンストラクト時に作成
	private $user;     //ユーザーID。auth()で使う
	private $pass;     //パスワード
	private $from;     //fromアドレス
	
	private $mb_encodetype = "ISO-2022-JP";
	
	public $debug = false;
	public $log = "";
	
	private $fp; //file pointer
	
	//認証タイプ
	//NOLOG:ログインしない。EHLO ではなく HELO を使う。
	private $authtype; //認証タイプ
	
	//インスタンスのステータス。
	//  0:コンストラクト
	// 10:EHLO/HELO 送出
	// 20:ログイン
	// 80:ログアウト完了
	private $status; 
	
	/**
	 * コンストラクタ
	 */
	function __construct($from,$server,$port=25){
		$this->from = $from;
		$this->server = $server;
		$this->port = $port;
		$this->fp = fsockopen($server,$port);
		$this->sRead($this->fp);
		$this->status = 0;
	}
	
	/**
	 * デストラクタ
	 */
	function __destruct(){
		if($this->status < 80){
			//ログアウトしてない場合はログアウト
			$this->quit();
		}
	}
	
	
	/**
	 * SMTPサーバに EHLO する
	 */
	private function ehlo(){
		if(!isset($_SERVER['REMOTE_ADDR'])) $_SERVER['REMOTE_ADDR'] = "127.0.0.1";
		
		$this->sWrite($this->fp,"EHLO [".$_SERVER['REMOTE_ADDR']."]\r\n");
		$result = $this->sReadFinalLine($this->fp);
		
		if(substr($result,0,4)!='250 '){
			if(!isset($_SERVER['SERVER_NAME'])) $_SERVER['SERVER_NAME'] = "example.com";
			$this->sWrite($this->fp,"EHLO ".$_SERVER['SERVER_NAME']."\r\n");
			$result = $this->sReadFinalLine($this->fp);
		}
		if(substr($result,0,4)!='250 '){
			die("Illigal status.".$result."<HR /><PRE>\n".$this->log."</HR>");
		}
		
		$this->status = 10;
		
	}
	
	/**
	 * SMTPサーバに HELO する
	 */
	private function helo(){
		if(!isset($_SERVER['REMOTE_ADDR'])) $_SERVER['REMOTE_ADDR'] = "127.0.0.1";
		
		$this->sWrite($this->fp,"HELO [".$_SERVER['REMOTE_ADDR']."]\r\n");
		$result = $this->sReadFinalLine($this->fp);
		
		if(substr($result,0,4)!='250 '){
			if(!isset($_SERVER['SERVER_NAME'])) $_SERVER['SERVER_NAME'] = "example.com";
			$this->sWrite($this->fp,"HELO ".$_SERVER['SERVER_NAME']."\r\n");
			$result = $this->sReadFinalLine($this->fp);
		}
		if(substr($result,0,4)!='250 '){
			die("Illigal status.".$result."<HR /><PRE>\n".$this->log."</HR>");
		}
		
		$this->status = 10;
		
	}
	
	/**
	 * ユーザー認証を行う。
	 * ただし、第1引数がNOLOGやデフォルト値の場合は、
	 * ESMTPの認証を行わず、認証なしSMTPでログイン(HELO)する。
	 */
	public function auth($authtype="NOLOG",$user="",$pass=""){
		$this->authtype = $authtype;
		$this->user = $user;
		$this->pass = $pass;
		switch(strtoupper($authtype)){
		case "NOLOG":
		case "NOAUTH":
		case "":
			$this->helo();
			break;
		case "LOGIN":
			$this->ehlo();
			$this->sWrite($this->fp,"AUTH LOGIN\r\n");
			$this->sRead($this->fp);
			$this->sWrite($this->fp,base64_encode($user)."\r\n");
			$this->sRead($this->fp);
			$this->sWrite($this->fp,base64_encode($pass)."\r\n");
			$result = $this->sReadFinalLine($this->fp);
			if(substr($result,0,4)!='235 '){
				die("Login failed.".$result."<HR /><PRE>\n".$this->log."</PRE>");
			}
			break;
		case "DIGEST-MD5":
			$this->ehlo();
			$this->sWrite($this->fp,"AUTH DIGEST-MD5\r\n");
			$aryServerMessage = array();
			
			$aryServerMessage['responce'] = $this->sRead($this->fp);
			preg_match('/3\d\d\s+(\S+)/',$aryServerMessage['responce'],$regResult);
			
			$aryServerMessage['full'] = base64_decode($regResult[1]);
			preg_match('/nonce=\"([^\"]+)\"/',$aryServerMessage['full'],$regResult);
			$aryServerMessage['nonce'] = $regResult[1];
			preg_match('/realm=\"([^\"]+)\"/',$aryServerMessage['full'],$regResult);
			$aryServerMessage['realm'] = $regResult[1];
			preg_match('/qop=\"([^\"]+)\"/',$aryServerMessage['full'],$regResult);
			$aryServerMessage['qop'] = $regResult[1];
			preg_match('/charset=([^\"\,]+)/',$aryServerMessage['full'],$regResult);
			$aryServerMessage['charset'] = $regResult[1];
			preg_match('/algorithm=([^\"\,]+)/',$aryServerMessage['full'],$regResult);
			$aryServerMessage['algorithm'] = $regResult[1];
			
			$aryServerMessage['nc'] = "00000001";
			$aryServerMessage['cnonce'] = "e79e26e0d17c978d";
			
			$aryServerMessage['a1'] = $user.":".$aryServerMessage['realm'].":".$pass;
			$aryServerMessage['a1_hash'] = md5($aryServerMessage['a1']);
			
			$aryServerMessage['a2'] = "";
			
			$this->sWrite($this->fp,$user."\r\n");
			$this->sRead($this->fp);
			break;
		
		
		case "CRAM-MD5":
			$this->ehlo();
			$this->sWrite($this->fp,"AUTH CRAM-MD5\r\n");
			$aryServerMessage = array();
			
			$aryServerMessage['server_responce'] = $this->sRead($this->fp);
			preg_match('/3\d\d\s+(\S+)/',$aryServerMessage['server_responce'],$regResult);
			
			$aryServerMessage['challenge'] = base64_decode($regResult[1]);
			
			$aryServerMessage['digest'] = $this->hmac_md5($aryServerMessage['challenge'],$pass);
			$aryServerMessage['responce'] = base64_encode($user ." ". $aryServerMessage['digest']);
			
			$this->sWrite($this->fp,$aryServerMessage['responce']."\r\n");
			$this->sRead($this->fp);
			break;
			
		default:
			die("[DIE] Esmtp->auth Illigal auth type.");
			break;
		}
	}
	
	/**
	 * メールを送信する
	 */
	public function send($rcpt_to,$subject,$body,$ex_header=""){
		
		//HELOしてない場合(auth()を行っていない場合)は、HELOする。
		if($this->status < 10){
			$this->helo();
		}
		
		$this->sWrite($this->fp,"MAIL FROM:<".$this->getRealAddr($this->from).">\r\n");
		$this->sRead($this->fp);
		if(!is_array($rcpt_to)){
			$rcpt_to = explode(",",$rcpt_to);
		}
		foreach($rcpt_to as $cell){
			$cell = trim($cell);
			$cell = trim($cell,"<>"); //20090331
			if(!$cell) continue;
			$this->sWrite($this->fp,"RCPT TO:<".$this->getRealAddr($cell).">\r\n");
			$this->sRead($this->fp);
		}
		
		$this->sWrite($this->fp,"DATA\r\n");
		$result = $this->sReadFinalLine($this->fp);
		
		if(substr($result,0,4)!='354 '){
			die("Illigal status.".$result."<HR /><PRE>\n".$this->log."</HR>");
		}
		
		$this->sWrite($this->fp,"Subject: ".$subject."\r\n");
		if($ex_header){
			//$ex_header = $this->addCrForBareLf($ex_header);
			$ex_header = str_replace("\r","",$ex_header);
			$aryExHeader = explode("\n",$ex_header);
			foreach($aryExHeader as $cellAryExHeader){
				$cellAryExHeader=trim($cellAryExHeader);
				if(!$cellAryExHeader) continue;
				$this->sWrite($this->fp,$cellAryExHeader."\r\n");
			}
		}
		
		//$body = $this->addCrForBareLf($body);
		$this->sWrite($this->fp,"\r\n");
		
		$body = str_replace("\r","",$body);
		$aryBody = explode("\n",$body);
		
		//1行づつ本文を流し込む
		foreach($aryBody as $lineBody){
			if($lineBody == ".") $lineBody = "";
			$this->sWrite($this->fp,$lineBody."\r\n");
		}
		
		$this->sWrite($this->fp,".\r\n");
		$result = $this->sReadFinalLine($this->fp);
		$this->log .= "[Result] ".$result;
		if(substr($result,0,4)=='250 '){
			return true;
		}else{
			return false;
		}
	}
	
	
	
	/**
	 * マルチバイトをエンコードして、メールを送信する
	 */
	public function mb_send($rcpt_to,$subject,$body,$ex_header=""){
		$aryExHeader = explode("\r\n",$ex_header);
		$outExHeader = "";
		$this->log .= "----- Start mb_send() -----\n";
		foreach($aryExHeader as $value){
			if(!$value) continue;
			//if(preg_match("/^[\w\d\s\,\"<>\@\.\:\-\/\;\=\!\?]+\$/",$value)){ }
			/*
			if(preg_match("/^[\s\!-\~]+\$/",$value)){
				$outExHeader .= $value."\r\n";
				$this->log .= $value." ... [mb nohit]\n";
			}else{
				if(preg_match("/^(\w+:\s*)(.*)\$/",$value,$aryResult)){
					//TODO:実メールアドレスまでMIMEらない →複数アドレスの分割が難しい
					$outExHeader .= $aryResult[1]." ".mb_encode_mimeheader($aryResult[2],$this->mb_encodetype)."\r\n";
					$this->log .= $value." ... [mb hit1]\n";
				}else{
					$outExHeader .= mb_encode_mimeheader($value,$this->mb_encodetype)."\r\n";
					$this->log .= $value." ... [mb hit2]\n";
				}
			}
			*/
			//シングルバイト以外をMIMEエンコード 20090401
			//$outExHeader .= preg_replace_callback("/([^\w\s\,\"<>\@\.\:\-\/\;\=\!\?]+)/",array($this, "encodeMimeheader"),$value)."\r\n";
			//$outExHeader .= preg_replace_callback("/[^\w\s\,\"<>\@\.\:\-\/\;\=\!\?]+/",array($this, "encodeMimeheader"),$value)."\r\n";
			$outExHeader .= preg_replace_callback("/[^\x20-\x7e]+/",array($this, "encodeMimeheader"),$value)."\r\n";
			//$outExHeader .= preg_replace_callback("/([^\s\S]+)/",array($this, "encodeMimeheader"),$value)."\r\n";
		}
		$this->log .= $outExHeader;
		$this->log .= "----- End mb_send() -----\n";
		//die($outExHeader); //debug
		return $this->send(
			$rcpt_to,
			mb_encode_mimeheader($subject,$this->mb_encodetype),
			mb_convert_encoding($body,$this->mb_encodetype,mb_internal_encoding()),
			$outExHeader
		);
	}
	
	/**
	 * コールバック用、mimeエンコード
	 */
	private function encodeMimeheader($aryReg){
		if(isset($aryReg[0]) && $aryReg[0]){
			return mb_encode_mimeheader($aryReg[0],$this->mb_encodetype);
		}else{
			return ;
		}
	}
	
	/**
	 * ログアウト
	 */
	public function quit(){
		$this->sWrite($this->fp,"QUIT\r\n");
		$this->sRead($this->fp);
		fclose($this->fp);
		$this->status = 80;
	}
	
	/**
	 * ソケットから読み込み
	 */
	private function sRead($fp){
		$response = fread($fp,4096);
		//$response .= fgets($fp);
		if($this->debug) echo nl2br($response);
		$this->log .= $response;
		return $response;
	}
	
	/**
	 * ソケットから1行まるごと読み込み
	 */
	private function sReadLine($fp){
		$response = fgets($fp);
		if($this->debug) echo nl2br($response);
		$this->log .= $response;
		return $response;
	}
	
	/**
	 * ソケットから複数行読み込み
	 */
	private function sReadFinalLine($fp){
		for($i=0;$i<20;$i++){
			$response = $this->sReadLine($fp);
			if(substr($response,3,1)=='-') continue;
			break;
		}
		return $response;
	}
	
	/**
	 * ソケットに書き込み
	 */
	private function sWrite($fp,$strMessage){
		fwrite($fp,$strMessage);
		if($this->debug) echo "> ".nl2br($strMessage);
		$this->log .= "> ".$strMessage;
	}
	
	/**
	 * アドレスを解析する。< >など、無駄な記号を除去
	 */
	private function getRealAddr($str){
		$reg = "/([a-z0-9_\-\.\+]+@[a-z0-9_\-\.]+[a-z]{2,6})/i";
		if(preg_match($reg,$str,$aryResult)){
			return $aryResult[1];
		}else{
			die("error: getRealAddr : no Match Addr"); //debug
		}
	}
	
	/**
	 * ハッシュ作成
	 */
	public function hmac_md5($myChallenge,$myPasswd){
		$length = 64;
		$ipad = str_repeat(chr(0x36),$length);
		$opad = str_repeat(chr(0x5C),$length);
		$p = str_pad($myPasswd,$length,chr(0x00));
		$a = $p^$ipad;
		$b = $p^$opad;
		return md5($b.pack("H*",md5($a.$myChallenge)));
	}
	
	/**
	 * 改行コードをCRLFに統一する
	 */
	/* 20090501 Drop
	private function addCrForBareLf($str){
		$str = str_replace("\r","",$str);
		$str = str_replace("\r\n","\n",$str);
		return $str;
	}
	*/
}


?>
