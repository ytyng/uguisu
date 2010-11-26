<?
class MailAddressChecker{
	
	private $strDebug = "";
	
	private function debugWrite($methodname,$message){
		$this->strDebug .= "[".$methodname."] ".$message."\n";
	}
	
	public function getDebugMessage(){
		return $this->strDebug;
	}
	
	function trim($mailAddr){
		$this->debugWrite(__METHOD__,"Start.".$mailAddr);
		//$reg = "([a-z0-9+_]|\\-|\\.)+@(([a-z0-9_]|\\-)+\\.)+[a-z]{2,6}";
		$reg = "([A-Za-z0-9+_]|\\-|\\.)+@(([a-z0-9_]|\\-)+\\.)+[a-z]{2,6}";
		if(preg_match("/".$reg."/",$mailAddr,$result)){
			return $result[0];
		}else{
			$this->debugWrite(__METHOD__,"Trim failed. ".$mailAddr);
			return "";
		}
	}
	
	function trimMulti($mailAddr){
		$this->debugWrite(__METHOD__,"Start.".$mailAddr);
		$mailAddr = str_replace(';',',',$mailAddr);
		$aryMailAddr = explode(',',$mailAddr);
		$aryMailAddrOut = array();
		
		foreach($aryMailAddr as $value){
			$result = $this->trim($value);
			if($result){
				$aryMailAddrOut[]=$result;
			}else{
				$this->debugWrite(__METHOD__,"Regist cancel. ".$value);
			}
		}
		
		return join(',',$aryMailAddrOut);
	}
	
	function isEmail($email){
		//$reg = "([a-z0-9+_]|\\-|\\.)+@(([a-z0-9_]|\\-)+\\.)+[a-z]{2,6}";
		$reg = "([A-Za-z0-9+_]|\\-|\\.)+@(([a-z0-9_]|\\-)+\\.)+[a-z]{2,6}";
		if(!(strstr($email,'@') && strstr($email, '.'))) return false;
		if(preg_match("/^".$reg."$/i",$email)) return true;
		//if(preg_match("/^<".$reg.">$/i",$email)) return true;
		if(preg_match("/<".$reg.">$/i",$email)) return true;
		if(preg_match("/^\"".$reg."\"$/i",$email)) return true;
		if(preg_match("/^\".*\"\s*<".$reg.">$/i",$email)) return true;
		
		return false;
	}
	
	
	function isEmailMulti($email){
		$aryEmail = explode(',',$email);
		foreach($aryEmail as $str){
			if(!$this->isEmail(trim($str))) return false;
		}
		return true;
	}

}
?>