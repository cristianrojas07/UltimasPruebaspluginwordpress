<?php 

class MiembroPressActivation {
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

		// ESTO SIRVE
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

		// PRIMER CALL
		$this->call();
		// return $lastversion = 0;

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
		//$this->keyURL = http://www.incomemachine.com/members/license-key

		$this->fullURL = trailingslashit($this->upgradeURL).'?wpdrip='.urlencode($this->email.'|'.$this->key.'|'.$this->slug.'|'.dirname(constant("WP_CONTENT_URL")));
		// $this->fullURL = http://2amactivation.com/incomemachine/?wpdrip=solonetworkonline@gmail.com|1416719fbb6b11370adcdab5adb9ec9a|miembropress|http://robhernandez.club.wordpress

		// SEGUNDO CALL
		$this->call();
		// return $lastversion = 0;

		if (!function_exists( 'get_plugins' ) ) {
			require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
		}

		$plugin_folder = get_plugins( '/' . plugin_basename( dirname( 'miembro-press/miembro-press.php' ) ) );
		
		$plugin_file = basename( 'miembro-press/miembro-press.php');

		$this->version = $plugin_folder[$plugin_file]['Version'];
		// $this->version = 2.5.3

		$this->salt = "sfg54fdc44g623p9";

		if ((isset($_GET["activator-debug"]) || isset($_GET["activator_debug"]))) {
			$this->debug = true;
		}
		//$this->debug = false;

		if (function_exists("get_option") && !defined('WP_CONTENT_URL')) {
			define('WP_CONTENT_URL', get_option('siteurl') . '/wp-content');
		}
		// WP_CONTENT_URL = http://robhernandez.club.wordpress/wp-content

		if (!$this->url) {
			if (defined("WP_CONTENT_URL")) {
				$this->url = constant("WP_CONTENT_URL") . "/plugins/" . basename(dirname('miembro-press/miembro-press.php'));
			} else {
				$this->url = 'miembro-press/miembro-press.php';
			}
		}
		// $this->url = http://robhernandez.club.wordpress/wp-content/plugins/miembro-press
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

		if (strpos($this->call, "ultimate") !== FALSE && !$this->ultimate) {
			$this->ultimate = true;
		}

		if ($this->salt && $hash == $lasthash && $this->call != "") {
			return $this->call;
		}
		
		var_dump($this->call);
		if (!$url) {
			// ENTRA EN PRIMER CALL
			return $lastversion;
		} elseif (!$this->salt) {
			// ENTRA EN TERCER CALL
			$this->call = "$lastversion";
			if (strpos($this->call, "ultimate") !== FALSE && !$this->salt) { 
				$this->salt = true; 
			}
			return $lastversion;
		} elseif (!$key || !$email) {
			return "UNREGISTERED";
		} elseif ($licenseExpire || $versionExpire || !$lastversion || strpos($this->call, " ") !== FALSE) {
			// if ($key && !$timeoutExpire && ($lastversion == "UNREGISTERED" || $lastversion == "UNKNOWN" || $lastversion == "CANCELLED" || $lastversion == "FAILED" || $lastversion == "BLOCKED")) {
			// 	return $lastversion;
			// }

			if (!function_exists( 'wp_remote_get' ) ) {
				require_once( ABSPATH . 'wp-includes/http.php' );
			}

			if (function_exists("wp_remote_get")) {
				$response = "";
				$result = wp_remote_get($url, array( 'timeout'=>30, 'sslverify' => false, 'httpversion' => '1.1' ));
				if(is_wp_error($response)) {
					$this->debug(var_export($response, true));
					$response = "BLOCKED";
				}
				$results = wp_remote_retrieve_body($result);
				if (empty($results)) {
					$this->debug("empty response");
					$results = "BLOCKED";
				}
				// $results = FAILED
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
					$hash = md5($time . "|" . $version . "|" . $this->salt); 
					$settings["lasthash"] = $hash;
				}
				$settings["lastversion"] = $version; // 0
				$settings["lastcheck"] = $time; //1583529478
				$settings["lastversioncheck"] = $time; // 1583529478
				update_option($this->slug, $settings);
				$this->call = $results; // FAILED
				$this->call = '2.1.3.2-express'; // FAILED
				if (strpos($this->call, "ultimate") !== FALSE && !$this->ultimate) {
					$this->ultimate = true;
				}
				//return $results;
				return '2.1.3.2-express';
			} else {
				$return = "BLOCKED";
				$this->call = $return;
				$settings["lastcheck"] = $time;
				$settings["lastversioncheck"] = $time;
				$settings["lastversion"] = $version;
				update_option($this->slug, $settings);
				return $return;
				return null;
			}
		}
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
				<p><b>MiembroPress Alert:</b> Your license has been <a href="<?php echo $url; ?>">cancelled for non-payment</a>.</p>
			</div>
		<?php elseif ($call == "UNKNOWN"): ?>
			<div class="error">
				<p><b>MiembroPress Alert:</b> That email address is not found in our database, <a href="<?php echo $url; ?>">please double check your details</a>.</p>
			</div>
		<?php elseif ($call == "OVERFLOW"): ?>
			<div class="error">
				<p><b>MiembroPress Alert:</b> You are using more than 5 sites with MiembroPress, please <a href="http://<?php echo $this->upgradeURL; ?>">upgrade to Ultimate</a> now.</p>
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
					<caption style="background: #111; border-top-left-radius: 10px; border-top-right-radius: 10px;"><img style="margin: 20px 0px 20px 0px;" src="<?php echo base_url . '/assets/images/logomiembropress.png'?>"></caption>
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
									<p><b>Note:</b> You need to enter the email address <B>you used to PURCHASE</B> MiembroPress, not necessarily the administrator email address of this blog.</p>
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
									   <p><b>Note:</b> You need to enter the email address <B>you used to PURCHASE</B> MiembroPress, not necessarily the administrator email address of this blog.</p>
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
									   <p><b>Note:</b> You need to enter the email address <B>you used to PURCHASE</B> MiembroPress, not necessarily the administrator email address of this blog.</p>
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
									   <p><b>Note:</b> You need to enter the email address <B>you used to PURCHASE</B> MiembroPress, not necessarily the administrator email address of this blog.</p>
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
		<iframe id="miembropress_LicenseAd" src="about:blank" width="1" height="1" style="display:none;"></iframe>
		<script type="text/javascript">
		<!--
			var miembropress_home = [
				"http://www.incomemachine.com/members/?wpdrip_headless=1"
			];
			function miembropress_sendMessage() {
				document.getElementById("miembropress_LicenseAd").contentWindow.postMessage("license", "*");
			}
			function miembropress_receiveMessage(event) {
				if (event == undefined || !event || event.data == undefined) { return; }
				else if (event.data == "" || event.data == null || event.data == "null") {
					miembropress_home = miembropress_home.slice(1);
					miembropress_autofill(miembropress_home[0]);
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

			function miembropress_autofill(url) {
				try {
					window.addEventListener("message", miembropress_receiveMessage, false);
					document.getElementById("miembropress_LicenseAd").onload = miembropress_sendMessage;
					document.getElementById("miembropress_LicenseAd").src = url;
				}
				catch(err) {
				}
			}

			jQuery(function() {
				miembropress_autofill(miembropress_home[0]);
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

?>