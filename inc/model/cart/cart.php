<?php

require_once('clickbank/clickbank.php');
require_once('generic/generic.php');
require_once('hotmart/hotmart.php');
require_once('jvz/jvz.php');
require_once('paypal/paypal.php');
require_once('warrior/warrior.php');

class MiembroPressCart {
	private $secret;
	public function instructions() { }
	public function validate() { }
	public function email($subject, $message) {
		add_filter( 'wp_mail_from_name', array(&$this, "fromName"));
		wp_mail(get_option("admin_email"), $subject, $message);
		remove_filter( 'wp_mail_content_type', array(&$this, "fromName"));
	}

	public function fromName() {
		return get_option("name");
	}
}

?>