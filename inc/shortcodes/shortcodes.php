<?php

class MiembroPressShortcodes {
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
		global $miembropress;
		$current_user = wp_get_current_user();
		if (count($atts) == 0) { return ""; }
		$level = $atts[0];
		$userLevels = $miembropress->model->getLevelInfo($current_user->ID, "A");
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

?>