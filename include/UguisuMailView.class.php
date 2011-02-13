<?php
/*
メール表示クラス。

static
*/

class UguisuMailView{
	
	private static $CONFIG  = array();
	//public  static $accountRecord = array();
	
	private static $dbh;
	
	private static $accountDirectory = "";
	
	private static $mailRecord = array();
	
	private static $isArchive = false; //読み込み対象がアーカイブデータかどうか
	
	private static $maildataTable = "";
	
	/**
	 * 初期化
	 */
	public static function initialize($account,$mail_id,$isArchive=false){
		Logger::debug(__METHOD__,"account=".$account.",mail_id=".$mail_id);
		
		global $CONFIG;
		//global $ACCOUNT;
		
		self::$CONFIG  = &$CONFIG;
		//self::$accountRecord = $ACCOUNT[$account];
		
		self::$isArchive=$isArchive;
		
		self::$accountDirectory = self::$CONFIG['dataDirectory']."/".$account;
		
		if(self::$isArchive){
			//アーカイブモードの場合
			self::$dbh = new PDO(
				self::$CONFIG['pdoDriver'].":".self::$accountDirectory."/".self::$CONFIG['mailDbArchve'],
				self::$CONFIG['pdoUser'],
				self::$CONFIG['pdoPassword']
			);
			self::$maildataTable = "maildata_archive";
		}else{
			//通常
			self::$dbh = new PDO(
				self::$CONFIG['pdoDriver'].":".self::$accountDirectory."/".self::$CONFIG['mailDatabase'],
				self::$CONFIG['pdoUser'],
				self::$CONFIG['pdoPassword']
			);
			self::$maildataTable = "maildata";
		}
		self::$dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_WARNING);
		
		self::readMailRecord($mail_id);
		
		self::setReaded();
		
	}
	
	
	/**
	 * メールレコードを読み込み
	 * initialize()から呼ばれる
	 */
	private static function readMailRecord($mail_id){
		Logger::debug(__METHOD__,"mail_id=".$mail_id);
		
		$sql = "SELECT * FROM ".self::$maildataTable." WHERE mail_id = :mail_id";
		$prepare = self::$dbh->prepare($sql);
		$prepare->execute(array(':mail_id'=>$mail_id));
		
		$result = $prepare->fetch();
		
		if(!count($result)) exit("[ERROR] No mail.");
		
		self::$mailRecord = $result;
		
	}
	
	/**
	 * メールレコードを既読にする
	 */
	private static function setReaded(){
		Logger::debug(__METHOD__);
		$sql = "UPDATE ".self::$maildataTable." SET readed = 1 WHERE mail_id = :mail_id";
		$prepare = self::$dbh->prepare($sql);
		$prepare->execute(array(':mail_id'=>self::$mailRecord['mail_id']));
		
		if(self::$mailRecord['readed'] == 0){
			UguisuAccount::setMailboxStatus('updateCountRequire',1);
		}
	}
	
	/**
	 * 任意フィールドを取得
	 */
	public static function get($key){
		Logger::debug(__METHOD__);
		return self::$mailRecord[$key];
	}
	
	/**
	 * 自動リンク
	 */
	public static function autoLink($text){
		// Deplicated
		//$text = ereg_replace(
		//	"(https?|ftp)(://[[:alnum:]\+\$\;\?\.%,!#~*/:@&=_-]+)",
		//	"<a class=\"autolink\" href=\"\\1\\2\" target=\"_blank\">\\1\\2</a>",
		//	$text
		//);
		$result = preg_replace(
			"/(https?|ftp)(\:\/\/[\w\+\$\;\?\.\%\,\!\#\~\*\/\:\@\&\=\_\-]+)/",
			"<a class=\"autolink\" href=\"\\1\\2\" target=\"_blank\">\\1\\2</a>",
			$text
		);
		return $result;
	}
	
	
	/**
	 * コンマの後にスペースを入れる。
	 * Ccが長い場合にFirefoxで自動折り返しが有効とならない場合があるので、その回避策。
	 * TODO:スペースの重複追加を判断できるようにするべきか
	 */
	public static function commaAddSpace($str){
		return str_replace(",",", ",$str);
	}
}
?>