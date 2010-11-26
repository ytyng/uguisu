pop3-class.php By TOMO


[Version] : 1.1.0 (2002/07/06)
[URL]     : http://www.spencernetwork.org/
[E-MAIL]  : groove@spencernetwork.org


pop3クラスはPOP3(Post Office Protocol version 3)を使用してPHPスクリプトから
外部のPOP3サーバへアクセスするためのクラスで、POP3の基本機能を提供します。

fsockopen()を使用し、拡張モジュールの機能を使用していないので特別な環境
でなくても使用することができます。


- インターフェース

  [ コンストラクタ ]

    pop3(string server, string user, string pass [, int port = 110])

        サーバ名、ユーザ名、パスワードを指定してください。
        ポート番号は省略可能で、省略時は110が設定されます。


  [ プロパティ ]

    bool apop :
        認証にAPOPを使用するかどうかのフラグです。
        APOPを使用する場合はTRUEにしてください。デフォルトはFALSEです。

    bool debug :
        デバッグ用のメッセージを出力するかどうかのフラグです。
        サーバとの通信内容などを出力します。
        パスワードもそのまま出力するので、注意してください。
        デフォルトはFALSEです。

    int time_out :
        fsockopen()で接続を試みるときのタイムアウト設定です。
        デフォルトは30秒です。

    string server :
        POP3サーバ名またはIPアドレスです。
        コンストラクタによって設定されます。

    int port :
        POP3ポート番号です。デフォルトは110です。通常は変更する必要ありません。

    string user :
        POP3ユーザー名(アカウント名)です。
        コンストラクタによって設定されます。

    string pass :
        user用のPOP3パスワードです。
        コンストラクタによって設定されます。


    [ メソッド ]

    bool open(void)

        serverへ接続し、userとpassを使用してログインします。
        成功時にTRUEを返し、失敗時にFALSEを返します。

    bool close(void)

        serverからログオフして接続を切断します。
        成功時にTRUEを返し、失敗時にFALSEを返します。

    array get_stat(void)

        サーバ上のメッセージ数と合計サイズ(バイト)を取得し配列で返します。
        取得失敗時にFALSEを返します。

    array get_list(int num = 0)

        引数 num で指定されたメッセージのシリアル番号とサイズ(バイト)を取得し
        配列で返します。
        引数 num が省略された場合サーバ上の全てのメッセージのシリアル番号と
        サイズのリストを配列で返します。取得失敗時にFALSEを返します。

    array get_uidl(int num = 0)

        引数 num で指定されたメッセージのユニークIDを取得し配列で返します。
        引数 num が省略された場合サーバ上の全てのメッセージのユニークIDリスト
        を配列で返します。取得失敗時にFALSEを返します。

    bool dele(int num)

        引数 num で指定されたメッセージをサーバ上から削除します。
        実際には削除フラグを立てるだけです。削除はログオフ時に行なわれます。
        後述する rset() を使用することでキャンセルすることができます。
        取得失敗時にFALSEを返します。

    string top(int num, int line)

        引数 num で指定したメッセージのヘッダとメッセージボディ最初のline行
        を取得します。引数 line に0を指定した場合ヘッダのみを取得します。次
        のhead()と同様の動作です。取得失敗時にFALSEを返します。

    string head(int num)

        引数 num で指定したメッセージのヘッダを取得します。
        取得成功時にTRUEを返し、失敗時にFALSEを返します。
        top(num 0) と同じです。

    string retr(int num)

        引数 num に指定したメッセージ全体を取得します。失敗時にFALSEを返します。

    bool noop(void)

        サーバへNOOPコマンドを送信します。
        成功時にTRUEを返し、失敗時にFALSEを返します。

    bool rset(void)

        削除フラグをキャンセルします。
        成功時にTRUEを返し、失敗時にFALSEを返します。


- 利用規定

  - 著作権は放棄しませんが、スクリプトの一部または全部を使用・改造・再配布
    することは自由です。

  - このスクリプトを使用したことで生じたいかなる不都合・損害にも作者は一切
    その責任を負いません。
