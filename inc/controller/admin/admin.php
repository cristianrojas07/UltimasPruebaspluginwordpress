<?php

require_once('activation/activation.php');

class MiembroPressAdmin {
	public $activation;
	public $menu;
	function __construct() {
		$this->activation = new MiembroPressActivation("miembropress", "MiembroPress");
		if (!function_exists('add_action')) { return; }
		add_action('admin_menu', array(&$this, 'menu_setup'));
		if ($this->activation->call == 0) {
			add_filter("plugin_action_links", array(&$this, 'links_unregistered'), 10, 2);
			return;
		}else{
			require_once( path_url_inc . 'controller/customizer/login-customizer.php');
		}
		add_action('admin_bar_menu', array($this, "admin_bar" ), 35 );
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
		//add_filter("plugin_action_links", array(&$this, 'links'), 10, 2);
		add_action('add_meta_boxes', array(&$this, 'meta_boxes'));
		add_filter("wp_insert_post", array(&$this, 'meta_save'), 10, 2);
		add_filter("save_post", array(&$this, 'meta_save'));
		
	}

	public function menu_setup() {
		$menu = add_menu_page('MiembroPress', 'MiembroPress', 'administrator', $this->ttlMenu(), array(&$this, "menu_dashboard"), base_url . '/assets/images/iconmiembropress.png');

		$this->menu = admin_url("admin.php?page=".$this->ttlMenu());

		$call = $this->activation->call();
		if (empty($call) || $call == "FAILED" || $call == "UNREGISTERED" || $call == "OBSOLETE") { return;	}

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

	public function menu_payments() {
		global $miembropress;
		$currentCart = "Generic";
		if (isset($_POST["cart"])) {
			$currentCart = stripslashes($_POST["cart"]);
			$miembropress->model->setting("cart_last", $currentCart);
		} else {
			$currentCart = $miembropress->model->setting("cart_last");
		}
		$cartClass = null;
		if ($currentCart && isset($miembropress->carts[$currentCart])) {
			$cartClass = $miembropress->carts[$currentCart];
		}

		if (!class_exists($cartClass) || get_parent_class($cartClass) != "MiembroPressCart") {
			$cartClass = null;
		} ?>
		<div class="wrap" style="clear:both;">
			<?php $this->menu_header("Payments"); ?>
			<form method="post">
				<p>
					<label>Select Processor:
					<select name="cart" onchange="this.form.submit();">
					<?php foreach ($miembropress->carts as $cartName => $class): ?>
						<option <?php selected($cartName == $currentCart); ?> value="<?php echo htmlentities($cartName); ?>"><?php echo htmlentities($cartName); ?> &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</option>
					<?php endforeach; ?>
					</select></label>
					<input type="submit" value="Select Shopping Cart" class="button-primary menus_buttons button-activate " />
				</p>
				<?php if ($cartClass != null) { $cart = new $cartClass; $cart->instructions(); } ?>
			</form>
		</div>
		<?php
	}

	public function menu_social() {
		global $miembropress;
		$facebook_enabled = $miembropress->model->setting("social_facebook_enabled")==1;
		$google_enabled = $miembropress->model->setting("social_google_enabled")==1;
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
					<input type="submit" class="button-primary menus_buttons button-activate " value="Select Social Network" />
					</label>
				</p>
			<?php if ($social_selection == "facebook") { $this->menu_social_facebook(); } elseif ($social_selection == "google") { $this->menu_social_google(); } ?>
			</form>
		</div>
		<?php $this->javascript();
	}

	function menu_social_facebook() {
		global $miembropress;
		if (isset($_POST["miembropress_settings_facebook_app"])) {
			if (isset($_POST["miembropress_settings_facebook_enabled"])) {
				$miembropress->model->setting("social_facebook_enabled", 1);
			} else {
				$miembropress->model->setting("social_facebook_enabled", 0);
			}
			if (isset($_POST["miembropress_settings_facebook_app"])) {
				$miembropress->model->setting("social_facebook_app", stripslashes($_POST["miembropress_settings_facebook_app"]));
			}
			if (isset($_POST["miembropress_settings_facebook_secret"])) {
				$miembropress->model->setting("social_facebook_secret", stripslashes($_POST["miembropress_settings_facebook_secret"]));
			}
		}
		$facebook_enabled = $miembropress->model->setting("social_facebook_enabled")==1;
		$facebook_app = $miembropress->model->setting("social_facebook_app");
		$facebook_secret = $miembropress->model->setting("social_facebook_secret");
		$parse = parse_url(home_url());
		$facebook_url = $parse["host"]; ?>
		<p>
			<label>
				<input type="checkbox" id="miembropress_settings_facebook_enabled" name="miembropress_settings_facebook_enabled" <?php checked($facebook_enabled); ?>/> Enable Facebook Login?
			</label>
			<?php if ($facebook_enabled) { ?>
				<a href="<?php echo home_url("wp-login.php?miembropress_login=facebook"); ?>">(test Facebook login)</a>
			<?php
			} ?>
			<br />
			<label>
				<strong>App ID:</strong>
				<input type='text' class='code' name='miembropress_settings_facebook_app' size='35' value="<?php echo htmlentities($facebook_app); ?>" onchange='jQuery("#miembropress_settings_facebook_enabled").attr("checked", true);' />
				<br />
			</label>
			<label>
				<strong>App Secret:</strong>
				<input type='password' class='code' name='miembropress_settings_facebook_secret' size='35' value="<?php echo htmlentities($facebook_secret); ?>" onchange='jQuery("#miembropress_settings_facebook_enabled").attr("checked", true);" onfocus='this.type="text"' onblur='this.type="password"' />
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
		<p><input type="submit" class="button-primary menus_buttons button-activate " value="Save All Changes" /></p>
		<?php
	}

	function menu_social_google() {
		global $miembropress;
		if (isset($_POST["miembropress_settings_google_app"])) {
			if (isset($_POST["miembropress_settings_google_enabled"])) {
				$miembropress->model->setting("social_google_enabled", 1);
			} else {
				$miembropress->model->setting("social_google_enabled", 0);
			}
			if (isset($_POST["miembropress_settings_google_app"])) {
				$miembropress->model->setting("social_google_app", stripslashes($_POST["miembropress_settings_google_app"]));
			}
			if (isset($_POST["miembropress_settings_google_secret"])) {
				$miembropress->model->setting("social_google_secret", stripslashes($_POST["miembropress_settings_google_secret"]));
			}
		}
		$google_enabled = $miembropress->model->setting("social_google_enabled")==1;
		$google_app = $miembropress->model->setting("social_google_app");
		$google_secret = $miembropress->model->setting("social_google_secret"); ?>
		<p>
			<label> <input type="checkbox" id="miembropress_settings_google_enabled" name="miembropress_settings_google_enabled" <?php checked($google_enabled); ?> /> Enable Google Login?</label>
			<?php if ($google_enabled): ?><a href="<?php echo home_url("wp-login.php?miembropress_login=google"); ?>">(test Google login)</a><?php endif; ?>
			<br />
			<label><strong>Client ID:</strong> <input type="text" class="code" name="miembropress_settings_google_app" size="75" value="<?php echo htmlentities($google_app); ?>" onchange="jQuery('#miembropress_settings_google_enabled').attr('checked', true);" /></label><br />
			<label><strong>Client Secret:</strong> <input type="password" class="code" name="miembropress_settings_google_secret" size="35" value="<?php echo htmlentities($google_secret); ?>" onchange="jQuery('#miembropress_google_facebook_enabled').attr('checked', true);" onfocus="this.type='text'" onblur="this.type='password'" /></label><br />
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
        <p><input type="submit" class="button-primary menus_buttons button-activate " value="Save All Changes" /></p>
		<?php
	}

	public function menu_autoresponder() {
    	global $miembropress;
        $levels = $miembropress->model->getLevels();
        $currentLevel = -1;
        if (isset($_REQUEST["l"])) { $currentLevel = intval($_REQUEST["l"]); }
        $levelInfo = $miembropress->model->getLevel($currentLevel);
        $saveLevel = null;
        if (isset($_POST["miembropress_save"])) {
            $saveLevel = intval($_POST["miembropress_save"]);
            $miembropress->model->setAutoresponder( $saveLevel, $_POST["miembropress_code"], $_POST["miembropress_email"], $_POST["miembropress_firstname"], $_POST["miembropress_lastname"] );
        }

        $code = "";
        $email = "";
        $firstname = "";
        $lastname = "";

        if ($autoresponder = $miembropress->model->getAutoresponder($currentLevel)) {
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
				<input type="hidden" name="miembropress_save" value="<?php echo intval($currentLevel); ?>" />
				<input type="hidden" name="email_stored" id="email_stored" value="<?php echo htmlentities($email); ?>" />
				<input type="hidden" name="firstname_stored" id="firstname_stored" value="<?php echo htmlentities($firstname); ?>" />
				<input type="hidden" name="lastname_stored" id="lastname_stored" value="<?php echo htmlentities($lastname); ?>" />

				<p>Select Level: <select name="l" onchange="this.form.submit()">
				<option value="-1" <?php if ($currentLevel === null || $currentLevel == - 1): ?>selected="selected"<?php endif; ?>>Levels...</option>
				<?php foreach ($levels as $level): ?>
					<option <?php if ($level->ID == $currentLevel): ?>selected="selected"<?php endif; ?> value="<?php echo intval($level->ID); ?>"><?php echo htmlentities($level->level_name); ?></option>
				<?php endforeach; ?>
				</select><input class="button-primary menus_buttons button-activate " type="submit" value="Setup Autoresponder for Level" /></p>

				<?php if ($currentLevel > - 1): ?>
					<p>Paste in Autoresponder Signup Code: <span style="background-color:yellow; font-weight:bold;">(HTML code only, do not enter JavaScript code)</span></p>
					<p><textarea name="miembropress_code" id="miembropress_code" class="code" cols="100" rows="10" onchange="assignDropdown()"><?php echo htmlentities($code); ?></textarea></p>
					<p>
						<select name="miembropress_email" id="miembropress_email">
							<option value="-">--- Email Address Field (optional) ---</option>
						</select>
						<select name="miembropress_firstname" id="miembropress_firstname">
							<option value="-">--- First Name Field ---</option>
						</select>
						<select name="miembropress_lastname" id="miembropress_lastname">
							<option value="-">--- Last Name Field (optional) ---</option>
						</select>
					</p>

					<p><input class="button-primary menus_buttons button-activate " type="submit" value="Save All Changes" /></p>
				<?php endif; ?>

        	</form>
        	<!-- important: parse area is outside our form -->
        	<div id="miembropress_parse" style="display:none;"></div>

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

	function menu_autoresponder_javascript() { 

		?>
		<script type='text/javascript'>

			function verifyForm(emailValue, firstValue, lastValue) {
				assignDropdown(); // update forms

				var parseForm = jQuery('#miembropress_parse').find('form:first'); // form to submit
				parseForm = jQuery(parseForm);

				// Fill in values
				var emailField = jQuery('#miembropress_email').val();
				var firstField = jQuery('#miembropress_firstname').val();
				var lastField = jQuery('#miembropress_lastname').val();

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
				var parseForm = document.getElementById('miembropress_parse');
				var email = document.getElementById('miembropress_email');
				var firstname = document.getElementById('miembropress_firstname');
				var lastname = document.getElementById('miembropress_lastname');

				var choices = new Array();

				jQuery("#miembropress_parse").html(
					jQuery("#miembropress_code").val()
				);

				jQuery(parseForm).find("input[name='redirect'], style").each(function(i, obj) { jQuery(obj).remove(); });

				jQuery(parseForm).find("input").each(function(i, src) {
		        	var t = jQuery(src).attr("type");
	                if (t == "text" || t == "email") {
	                	choices[choices.length] = jQuery(src).attr("name");
	                }
	            });

				// Update text area
				jQuery("#miembropress_code").val(
					jQuery("#miembropress_parse").html()
				);

				populateDropdown(email, choices, "Email Field: ");
				populateDropdown(firstname, choices, "First Name Field: ");
				populateDropdown(lastname, choices, "Last Name Field: ");

				//firstname.length = 0;

				if (jQuery('#lastname_stored').val() == '') {
				   lastname.value = undefined;
				}

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
	
			assignDropdown();
		</script>
		<?php
	}

	public function menu_levels() {
		global $miembropress;
		$action = (isset($_GET["miembropress_action"])) ? $_GET["miembropress_action"] : "levels"; 
		?>
		<div class="wrap" style="clear:both;">
			<?php $this->menu_header("Levels"); ?>
			<h3 class="nav-tab-wrapper menu__tabs">
				<a class="nav-tab<?php if ($action == "levels"): ?> nav-tab-active active<?php endif; ?>" href="?page=<?php echo plugin_basename('miembro-press/miembro-press.php'); ?>-levels&miembropress_action=levels">Manage Levels</a>
				<a class="nav-tab<?php if ($action == "registration"): ?> nav-tab-active active<?php endif; ?>" href="?page=<?php echo plugin_basename('miembro-press/miembro-press.php'); ?>-levels&miembropress_action=registration">Registration Page</a>
				<a class="nav-tab<?php if ($action == "upgrade"): ?> nav-tab-active active<?php endif; ?>" href="?page=<?php echo plugin_basename('miembro-press/miembro-press.php'); ?>-levels&miembropress_action=upgrade">Sequential Upgrade</a>
			</h3>
			<?php

			if ($action == "levels") {
				$this->menu_levels_list();
			} elseif ($action == "registration") {
				$this->menu_levels_registration();
			} elseif ($action == "upgrade") {
				$this->menu_levels_upgrade();
			} ?>
		</div>
		<?php
	}

	public function menu_levels_list() {
		global $wpdb;
		global $miembropress;
		$levels = $miembropress->model->getLevels();
		$currentLevel = -1;
		$valoresGDPR = -1;
		$levelTable = $miembropress->model->getLevelTable();
		if (isset($_REQUEST["l"])) {
			$currentLevel = intval($_REQUEST["l"]);
			$valoresGDPR = $wpdb->get_results("SELECT `gdpr_label`, `gdpr_url`, `gdpr_text`, `gdpr_color`, `gdpr_size` FROM `$levelTable` WHERE `ID` = $currentLevel", ARRAY_A);
			foreach ($valoresGDPR as $clave) {
				$gdpr_label = $clave["gdpr_label"];
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
			if ($pageValue->post_name == "miembropress") { continue; }
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
		if (isset($_POST["miembropress_gdpr_url"])){
			$gdprURL = $_POST["miembropress_gdpr_url"];
			$wpdb->query("UPDATE `$levelTable` SET `gdpr_url` = '$gdprURL' WHERE `ID` = $currentLevel");
			if (isset($_POST["miembropress_gdpr_color"])){
				$gdprColor = $_POST["miembropress_gdpr_color"];
				$wpdb->query("UPDATE `$levelTable` SET `gdpr_color` = '$gdprColor' WHERE `ID` = $currentLevel");
			}
			if (isset($_POST["miembropress_gdpr_text"])){
				$gdprText = $_POST["miembropress_gdpr_text"];
				$wpdb->query("UPDATE `$levelTable` SET `gdpr_text` = '$gdprText' WHERE `ID` = $currentLevel");
			}
			if (isset($_POST["miembropress_gdpr_size"])){
				$gdprSize = $_POST["miembropress_gdpr_size"];
				$wpdb->query("UPDATE `$levelTable` SET `gdpr_size` = '$gdprSize' WHERE `ID` = $currentLevel");
			}
			if (isset($_POST["miembropress_gdpr_label"])){
				$gdprLabel = $_POST["miembropress_gdpr_label"];
				$wpdb->query("UPDATE `$levelTable` SET `gdpr_label` = '$gdprLabel' WHERE `ID` = $currentLevel");
			}
			$currentLevel = -1;
		}

		if (isset($_POST["miembropress_delete"]) && is_array($_POST["miembropress_delete"])) {
			$deletes = array_keys($_POST["miembropress_delete"]);
			foreach ($deletes as $delete) {
				$miembropress->model->deleteLevel($delete);
			}
		}

		if (isset($_POST["miembropress_new"]["name"]) && !empty($_POST["miembropress_new"]["name"])) {
			$new = $_POST["miembropress_new"];
			if (!isset($new["all"])) { $new["all"] = false; }
			if (!isset($new["comments"])) { $new["comments"] = false; }
			$miembropress->model->createLevel($new["name"], $new["all"], $new["comments"], $new["hash"]);
		}

		if (isset($_POST["miembropress_level"]) && is_array($_POST["miembropress_level"])) {
			foreach ($_POST["miembropress_level"] as $levelID => $levelName) {
				$levelAll = isset($_POST["miembropress_all"][$levelID]) ? true : false;
				$levelComments = isset($_POST["miembropress_comments"][$levelID]) ? true : false;
				$levelRegister = 0;
				$levelLogin = 0;
				$gdprActive = isset($_POST["miembropress_gdpr_active"][$levelID]) ? true : false;
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
				$miembropress->model->editLevel($levelID, array( "level_name" => $levelName, "level_all" => $levelAll, "gdpr_active" => $gdprActive, "level_comments" => $levelComments, "level_page_register" => $levelRegister, "level_page_login" => $levelLogin, "level_expiration" => $levelExpiration ));

			}
		} 
		
		?>
		<h3>Manage Membership Levels</h3>
		<p>Use levels to create &quot;groups&quot; or &quot;packages&quot; of content to give away or resell.</p>
		<p>After you've created the level you want, use the Content tab to assign content to that level, then use the Members tab to assign members to add them to your level.</p>
		<p><b>Important:</b> Levels cannot be deleted if they contain members. If a level below cannot be deleted, you probably need to remove members from it.</p>
		<form method="post">
			<table class="widefat" style="width:1000px;">
				<thead>
					<tr>
						<th scope="col">&nbsp;</th>
						<th scope="col" style="line-height:20px; width:15%;">Membership Level</th>
						<th scope="col" style="line-height:20px; width:10%; padding-right:20px;"><nobr>Access To...</nobr></th>
						<th scope="col" style="line-height:20px; width:50%;">Registration URL</th>
						<th scope="col" style="line-height:20px; width:25%;">Redirect Pages</th>
						<th scope="col">GDPR</th>
					</tr>
				</thead>
				<tbody>
					<?php $i = 0; ?>
					<?php foreach ($miembropress->model->getLevels() as $level): ?>
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
								<input type="checkbox" class="miembropress_delete" name="miembropress_delete[<?php echo intval($level->ID); ?>]" id="miembropress_delete[<?php echo intval($level->ID); ?>]" />
								<?php endif; ?>
							</td>
							<td style="padding:5px; vertical-align:top;">
								<input type="text" name="miembropress_level[<?php echo intval($level->ID); ?>]" size="20" value="<?php echo htmlentities($level->level_name); ?>" /><br />
								<small>
								   &nbsp; <a href="<?php echo $this->tabLink("levels") . "&miembropress_action=registration&l=" . intval($level->ID); ?>">Registration Page</a><br />
								   &nbsp; <a href="<?php echo $this->tabLink("levels") . "&miembropress_action=upgrade"; ?>">Sequential Upgrade</a>
								</small>
							</td>
								<td style="padding:5px; vertical-align:top; padding-right:20px;">
								<nobr><label><input type="checkbox" name="miembropress_all[<?php echo intval($level->ID); ?>]" <?php if ($level->level_all): ?>checked="checked"<?php endif; ?> /> All Posts &amp; Pages</label></nobr><br />
								<nobr><label><input type="checkbox" name="miembropress_comments[<?php echo intval($level->ID); ?>]" <?php if ($level->level_comments): ?>checked="checked"<?php endif; ?> /> Write Comments</label></nobr><br />
								<nobr><label><input type="checkbox" class="miembropress_expires" name="level_expires[<?php echo intval($level->ID); ?>]" <?php checked($level->level_expiration > 0); ?> /> Expires<span class="miembropress_expires_detail" style="display:<?php echo ($level->level_expiration > 0) ? 'inline' : 'none'; ?>"> After <input type="text" class="miembropress_expiration" name="level_expiration[<?php echo intval($level->ID); ?>]" size="2" maxlength="5" value="<?php echo $showExpiration; ?>" style="font-size:10px;" /> Days</span></label></nobr>
							</td>
							<td style="padding:5px; vertical-align:middle; word-break: break-all;">
								<a href="<?php echo $miembropress->model->signupURL($level->level_hash); ?>" target="_blank"><?php echo $miembropress->model->signupURL($level->level_hash); ?></a>
							</td>
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
										<label style="display: unset;">
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
							<input type="checkbox" name="miembropress_gdpr_active[<?php echo intval($level->ID); ?>]" <?php if ($level->gdpr_active): ?>checked="checked"<?php endif; ?> />
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<p><input class="button-primary menus_buttons button-activate " type="submit" value="Save All Changes" onclick="return miembropress_confirmLevels();"/> 
			<input class="button-primary menus_buttons button-activate " type="submit" value="Delete Selected Levels" onclick="return miembropress_confirmLevels();"/></p>
		</form>
	    <script type="text/javascript">
			jQuery(function() {
				jQuery(".miembropress_expires").click(function() {
					jQuery(this).next(".miembropress_expires_detail").toggle().children(".miembropress_expiration").get(0).select();
				});
			});
			function miembropress_confirmLevels() {
				var result = true;
				jQuery('.miembropress_delete').each(function(i, obj) {
					if (obj.checked) {
						result = confirm('Are you SURE you want to delete the selected levels? This action cannot be undone. Click OK to Continue, or Cancel to stop.');
						return;
					}
				});
				return result;
			}
		</script>
		<br />
		<h3>Add a New Membership Level</h3>
		<form method="post">
			<input type="hidden" name="action" value="miembropress_add_level">
			<table class="widefat" style="width:1000px;">
				<thead>
					<tr>
						<th style="padding:5px;">&nbsp;</th>
						<th scope="col" style="line-height:20px; width:10%;">Membership Level</th>
						<th scope="col" style="line-height:20px;"><nobr>Access To...</nobr></th>
						<th scope="col" style="line-height:20px;">Registration URL</th>
					</tr>
				</thead>
				<tbody>
					<tr class="alternate" style="height:50px;">
						<td style="padding:5px; vertical-align:middle;">&nbsp;</td>
						<td style="padding:5px; vertical-align:middle;"><input type="text" name="miembropress_new[name]" size="20" placeholder="New Level Name"></td>
						<td style="padding:5px; vertical-align:middle;">
							<nobr><label><input type="checkbox" name="miembropress_new[all]" checked="checked" /> All Posts &amp; Pages</label></nobr><br />
							<nobr><label><input type="checkbox" name="miembropress_new[comments]" checked="checked" /> Write Comments</label></nobr>
						</td>
						<td style="padding:5px; vertical-align:middle;"><nobr><label><?php echo $miembropress->model->signupURL(); ?><input type="text" size="8" name="miembropress_new[hash]" value="<?php echo $miembropress->model->hash(); ?>" size="6"></label></nobr></td>
					</tr>
				</tbody>
			</table>
			<p><input class="button-primary menus_buttons button-activate " type="submit" value="Add New Level" /></p>
		</form>
		<br />
		<h3>Modify GDPR by Levels</h3>
		<form method="post">
			<p>Select Level: <select name="l" onchange="this.form.submit()">
				<option value="-1" <?php if ($currentLevel === null || $currentLevel == - 1): ?>selected="selected"<?php endif; ?>>Levels...</option>
				<?php foreach ($levels as $level): ?>
					<option <?php if ($level->ID == $currentLevel): ?>selected="selected"<?php endif; ?> value="<?php echo intval($level->ID); ?>"><?php echo htmlentities($level->level_name); ?></option>
				<?php endforeach; ?>
				</select><input class="button-primary menus_buttons button-activate " type="submit" value="Select Level" /></p>
			<?php if ($currentLevel > - 1): ?>
				<div>
					<p><?php echo __('<a href="javascript:void" title="Enter Text Without Privacy Policy Link" class="tooltip"><span title="Tip">Text Without Privacy Policy Link</span></a>:', 'kingdomresponse')?>
					<input required type="text" name="miembropress_gdpr_label" value="<?php echo $gdpr_label?>"></p>
					<p><?php echo __('<a href="javascript:void" title="Enter Text Privacy Policy" class="tooltip"><span title="Tip">Text Privacy Policy</span></a>:', 'kingdomresponse')?>
					<input required type="text" name="miembropress_gdpr_text" value="<?php echo $gdpr_text?>"></p>
					<p><?php echo __('<a href="javascript:void" title="Enter URL Privacy Policy" class="tooltip"><span title="Tip">URL Privacy Policy (full URL)</span></a>:', 'kingdomresponse')?>
					<input required type="text" name="miembropress_gdpr_url" value="<?php echo $gdpr_url?>"></p>
					<p><?php echo __('<a href="javascript:void" title="Enter Color Text Privacy Policy" class="tooltip"><span title="Tip">Color Privacy Policy</span></a>:', 'kingdomresponse')?>
						<input type="color" name="miembropress_gdpr_color" value="<?php echo $gdpr_color?>"></p>
					<p><?php echo __('<a href="javascript:void" title="Enter Size Text Privacy Policy" class="tooltip"><span title="Tip">Size Text Privacy Policy</span></a>:', 'kingdomresponse')?>
						<input required type="number" name="miembropress_gdpr_size" min="1" max="70" value="<?php echo $gdpr_size?>"></p>
				</div>

				<p><input class="button-primary menus_buttons button-activate " type="submit" value="Save All Changes" /></p>
			<?php endif; ?>
		</form>
		<?php
	}

	public function menu_levels_registration() {
		global $miembropress;
		$levels = $miembropress->model->getLevels();
		$currentLevel = -1;
		$levelName = "";
		$forLevel = "All Levels";
		if (isset($_REQUEST["l"])) {
			$currentLevel = $_REQUEST["l"];
			if (isset($levels[$currentLevel])) {
				$level = $levels[$currentLevel];
				$levelName = $level->level_name;
				$forLevel = '<a href="'.$miembropress->model->signupURL($level->level_hash).'">'.htmlentities($levelName) . " Level".'</a>';
			}
		}
		$save = null;
		if (isset($_POST["miembropress_save"])) {
			$save = intval($_POST["miembropress_save"]);
		}
		if ($save !== null && isset($_POST["miembropress_registration_header"])) {
			$header = stripslashes($_POST["miembropress_registration_header"]);
			if (!$header || $header == "") {
				$miembropress->model->levelSetting($save, "header", null);
			} else {
				$miembropress->model->levelSetting($save, "header", $header);
			}
		}
		if ($save !== null && isset($_POST["miembropress_registration_footer"])) {
			$footer = stripslashes($_POST["miembropress_registration_footer"]);
			if (!$footer || $footer == "") {
				$miembropress->model->levelSetting($save, "footer", null);
			} else {
				$miembropress->model->levelSetting($save, "footer", $footer);
			}
		}
		$header = $miembropress->model->levelSetting($currentLevel, "header");
		$footer = $miembropress->model->levelSetting($currentLevel, "footer"); ?>
		<h3>Registration Page</h3>
		<p>Customize the look and feel of the registration pages for each level (the screen people see after the pay, and are filling in their account details).</p>
		<p>It is highly recommended you place a Facebook retargeting pixel (custom website audience) or tracking conversion pixel in the &quot;footer&quot; section for the appropriate level.</p>
		<form method="POST">
			<input type="hidden" name="miembropress_save" value="<?php echo intval($currentLevel); ?>" />
			<p>
				<b>Browse Level:</b>
				<a <?php if (-1 == $currentLevel): ?>style="font-weight:bold;"<?php endif; ?>href="<?php echo $this->tabLink("levels") . "&miembropress_action=registration&l=-1"; ?><?php if (isset($_GET["o"])): ?>&o=<?php echo htmlentities($_GET["o"]); ?><?php endif; ?>">All Members (<?php echo $miembropress->model->getMemberCount(); ?>)</a> &nbsp;&nbsp;
				<?php foreach ($levels as $level): ?>
				<a <?php if (is_numeric($currentLevel) && $level->ID == $currentLevel): ?>style="font-weight:bold;"<?php endif; ?> href="<?php echo $this->tabLink("levels") . "&miembropress_action=registration&l=" . intval($level->ID); ?>"><?php echo htmlentities($level->level_name); ?> (<?php echo intval($level->active); ?>)</a> &nbsp;&nbsp;
				<?php endforeach; ?>
			</p>

			<p><b>Registration Page Header for <?php echo $forLevel; ?>:</b><br >
			<textarea name="miembropress_registration_header" class="code" cols="120" rows="8" style="font-size:10px;"><?php echo htmlentities($header); ?></textarea></p>
			<p><b>Registration Page Footer for <?php echo $forLevel; ?>:</b><br >
			<textarea name="miembropress_registration_footer" class="code" cols="120" rows="8" style="font-size:10px;"><?php echo htmlentities($footer); ?></textarea></p>
			<p><input type="submit" class="button-primary menus_buttons button-activate " value="Save All Changes"></p>
		</form>
		<?php
	}

	public function menu_levels_upgrade() {
		global $miembropress;
		$levels = $miembropress->model->getLevels();
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
		if (isset($_POST["miembropress_upgrade_level"])) {
			$actions = $_POST["miembropress_upgrade_action"];
			$upgrades = $_POST["miembropress_upgrade_level"];
			$upgrade = 0;
			foreach ($_POST["miembropress_upgrade_level"] as $upgradeFrom => $upgradeTo) {
				$previousLevel = $_POST["miembropress_upgrade_level_previous"][$upgradeFrom];
				$previousAction = $_POST["miembropress_upgrade_level_previous"][$upgradeFrom];
				$previousDelay = @intval($_POST["miembropress_upgrade_delay_previous"][$upgradeFrom]);
				$previousDate = @intval($_POST["miembropress_upgrade_date_previous"][$upgradeFrom]);
				$schedule = $_POST["miembropress_upgrade_schedule"][$upgradeFrom];
				$action = null;
				if (isset($actions[$upgradeFrom])) {
					$action = $actions[$upgradeFrom];
				}
				$delay = 0;
				if ($schedule == "after") {
					$delay = @intval($_POST["miembropress_upgrade_after"][$upgradeFrom]);
				}
				$date = null;
				if ($schedule == "date") {
					$date = @intval($_POST["miembropress_upgrade_date"][$upgradeFrom])/1000;
				} else { $date = null; }

				if ($previousLevel == $upgradeTo && $previousAction == $action && $previousDelay == $delay && $previousDate == $date) { continue; }
				if ($upgradeTo == 0 || !$action) {
					$miembropress->model->levelSetting($upgradeFrom, "add", null);
					$miembropress->model->levelSetting($upgradeFrom, "move", null);
					$miembropress->model->levelSetting($upgradeFrom, "upgrade", null);
					$miembropress->model->levelSetting($upgradeFrom, "delay", null);
					$change = true; continue;
				} else {
					$miembropress->model->levelSetting($upgradeFrom, "dateDelay", $date);
					if ($action == "add") {
						$miembropress->model->levelSetting($upgradeFrom, "add", $upgradeTo);
						$miembropress->model->levelSetting($upgradeFrom, "move", null);
						$change = true;
					} elseif ($action == "move") {
						$miembropress->model->levelSetting($upgradeFrom, "add", null);
						$miembropress->model->levelSetting($upgradeFrom, "move", $upgradeTo);
						$change = true;
					}
					$miembropress->model->levelSetting($upgradeFrom, "delay", $delay);
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
					if ($actionAdd = $miembropress->model->levelSetting($level->ID, "add")) {
						$actionMethod = "add";
						$actionValue = $actionAdd;
					} elseif ($actionMove = $miembropress->model->levelSetting($level->ID, "move")) {
						$actionMethod = "move"; $actionValue = $actionMove;
					} else { $actionMethod = ""; }
					$schedule = null;
					$actionDate = $miembropress->model->levelSetting($level->ID, "dateDelay");
					if ($actionDate) { $schedule = "date"; }
					if (!$actionDate) { $actionDate = 0; }
					$actionDelay = @intval($miembropress->model->levelSetting($level->ID, "delay"));
					if (!$schedule && $actionDelay > 0) { $schedule = "day"; }
					if (!$schedule) { $schedule = "instant"; }
					$levelLink = add_query_arg(array('page' => plugin_basename('miembro-press/miembro-press.php').'-levels', 'miembropress_action' => 'levels'), admin_url('admin.php')); ?>

					<!-- name of level we will apply this to -->
					<td style="padding:5px; padding-left:15px; vertical-align:middle;"><a href="<?php echo $levelLink; ?>"><?php echo htmlentities($level->level_name); ?></a></td>

					<!-- add or move -->
					<td style="padding:5px; vertical-align:middle;" align="center">
						<nobr>
							<input type="hidden" name="miembropress_upgrade_action_previous[<?php echo intval($level->ID); ?>]" value="<?php echo intval($actionValue); ?>" />
							<label><input type="radio" name="miembropress_upgrade_action[<?php echo intval($level->ID); ?>]" value="add" class="miembropress_upgrade_action_add" rel="<?php echo intval($level->ID); ?>" <?php checked($actionMethod == "add"); ?> />Add</label>&nbsp;&nbsp;
							<label><input type="radio" name="miembropress_upgrade_action[<?php echo intval($level->ID); ?>]" value="move" class="miembropress_upgrade_action_move" rel="<?php echo intval($level->ID); ?>" <?php checked($actionMethod == "move"); ?> />Move</label>&nbsp;&nbsp;
							<label><input type="radio" name="miembropress_upgrade_action[<?php echo intval($level->ID); ?>]" value="" class="miembropress_upgrade_action_nothing" rel="<?php echo intval($level->ID); ?>" <?php checked($actionMethod == ""); ?> />Do Nothing</label>
						</nobr>
					</td>

					<!-- select level -->
					<td style="padding:5px; vertical-align:middle;">
						<input type="hidden" name="miembropress_upgrade_level_previous[<?php echo intval($level->ID); ?>]" value="<?php echo intval($actionUpgrade); ?>" />
						<select name="miembropress_upgrade_level[<?php echo intval($level->ID); ?>]" class="miembropress_upgrade_level" rel="<?php echo intval($level->ID); ?>">
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
						<input type="hidden" name="miembropress_upgrade_delay_previous[<?php echo intval($level->ID); ?>]" value="<?php echo intval($actionValue); ?>" />

						<input type="hidden" name="miembropress_upgrade_date_previous[<?php echo intval($level->ID); ?>]" value="<?php echo intval($actionDate) * 1000; ?>" />
						<input type="hidden" name="miembropress_upgrade_date[<?php echo intval($level->ID); ?>]" id="miembropress_upgrade_date[<?php echo intval($level->ID); ?>]" value="<?php echo intval($actionDate) * 1000; ?>" />
						<nobr>
							<label><input type="radio" name="miembropress_upgrade_schedule[<?php echo intval($level->ID); ?>]" value="instant" class="miembropress_upgrade_delay" rel="<?php echo intval($level->ID); ?>" <?php checked($schedule == "instant"); ?> />Instantly</label><br />
							<label><input type="radio" name="miembropress_upgrade_schedule[<?php echo intval($level->ID); ?>]" value="after" class="miembropress_upgrade_delay" rel="<?php echo intval($level->ID); ?>" <?php checked($schedule == "day"); ?> /><span class="miembropress_upgrade_after_none" style="display:<?php echo (($schedule != "day") ? 'inline' : 'none'); ?>;" rel="<?php echo intval($level->ID); ?>">After...</span><span class="miembropress_upgrade_after" style="display:<?php echo (($schedule == "day") ? 'inline' : 'none'); ?>;" rel="<?php echo intval($level->ID); ?>">After <input type="number" class="miembropress_after_delay" name="miembropress_upgrade_after[<?php echo intval($level->ID); ?>]" size="3" maxlength="5" value="<?php echo max(1, $actionDelay); ?>" style="font-size:11px; width:50px;" /> Days on Level</span>
							</label><br />
							<label><input type="radio" name="miembropress_upgrade_schedule[<?php echo intval($level->ID); ?>]" value="date" class="miembropress_upgrade_delay" rel="<?php echo intval($level->ID); ?>" <?php checked($schedule == "date"); ?> /><span class="miembropress_upgrade_date_none" style="display:<?php echo (($schedule != "date") ? 'inline' : 'none'); ?>;" rel="<?php echo intval($level->ID); ?>">On Date...</span><span class="miembropress_upgrade_date" style="display:<?php echo (($schedule == "date") ? 'inline' : 'none'); ?>;" rel="<?php echo intval($level->ID); ?>">On Date: <input type="text" class="miembropress_date_delay" rel="<?php echo intval($level->ID); ?>" name="miembropress_upgrade_date_display[<?php echo intval($level->ID); ?>]" style="font-size:11px;" /></span>
							</label>
						</nobr>
					</td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<p><input class="button-primary menus_buttons button-activate " type="submit" value="Save All Changes" /> <input class="button-primary menus_buttons button-activate " type="submit" value="Delete Selected Levels" /></p>
		</form>
		<script type="text/javascript">

		jQuery(function() {
			jQuery('.miembropress_date_delay').each(function (i, obj) {
				var rel = jQuery(obj).attr("rel");
				var altField = '#miembropress_upgrade_date<?php echo chr(92) . chr(92); ?>['+rel+'<?php echo chr(92) . chr(92); ?>]';
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

			jQuery('input.miembropress_upgrade_delay[value="instant"]').click(function() {
				var rel = jQuery(this).attr("rel");
				jQuery('span.miembropress_upgrade_date[rel="'+rel+'"]').hide();
				jQuery('span.miembropress_upgrade_date_none[rel="'+rel+'"]').show();
				jQuery('span.miembropress_upgrade_after_none[rel="'+rel+'"]').show();
				jQuery('span.miembropress_upgrade_after[rel="'+rel+'"]').hide();
			});

			// after button clicked
			jQuery('input.miembropress_upgrade_delay[value="after"]').click(function() {
				var rel = jQuery(this).attr("rel");
				jQuery('span.miembropress_upgrade_date[rel="'+rel+'"]').hide();
				jQuery('span.miembropress_upgrade_date_none[rel="'+rel+'"]').show();
				jQuery('span.miembropress_upgrade_after_none[rel="'+rel+'"]').hide();
				jQuery('span.miembropress_upgrade_after[rel="'+rel+'"]').show();
			});

			jQuery('input.miembropress_upgrade_delay[value="date"]').click(function() {
				var rel = jQuery(this).attr("rel");
				jQuery('span.miembropress_upgrade_date[rel="'+rel+'"]').show();
				jQuery('span.miembropress_upgrade_date_none[rel="'+rel+'"]').hide();
				jQuery('span.miembropress_upgrade_after_none[rel="'+rel+'"]').show();
				jQuery('span.miembropress_upgrade_after[rel="'+rel+'"]').hide();
			});

			jQuery(".miembropress_upgrade_level").change(function() {
				var rel = parseInt(jQuery(this).attr("rel"));
				if (isNaN(rel)) { return; }

				var addOption = jQuery(".miembropress_upgrade_action_add[rel='"+rel+"']");
				var moveOption = jQuery(".miembropress_upgrade_action_move[rel='"+rel+"']");

				var instantOption = jQuery(".miembropress_upgrade_delay[rel='"+rel+"'][value='instant']");

				if (jQuery(this).val() == "0") {
				   addOption.attr("checked", false);
				   moveOption.attr("checked", false);
				   instantOption.click();
				}
				else if (!addOption.attr("checked") && !moveOption.attr("checked")) {
				   addOption.attr("checked", true);
				}
			}); // miembropress_upgrade_level
		});
		</script>
		<?php
	}

	public function menu_content() {
		global $miembropress;
		$levels = $miembropress->model->getLevels();
		$currentLevel = -1;
		if (isset($_REQUEST["l"])) {
			$currentLevel = intval($_REQUEST["l"]);
		} elseif (isset($_REQUEST["miembropress_level"])) {
			$currentLevel = intval($_REQUEST["miembropress_level"]);
		} elseif ($firstLevel = reset($levels)) {
			$currentLevel = $firstLevel->ID;
		}
		$currentLevelName = "";
		if ($currentLevel > 0 && isset($levels[$currentLevel]) && isset($levels[$currentLevel]->level_name)) {
			$currentLevelName = $levels[$currentLevel]->level_name;
		}
		$levelInfo = $miembropress->model->getLevel($currentLevel);
		$saveLevel = null;
		if (isset($_POST["miembropress_save"])) {
			$saveLevel = intval($_POST["miembropress_save"]);
		}
		if (isset($_POST["miembropress_posts"]) && is_array($_POST["miembropress_posts"]) && $saveLevel !== null) {
			foreach (array_keys($_POST["miembropress_posts"]) as $post) {
				$post = intval($post);
				if (isset($_POST["miembropress_checked"][$post])) {
					$miembropress->model->protect($post, $saveLevel);
				} else {
					$miembropress->model->unprotect($post, $saveLevel);
				}
			}
		}
		$action = "posts";
		if (isset($_REQUEST["miembropress_action"])) {
			$action = $_REQUEST["miembropress_action"];
		}
		if ($action == "posts") {
			$posts = get_posts("posts_per_page=-1");
		} elseif ($action == "pages") {
			$posts = get_pages("posts_per_page=-1");
		}
		$postAccess = $miembropress->model->getPostAccess($currentLevel);
		$allLevels = $miembropress->model->getLevels();
		?>
		<div class="wrap" style="clear:both;">
			<?php $this->menu_header("Content"); ?>
			<h3 class="nav-tab-wrapper menu__tabs">
				<a class="nav-tab<?php if ($action == "posts"): ?> nav-tab-active active<?php endif; ?>" href="?page=<?php echo plugin_basename('miembro-press/miembro-press.php'); ?>-content&miembropress_action=posts&miembropress_level=<?php echo $currentLevel; ?>">Posts</a>
				<a class="nav-tab<?php if ($action == "pages"): ?> nav-tab-active active<?php endif; ?>" href="?page=<?php echo plugin_basename('miembro-press/miembro-press.php'); ?>-content&miembropress_action=pages&miembropress_level=<?php echo $currentLevel; ?>">Pages</a>
			</h3>
			<?php if ($action == "posts"): ?><h3>Manage Posts</h3><?php endif; ?>
			<?php if ($action == "pages"): ?><h3>Manage Pages</h3><?php endif; ?>
			<p>Choose which content is shown to the everyone, and which is shown to members only in the Protection menu.<br /> If a box is checked in Protection, that means it is protected and viewable to members only.</p>
			<p>Then choose one of your membership levels to assign that post or page to that level.</p>
			<p>For example, if a post is NOT checked for the Full level, then a user on the Full level does not have access to it.<br /> If a checkbox for a post IS checked, then any user on the Full level will be able to see it.</p>
			<p>If a box cannot be unchecked (it is grayed out) that means that the level has been set to have access to ALL content.<br /> You can go back to the Levels tab, uncheck &quot;All Posts &amp; Pages&quot; then return here to control access for that post and level.</p>
			<form method="post">
				<input type="hidden" name="miembropress_save" value="<?php echo intval($currentLevel); ?>" />
				<h3>Manage Access<?php
				if ($currentLevelName) {
					echo " (" . htmlentities($currentLevelName) . ")";
				} ?>
				</h3>
				<p>Choose a Membership Level:
					<a <?php if (-1 == $currentLevel): ?>style="font-weight:bold; color:red;"<?php endif; ?>href="<?php echo $this->tabLink("content") . "&l=-1"; ?>">Protection</a> &nbsp;&nbsp;
					<?php foreach ($levels as $level): ?>
					<a <?php if (is_numeric($currentLevel) && $level->ID == $currentLevel): ?>style="font-weight:bold; color:red;"<?php endif; ?> href="<?php echo $this->tabLink("content") . "&l=".intval($level->ID) . "&miembropress_action=".$action ; ?>"><?php echo htmlentities($level->level_name); ?></a> &nbsp;&nbsp;
					<?php endforeach; ?>
				</p>

				<?php if ($currentLevelName): ?><p><a href="<?php echo admin_url("post-new.php?post_type=" . (($action == "posts") ? "post" : "page") . "&miembropress_new=" . $currentLevel); ?>" class="button-primary menus_buttons button-activate " style="top:0px;">Add New <?php echo (($action == "posts") ? 'Post' : 'Page'); ?> on &quot;<?php echo $currentLevelName; ?>&quot; Level</a></p><?php endif; ?>
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
						if ($post->post_name == "wishlist-member" || $post->post_name == "miembropress") {
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
								<input type="hidden" name="miembropress_posts[<?php echo intval($post->ID); ?>]" value="1" />
								<input type="checkbox" <?php if ($disabled): ?>disabled="disabled"<?php endif; ?> <?php if ($checked): ?>checked="checked"<?php endif; ?> name="miembropress_checked[<?php echo intval($post->ID); ?>]" id="miembropress_checked[<?php echo intval($post->ID); ?>]" />
							</th>
							<td><label for="miembropress_checked[<?php echo intval($post->ID); ?>]"><?php echo date("m/d/" . chr(89), strtotime($post->post_date)); ?></label></td>
							<td><a href="<?php echo get_edit_post_link($post->ID); ?>"><b><?php echo htmlentities($post->post_title); ?></b></a></td>
							<td>
								<?php
								$levelLinks = array();
								foreach ($miembropress->model->getLevelsFromPost($post->ID) as $levelAccess => $levelName) {
									if (!isset($allLevels[$levelAccess])) { continue; }
									$protected = $miembropress->model->isProtected($post->ID);
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
				<p><input type="submit" class="button-primary menus_buttons button-activate " value="Save All Changes" /></p>
			</form>
		</div>
		<?php
	}

	public function menu_members() {
		global $miembropress;
		$message = null;
		$messageLevels = null;
		$users = array();
		if (isset($_POST["miembropress_users"])) {
			$users = $_POST["miembropress_users"];
		}
		$action = (isset($_GET["miembropress_action"])) ? $_GET["miembropress_action"] : "members";
		$levels = null;
		if (isset($_POST["miembropress_levels"]) && is_numeric($_POST["miembropress_levels"])) {
			$levels = intval($_POST["miembropress_levels"]);
		}
		if (isset($_POST["miembropress_users"]) && is_array($_POST["miembropress_users"])) {
			foreach ($users as $user) {
				if (isset($_POST["miembropress_action_move"])) {
					$miembropress->model->move($user, $levels);
					$message = "move";
					$messageLevels = $levels;
				} elseif (isset($_POST["miembropress_action_add"])) {
					$miembropress->model->add($user, $levels);
					$message = "add";
					$messageLevels = $levels;
				} elseif (isset($_POST["miembropress_action_remove"])) {
					$miembropress->model->remove($user, $levels);
					$message = "remove";
					$messageLevels = $levels;
				} elseif (isset($_POST["miembropress_action_cancel"])) {
					$miembropress->model->cancel($user, $levels);
					$message = "cancel";
					$messageLevels = $levels;
				} elseif (isset($_POST["miembropress_action_uncancel"])) {
					$miembropress->model->uncancel($user, $levels);
					$message = "uncancel";
					$messageLevels = $levels;
				} elseif (isset($_POST["miembropress_action_delete"])) {
					$miembropress->model->deleteUser($user);
					$message = "delete";
					$messageLevels = $levels;
				}
			}
		}
		if (isset($_POST["miembropress_temps_delete"]) && isset($_POST["miembropress_temps"]) && is_array($_POST["miembropress_temps"])) {
			foreach (array_keys($_POST["miembropress_temps"]) as $temp) {
				$miembropress->model->deleteTemp($temp);
			}
		} elseif (isset($_POST["miembropress_temps_complete"]) && isset($_POST["miembropress_temps"]) && is_array($_POST["miembropress_temps"])) {
			foreach (array_keys($_POST["miembropress_temps"]) as $temp) {
				$miembropress->model->completeTemp($temp);
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

			<h3 class="nav-tab-wrapper menu__tabs">
				<a class="nav-tab<?php if ($action == "members"): ?> nav-tab-active active<?php endif; ?>" href="?page=<?php echo plugin_basename('miembro-press/miembro-press.php'); ?>-members&miembropress_action=members">Manage Members (<?php echo $miembropress->model->getMemberCount(); ?>)</a>
				<a class="nav-tab<?php if ($action == "incomplete"): ?> nav-tab-active active<?php endif; ?>" href="?page=<?php echo plugin_basename('miembro-press/miembro-press.php'); ?>-members&miembropress_action=incomplete">Incomplete Registrations (<?php echo $miembropress->model->getTempCount(); ?>)</a>
				<a class="nav-tab<?php if ($action == "export"): ?> nav-tab-active active<?php endif; ?>" href="?page=<?php echo plugin_basename('miembro-press/miembro-press.php'); ?>-members&miembropress_action=export">Export</a>
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

	public function create($vars=null, $api=false) {
		global $miembropress;
		global $wpdb;
		$tablaLicencias = $wpdb->prefix . "licencias";
		$tablaLicenciasMember = $wpdb->prefix . "licencias_member";
		$userTable = $miembropress->model->getUserTable();
		$notify = true;
		$redirect = true;
		if ($api) { $redirect = false; }
		if ($vars==null) {
			if (!$api && !is_admin() && !current_user_can("administrator") && isset($_POST["miembropress_level"])) {
				unset($_POST["miembropress_level"]);
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
		extract(MiembroPress::extract($vars));
		$validate = MiembroPress::validate($vars);
		
		$level = null;
		if (isset($vars["miembropress_level"])) {
			$level = intval($vars["miembropress_level"]);
		} elseif (isset($miembropress->registerTemp) && $miembropress->registerTemp) {
			$temp = $miembropress->model->getTempFromTransaction($miembropress->registerTemp);
			if ($temp && isset($temp->level_id)) {
				$level = intval($temp->level_id);
			}
		} elseif (isset($miembropress->registerLevel) && isset($miembropress->registerLevel->ID)) {
			$level = $miembropress->registerLevel->ID;
		} elseif ($request = $miembropress->hashRequest()) {
			if ($registerLevel = $miembropress->model->getLevelFromHash($request)) {
				$level = $registerLevel->ID;
			}
		}

		$transaction = null;
		if(isset($vars["hotmart_transaction"])){
			$transaction = $vars["hotmart_transaction"];
		}

		$ipUser = $this->getRealIP();

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
		if (isset($miembropress->registerTemp) && $miembropress->registerTemp) {
			$txn = $miembropress->registerTemp;
		} elseif (isset($vars["miembropress_temp"]) && $vars["miembropress_temp"]) {
			$txn = $vars["miembropress_temp"];
		}
		if ($existing){
			if ($user = get_user_by('login', $username)) {
				$check_password = wp_check_password($password1, $user->data->user_pass, $user->ID);
				if ($check_password){
					$miembropress->model->add($user->ID, $level, $txn);
					$miembropress->model->removeTemp($txn);
					if (!is_admin()) {
						if ($redirect) {
							wp_signon(array('user_login' => $username, 'user_password' => $password1, 'remember' => true), (is_ssl()?true:false));
							$levelInfo = $miembropress->model->getLevel($level);
							$nameLevel = $miembropress->model->getLevelName($level);
							$userID = intval($user->ID);
							$licenciaKey = $wpdb->get_var("SELECT licencias FROM wpop_licencias ORDER BY id LIMIT 1");
							$idLicencia = $wpdb->get_var("SELECT id FROM wpop_licencias ORDER BY id LIMIT 1");
							if ($nameLevel == "Full") {
								$wpdb->query("INSERT INTO wpop_licencias_member (id, licencia, activado, type, user_id) VALUES ($idLicencia, '$licenciaKey', '0', 'Full', $userID)");
								$wpdb->query("DELETE FROM wpop_licencias WHERE licencias = '$licenciaKey'");
							}
							if ($nameLevel == "Personal") {
								$wpdb->query("INSERT INTO wpop_licencias_member (id, licencia, activado, type, user_id, maxsitio) VALUES ($idLicencia, '$licenciaKey', '0', 'Personal', $userID, 1)");
								$wpdb->query("DELETE FROM wpop_licencias WHERE licencias = '$licenciaKey'");
							}
							if ($nameLevel == "Profesional") {
								$wpdb->query("INSERT INTO wpop_licencias_member (id, licencia, activado, type, user_id, maxsitio) VALUES ($idLicencia, '$licenciaKey', '0', 'Profesional', $userID, 3)");
								$wpdb->query("DELETE FROM wpop_licencias WHERE licencias = '$licenciaKey'");
							}
							if ($nameLevel == "Agencia") {
								$wpdb->query("INSERT INTO wpop_licencias_member (id, licencia, activado, type, user_id, maxsitio) VALUES ($idLicencia, '$licenciaKey', '0', 'Agencia', $userID, 10)");
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
					$miembropress->model->userSetting($newUser, $key, $value);
				}
			}

			//ESTO VA SI O SI
			// miembropress_hotmart_transaction
			$miembropress->model->add($newUser, $level, $txn, null, $transaction, $ipUser);
			if (isset($temp) && isset($temp->level_status) && $temp->level_status == "C") {
				$miembropress->model->cancel($newUser, $level);
			}

			$userID = intval($newUser);
			$levelInfo = $miembropress->model->getLevel($level);
			$nameLevel = $miembropress->model->getLevelName($level);

			$licenciaKey = $wpdb->get_var("SELECT licencias FROM $tablaLicencias ORDER BY id LIMIT 1");
			$idLicencia = $wpdb->get_var("SELECT id FROM $tablaLicencias ORDER BY id LIMIT 1");
			if ($nameLevel == "Full") {
				$wpdb->query("INSERT INTO $tablaLicenciasMember (id, licencia, activado, type, user_id) VALUES ($idLicencia, '$licenciaKey', '0', 'Full', $userID)");
				$wpdb->query("DELETE FROM $tablaLicencias WHERE licencias = '$licenciaKey'");
			}
			if ($nameLevel == "Personal") {
				$wpdb->query("INSERT INTO $tablaLicenciasMember (id, licencia, activado, type, user_id, maxsitio) VALUES ($idLicencia, '$licenciaKey', '0', 'Personal', $userID, 3)");
				$wpdb->query("DELETE FROM $tablaLicencias WHERE licencias = '$licenciaKey'");
			}
			if ($nameLevel == "Profesional") {
				$wpdb->query("INSERT INTO $tablaLicenciasMember (id, licencia, activado, type, user_id, maxsitio) VALUES ($idLicencia, '$licenciaKey', '0', 'Profesional', $userID, 9)");
				$wpdb->query("DELETE FROM $tablaLicencias WHERE licencias = '$licenciaKey'");
			}
			if ($nameLevel == "Agencia") {
				$wpdb->query("INSERT INTO $tablaLicenciasMember (id, licencia, activado, type, user_id, maxsitio) VALUES ($idLicencia, '$licenciaKey', '0', 'Agencia', $userID, 30)");
				$wpdb->query("DELETE FROM $tablaLicencias WHERE licencias = '$licenciaKey'");
			}

			$miembropress->model->removeTemp($txn);
			$headers = 'From: '.get_option("blogname").' < '.get_option("admin_email") .' > ' . "";
			$home = home_url("/login");
			$message = "Un nuevo miembro se ha registrado con la siguiente información: \nSitio Web: ".$home."\nNombre de Usuario: ".$username."\nEmail: ".$email."\nNombre: ".$firstname."\nApellido: ".$lastname." ";
			$message .= "\n Dirección IP: ".$_SERVER["REMOTE_ADDR"]." ";
			$message .= "";
			$levels = $miembropress->model->getLevels();
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
			if ($attribution = $miembropress->model->setting("emailattribution")) {
				$link = "http://www.miembropress.com";
				if ($affiliate = $miembropress->model->setting("affiliate")) {
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

	function getRealIP() {
		if (!empty($_SERVER['HTTP_CLIENT_IP']))
			return $_SERVER['HTTP_CLIENT_IP'];
		   
		if (!empty($_SERVER['HTTP_X_FORWARDED_FOR']))
			return $_SERVER['HTTP_X_FORWARDED_FOR'];
	   
		return $_SERVER['REMOTE_ADDR'];
	}

	function register($blank=false) {
		global $wpdb;
		global $miembropress;
		$page = (isset($_REQUEST["page"]) ? $_REQUEST["page"] : "");
		$pluginPage = (function_exists("plugin_basename") ? plugin_basename('miembro-press/miembro-press.php') : "");
		if (!function_exists("current_user_can") || !current_user_can("manage_options") || !is_admin() || strpos($page, $pluginPage) === false) {
			if (!$miembropress->registerLevel && !$miembropress->registerTemp) { return; }
		}
		extract(MiembroPress::extract());
		$validate = MiembroPress::validate();
		if (isset($miembropress->registerMetadata)) {
			extract($miembropress->registerMetadata);
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
		if (isset($_POST["miembropress_username"])) {
			$username = stripslashes($_POST["miembropress_username"]);
		}
		if (!is_admin()) {
			if ($globalHeader = $miembropress->model->levelSetting(-1, "header")) {
				eval( ' ?> '.stripslashes($globalHeader).' <?php ' );
			}
			if (isset($miembropress->registerLevel->ID) && ($header = $miembropress->model->levelSetting($miembropress->registerLevel->ID, "header"))) {
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
						<a style="text-decoration:underline;" href="#" onclick="document.getElementById('miembropress_registration').style.display='block'; this.innerHTML = 'Rellene el siguiente formulario para crear una nueva cuenta'; return false;">Haz clic aquí para crear una nueva cuenta</a>
					<?php endif; ?>
					</b>
				</p>
			<?php endif; ?><br />
		<?php endif; ?>
		<div id="iv-form3" class="col-md-12">
			<form method="post" class="form-horizontal">
				<input type="hidden" name="action" value="miembropress_register">
				<?php if (is_user_logged_in() && current_user_can("manage_options")): ?>
					<input type="hidden" name="wp_http_referer" value="miembropress" />
				<?php endif; ?>
				<?php if (isset($miembropress->registerTemp)): ?><input type="hidden" name="miembropress_temp" value="<?php echo htmlentities($miembropress->registerTemp); ?>">
				<?php elseif (isset($miembropress->registerLevel->level_hash)): ?><input type="hidden" name="miembropress_hash" value="<?php echo htmlentities($miembropress->registerLevel->level_hash); ?>">
				<?php endif; ?>
				<?php if (isset($miembropress->registerLevel->ID)): ?>
					<input type="hidden" name="miembropress_register" value="<?php echo intval($miembropress->registerLevel->ID); ?>">
				<?php endif; ?>
				<?php if (isset($_GET['trs'])): ?>
					<input type="hidden" name="hotmart_transaction" value="<?php echo $_GET['trs']; ?>">
				<?php endif; ?>
				<?php if (isset($_GET["existing"]) && $_GET["existing"] == 1): ?>
					<div class="row">
						<div class="col-md-1"></div>
						<div class="col-md-10">
							<h2 class="header-profile"><div>Acceso a cuenta existente</div></h2>
						</div>
					</div>
					<div class="row">
						<div class="col-md-1 "></div>
						<div class="col-md-10 "> <div>
							<?php if (isset($_GET["passwordSent"])): ?>
								<blockquote>
									<p>Nueva contraseña enviada. Por favor revise su correo electrónico y continúe llenando esta página.</p>
								</blockquote>
							<?php elseif (isset($validate["userAvailable"]) && $validate["userAvailable"] == true): ?>
								<blockquote>
									<p>El usuario no existe:<br /> <a href="<?php echo $nonExistingLink; ?>">Haz clic aquí para crear una nueva cuenta.</a></p>
								</blockquote>
							<?php elseif (isset($_POST["miembropress_password1"]) && isset($validate["passwordCorrect"]) && $validate["passwordCorrect"] == false): ?>
								<blockquote>
									<p>Contraseña incorrecta:<br /> <a href="<?php echo wp_lostpassword_url($lostPasswordLink); ?>">Haga clic aquí para recuperar su contraseña</a><br />
									Abre en una nueva ventana, asegúrese de volver a esta página.</p>
								</blockquote>
							<?php endif; ?>
							<div class="form-group row">
								<label for="miembropress_username" class="col-md-4 control-label"><b>Nombre de usuario:</b></label>
								<div class="col-md-8 has-success">
									<input type="text" placeholder="Introduce tu nombre de usuario" class="form-control ctrl-textbox valid" name="miembropress_username" id="miembropress_username" size="15" value="<?php echo htmlentities($username); ?>">
								</div>
							</div>
							<div class="form-group row">
								<label for="miembropress_password" class="col-md-4 control-label"><b>Contraseña:</b></label>
								<div class="col-md-8 has-success">
									<input type="password" placeholder="Introduce tu contraseña" class="form-control ctrl-textbox valid" name="miembropress_password1" id="miembropress_password" size="10">
								</div>
							</div>
							<div class="row">
								<div class="col-md-4 col-xs-4 col-sm-4 "></div>
								<div class="col-md-8 col-xs-8 col-sm-8 ">
									<input type="submit" class="button-primary button-activate" value="   Ingresar a la cuenta existente   ">
									<br /><br/>
									<a href="<?php echo wp_lostpassword_url(rawurlencode($lostPasswordLink)); ?>">¿Se te olvidó tu contraseña?</a>
								</div>
							</div>
				<?php else: ?>
					<?php if (!is_admin()): ?>
							<div id="miembropress_registration">
								<div class="row">
									<div class="col-md-1"></div>
									<div class="col-md-10">
										<h2 class="header-profile"><div>Registro de Nueva Cuenta</div></h2>
									</div>
								</div>
								<div class="row">
									<div class="col-md-1 "></div>
									<div class="col-md-10 "> <div>
								<?php $miembropress->social->registration(); ?>
					<?php endif; ?>
											<div class="form-group row">
												<label for="miembropress_username" class="col-md-4 control-label"><b>Nombre de Usuario:</b></label>
												<div class="col-md-8 has-success">
													<input type="text" placeholder="Introduce un nombre de usuario" class="form-control ctrl-textbox valid" name="miembropress_username" id="miembropress_username" size="15" value="<?php echo htmlentities($username); ?>" onblur="miembropress_suggest()" />
													<div class="desc">
														<?php if (!$validate["empty"] && !$validate["username"]): ?><small>ERROR: El nombre de usuario deseado debe tener al menos 4 caracteres (letras y números).<br /></small>
														<?php elseif (!$validate["empty"] && !$validate["userAvailable"]): ?><small>ERROR: Nombre de usuario existente, por favor intente con otro.<br /></small>
														<?php else: ?><small>Ingrese su nombre de usuario deseado. <br /> Debe tener al menos 4 caracteres (letras y números) de largo.</small><?php endif; ?>
													</div>
												</div>
											</div>
											
										
											<div class="form-group row">
												<label for="miembropress_firstname" class="col-md-4 control-label"><b>Nombres:</b></label>
												<div class="col-md-8 has-success">
													<input type="text" placeholder="Introduce tu nombre" class="form-control ctrl-textbox valid" name="miembropress_firstname" id="miembropress_firstname" size="15" value="<?php echo htmlentities($firstname); ?>" />
													<div class="desc">
														<?php if (!$validate["empty"] && !$validate["firstname"]): ?><small>ERROR: Su nombre debe tener al menos 2 caracteres (letras y números).<br /></small><?php endif; ?>
													</div>
												</div>
											</div>
										
											<div class="form-group row">
												<label for="miembropress_lastname" class="col-md-4 control-label"><b>Apellidos:</b></label>
												<div class="col-md-8 has-success">
													<input type="text" placeholder="Introduce tu apellido" class="form-control ctrl-textbox valid" name="miembropress_lastname" id="miembropress_lastname" size="15" value="<?php echo htmlentities($lastname); ?>">
													<div class="desc">
														<?php if (!$validate["empty"] && !$validate["lastname"]): ?><small>ERROR: Su apellido debe contener al menos 2 caracteres (letras y números).<br /></small><?php endif; ?>
													</div>
												</div>
											</div>
										
											<div class="form-group row">
												<label for="miembropress_email" class="col-md-4 control-label"><b>Email:</b></label>
												<div class="col-md-8 has-success">
													<input type="email" placeholder="Introduce tu email" class="form-control ctrl-textbox valid" name="miembropress_email" id="miembropress_email" size="25" value="<?php echo htmlentities($email); ?>">
													<div class="desc">
														<?php if (!$validate["empty"] && !$validate["email"]): ?><small>ERROR: Por favor, introduzca una dirección de correo electrónico válida.<br /></small>
														<?php elseif (!$validate["empty"] && !$validate["emailAvailable"]): ?><small>ERROR: Email existente, por favor intente con otro.</small>
														<?php endif; ?>
													</div>
												</div>
											</div>
										
											<div class="form-group row">
												<label for="miembropress_password1" class="col-md-4 control-label"><b>Contraseña (dos veces):</b></label>
												<div class="col-md-8 has-success">
													<?php if (is_admin()): ?>
														<input type="password" name="miembropress_password1" id="miembropress_password1" size="25" placeholder="(Deje en blanco para generar automáticamente)" onkeyup="document.getElementById('miembropress_password2').style.display=((this.value=='')?'none':'block');"/>
														<input type="password" name="miembropress_password2" id="miembropress_password2" size="25" placeholder="(Ingrese de nuevo la contraseña)" />
													<?php else: ?>
														<input type="password" placeholder="Introduce una contraseña" class="form-control ctrl-textbox valid" name="miembropress_password1" id="miembropress_password1" size="25"/>
														<input type="password" placeholder="Vuelva a introducir la contraseña" class="form-control ctrl-textbox valid" name="miembropress_password2" id="miembropress_password2" size="25" />
												<?php endif; ?>
													<div class="desc">
														<?php if (!$validate["empty"] && !$validate["password"]): ?><small>ERROR: Su contraseña debe tener al menos 6 caracteres (letras y números).<br /></small>
														<?php elseif (!$validate["empty"] && !$validate["passwordMatch"]): ?><small>ERROR: Las dos contraseñas que ingresaste deben coincidir.<br /></small>
														<?php else: ?><small>Introduzca su contraseña deseada dos veces. <br /> Debe tener al menos 6 caracteres (letras y números) de longitud.</small><?php endif; ?>
													</div>
												</div>
											</div>
											<?php
											$levelTable = $miembropress->model->getLevelTable();
											$hashLevel = $miembropress->registerLevel->level_hash;
											$result = $wpdb->get_var("SELECT `gdpr_active` FROM `$levelTable` WHERE `level_hash` = '$hashLevel'");
											?>
											<?php if ($result){ ?>
												<div class="form-group row">
													<label class="col-md-4 control-label"></label>
													<?php
													$valoresGDPR = $wpdb->get_results("SELECT `gdpr_label`, `gdpr_url`, `gdpr_text`, `gdpr_color`, `gdpr_size` FROM `$levelTable` WHERE `level_hash` = '$hashLevel'", ARRAY_A);
													foreach ($valoresGDPR as $key => $valores) {
														$gdpr_label = $valores['gdpr_label'];
														$gdpr_url = $valores['gdpr_url'];
														$gdpr_text = $valores['gdpr_text'];
														$gdpr_color = $valores['gdpr_color'];
														$gdpr_size = $valores['gdpr_size'];
													}

													?>
													<div class="col-md-8 has-success">
														<div class="custom-control custom-checkbox">
															<input type="checkbox" class="custom-control-input" id="checkedGdpr" required /><label class="custom-control-label" for="checkedGdpr"><?php echo $gdpr_label; ?>
															<a href="<?php echo $gdpr_url; ?>" target="_blank" style="color:<?php echo $gdpr_color; ?>!important; font-size:<?php echo $gdpr_size; ?>!important; text-decoration: underline!important;"><?php echo $gdpr_text; ?></a></label>
														</div>
													</div>
												</div>
												<?php
											}
											?>
										
										<?php if (is_admin()): ?>
										
											<td style="vertical-align:middle;"><label for="miembropress_email"><b>Nivel de Membresia:</b></label>
											<td style="vertical-align:middle;">
												<select name="miembropress_level">
													<?php foreach ($miembropress->model->getLevels() as $level): ?>
													<option value="<?php echo intval($level->ID); ?>"><?php echo htmlentities($level->level_name); ?></option>
													<?php endforeach; ?>
												</select>
											
										
										<?php endif; ?>

										<div class="row">
											<div class="col-md-4 col-xs-4 col-sm-4 "></div>
											<div class="col-md-8 col-xs-8 col-sm-8 ">
												<input type="submit" class="btn btn-info ctrl-btn button-primary button-activate" value="   Registrarse   ">
											</div>
										</div>
								<?php
								if (!is_admin() && !isset($_REQUEST["existing"]) && count($_POST) == 0): ?>
									<script type="text/javascript">
										//document.getElementById("miembropress_registration").style.display="none";
									</script>
								<?php endif; ?>
								<script type="text/javascript">
									<?php if (is_admin()): ?>document.getElementById("miembropress_password2").style.display="none";<?php endif; ?>

									function miembropress_suggest() {
										var username = document.getElementById("miembropress_username");
										var firstname = document.getElementById("miembropress_firstname");
										var lastname = document.getElementById("miembropress_lastname");

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
								</script>
							</div>
				<?php endif; ?>
			</form>
		</div>
		<?php if (!is_admin()) { 
			if ($globalFooter = $miembropress->model->levelSetting(-1, "footer")) { 
				eval( ' ?> '.stripslashes($globalFooter).' <?php ' ); 
			} 
			
			if (isset($miembropress->registerLevel->ID) && ($footer = $miembropress->model->levelSetting($miembropress->registerLevel->ID, "footer"))) { 
				eval( ' ?> '.stripslashes($footer).' <?php ' ); 
			} 
		} ?>
		<?php
	}

	private function menu_members_list($message=null, $messageLevels=null) {
		global $miembropress;
		$current_user = wp_get_current_user();
		$action = (isset($_REQUEST["miembropress_action"])) ? $_REQUEST["miembropress_action"] : "members";
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
		$levels = $miembropress->model->getLevels();
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
		$users = $miembropress->model->getMembers($userQuery); ?>
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
				<input type="search" value="<?php echo htmlentities($search); ?>" name="s" placeholder="Search Members" ondblclick="miembropress_multisearch(this);" />
				<?php endif; ?>
				<input type="submit" class="button-primary menus_buttons button-activate " value="Search">
				<a title="Add New Member" href="#TB_inline?height=450&amp;width=500&amp;inlineId=miembropress_newmember" class="thickbox button-primary menus_buttons button-activate ">Add New Member</a>
			</p>
			<p>
				<b>Browse Level:</b>
				<a <?php if (-1 == $currentLevel): ?>style="font-weight:bold;"<?php endif; ?>href="<?php echo $this->tabLink("members") . "&l=-1"; ?><?php if (isset($_GET["o"])): ?>&o=<?php echo htmlentities($_GET["o"]); ?><?php endif; ?>">All Members (<?php echo $miembropress->model->getMemberCount(); ?>)</a> &nbsp;&nbsp;
				<?php foreach ($levels as $level): ?>
				<a <?php if (is_numeric($currentLevel) && $level->ID == $currentLevel): ?>style="font-weight:bold;"<?php endif; ?> href="<?php echo $this->tabLink("members") . "&l=" . intval($level->ID); ?><?php if (isset($_GET["o"])): ?>&o=<?php echo htmlentities($_GET["o"]); ?><?php endif; ?>"><?php echo htmlentities($level->level_name); ?> (<?php echo intval($level->active); ?>)</a> &nbsp;&nbsp;
				<?php endforeach; ?>
			</p>
			<?php if (is_numeric($currentLevel)): ?>
			<p>Check the users you want to change and make your selection:<br />
				<nobr>
				<select name="miembropress_levels" id="miembropress_levels">
					<option value="-">Levels...</option>
					<?php foreach ($levels as $level): ?>
					<option value="<?php echo intval($level->ID); ?>"><?php echo htmlentities($level->level_name); ?></option>
					<?php endforeach; ?>
				</select>
				<input name="miembropress_action_add" type="submit" class="button-primary menus_buttons button-activate " value="Add to Level" />
				<input name="miembropress_action_move" type="submit" class="button-primary menus_buttons button-activate " value="Move to Level" />
				<input name="miembropress_action_remove" type="submit" class="button-primary menus_buttons button-activate " value="Remove from Level" />
				<input name="miembropress_action_cancel" type="submit" class="button-primary menus_buttons button-activate " value="Cancel from Level" />
				<input name="miembropress_action_uncancel" type="submit" class="button-primary menus_buttons button-activate " value="Uncancel from Level" />
				<input name="miembropress_action_delete" type="submit" class="button-primary menus_buttons button-activate " value="Delete Members" onclick="return miembropress_confirm();" />
				</nobr>
			</p>
			<?php endif; ?>
			<?php $this->menu_members_table($currentLevel, $users, $levels, $search); ?>
		</form>
		<div align="center" id="miembropress_newmember" style="display:none;">
			<div id="miembropress_popup">
				<?php $this->register(true); ?>
			</div>
		</div>
		<?php $this->javascript(); ?>
		<style type="text/css">
			/*
			#miembropress_popup { height:500px; }
			#TB_window { height:500px; }
			#TB_ajaxContent { height:500px !important; }
			*/
			/* #TB_title { display:none; }*/

			#miembropress_popup { margin-top:20px; }
			#miembropress_popup h3 { font-size:24px; margin:10px 0 20px 0; padding-bottom:5px; border-bottom:1px solid #dbdbdb; }
			#miembropress_popup table tbody tr td { vertical-align:top; padding:2px; }
			#miembropress_popup .desc { font-size:11px; font-style:italic; padding-bottom:5px; }
		</style>
		<?php
	}

	private function menu_members_table($currentLevel, &$users, &$levels, $search, $page=1) {
		global $miembropress;
		$nextCron = wp_next_scheduled('miembropress_process');
		$thisPage = (isset($_REQUEST["page"]) ? $_REQUEST["page"] : "");
		?>
		<!-- table of users -->
		<table class="widefat" style="width:900px;">
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
				$fullname = $firstname . " " . $lastname;
				$userLevels = $miembropress->model->getLevelInfo($user->ID);
				if (!$userLevels || !is_array($userLevels)) { $userLevels = array(); } ?>
				<tr>
					<th scope="row" class="check-column">
					<input type="checkbox" name="miembropress_users[<?php echo intval($user->ID); ?>]" id="miembropress_users[<?php echo intval($user->ID); ?>]" value="<?php echo intval($user->ID); ?>">
					</th>
					<td><label style="vertical-align:top;" for="miembropress_users[<?php echo intval($user->ID); ?>]"><?php echo htmlentities($fullname); ?></label></td>
					<td><strong><a href="user-edit.php?user_id=<?php echo intval($user->ID); ?>&amp;wp_http_referer=miembropress"><?php echo htmlentities($user->user_login); ?></a></strong></td>
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

	private function menu_members_message($message, $level) {
		global $miembropress;
		$text = array( 'add' => 'Selected members added to %level%.', 'move' => 'Selected members moved to %level%.', 'cancel' => 'Selected members canceled from %level%.', 'uncancel' => 'Selected members uncanceled from %level%.', 'remove' => 'Selected members added to %level%.', 'delete' => 'Selected members deleted.', );
		if (!$message) { return; }
		if (!isset($text[$message])) { return; }
		$levelName = $miembropress->model->getLevelName($level);
		if (!$levelName) { return; }
		$output = $text[$message];
		$output = str_replace('%level%', $levelName, $output);
		echo '<div class="updated fade">'.$output.'</div>';
	}

	private function menu_members_temp() {
		global $miembropress; ?>
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
					<?php foreach ($miembropress->model->getTemps() as $temp): ?>
					<?php $level = $miembropress->model->getLevel($temp->level_id); 
					$link = $miembropress->model->signupURL($level->level_hash) . "&complete=".$temp->txn_id; ?>
					<tr>
						<th scope="row" class="check-column"><input type="checkbox" name="miembropress_temps[<?php echo intval($temp->ID); ?>]" /></td>
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
			<p><input name="miembropress_temps_complete" type="submit" class="button-primary menus_buttons button-activate " value="Complete Selected Registrations" />
			<input name="miembropress_temps_delete" type="submit" class="button-primary menus_buttons button-activate " value="Delete Selected Registrations" onclick="return confirm('Are You SURE You Want to Delete the Selected Incomplete Registrations? Click OK to delete, Cancel to stop.');" /></p>
		</form>
		<?php
	}

	private function menu_members_export() { 
		global $miembropress; 
		$levels = $miembropress->model->getLevels(); ?>
		<h3>Export Members</h3>
		<p>Dump a list of some (or all) of your members into a CSV (comma separated) file that you can import into your autoresponder or customer management system.</p>
		<form method="POST">
			<input type="hidden" name="miembropress_action" value="download">
			<ul>
				<?php foreach ($levels as $level): ?>
				<li><label><input type="checkbox" checked="checked" name="miembropress_level[]" value="<?php echo intval($level->ID); ?>" /> <?php echo htmlentities($level->level_name); ?></label></li>
				<?php endforeach; ?>
			</ul>
			<ul>
				<li><label><input type="radio" name="miembropress_status" value="active" checked="checked" /> Active Members Only</label></li>
				<li><label><input type="radio" name="miembropress_status" value="canceled" /> Canceled Members Only</label></li>
				<li><label><input type="radio" name="miembropress_status" value="all" /> Active &amp; Canceled Members</label></li>
			</ul>
			<p>When you click the &quot;Export Members&quot; button, a file will download which you can save to your desktop.</p>
			<input type="submit" class="button-primary menus_buttons button-activate " value="Export Members" />
		</form>
		<?php
	}

	public function menu_popup() {
		global $miembropress;
		if (isset($_POST["miembropress_settings_header"])) {
			if (isset($_POST["miembropress_settings_header"])) {
				$miembropress->model->setting("header", stripslashes($_POST["miembropress_settings_header"]));
			}
			if (isset($_POST["miembropress_settings_footer"])) {
				$miembropress->model->setting("footer", stripslashes($_POST["miembropress_settings_footer"]));
			}
		}
		$header = $miembropress->model->setting("header");
		$footer = $miembropress->model->setting("footer");
		?>
		<div class="wrap" style="clear:both; width:1000px;">
			<?php $this->menu_header("Popup"); ?>
			<h3 class="espacio-steps">To configure the Maximizer you must pay attention to the following instructions.</h3>
			<h3><span class="fondo-steps">Step 1:</span> Create Content.</h3>
			<h3 class="espacio-steps">Here you have two options to create the content.
			<br />
			Choose one of the two OPTIONS below:</h3>
			<h3>- OPTION A&#41; Add content of the maximizer popup by text:</h3>
			<form id="form" action="<?php echo base_url_inc . 'popup.php'; ?>" method="get" target="_blank">
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

				<h3>- OPTION B&#41; Add content of the maximizer popup by HTML code:</h3>
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
				&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<button class="button-primary menus_buttons button-activate ">Generate Popup</button>
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
					<textarea name="miembropress_settings_header" class="code" cols="90" rows="7" style="font-size:11px;"><?php echo htmlentities($header); ?></textarea></p>
					<textarea name="miembropress_settings_footer" class="code" cols="90" rows="7" style="font-size:11px;"><?php echo htmlentities($footer); ?></textarea></style>
				</blockquote>
				<p><input type="submit" class="button-primary menus_buttons button-activate " value="Save All Changes" /></p>
			</form>
		</div>
      <?php
	}

	public function menu_settings() {
		global $miembropress;
		if (isset($_POST)) {
			if (isset($_POST["miembropress_settings_notify"])) {
				$miembropress->model->setting("notify", 1);
			} else {
				$miembropress->model->setting("notify", 0);
			}
			if (isset($_POST["miembropress_settings_profile"])) {
				$miembropress->model->setting("profile", 1);
			} else {
				$miembropress->model->setting("profile", 0);
			}
			if (isset($_POST["miembropress_settings_front_page"]) && is_numeric($_POST["miembropress_settings_front_page"])) {
				if ($_POST["miembropress_settings_front_page"] == 0) {
					update_option("show_on_front", "posts");
					update_option("page_on_front", 0);
				} else {
					update_option("show_on_front", "page");
					update_option("page_on_front", $_POST["miembropress_settings_front_page"]);
				}
			}
			if (isset($_POST["miembropress_settings_order"])) {
				$miembropress->model->setting("order", stripslashes($_POST["miembropress_settings_order"]));
			}
			if (isset($_POST["miembropress_settings_header"])) {
				$miembropress->model->setting("header", stripslashes($_POST["miembropress_settings_header"]));
			}
			if (isset($_POST["miembropress_settings_footer"])) {
				$miembropress->model->setting("footer", stripslashes($_POST["miembropress_settings_footer"]));
			}
			if (isset($_POST["miembropress_settings_support"])) {
				$miembropress->model->setting("support", stripslashes($_POST["miembropress_settings_support"]));
			}
			if (isset($_POST["miembropress_settings_affiliate"])) {
				if (isset($_POST["miembropress_settings_attribution"])) {
					$miembropress->model->setting("attribution", 1);
				} else {
					$miembropress->model->setting("attribution", 0);
				}
				if (isset($_POST["miembropress_settings_emailattribution"])) {
					$miembropress->model->setting("emailattribution", 1);
				} else {
					$miembropress->model->setting("emailattribution", 0);
				}
				$affiliate = stripslashes($_POST["miembropress_settings_affiliate"]);
				$miembropress->model->setting("affiliate", $affiliate);
			}
			if (isset($_POST["miembropress_settings_nonmember_page"])) {
				$miembropress->model->setting("nonmember_page", @intval($_POST["miembropress_settings_nonmember_page"]));
			}
			if (isset($_POST["miembropress_settings_nonmember_url"])) {
				$miembropress->model->setting("nonmember_url", stripslashes($_POST["miembropress_settings_nonmember_url"]));
			}
			if (isset($_POST["miembropress_settings_logout_page"])) {
				$miembropress->model->setting("logout_page", @intval($_POST["miembropress_settings_logout_page"]));
			}
			if (isset($_POST["miembropress_settings_logout_url"])) {
				$miembropress->model->setting("logout_url", stripslashes($_POST["miembropress_settings_logout_url"]));
			}
		}

		$notify = $miembropress->model->setting("notify")==1;
		$profiles = $miembropress->model->setting("profile")==1;
		$order = $miembropress->model->setting("order");
		$header = $miembropress->model->setting("header");
		$footer = $miembropress->model->setting("footer");
		$attribution = $miembropress->model->setting("attribution")==1;
		$emailattribution = $miembropress->model->setting("emailattribution")==1;
		$affiliate = $miembropress->model->setting("affiliate");
		$support = $miembropress->model->setting("support");
		$nonmember_page = @intval($miembropress->model->setting("nonmember_page"));
		$nonmember_url = $miembropress->model->setting("nonmember_url");
		if (!$nonmember_url) { $nonmember_url = ((is_ssl()) ? "https://" : "http://"); }
		$logout_page = @intval($miembropress->model->setting("logout_page"));
		$logout_url = $miembropress->model->setting("logout_url");
		if (!$logout_url) { $logout_url = ((is_ssl()) ? "https://" : "http://"); }
		$pages = get_pages();
		$front_page = 0;
		if (get_option("show_on_front") == "page") { 
			$front_page = @intval(get_option("page_on_front")); 
		} 
		?>
		<div class="wrap" style="clear:both; width:1000px;">
			<?php $this->menu_header("Settings"); ?>
			<form method="POST">
				<?php if (count($_POST) > 0): ?>
					<div class="updated">Settings saved.</div>
				<?php endif; ?>
				<h3>Customizations</h3>
				<p><label>
					<input type="checkbox" name="miembropress_settings_notify" <?php checked($notify); ?> /> Email the site administrator <code><?php echo htmlentities(get_option("admin_email")); ?></code> about every transaction (including refunds) sent to this membership site?
				</label></p>
				<p><label>
					<input type="checkbox" name="miembropress_settings_profile" <?php checked($profiles); ?> /> Allow users to view and edit <a target="_blank" href="<?php echo get_edit_user_link(); ?>">their own profile information</a>?
				</label></p>
				<h3>Front Page</h3>
				<blockquote>
					<p>If you're using WordPress to host your sales letter separately (i.e. a separate WordPress installation at <code>example.com</code> and this membership site at <code>example.com/members</code>), we recommend you leave these settings alone.</p>
					<p>On the other hand, if you're using this WordPress site to host BOTH your membership site and sales letter, set the &quot;front page&quot; below to be the front page for logged-in members, and the &quot;non-member&quot; page to be your sales letter, where members can click a button to buy access into your membership site.</p>
					<p><label>Front Page:</label>
						<select name="miembropress_settings_front_page">
							<option value="0" <?php selected($front_page == 0); ?>>(No Default Page)</option>
							<?php foreach ($pages as $page): ?>
							<?php
							if ($page->post_name == "wishlist-member") { continue; }
							if ($page->post_name == "miembropress") { continue; }
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
						<select name="miembropress_settings_nonmember_page" onchange="jQuery('#miembropress_settings_nonmember_url').attr('disabled', this.value!=0);">
							<option value="" <?php selected($nonmember_page == 0); ?>>Enter an external URL below...</option>
							<option value="-1" <?php selected($nonmember_page == "-1"); ?>>[WordPress Login Page]</option>
							<?php foreach ($pages as $page): ?>
								<?php 
								if ($page->post_name == "wishlist-member") { continue; } 
								if ($page->post_name == "miembropress") { continue; } 
								if ($page->post_name == "copyright") { continue; } 
								if ($page->post_name == "disclaimer") { continue; } 
								if ($page->post_name == "earnings") { continue; } 
								if ($page->post_name == "privacy") { continue; } 
								if ($page->post_name == "terms-conditions") { continue; } 
								if ($miembropress->model->isProtected($page->ID)) { continue; } 
								?>
							<option <?php selected($nonmember_page == $page->ID); ?> value="<?php echo intval($page->ID); ?>"><?php echo htmlentities($page->post_title); ?></option>
							<?php endforeach; ?>
						</select>
						<small>(external URL or <a href="<?php echo $this->tabLink("content&miembropress_action=pages&l=-1"); ?>">UNPROTECTED page</a> on your site)</small><br/>
						<input type="text" id="miembropress_settings_nonmember_url" name="miembropress_settings_nonmember_url" size="65" <?php disabled($nonmember_page != 0); ?> value="<?php echo htmlentities($nonmember_url); ?>" <?php disabled($nonmember_page != 0); ?> />
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
						<select name="miembropress_settings_logout_page" onchange="jQuery('#miembropress_settings_logout_url').attr('disabled', this.value!=0);">
							<option value="" <?php selected($logout_page == 0); ?>>Enter an external URL below...</option>
							<option value="-1" <?php selected($logout_page == "-1"); ?>>[WordPress Login Page]</option>
							<?php foreach ($pages as $page): ?>
							<?php
							if ($page->post_name == "wishlist-member") { continue; }
							if ($page->post_name == "miembropress") { continue; }
							if ($page->post_name == "copyright") { continue; }
							if ($page->post_name == "disclaimer") { continue; }
							if ($page->post_name == "earnings") { continue; }
							if ($page->post_name == "privacy") { continue; }
							if ($page->post_name == "terms-conditions") { continue; }
							if ($miembropress->model->isProtected($page->ID)) { continue; }
							?>
							<option <?php selected($logout_page == $page->ID); ?> value="<?php echo intval($page->ID); ?>"><?php echo htmlentities($page->post_title); ?></option>
							<?php endforeach; ?>
						</select>
						<small>(external URL or <a href="<?php echo $this->tabLink("content&miembropress_action=pages&l=-1"); ?>">UNPROTECTED page</a> on your site)</small><br/>
						<input type="text" id="miembropress_settings_logout_url" name="miembropress_settings_logout_url" size="65" <?php disabled($logout_page != 0); ?> value="<?php echo htmlentities($logout_url); ?>" <?php disabled($logout_page != 0); ?> />
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
				<p><input type="submit" class="button-primary menus_buttons button-activate " value="Save All Changes" /></p>
				<h3>Post Sort Order</h3>
				<blockquote>
					<p>If you want to change the order that posts are shown (such as oldest to newest or newest to oldest), change that here.</p>
					<label><input type="radio" name="miembropress_settings_order" value="descending" <?php checked($order == "descending"); ?> /> Newest On Top, Oldest on Bottom <em>(descending)</em></label><br />
					<label><input type="radio" name="miembropress_settings_order" value="ascending" <?php checked($order == "ascending"); ?> /> Oldest On Top, Newest on Bottom <em>(ascending)</em></label>
				</blockquote>
				<h3>Offsite Links</h3>
				<p>MiembroPress can display these links at the bottom of your membership site, no matter what theme you are currently using.</p>
				<blockquote>
					<p><b>Support Desk:</b> We recommend you setup ONE support ticket system using <a href="https://ticketsystem.pro" target="_blank">Ticket System Plugin</a> so you only need to check one location for customer support issues such as lost passwords, pre-sales questions, or refund requests.</p>
					<p><label>Support Desk URL: 
						<input type="text" name="miembropress_settings_support" value="<?php echo htmlentities($support); ?>" class="code" size="35" />
						</label>
						<small>&#40;must include &quot;http://&quot; in web address)</small>
					</p>
					<p><b>Affiliate Link:</b> If you would like to promote the MiembroPress plugin to your members and earn a commission, please <a target="_blank" href="https://miembropress.com/afiliados/">register for our affiliate program</a>.</p>
					<p> Affiliate URL (optional): <label><input type="text" name="miembropress_settings_affiliate" size="35" value="<?php echo htmlentities($affiliate); ?>" /></label><br /></p>
					<label><input type="checkbox" name="miembropress_settings_attribution" <?php checked($attribution); ?> /> Show Link in Site Footer</label> <label><input type="checkbox" name="miembropress_settings_emailattribution" <?php checked($emailattribution); ?> /> Send Link in Email Notifications</label>
				</blockquote>
				<p><input type="submit" class="button-primary menus_buttons button-activate " value="Save All Changes" /></p>
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

	public function menu_dashboard() {
		global $miembropress; ?>
		<div class="wrap" style="clear:both;">
			<?php $this->menu_header("Dashboard"); ?>
			
			<?php if(strlen($this->activation->call) > 256): ?>
				<xmp><?php echo $this->activation->call; ?></xmp>
			<?php endif; ?>

			<?php if ($this->activation->call == 0 || strlen($this->activation->call) > 256): ?>
				</div>
				<?php return; ?>
			<?php endif; ?>

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
						<iframe allowtransparency="true" style="visibility: hidden;" src="http://www.miembropress.com/ads/?email=<?php echo urlencode($miembropress->admin->activation->email); ?>&members=<?php echo intval($miembropress->model->getMemberCount()); ?>&version=<?php echo $miembropress->admin->activation->version; ?>" width="480" height="360" frameborder="1" border="1"></iframe>
					</td>
				</tr>
			</table>
		</div>
    	<?php
	}

	public function ttlMenu($name=""){
		if ($name != "") {
			$suffix = "-".$name;
		} else { $suffix = ""; }
		return plugin_basename('miembro-press/miembro-press.php'.$suffix);
	}

	public function links_unregistered($links, $file) {
		if ($file == plugin_basename('miembro-press/miembro-press.php')) {
			array_unshift($links, '<a href="admin.php?page='.$file.'">Registrar</a>');
		}
		return $links;
	}

	public function admin_bar() {
		global $miembropress;
		$nonmember_page = @intval($miembropress->model->setting("nonmember_page"));
		$nonmember_url = $miembropress->model->setting("nonmember_url");
		if ($nonmember_page == 0) {
			$nonmember_link = $nonmember_url;
		} elseif ($nonmember_page) {
			$nonmember_link = get_permalink($nonmember_page);
		} else { $nonmember_link = null; }
		$logout_page = @intval($miembropress->model->setting("logout_page"));
		$logout_url = $miembropress->model->setting("logout_url");
		if ($logout_page == 0) {
			$logout_link = $logout_url;
		} elseif ($logout_page) {
			$logout_link = get_permalink($logout_page);
		} else {
			$logout_link = null;
		}
		$this->add_root_menu("MiembroPress", "miembropress", $this->tabLink());
		$this->add_sub_menu("Dashboard", $this->tabLink(), "miembropress", "miembropress_dashboard" );
		$this->add_sub_menu("Settings", $this->tabLink("settings"), "miembropress", "miembropress_settings" );
		if ($nonmember_link) {
			$this->add_sub_menu("&nbsp;&nbsp; View Non-Member Page", $nonmember_link, "miembropress", "miembropress_nonmember_link" );
		}
		if ($logout_link) {
			$this->add_sub_menu("&nbsp;&nbsp; View Log-Out Page", $logout_link, "miembropress", "miembropress_logout_link" );
		}
		$this->add_sub_menu("Members", $this->tabLink("members"), "miembropress", "miembropress_members" );
		$this->add_sub_menu("Levels", $this->tabLink("levels"), "miembropress", "miembropress_levels" );
		$this->add_sub_menu("Content", $this->tabLink("content"), "miembropress", "miembropress_content" );
		$this->add_sub_menu("Payments", $this->tabLink("payments"), "miembropress", "miembropress_payments" );
		$this->add_sub_menu("Autoresponder", $this->tabLink("autoresponder"), "miembropress", "miembropress_autoresponder");
		$this->add_sub_menu("Social", $this->tabLink("social"), "miembropress", "miembropress_social");
		$this->add_sub_menu("Maximizer", $this->tabLink("popup"), "miembropress", "miembropress_popup");
		$this->add_sub_menu("Custom Login", $this->customizerLink(), "miembropress", "miembropress_customizer");
	}
	
	function add_root_menu($name, $id, $href = FALSE) {
		global $wp_admin_bar;
		if ( !is_super_admin() || !is_admin_bar_showing() ) return;
		$wp_admin_bar->add_menu( array( `ID` => $id, 'meta' => array(), 'title' => $name, 'href' => $href ) );
	}

	function add_sub_menu($name, $link, $root_menu, $id, $meta = FALSE) {
		global $wp_admin_bar;
		if ( ! is_super_admin() || ! is_admin_bar_showing() ) return;
		$wp_admin_bar->add_menu( array( 'parent' => $root_menu, `ID` => $id, 'title' => $name, 'href' => $link, 'meta' => $meta ) );
	}

	public function tabLink($name="") {
		if ($name != "") {
			$suffix = "-".$name;
		} else { $suffix = ""; }
		return admin_url('admin.php?page='.plugin_basename('miembro-press/miembro-press.php').$suffix);
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

	public function admin_init() {
		if (!isset($_REQUEST["page"])) { return; }
		if (strpos($_REQUEST["page"], plugin_basename('miembro-press/miembro-press.php')) === false) { return; }
		wp_enqueue_script('jquery');
		$this->thickbox();
	}

	private function thickbox() {
		wp_enqueue_script('thickbox',null,array('jquery'));
		wp_enqueue_style('thickbox.css', '/'.constant("WPINC").'/js/thickbox/thickbox.css', null, '1.0');
	}

	public function profile_update($userID) {
		global $miembropress;
		MiembroPress::clearCache();
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

		if (isset($_POST["miembropress_transaction"])) {
			$transactions = $_POST["miembropress_transaction"];
		}
		if (isset($_POST["miembropress_transaction"])) {
			$transactions_original = $_POST["miembropress_transaction_original"];
		}
		if (isset($_POST["miembropress_date"])) {
			$date = $_POST["miembropress_date"];
		}
		if (isset($_POST["miembropress_date_original"])) {
			$date_original = $_POST["miembropress_date_original"];
		}
		if (isset($_POST["miembropress_level"])) {
			$allLevels = $_POST["miembropress_level"];
		}
		if (isset($_POST["miembropress_subscribed"])) {
			$subscribed = $_POST["miembropress_subscribed"];
		}
		if (isset($_POST["miembropress_levels"])) {
			$levels = $_POST["miembropress_levels"];
		}

		foreach ($transactions as $level => $transaction) {
			if ($transactions[$level] == $transactions_original[$level]) { continue; }
			$miembropress->model->updateTransaction($user, $level, $transaction);
		}

		foreach (array_keys($allLevels) as $level) {
			if (isset($subscribed[$level])) {
				$miembropress->model->setSubscribed($user, $level);
			} else {
				$miembropress->model->setSubscribed($user, $level, false);
			}
		}

		foreach ($date as $level => $theDate) {
			if ($date[$level] == $date_original[$level]) { continue; }
			$miembropress->model->updateLevelDate($user, $level, $theDate);
			if (strtotime($date[$level]) < strtotime($date_original[$level])) {
				$start = strtotime($date[$level]);
				$end = time();
				if ($start <= 1 || $end <= 1) { break; }
				$offset = 0;
				while ($start <= $end) {
					$miembropress->model->processUpgrade($start, $userID);
					$start = $start + 86400;
				}
			}
		}
		
		if (isset($_POST["miembropress_action_move"])) {
			$miembropress->model->move($user, $levels);
		} elseif (isset($_POST["miembropress_action_add"])) {
			$miembropress->model->add($user, $levels);
		} elseif (isset($_POST["miembropress_action_remove"])) {
			$miembropress->model->remove($user, $levels);
		} elseif (isset($_POST["miembropress_action_cancel"])) {
			$miembropress->model->cancel($user, $levels);
		} elseif (isset($_POST["miembropress_action_uncancel"])) {
			$miembropress->model->uncancel($user, $levels);
		} elseif (isset($_POST["miembropress_action_delete"])) {
			$miembropress->model->deleteUser($user); $this->maybeRedirect();
		}
		if (isset($_POST["miembropress_action_password"])) {
			$this->retrieve_password($user);
		}
		if (isset($_POST["miembropress_action_impersonate"]) && current_user_can("administrator")) {
			$userdata = get_user_by("id", $user);
			wp_set_current_user($user, $userdata->user_login);
			wp_set_auth_cookie($user, true);
			do_action('wp_login', $userdata->user_login, $userdata);
			wp_redirect(admin_url());
			die();
		}
	}

	public function maybeRedirect() {
		if (isset($_REQUEST["wp_http_referer"]) && strpos($_REQUEST['wp_http_referer'], "miembropress") !== FALSE) {
			wp_redirect($this->tabLink("members"));
			die();
		}

		if (isset($_REQUEST["miembropress_action_delete"])) {
			wp_redirect($this->tabLink("members"));
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

	public function profile($user) {
		global $miembropress;
		if (!current_user_can('administrator')) { return; }
		if (isset($_GET["miembropress_social_disconnect"])) {
			if ($_GET["miembropress_social_disconnect"] == "facebook") {
				$miembropress->model->userSetting($user->ID, "social_facebook", null);
			}
			if ($_GET["miembropress_social_disconnect"] == "google") {
				$miembropress->model->userSetting($user->ID, "social_google", null);
			}
		}
		$profile_url = admin_url("user-edit.php?user_id=".$user->ID);
		$levels = $miembropress->model->getLevelInfo($user->ID);
		$allLevels = $miembropress->model->getLevels();
		$loginFirst = intval($miembropress->model->userSetting($user->ID, "loginFirst"));
		$logins = $miembropress->model->userSetting($user->ID, "logins");
		if (!is_array($logins)) {
			$logins = array();
		}
		arsort($logins);
		$sequentialLink = add_query_arg(array('page'=>$this->ttlMenu("levels"), 'miembropress_action'=>'upgrade'), admin_url('admin.php'));
		$social_facebook = $miembropress->model->userSetting($user->ID, "social_facebook");
		$social_google = $miembropress->model->userSetting($user->ID, "social_google"); ?>
		<div id="MiembroPressUserProfile">
			<h3>MiembroPress</h3>

			<!-- Social integration -->
			<?php if ($social_facebook || $social_google): ?>
			<p>
			<b>Social integration:</b>
			<?php if ($social_facebook): ?><br /><a target="_blank" href="https://www.facebook.com/<?php echo htmlentities($social_facebook); ?>">Facebook</a> <a href="<?php echo add_query_arg(array("miembropress_social_disconnect" => "facebook"), $profile_url); ?>">(disconnect)</a>
			<?php endif; ?>
			<?php if ($social_google): ?><br /><a target="_blank" href="https://plus.google.com">Google</a></b> <a href="<?php echo add_query_arg(array("miembropress_social_disconnect" => "google"), $profile_url); ?>">(disconnect)</a>
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
						$levelAdd = $miembropress->model->levelSetting($level->level_id, "add");
						$levelMove = $miembropress->model->levelSetting($level->level_id, "move");
						$levelDelay = @intval($miembropress->model->levelSetting($level->level_id, "delay"));
						$levelDateDelay = @intval($miembropress->model->levelSetting($level->level_id, "dateDelay"));
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
						$daysOnLevel = $miembropress->model->timestampToDays($level->level_date);
						$upgradeDaysLeft = 0;
						$upgradeETA = null;
						if ($levelDateDelay) {
							$upgradeDaysLeft = max(0, $miembropress->model->timestampToDays($levelDateDelay) * -1);
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
								<input type="hidden" name="miembropress_level[<?php echo intval($level->level_id); ?>]" value="1" />
								<a style="text-decoration:<?php echo $textDecoration; ?>;" href="<?php echo $this->tabLink("members"); ?>&l=<?php echo intval($level->level_id); ?>"><strong><?php echo htmlentities($level->level_name); ?></strong></a>
							</td>
							<td style="text-align:left;">
								<input type="text" name="miembropress_transaction[<?php echo intval($level->level_id); ?>]" size="20" value="<?php echo htmlentities($level->level_txn); ?>" />
								<input type="hidden" name="miembropress_transaction_original[<?php echo intval($level->level_id); ?>]" value="<?php echo htmlentities($level->level_txn); ?>" />
							</td>
							<td style="text-align:left;"><label><input type="checkbox" name="miembropress_subscribed[<?php echo intval($level->level_id); ?>]" <?php checked($level->level_subscribed == 1); ?> class="miembropress_subscribed" /> <span class="miembropress_subscribed_label"><?php echo ($level->level_subscribed == 1) ? chr(89) . "es" : "No"; ?></span></label></td>
								<td style="text-align:left;">
								<nobr><input type="text" name="miembropress_date[<?php echo intval($level->level_id); ?>]" value="<?php echo htmlentities($level->level_date); ?>" size="20" /></nobr>
								<input type="hidden" name="miembropress_date_original[<?php echo intval($level->level_id); ?>]" value="<?php echo htmlentities($level->level_date); ?>" />
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
								<input name="miembropress_action_password" type="submit" class="button-primary menus_buttons button-activate " value="Send Reset Password Link to Member" />
								<input type="submit" name="submit" id="submit" class="button button-primary menus_buttons button-activate " value="Update User">

								<br /><br />

								<select name="miembropress_levels" id="miembropress_levels">
									<option value="-">Levels...</option>
									<?php foreach ($allLevels as $level): ?>
									<option value="<?php echo intval($level->ID); ?>"><?php echo htmlentities($level->level_name); ?></option>
									<?php endforeach; ?>
								</select>

								<input name="miembropress_action_add" type="submit" class="button-primary menus_buttons button-activate " value="Add to Level" />
								<input name="miembropress_action_move" type="submit" class="button-primary menus_buttons button-activate " value="Move to Level" />
								<input name="miembropress_action_remove" type="submit" class="button-primary menus_buttons button-activate " value="Remove from Level" />
								<input name="miembropress_action_cancel" type="submit" class="button-primary menus_buttons button-activate " value="Cancel from Level" />
								<input name="miembropress_action_uncancel" type="submit" class="button-primary menus_buttons button-activate " value="Uncancel from Level" />
								<input name="miembropress_action_delete" type="submit" class="button-primary menus_buttons button-activate " style="background-color:red !important;" value="Delete Member" onclick="return confirm('Are you SURE you want to delete this member? This action cannot be undone. Press OK to continue, Cancel to stop.');" /><br /><br />

								<?php
									if ($user->first_name) {
										$loginAs = $user->first_name . "" . $user->last_name;
									} else {
										$loginAs = $user->user_login;
									}
								?>
								<input name="miembropress_action_impersonate" type="submit" class="button-primary menus_buttons button-activate " value="Login As <?php echo htmlentities($loginAs); ?>" onmouseover="this.value='Login As <?php echo htmlentities($loginAs); ?> (will log you out of this account)'" onmouseout="this.value='Login As <?php echo htmlentities($loginAs); ?>'" />
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
		</div> <!-- id MiembroPressUserProfile -->

        <script type="text/javascript">
			// Move MiembroPress preferences to the top of the profile page
			jQuery("#MiembroPressUserProfile").insertBefore("h2:first");
			jQuery(function() {
			// Have text next to Subscribed checkbox show yes or no
				jQuery(".miembropress_subscribed").click(function() {
					var val = (jQuery(this).attr("checked")) ? "Yes" : "No";
					jQuery(this).next(".miembropress_subscribed_label").html(val);
				});
			});
        </script>
        <?php
	}

	public function columns($columns) {
		$columns['miembropress'] = 'Access';
		return $columns;
	}

	public function column($column, $post_id) {
		global $miembropress;
		if (!$miembropress->model->isProtected($post_id)) {
			echo "<i>Everyone</i>";
			return;
		}

		if ($levels = $miembropress->model->getLevelsFromPost($post_id)) {
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

	public function dashboard_setup() {
		if (!current_user_can('administrator')) { return; }
		wp_add_dashboard_widget('miembropress', 'MiembroPress Dashboard', array(&$this, 'menu_dashboard_panel'));
	}

	public function links($links, $file) {
		if ($file == plugin_basename('miembro-press/miembro-press.php')) {
			array_unshift($links, '<a href="options-general.php?page='.$file.'">Settings</a>');
		}
		return $links;
	}

	public function meta_boxes() {
		if (!function_exists("add_meta_box")) { return; }
		add_meta_box('miembropress-meta', 'MiembroPress', array(&$this, "meta"), "post", "side", "high");
		add_meta_box('miembropress-meta', 'MiembroPress', array(&$this, "meta"), "page", "side", "high");
	}

	public function meta_save($postID=0) {
		global $post;
		global $miembropress;
		if (!isset($_REQUEST["miembropress_action"])) { return; }
		if ($_REQUEST["miembropress_action"] != "meta_save") { return; }
		if ($postID == 0 && isset($post->ID)) {$postID = $post->ID; }
		if (defined("DOING_AUTOSAVE") && constant("DOING_AUTOSAVE")) { return $postID; }
		if (function_exists("wp_is_post_autosave") && wp_is_post_autosave($postID)) { return; }
		if (function_exists("wp_is_post_revision") && ($postRevision = wp_is_post_revision($postID))) { $postID = $postRevision; }
		if (!current_user_can('edit_post', $postID)) { return; }
		MiembroPress::clearCache();
		$protect = ($_POST["miembropress_protect"] == 1);
		
		if ($protect) {
			$miembropress->model->protect($postID, -1);
		} else {
			$miembropress->model->unprotect($postID, -1);
		}

		if ((isset($_POST["miembropress_action"]) && $_POST["miembropress_action"] == "meta_save") || (isset($_POST["miembropress_level"]) && is_array($_POST["miembropress_level"]))) {
			if (isset($_POST["miembropress_level"])) {
				$levels = array_keys($_POST["miembropress_level"]);
			} else { $levels = array(); }
			foreach ($miembropress->model->getLevels() as $level) {
				if (in_array($level->ID, $levels)) {
					$miembropress->model->protect($postID, $level->ID);
				} else {
					$miembropress->model->unprotect($postID, $level->ID);
				}
			}
		}
	}

	public function mail_from_name($original_name) {
		return "Rob Hernandez";
		return get_option( 'name' );
	}

	public function mail_from_email($original_email) {
		return "admin@jumpx.com";
		return get_option('admin_email');
	}
	
	public function meta($fullsize=true) {
		global $miembropress;
		global $post;
		$postID = intval($post->ID);
		$protected = $miembropress->model->isProtected($postID);
		if (empty($post->post_title) && empty($post->post_content)) { $protected = true; }
		$allLevels = $miembropress->model->getLevels();
		$postLevels = array_keys($miembropress->model->getLevelsFromPost($postID));
		if (isset($_REQUEST["miembropress_new"]) && @intval($_REQUEST["miembropress_new"]) > 0) {
			$postLevels[] = @intval($_REQUEST["miembropress_new"]);
		} 
		
		?>
		<input type="hidden" name="miembropress_action" value="meta_save" />
		<?php if ($fullsize): ?>
			<div style="padding: 0; margin:0; border:0; border-bottom: 1px solid #dfdfdf;">
			<p>Allow access to...</p>
			<blockquote>
		<?php endif; ?>
        <label style="display:inline;"><input type="radio" name="miembropress_protect" <?php checked($protected); ?> value="1"> <b>Members Only</b></label>
		<br />
        <label style="display:inline;"><input type="radio" name="miembropress_protect" <?php checked(!$protected); ?>value="0"> <b>Everyone</b></label>
		<br />
        <?php if ($fullsize): ?>
			</blockquote>
			</div>
			<p>Which membership levels have access to view this content?</p>
			<blockquote>
		<?php endif; ?>
        <ul class="miembropress_levels">
			<li><label><b><input type="checkbox" onclick="miembropress_check(this.checked)" /> Select/Unselect All Levels</b></li>
			<?php foreach ($allLevels as $level): ?>
            <li class="miembropress_level">
            <label>
               <input class="miembropress_level" type="checkbox" <?php disabled($level->level_all == 1 || $level->level_page_login == $post->ID); ?> <?php checked(in_array($level->ID, $postLevels) || $level->level_all == 1 || $level->level_page_login == $post->ID); ?> name="miembropress_level[<?php echo htmlentities($level->ID); ?>]" /> <?php echo htmlentities($level->level_name); ?>
               <?php if ($level->level_page_login == $postID): ?><small><a href="<?php echo $this->tabLink("levels"); ?>">(login page for level)</a></small>
			   <?php endif; ?>
            </label>
            </li>
            <?php endforeach; ?>
         </ul>
		<?php if ($fullsize): ?>
			</blockquote>
		<?php endif; ?>

		<?php if (!$fullsize): ?>
        <style type="text/css">
        	li.miembropress_level { display:inline-block; min-width:100px; }
		</style>
		<?php endif; ?>
		<script type="text/javascript">
			function miembropress_check(val) {
				jQuery('.miembropress_level').each(function(i, obj) {
					if (jQuery(obj).attr("disabled") != undefined) { return; }
					jQuery(obj).attr('checked', val);
				});
			}
		</script>
      <?php
	}

	public function menu_header($text="") {
		$call = $this->activation->call();

		if (empty($call) || $call == "FAILED" || $call == "UNREGISTERED" || $call == "OBSOLETE") {
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
			<caption style="background: #111; border-top-left-radius: 10px; border-top-right-radius: 10px;"><img style="margin: 20px 0px 20px 0px;" src="<?php echo base_url . '/assets/images/logomiembropress.png' ?>"></caption>
			<tbody>
				<th colspan="2" style="padding: 20px;background: #ffffff;border: 1px outset white;">Create your membership sites in minutes and get instant payments, with just a few clicks. Integrated with Hotmart, PayPal, ClickBank, JVZoo and WarriorPlus.</th>
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
			#wpfooter { display:none; }
		</style>

		<?php
	}

	public function menu_dashboard_panel() {
		global $miembropress;
		$call = $this->activation->call();
		if (empty($call) || $call == "FAILED" || $call == "UNREGISTERED" || $call == "OBSOLETE") { return;	}
		if (isset($_REQUEST["s"]) && !empty($_REQUEST["s"])) {
			$this->menu_members();
			return;
		}
		$levels = $miembropress->model->getLevels();
		$recent_signups = $miembropress->model->getMembers("number=50&orderby=registered&order=DESC");
		$recent_logins = $miembropress->model->getMembers("number=50&orderby=lastlogin&order=DESC");
		foreach ($miembropress->model->getMembersSince(time()-86400) as $row) {
			if (!isset($levels[$row->level_id])) { continue; }
			$levels[$row->level_id]->since24 = $row->total;
		}
		foreach ($miembropress->model->getMembersSince(time()-86400*7) as $row) {
			if (!isset($levels[$row->level_id])) { continue; }
			$levels[$row->level_id]->since7 = $row->total;
		}
		foreach ($miembropress->model->getMembersSince(time()-86400*30) as $row) {
			if (!isset($levels[$row->level_id])) { continue; }
			$levels[$row->level_id]->since30 = $row->total;
		}

		?>
		<form method="post" action="<?php echo $this->menu; ?>-members">
			<p>
				<input type="search" value="" name="s" placeholder="Search Members" ondblclick="miembropress_multisearch(this);">
				<input type="submit" class="button-primary menus_buttons button-activate " value="Search">
			</p>

			<table class="widefat" style="width:100%;" id="miembropress_dashboard">
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
					<tr class="miembropress_separator">
						<td colspan="6" class="alternate">&nbsp;</td>
					</tr>
					<tr>
						<td style="vertical-align:middle;"><i><a href="<?php echo $this->tabLink("members"); ?>">Total Members</a></i></td>
						<td align="center" class="miembropress_total"><?php echo intval($miembropress->model->getMemberCount("A")); ?></td>
						<td align="center" colspan="4">&nbsp;</td>
					</tr>
				</tbody>
			</table>
		</form>
		<div style="clear:both; width:100%; margin-top:20px; text-align:center;">
	      	<a class="recent_link" href="#" style="font-weight:bold;" onclick="jQuery('.recent_link').css('font-weight', 'normal');
			jQuery(this).css('font-weight', 'bold'); 
			jQuery('#miembropress_recent_signups').show();
			jQuery('#miembropress_recent_logins').hide();
			return false;">Recent Signups</a>
			-
			<a class="recent_link" href="#" onclick="jQuery('.recent_link').css('font-weight', 'normal'); jQuery(this).css('font-weight', 'bold');
			jQuery('#miembropress_recent_signups').hide();
			jQuery('#miembropress_recent_logins').show();
			return false;">Recent Logins</a>
        </div>

		<?php

 		$recentSignupScroll = count($recent_signups) > 5;
		$recentLoginScroll = count($recent_logins) > 5;

		?>
      	<div id="miembropress_recent_signups"<?php if ($recentSignupScroll): ?> style="height:150px; overflow:auto;"<?php endif; ?>>
	      	<ul>
		      <?php foreach ($recent_signups as $recent_signup): ?>
		         <li>
		            <a href="<?php echo get_edit_user_link($recent_signup->ID); ?>"><?php echo htmlentities($recent_signup->user_login); ?></a>
		            <span style="float:right;"><?php echo htmlentities($recent_signup->user_registered); ?> (<?php echo $this->timesince(strtotime($recent_signup->user_registered)); ?>)</span>
		         </li>
		      <?php endforeach; ?>
		    </ul>
		</div>
			  <div id="miembropress_recent_logins" <?php if ($recentLoginScroll): ?>style="display:none; height:150px; overflow:auto;"<?php else: ?>style="display:none;"<?php endif; ?>>
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
			#miembropress_dashboard thead tr th, #miembropress_dashboard tbody tr td { font-size:14px; }
			div.inside form #miembropress_dashboard thead tr th, div.inside form #miembropress_dashboard tbody tr td {
				font-size:12px;
				padding:8px 4px !important;
			}
			.miembropress_total {
				padding:5px; font-weight:bold; font-size:22px !important;
			}
			div.inside form #miembropress_dashboard tbody tr.miembropress_separator { display:none; }
		</style>

		<?php $this->menu_dashboard_chart(); ?>
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

	public function javascript() { ?>
		<script type="text/javascript">
			function miembropress_video(caller, slug) {
				jQuery(caller).css("display", "block").html('<iframe style="max-width:100%; padding:10px;" width="1024" height="600" src="https://www.youtube.com/embed/'+slug+'?autoplay=1&rel=0&showinfo=0" frameborder="0" border="0" allowfullscreen></iframe>').blur();
				return false;
			}
			function miembropress_multisearch(caller) {
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
			function miembropress_change() {
				var action = jQuery("#miembropress_action").val();
				var levels = jQuery("#miembropress_levels");

				if (action == "move" || action == "add" || action == "remove" || action == "cancel" || action == "uncancel") {
					levels.show();
				}
				else {
					levels.hide();
				}
			}
			function miembropress_confirm() {
				var message = 'Are you SURE you want to delete the selected members? This action cannot be undone. Click OK to Continue, or Cancel to stop.';
				return confirm(message);
			}
			
		</script>
      <?php
	}

	public function menu_dashboard_chart() {
		global $miembropress;
		$firstMember = $miembropress->model->getMembers("number=1&orderby=registered&order=ASC");
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
			$months[$key] = array( "active" => $miembropress->model->getMemberCount("A", 0, $end), "canceled" => $miembropress->model->getMemberCount("C", $start, $end), "thisMonth" => $miembropress->model->getMemberCount(null, $start, $end) );
		}
		?>
		<div id="miembropress_chart" style="background-color:transparent; margin-left:-20px; height: 200px;"></div>
			<script type="text/javascript" src="//www.google.com/jsapi"></script>
			<script type="text/javascript">
				google.load('visualization', '1', {packages: ['corechart']});
			</script>
			<script type="text/javascript">
				var chartWidth;
				if (jQuery("#miembropress.postbox").length == 0) { chartWidth = 700; }
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
					new google.visualization.LineChart(document.getElementById('miembropress_chart')).
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
}

?>