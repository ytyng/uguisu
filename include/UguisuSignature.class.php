<?php

/*
Uguisu アドレスブッククラス


*/

class UguisuSignature{
	
	private static $CONFIG  = array();
	
	private static $dbh;
	
	private static $signatureList;
	
	/**
	 * 初期化
	 */
	public static function initialize(){
		Logger::debug(__METHOD__);
		
		global $CONFIG;
		self::$CONFIG  = &$CONFIG;
		
		$signatureDb = self::$CONFIG['dataDirectory']."/".self::$CONFIG['signatureDb'];
		
		if(!is_file($signatureDb)){
			//dbファイルが存在しない場合、作成
			self::createDb($signatureDb);
		}
		
		self::$dbh = new PDO(
			self::$CONFIG['pdoDriver'].":".$signatureDb,
			self::$CONFIG['pdoUser'],
			self::$CONFIG['pdoPassword']
		);		
		self::$dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_WARNING);
		
		self::read();
	}
	
	/**
	 * DBファイル作成
	 */
	private function createDb($signatureDb){
		Logger::debug(__METHOD__,$signatureDb);
		self::$dbh = new PDO(
			self::$CONFIG['pdoDriver'].":".$signatureDb,
			self::$CONFIG['pdoUser'],
			self::$CONFIG['pdoPassword']
		);		
		self::$dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_WARNING);
		
		$sql = "CREATE TABLE signature (".
			//"seq_id   INTEGER PRIMARY KEY NOT NULL,".
			"title    TEXT PRIMARY KEY NOT NULL, ".
			"bodytext TEXT, ".
			"mtime    INTEGER ".
		")";
		self::$dbh->exec($sql);
	}
	
	/**
	 * 登録
	 */
	public static function save($title,$bodytext){
		Logger::debug(__METHOD__,$title);
		
		if(trim($bodytext)==""){
			//本文なしなら削除
			$sql = "DELETE FROM signature WHERE title = :title";
			$values = array(':title'=>$title,);
			$prepare = self::$dbh->prepare($sql);
			$prepare->execute($values);
			return;
		}
		
		/*
		if($seq_id == ""){
			//ID指定無しなら新規登録
			$sql = "SELECT max(seq_id) as max_id FROM signature";
			$prepare = self::$dbh->prepare($sql);
			$prepare->execute();
			$result = $prepare->fetch();
			
			$maxId = $result['max_id'];
			if(!$maxId){
				$maxId = 0;
			}
			$seq_id = $maxId + 1;
		}
		*/
		
		$sql = "REPLACE INTO signature ( ".
			"title, bodytext, mtime ".
			") VALUES ( ".
			":title, :bodytext, :mtime ".
			")";
		
		$values = array(
			//":seq_id"   => $seq_id,
			":title"    => $title,
			":bodytext" => $bodytext,
			":mtime"    => time(),
		);
		
		$prepare = self::$dbh->prepare($sql);
		$prepare->execute($values);
		
		
		
	}
	
	/**
	 * 署名リストを読み込み
	 * initiarizeから呼ばれる
	 */
	private static function read($orderBy="mtime DESC"){
		Logger::debug(__METHOD__,$orderBy);
		
		$allowOrder = array("mtime","mtime DESC","seq_id","seq_id DESC");
		if(!in_array($orderBy,$allowOrder)) exit("[ERROR] Order not allow.");
		
		$sql="SELECT title, bodytext FROM signature ";
		if($orderBy){
			$sql .= "ORDER BY ".$orderBy;
		}
		$prepare = self::$dbh->prepare($sql);
		$prepare->execute();
		self::$signatureList = $prepare->fetchAll();
	}
	
	/**
	 * 署名リストを取得
	 */
	public static function getSignatureList(){
		Logger::debug(__METHOD__);
		return self::$signatureList;
	}
	
	/**
	 * 署名を1行にする
	 */
	public static function oneLineText($s){
		
		
	}
}
?>