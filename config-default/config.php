<?php
/*

Uguisu PHPメーラー 設定ファイル

*/

define('BASE_ENCODING','UTF-8');

mb_http_output(BASE_ENCODING);
mb_internal_encoding(BASE_ENCODING);
mb_regex_encoding(BASE_ENCODING);

$CONFIG = array(
	
	//▼デバッグレベル 0:デバッグなし 1:HTMLコメントで表示 2:デバッグ表示 3:トレース文も表示
	'debugLevel'        => 1,
	
	//▼認証用スクリプト。config.phpがインクルードされた後に実行される
	'authScript'       => 'config/auth.php',
	'authScriptMobile' => 'config/auth.php',
	'loggerClass'      => 'include/Logger.class.php',
	'accountListClass' => 'include/UguisuAccountList.class.php',
	'accountClass'     => 'include/UguisuAccount.class.php',
	'pop3Class'        => 'include/Pop3.class.php',
	'mailParserClass'  => 'include/MailParser.class.php',
	'mailListClass'    => 'include/UguisuMailList.class.php',
	'mailViewClass'    => 'include/UguisuMailView.class.php',
	'mailSendClass'    => 'include/UguisuMailSend.class.php',
	'smtpClass'        => 'include/Esmtp.class.php',
	'addressBookClass' => 'include/UguisuAddressBook.class.php',
	'signatureClass'   => 'include/UguisuSignature.class.php',
	'mailArchiveClass' => 'include/UguisuMailArchive.class.php',
	
	//▼データ保存用ディレクトリ
	//WEBサーバ公開ディレクトリ以外を推奨
	//絶対指定の場合はスラッシュから書く。末尾スラッシュ不要
	'dataDirectory'    => 'data', 
	
	//▼アカウントディレクトリ内のデータファイル
	'sourceDirectory'  => 'source', //メールソース保存場所
	'attachDirectory'  => 'attach', //添付ファイル保存場所
	'mailDatabase'     => 'maildata.db', //メールデータのDB名、アカウントディレクトリ内
	'mailDbArchve'     => 'maildata-archive.db', //メールアーカイブデータのDB名
	
	//▼データディレクトリの中のファイル
	'addressBookDb'    => 'address-book.db',  //アドレスブックのDB
	'signatureDb'      => 'signature.db',     //メール署名のDB
	
	//▼アカウント設定ファイル
	//絶対指定の場合はスラッシュから書く。
	'accountSettingFile' => 'data/account-setting.php',
	
	//▼メール送信ログファイル
	//絶対指定の場合はスラッシュから書く。
	'mailSendLog'        => 'data/mail-send.log',
	
	//▼DBMS
	'pdoDriver'     => 'sqlite',
	'pdoUser'       => null,
	'pdoPassword'   => null,
	
	//▼メール受信時のタイムアウト
	'mailDownloadTimeout' => 300,
	
	//▼OSのファイルシステムのエンコーディング
	'fileSystemEncoding' => 'UTF-8',
	
	//▼1ページに最大何件メールを表示するか
	'mailVolumeParPage'  => 100,
	
	//▼不明なメールアドレス
	'unknownMailaddress' => 'UNKNOWN',
	
	//▼メールリスト件名最大長
	'mailListSubjectMaxLength' => 60,
	
	//▼メールリスト差出人最大長
	'mailListFromMaxLength' => 30,
	
	//▼メールリスト省略時マーカー
	'mailListCutoffMarker' => '...',
	
	//▼スレッド表示時、最新のものを上に表示する
	'threadReverse' => true,
	
	//▼組み込みカテゴリ
	'built-in-category' => array(
		0 => array(
			'name' => '受信メール',
			'icon' => '&#x2709;',
		),
		10 => array(
			'name' => '保存ボックス',
			'icon' => '&#x2723;',
		),
		90 => array(
			'name' => 'ごみ箱',
			'icon' => '&#x2716;',
		),
	),
	
	//▼デフォルトアイコン
	'default-category-icon' => '・',
	
	//▼アーカイブのアイコン
	'mail-archive-icon' => '■',
	
	//▼メールリストのアーカイブへのリンク文字
	'mail-archive-name' => 'アーカイブ', 
	
	//▼ごみ箱属性
	'trashBox' => 90,
	
	//▼件名なしメールに強制的につける件名
	'untitled' => '(件名なし)',
	
	//▼メールリストに表示する検索タイプ
	'quickSearchList' => array(
		'subject'  => '件名',
		'bodytext' => '本文',
		'FULL'     => '全文',
		'h_from'   => 'From',
		'h_to'     => 'To',
		'attach'   => '添付',
	),
	
	//▼全文検索対象フィールド
	'fullSearchField' => array('subject','h_from','h_to','bodytext','attach'),
	
	//▼メニュー追加ファイル
	'accountListAppendix' => 'config/account-list-appendix.php',
	
);

//■XMPP通知設定
//batch/recieve-mail.php でメールを受信した際、
//新規メールの情報をここで指定したXMPP(Jabber)アカウントに送信することができます。
//メールチェッカーのように使えます。
$XMPP_NOTIFY_CONFIG = array(
	'enable'    => true,                  //XMPP通知有効・無効
	'xmppClass' => 'include/XMPPHP/XMPP.php', //XMPP.phpクラス
	'server'    => 'talk.google.com',     //xmppサーバ
	'port'      => 5222,                  //xmppポート
	'username'  => 'example',             //ログインアカウントID
	'password'  => 'PASSWORD',            //ログインパスワード
	'resource'  => 'xmpp',                //リソース文字列
	'domain'    => 'gmail.com',           //jabberドメイン
	'notify-to' => 'example2@gmail.com',  //通知先アカウント
	'header'    => "【新着メール】\n",    //通知メッセージの文頭に付与
	'footer'    => "http://example.com/uguisu/\n", //通知メッセージの文末に付与
);

//■デイリーサマリレポート設定
//batch/report-daily-summary.php を実行した際に、1日分のメールを特定アドレスに送信することができる。
//各アカウント設定の、report-summary がtrueでないとレポートされない。
$SUMMARY_REPORT_CONFIG = array(
	//▼メールサマリレポートの送信アカウント (アカウント設定ファイル内で設定されていること)
	'fromAccount' => 'user@example.com',
	
	//▼メールサマリレポートの送信アドレス (アカウント設定ファイル内に無くとも良い)
	'toAddress'   => 'user2@example.com',
	
	//▼件名
	'subject'     => '[uguisu]デイリーサマリレポート',
	
	//▼ヘッダー webアプリケーションのURLなど
	'header'      => "http://example.com/uguisu/\n",
	
	//▼件名の最大幅(この値を超えたら切り落とす)
	'maxLength'   => 40,
);	


if($CONFIG['debugLevel']){
	error_reporting(E_ALL);
}
?>
