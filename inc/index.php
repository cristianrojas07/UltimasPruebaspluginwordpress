<?php

// FOR HREF
define( 'base_url_inc' , plugin_dir_url( __FILE__ ));

// FOR REQUIRE
define( 'path_url_inc' , plugin_dir_path( __FILE__ ));

require_once('admin/admin.php');
require_once('protection/protection.php');
require_once('model/model.php');
require_once('view/view.php');
require_once('social/social.php');
require_once('shortcodes/shortcodes.php');
require_once('mgapi/mgapi.php');
require_once('mgapi/mgapi2.php');

$miembropress = new MemberGenius();

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
		register_activation_hook(path_url . '/miembro-press.php', array(&$this->model, 'install'));
		add_action('plugins_loaded', array(&$this->model, 'maybeInstall'));
		register_deactivation_hook(path_url . '/miembro-press.php', array(&$this->model, 'uninstall'));

		@session_start();
		add_action('init', array(&$this, 'init'));
		add_action('wp_enqueue_scripts', array(&$this->view, 'enqueue_scripts'));
		add_action('after_setup_theme', array(&$this->view, 'login'));
		add_action('wp_logout', array(&$this->view, 'logout'));
		add_action('wp_footer', array(&$this->view, 'autoresponder'));
		add_filter('pre_get_posts', array(&$this->view, 'order'));
		add_action('admin_bar_menu', array( $this, "admin_bar_switch_user" ), 50 );
		//add_action('admin_bar_menu', array( $this, "admin_bar_switcher" ), 35 );
		add_action('wp_before_admin_bar_render', array(&$this, 'admin_bar_remove_profile'));
		add_action('init', array(&$this, 'remove_profile_access'));
		add_action('after_setup_theme', array(&$this, 'widgets_init'));
		add_filter('author_link', '__return_zero');
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

		if ($this->model->setting("profile") == 1 || current_user_can('administrator')) { return; }

		if (is_admin() && ! current_user_can( 'administrator' ) && !(defined( 'DOING_AJAX') && constant("DOING_AJAX"))) {
			wp_redirect(admin_url()); 
			exit;
		}
	}


	public function widgets_init() {
		$widgets = get_option( 'sidebars_widgets' );
		$first = null;

		foreach ($widgets as $key => $widget) {

			if ($key == "wp_inactive_widgets") {
				$inactive = array_search("miembropress", $widget);
				if ($inactive !== null) {
					unset($widgets["wp_inactive_widgets"][$inactive]);
				}
				continue;
			}

			if (!$first) {
				$first = $key;
			}

			if (is_array($widget) && in_array("miembropress", $widget)) { return; }

		}

		if ($first) {
			array_unshift($widgets[$first], "miembropress");
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
		global $wp_admin_bar; 
		
		if ($this->model->setting("profile") == 1 || current_user_can('administrator')) { return; }

		$wp_admin_bar->remove_menu('edit-profile');
		$logoutMenu = $wp_admin_bar->get_node('logout');
	}

	public function admin_bar_switch_user() {
		global $wp_admin_bar; 
		$wp_admin_bar->remove_menu('wp-logo');
		$logout_menu = $wp_admin_bar->get_node('logout');
		if ($logout_menu) {
			$wp_admin_bar->remove_menu('logout');
		}

		$wp_admin_bar->add_menu(array( 'parent' => 'user-actions', 'id' => 'switch-user', 'title' => __( 'Switch User'), 'href' => add_query_arg(array('miembropress_action'=>'switch_user'), admin_url()) ));

		if ($logout_menu) {
			$wp_admin_bar->add_menu($logout_menu); }
	}

	public function register_widgets() {
		wp_register_sidebar_widget( 'miembropress', 'MiembroPress', array(&$this->view, 'widget'), array('description' => 'Muestre un formulario de inicio/cierre de sesión para los miembros de su sitio de membresía.') );
		wp_register_widget_control( 'miembropress', 'MiembroPress', array(&$this->view, 'widget_control'), array('id_base' => 'miembropress') );
	}

	private function placeholder() {
		global $wpdb;
		$placeholder = get_page_by_path('miembropress');
		$content = array( 'post_title' => 'MiembroPress', 'post_type' => 'page', 'post_name' => 'miembropress', 'post_content' => 'Do not edit.', 'post_status' => 'publish', 'post_author' => 1, 'comment_status' => 'closed' );
		

		// EVITAR QUE SE CREE PÁGINA MIEMBROPRESS
		/*
		if (!$placeholder) {
			wp_insert_post($content);
		}

		if ($placeholder->post_status != "publish") {
			$content["ID"] = $placeholder->ID; 
			wp_update_post($content);
		}
		*/
	}

	function init() {
		$current_user = wp_get_current_user();
		global $miembropress;
		if (strpos($_SERVER["REQUEST_URI"], '/miembropress/') !== false) {
			MemberGenius::clearCache();
		}
		$miembropress_givenuser = null;
		$miembropress_givenpass = null;
		remove_all_filters('retrieve_password');
		
		// ESTO AGREGA UNA PAGINA AL MENU
		$this->placeholder();

		if (is_user_logged_in()) {
			if (!defined("DONOTCACHEPAGE")) {
				define("DONOTCACHEPAGE", 1);
			}
		}

		if (isset($_GET["miembropress_action"]) && $_GET["miembropress_action"] == "switch_user") {
			wp_logout();
		}

		if (!function_exists('current_user_can') && file_exists(constant("ABSPATH") . constant("WPINC") . "/capabilities.php")) {
			@require_once(constant("ABSPATH") . constant("WPINC") . "/capabilities.php");
		}

		if (function_exists("current_user_can") && current_user_can("manage_options") && isset($_REQUEST["miembropress_action"]) && $_REQUEST["miembropress_action"] == "download") {
			$this->view->download();
		}

		if (count($_POST) == 0 && (is_admin() || $_SERVER["REMOTE_ADDR"] == $_SERVER["SERVER_ADDR"])) {
		} else {
			if (isset($_POST["wppp_username"]) && isset($_POST["wppp_password"])) {
				setcookie('wppp_username', $_POST["wppp_username"], 0, '/');
				setcookie('wppp_password', $_POST["wppp_password"], 0, '/');
				$miembropress_givenuser = $_POST['wppp_username'];
				$miembropress_givenpass = $_POST['wppp_password'];
			}elseif (isset($_COOKIE['wppp_username']) && isset($_COOKIE['wppp_password'])) {
				$miembropress_givenuser = $_COOKIE['wppp_username']; $miembropress_givenpass = $_COOKIE['wppp_password'];
			}
			$miembropress_user = get_option("wppp_username");
			$miembropress_pass = get_option("wppp_password");
			$miembropress_validated = ($miembropress_givenuser == $miembropress_user && $miembropress_givenpass == $miembropress_pass);

			if (!$miembropress_user || !$miembropress_pass) {
				$miembropress_validated = false;
			}

			if (is_user_logged_in()) {
				$miembropress_validated = true;
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
					if (isset($_SESSION["miembropress_temp"])) {
						$temp = $_SESSION["miembropress_temp"];
						unset($_SESSION["miembropress_temp"]);
					} elseif (isset($_POST["miembropress_temp"])) {
						$temp = $_POST["miembropress_temp"];
					}
					if (is_user_logged_in() && !isset($_REQUEST["complete"]) && !current_user_can("manage_options")) {
						$current_user = wp_get_current_user();
						$this->model->add($current_user->ID, $registerLevel->ID);
					} elseif (isset($_POST["miembropress_hash"])) {
						$newUser = $this->admin->create();
						if (!$newUser || !is_numeric($newUser)) {
							$this->protection->lockdown("register");
						}
					} elseif ($temp) {
						$registerTemp = $miembropress->model->getTempFromTransaction($temp);
						$this->registerTemp = $registerTemp->txn_id;
						$newUser = $this->admin->create();
						if (!$newUser || !is_numeric($newUser)) {
							$this->protection->lockdown("register");
						}
					} elseif (isset($_REQUEST["complete"])) {
						if ($temp = $miembropress->model->getTempFromTransaction($_REQUEST["complete"])) {
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
						$_SESSION["miembropress_temp"] = $verify["transaction"];
						$userID = 0;
						if ($verify["transaction"]) {
							$userID = $miembropress->model->getUserIdFromTransaction($verify["transaction"]);
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
								$miembropress->model->cancel($userID, intval($verify["level"]));
								if ($txnLevels = $miembropress->model->getLevelsFromTransaction($verify["transaction"])) {
									foreach ($txnLevels as $txnLevel) {
										$miembropress->model->cancel($userID, intval($txnLevel));
									}
								} elseif ($verify["level"]) {
									$miembropress->model->cancel($userID, intval($verify["level"]));
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
						} elseif ($temp = $miembropress->model->getTempFromTransaction($verify["transaction"])) {
							if ($verify["action"] == "cancel") {
								$miembropress->model->cancelTemp($temp->txn_id);
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
								$miembropress->model->createTemp($verify["transaction"], $verify["level"], $verify);
								$this->registerTemp = $verify["transaction"];
								$_SESSION["miembropress_temp"] = $this->registerTemp;
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
			if (isset($_POST["miembropress_hash"])) {
				$hash = $_POST["miembropress_hash"];
			} elseif (isset($_SERVER["QUERY_STRING"])) {
				$hash = urldecode($_SERVER["QUERY_STRING"]);
				$split = preg_split('@[/&]@', $hash, 4);
				if (count($split) >= 3) {
					list(, $plugin, $hash) = $split;
				} elseif (count($split) == 2) {
					list(, $plugin) = $split;
				}

				if ($plugin != "miembropress") {
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
		if (isset($vars["miembropress_username"])) {
			$username = stripslashes($vars["miembropress_username"]);
		}
		if (isset($vars["miembropress_firstname"])) {
			$firstname = stripslashes($vars["miembropress_firstname"]);
		}
		if (isset($vars["miembropress_lastname"])) {
			$lastname = stripslashes($vars["miembropress_lastname"]);
		}
		if (isset($vars["miembropress_email"])) {
			$email = stripslashes($vars["miembropress_email"]);
		}
		if (isset($vars["miembropress_password1"])) {
			$password1 = stripslashes($vars["miembropress_password1"]);
		}
		if (isset($vars["miembropress_password2"])) {
			$password2 = stripslashes($vars["miembropress_password2"]);
		}
		if ($password1 == "") {
			$password1 = MemberGenius::generate();
			$password2 = $password1;
		}
		return array( "username" => trim($username), "firstname" => trim($firstname), "lastname" => trim($lastname), "email" => trim($email), "password1" => trim($password1), "password2" => trim($password2) );
	}
}


if (!function_exists("class_alias")) {
	function class_alias($original, $alias) {
		$newclass = create_function('','class '.$alias.' extends '.$original.' {}');
		$newclass();
	}
}

if (!is_plugin_active("wishlist-member/wishlist-member.php") && !class_exists("WLMAPI")) {
	class_alias("MGAPI", "WLMAPI");
}

/*
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

wp_register_style( 'miembro-press-css', base_url . '/assets/css/estilo.css');
wp_enqueue_style( 'miembro-press-css' );
wp_register_style( 'bootstrap-font-css', "https://maxcdn.bootstrapcdn.com/font-awesome/4.4.0/css/font-awesome.min.css");
wp_enqueue_style( 'bootstrap-font-css' );

?>