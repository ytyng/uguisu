<?php
/*
 * pop3-class.php By TOMO (2002/07/06) 
 *
 * [Version] : 1.1.0 (2002/07/06)
 * [URL]     : http://www.spencernetwork.org/
 * [E-MAIL]  : groove@spencernetwork.org
 *
 * pop3-class.php is free but without any warranty.
 * use this script at your own risk.
 *
 */

/* Interface 

[ Constractor ]

    pop3(string server, string user, string pass [, int port = 110])

[ Property ]

    bool apop
    bool debug
    int time_out
    string server 
    int port
    string user
    string pass

[ Method ]

    bool open(void)
    bool close(void)
    array get_stat(void)
    array get_list(int num = 0)
    array get_uidl(int num = 0)
    bool dele(int num)
    string top(int num, int line)
    string head(int num)
    string retr(int num)
    bool noop(void)
    bool rset(void)

*/

class pop3
{
	/* Public variables */
	var $apop;      // use APOP
	var $debug;     // print debug message
	var $time_out;  // fsockopen() time-out
	var $server;    // pop3 server
	var $port;      // pop3 port number
	var $user;      // login user
	var $pass;      // login password

	/* Private variables */
	var $_sock;

	function pop3($server, $user, $pass, $port = 110)
	{
		$this->apop     = FALSE;
		$this->debug    = FALSE;
		$this->time_out = 30;
		$this->server   = $server;
		$this->port     = $port;
		$this->user     = $user;
		$this->pass     = $pass;

		$this->_sock    = FALSE;
	}

	/* Public functions */
	function open()
	{
		if ($this->_sock) $this->close();

		$this->_debug("Trying to ".$this->server.":".$this->port." ...\n");
		$this->_sock = @fsockopen($this->server, $this->port, $errno, $errstr, $this->time_out);
		if (!$this->_sock) {
			$this->_debug("Error: Could not connect to ".$this->server."\n");
			$this->_debug("Error: ".$errstr." (".$errno.")\n");
			return FALSE;
		}
		if (!$this->_ok($resp)) {
			$this->_debug("Error: Could not connect to ".$this->server."\n");
			return FALSE;
		}

		if (ereg("<.+>", $resp, $match)) {
			$time_stamp = $match[0];
		} else {
			$time_stamp = '';
		}

		$this->_debug("Connected to ".$this->server."\n");

		if (!$this->_login($time_stamp)) {
			$this->_debug("Error: Login failed\n");
			return FALSE;
		}
		$this->_debug("Login succeeded\n");
		return TRUE;
	}

	function get_stat()
	{
		if (!$this->_putcmd('STAT')) {
			return FALSE;
		}
		if (!$this->_ok($resp)) {
			return FALSE;
		}
		list($num, $octet) = explode(' ', $resp);
		return array($num, $octet);
	}

	function get_list($num = 0)
	{
		return $this->_get_list('LIST', $num);
	}

	function dele($num)
	{
		if (!$this->_putcmd('DELE', $num)) {
			return FALSE;
		}
		return $this->_ok($resp);
	}

	function get_uidl($num = 0)
	{
		return $this->_get_list('UIDL', $num);
	}

	function top($num, $line)
	{
		if (!$this->_putcmd('TOP', $num.' '.$line)) {
			return FALSE;
		}
		if (!$this->_ok($resp)) {
			return FALSE;
		}
		return $this->_getresp();
	}

	function head($num)
	{
		return $this->top($num, 0);
	}

	function retr($num)
	{
		if (!$this->_putcmd('RETR', $num)) {
			return FALSE;
		}
		if (!$this->_ok($resp)) {
			return FALSE;
		}
		return $this->_getresp();
	}

	function noop()
	{
		if (!$this->_putcmd('NOOP')) {
			return FALSE;
		}
		return $this->_ok($resp);
	}

	function rset()
	{
		if (!$this->_putcmd('RSET')) {
			return FALSE;
		}
		return $this->_ok($resp);
	}

	function close()
	{
		if (!$this->_sock) {
			$this->_debug("Error: Socket is not opened\n");
			return FALSE;
		}

		if (!$this->_putcmd('QUIT')) {
			return FALSE;
		}
		if (!$this->_ok($resp)) {
			return FALSE;
		}

		$ret = fclose($this->_sock);
		if ($ret) {
			$this->_debug("Disconnected from remote host\n");
			return TRUE;
		} else {
			$this->_debug("Error: Could not disconnect from remote host\n");
			return FALSE;
		}
	}

	/* Private Functions */

	function _login($time_stamp)
	{
		if ($this->apop) {
			return $this->_login_apop($time_stamp);
		} else {
			return $this->_login_pop();
		}
	}

	function _login_pop()
	{
		if (!$this->_putcmd('USER', $this->user)) {
			return FALSE;
		}
		if (!$this->_ok($resp)) {
			return FALSE;
		}

		if (!$this->_putcmd('PASS', $this->pass)) {
			return FALSE;
		}
		if (!$this->_ok($resp)) {
			return FALSE;
		}

		return TRUE;
	}

	function _login_apop($time_stamp)
	{
		$digest = md5($time_stamp.$this->pass);

		if (!$this->_putcmd('APOP', $this->user." ".$digest)) {
			return FALSE;
		}
		if (!$this->_ok($resp)) {
			return FALSE;
		}

		return TRUE;
	}

	function _putcmd($cmd, $arg = '')
	{
		if (!$this->_sock) {
			$this->_debug("Error: Socket is not opened\n");
			return FALSE;
		}

		if ($arg != '') {
			$cmd = $cmd.' '.$arg;
		}

		if (!fputs($this->_sock, $cmd."\r\n")) {
			$this->_debug("Error: Could not put command $cmd\n");
			return FALSE;
		}

		$this->_debug('> '.$cmd."\n");

		return TRUE;
	}

	function _ok(&$resp)
	{
		if (!$this->_sock) {
			$this->_debug("Error: Socket is not opened\n");
			return FALSE;
		}

		$line = trim(fgets($this->_sock, 512));
		$this->_debug('< ' . $line . "\n");

		$tmp = split(' ', $line, 2);
		if (isset($tmp[1])) {
			$resp = $tmp[1];
		} else {
			$resp = '';
		}

		if (substr($line, 0, 1) != '+') {
			return FALSE;
		}

		return TRUE;
	}

	function _getresp()
	{
		if (!$this->_sock) {
			$this->_debug("Error: Socket is not opened\n");
			return FALSE;
		}

		$response = '';
		$line = '';
		while ($line != ".\r\n") {
			$line = ereg_replace("^\\.\\.", '.', $line);
			$response .= $line;
			$line = fgets($this->_sock, 512);
		}
		$this->_debug(str_replace("\r\n", "\n", $response));
		return $response;
	}

	function _debug($message)
	{
		if ($this->debug) {
			echo $message;
		}
	}

	function _get_list($cmd, $num = 0)
	{
		if ($num < 1) {
			$arg = '';
		} else {
			$arg = $num;
		}

		if (!$this->_putcmd($cmd, $arg)) {
			return FALSE;
		}
		if (!$this->_ok($resp)) {
			return FALSE;
		}

		if ($num < 1) {
			$resp = trim($this->_getresp());
		}

		if ($resp == '') {
			return array('');
		}

		$data = explode("\r\n", $resp );
		reset($data);
		while (list($key, $value) = each($data)) {
			list($no, $uid) = explode(' ', $value);
			$list[$no] = $uid;
		}
		return $list;
	}
}
?>
