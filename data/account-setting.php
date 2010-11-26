<?php

$ACCOUNT = array();

// =============================================================================
/*
■アカウントの基本設定
POP,SMTPでSSLを使う場合は、pop-server,smtp-server を、
ssl://サーバアドレス という表記にしてください。
ただし、PHPがSSLでのfsockopen()を使える設定である必要があります。
たとえば、GmailやHotmailは、SSLでないと受信できません。
*/
$ACCOUNT['user@example.com'] = array(
	'pop-server'   => "pop.example.com",
	//'pop-server'   => "ssl://pop.example.com",
	'pop-id'       => "user",
	'pop-passwd'   => "PASSWORD",
	'pop-port'     => 110,
	'smtp-server'  => "smtp.example.com",
	//'smtp-server'  => "ssl://smtp.example.com",
	'smtp-port'    => 587,
	'smtp-authtype'=> "LOGIN",
	'smtp-id'      => "user",
	'smtp-passwd'  => "PASSWORD",
	'smtp-a-cc'    => "",   //常時Ccアドレス
	'smtp-a-bcc'   => "<user@example.com>,<forward@example.com>", //常時Bccアドレス
	'save-source'  => true,   //trueの場合、メールのソースを保存する
	'server-keep-days' => 7,  //この日数だけサーバに残す。0でダウンロード即時削除、-1で永久
	'reload-time'      => 900,//この秒数内であれば、再度メールリストを表示しても自動リロードしない
	'auto-trash-days'  => 30, //「ごみ移」ボタンで自動的に削除する期間閾値。この日数だけ昔のメールはごみ箱に移動する。
	'default-encoding' => "iso-2022-jp", //文字コードが不明な時はこれでデコード
	'notify'           => true, //trueの場合、XMPPメールチェック報告の対象
	'report-summary'   => true, //trueの場合、サマリメール送信の対象
	'strong-unread'    => true, //trueの場合、未読メール数を強調
);

/*
■カテゴリ(フォルダ)の設定
・カテゴリID => array('name' => カテゴリ名,'icon'=>アイコン) という形で指定。
・カテゴリIDは100以上の数値
*/
$ACCOUNT['user@example.com']['category'] = array(
	100 => array(
		'name' => "spam",
		'icon' => "&#x274F;", //アイコン記号。HTMLタグ可
	),
	110 => array(
		'name' => "user2",
		'icon' => "&#x274F;", //アイコン記号。HTMLタグ可
	),
);

/*
■受信時自動振り分けの設定
・key には、以下のキー(フィールド)を指定可能
subject,from,from_real,to,to_real,cc,to_cc,from_to,from_to_cc,bodytext
from_real,to_realは、それぞれ正規化(パターン検出)したメールアドレスを表す。
それ以外のメールアドレス関係のキーは、ヘッダに入っているものをそのまま比較する。
・mode には、以下のモードを指定可能
startsWith(前方一致),endsWith(後方一致),contains(部分一致),equals(完全一致)
*/
$ACCOUNT['user@example.com']['dispatch'] = array(
	array(
		//▼この例では、件名が[spam]から始まるメールを受信した場合、spamカテゴリに移動します。
		'to'    => 100,           //転送先カテゴリのID
		'key'   => "subject",     //振り分け対象キー。
		'mode'  => "startsWith",  //検索モード
		'value' => "[spam]",      //検索する文字列
	),
	array(
		//▼この例では、user2@example.comからのメールを受信した場合、user2カテゴリに移動します。
		'to'    => 110,           //転送先カテゴリのID
		'key'   => "from_to",     //振り分け対象キー。
		'mode'  => "contains",    //検索モード
		'value' => "user2@example.com", //検索する文字列
	),
);

/*
■クイックフィルタの設定
アカウントリスト(左ペイン)に表示され、ワンクリックで検索を実行可能。
・key には、以下のキー(フィールド)を指定可能。上記のカテゴリ設定と微妙に違うので注意
subject,from,from_realto,to_real,cc,header,bodytext,full
その場合、modeには以下の値を設定可能
contains (部分一致検索)

また、keyを etime 、modeに new-second を指定して、、
valueに整数を指定することで、「最新○秒以内のメール」を検索できる。
*/
$ACCOUNT['user@example.com']['filter'] = array(
	'3日以内' => array(               //フィルタ名。任意
		'key'      => "etime",        //フィルタ対象キー
		'mode'     => "new-second",   //検索モード
		'value'    => 86400 * 3,      //検索値
		'category' => 0,              //対象カテゴリ、0はデフォルトメールボックス
		'icon'     => "▼",          //アイコン記号。HTMLタグ可
	),
	'送信メール' => array(               //フィルタ名。任意
		'key'      => "from",        //フィルタ対象キー
		'mode'     => "contains",   //検索モード
		'value'    => "user@example.com",      //検索値
		'category' => 0,              //対象カテゴリ、0はデフォルトメールボックス
		'icon'     	 => "▼",          //アイコン記号。HTMLタグ可
	),
);

// =============================================================================

/*
複数のアカウントを使う場合は、$ACCOUNT配列に追加していってください。

$ACCOUNT['user2@example.com'] = array(
	'pop-server'   => "ssl://pop.example.com",
	'pop-id'       => "user",
	'pop-passwd'   => "PASSWORD",
	:
	以下略
	

*/

?>