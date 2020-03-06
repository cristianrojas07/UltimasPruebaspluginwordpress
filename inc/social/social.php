<?php

require_once('handler/social-handler.php');

class MemberGeniusSocial {
	var $handlers;
	function __construct() {
		$this->handlers = array( "google" => new MemberGeniusSocialHandlerGoogle, "facebook" => new MemberGeniusSocialHandlerFacebook );
	}

	function registration() {
		foreach ($this->handlers as $handler) {
			$handler->registration();
		}
	}
}

?>