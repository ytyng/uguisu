<?php

/*
Uguisu アカウントリスト
Staticに使う

[maildata]
seq_id       int autoincrement
mail_id      text
subject      text
h_from       text
h_from_real  text //正規化したfrom
h_from_fancy text //アドレス形式ではないfrom
h_to         text
h_to_real    text //正規化したto
h_cc         text
h_message_id text
h_references text
bodytext     text
header       text
attach       text 添付ファイル名をタブ区切りで格納、実際に保存される添付ファイルは、mail_idと連番で作成
etime        int
category     int 0:通常 10:保護 90:ごみ箱
100～ ユーザーフィルタ
readed       INTEGER 0:未読 1:既読

*/


class UguisuAccountList{
	
	private static $CONFIG  = array();
	public  static $ACCOUNT = array();
	
	private static $mailCountTable = array();
	
	/**
	 * 初期化
	 */
	public static function initialize(){
		global $CONFIG;
		global $ACCOUNT;
		
		Logger::debug(__METHOD__);
		
		self::$CONFIG  = &$CONFIG;
		self::$ACCOUNT = &$ACCOUNT;
		
		
		//アカウントディレクトリをチェック、なければ作成
		foreach(self::$ACCOUNT as $account => $record){
			if(isset($record['type']) && $record['type'] == 'imap'){
				continue;
			}
			if(!is_dir(self::$CONFIG['dataDirectory']."/".$account)){
				self::initializeAccount($account);
			}
		}
		
		//self::readMailCount();
		
	}
	
	
	/**
	 * アカウントディレクトリを初期化
	 */
	private static function initializeAccount($account){
		Logger::debug(__METHOD__,"account=".$account);
		if(!is_dir(self::$CONFIG['dataDirectory'])) exit("[ERROR] No data directory. ".self::$CONFIG['dataDirectory']);
		$accountDirectory=self::$CONFIG['dataDirectory']."/".$account;
		mkdir($accountDirectory);
		mkdir($accountDirectory."/".self::$CONFIG['sourceDirectory']);
		mkdir($accountDirectory."/".self::$CONFIG['attachDirectory']);
		
		$dbh = new PDO(
			self::$CONFIG['pdoDriver'].":".$accountDirectory."/".self::$CONFIG['mailDatabase'],
			self::$CONFIG['pdoUser'],
			self::$CONFIG['pdoPassword']
		);
		$dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_WARNING);
		
		$sql = "CREATE TABLE maildata (".
			"seq_id       INTEGER PRIMARY KEY NOT NULL,".
			"mail_id      TEXT, ".
			"subject      TEXT, ".
			"h_from       TEXT, ".
			"h_from_real  TEXT, ".
			"h_from_fancy TEXT, ".
			"h_to         TEXT, ".
			"h_to_real    TEXT, ".
			"h_cc         TEXT, ".
			"h_message_id TEXT, ".
			"h_references TEXT, ".
			"bodytext     TEXT, ".
			"header       TEXT, ".
			"attach       TEXT, ".
			"attach_content_type TEXT, ".
			"etime        INTEGER, ".
			"category     INTEGER, ".
			"readed       INTEGER ".
		")";
		//UguisuAccount.class.php#archiveTrash と、内容を合わせること
		//echo $sql;
		$dbh->exec($sql);
		
		$sql = "CREATE INDEX index_mail_id ON maildata(mail_id)";
		$dbh->exec($sql);
		$sql = "CREATE INDEX index_etime ON maildata(etime)";
		$dbh->exec($sql);
		$sql = "CREATE INDEX index_h_message_id ON maildata(h_message_id)";
		$dbh->exec($sql);
		$sql = "CREATE INDEX index_h_references ON maildata(h_references)";
		$dbh->exec($sql);
		
		$sql = "CREATE TABLE mailbox_status (".
			"key_id  TEXT PRIMARY KEY NOT NULL,".
			"value   TEXT".
		")";
		$dbh->exec($sql);
		
		$sql = "INSERT INTO mailbox_status ( key_id,value) values ( 'latestDownload',0 )";
		$dbh->exec($sql);
		$sql = "INSERT INTO mailbox_status ( key_id,value) values ( 'updateCountRequire',1 )";
		$dbh->exec($sql);
		
		$sql = "CREATE TABLE mailcount (".
			"category  TEXT PRIMARY KEY NOT NULL, ".
			"fullcount INTEGER, ".
			"unread    INTEGER ".
		")";
		$dbh->exec($sql);
		
		/*
		$sql = "CREATE TABLE mailthread (".
			"seq_id       INTEGER PRIMARY KEY NOT NULL,".
			"thread_id    TEXT, ".
			"mail_id_tsv  TEXT, ".
			"subject      TEXT, ".
			"etime        INTEGER, ".
			"category     INTEGER, ".
			"readed       INTEGER "
		")";
		*/
		
		
		
	}
	
	
	
	/**
	 * アカウントのメール件数を取得
	 */
	public function getMailCount($account,$category){
		Logger::debug(__METHOD__,$account,$category);
		
		if(!count(self::$mailCountTable)){
			self::readMailCount();
		}
		
		if(isset(self::$mailCountTable[$account][$category])){
			return self::$mailCountTable[$account][$category];
		}else{
			return array('fullcount' => 0, 'unread' => 0,);
		}
	}
	/**
	 * アカウントのメール件数トータルを取得
	 */
	public function getMailCountTotal($account){
		Logger::debug(__METHOD__,$account);
		
		if(!count(self::$mailCountTable)){
			self::readMailCount();
		}
		$aryReturn = array(
			'fullcount' => 0,
			'unread'    => 0,
		);
		foreach(self::$mailCountTable[$account] as $category => $record){
			$aryReturn['fullcount'] += $record['fullcount'];
			$aryReturn['unread']    += $record['unread'];
		}
		return $aryReturn;
	}
		
	/**
	 * メール件数を読み込み
	 * 
	 * メール件数の管理は、SQLiteではなく生テキストファイルでやったほうが低リソースな気がする
	 */
	private function readMailCount(){
		Logger::debug(__METHOD__);
		
		foreach(self::$ACCOUNT as $account => $accountRecord){
			$accountDirectory=self::$CONFIG['dataDirectory']."/".$account;
			
			$dbh = new PDO(
				self::$CONFIG['pdoDriver'].":".$accountDirectory."/".self::$CONFIG['mailDatabase'],
				self::$CONFIG['pdoUser'],
				self::$CONFIG['pdoPassword']
			);
			$dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_WARNING);
		
			$sql = "SELECT category,fullcount,unread FROM mailcount";
			$prepare = $dbh->prepare($sql);
			$prepare->execute();
			$result = $prepare->fetchAll();
			
			self::$mailCountTable[$account] = array();
			
			foreach($result as $record){
				self::$mailCountTable[$account][$record['category']] = array(
					'fullcount' => $record['fullcount'],
					'unread'    => $record['unread'],
				);
			}
			$dbh = null;
		}
	}
	
	
	/**
	 * 全アカウントのメール件数をアップデート
	 */
	public static function updateCount(){
		Logger::debug(__METHOD__);
		
		foreach(self::$ACCOUNT as $account => $accountRecord){
			$accountDirectory=self::$CONFIG['dataDirectory']."/".$account;
			
			$dbh = new PDO(
				self::$CONFIG['pdoDriver'].":".$accountDirectory."/".self::$CONFIG['mailDatabase'],
				self::$CONFIG['pdoUser'],
				self::$CONFIG['pdoPassword']
			);
			$dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_WARNING);
			
			//再計算が必要かどうかチェック
			$sql="SELECT value FROM mailbox_status WHERE key_id = 'updateCountRequire'";
			$prepare = $dbh->prepare($sql);
			$prepare->execute();
			$result = $prepare->fetch();
			if(isset($result['value'])){
				if($result['value']){
					$updateCountRequire = true;  //結果trueなら再計算する
				}else{
					$updateCountRequire = false; //結果falseなら再計算しない
				}
			}else{
				$updateCountRequire = true; //結果なし(異常)なら再計算する
			}
			if($updateCountRequire){
				Logger::debug(__METHOD__,$account."のメール件数を再計算");
				
				//20090708 タイムアウトすることがあるので
				set_time_limit(self::$CONFIG['mailDownloadTimeout']);
				
				$sql = "DELETE FROM mailcount ";
				$prepare = $dbh->prepare($sql);
				$prepare->execute();
				
				$sql = "SELECT count(*) as count, category, readed ".
					"FROM maildata GROUP BY category, readed ";
				$prepare = $dbh->prepare($sql);
				$prepare->execute();
				$result = $prepare->fetchAll();
				
				//結果を整形
				$mailCount = array();
				foreach($result as $record){
					
					if(!isset($mailCount[$record['category']])){
						$mailCount[$record['category']] = array();
						$mailCount[$record['category']]['unread']    = 0;
						$mailCount[$record['category']]['fullcount'] = 0;
					}
					
					if($record['readed'] == 0){ //未読数の場合
						$mailCount[$record['category']]['unread']     = $record['count'];
						$mailCount[$record['category']]['fullcount'] += $record['count'];
					}else{ //既読の場合
						$mailCount[$record['category']]['fullcount'] += $record['count'];
					}
				}
				
				//結果を書き込み
				$sql = "INSERT INTO mailcount ( ".
					"category, fullcount, unread ".
					") VALUES ( ".
					":category, :fullcount, :unread ".
					")";
				$prepare = $dbh->prepare($sql);
				foreach($mailCount as $category => $record){
					$values = array(
						":category"  => $category,
						":fullcount" => $record['fullcount'],
						":unread"    => $record['unread'],
					);
					$prepare->execute($values);
				}
				
				//$sql="UPDATE mailbox_status SET value = 0 WHERE key_id = 'updateCountRequire'";
				$sql="REPLACE INTO mailbox_status ( key_id, value ) VALUES ('updateCountRequire',0)";
				$prepare = $dbh->prepare($sql);
				$prepare->execute();
			}
		}
		return;
	}

}

?>