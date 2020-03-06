<?php

class MGAPI2 {
	const marker = '/wlmapi/2.0/';
	const marker_alternate = '/wlmapi/2_0/';
	private $return_type = 'php';
	private $request = '';
	var $method; var $data;
	const ERROR_ACCESS_DENIED = 0x00010000;
	const ERROR_INVALID_AUTH = 0x00010001;
	const ERROR_INVALID_REQUEST = 0x00010002;
	const ERROR_INVALID_RETURN_FORMAT = 0x00010004;
	const ERROR_INVALID_RESOURCE = 0x00010008;
	const ERROR_FORMAT_NOT_SUPPORTED_JSON = 0x00020001;
	const ERROR_FORMAT_NOT_SUPPORTED_XML = 0x00020002;
	const ERROR_METHOD_NOT_SUPPORTED = 0x00040001;
	function __construct($request='EXTERNAL', $method='GET', $data=null) {
		$this->method = $method;
		if ($request == 'EXTERNAL') {
			$request = $_SERVER['REQUEST_URI'];
			$method = $_SERVER['REQUEST_METHOD'];
			if ($method == 'GET') { $data = $_GET; }
			elseif ($method == 'POST') { $data = $_POST; }
			else { parse_str(file_get_contents('php://input'), $data); }
			$this->method = $method;
			$this->data = $data;
		} else { return; }

		if (strpos($request, MGAPI2::marker) !== false) {
			$explode = explode(MGAPI2::marker, $request, 2);
			$pop = array_pop($explode);
			$request = explode('/', $pop);
		} elseif (strpos($request, MGAPI2::marker_alternate) !== false) {
			$explode = explode(MGAPI2::marker_alternate, $request, 2);
			$pop = array_pop($explode); $request = explode('/', $pop);
		}

		$this->return_type = strtoupper(array_shift($request));
		$accepted_return_types = array('PHP');
		if (!in_array($return_type, $accepted_return_types)) {
			MGAPI2::process_result($this->error(MGAPI2::ERROR_INVALID_RETURN_FORMAT));
		}

		$this->request = implode( '/', $request );
		$functions = array();
		$parameters = array();
		while (!empty($request)) {
			$functions[] = trim(strtolower(array_shift($request)));
			if (!empty($request)) {
				$parameters[] = trim(array_shift($request));
			}
		}
		$functions = array_diff($functions, array(''));
		$function = '_' . implode('_', $functions);
		$result = $this->parse($function, $parameters);
		$this->output($result);
	}
	function output($result) {
		if ($this->return_type != 'PHP') { die(); }
		header('Content-type: text/plain');
		$output = serialize($this->process_result($result));
		echo $output;
		die();
	}

	function parse($function, $parameters) {
		if (!method_exists($this, $function)) {
			$this->output($this->error(MGAPI2::ERROR_INVALID_REQUEST));
			return;
		}
		if ($function == '_resources' || $function == '_auth') {
			$result = call_user_func(array($this, $function));
		} else {
			$key = $this->auth_key();
			$cookie = $this->auth_cookie();
			$result = call_user_func_array(array($this, $function), $parameters);
		}
		$this->output($result);
	}

	function error($error) {
		return array('ERROR_CODE' => $error, 'ERROR' => MGAPI2::get_error_msg($error));
	}

	private function get_error_msg($error) {
		if ($error == MGAPI2::ERROR_METHOD_NOT_SUPPORTED) { return 'Method Not Supported';}
		if ($error == MGAPI2::ERROR_ACCESS_DENIED) { return 'Access Denied, not authenticated'; }
		if ($error == MGAPI2::ERROR_INVALID_AUTH) { return 'Access denied, invalid authentication'; }
		if ($error == MGAPI2::ERROR_INVALID_REQUEST) { return 'Page not found, invalid method'; }
		if ($error == MGAPI2::ERROR_INVALID_RETURN_FORMAT) { return 'Page not found, invalid return format'; }
		if ($error == MGAPI2::ERROR_INVALID_RESOURCE) { return 'Page not found, invalid resource'; }
		if ($error == MGAPI2::ERROR_FORMAT_NOT_SUPPORTED_XML) { return 'Unsupported media type'; }
		if ($error == MGAPI2::ERROR_FORMAT_NOT_SUPPORTED_JSON) { return 'Unsupported media type'; }
	}

	function process_result($result) {
		if (!is_array($result)) { $result = array(); }
		if (!isset($result['ERROR_CODE']) || empty($result['ERROR_CODE'])) { $success = 1; }
		else { $success = 0; }
		$result = array('success' => $success) + $result; return $result;
	}

	private function auth_key() {
		global $miembropress;
		static $hash = 0;
		if (empty($hash)) {
			$lock = null;
			if (isset($_COOKIE["lock"])) {
				$lock = $_COOKIE["lock"];
			}
			$key = $miembropress->model->setting("api_key");
			if (!$key || !$lock) { return false; }
			$hash = md5($lock . $key);
		}
		return $hash;
	}

	private function auth_cookie() {
		static $cookie = 0;
		if ( empty( $cookie ) ) {
			$cookie = md5('WLMAPI2' . $this->auth_key() );
		}
		return $cookie;
	}

	private function _auth() {
		if ($this->return_type != 'PHP') { return $this->error(MGAPI2::ERROR_METHOD_NOT_SUPPORTED); }
		$hash = $this->auth_key();
		$cookie = parse_url(home_url());
		if (empty($cookie['path'])) { $cookie['path'] = '/'; }
		if ($this->method == 'GET') {
			$lock = md5(strrev(md5($_SERVER['REMOTE_ADDR'] . microtime())));
			@setcookie('lock', $lock, 0, $cookie['path']);
			return array('lock' => $lock);
		}
		if ($this->method == 'POST') {
			$cookie_name = $this->auth_cookie();
			if ($this->data['key'] !== $hash) { return $this->error(MGAPI2::ERROR_INVALID_AUTH ); }
			@setcookie($cookie_name, $hash, 0, $cookie['path']);
			return array('key' => $hash);
		}
		return $this->error(MGAPI2::ERROR_METHOD_NOT_SUPPORTED);
	}

	private function _members() {
		global $miembropress;
		if ($this->return_type != 'PHP') { return $this->error(MGAPI2::ERROR_METHOD_NOT_SUPPORTED); }
		if ($this->method == 'GET') {
			$list = array();
			foreach ($miembropress->model->getMembers() as $member) {
				if (!isset($member->user_login)) { continue; }
				$list[] = array("id"=>$member->ID, "user_login"=>$member->user_login, "user_email"=>$member->user_email);
			}
			return $list;
		}

		if ($this->method == 'POST') {
			if (!isset($this->data['user_login']) || empty($this->data['user_login'])) {
				return $this->error(MGAPI2::ERROR_INVALID_REQUEST);
			}
			$vars = array( 'action' => 'miembropress_register', 'miembropress_level' => $this->data['Levels'][0], 'miembropress_username' => $this->data['user_login'], 'miembropress_password1' => $this->data['user_pass'], 'miembropress_email' => $this->data['user_email'], 'miembropress_firstname' => $this->data['first_name'], 'miembropress_lastname' => $this->data['last_name'] );
			$result = $miembropress->admin->create($vars, true);
			return array('member' => $result);
		}
	}

	private function _levels_members($level_id, $member_id=null) {
		global $miembropress;
		if ($this->return_type != 'PHP') { return $this->error(MGAPI2::ERROR_METHOD_NOT_SUPPORTED); }
		if ($this->method == 'GET') {
			$list = array();
			foreach ($miembropress->model->getMembers("levels=".$level_id) as $member) {
				if (!isset($member->user_login)) { continue; }
				$list[] = array("id"=>$member->ID, "user_login"=>$member->user_login, "user_email"=>$member->user_email);
			}
			mail("robert.plank@gmail.com", "members levels list",
			var_export($list, true));
			return $list;
		}
		if ($this->method == 'POST') {
			return $this->error(MGAPI2::ERROR_METHOD_NOT_SUPPORTED);
		}
	}
}

?>