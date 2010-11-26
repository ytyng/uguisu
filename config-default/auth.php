<?php
/*

uguisu用 認証スクリプト
各ページのロード時に必ず呼ばれる。

PHP内で認証を行う場合は、そのスクリプトをここに書いてください。

PHP以外で、例えばapacheでHTTP認証する場合や、
そもそも認証が不要な場合は、このスクリプトは空で良いです。

*/

class AuthScript{
	
	/**
	 * CASE1:特定のアドレスのみ許可する場合
	 */
	public static function filterIpAddress(){
		
		$allowIpAddress = array(
			"127.0.0.1",
			"192.168.0.2",
			"192.168.0.3",
		);
		
		if(in_array($_SERVER['REMOTE_ADDR'],$allowIpAddress)){
			//許可リストにIPアドレスがある場合は動作を許可
		}else{
			//許可リストにIPアドレスが無い場合は強制終了
			exit("[ERROR] Have no parmission.");
		}
	}
	
}


//AuthScript::filterIpAddress();


?>