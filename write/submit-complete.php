<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=<?php echo BASE_ENCODING; ?>" />
<meta http-equiv="Cache-Control" content="no-cache" />
<meta http-equiv="Pragma" content="no-cache" />
<meta http-equiv="Expires" content="0" />
<title>Uguisu: メール送信完了</title>
</head>
<body>
メールを送信しました
<hr />
<?php
if($CONFIG['debugLevel'] >= 1){
	echo "<pre>\n";
	echo htmlspecialchars(UguisuMailSend::getSmtpLog());
	echo "<hr />\n";
	echo htmlspecialchars($_POST['body']);
	echo "<hr />\n";
	echo "</pre>\n";
}
?>	
</pre>
<?php Logger::output($CONFIG['debugLevel']); ?>
</body>
</html>
