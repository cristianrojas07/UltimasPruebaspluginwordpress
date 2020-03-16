<?php

class MiembroPressCartWarrior extends MiembroPressCart {
	function instructions() {
		global $miembropress;
		$levels = $miembropress->model->getLevels();
		$secret = $miembropress->model->setting("warriorplus_secret");
		$checkout = $miembropress->model->signupURL($secret);
		if (isset($_POST["warriorplus_token"])) {
			$miembropress->model->setting("warriorplus_token", trim(stripslashes($_POST["warriorplus_token"])));
		}

		if (isset($_POST["miembropress_warriorplus_item"]) && is_array($_POST["miembropress_warriorplus_item"])) {
			$items = array();
			foreach ($_POST["miembropress_warriorplus_item"] as $key => $value) {
				$items[$key] = $value;
			}
			$miembropress->model->setting("warriorplus_items", $items);
		}
		$items = $miembropress->model->setting("warriorplus_items");
		if (!is_array($items)) { $items = array(); }
		?>
		<h3>WarriorPlus Payment</h3>
		<h3>Step 1: Signup with Warrior Plus</h3>
		<p>Register an account at <a target="_blank" href="https://warriorplus.com">Warrior Plus</a>.
		<h3>Step 2: Create New Product</h3>
		<p>After registering and logging into <a target="_blank" href="https://warriorplus.com">Warrior Plus</a>, go to <code>Products</code>, <code>New Product.</code></p>
		<ul style="list-style:disc; padding-left:25px;">
			<li>Product Name: <code>Name of the Product</code></li>
			<li>Delivery URL: <code><?php echo home_url("/login"); ?></code></li>
		</ul>
		<p>Scroll down to <code>Advanced Integration.</code></p>
		<ul style="list-style:disc; padding-left:25px;">
			<li>Software: <code>Wishlist Member</code></li>
			<li>Wishlist URL: <code><?php echo home_url(); ?></code></li>
			<li>API Key: <code><?php echo $miembropress->model->setting("api_key"); ?></code></li>
			<li>Membership SKU: <i>(check table below)</i></li>
			<li>WL Post URL: <code><?php echo home_url(); ?></code></li>
			<li>Notification URL: <code><?php echo $checkout; ?></code></li>
			<li>Key Generation URL: <i>(leave blank)</i></li>
			<li>Send IPN To Delivery URL: <code>ON</code></li>
		</ul>
		<h3>Step 3: Set Security Key</h3>
		<p>In WarriorPlus, click on your username on the top right, and in the dropdown, choose <code>Account Settings</code>.</p>
		<p>Then click <code>Security Key</code> and if you don't have a security key set, click the link that says <code>Click here to create a Warrior+Plus Security key.</code></p>
		<p>Copy and paste your security key from WarriorPlus, enter it below, and click Save.</p>
		<p><input type="text" name="warriorplus_token" value="<?php echo htmlentities($miembropress->model->setting("warriorplus_token")); ?>" size="35" /><input type="submit" class="button-primary button-activate" value="Save WarriorPlus Security Key" /></p>
		<h3>Step 4: Specify Membership SKU &amp; Post URL</h3>
		<p>In the table below, choose which membership level you're granting access to. Based on that, copy the <code>Membership SKU</code> and <code>WL Post URL</code> into WarriorPlus and click <code>Save</code>.
		<h3>Step 5: Copy Product Code from WarriorPlus</h3>
		<p>Finally, copy the Product Code (from the top of the WarriorPlus page, above &quot;Product Name&quot;) and paste it in the table below to match the corresponding level that your product relates to.<p>
		<p>Then click <code>Save WarriorPlus Product Codes.</code> This is necessary to handle refunds.</p>
		<blockquote>
			<p><table class="widefat" style="width:800px;">
					<thead>
						<tr>
							<th scope="col" style="width:250px;">Level to Provide Access To...</th>
							<th scope="col" style="width:150px; text-align:center;">Membership SKU</th>
							<th scope="col" style="width:300px;  text-align:center;">Product Code<br /><small>(from Warrior Plus, i.e. <code>wso_p1abc1</code>)</small></th>
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
							<td style="text-align:center;"><?php echo htmlentities($level->ID); ?></td>
							<td style="text-align:center;"><input type="text" size="10" name="miembropress_warriorplus_item[<?php echo htmlentities($level->ID); ?>]" value="<?php echo $item; ?>" /> </td>
					<?php endforeach; ?>
				</table>
			</p>
		</blockquote>
		<p><input type="submit" class="button-primary button-activate" value="Save WarriorPlus Product Codes" /></p>
      <?php
	}

	function verify() {
		global $miembropress;
		$info = null;
		MiembroPress::clearCache();
		if (!isset($_POST["RECEIVERBUSINESS"])) { return; }
		$token = $miembropress->model->setting("warriorplus_token");
		if (!$token) { return; }
		if (!isset($_POST["WP_ACTION"]) || !isset($_POST["WP_SECURITYKEY"]) || !isset($_POST["WP_ITEM_NUMBER"]) || !isset($_POST["WP_ITEM_NUMBER"]) || !isset($_POST["WP_SECURITYKEY"])) { return; }
		if ($_POST["WP_SECURITYKEY"] != $token) { return; }
		$item = $_POST["WP_ITEM_NUMBER"];
		$items = $miembropress->model->setting("warriorplus_items");
		$level = array_search($item, $items);
		if (!$level) { return; }
		$result = array( "firstname" => $_POST["FIRSTNAME"], "lastname" => $_POST["LASTNAME"], "email" => $_POST["EMAIL"], "username" => $_POST["EMAIL"], "level" => $level, "transaction" => $info["WP_TXNID"] );
		$action = $_POST["WP_ACTION"];
		if ($action == "refund") {
			$result["action"] = "cancel";
			return $result;
		}
		return null;
	}
}

?>