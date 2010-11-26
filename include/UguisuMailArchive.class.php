<?php
/*
メールアーカイブを操作するクラス

static

*/


class UguisuMailArchive{
	
	private static $CONFIG  = array();
	public  static $accountRecord = array();
	
	private static $dbh; //アーカイブDB用
	
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
			self::$CONFIG['pdoDriver'].":".self::$accountDirectory."/".self::$CONFIG['mailDbArchve'],
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
			"FROM maildata_archive ".
			"WHERE ";
		$values = array();
		
		/*
		if($category >= 0){
			$sql .= "category = :category ";
			$values[':category'] = $category;
		}else{
			//categoryが0以下なら、ごみ箱以外の全カテゴリを対象
			$sql .= "category != :category ";
			$values[':category'] = self::$CONFIG['trashBox'];
		}
		*/
		$sql .= "1 = 1 ";
		
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
	
}
?>