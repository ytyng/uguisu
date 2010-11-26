<?php
require('config/config.php');
require($CONFIG['authScript']);

require('include/UguisuSelfTest.class.php');
UguisuSelfTest::stopOrContinue();

header('Content-Type: text/html; charset='.BASE_ENCODING);
?><!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN">
<html>
<head>
<title>Uguisu</title>
<link rel="shortcut icon" href="image/favicon.ico" />
</head>
<frameset cols="200px,*">
	<frame src="pane/account-list.php" name="paneLeft" id="paneLeft">
	<frameset rows="40%,*">
	<frame src="about:blank" name="paneTop" id="paneTop">
	<frame src="about:blank" name="paneBottom" id="paneBottom">
	</frameset>
</frameset>
</html>
