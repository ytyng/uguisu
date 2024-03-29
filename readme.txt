
	uguisu | PHPメーラー
	
	ytyng.com
	
	2009-07-15 メール送信時に Date: ヘッダをつけるようにした
	2009-09-07 メールアーカイブ機能を追加
	2009-09-14 カテゴリごとの自動削除日時設定を追加
	
	
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

[概要]
	
	PHPで動作するメーラーです。
	
	テスト用のメールアカウントや、所謂「捨てメアド」など、
	それほど重要ではない多数のメールアドレスを、
	(場合によっては複数人で)同時運用することを想定しています。
	
	同梱の受信スクリプトで、受信メール概要をXMPPで通知することができます。
	同梱のスクリプトで、24時間以内のメールの概要をメールで通知することができます。
	
	SSLでの送受信を行うには、PHPのSSLモジュールの設定が有効になっている必要があります。。
	SMTP認証は、LOGIN と CRAM-MD5 に対応しています。
	
	
[要件]
	
	PHP5.2
	PDO + SQLite(3)
	mbstring, imap モジュールを使います
	
	
[注意]
	
	メールアカウントのパスワードや受信データを、平文で保存します。
	管理には十分気をつけてください。
	
	機密文章の送受信は行わないでください。
	いかなる損害に対しても、開発者 ytyng.com は責任を負いません。
	
	
[インストール方法]
	
	圧縮ファイルを解凍後、webサーバの公開ディレクトリに設置してください。
	データ保存用ディレクトリ(data)は、webサーバの実行ユーザーが書き込みが行える必要があります。
	簡易的には、Linuxの場合はパーミッション777にしてください。
	Windowsの場合は、Everyoneの書き込み権限を与えてください。
	
	index.phpにアクセスすることで、ページが表示されます。
	
	
[設定方法]
	
	設定ファイルをエディタで編集してください。
	設定ファイルは、config/config.php です。
	
	デフォルトでは、データ保存ディレクトリは data/ となっていますが、
	webサーバが表示できないディレクトリに変更することを強く推奨します。
	
	アカウント設定ファイルも同様に編集を行ってください。
	デフォルトでは data/account-setting.php です。
	
	
[認証設定]
	
	認証プログラムは同梱しておりません。
	
	ただし、config/auth.php は各ページの表示時に必ず動作するようになっており、
	認証スクリプトを記述することを想定しています。
	
	Apacheなどのwebサーバで認証を行う場合は、config/auth.phpは空でかまいません。
	
	
[同梱バッチプログラム]
	
	batch/ 以下に、バッチ動作用のプログラムが同梱してあります。
	設定は、config/config.php の下部にあります。
	
	・recieve-mail.php
		メールを巡回受信するスクリプトです。cronで定期的に実行することを想定しています。
		メールを受信時に、XMPP(Jabber)で通知を送信することができます。
	
	・recieve-mail.wsf
		recieve-mail.php を定期実行するためのWindows用スクリプトです。
		実行すると常駐し、一定時間おきにメールを受信します。
		詳細は、エディタで開いて見てください。
	
	・xmpp-test.php xmpp-test.wsf
		XMPP(Jabber)通知のテスト用スクリプトです。
		設定ファイルで記述したアカウントに、XMPPのテストメッセージを送信します。
	
	・report-daily-summary.php report-daily-summary.wsf
		過去24時間分の受信メールの概要を、指定アドレスに向けてメール送信します。
		携帯電話のアドレスに送信することを意識しています。


[モバイル版]
	
	m/ にアクセスすると、モバイル版の表示ができます。
	メールチェックのみ可能で、送信はできません。
	

[TODO]
	
	
	
[バグというか仕様]
	
	階層化したマルチパートメッセージは、下ペインで文章を表示できません。
	添付ファイルとしてダウンロードすることはできるので、添付ファイルとしてダウンロード後、
	テキストエディタなどで読んでください。
	
	バグを発見したら、info@ytyng.com までご連絡くださると助かります。
	
	
[同梱モジュールと謝辞]
	
	POP3受信クラスとして、「pop3-class.php By TOMO」( http://www.spencernetwork.org/ )
	を使用しています。
	
	XMPP送信クラスとして、「XMPPHP: The PHP XMPP Library」( http://code.google.com/p/xmpphp/ )?
	を使用しています。
	
	素晴らしいモジュールの開発者達に乾杯。
	
	
	そして、大変使いやすいソフトウェアを公開されている、
	The PHP Group, Apache Software Foundation, Fedora project, Microsoft, phpspot 他
	多くの先人達に感謝。

