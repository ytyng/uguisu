<?php
/*
アカウント情報を管理するクラス。
メールの受信などを行う

20090518 メール保存の際、全角チルダを波ダッシュ化
20090617 メールダウンロード時にロック処理を追加
20090907 アーカイブ処理を追加
*/

class UguisuAccount{
	
	private static $CONFIG  = array();
	public  static $accountRecord = array();
	
	private static $dbh;
	private static $pop3;
	
	private static $accountDirectory = "";
	
	public  static $latestDownloadTime = 0;
	
	private static $notifyMessage = ""; //メール受信サマリ文。着信通知に使う。
	
	//private static $mailCountTable = array();
	
	/**
	 * 初期化
	 */
	public static function initialize($account){
		global $CONFIG;
		global $ACCOUNT;
		
		Logger::debug(__METHOD__,$account);
		
		self::$CONFIG  = &$CONFIG;
		self::$accountRecord = $ACCOUNT[$account];
		
		self::$accountDirectory = self::$CONFIG['dataDirectory']."/".$account;
		
		self::$dbh = new PDO(
			self::$CONFIG['pdoDriver'].":".self::$accountDirectory."/".self::$CONFIG['mailDatabase'],
			self::$CONFIG['pdoUser'],
			self::$CONFIG['pdoPassword']
		);		
		self::$dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_WARNING);
		
		self::$latestDownloadTime = self::getMailboxStatus('latestDownload');
		
		self::$notifyMessage="";
	}
	
	
	/**
	 * メールボックスのステータスを取得
	 */
	public static function getMailboxStatus($key){
		Logger::debug(__METHOD__,"key=".$key);
		$sql="SELECT value FROM mailbox_status WHERE key_id = :key_id";
		$sth = self::$dbh->prepare($sql);
		$sth->execute(array(':key_id'=>$key));
		$record = $sth->fetch();
		if(isset($record['value'])){
			return $record['value'];
		}else{
			return false;
		}
	}
	
	/**
	 * メールボックスのステータスを設定
	 */
	public static function setMailboxStatus($key,$value){
		Logger::debug(__METHOD__,"key=".$key.",value=".$value);
		$sql="UPDATE mailbox_status SET value = :value WHERE key_id = :key_id";
		$sth = self::$dbh->prepare($sql);
		$sth->execute(array(':value'=>$value,':key_id'=>$key));
	}
	
	/**
	 * メールボックスのステータスをダンプ
	 */
	public static function dumpMailboxStatus(){
		Logger::debug(__METHOD__);
		$sql="SELECT key_id,value FROM mailbox_status";
		$sth = self::$dbh->prepare($sql);
		$sth->execute();
		return $sth->fetchAll();
	}
	
	/**
	 * メールデータをダンプ
	 */
	public static function dumpMailCountTable(){
		Logger::debug(__METHOD__);
		$sql="SELECT * FROM mailcount ";
		$sth = self::$dbh->prepare($sql);
		$sth->execute();
		return $sth->fetchAll();
	}
	
	/**
	 * メールデータをダンプ
	 */
	public static function dumpMailDataTable($count=10){
		Logger::debug(__METHOD__);
		$sql="SELECT * FROM maildata ORDER BY etime desc LIMIT :limit";
		$sth = self::$dbh->prepare($sql);
		$sth->execute(array(':limit'=>$count));
		return $sth->fetchAll();
	}
	
	
	/**
	 * メールをダウンロード
	 */
	public static function downloadMail(){
		Logger::debug(__METHOD__);
		
		
		if(self::isLocked()){
			exit("System is locking. Please wait for a minute, and retry.");
		}
		self::lock();
		
		require_once(self::$CONFIG['pop3Class']);
		require_once(self::$CONFIG['mailParserClass']);
		
		self::$pop3 = new pop3(
			self::$accountRecord['pop-server'],
			self::$accountRecord['pop-id'],
			self::$accountRecord['pop-passwd'],
			self::$accountRecord['pop-port']
		);
		
		set_time_limit(self::$CONFIG['mailDownloadTimeout']);
		
		
		self::$pop3->open() or exit("login failed.");
		
		//■受信可能メール数を取得
		$aryStat = self::$pop3->get_stat();
		if(!$aryStat[0]){ //メールがなければ終了
			self::$pop3->close();
			self::setMailboxStatus('latestDownload',time());
			self::unLock();
			return;
		}
		
		//print_r($stat); //例: Array ( [0] => 14 [1] => 261620 )
		
		//■ユニークIDリストを取得
		$aryUid = self::$pop3->get_uidl();
		
		//print_r($aryUid); //例: Array ( [1] => APBVXcoAAQUrSaseigeBxizt31E [2] => AO9VXcoAADmHSaxtewwppgJwnUk
		
		$sqlMailCheck = "SELECT mail_id,etime FROM maildata WHERE mail_id = :mail_id";
		$prepareMailCheck = self::$dbh->prepare($sqlMailCheck);
		
		$sqlMailSave = "INSERT INTO maildata ( ".
			"mail_id, subject, h_from, h_from_real, h_from_fancy, h_to, h_to_real, h_cc, ".
			"h_message_id, h_references, bodytext, header, attach, ".
			"attach_content_type, etime, category, readed ".
			") VALUES ( ".
			":mail_id, :subject, :h_from, :h_from_real, :h_from_fancy, :h_to, :h_to_real, :h_cc, ".
			":h_message_id, :h_references, :bodytext, :header, :attach, ".
			":attach_content_type, :etime, :category, :readed ".
			")";
		$prepareMailSave = self::$dbh->prepare($sqlMailSave);
		
		//echo $sqlMailCheck;
		$updateCountRequire = false;
		
		foreach($aryUid as $i => $mailId){
			//Logger::debug(__METHOD__,"mailId=".$mailId);
			//メールがダウンロード済みか判断
			$prepareMailCheck->execute(array(':mail_id'=>$mailId,));
			$record = $prepareMailCheck->fetch();
			
			//print_r($record);//debug
			
			if(!$record['mail_id']){
				//メールが存在しない場合はダウンロードして保存
				Logger::debug(__METHOD__,"メールを保存:mailId=".$mailId);
				$mailBody = self::$pop3->retr($i);
				
				//ソースを保存する設定の場合、保存
				if(self::$accountRecord['save-source']){
					Logger::debug(__METHOD__,"メールソースをファイルとして保存");
					$sourceFile = self::$accountDirectory."/".self::$CONFIG['sourceDirectory']."/".self::sanitizeMailId($mailId);
					file_put_contents($sourceFile,$mailBody);
				}
				
				//メールパーサを作成
				$mailParser = new MailParser();
				
				//デフォルトエンコーディングを指定
				if(self::$accountRecord['default-encoding']){
					$mailParser->setDefaultEncoding(self::$accountRecord['default-encoding']);
				}
				//パース実行
				$mailParser->parse($mailBody);
				
				//echo "<pre>".htmlspecialchars($mailParser->getBodyText())."</pre>"; //debug
				
				//マルチパートメールの場合、ファイルをファイルとして保存
				$attachFileName    = array();
				$attachContentType = array();
				if($mailParser->isMultiPart()){
					Logger::debug(__METHOD__,"マルチパートメールをファイルとして保存");
					$attachDirectory = self::$accountDirectory."/".self::$CONFIG['attachDirectory']."/".self::sanitizeMailId($mailId);
					mkdir($attachDirectory);
					
					foreach($mailParser->aryBody as $partIndex => $part){
						Logger::debug(__METHOD__,"ファイル[".$partIndex."]");
						Logger::debug(__METHOD__,"ファイル名=".$part['filename']);
						file_put_contents(
							$attachDirectory."/".mb_convert_encoding($part['filename'],self::$CONFIG['fileSystemEncoding'],mb_internal_encoding()),
							$mailParser->getMultipartFileBinary($partIndex)
						);
						$attachFileName[]    = $part['filename'];
						$attachContentType[] = $part['content-type'];
					}
					
				}
				
				
				
				
				$values = array(
					':mail_id'      => $mailId,
					':subject'      => $mailParser->getHeader('subject'),
					':h_from'       => $mailParser->getHeader('from'),
					':h_from_real'  => self::realMailAddress($mailParser->getHeader('from')),
					':h_from_fancy' => self::fancyMailAddress($mailParser->getHeader('from')),
					':h_to'         => $mailParser->getHeader('to'),
					':h_to_real'    => self::realMailAddress($mailParser->getHeader('to')),
					':h_cc'         => $mailParser->getHeader('cc'),
					':h_message_id' => $mailParser->getMessageId(),
					':h_references' => $mailParser->getReferences(),
					//':bodytext'     => $mailParser->getBodyText(),
					':bodytext'     => self::convertMbTilde2SwangDash($mailParser->getBodyText()),
					':header'       => $mailParser->getHeaderFull(),
					':attach'       => implode ("\t",$attachFileName),
					':attach_content_type' => implode ("\t",$attachContentType),
					':etime'        => $mailParser->getTimeStamp(),
					':category'     => 0,
					':readed'       => 0,
				);
				
				//振り分けを行う。
				//この引数のキーが、アカウント設定 $ACCOUNT['category'][]['dispatch']['key'] に使える。
				$values[':category'] = self::dispatchAtRecieve(array(
					'subject'    => $values[':subject'],
					'from'       => $values[':h_from'],
					'from_real'  => $values[':h_from_real'],
					'to'         => $values[':h_to'],
					'to_real'    => $values[':h_to_real'],
					'cc'         => $values[':h_cc'],
					'to_cc'      => $values[':h_to'].",".$values[':h_cc'],
					'from_to'    => $values[':h_from'].",".$values[':h_to'],
					'from_to_cc' => $values[':h_from'].",".$values[':h_to'].",".$values[':h_cc'],
					'bodytext'   => $values[':bodytext'],
				));
				
				//件名が無い場合
				if(!$values[':subject']){
					$values[':subject'] = self::$CONFIG['untitled'];
				}
				
				$prepareMailSave->execute($values);
				
				//お知らせメッセージに登録。メールチェッカー用
				self::setNotifyMessage($values[':subject'],$values[':h_from_fancy'],$values[':etime']);
				
				//期限超過のメールをサーバから削除
				$limitTime = $mailParser->getTimeStamp() + (86400 * self::$accountRecord['server-keep-days']);
				
				if(self::$CONFIG['debugLevel'] >= 2){
					$mailParser->printDebugMessage();//debug
				}
				$updateCountRequire = true;
			}else{
				Logger::debug(__METHOD__,"メールは保存済み:mailId=".$mailId);
				//期限超過のメールをサーバから削除
				$limitTime = $record['etime'] + (86400 * self::$accountRecord['server-keep-days']);
				Logger::debug(__METHOD__,"削除判定:".$record['etime']."+".(86400 * self::$accountRecord['server-keep-days'])."<".time());
			
			}
			
			//メール削除実行
			if(self::$accountRecord['server-keep-days'] >= 0){
				if($limitTime < time()){
					Logger::debug(__METHOD__,"サーバからメールを削除:mailId=".$mailId);
					self::$pop3->dele($i) or print("メール削除に失敗:mailId=".$mailId);
				}
			}else{
				//'server-keep-days' が-1なら、メールを削除しない
			}
			
		}
		self::$pop3->close();
		self::setMailboxStatus('latestDownload',time());
		//メールがダウンロードされたのであれば、件数を更新
		if($updateCountRequire){
			self::setMailboxStatus('updateCountRequire',1);
		}
		
		self::unLock();
	}
	
	/**
	 * POP3サーバの状態表示
	 */
	public static function pop3stats(){
		Logger::debug(__METHOD__);
		require_once(self::$CONFIG['pop3Class']);
		require_once(self::$CONFIG['mailParserClass']);
		
		self::$pop3 = new pop3(
			self::$accountRecord['pop-server'],
			self::$accountRecord['pop-id'],
			self::$accountRecord['pop-passwd'],
			self::$accountRecord['pop-port']
		);
		
		self::$pop3->open() or exit("login failed.");
		
		$aryStat = self::$pop3->get_stat();
		$aryList = self::$pop3->get_list();
		$aryUid  = self::$pop3->get_uidl();
		self::$pop3->close();
		return array($aryStat,$aryList,$aryUid);
	}
	
	
	
	/**
	 * メールのユニークIDをOS(ファイルシステム)にとって安全なものに変換する
	 */
	public static function sanitizeMailId($mail_id){
		return md5($mail_id);
	}
	
	/**
	 * メールボックスの全レコードを消去
	 */
	public static function deleteMailData(){
		Logger::debug(__METHOD__);
		$sql="DELETE FROM maildata ";
		$sth = self::$dbh->prepare($sql);
		$sth->execute();
		return ;
	}
	
	/**
	 * メールアドレスを正規化する。< >など、無駄な記号を除去
	 * 複数アドレスはカンマ区切りに統一する。
	 */
	private static function realMailAddress($str){
		$addressList = preg_split("/[\s\,\;]+/",$str);
		$outBuffer = array();
		$reg = "/([a-z0-9_\-\.]+@[a-z0-9_\-\.]+[a-z]{2,6})/i";
		
		foreach($addressList as $address){
			if(preg_match($reg,$address,$aryResult)){
				$outBuffer[] = $aryResult[1];
			}
		}
		return implode(",",$outBuffer);
	}
	
	/**
	 * メールアドレスから、実際のアドレスではなく付加してある自称名称を抜き出す。
	 */
	private static function fancyMailAddress($str){
		if(!$str) return "";
		$n = "";
		if(preg_match("/^([^<]+)</",$str,$result)){
			// < の前までを取得
			$n = $result[1];
			$n = trim($n);
			$n = trim($n,"\"\'");
			return $n;
		}
		
		if(preg_match("/([\w_\-\.]+)@/i",$str,$result)){
			// @ の前までを取得
			return $result[1];
		}
		
		$n = $str;
		$n = trim($str);
		$n = trim("\"\'");
		if($n){
			return $n;
		}else{
			return self::$CONFIG['unknownMailaddress']; 
		}
	}
	
	
	/**
	 * カテゴリリストを作成
	 */
	public static function getCategoryList(){
		$aryTmp = array();
		//ビルトインカテゴリ
		foreach(self::$CONFIG['built-in-category'] as $categoryId => $record){
			$aryTmp[$categoryId] = $record['name'];
		}
		//独自カテゴリ
		foreach(self::$accountRecord['category'] as $categoryId => $record){
			$aryTmp[$categoryId] = $record['name'];
		}
		return $aryTmp;
	}
	
	/**
	 * メールカテゴリを移動
	 */
	public static function moveMail($mail_id,$category){
		Logger::debug(__METHOD__,$mail_id.",".$category);
		
		$sql = "UPDATE maildata SET category = :category WHERE mail_id = :mail_id";
		$values = array(":category"=>$category,":mail_id"=>$mail_id,);
		$prepare = self::$dbh->prepare($sql);
		$prepare->execute($values);
		//if(self::$CONFIG['mailCountAlways']) self::updateCount();
		self::setMailboxStatus('updateCountRequire',1);
		return;
	}
	
	/**
	 * カテゴリ内のメールを自動的にごみ箱へ移動
	 */
	public static function autoTrash($category){
		Logger::debug(__METHOD__,$category);
		
		$autoTrashDays = self::$accountRecord['auto-trash-days'];
		if(isset(self::$accountRecord['category'][$category]['auto-trash-days'])){
			$autoTrashDays = self::$accountRecord['category'][$category]['auto-trash-days'];
			Logger::debug(__METHOD__,"Overwrite autoTrashDays. ".$autoTrashDays);
		}
		
		
		$sql = "UPDATE maildata SET category = :to_category WHERE category = :from_category and etime < :threshold ";
		$values = array(
			':to_category'    => self::$CONFIG['trashBox'],
			':from_category'  => $category,
			':threshold'      => time() - (86400 * $autoTrashDays),
		);
		$prepare = self::$dbh->prepare($sql);
		$prepare->execute($values);
		//if(self::$CONFIG['mailCountAlways']) self::updateCount();
		self::setMailboxStatus('updateCountRequire',1);
		return;
	}
	
	/**
	 * ごみ箱を空にする
	 */
	public static function emptyTrash(){
		Logger::debug(__METHOD__);
		
		if(self::isLocked()){
			exit("System is locking. Please wait for a minute, and retry.");
		}
		self::lock();
		
		$sql = "DELETE FROM maildata WHERE category = :category ";
		$values = array(":category"=>self::$CONFIG['trashBox'],);
		$prepare = self::$dbh->prepare($sql);
		$prepare->execute($values);
		//if(self::$CONFIG['mailCountAlways']) self::updateCount();
		self::setMailboxStatus('updateCountRequire',1);
		self::unLock();
		return;
	}
	
	/**
	 * ごみ箱をアーカイブする
	 */
	public static function archiveTrash(){
		Logger::debug(__METHOD__);
		
		if(self::isLocked()){
			exit("System is locking. Please wait for a minute, and retry.");
		}
		self::lock();
		
		
		//アーカイブDBハンドルを作成
		$archiveDbh = new PDO(
			self::$CONFIG['pdoDriver'].":".self::$accountDirectory."/".self::$CONFIG['mailDbArchve'],
			self::$CONFIG['pdoUser'],
			self::$CONFIG['pdoPassword']
		);
		$archiveDbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_WARNING);
		
		//テーブル存在チェック
		$sql = "SELECT count(*) as cnt FROM sqlite_master WHERE type='table' AND name=:table_name;";
		$prepare = $archiveDbh->prepare($sql);
		$prepare->execute(array(':table_name'=>"maildata_archive"));
		$result = $prepare->fetchAll();
		
		if($result[0]['cnt'] == 0){
			//テーブルが存在しないため、作成。
			Logger::debug(__METHOD__,"Table is not exists. Create table.");
			
			//UguisuAccountList.class.php#initializeAccount の、Create文と内容を合わせること。
			$sql = "CREATE TABLE maildata_archive (".
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
			Logger::debug(__METHOD__,"SQL: ".$sql);
			$archiveDbh->exec($sql);
		}
		
		
		//ごみ箱内のメール一覧を取得。クラス内のDBハンドルを使う
		$sql = "SELECT * FROM maildata WHERE category = :category ";
		$values = array(":category"=>self::$CONFIG['trashBox'],);
		$prepare = self::$dbh->prepare($sql);
		$prepare->execute($values);
		$result = $prepare->fetchAll();
		
		//ごみ箱から削除する用のSQL
		$sqlMailDelete = "DELETE FROM maildata WHERE mail_id = :mail_id";
		$prepareMailDelete = self::$dbh->prepare($sqlMailDelete);
		
		
		//アーカイブをチェックするSQL
		$sqlMailCheck = "SELECT mail_id,etime FROM maildata_archive WHERE mail_id = :mail_id";
		$prepareMailCheck = $archiveDbh->prepare($sqlMailCheck);
		
		//アーカイブに記録するSQL
		$sqlMailSave = "INSERT INTO maildata_archive ( ".
			"mail_id, subject, h_from, h_from_real, h_from_fancy, h_to, h_to_real, h_cc, ".
			"h_message_id, h_references, bodytext, header, attach, ".
			"attach_content_type, etime, category, readed ".
			") VALUES ( ".
			":mail_id, :subject, :h_from, :h_from_real, :h_from_fancy, :h_to, :h_to_real, :h_cc, ".
			":h_message_id, :h_references, :bodytext, :header, :attach, ".
			":attach_content_type, :etime, :category, :readed ".
			")";
		$prepareMailSave = $archiveDbh->prepare($sqlMailSave);
		
		
		//メールをアーカイブにコピー
		foreach($result as $record){
			
			//メールがアーカイブ済みか判断
			$prepareMailCheck->execute(array(':mail_id'=>$record['mail_id'],));
			$checkRecord = $prepareMailCheck->fetch();
			
			if(!$checkRecord['mail_id']){
				//メールが存在しない場合はダウンロードして保存
				$values = array(
					':mail_id'             => $record['mail_id'],
					':subject'             => $record['subject'],
					':h_from'              => $record['h_from'],
					':h_from_real'         => $record['h_from_real'],
					':h_from_fancy'        => $record['h_from_fancy'],
					':h_to'                => $record['h_to'],
					':h_to_real'           => $record['h_to_real'],
					':h_cc'                => $record['h_cc'],
					':h_message_id'        => $record['h_message_id'],
					':h_references'        => $record['h_references'],
					':bodytext'            => $record['bodytext'],
					':header'              => $record['header'],
					':attach'              => $record['attach'],
					':attach_content_type' => $record['attach_content_type'],
					':etime'               => $record['etime'],
					':category'            => $record['category'],
					':readed'              => $record['readed'],
				);
				$prepareMailSave->execute($values);
				Logger::debug(__METHOD__,"Insert to archive ok. ".$record['subject']."/".$record['h_from']);
				
			}
			//アーカイブ済みかどうかにかかわらず、メールは削除
			$prepareMailDelete->execute(array(':mail_id'=>$record['mail_id']));
			Logger::debug(__METHOD__,"Delete ok. ".$record['mail_id']."/".$record['subject']."/".$record['h_from']);
		}
		self::setMailboxStatus('updateCountRequire',1);
		self::unLock();
		return;
	}
	
	
	
	/**
	 * 受信時自動振り分け
	 */
	public static function dispatchAtRecieve($mailData){
		Logger::debug(__METHOD__);
		
		if(!isset(self::$accountRecord['dispatch'])) return 0;
		
		foreach(self::$accountRecord['dispatch'] as $i => $record){
			Logger::debug(__METHOD__,"dispatch[".$i."]");
			switch($record['mode']){
			case "start":
			case "startsWith":
			case "startswith":
				if(self::startsWith($mailData[$record['key']],$record['value'])){
					Logger::debug(__METHOD__,"dispatch[".$i."]にヒット (".$record['to'].")");
					return $record['to'];
				}
				break;
			case "end":
			case "endsWith":
			case "endswith":
				if(self::endsWith($mailData[$record['key']],$record['value'])){
					Logger::debug(__METHOD__,"dispatch[".$i."]にヒット (".$record['to'].")");
					return $record['to'];
				}
				break;
			case "contain":
			case "contains":
			case "like":
				if(stripos($mailData[$record['key']],$record['value']) !== false){
					Logger::debug(__METHOD__,"dispatch[".$i."]にヒット (".$record['to'].")");
					return $record['to'];
				}
				break;
			case "equal":
			case "equals":
			case "=":
				if($mailData[$record['key']] == $record['value']){
					Logger::debug(__METHOD__,"dispatch[".$i."]にヒット (".$record['to'].")");
					return $record['to'];
				}
				break;
			}
		}
		return 0;
	}
	
	
	/**
	 * 指定文字で始まるか
	 */
	public static function startsWith($haystack,$needle){
		Logger::debug(__METHOD__,strtolower($needle).",".strtolower(substr($haystack,0,strlen($needle))));
		return strtolower($needle) == strtolower(substr($haystack,0,strlen($needle)));
	}
	
	/**
	 * 指定文字で終わるか
	 */
	public static function endsWith($haystack,$needle){
		Logger::debug(__METHOD__,strtolower($needle).",".strtolower(substr($haystack,strlen($needle) * -1 )));
		return strtolower($needle) == strtolower(substr($haystack,strlen($needle) * -1 ));
	}
	
	/**
	 * メール着信を通知メッセージに登録
	 */
	private static function setNotifyMessage($subject,$from,$date){
		$message  = "◆ ";
		$message .= date("H:i",$date);
		$message .= " ".$from."\n";
		$message .= mb_strimwidth($subject,0,self::$CONFIG['mailListSubjectMaxLength'],self::$CONFIG['mailListCutoffMarker'])."\n";
		self::$notifyMessage .= $message;
	}
	
	/**
	 * メール着信通知を取得
	 */
	public static function getNotifyMessage(){
		return self::$notifyMessage;
	}
	
	/**
	 * カテゴリ内のメールを全て既読にする
	 */
	public static function allReaded($category){
		Logger::debug(__METHOD__,$category);
		$sql = "UPDATE maildata SET readed = 1 WHERE category = :category";
		$values = array(
			':category' => $category,
		);
		$prepare = self::$dbh->prepare($sql);
		$prepare->execute($values);
		self::setMailboxStatus('updateCountRequire',1);
		return;
	}
	
	/**
	 * 全角チルダを波ダッシュに変換
	 */
	private static function convertMbTilde2SwangDash($s){
		$MB_TILDE = chr(227).chr(128).chr(156);
		return str_replace($MB_TILDE,"～",$s);
	}
	
	
	/**
	 * 処理ロックをかける
	 */
	private static function lock(){
		if(!self::$CONFIG['lockEnable']){
			Logger::debug(__METHOD__,"Lock disable.");
			return;
		}
		Logger::debug(__METHOD__);
		
		file_put_contents(
			self::$CONFIG['dataDirectory']."/".self::$CONFIG['lockFile'],
			time()
		);
	}
	
	/**
	 * 処理ロックされているか
	 */
	private static function isLocked(){
		if(!self::$CONFIG['lockEnable']){
			Logger::debug(__METHOD__,"Lock disable.");
			return;
		}
		Logger::debug(__METHOD__);
		
		$lockFile = self::$CONFIG['dataDirectory']."/".self::$CONFIG['lockFile'];
		$isEnable = false;
		if(is_file($lockFile)){
			//ファイルが存在して
			$t = file_get_contents($lockFile);
			
			if((int)$t + self::$CONFIG['lockLifetime'] > time()){
				//かつ、有効時間内であれば、trueを返す
				$isEnable = true;
			}else{
				//有効期限が切れているなら、ロックを除去
				unlink($lockFile);
			}
		}
		return $isEnable;
	}
	
	/**
	 * 処理ロック解除
	 */
	private static function unLock(){
		if(!self::$CONFIG['lockEnable']){
			Logger::debug(__METHOD__,"Lock disable.");
			return;
		}
		Logger::debug(__METHOD__);
		
		$lockFile = self::$CONFIG['dataDirectory']."/".self::$CONFIG['lockFile'];
		if(is_file($lockFile)){
			//ファイルがあれば削除
			unlink($lockFile);
		}
	}
	
	
}
?>