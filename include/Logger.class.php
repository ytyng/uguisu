<?php
class Logger{
	public static $debugBuffer = "";
	
	public static function debug($methodName,$debugString=""){
		self::$debugBuffer .= "[".$methodName."]".$debugString."\n";
	}
	
	public static function printDebugMessagePre(){
		echo "<pre>\n";
		echo htmlspecialchars(self::$debugBuffer);
		echo "</pre>\n";
	}
	public static function printDebugMessageComment(){
		echo "<!-- Logger::printDebugMessageComment\n";
		echo htmlspecialchars(self::$debugBuffer);
		echo "-->\n";
	}
	
	
	public static function output($debugLevel){
		if($debugLevel >= 2){
			self::printDebugMessagePre();
		}else if($debugLevel >= 1){
			self::printDebugMessageComment();
		}
	}
}
?>