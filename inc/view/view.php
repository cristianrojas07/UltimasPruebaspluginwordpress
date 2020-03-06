<?php

class MemberGeniusView {
	public function __construct() {
		add_action("wp_head", array(&$this, "head"));
		add_action("wp_footer", array(&$this, "foot"));
	}

	public function head() {
		global $miembropress;
		if ($header = $miembropress->model->setting("header")) {
			eval( '?> '.stripslashes(do_shortcode($header)).' <?php ' );
		}
	}

	public function foot() {
		global $miembropress;
		if ($footer = $miembropress->model->setting("footer")) {
			eval( ' ?> '.stripslashes(do_shortcode($footer)).' <?php ' );
		}
		$output = array();
		if ($attribution = $miembropress->model->setting("attribution")) {
			$link = "https://miembropress.com";
			if ($affiliate = $miembropress->model->setting("affiliate")) {
				$link = $affiliate;
			}
			$output[] = ' <a target="_blank" href="'.htmlentities($link).'"> Sitio de membresía de WordPress creado con MiembroPress </a> ';
		}

		if ($support = $miembropress->model->setting("support")) {
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
		global $miembropress;
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
		if (isset($_POST["miembropress_level"]) && is_array($_POST["miembropress_level"])) {
			$query .= "&levels=".implode(",", $_POST["miembropress_level"]);
		}
		if (isset($_POST["miembropress_status"])) {
			if ($_POST["miembropress_status"] == "active") {
				$query .= "&status=A";
			} elseif ($_POST["miembropress_status"] == "canceled") {
				$query .= "&status=C";
			} elseif ($_POST["miembropress_status"] == "all") {
				$query .= "&status=A,C";
			}
		}
		$members = $miembropress->model->getMembers($query);
		echo "username,firstname,lastname,email,level,date";
		foreach ($members as $memberKey => $member) {
			$username = $member->user_login;
			$firstname = get_user_meta($member->ID,'first_name', true);
			$lastname = get_user_meta($member->ID,'last_name', true);
			$email = $member->user_email;
			$levels = array();
			foreach ($miembropress->model->getLevelInfo($member->ID) as $userLevel) {
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
        global $miembropress;
        $order = $miembropress->model->setting("order");
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
        global $miembropress;
        if (isset($_GET["miembropress_action"]) && $_GET["miembropress_action"] == "switch_user") {
            wp_redirect(wp_login_url());
            die();
        } else {
            $logout_page = @intval($miembropress->model->setting("logout_page"));
            $logout_url = $miembropress->model->setting("logout_url");
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
        $userLevels = $miembropress->model->getLevelInfo($user->ID);
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
		<?php else: ?>
			<?php wp_login_form(); ?>
		<?php endif; ?>
		<?php echo $after_widget;
    }

	public function autoresponder() {
        global $miembropress;
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
        $levels = $miembropress->model->getLevelInfo($current_user->ID, "U");
        if (!is_array($levels) || count($levels) == 0) { return; }
        $autoresponders = array();
        foreach ($levels as $level) {
            $miembropress->model->subscribe($current_user->ID, $level->level_id);
            $autoresponders[] = $miembropress->model->getAutoresponder($level->level_id);
        }
        $i = 0;
        foreach ($autoresponders as $key => $autoresponder): ?>
			<div id="miembropress_autoresponder[<?php echo $key; ?>]" style="display:none;">
			<?php echo $autoresponder["code"]; ?>
			</div>
			<iframe name="miembropress_submit[<?php echo $key; ?>]" width="1" height="1" src="about:blank" style="display:none;"></iframe>
			<?php $i++; ?>
			<?php endforeach; ?>

			<script type="text/javascript">
			<!--
			jQuery(function() {
			<?php foreach ($autoresponders as $key => $value): ?>
			var theAutoresponder = jQuery(document.getElementById("miembropress_autoresponder[<?php echo $key; ?>]"));
			var theForm = theAutoresponder.find("form").first();
			theForm.attr("target", "miembropress_submit[<?php echo $key; ?>]");

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

?>