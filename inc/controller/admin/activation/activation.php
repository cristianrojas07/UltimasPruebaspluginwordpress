<?php 

class MiembroPressActivation {
	public $call;
	public $key;
	public $fullURL;
	private $url;
	private $salt;
	public $obj;

	function __construct($productSlug, $productName) {
		global $wpdb;
		$prefix = $wpdb->prefix . "miembropress_";
		$this->settingsTable = $prefix."settings";
		$this->slug = $productSlug;
		$this->call = "";

		if (function_exists("current_user_can") && current_user_can("manage_options") && isset($_POST[$this->slug."_deactivate"])) {
			$settings = get_option($this->slug);
			$licencia = $settings["key"];
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
		@list($this->key) = $this->settings_get("key");

		$this->productName = $productName;

		$this->fullURL = "https://miembros.miembropress.com/verificarClave.php?license={$this->key}";

		$this->call();

		if (!function_exists( 'get_plugins' ) ) {
			require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
		}

		$plugin_folder = get_plugins( '/' . plugin_basename( dirname( 'miembro-press/miembro-press.php' ) ) );
		
		$plugin_file = basename( 'miembro-press/miembro-press.php');

		$this->version = $plugin_folder[$plugin_file]['Version'];
		$this->salt = "sfg54fdc44g623p9";

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

    function deactivate() {
		if (!isset($this->settings)) {
			$this->settings = get_option($this->slug);
		}
		unset($this->settings["key"]);
		unset($this->settings["lastcheck"]);
		unset($this->settings["lasthash"]);
		unset($this->settings["lastversion"]);
		update_option($this->slug, $this->settings);
	}
    
    function deactivation_button() {
		echo '<input type="submit" class="button-primary button-activate" name="'.$this->slug.'_deactivate" value="Deactivate License for This Site" onclick="return confirm(\'Are you SURE you want to deactivate your license for this site?\');" />';
    }

    function call() {
		$settings = array();
		if (isset($_POST["licenciaMember"])) {
			$settings = array();
			$settings = get_option($this->slug);
			if (!is_array($settings)) {
				$settings = array();
			}
			$settings["key"] = trim($_POST["licenciaMember"]);
			update_option($this->slug, $settings);
		}

		$hash = "";
		$key = "";
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

		$licenseExpire = (time()-$lastcheck) > 86400;
		$versionExpire = (time()-$lastversioncheck) > 3600;
		$timeoutExpire = (time()-$lastversioncheck) > 30;

		$hash = "";
		if ($this->salt) {
			$hash = md5($lastversioncheck."|" . "|" . $lastversion . "|" . $this->salt);
		}
		
		if ($this->salt && $hash == $lasthash && $this->call != "") {
			return $this->call;
		}

		if (!$url) {
			return $lastversion;
		} elseif (!$this->salt) {
			$this->call = "$lastversion";
			return $lastversion;
		} elseif (!$key) {
			return "UNREGISTERED";
		} elseif ($licenseExpire || $versionExpire || !$lastversion || strpos($this->call, " ") !== FALSE) {
			if ($key && !$timeoutExpire && ($lastversion == "UNREGISTERED" || $lastversion == "FAILED")) {
				return $lastversion;
			}

			if (!function_exists( 'wp_remote_get' ) ) {
				require_once( ABSPATH . 'wp-includes/http.php' );
			}

			if (function_exists("wp_remote_get")) {
				$response = "";
				$result = wp_remote_get($url, array( 'timeout'=>30, 'sslverify' => false, 'httpversion' => '1.1' ));

				if(is_wp_error($response)) {
					$response = "FAILED";
				}
				$results = wp_remote_retrieve_body($result);
				$obj = json_decode($results, true);

				if (!$obj['success'] || !$obj['status']) {
					$this->call = 0;
					return "FAILED";
				}

			} else {
				$snoopy = new Snoopy();
				$snoopy->_fp_timeout = 10;
				if ($result = $snoopy->fetch($url)) {
					$results = $snoopy->results;
				}
			}
			if ($obj) {
				if($obj['success'] && $obj['maxsitio'] != 0 && $obj['status']){
					$license = $key;
					$myURL = $_SERVER['HTTP_HOST'];
					$ch = curl_init();
					curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
					curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
					curl_setopt($ch, CURLOPT_URL, "https://miembros.miembropress.com/activacion.php?license=$license&url=$myURL");
					curl_exec($ch);
					curl_close($ch);

					$version = $lastversion;
					$time = time();
					$hash = "";
					if ($this->salt) {
						$hash = md5($time . "|" . $version . "|" . $this->salt); 
						$settings["lasthash"] = $hash;
					}
					$settings["lastversion"] = $version;
					$settings["lastcheck"] = $time;
					$settings["lastversioncheck"] = $time;
					update_option($this->slug, $settings);
	
					$this->call = $lastversion;
					return $lastversion;
				}
			} else {
				$return = "FAILED";
				$this->call = $return;
				$settings["lastcheck"] = $time;
				$settings["lastversioncheck"] = $time;
				$settings["lastversion"] = $version;
				update_option($this->slug, $settings);
				return $return;
			}
		}
		//$this->call = $lastversion;
		//return $lastversion;
		$this->call = $this->val_status($lastversion, true, !$settings["lastversion"]);
		return $this->val_status($lastversion, false, !$settings["lastversion"]);
    }
	
	function val_status($lastversion, $call = false, $noExist = false){
		if (!function_exists( 'wp_remote_get' ) ) {
			require_once( ABSPATH . 'wp-includes/http.php' );
		}

		if (function_exists("wp_remote_get")) {
			$response = "";
			$result = wp_remote_get($this->fullURL, array( 'timeout'=>30, 'sslverify' => false, 'httpversion' => '1.1' ));

			if(is_wp_error($response)) {
				$response = "FAILED";
			}

			$results = wp_remote_retrieve_body($result);
			$obj = json_decode($results, true);
			if (!$obj['status']) {
				if(!$noExist){
					$this->settings = get_option($this->slug);
					unset($this->settings["lastversion"]);
					update_option($this->slug, $this->settings);
				}
				if($call){
					return 0;
				}
				return "OBSOLETE";
			}else{
				if($noExist){
					$this->settings = get_option($this->slug);
					$this->settings["lastversion"] = $lastversion;
					update_option($this->slug, $this->settings);
				}
				return $lastversion;
			}

		} else {
			$snoopy = new Snoopy();
			$snoopy->_fp_timeout = 10;
			if ($result = $snoopy->fetch($url)) {
				$results = $snoopy->results;
			}
		}
	}

    function message($call="") {
		if (empty($call)) { $call = $this->call(); }
		?>
		<?php if (empty($call)) : ?>
		<!-- do nothing -->
		<?php elseif ($call == "UNREGISTERED"): ?>
			<div class="error">
				<p><b><?php echo $this->productName; ?> Alert:</b> You need to <a href="<?php echo $url; ?>">enter your license key</a> to begin using the plugin.</p>
			</div>
		<?php elseif ($call == "FAILED"): ?>
			<div class="error">
				<p><b>MiembroPress Alert:</b> <a href="<?php echo $url; ?>">Incorrect license key</a></p>
			</div>
		<?php elseif ($call == "OBSOLETE"): ?>
		<div class="error">
			<p><b>MiembroPress Alert:</b> <a href="<?php echo $url; ?>">You have not renewed your product</a></p>
		</div>
		<?php endif; ?>
		<?php
    }
    
    function register($call) {?>
		<div class="wrap" style="clear:both;">
			<form method="post" autocomplete="off">
				<table class="form-table" width="100%" cellspacing="10" cellpadding="10">
					<caption style="background: #111; border-top-left-radius: 10px; border-top-right-radius: 10px;"><img style="margin: 20px 0px 20px 0px;" src="<?php echo base_url . '/assets/images/logomiembropress.png'?>"></caption>
					<tbody>
						<th colspan="2" style="padding: 20px;background: #ffffff;border: 1px outset white;">Create your membership sites in minutes and get instant payments, with just a few clicks. Integrated with Hotmart, PayPal, ClickBank, JVZoo and WarriorPlus.</th>
					</tbody>
					<tfoot>
						<tr>
							<th colspan="2" style="padding: 20px;background: #d4d4d4;border-bottom-left-radius: 10px;border-bottom-right-radius: 10px;">Enter your license:
								<input type="text" id="licenciaMember" name="licenciaMember" value="<?php echo $_POST["licenciaMember"]?>" placeholder="Please enter your license" size="32"/>
								<input type="submit" class="button-primary button-activate btn" value="Validate MiembroPress" name="Enviar" />
							</th>
						</tr>
					</tfoot>
				</table>
			</form>
		</div>
		<?php
	}
}
?>