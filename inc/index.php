<?php

// FOR HREF
define( 'base_url_inc' , plugin_dir_url( __FILE__ ));

// FOR REQUIRE
define( 'path_url_inc' , plugin_dir_path( __FILE__ ));

require_once('controller/admin/admin.php');
require_once('controller/protection/protection.php');
require_once('controller/social/social.php');
require_once('controller/shortcodes/shortcodes.php');
require_once('model/model.php');
require_once('view/view.php');

$miembropress = new MiembroPress();

class MiembroPress {

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
		$this->admin = new MiembroPressAdmin();
		$this->protection = new MiembroPressProtection();
		$this->model = new MiembroPressModel();
		$this->view = new MiembroPressView();
		$this->social = new MiembroPressSocial();
		$shortcodes = new MiembroPressShortcodes();
		$this->carts = array( "Hotmart" => "MiembroPressCartHotmart", "Generic" => "MiembroPressCartGeneric", "Clickbank" => "MiembroPressCartClickbank",  "JVZoo" => "MiembroPressCartJVZ", "PayPal" => "MiembroPressCartPayPal", "WarriorPlus" => "MiembroPressCartWarrior", );
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
		add_action('admin_bar_menu', array( $this, "admin_bar_switcher" ), 35 );
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
			MiembroPress::clearCache();
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
			if ($request = $this->hashRequest()) {
				MiembroPress::clearCache();
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

	public static function validate($vars=null) {
		if ($vars == null) {
			$vars = $_POST;
		}
		extract(MiembroPress::extract($vars));
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
			$password1 = MiembroPress::generate();
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

wp_register_style( 'bootstrap-font-css', "https://maxcdn.bootstrapcdn.com/font-awesome/4.4.0/css/font-awesome.min.css");
wp_enqueue_style( 'bootstrap-font-css' );

// Incluir css estilo.css
wp_register_style( 'miembropress_css', base_url . 'assets/css/estilo.css', array(), '4.5.5');
wp_enqueue_style( 'miembropress_css' ); 


// Incluir Bootstrap CSS
wp_register_style( 'bootstrap_css', base_url . 'assets/css/bootstrap.css', array(), '4.4.5');
wp_enqueue_style( 'bootstrap_css' ); 


// Incluir Bootstrap JS
wp_register_script('bootstrap_js', base_url . 'assets/js/bootstrap.js', array('jquery'), '4.4.1', true);
wp_enqueue_script( 'bootstrap_js' ); 

?>