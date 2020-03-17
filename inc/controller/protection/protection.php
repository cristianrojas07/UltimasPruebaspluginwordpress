<?php

class MiembroPressProtection {
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
		global $miembropress;
		$current_user = wp_get_current_user();
		if (!isset($current_user->ID)) { return; }
		if ($miembropress->hashRequest()) { return; }
		if (is_404()) {
			foreach ($miembropress->model->getLevelInfo($current_user->ID) as $level) {
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
		global $miembropress;
		global $post;
		if (is_user_logged_in()) { return; }
		if ($miembropress->hashRequest()) { return $query; }
		$nonmember_page = @intval($miembropress->model->setting("nonmember_page"));
		$nonmember_url = trim($miembropress->model->setting("nonmember_url"));
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
		global $miembropress;
		$permalinks = get_option("permalink_structure");
		if ($wp_query->get("name") != "login" && !isset($_GET["login"])) { return; }
		if ($miembropress->hashRequest()) { return; }
		if (is_user_logged_in()) {
			wp_redirect(home_url());
			die();
		}
		wp_redirect(wp_login_url(home_url()));
		die();
	}

	public function logMeOut() {
		global $wp_query;
		global $miembropress;
		if ($wp_query->get("name") != "logout") { return; }
		wp_logout();
		die();
	}

	public function loggedOut() {
		global $miembropress;
		global $posts;
		if (is_user_logged_in()) { return; }
		if ($miembropress->hashRequest()) { return; }
		$nonmember_page = @intval($miembropress->model->setting("nonmember_page"));
		$nonmember_url = $miembropress->model->setting("nonmember_url");
		if (!is_array($posts) || count($posts) == 0) {
			if ($nonmember_page == 0) {
				wp_redirect(wp_login_url($_SERVER['REQUEST_URI']));
				die();
			}
		}
	}


	public function afterLogin($user_login, $user=null) {
		global $miembropress;
		if ($miembropress->hashRequest()) { return; }
		if (is_admin() && current_user_can("administrator")) { return; }
		if ($user == null && $user_login) { $user = get_user_by("login", $user_login); }
		foreach ($miembropress->model->getLevelInfo($user->ID) as $level) {
			if (isset($level->level_page_login) && $level->level_page_login) {
				if ($redirect = get_permalink(intval($level->level_page_login))) {
					wp_redirect($redirect);
					die();
				}
			}
		}
	}

	public function init() {
		global $miembropress;
		$current_user = wp_get_current_user();
		if (!is_admin()) {
			if (is_user_logged_in()) {
				$this->allowed = $miembropress->model->getPosts($current_user->ID);
				$ip = ip2long($_SERVER["REMOTE_ADDR"]);
				$loginFirst = intval($miembropress->model->userSetting($current_user->ID, "loginFirst"));
				if (!$loginFirst) {
					$miembropress->model->userSetting($current_user->ID, "loginFirst", $ip);
				}
				$miembropress->model->userSetting($current_user->ID, "loginLastTime", time());
				$logins = $miembropress->model->userSetting($current_user->ID, "logins");
				if (!is_array($logins)) {
					$logins = array();
				}
				$logins[$ip] = time();
				arsort($logins);
				$logins = array_slice($logins, 0, 10, true);
				$miembropress->model->userSetting($current_user->ID, "logins", $logins);
			} else {
				$this->allowed = $miembropress->model->getPostAccess(-1);
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
			if ($pages[$i]->post_name == "miembropress") {
				unset($pages[$i]);
				break;
			}
		}
		return $pages;
	}

	public function excludePageBackend($query) {
		if (!is_admin()) { return $query; }
		if ($placeholder = get_page_by_path("miembropress")) {
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
		if ($placeholder = get_page_by_path("miembropress")) {
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
		global $miembropress;
		add_filter( 'wp_nav_menu_items', '__return_empty_string', 10, 2 );
		remove_all_filters('posts_groupby');
		remove_all_filters('posts_where');
		remove_all_filters('posts_join');
		if ($action == "login") {
			add_filter('the_content', array(&$this, 'content'), 1000);
		} elseif ($action == "register") {
			add_filter('page_template', array(&$this, "lockdown_template"), 10);
			add_filter('the_content', array($miembropress->admin, 'register'), 1000);
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
			$file = trailingslashit($theme) . 'templates/template-onecolumn.php';
		}

		if ($file && file_exists($file)) { return $file; }
		return $template;
	}

	public function permalink($url) {
		return "?".$_SERVER["QUERY_STRING"];
	}

	public function noFooter() { ?>
		<style type="text/css">
		
		.entry-meta, .navigation, .nav-menu, .site-info, #colophon { display:none; }
		.entry-title { text-align:center; }
		
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
		global $miembropress;
		global $wpdb;
		$userTable = $miembropress->model->getUserTable();
		$hotmartTable = $miembropress->model->getHotmartTable();
		if(isset($_GET['trs'])){
			$transaction = $_GET['trs'];
			$existTransaction = $wpdb->get_var( "SELECT ID FROM $hotmartTable WHERE transaction = '$transaction'" );
			$existTransactionUser = $wpdb->get_var( "SELECT ID FROM $userTable WHERE transaction = '$transaction'" );

			if(is_null($existTransaction)){
				die("<h3>404 Página No Encontrada</h3>");
			}
			if(!is_null($existTransactionUser)){
				die("<h2>Usted ya se ha creado una cuenta, los datos se enviaron a su correo.</h2>");
			}
			$this->verificatedUserIp($userTable);
		}
		
		$this->verificatedUserIp($userTable);
		return;
		/*
		if (isset($miembropress->registerLevel->level_name)) {
			return $miembropress->registerLevel->level_name . " Registro";
		}
		return $this->protectedTitle;*/
	}

	public function verificatedUserIp($userTable){
		global $miembropress;
		global $wpdb;
		$uri = urldecode($_SERVER["REQUEST_URI"]);
		$ipUser = $miembropress->admin->getRealIP();
		$existIp = $wpdb->get_row( "SELECT ID, level_id FROM $userTable WHERE ip_user = '$ipUser'" );
		if(!is_null($existIp->ID)){
			$levelId = $existIp->level_id;
			$levelHash = $miembropress->model->getLevelHash($levelId);
			if(strstr($uri, $levelHash)){
				die("<h2>Usted ya se ha creado una cuenta, los datos se enviaron a su correo.</h2>");
			}
		}
	}
	
	public function comments($posts) {
		global $miembropress;
		if (current_user_can("manage_options")) { return $posts; }
		$current_user = wp_get_current_user();
		$levels = $miembropress->model->getLevelInfo($current_user->ID);
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
			} elseif (file_exists(dirname('index.php')."/member-login.php")) {
				require(dirname('index.php')."/member-login.php");
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


?>