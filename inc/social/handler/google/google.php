<?php

class MiembroPressSocialHandlerGoogle extends MiembroPressSocialHandler {
	var $slug = "google";
	var $name = "Google";
	var $dashicon = "dashicons-googleplus";
	var $color = "#DC4E41";
	public function request($query="miembropress_login") {
		global $miembropress;
		@session_start();
		extract(parent::request($query));
		$_SESSION['state'] = md5(uniqid(rand(), TRUE));
		if ($level && $query == "miembropress_register") {
			$_SESSION['miembropress_hash'] = $level;
		}
		$app = $miembropress->model->setting("social_google_app");
		$secret = $miembropress->model->setting("social_google_secret");
		$scope = "email";
		$redirect = home_url("wp-login.php?".$query."=google_callback");
		$verify = "https://accounts.google.com/o/oauth2/auth?response_type=code&client_id=".rawurlencode($app)."&redirect_uri=".urlencode($redirect)."&scope=".rawurlencode($scope)."&state=token&access_type=offline";
		wp_redirect($verify);
		die();
	}

	public function callback($query="miembropress_login") {
		global $miembropress;
		if (!isset($_GET["code"])) { return; }
		$code = stripslashes($_GET["code"]);
		$app = $miembropress->model->setting("social_google_app");
		$secret = $miembropress->model->setting("social_google_secret");
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

?>