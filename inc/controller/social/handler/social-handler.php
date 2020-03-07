<?php

require_once('facebook/facebook.php');
require_once('google/google.php');

class MiembroPressSocialHandler {
	var $error = null;
	var $ready = false;
	var $slug_callback, $slug_connect;
	var $label_login, $label_register;
	var $setting_enabled, $setting_app, $setting_secret;
	var $color = "#0085ba";
	function __construct() {
		$this->slug_callback = $this->slug."_callback";
		$this->slug_connect = $this->slug."_connect";
		$this->slug_session = "miembropress_social_".$this->slug;
		$this->label_login = "Log In with ".$this->name;
		$this->label_register = "Register with ".$this->name;
		$this->setting_enabled = "social_".$this->slug."_enabled";
		$this->setting_app = "social_".$this->slug."_app";
		$this->setting_secret = "social_".$this->slug."_secret";
		$this->setting_user = "social_".$this->slug;
		add_action('init', array(&$this, 'init'), 1);
		add_action('init', array(&$this, 'login_social'));
		add_action('init', array(&$this, 'register_social'));
		add_filter('login_message', array(&$this, 'login_message'));
		add_filter('login_form_bottom', array(&$this, 'login_form_bottom'), 100);
		add_action('login_form', array(&$this, 'login_form'));
		add_action('wp_login', array(&$this, 'login_save'), 8, 2);
	}

	public function init() {
		global $miembropress;
		$this->ready = $miembropress->model->setting($this->setting_enabled) && $miembropress->model->setting($this->setting_app) && $miembropress->model->setting($this->setting_secret);
		if (!$this->ready) { return; }
		@session_start();
	}

	function login($wp_user) {
		wp_set_auth_cookie($wp_user->ID);
		do_action('wp_login', $wp_user->user_login, $wp_user);
		$_POST['log'] = $wp_user->user_login;
		header("Location:".home_url());
		die();
	}

	function register($user, $level=null, $txn=null) {
		global $miembropress;
		if ($level == null) {
			return new WP_Error("nolevel", "No level given.");
		}
		$username = $user["username"];
		if (get_user_by('login', $username)) {
			$username = $user["email"];
		}
		if (get_user_by('login', $username)) {
			$username = $user["email"];
			return new WP_Error("userexists", "User already exists.");
		}
		$create = array( "action" => "miembropress_register", "miembropress_level" => $level, "miembropress_username" => $username, "miembropress_email" => $user["email"], "miembropress_firstname" => $user["first_name"], "miembropress_lastname" => $user["last_name"] );
		$output = $miembropress->admin->create($create);
		return $output;
	}

	public function request($query="miembropress_login") {
		global $miembropress;
		$token = $miembropress->model->setting($this->setting_app);
		if (!$token) { return; }
		$result = array("token" => $token, "level" => null, "url" => null);
		$url = home_url("wp-login.php?".$query."=".$this->slug_callback);
		if ($query == "miembropress_register") {
			reset($_GET);
			$level = basename(key($_GET));
			$result["level"] = $level;
			$url = $miembropress->model->signupURL($level, true).' & '.$query.' = '.$this->slug_callback;
		}

		$result["url"] = $url;
		return $result;
	}

	public function process($social_user, $setting_user, $query="miembropress_login") {
		global $miembropress;
		@session_start();
		if (empty($social_user) || !isset($social_user["id"]) || !isset($social_user["email"]) || !is_numeric($social_user["id"]) || !is_email($social_user["email"])) {
			return false;
		} elseif (isset($_SESSION["miembropress_hash"]) && $_SESSION["miembropress_hash"]) {
			$hash = stripslashes($_SESSION["miembropress_hash"]);
			unset($_SESSION["miembropress_hash"]);
		} else {
			$hash = basename(key($_GET));
		}
		if ($query == "miembropress_register" && isset($_SESSION["miembropress_temp"]) && ($temp_user = $miembropress->model->getTempFromTransaction($_SESSION["miembropress_temp"]))) {
			$overwrite = array( "username" => $social_user["username"], "email" => $social_user["email"], "firstname" => $social_user["first_name"], "lastname" => $social_user["last_name"], $setting_user => $social_user["id"] );
			$complete = $miembropress->model->completeTemp($temp_user->ID, $overwrite);
		} elseif ($wp_user = $miembropress->model->userSearch($setting_user, $social_user["id"])) {
			if (!isset($wp_user->ID) || !is_numeric($wp_user->ID)) { return false; }
			if ($query == "miembropress_register") { $level = $miembropress->model->getLevelFromHash($hash);
				$miembropress->model->add($wp_user->ID, $level->ID);
				if (current_user_can("administrator")) { wp_redirect($miembropress->admin->tabLink("members"));
					die();
				} else {
					$this->login($wp_user);
				}
				return;
			} else {
				$this->login($wp_user);
			}
		} elseif (($social_user["verified"] == true) && ($wp_user = get_user_by('email', $social_user["email"]))) {
			if ($existingUser = $miembropress->model->userSearch($setting_user, $social_user["id"])) {
				$miembropress->model->userSetting($wp_user->ID, $setting_user, null);
			}
			$miembropress->model->userSetting($wp_user->ID, $setting_user, $social_user["id"]);
			if ($query == "miembropress_register") {
				$level = $miembropress->model->getLevelFromHash($hash);
				$miembropress->model->add($wp_user->ID, $level->ID);
				if (current_user_can("administrator")) {
					wp_redirect($miembropress->admin->tabLink("members"));
					die();
				} else { $this->login($wp_user); }
			} else {
				$this->login($wp_user);
			}
		} else {
			if ($query == "miembropress_register") {
				$level = $miembropress->model->getLevelFromHash($hash);
				if ($level && isset($level->ID)) {
					$this->register($social_user, $level->ID);
					if (current_user_can("administrator")) {
						wp_redirect($miembropress->admin->tabLink("members"));
						die();
					} else {
						$this->login($wp_user);
					}
				} else {
					return new WP_Error("nolevel", "Membership level does not exist.");
				}
			} else {
				$_SESSION[$this->slug_session] = $social_user["id"];
				$redirect = home_url("wp-login.php?".$query."=".$this->slug_connect);
				header("Location: ".$redirect);
				die();
			}
		}
	}

	public function login_social() {
		if (!isset($_GET["miembropress_login"])) { return; }
		if ($_GET["miembropress_login"] == $this->slug) {
			$this->request();
			return;
		}
		if ($_GET["miembropress_login"] == $this->slug_callback) {
			$this->callback();
			return;
		}
	}

	public function register_social() {
		if (!isset($_GET["miembropress_register"])) { return; }
		if ($_GET["miembropress_register"] == $this->slug) {
			$this->error = $this->request("miembropress_register");
			return;
		}
		if ($_GET["miembropress_register"] == $this->slug_callback) {
			$this->error = $this->callback("miembropress_register");
			return;
		}
	}

	public function button($text=null, $url=null) {
		if ($text == null) {
			$text = $this->label_login;
		}
		if ($url == null) {
			$url = home_url("wp-login.php?miembropress_login=".$this->slug);
		}
		return ' <a id = "miembropress_social" href = "'.htmlentities($url).'" name = "miembropress_login_'.$this->slug.'" class = "button button-primary button-large" style = "display:block; width:100%; max-width:300px; margin-top:10px; text-align:center; background-color: '.$this->color.'; border:none !important; color: #fff; text-decoration: none; text-shadow:0 0 0 !important; box-shadow:0 0 0 ! important; border-radius: 3px; cursor:pointer; vertical-align: middle; height: 30px; line-height: 28px; padding: 0 12px 2px; margin-bottom:5px;"onclick = "jQuery(\'#user_login, #user_pass, #rememberme, #wp_submit, #miembropress_username, #miembropress_firstname, #miembropress_lastname, #miembropress_email, #miembropress_password1, #miembropress_password2\').attr(\'disabled\', true);" > <span class = "dashicons '.$this->dashicon.'" style = "width:40px; height:40px; font-size:30px;"> </span> '.htmlentities($text).' </a> ';
	}

	public function login_form_bottom($buffer) {
		if (!$this->ready) { return $buffer; }
		return $buffer.$this->button();
	}

	function login_save($user_login, $wp_user) {
		global $miembropress;
		if (!isset($_SESSION[$this->slug_session]) || !is_numeric($_SESSION[$this->slug_session])) { return; }
		$user_id = $_SESSION[$this->slug_session];
		$miembropress->model->userSetting($wp_user->ID, $this->setting_user, $user_id);
		unset($_SESSION[$this->slug_session]);
	}

	public function login_form() {
		global $miembropress;
		if (isset($_GET["miembropress_login"]) && !isset($_GET["code"])) { return; }
		if (!$this->ready) { return; }
		echo $this->button();
		?>
		<script type="text/javascript">
			<!--
			jQuery(function() {
				jQuery("#miembropress_social").insertAfter("#wp-submit");
			});
			// -->
		</script>
		<?php
	}

	public function login_message() {
		if (!isset($_GET["miembropress_login"])) { return; }
		if ($_GET["miembropress_login"] != $this->slug_connect) { return; }
		echo ' <p class="message"> This is the first time you are logging in with your '.$this->name.' account. Continue logging in with your existing account to continue. </p> ';
	}

	public function registration() {
		if (!$this->ready) { return; }
		wp_enqueue_style('dashicons');
		$get = $_GET;
		$level = key($get);
		array_shift($get);
		$socialLink = "?".$level."&miembropress_register=".$this->slug;
		echo ' <p align = "center" > '.$this->button($this->label_register, $socialLink).' </p> ';
		if (is_wp_error($this->error)) {
			echo ' <blockquote class = "error" > '.htmlentities($this->error->get_error_message()).' </blockquote> ';
		}
	}
}

?>