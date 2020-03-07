<?php

class MiembroPressCartHotmart extends MiembroPressCart {
	function instructions() {
		global $miembropress;
		if (isset($_POST["hotmart_id"])) {
			$hotmart_id = trim($_POST["hotmart_id"]);
			if (!$hotmart_id) { $hotmart_id = null; }
			$miembropress->model->setting("hotmart_id", $hotmart_id);
		}
		if (isset($_POST["hotmart_secretid"])) {
			$hotmart_secretid = trim($_POST["hotmart_secretid"]);
			if (!$hotmart_secretid) { $hotmart_secretid = null; }
			$miembropress->model->setting("hotmart_secretid", $hotmart_secretid);
		}
		if (isset($_POST["hotmart_basic"])) {
			$hotmart_basic = trim($_POST["hotmart_basic"]);
			if (!$hotmart_basic) { $hotmart_basic = null; }
			$miembropress->model->setting("hotmart_basic", $hotmart_basic);
		}
		$secret = $miembropress->model->setting("hotmart_secret");
		$token = $miembropress->model->setting("hotmart_token");
		$id = $miembropress->model->setting("hotmart_id");
		$secretid = $miembropress->model->setting("hotmart_secretid");
		$basic = $miembropress->model->setting("hotmart_basic");
		$levels = $miembropress->model->getLevels();
		$firstLevel = reset($levels);
		$checkout = $miembropress->model->signupURL($secret);
		if (!$token) {
			$token = $miembropress->model->setting("hotmart_token", rand(10000000, 99999999));
		}
		if (!$id) {
			$id = $miembropress->model->setting("hotmart_id", rand(10000000, 99999999));
		}
		if (!$secretid) {
			$token = $miembropress->model->setting("hotmart_secretid", rand(10000000, 99999999));
		}
		if (!$basic) {
			$token = $miembropress->model->setting("hotmart_basic", rand(10000000, 99999999));
		}
		if (isset($_POST["miembropress_hotmart_item"]) && is_array($_POST["miembropress_hotmart_item"])) {
			$items = array();
			foreach ($_POST["miembropress_hotmart_item"] as $key => $value) {
				$value = @intval($value);
				if ($value > 0) { $items[$key] = $value; }
			}
			$miembropress->model->setting("hotmart_items", $items);
		}
		$items = $miembropress->model->setting("hotmart_items");
		if (!is_array($items)) { $items = array(); }
		?>
		<h3>Hotmart Payment</h3>
		<p>In order to accept payments using Hotmart, you must:</p>
		<h3><b style="background-color:yellow;">Step 1:</b> Register a Hotmart SELLER account by registering at the Hotmart website </h3>
		<h3><b style="background-color:yellow;">Step 2:</b> Set your "Hotmart Credentials" to match both this page and your "Tools" area in Hotmart <br />
			Paste Hotmart ID, SecretID & Basic below:</h3>
			<blockquote>
				<blockquote>
					<ul>
						<li>
							<h3>A) Go to: "HotConnect" -> "Credentials" to get or create your credentials.</h3>
						</li>
						<li>
							<h3>B) Paste your Client ID, Client Secret &amp Basic below:</h3>
						</li>
					</ul>
				</blockquote>
				<ol style="list-style-type:upper-alpha;">
					<p><blockquote>
					<label><b>Client ID:</b>
						<?php if ($miembropress->model->setting("hotmart_id")): ?>
							<a href="#" onclick="jQuery('.hotmart_id').show(); jQuery(this).hide(); return false;">Click to Show</a>
						<?php endif; ?>
						<input type="text" name="hotmart_id" class="hotmart_id" <?php if ($miembropress->model->setting("hotmart_id")): ?>style="display:none;"<?php endif; ?> value="<?php echo htmlentities($miembropress->model->setting("hotmart_id")); ?>" size="25" />
					</label>
					<input class="hotmart_id" type="submit" <?php if ($miembropress->model->setting("hotmart_id")): ?>style="display:none;"<?php endif; ?> class="button-secondary" value="Save Client ID" />
					</blockquote></p>
					<p><blockquote>
					<label><b>Client Secret:</b>
						<?php if ($miembropress->model->setting("hotmart_secretid")): ?>
						<a href="#" onclick="jQuery('.hotmart_secretid').show(); jQuery(this).hide(); return false;">Click to Show</a>
						<?php endif; ?>
						<input type="text" name="hotmart_secretid" class="hotmart_secretid" <?php if ($miembropress->model->setting("hotmart_secretid")): ?>style="display:none;"<?php endif; ?> value="<?php echo htmlentities($miembropress->model->setting("hotmart_secretid")); ?>" size="25" />
					</label>
					<input class="hotmart_secretid" type="submit" <?php if ($miembropress->model->setting("hotmart_secretid")): ?>style="display:none;"<?php endif; ?> class="button-secondary" value="Save Client Secret" />
					</blockquote></p>
					<p><blockquote>
					<label><b>Basic:</b>
						<?php if ($miembropress->model->setting("hotmart_basic")): ?>
						<a href="#" onclick="jQuery('.hotmart_basic').show(); jQuery(this).hide(); return false;">Click to Show</a>
						<?php endif; ?>
						<input type="text" name="hotmart_basic" class="hotmart_basic" <?php if ($miembropress->model->setting("hotmart_basic")): ?>style="display:none;"<?php endif; ?> value="<?php echo htmlentities($miembropress->model->setting("hotmart_basic")); ?>" size="25" />
					</label>
					<input class="hotmart_basic" type="submit" <?php if ($miembropress->model->setting("hotmart_basic")): ?>style="display:none;"<?php endif; ?> class="button-secondary" value="Save Basic" />
					</blockquote></p>
				</ol>
			</blockquote><br />
		<h3><b style="background-color:yellow;">Step 3:</b> Set Thank You Page</h3>
			<blockquote>
				<blockquote>
					<ul>
						<li>
							<h3>A) Copy your thank you page.</h3>
							<blockquote>
								<ol style="list-style-type:upper-alpha;">
								<p align="center">
									<textarea name="miembropress_checkout" id="miembropress_checkout" cols="60" rows="2" class="code" style="font-size:18px; font-weight:bold; background-color:white;" readonly="readonly"><?php echo plugins_url('/miembro-press/request.php', dirname(__FILE__) ) ?></textarea><br />
									<input style="text-align:center;" type="submit" class="button-secondary" onclick="document.getElementById('miembropress_checkout').select(); return false;" value="Select All" />
								</p>
								</ol>
							</blockquote>
						</li>
						<br />
						<li>
							<h3>B) Go to "Checkout Configurations" -> Thank You Page Options -> External Page and Paste Your Thank You Page:</h3>
						</li>
					</ul>
				</blockquote>
			</blockquote><br />
		<h3><b style="background-color:yellow;">Step 4:</b> Enter Product ID from Hotmart</h3>
			<p>It should be a 6-digit number printed like this:<br />
			<code>BASIC INFORMATION FOR PRODUCT ID: 111222</code></p>
			<p>Enter that product ID in the table below corresponding to the level you want to grant access to.</p>
			<blockquote>
				<p><table class="widefat" style="width:400px;">
				<thead>
				<tr>
					<th scope="col">Level Name to Provide Access To...</th>
					<th scope="col" style="text-align:center;">Product ID</th>
				</tr>
				</thead>
				<?php foreach ($levels as $level): ?>
				<?php $item = "";
				if (isset($items[$level->ID])) {
					$item = $items[$level->ID];
				} else {
					$item = "";
				} ?>
				<tr>
				<td><?php echo htmlentities($level->level_name); ?></td>
				<td style="text-align:center;"><input type="text" size="10" name="miembropress_hotmart_item[<?php echo htmlentities($level->ID); ?>]" value="<?php echo $item; ?>" /> </td>
				<?php endforeach; ?>
				</table></p>
				<p><input type="submit" class="button" value="Save Hotmart Product IDs" /></p>
			</blockquote>
		<p><input type="submit" class="button-primary" value="Save Settings" /></p>
	<?php
	}

	function verify() {
		global $miembropress;
		$info = null;
		MiembroPress::clearCache();
		if (isset($_GET["transaction"])) {
			$info = $this->verifyPDT($_GET["transaction"], $_GET["aff"], $miembropress->model->setting("hotmart_token"));
		}
		$transaction = null;
		if (isset($info["transaction_ext"])) {
			$transaction = $info["transaction_ext"];
		}
		$status = null;
		if (isset($info["status"])) {
			$status = $info["status"];
		}

		if (isset($info["first_name"]) && isset($info["last_name"]) && isset($info["email"]) && isset($info["prod"])) {
			$result = array( "firstname" => $info["first_name"], "lastname" => $info["last_name"], "email" => $info["email"], "username" => $info["first_name"]." ".$info["last_name"], "level" => intval($info["prod"]), "transaction" => $transaction, "action" => "register" );
		} else {
			$result = array();
		}

		if ($status == "expired" || $status == "refunded" || $status == "canceled") {
			$result["action"] = "cancel";
		}
		return $result;
	}

	function verifyPDT($transaction, $aff, $token) {
		$post = wp_remote_post(htmlentities($checkout), array( "body" => array("transaction" => $transaction, "aff" => $aff, "hottok" => $token), "httpversion" => 1.1 ) );
		$body = wp_remote_retrieve_body($post);
		$lines = explode(" ", $body);
		$result = array();
		if (trim($lines[0]) != "SUCCESS") { return null; }
		foreach ($lines as $line) {
			$key = null;
			$value = null;
			$fields = @explode("=", trim($line));
			if (count($fields) == 1) {
				list($key) = $fields;
			} else {
				list($key, $value) = $fields;
			}
			if ($value !== null) {
				$result[$key] = urldecode($value);
			}
		}
		return $result;
	}
}

?>