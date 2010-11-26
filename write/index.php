<?php
chdir("../");

require("config/config.php");
require($CONFIG['authScript']);

$additionalQuery="";
if(isset($_GET['account'])){
	$additionalQuery.="?account=".htmlspecialchars($_GET['account']);
	if(isset($_GET['reply'])){
		$additionalQuery.="&reply=".rawurlencode($_GET['reply']);
	}
	if(isset($_GET['replyAll'])){
		$additionalQuery.="&replyAll=".rawurlencode($_GET['replyAll']);
	}
}


header("Content-Type: text/html; charset=".BASE_ENCODING);
?><!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=<?php echo BASE_ENCODING; ?>">
<title>Uguisu: メール作成</title>
<link rel="shortcut icon" href="../image/favicon.ico" />
</head>
<frameset cols="30%,*">
	<frame src="address-book.php" name="writePaneLeft" id="writePaneLeft">
	<frame src="submit-form.php<?php echo $additionalQuery; ?>" name="writePaneRight" id="writePaneRight">
</frameset>
</html>