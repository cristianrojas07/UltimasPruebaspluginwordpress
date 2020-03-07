<?php 

class MiembroPressCartPayPal extends MiembroPressCart {
	function instructions() {
		global $miembropress;
		if (isset($_POST["paypal_token"])) {
			$miembropress->model->setting("paypal_token", trim($_POST["paypal_token"]));
		}

		$secret = $miembropress->model->setting("paypal_secret");
		$levels = $miembropress->model->getLevels();
		$firstLevel = reset($levels);
		$checkout = $miembropress->model->signupURL($secret);
		?>
		<h3>PayPal Payment</h3>
		<div style="width:800px;">
			<p>In order to accept payments using PayPal, you must:</p>
			<ul style="list-style:disc; padding-left:25px;">
				<li>enable &quot;IPN&quot; and &quot;PDT&quot; in your PayPal account</li>
				<li>enter your &quot;PDT token&quot; on this page</li>
				<li>create a payment button with the &quot;item ID&quot;, &quot;thank you URL&quot;, and &quot;advanced variables&quot; that we provide to you</li>
			</ul>
			<p>It is very important that you follow each step to ensure your payment button is working properly.<br />
			We also highly recommend that you run a test purchase (have a friend buy from you and go through the checkout process) to make sure everything is running smoothly.</p>
			<h3>Step 1: Configure PayPal Settings</h3>
			<blockquote>
				<p><i>(MiembroPress requires you to have a PayPal Business Account. If you only have a PayPal Personal Account then you will need to click <a target="_blank" href="https://www.paypal.com/cgi-bin/webscr?cmd=_registration-run">upgrade your account</a> and choose Business account.)</i></p>
				<ol style="list-style-type:upper-alpha;">
					<li><input type="checkbox" /> <b>Enable Payment Data Transfer:</b> Login to <a target="_blank" href="https://www.paypal.com">your PayPal Account</a> click on <code>Profile</code>, then <code>My Selling Tools</code>, the <code>Selling Online</code> section, and <code>Website Preferences.</code><br />
					If you don't have a <code>Profile</code> tab, click the &quot;head&quot; icon on the top right, <code>Profile and Settings</code> and then <code>Website Preferences.</code><br />
					Click &quot;Update&quot; on the right.</li>
					<li><input type="checkbox" /> Set <code>Auto-Return</code> to <code>On</code> and click <code>Save</code> before continuing.<br />
					If the Return URL is blank, set it to: <code><?php echo site_url(); ?></code> (this URL does not matter, it only needs to point to a FUNCTIONAL website)</li>
					<li><input type="checkbox" /> Set <code>Payment Data Transfer</code> to <code>On</code> and click <code>Save</code> again. <i>Nothing else needs to be changed on this page.</i></li>
					<li><input type="checkbox" /> <b>Next, Enable Instant Payment Notifications:</b> In PayPal, go to <code>Profile</code>, then <code>My Selling Tools</code>, and <code>Instant Payment Notifications</code>.</li>
					<li><input type="checkbox" /> If IPN is turned off, click the yellow button that says <code>Turn IPN On</code>.<br />
					Click <code>Edit Settings</code>, and if the <code>Notification URL</code> is blank, enter <code><?php echo site_url(); ?></code><br />
					(Once again, this URL is not important, it only needs to be an address to a functioning website.)</li>
					<li><input type="checkbox" /> Confirm that the option for <code>Receive IPN messages (Enabled)</code> is checked and click the <code>Save</code> button to save your changes.</li>
				</ol>
			</blockquote>
			<h3>Step 2: Paste Your &quot;Identity Token&quot; from PayPal Below</h3>
			<blockquote>
				<ol style="list-style-type:upper-alpha;">
					<li><input type="checkbox" /> In your PayPal account, go to: <code>Profile</code>, <code>My Selling Tools</code>, the <code>Selling Online</code> section, then <code>Website Preferences</code>.<br />
					If you don't have a <code>Profile</code> tab, click the &quot;head&quot; icon on the top right, <code>Profile and Settings</code> and then <code>Website Preferences.</code><br />
					Click &quot;Update&quot; on the right.</li>
					<li><input type="checkbox" /> Under the Payment Data Transfer section, it should have the text <code>Identity Token:</code> followed by a series of letters and numbers. Copy that special code EXACTLY by highlighting it with your mouse. Be sure not to select the exact text says &quot;Identity Token:&quot; but everything directly after it.</li>
					<li><input type="checkbox" /> Then right click the text box below, and choose &quot;Paste&quot; to place your PDT Identity Token.</li>
				</ol>
				<label><b>PDT Identity Token:</b>
				<?php if ($miembropress->model->setting("paypal_token")): ?>
				<a href="#" onclick="jQuery('.paypal_token').show(); jQuery(this).hide(); return false;">Click to Show</a>
				<?php endif; ?>
				<input type="text" name="paypal_token" class="paypal_token" <?php if ($miembropress->model->setting("paypal_token")): ?>style="display:none;"<?php endif; ?> value="<?php echo htmlentities($miembropress->model->setting("paypal_token")); ?>" size="80" />
				</label> <input <?php if ($miembropress->model->setting("paypal_token")): ?>style="display:none;"<?php endif; ?> type="submit" class="button-secondary" value="Save PDT Identity Token" />
			</blockquote>
			<h3>Step 3: Create a Button in PayPal (Buy Now or Subscription)</h3>
			<blockquote>
				<ol style="list-style-type:upper-alpha;">
					<li><input type="checkbox" /> Create a button that your customers can click on to pay you money and gain access to your site.<br />
					In <a target="_blank" href="https://www.paypal.com">PayPal</a>, Go to <code>Merchant Services</code>, then <code>Create Payment Buttons For Your Website</code> and then choose to create a <code>Buy Now</code> button (for single payments) or a <code>Subscription</code> button for recurring payments.<br />
					If you don't have a <code>Merchant Services</code> tab, click the <code>Tools</code> tab, <code>PayPal Buttons</code>, <code>Create a Button</code>, then <code>Create New Button.</code>
					</li>
					<li>Under <code>Choose a Button Type</code>, choose <code>Buy Now</code> for a single payment site or <code>Subscription</code> for a payment plan or continuity site.</li>
					<li><input type="checkbox" /> Under <code>Item Name</code>, type in the name of the product or membership customers are buying, such as: <code><?php echo get_option("name"); ?> Access</code></li>
					<li><input type="checkbox" /> Under <code>Item ID</code> (this is labeled <code>Subscription ID</code> when creating a recurring button), you will need to copy the NUMBER from this page matching the LEVEL you want to provide access to. For example, if you want to grant access to the <code><?php echo htmlentities($firstLevel->level_name); ?></code> level, you would enter <code><?php echo intval($firstLevel->ID); ?></code> as your button's Item ID.</code></li>
					<p><table class="widefat" style="width:400px;">
						<thead>
							<tr>
								<th scope="col">Level Name</th>
								<th scope="col" style="text-align:center;">Item ID / Subscription ID</th>
							</tr>
						</thead>

						<?php foreach ($levels as $level): ?>
						<tr>
							<td><?php echo htmlentities($level->level_name); ?></td>
							<td style="text-align:center;"><?php echo intval($level->ID); ?></td>
						<?php endforeach; ?>
						</table>
					</p>
					<li><input type="checkbox" /> If you are creating a Buy Now button, enter the amount you want to charge for access in the <code>Price</code> section, such as <code>10.00</code>.</li>
					<li><input type="checkbox" /> If you are creating a Subscription button, you can set your price where under <code>Billing Amount Each Cycle</code>, this is the amount to charge with each billing such as <code>10.00</code>. Under <code>Billing Cycle</code>, choose how often you want to rebill your customers, such as every &quot;30 days&quot; or every &quot;1 month&quot;. Finally, under <code>After How Many Cycles Should Billing Stop?</code> choose <code>Never</code> to continue payments forever or choose a number, for example &quot;5&quot; to bill 5 times and then stop.</li>
					<li><input type="checkbox" /> We highly recommend that in the <code>Step 2: Track Inventory (optional)</code> section, you leave <code>Save Button at PayPal</code> checked.</li>
				</ol>
			</blockquote>
			<h3>Step 4: Set the &quot;Thank You URL&quot; of Your Button</h3>
			<blockquote>
				<ol style="list-style-type:upper-alpha;">
					<li><input type="checkbox" /> Click on <code>Step 3: Customize Advanced Features (optional)</code> on PayPal screen to keep going.</li>
					<li><input type="checkbox" /> <b>Finish Checkout:</b> Also check the box labeled <code>Take Customers To This URL When They Finish Checkout</code></li>
					<li><input type="checkbox" /> Copy that URL to your PayPal button creation screen by highlighting it (click and hold down), be sure not to highlight any spaces or blank areas, right click and Copy, then switch back to PayPal and Paste.</li>
					<p align="center">
					<textarea name="miembropress_checkout" id="miembropress_checkout" cols="60" rows="2" class="code" style="font-size:18px; font-weight:bold;"><?php echo htmlentities($checkout); ?></textarea><br />
					<input style="text-align:center;" type="submit" class="button-secondary" onclick="document.getElementById('miembropress_checkout').select(); return false;" value="Select All" />
					</p>
				</ol>
			</blockquote>
			<h3>Step 5: Paste the Code Below in the &quot;Add Advanced Variables&quot; Field</h3>
			<blockquote>
				<p>You're almost finished creating your payment button.</p>
				<p><input type="checkbox" /> At the very botton of the page, check the <code>Add Advanced Variables</code> checkbox, and in the box below it, paste in this final bit of code:</p>
			</blockquote>
			<p align="center" style="text-align:center;">
				<textarea name="miembropress_variables" id="miembropress_variables"  cols="70" rows="3" class="code" style="font-size:18px; font-weight:bold;">rm=2<?php echo chr(10); ?>notify_url=<?php echo $checkout; ?><?php echo chr(10); ?>return=<?php echo $checkout; ?></textarea><br />
				<input style="text-align:center;" type="submit" class="button-secondary" onclick="document.getElementById('miembropress_variables').select(); return false;1" value="Select All" />
			</p>
			<blockquote>
				<p>Please make sure that you select, copy and paste all three lines above, into your PayPal button, with no extra spaces anywhere.</p>
			</blockquote>
			<h3>Step 6: Copy the Button Code from PayPal and Paste Into Your Sales Letter</h3>
			<blockquote>
				<p><input type="checkbox" /> Click <code>Create Button</code> and you should be taken to a new screen where you are presented with special code to place on your website to accept payments.</p>
				<p>We highly suggest you click the <code>Email</code> tab to grab a simple link that you can place on your web pages. We use <a target="_blank" href="http://www.papertemplate.com">Paper Template</a> to present sales letters to customers.</p>
			</blockquote>
			<p><input type="submit" class="button-primary" value="Save Settings" /></p>
		</div>
		<?php
	}

	function verify() {
		global $miembropress;
		$info = null;
		MiembroPress::clearCache();
		if (isset($_GET["tx"])) {
			$info = $this->verifyPDT($_GET["tx"], $miembropress->model->setting("paypal_token"));
		} elseif (isset($_POST["payer_email"])) {
			$info = $this->verifyIPN($_POST);
		}
		$transaction = null;
		if (isset($info["subscr_id"])) {
			$transaction = $info["subscr_id"];
		} elseif (isset($info["parent_txn_id"])) {
			$transaction = $info["parent_txn_id"];
		} elseif (isset($info["txn_id"])) {
			$transaction = $info["txn_id"];
		}
		$status = null;
		if (isset($info["payment_status"])) {
			$status = $info["payment_status"];
		} elseif (isset($info["txn_type"])) {
			$status = $info["txn_type"];
		}

		if (isset($info["first_name"]) && isset($info["last_name"]) && isset($info["payer_email"]) && isset($info["item_number"])) {
			$result = array( "firstname" => $info["first_name"], "lastname" => $info["last_name"], "email" => $info["payer_email"], "username" => $info["first_name"]." ".$info["last_name"], "level" => intval($info["item_number"]), "transaction" => $transaction, "action" => "register" );
		} else {
			$result = array();
		}

		if ($status == "Expired" || $status == "Failed" || $status == "Refunded" || $status == "Reversed" || $status == "subscr_failed" || $status == "recurring_payment_suspended_due_to_max_failed_payment" || $status == "subscr_cancel") {
			$result["action"] = "cancel";
		}
		return $result;
	}

	function verifyPDT($tx, $token) {
		$post = wp_remote_post("https://www.paypal.com/cgi-bin/webscr", array( "body" => array("cmd" => "_notify-synch", "tx" => $tx, "at" => $token), "httpversion" => 1.1 ) );
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

	function verifyIPN($vars) {
		global $miembropress;
		$post = wp_remote_post("https://www.paypal.com/cgi-bin/webscr", array( "body" => array_merge(array("cmd" => "_notify-validate"), $vars), "httpversion" => 1.1 ) );
		$body = wp_remote_retrieve_body($post);
		if ($miembropress->model->setting("notify")==1) {
			$this->notify();
		}
		if (trim($body) != "VERIFIED") { return null; }
		return $vars;
	}

	public function notify() {
		if (!isset($_POST["payer_email"])) { return; }
		$subject = "[IPN] PayPal Notification Log";
		$price = "";
		if (isset($_POST["amount3"])) {
			$price = floatval($_POST["amount3"]);
		} elseif (isset($_POST["mc_amount3"])) {
			$price = floatval($_POST["mc_amount3"]);
		} elseif (isset($_POST["mc_gross"])) {
			$price = floatval($_POST["mc_gross"]);
		} elseif (isset($_POST["mc_gross1"])) {
			$price = floatval($_POST["mc_gross1"]);
		} elseif (isset($_POST["payment_gross"])) {
			$price = floatval($_POST["payment_gross"]);
		} if ($price == "") {
			$thePrice = "";
		} else {
			$thePrice = "" . number_format($price, 2);
		}

		if ($_POST["payment_status"] == "Refunded" || $_POST["payment_status"] == "Reversed") {
			$subject .= " (REFUND".$thePrice.")";
		} elseif ($_POST["txn_type"] == "web_accept" || $_POST["txn_type"] == "express_checkout") {
			$subject .= " (SALE".$thePrice.")";
		} elseif ($_POST["txn_type"] == "subscr_payment") {
			$subject .= " (REBILL".$thePrice.")";
		} elseif ($_POST["txn_type"] == "subscr_eot") {
			$subject .= " (ENDED".$thePrice.")";
		} elseif ($_POST["txn_type"] == "recurring_payment_outstanding_payment_failed" || $_POST["txn_type"] == "subscr_failed" || $_POST["txn_type"] == "recurring_payment_suspended_due_to_max_failed_payment" || $_POST["txn_type"] == "recurring_payment_skipped") {
			$subject .= " (REBILL FAILED".$thePrice.")";
		} elseif ($_POST["txn_type"] == "subscr_cancel" || $_POST["txn_type"] == "recurring_payment_profile_cancel") {
			$subject .= " (CANCELLATION".$thePrice.")";
		} elseif ($_POST["txn_type"] == "subscr_signup") {
			$subject .= " (SIGNUP".$thePrice.")";
		}
		$paymentDate = 0;
		if (isset($_POST["payment_date"])) {
			$paymentDate = strtotime($_POST["payment_date"]);
		} elseif (isset($_POST["subscr_date"])) {
			$paymentDate = strtotime($_POST["subscr_date"]);
		} else {
			$paymentDate = time();
		}
		$message = "A new sale/refund was processed for customer ".htmlentities($_POST["payer_email"]);
		$message .= " on ".date("m/d/".chr(89), $paymentDate)." at ".date("g:i A e", $paymentDate); $message .= " Details: ";
		foreach ($_POST as $key => $value) {
			$message .= $key.': '.$value." ";
		}
		$this->email($subject, $message);
	}
}

?>