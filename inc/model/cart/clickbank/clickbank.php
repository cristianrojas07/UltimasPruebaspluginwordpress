<?php

class MemberGeniusCartClickbank extends MemberGeniusCart {
	function instructions() {
		global $miembropress;
		if (isset($_POST["clickbank_token"])) {
			$token = trim($_POST["clickbank_token"]);
			if (!$token) { $token = null; }
			$miembropress->model->setting("clickbank_token", $token);
		}
		$secret = $miembropress->model->setting("clickbank_secret");
		$token = $miembropress->model->setting("clickbank_token");
		if (!$token) {
			$token = $miembropress->model->setting("clickbank_token", rand(10000000, 99999999));
		}
		if (isset($_POST["clickbank_account"])) {
			$account = preg_replace('@[^A-Z0-9]@si', '', stripslashes($_POST["clickbank_account"]));
			$miembropress->model->setting("clickbank_account", $account);
		}
		$account = $miembropress->model->setting("clickbank_account");
		if (!$account) { $account = "account_nickname"; }
		if (isset($_POST["miembropress_clickbank_item"]) && is_array($_POST["miembropress_clickbank_item"])) {
			$items = array();
			foreach ($_POST["miembropress_clickbank_item"] as $key => $value) {
				$items[$key] = $value;
			}
			$miembropress->model->setting("clickbank_items", $items);
		}
		$items = $miembropress->model->setting("clickbank_items");
		if (!is_array($items)) { $items = array(); }
		$levels = $miembropress->model->getLevels();
		$firstLevel = reset($levels);
		$checkout = $miembropress->model->signupURL($secret); ?>
      <h3>Clickbank Payment</h3>

      <p>In order to accept payments using Clickbank, you must:</p>

      <ul style="list-style:disc; padding-left:25px;">
         <li>Register a Clickbank SELLER account by registering at the <a target="_blank" href="https://accounts.clickbank.com/public/#/signup/form/key/">Clickbank</a> website</li>
         <li>Set your &quot;Clickbank secret key&quot; to match both this page and your &quot;My Site&quot; area in Clickbank</li>
         <li>Paste in the &quot;Clickbank IPN URL&quot; we give you</li>
         <li>Create a Clickbank product (or edit your existing one)</li>
         <li>Edit that product and set the &quot;thank you&quot; page we provide you</li>
         <li>Pay Clickbank's one-time $49.95 activation charge so that you can begin taking payments</li>
      </ul>

      <div style="width:800px;">
      <h3><b style="background-color:yellow;">Step 1:</b> Configure Clickbank Account Information</h3>

      <p>After you've created your Clickbank account, login at <a target="_blank" href="http://www.clickbank.com">Clickbank.com</a>. In the top tabs, go to Settings, My Site, scroll down to &quot;Advanced Tools&quot; and click Edit. The Secret Key is on that page.</p>

      <p>Be sure the secret key on Clickbank's site matches the secret key here.</p>

      <p><blockquote>
      <label><b>Clickbank Secret Key:</b>
      <?php if ($miembropress->model->setting("clickbank_token")): ?>
      <a href="#" onclick="jQuery('.clickbank_token').show(); jQuery(this).hide(); return false;">Click to Show</a>
      <?php endif; ?>

      <input type="text" name="clickbank_token" class="clickbank_token" <?php if ($miembropress->model->setting("clickbank_token")): ?>style="display:none;"<?php endif; ?> value="<?php echo htmlentities($miembropress->model->setting("clickbank_token")); ?>" size="25" />
         </label> <input class="clickbank_token" type="submit" <?php if ($miembropress->model->setting("clickbank_token")): ?>style="display:none;"<?php endif; ?> class="button-secondary" value="Save Clickbank Secret Key" />
      </blockquote>

      <blockquote>
      <label><b>Clickbank Account Name:</b>
      <input type="text" name="clickbank_account" value="<?php echo htmlentities($miembropress->model->setting("clickbank_account")); ?>" size="20" />
      </label>
      </blockquote></p>

      <p><input type="submit" class="button" value="Save Account Settings" /></p>

      <h3><b style="background-color:yellow;">Step 2:</b> Set Clickbank &quot;Instant Payment Notification&quot; Settings</h3>

      <p>While you're still in the &quot;Advanced Tools&quot; page (Settings, My Site, Advanced Tools, Edit) copy the URL below and paste it in as the Instant Notification URL. Set &quot;Version&quot; to <code>4.0</code>.</p>

      <blockquote>
         <p align="center">
         <textarea name="miembropress_notify" id="miembropress_notify" cols="60" rows="2" class="code" style="font-size:18px; font-weight:bold; background-color:white;" readonly="readonly"><?php echo htmlentities($checkout); ?></textarea><br />
         <input style="text-align:center;" type="submit" class="button-secondary" onclick="document.getElementById('miembropress_notify').select(); return false;" value="Select All" />
         </p>
      </blockquote>

      <h3><b style="background-color:yellow;">Step 3:</b> Create Product in Clickbank</h3>

      <p>Your account is ready to go, now let's create your product within Clickbank. Go to Settings, My Products, Add New: Product.</p>

      <ul style="list-style:disc; padding-left:25px;">
         <li><b>Product Type:</b> One-Time Digital Product</li>
         <li><b>Product Category:</b> Website Membership</li>
         <li><b>Product Title:</b> Name of Your Product</li>
         <li><b>Language:</b> English</li>
         <li><b>Pitch Page URL:</b> the URL to your sales letter</li>
         <li><b>Product Price:</b> the price you'll charge for your product (minimum $3)</li>
         <li><b>Thank You Page URL:</b> set this to the URL below</li>
      </ul>

      <blockquote>
         <p align="center">
         <textarea name="miembropress_notify" id="miembropress_thankyou" cols="60" rows="2" class="code" style="font-size:18px; font-weight:bold; background-color:white;" readonly="readonly"><?php echo htmlentities($checkout); ?></textarea><br />
         <input style="text-align:center;" type="submit" class="button-secondary" onclick="document.getElementById('miembropress_thankyou').select(); return false;" value="Select All" />
         </p>
      </blockquote>

      <p>Click <b>&quot;Save Product&quot;</b> to finish creating your product.</p>

      <blockquote>
      <p><i>If you want to create a recurring product in the steps above (fixed-term or continuity as opposed to a single-payment product) just choose &quot;Recurring Digital Product&quot; for the Product Type.</i></p>

      <p><i>The only difference with this setting is that you'll have extra text boxes to fill out to define the Initial Product Price (first payment amount), Recurring Product Price (amount charged each subsequent billing), Rebill Frequency (re-bill weekly or monthly), and Subscription Duration (how many total payments, such as 5 payments, or unlimited).</i></p>
      </blockquote>

      <h3><b style="background-color:yellow;">Step 4:</b> Match Clickbank Item IDs to Membership Levels</h3>

      <p>View your products in the Clickbank control panel by going to Settings, My Products. On the left-most column of each product you sell, you'll see an &quot;Item&quot; column. Copy the item of the product you created, and paste it below next to the membership level you want to grant access to, after that person purchases that item.</p>

      <p>

      <blockquote>
         <p><table class="widefat" style="width:800px;">
         <thead>
         <tr>
            <th scope="col" style="width:250px;">Level to Provide Access To...</th>
            <th scope="col" style="width:150px; text-align:center;">Clickbank Item</th>
            <th scope="col" style="text-align:left;">Clickbank Payment Link</th>
         </tr>
         </thead>

         <?php foreach ($levels as $level): ?>
         <?php $item = "";
    if (isset($items[$level->ID])) {
        $item = $items[$level->ID];
    } else {
        $item = "";
    }
    if ($item) {
        $link = "http://" . $item . "." . $account . ".pay.clickbank.net/?sku=" . $level->ID;
    } else {
        $link = '';
    } ?>
         <tr>
            <td><?php echo htmlentities($level->level_name); ?></td>
            <td style="text-align:center;"><input type="text" size="3" name="miembropress_clickbank_item[<?php echo htmlentities($level->ID); ?>]" value="<?php echo $item; ?>" /> </td>
            <td>
               <?php if ($link): ?>
               <nobr><a target="_blank" href="<?php echo htmlentities($link); ?>"><?php echo htmlentities($link); ?></a></nobr>
               <?php else: ?>
               &nbsp;
               <?php endif; ?>
            </td>
         <?php endforeach; ?>
         </table></p>
      </blockquote>

      <p><input type="submit" class="button" value="Save Clickbank ID's" /></p>

      <h3><b style="background-color:yellow;">Step 5:</b> Test Your Payment Button</h3>

      <p>In your Clickbank account, go to Settings, My Site. Scroll down to the &quot;Testing Your Products&quot; section. Click the &quot;Edit&quot; link on the site. On the page that loads, click &quot;Generate New Card Number.&quot;</p>

      <p>Click on the appropriate &quot;pay&quot; link above (in Step 4) and check out using the test credit card numnber, expiration date, and validation code provided to you by Clickbank.</p>

      <h3><b style="background-color:yellow;">Step 5:</b> Copy the Buy Button Code from Clickbank and Paste Into Your Sales Letter</h3>

      <blockquote>
      <p>The &quot;Clickbank Payment Link&quot; in the table above (Step 3) is the link you'll place on your website to accept payments.</p>

      <p>Grab the HTML code to place on your web pages. We use <a target="_blank" href="http://www.papertemplate.com">Paper Template</a> to present sales letters to customers.</p>
      </blockquote>

      <p><input type="submit" class="button-primary" value="Save Settings" /></p>
      </div>
      <?php
 }

	function verify() {
		global $miembropress;
		$info = null;
		MemberGenius::clearCache();
		if (isset($_POST["ctransaction"]) && $_POST["ctransaction"] == "TEST" && $_POST["ccustemail"] == "testuser@somesite.com") {
			ob_end_clean();
			header("HTTP/1.1 200 OK");
			die();
		}
		if (isset($_GET["cbreceipt"])) {
			$info = $this->verifyPDT($_GET, $miembropress->model->setting("clickbank_token"));
		} elseif (isset($_POST["caffitid"])) {
			$info = $this->verifyIPN($_POST, $miembropress->model->setting("clickbank_token"));
		}
		if (!$info || count($info) == 0) { return false; }
		$items = $miembropress->model->setting("clickbank_items");
		if (!is_array($items)) { $items = array(); }
		if (isset($info["ccustname"]) && isset($info["ccustemail"]) && isset($info["cproditem"]) && isset($info["ctransreceipt"])) {
			parse_str($info["cvendthru"], $cvendthru);
			if (isset($cvendthru["sku"])) { $sku = $cvendthru["sku"]; }
			else { $level = array_search($info["cproditem"], $items); }
			list($firstname, $lastname) = preg_split('@ @', trim($info["ccustname"]), 2);
			$result = array( "firstname" => $firstname, "lastname" => $lastname, "email" => $info["ccustemail"], "username" => $firstname." ".$lastname, "level" => $level, "transaction" => $info["ctransreceipt"], "action" => "register" );
		} elseif (isset($info["cbreceipt"]) && isset($info["cname"]) && isset($info["cemail"])) {
			list($firstname, $lastname) = preg_split('@ @', trim($info["cname"]), 2);
			if (isset($info["sku"])) {
				$level = @intval($info["sku"]);
			} else {
				$level = array_search($info["item"], $items);
			}
			if (!$level) { $result = array(); }
			else { $result = array( "firstname" => $firstname, "lastname" => $lastname, "email" => $info["cemail"], "username" => $firstname." ".$lastname, "level" => $level, "transaction" => $info["cbreceipt"], "action" => "register" );}
		} else { $result = array(); }
		$status = null;
		if (isset($info["ctransaction"])) { $status = $info["ctransaction"]; }
		if ($status == "RFND" || $status == "CGBK" || $status == "INSF" || $status == "CANCEL-REBILL") { $result["action"] = "cancel"; }
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
			if (isset($post[$field])) {
				$vars[$field] = $post[$field];
			}
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
		$subject = "[IPN] Clickbank Notification Log";
		$price = "";
		if (isset($_POST["ctransamount"])) { $price = floatval($_POST["ctransamount"]/100); } if ($price == "") { $thePrice = ""; } else { $thePrice = "" . number_format($price, 2); } if ($_POST["ctransaction"] == "RFND" || $_POST["ctransaction"] == "CGBK" || $_POST["ctransaction"] == "INSF") { $subject .= " (REFUND".$thePrice.")"; } elseif ($_POST["ctransaction"] == "SALE") { $subject .= " (SALE".$thePrice.")"; } elseif ($_POST["ctransaction"] == "BILL") { $subject .= " (REBILL".$thePrice.")"; } elseif ($_POST["ctransaction"] == "CANCEL-REBILL") { $subject .= " (CANCELLATION".$thePrice.")"; } $paymentDate = 0; if (isset($_POST["ctranstime"])) { $paymentDate = intval($_POST["ctranstime"]); } else { $paymentDate = time(); } $message = "A new sale/refund was processe for customer ".htmlentities($_POST["ccustemail"]); if ($paymentDate) { $message .= " on ".date("m/d/".chr(89), $paymentDate)." at ".date("g:i A e", $paymentDate); } $message .= "Details: "; foreach ($_POST as $key => $value) { $message .= $key.': '.$value." "; } $this->email($subject, $message);
	}
}

?>