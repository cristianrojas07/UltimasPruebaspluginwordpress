<?php

require_once('handler/social-handler.php');

class MiembroPressSocial {
	var $handlers;
	function __construct() {
		$this->handlers = array( "google" => new MiembroPressSocialHandlerGoogle, "facebook" => new MiembroPressSocialHandlerFacebook );
	}

	function registration() {
		foreach ($this->handlers as $handler) {
			$handler->registration();
		}
	}
}

?>