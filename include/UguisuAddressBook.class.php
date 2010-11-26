<?php

/*
Uguisu アドレスブッククラス


*/

class UguisuAddressBook{
	
	private static $CONFIG  = array();
	
	private static $dbh;
	
	private static $addressList;
	
	/**
	 * 初期化
	 */
	public static function initialize(){
		Logger::debug(__METHOD__);
		
		global $CONFIG;
		self::$CONFIG  = &$CONFIG;
		
		$addressBookDb = self::$CONFIG['dataDirectory']."/".self::$CONFIG['addressBookDb'];
		
		if(!is_file($addressBookDb)){
			//dbファイルが存在しない場合、作成
			self::createDb($addressBookDb);
		}
		
		self::$dbh = new PDO(
			self::$CONFIG['pdoDriver'].":".$addressBookDb,
			self::$CONFIG['pdoUser'],
			self::$CONFIG['pdoPassword']
		);		
		self::$dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_WARNING);
		
		self::read();
	}
	
	/**
	 * DBファイル作成
	 */
	private function createDb($addressBookDb){
		Logger::debug(__METHOD__,$addressBookDb);
		self::$dbh = new PDO(
			self::$CONFIG['pdoDriver'].":".$addressBookDb,
			self::$CONFIG['pdoUser'],
			self::$CONFIG['pdoPassword']
		);		
		self::$dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_WARNING);
		
		$sql = "CREATE TABLE address_book (".
			//"seq_id  INTEGER PRIMARY KEY NOT NULL,".
			"address TEXT PRIMARY KEY, ".
			"name    TEXT, ".
			"memo    TEXT, ".
			//"ctime   INTEGER, ".
			"mtime   INTEGER ".
		")";
		self::$dbh->exec($sql);
		$sql = "CREATE INDEX index_address_book_address ON address_book(address)";
		self::$dbh->exec($sql);
		//addressがプライマリキーでもいいかもしれない
	}
	
	
	/**
	 * 登録
	 */
	public static function save($mailAddress,$contactName,$memo){
		Logger::debug(__METHOD__,$mailAddress);
		
		$mailAddress = trim(self::removeNl($mailAddress));
		$contactName = trim(self::removeNl($contactName));
		$memo        = trim(self::removeNl($memo));
		
		if($contactName){
			
			$sql = "REPLACE INTO address_book ( ".
				"address, name, memo, mtime ".
				") VALUES ( ".
				":address, :name, :memo, :mtime ".
				")";
			
			$values = array(
				':address' => $mailAddress,
				':name'    => $contactName, 
				':memo'    => $memo, 
				':mtime'   => time(),
			);
			
			$prepare = self::$dbh->prepare($sql);
			$prepare->execute($values);
			
		}else{
			//contactNameが空の場合、レコード削除を行う
			$sql = "DELETE FROM address_book WHERE address = :address";
			$values = array(':address' => $mailAddress,);
			$prepare = self::$dbh->prepare($sql);
			$prepare->execute($values);
		}
		
	}
	
	/**
	 * アドレスリストを読み込み
	 * initiarizeから呼ばれる
	 */
	private static function read($orderBy="name"){
		Logger::debug(__METHOD__,$orderBy);
		
		/* ORDER BY はバインドできない？
		$sql="SELECT address, name, memo FROM address_book ORDER BY :orderby";
		$prepare = self::$dbh->prepare($sql);
		$prepare->execute(array(':orderby'=>$orderBy));
		*/
		$allowOrder = array("address ","address DESC","name","name DESC");
		if(!in_array($orderBy,$allowOrder)) exit("[ERROR] Order not allow.");
		$sql="SELECT address, name, memo FROM address_book ";
		if($orderBy){
			$sql .= "ORDER BY ".$orderBy;
		}
		$prepare = self::$dbh->prepare($sql);
		$prepare->execute();
		self::$addressList = $prepare->fetchAll();
	}
	
	/**
	 * アドレスリストを取得
	 */
	public static function getAddressList(){
		Logger::debug(__METHOD__);
		return self::$addressList;
	}
	
	
	/**
	 * 改行除去
	 */
	public static function removeNl($s){
		$s = str_replace("\r","",$s);
		$s = str_replace("\n","",$s);
		return $s;
	}
}

?>