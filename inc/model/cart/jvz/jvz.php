<?php

class MiembroPressCartJVZ extends MiembroPressCart {
	function instructions() {
		global $miembropress;
		if (isset($_POST["jvz_token"])) {
			$token = trim($_POST["jvz_token"]);
			if (!$token) { $token = null; }
			$miembropress->model->setting("jvz_token", $token);
		}
		$secret = $miembropress->model->setting("jvz_secret");
		$token = $miembropress->model->setting("jvz_token");
		if (!$token) {
			$token = $miembropress->model->setting("jvz_token", rand(10000000, 99999999));
		}
		$levels = $miembropress->model->getLevels();
		$firstLevel = reset($levels);
		$checkout = $miembropress->model->signupURL($secret);
		if (isset($_POST["miembropress_jvz_item"]) && is_array($_POST["miembropress_jvz_item"])) {
			$items = array();
			foreach ($_POST["miembropress_jvz_item"] as $key => $value) {
				$value = @intval($value);
				if ($value > 0) { $items[$key] = $value; }
			}
			$miembropress->model->setting("jvz_items", $items);
		}
		$items = $miembropress->model->setting("jvz_items");
		if (!is_array($items)) { $items = array(); }
		?>
		<h3>JVZoo Payment</h3>
		<p>In order to accept payments using JV Zoo, you must:</p>
		<ul style="list-style:disc; padding-left:25px;">
			<li>Register a JVZoo SELLER account by registering at the <a target="_blank" href="https://www.jvzoo.com/sellers">JVZoo Seller</a> website</li>
			<li>Set your &quot;JVZIPN secret key&quot; to match both this page and your &quot;My Account&quot; area in JVZoo</li>
			<li>Create a JVZoo product (or edit your existing one)</li>
			<li>Edit that product and set the &quot;thank you&quot; page we provide you</li>
			<li>Check the &quot;pass parameters&quot; box</li>
			<li>Paste in the &quot;JVZIPN URL&quot; we give you, set &quot;JVZIPN Special Integration&quot; to &quot;Wishlist Member&quot; and paste in the &quot;Wishlist SKU&quot; we give you</li>
		</ul>
		<div style="width:800px;">
			<h3>Step 1: Configure JVZoo Settings &amp; Secret Key</h3>
			<blockquote>
				<ol style="list-style-type:upper-alpha;">
					<li><input type="checkbox" /> If you don't yet have a JVZoo Seller account, <a target="_blank" href="https://www.jvzoo.com/sellers">go to their website and create one</a>. It requires a <a target="_blank" href="https://www.paypal.com">PayPal account</a>, and they'll have you click through steps to link your PayPal account and pre-authorize payments to affiliates.</li>
					<li><input type="checkbox" /> After you've created that seller account, go to <code>My Account, My Account</code>, and next to <code>JVZIPN Secret Key</code>, click the link that says: <code>Click here to edit JVZIPN Secret Key</code>.</li>
					<li><input type="checkbox" /> Copy the JVZoo secret key from below (or match the one below if your account already has one.</li>
					<p><blockquote>
					<label><b>JVZoo Secret Key:</b>
						<?php if ($miembropress->model->setting("jvz_token")): ?>
						<a href="#" onclick="jQuery('.jvz_token').show(); jQuery(this).hide(); return false;">Click to Show</a>
						<?php endif; ?>
						<input type="text" name="jvz_token" class="jvz_token" <?php if ($miembropress->model->setting("jvz_token")): ?>style="display:none;"<?php endif; ?> value="<?php echo htmlentities($miembropress->model->setting("jvz_token")); ?>" size="25" />
					</label>
					<input class="jvz_token" type="submit" <?php if ($miembropress->model->setting("jvz_token")): ?>style="display:none;"<?php endif; ?> class="button-primary menus_buttons button-activate " value="Save JVZoo Secret Key" />
					</blockquote></p>
					<li><input type="checkbox" /> Be sure to click the <code>Save</code> button to apply this change to your account.</li>
				</ol>
			</blockquote>
			<h3>Step 2: Create JVZoo Product</h3>
			<blockquote>
				<ol style="list-style-type:upper-alpha;">
					<li><input type="checkbox" /> Create your product in the JVZoo member's area by going to <code>Sellers, Seller's Dashboard</code>.</li>
					<li><input type="checkbox" /> Click the giant yellow button that says: <code>Add A Product (It's FREE!)</code></li>
					<li><input type="checkbox" /> Fill in the name, price, commission payout, support email address, landing page (i.e. <code><?php if (is_ssl()): ?>https://<?php else: ?>http://<?php endif; ?><?php echo $_SERVER["HTTP_HOST"]; ?></code>, sales page URL (i.e. <code><?php if (is_ssl()): ?>https://<?php else: ?>http://<?php endif; ?><?php echo $_SERVER["HTTP_HOST"]; ?></code>).</li>
					<li><input type="checkbox" /> Set <code>Delivery Method</code> to <code>Thank You Page</code> (NOT Protected Download).</li>
				</ol>
			</blockquote>
			<h3>Step 3: Set Thank You Page</h3>
			<blockquote>
				<ol style="list-style-type:upper-alpha;">
				<p><input type="checkbox" /> While you're still creating that JVZoo product, be SURE that you check <code>Pass parameters to Download Page.</code> This is extremely important.</p>
				<p><input type="checkbox" /> Set the <code>Thank You / Download Page</code> to the URL below:</p>
				<p align="center">
					<textarea name="miembropress_checkout" id="miembropress_checkout" cols="60" rows="2" class="code" style="font-size:18px; font-weight:bold; background-color:white;" readonly="readonly"><?php echo htmlentities($checkout); ?></textarea><br />
					<input style="text-align:center;" type="submit" class="button-primary menus_buttons button-activate " onclick="document.getElementById('miembropress_checkout').select(); return false;" value="Select All" />
				</p>
				</ol>
			</blockquote>

			<h3>Step 4: Set the &quot;External Program Integration&quot; Settings</h3>

			<blockquote>
				<ol style="list-style-type:upper-alpha;">
					<li><input type="checkbox" /> Under <code>Recommended: Method #1</code>, set your <code>JVZIPN IPN URL</code> to the URL below:</li>

					<blockquote>
						<p align="center">
						<textarea name="miembropress_notify" id="miembropress_notify" cols="60" rows="2" class="code" style="font-size:18px; font-weight:bold; background-color:white;" readonly="readonly"><?php echo htmlentities($checkout); ?></textarea><br />
						<input style="text-align:center;" type="submit" class="button-primary menus_buttons button-activate " onclick="document.getElementById('miembropress_notify').select(); return false;" value="Select All" />
						</p>
					</blockquote>

					<li><input type="checkbox" /> Set <code>Use JVZIPN Output as Key Generation</code> to <code>NO</code>.</li>

					<li><input type="checkbox" /> Set the <code>JVZIPN Special Integration</code> dropdown to <code>Wishlist Member</code>.</li>
				</ol>
				<h3>Step 5: Enter Product ID from JVZoo</h3>
				<p><input type="checkbox" /> Find the product ID in JVZoo. This should be near the top of the Edit Product page, above &quot;Allow Sales.&quot;</p>
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
					<td style="text-align:center;"><input type="text" size="10" name="miembropress_jvz_item[<?php echo htmlentities($level->ID); ?>]" value="<?php echo $item; ?>" /> </td>
					<?php endforeach; ?>
					</table></p>
					<p><input type="submit" class="button-primary menus_buttons button-activate " value="Save JVZoo Product IDs" /></p>
				</blockquote>
			</blockquote>
			<h3>Step 6: Create Your Test Button in JVZoo</h3>
			<blockquote>
				<ol style="list-style-type:upper-alpha;">
					<li><input type="checkbox" /> Go to <code>Seller, Seller's Dashboard</code>, then under the <code>Additional Functions</code> subtitle on that page, click the <code>Test Purchases</code> button.</li>
					<li><input type="checkbox" /> On the next screen, find the product you just created in the dropdown list, and click <code>Create Test Purchase Code</code>.</li>
					<li><input type="checkbox" /> Be sure you're logged out of this membership site. The JVZoo page will give you a <code>Buy / Link</code>. Right click and open this link in a <code>New Incognito Window</code>. This will send you to a checkout page where you'll use a second PayPal account to pay $0.01 and verify that the checkout process works for you.</li>
					<li><input type="checkbox" /> Pay the $0.01, be redirected to your JVZoo purchases, click <code>Access Your Purchase</code> and you'll end up at your MiembroPress registration page to create an account for the product you just test-purchased.</li>
				</ol>
			</blockquote>
			<h3>Step 6: Copy the Buy Button Code from JVZoo and Paste Into Your Sales Letter</h3>
			<blockquote>
				<p><input type="checkbox" /> Go to <code>Seller, Seller's Dashboard</code>, find the product you just created, and click <code>Buy Buttons</code>. This will take you to a new screen where you are presented with special code to place on your website to accept payments.</p>
				<p>Grab the HTML code to place on your web pages. We use <a target="_blank" href="http://www.papertemplate.com">Paper Template</a> to present sales letters to customers.</p>
			</blockquote>
			<p><input type="submit" class="button-primary menus_buttons button-activate " value="Save Settings" /></p>
		</div>
		<?php
	}

	function verify() {
		global $miembropress;
		$info = null;
		MiembroPress::clearCache();
		if (isset($_GET["cbreceipt"])) {
			$info = $this->verifyPDT($_GET, $miembropress->model->setting("jvz_token"));
		} elseif (isset($_POST["caffitid"])) {
			$info = $this->verifyIPN($_POST, $miembropress->model->setting("jvz_token"));
		}
		if (!$info || count($info) == 0) { return false; }
		if (isset($info["ccustname"]) && isset($info["ccustemail"]) && isset($info["cproditem"]) && isset($info["ctransreceipt"])) {
			parse_str($info["cvendthru"], $cvendthru);
			if (isset($cvendthru["sku"])) {
				$sku = $cvendthru["sku"];
			} else { $sku = 0; }
			list($firstname, $lastname) = preg_split('@ @', trim($info["ccustname"]), 2);
			$result = array( "firstname" => $firstname, "lastname" => $lastname, "email" => $info["ccustemail"], "username" => $firstname." ".$lastname, "level" => intval($sku), "transaction" => $info["ctransreceipt"], "action" => "register" );
		} elseif (isset($info["cbreceipt"]) && isset($info["cname"]) && isset($info["cemail"])) {
			list($firstname, $lastname) = preg_split('@ @', trim($info["cname"]), 2);
			$result = array( "firstname" => $firstname, "lastname" => $lastname, "email" => $info["cemail"], "username" => $firstname." ".$lastname, "transaction" => $info["cbreceipt"], "action" => "register" );
			if (isset($info["sku"])) { $result["level"] = @intval($info["sku"]); }
		} else { $result = array(); }
		$status = null;
		if (isset($info["ctransaction"])) { $status = $info["ctransaction"]; }
		if ($status == "RFND" || $status == "CGBK" || $status == "INSF" || $status == "CANCEL-REBILL") {
			$result["action"] = "cancel";
		}
		return $result;
	}

	function verifyPDT($vars, $secretKey) {
		$rcpt=$_REQUEST['cbreceipt'];
		$time=$_REQUEST['time'];
		$item=$_REQUEST['item'];
		$cbpop=$_REQUEST['cbpop'];
		$xxpop=sha1("$secretKey|$rcpt|$time|$item");
		$xxpop=strtoupper(substr($xxpop,0,8));
		if ($cbpop==$xxpop) { return $vars; }
		return array();
	}

	function verifyIPN($post, $secretKey) {
		global $miembropress;
		$unescape = get_magic_quotes_gpc();
		$fields = array('ccustname', 'ccustemail', 'ccustcc', 'ccuststate', 'ctransreceipt', 'cproditem', 'ctransaction', 'ctransaffiliate', 'ctranspublisher', 'cprodtype', 'cprodtitle', 'ctranspaymentmethod', 'ctransamount', 'caffitid', 'cvendthru', 'cverify');
		if ($miembropress->model->setting("notify")==1) { $this->notify(); }
		$vars = array();
		foreach ($fields as $field) {
			if (isset($post[$field])) { $vars[$field] = $post[$field]; }
		}
		$pop = "";
		$ipnFields = array();
		foreach ($vars AS $key => $value) {
			if ($key == "cverify") { continue; }
			$ipnFields[] = $key;
		}
		foreach ($ipnFields as $field) {
			if ($unescape) {
				$pop .= stripslashes($post_vars[$field]) . "|";
			} else { $pop .= $vars[$field] . "|"; }
		}
		$pop = $pop . $secretKey;
		$calcedVerify = sha1(mb_convert_encoding($pop, "UTF-8"));
		$calcedVerify = strtoupper(substr($calcedVerify,0,8));
		if ($calcedVerify == $vars["cverify"]) { return $vars; }
		return array();
	}

	public function notify() {
		$subject = "[IPN] JVZoo Notification Log";
		$price = "";
		if (isset($_POST["ctransamount"])) {
			$price = floatval($_POST["ctransamount"]);
		}
		if ($price == "") {
			$thePrice = "";
		} else { $thePrice = "" . number_format($price, 2); }

		if ($_POST["ctransaction"] == "RFND" || $_POST["ctransaction"] == "CGBK" || $_POST["ctransaction"] == "INSF") {
			$subject .= " (REFUND".$thePrice.")";
		} elseif ($_POST["ctransaction"] == "SALE") {
			$subject .= " (SALE".$thePrice.")";
		} elseif ($_POST["ctransaction"] == "BILL") {
			$subject .= " (REBILL".$thePrice.")";
		} elseif ($_POST["ctransaction"] == "CANCEL-REBILL") {
			$subject .= " (CANCELLATION".$thePrice.")";
		}
		$paymentDate = 0;
		if (isset($_POST["ctranstime"])) {
			$paymentDate = intval($_POST["ctranstime"]);
		} else {
			$paymentDate = time();
		}
		$message = "A new sale/refund was processed for customer ".htmlentities($_POST["ccustemail"]);
		if ($paymentDate) {
			$message .= " on ".date("m/d/".chr(89), $paymentDate)." at ".date("g:i A e", $paymentDate);
		}
		$message .= "Details: ";
		foreach ($_POST as $key => $value) { $message .= $key.': '.$value." "; }
		$this->email($subject, $message);
	}
}

?>