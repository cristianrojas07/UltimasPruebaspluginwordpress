<?php

class MiembroPressSocialHandlerFacebook extends MiembroPressSocialHandler {
	var $slug = "facebook";
	var $name = "Facebook";
	var $dashicon = "dashicons-facebook";
	var $color = "#3c5a99";
	public function login_scripts() {
		global $miembropress;
		if (!$miembropress->model->setting("social_facebook_enabled") || !$miembropress->model->setting("social_facebook_app") || !$miembropress->model->setting("social_facebook_secret")) { return; }
		?>
		<script>
			window.fbAsyncInit = function() {
				FB.init({
					appId: ' < ? phpecho $miembropress->model->setting("social_facebook_app"); ?>',
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
		global $miembropress;
		if ($_REQUEST['state'] != $_SESSION['state']) {
			die("XSS");
		}

		$code = $_REQUEST['code'];
		$url = home_url("wp-login.php?".$query."=facebook_callback");
		if ($query == "miembropress_register") {
			reset($_GET);
			if (isset($_SESSION["miembropress_hash"])) {
				$hash = stripslashes($_SESSION["miembropress_hash"]);
				unset($_SESSION["miembropress_hash"]);
			} else {
				$hash = basename(key($_GET));
			}
			$url = $miembropress->model->signupURL($hash, true).'&'.$query.'=facebook_callback';
		}
		$token_url = "https://graph.facebook.com/oauth/access_token?" . "client_id=" . $miembropress->model->setting("social_facebook_app") . "&redirect_uri=" . urlencode($url) . "&client_secret=" . $miembropress->model->setting("social_facebook_secret") . "&code=" . $code;
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

?>