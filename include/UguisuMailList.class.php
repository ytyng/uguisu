<?php
/*
メールリストを作成するクラス。
検索などもここで行う。

static

*/


class UguisuMailList{
	
	private static $CONFIG  = array();
	public  static $accountRecord = array();
	
	private static $dbh;
	
	private static $accountDirectory = "";
	//public static $latestDownloadTime = 0;
	
	private static $searchKey="";  //検索対象フィールド。"FULL"は特殊値、フィールドを全体的に検索する
	private static $searchType=""; //contain:部分一致検索
	private static $searchValue="";
	//フィルタや検索機能は、自動的に$searchKeyなどに値を代入することで行う
	
	private static $orderKey="etime";
	//private static $orderKey="h_from";
	//private static $orderDir="";
	private static $orderDir="DESC";
	
	/**
	 * 初期化
	 */
	public static function initialize($account){
		Logger::debug(__METHOD__);
		
		global $CONFIG;
		global $ACCOUNT;
		
		self::$CONFIG  = &$CONFIG;
		self::$accountRecord = $ACCOUNT[$account];
		
		self::$accountDirectory = self::$CONFIG['dataDirectory']."/".$account;
		
		self::$dbh = new PDO(
			self::$CONFIG['pdoDriver'].":".self::$accountDirectory."/".self::$CONFIG['mailDatabase'],
			self::$CONFIG['pdoUser'],
			self::$CONFIG['pdoPassword']
		);		
		self::$dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_WARNING);
	}
	
	/**
	 * メールを取得
	 * @param $page      表示ページ 1スタート
	 *        $category  表示カテゴリ
	 */
	public static function getMailList($page=1,$category=0){
		Logger::debug(__METHOD__,"page=".$page.",category=".$category);
		
		$limit = self::$CONFIG['mailVolumeParPage'];
		
		$offset = $limit * ($page -1);
		
		$sql = "SELECT seq_id, mail_id, subject, h_from, h_from_fancy, etime, attach, readed ".
			"FROM maildata ".
			"WHERE ";
		$values = array();
			
		if($category >= 0){
			$sql .= "category = :category ";
			$values[':category'] = $category;
		}else{
			//categoryが0以下なら、ごみ箱以外の全カテゴリを対象
			$sql .= "category != :category ";
			$values[':category'] = self::$CONFIG['trashBox'];
		}
		
		//検索
		switch(self::$searchType){
		case "contain":
		case "contains":
			//部分一致モード
			$aryQ = preg_split("/\s/",trim(self::$searchValue));
			if(self::$searchKey == "FULL"){
				//全文検索
				foreach($aryQ as $i => $q){
					$sql .= "and ( ";
					foreach(self::$CONFIG['fullSearchField'] as $j => $searchField){
						if($j != 0) $sql .= "or ";
						$sql .= $searchField." like :search_".$searchField."_".$i." ";
						$values[':search_'.$searchField.'_'.$i] = "%".$q."%";
					}
					$sql .= ") ";
				}
			}else{
				foreach($aryQ as $i => $q){
					//フィールドにはバインドできないので。 TODO:脆弱性チェック
					$sql .= "and ".self::$searchKey." like :search_value_".$i." ";
					$values[':search_value_'.$i] = "%".$q."%";
				}
			}
			break;
		case "new-second":
		case "new":
			//最新○秒以内
			if(self::$searchKey != "etime") exit("[ERROR] new-second サーチ時の対象キーは etime のみ使用可能");
			$threshold = time() - (int)self::$searchValue;
			$sql .= "and ".self::$searchKey." >= :threshold ";
			$values[':threshold'] = $threshold;
			Logger::debug(__METHOD__,"最新検索:".$threshold);
			break;
		}
		
		//$sql .= "ORDER BY :order ";
		//$values[':order'] = self::$orderKey; //ORDER BY はバインドできない???
		
		$sql .= "ORDER BY ".self::$orderKey." ";
		
		$sql .= self::$orderDir." ";
		
		$sql .= "LIMIT :limit ";
		$values[':limit'] = $limit;
		
		$sql .= "OFFSET :offset ";
		$values[':offset'] = $offset;
		
		//$sql .= "ORDER BY etime DESC";
		
		Logger::debug(__METHOD__,"sql=".$sql);
		//print_r($values);
		Logger::debug(__METHOD__,"limit=".$limit.",offset=".$offset);
		
		$prepareMailList = self::$dbh->prepare($sql);
		$prepareMailList->execute($values);
		
		$result = $prepareMailList->fetchAll();
		
		return $result;
	}
	
	/**
	 * メールスレッドを取得
	 */
	public static function getMailThreadList($category=0){
		Logger::debug(__METHOD__);
		$sql = "SELECT seq_id, mail_id, subject, h_from, h_from_fancy, h_message_id, h_references, etime, attach, readed ".
			"FROM maildata ".
			"WHERE category = :category ".
			"ORDER BY seq_id ";
		$values=array();
		$values[':category']=$category;
		
		$prepareMailList = self::$dbh->prepare($sql);
		$prepareMailList->execute($values);
		
		$mailList = $prepareMailList->fetchAll();
		
		$aryThread = array();
		//$aryThread[]
		// 'record' => array(); //メールレコード
		// 'subject' => string 最新の件名
		// 'mtime'   => int    スレッド最終更新日付
		$aryMessageId = array();
		
		$threadId = -1;
		foreach($mailList as $record){
			//print_r($record);
			
			/*
			if($record['h_references'] && isset($aryMessageId[$record['h_references']])){
				//既出のメッセージIDなら
				$tmpThreadId = $aryMessageId[$record['h_references']];
				Logger::debug(__METHOD__,"既存のメッセージIDを検出:".$record['h_references']."=>".$tmpThreadId);
				$aryThread[$tmpThreadId]['record'][] = $record;
				$aryThread[$tmpThreadId]['subject']  = $record['subject'];
				$aryThread[$tmpThreadId]['etime']    = $record['etime'];
				
				$aryMessageId[$record['h_message_id']] = $tmpThreadId;
				
			}else if($record['h_message_id']){
				//新規のメッセージIDなら
				$threadId++;
				Logger::debug(__METHOD__,"新規のメッセージIDを登録:".$record['h_message_id']."=>".$threadId);
				$aryMessageId[$record['h_message_id']] = $threadId;
				$aryThread[$threadId] = array();
				$aryThread[$threadId]['record']  = array($record);
				$aryThread[$threadId]['subject'] = $record['subject'];
				$aryThread[$threadId]['etime']   = $record['etime'];
				
			}else{
				//メッセージIDなし
				$threadId++;
				Logger::debug(__METHOD__,"メッセージIDなし:".$record['h_message_id']);
				$aryThread[$threadId] = array();
				$aryThread[$threadId]['record']  = array($record);
				$aryThread[$threadId]['subject'] = $record['subject'];
				$aryThread[$threadId]['etime']   = $record['etime'];
			}
			*/
			
			$threadFound = false;
			
			if($record['h_references']){
				$referencesList = explode("\t",$record['h_references']); #h_referencesはTSVになっている
				foreach($referencesList as $references){
					if(isset($aryMessageId[$references])){
						//既出のメッセージIDなら
						$tmpThreadId = $aryMessageId[$references];
						Logger::debug(__METHOD__,"既存のメッセージIDを検出:".$references."(in ".$record['h_references'].")=>".$tmpThreadId);
						$aryThread[$tmpThreadId]['record'][] = $record;
						$aryThread[$tmpThreadId]['subject']  = $record['subject'];
						$aryThread[$tmpThreadId]['etime']    = $record['etime'];
						
						$aryMessageId[$record['h_message_id']] = $tmpThreadId;
						$threadFound = true;
						break;
					}
				}
			
			}
			if(!$threadFound){
				$threadId++;
				if($record['h_message_id']){
					//新規のメッセージIDなら
					Logger::debug(__METHOD__,"新規のメッセージIDを登録:".$record['h_message_id']."=>".$threadId);
					$aryMessageId[$record['h_message_id']] = $threadId;
				}else{
					Logger::debug(__METHOD__,"メッセージIDなし:".$record['h_message_id']);
				}
				$aryThread[$threadId] = array();
				$aryThread[$threadId]['record']  = array($record);
				$aryThread[$threadId]['subject'] = $record['subject'];
				$aryThread[$threadId]['etime']   = $record['etime'];
			}
			
		}
		
		//メールスレッドを最終更新日付でソート
		//usort($ary2,array($this, "compareSubjectTableBySpeed"));
		usort($aryThread,"UguisuMailList::compareMailThread");
		
		return $aryThread;
	}
	
	/**
	 * ソート用比較関数
	 */
	private static function compareMailThread($a, $b){
		return $a['etime'] - $b['etime'];
	}
	
	
	/**
	 * 検索ワードを指定
	 */
	public static function setSearchWord($searchKey,$searchType,$searchValue){
		$searchKey = strtolower($searchKey);
		//検索キーを変換する。アカウント設定ファイルのフィルタ設定に、
		//より自然に表記できるように。
		$searchKeyConvert = array(
			'from'      => "h_from",
			'from_real' => "h_from_real",
			'to'        => "h_to",
			'to_real'   => "h_to_real",
			'cc'        => "h_cc",
			'time'      => "etime",
			'full'      => "FULL",
		);
		if(isset($searchKeyConvert[$searchKey])){
			$searchKey = $searchKeyConvert[$searchKey];
		}
		
		self::$searchKey   = $searchKey;
		self::$searchType  = $searchType;
		self::$searchValue = $searchValue;
	}
	
	
	/**
	 * 24時間以内の全メールを取得
	 * サマリレポート送信用
	 */
	public static function getMailListToday(){
		Logger::debug(__METHOD__);
		
		$sql = "SELECT seq_id, mail_id, subject, h_from, h_from_fancy, etime, attach, readed ".
			"FROM maildata ".
			"WHERE etime > :threshold";
		$values = array(":threshold"=>time()-86400);
		$prepareMailList = self::$dbh->prepare($sql);
		$prepareMailList->execute($values);
		$result = $prepareMailList->fetchAll();
		
		return $result;
	}
	
	
}

?>