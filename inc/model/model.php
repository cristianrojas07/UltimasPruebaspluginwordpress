<?php

require_once('cart/cart.php');

class MiembroPressModel {

	private $levelTable;
	private $levelSettingsTable;
	private $userTable;
	private $contentTable;
	private $settingsTable;
	private $tempTable;
	function __construct() {
		global $wpdb;
		$prefix = $wpdb->prefix . "miembropress_";
		$this->levelTable = $prefix."levels";
		$this->levelSettingsTable = $prefix."level_settings";
		$this->userTable = $prefix."users";
		$this->userSettingsTable = $prefix."user_settings";
		$this->contentTable = $prefix."content";
		$this->settingsTable = $prefix."settings";
		$this->tempTable = $prefix."temps";
		add_action( 'pre_user_query', array(&$this, "preUserQuery"));
		add_action( 'before_delete_post', array(&$this, 'onDeletePost'));
		add_action( 'delete_user', array(&$this, 'onDeleteUser'));

		if (!$this->setting("hotmart_secret")) {
			$this->setting("hotmart_secret", $this->hash());
		}

		if (!$this->setting("generic_secret")) {
			$this->setting("generic_secret", $this->hash());
		}
		if (!$this->setting("paypal_secret")) {
			$this->setting("paypal_secret", $this->hash());
		}
		if (!$this->setting("jvz_secret")) {
			$this->setting("jvz_secret", $this->hash());
		}
		if (!$this->setting("clickbank_secret")) {
			$this->setting("clickbank_secret", $this->hash());
		}
		if (!$this->setting("warriorplus_secret")) {
			$this->setting("warriorplus_secret", $this->hash());
		}
		if (!$this->setting("jvz_token")) {
			$this->setting("jvz_token", rand(10000000, 99999999));
		}
		if (!$this->setting("api_key")) {
			$this->setting("api_key", $this->hash(16));
		}
		if ($this->setting("attribution") === null) {
			$this->setting("attribution", 1);
		}
		if ($this->setting("emailattribution") === null) {
			$this->setting("emailattribution", 1);
		}
		if ($this->setting("affiliate") === null) {
			$this->setting("affiliate", "https://miembropress.com");
		}
		add_action( 'miembropress_process', array(&$this, 'process'));
		add_action( 'wp', array(&$this, "setupSchedule"));
	}

	function cleanup() {
		if ($this->countLevels() == 0) {
			$this->createLevel("Full", true);
		}
	}

	public function setupSchedule() {
		if (!wp_next_scheduled('miembropress_process')) {
			wp_schedule_event(time(), 'hourly', 'miembropress_process');
		}
	}

	public function getLevelTable(){
		return $this->levelTable;
	}

	public function process($now=null) {
		if ($now == null) {
			$now = time();
		}
		$this->processExpiration($now);
		$this->processUpgrade($now);
	}

	public function processUpgrade($now=null, $user=null) {
		if ($now == null) {
			$now = time();
		}
		set_time_limit(0);
		ignore_user_abort(true);
		$levelInfo = array();
		$levels = $this->getLevels();
		foreach ($levels as $level) {
			$delay = @intval($this->levelSetting($level->ID, "delay"));
			$dateDelay = @intval($this->levelSetting($level->ID, "dateDelay"));
			$upgrade = null;
			if ($add = $this->levelSetting($level->ID, "add")) {
				$method = "add";
				$upgrade = $add;
			}
			if ($move = $this->levelSetting($level->ID, "move")) {
				$method = "move"; $upgrade = $move;
			}
			$expiration = $level->level_expiration;
			$levelInfo[$level->ID] = array( "add" => $add, "move" => $move, "expiration" => $expiration, "upgrade" => $upgrade, "delay" => $delay, "dateDelay" => $dateDelay );
		}
		if ($user) {
			$members = array($user);
		} else {
			$members = $this->getMembers("cron=$now");
		}

		foreach ($members as $member) {
			if (is_numeric($member)) {
				$memberID = $member;
			} elseif (isset($member->ID)) {
				$memberID = $member->ID;
			} else {
				continue;
			}
			$userLevels = $this->getLevelInfo($memberID);
			foreach ($userLevels as $levelID => $level) {
				if ($level->level_status != "A") {
					continue;
				}
				$daysOnLevel = 0;
				$expiration = $levelInfo[$levelID]["expiration"];
				$upgrade = $levelInfo[$levelID]["upgrade"];
				$delay = $levelInfo[$levelID]["delay"];
				$dateDelay = $levelInfo[$levelID]["dateDelay"];
				$add = $levelInfo[$levelID]["add"];
				$move = $levelInfo[$levelID]["move"];
				if ($expiration || $add || $move) {
					$daysOnLevel = $this->getDaysOnLevel($memberID, $levelID, $now);
				}
				if ($levelInfo[$levelID]["add"]) {
					$add = $levelInfo[$levelID]["add"];
				} elseif ($levelInfo[$levelID]["move"]) {
					$move = $levelInfo[$levelID]["move"];
				}
				if ($expiration && $expiration == $daysOnLevel) {
					$this->cancel($memberID, $levelID);
				}
				if ($add) {
					if ($dateDelay) {
						if ($dateOffset - $dateDelay < 86400) {
							$this->add($memberID, $add, $level->level_txn, $dateDelay);
						}
					} elseif ($delay == $daysOnLevel) {
						$this->add($memberID, $add, $level->level_txn, $now);
					}
				} elseif ($move) {
					if ($delay == $daysOnLevel) {
						$this->move($memberID, $move);
					}
				}
			}
			$this->userSetting($memberID, "cronLast", $now);
		}
	}

	public function processExpiration($now=null) {
		if ($now == null) {
			$now = time();
		}
		set_time_limit(0);
		ignore_user_abort(true);
		$levels = $this->getLevels();
		foreach ($levels as $level) {
			if ($level->level_expiration == 0) {
				continue;
			}
			$expiredDay = $now - ($level->level_expiration * 86400);
			$dateRange = 86400-1800;
			$dateStart = $expiredDay - $dateRange;
			$dateEnd = $expiredDay;
			$members = $this->getMembers("level=".$level->ID."&level_status=A&level_after=".$dateStart."&level_before=".$dateEnd);
			foreach ($members as $member) {
				$lastRun = @intval($this->userSetting($member->ID, "last_expiration"));
				if ($lastRun < $now && $now-$lastRun < 86400) {
					continue;
				}
				$this->userSetting($member->ID, "last_expiration", $now);
				$this->cancel($member->ID, $member->level_id);
			}
		}
	}

	public function signupURL($hash="", $escaped=false) {
		if ($escaped) {
			return site_url("?".urlencode("/miembropress/").$hash);
		}
		return site_url("index.php?/miembropress/".$hash);
	}

	public function hash($length=6) {
		$collision = true;
		while ($collision) {
			$dictionary = array_merge(range('0','9'),range('a','z'),range('A','Z'));
			if (!is_int($length) || @intval($length) < 6) {
				$length = 6;
			}
			$result = "";
			for ($i=0;$i<$length;$i++) {
				$result .= $dictionary[array_rand($dictionary)];
			}
			$collision = $this->hashCollision($result);
		}
		return $result;
	}

	private function hashCollision($hash) {
		$payment = $this->getPaymentFromHash($hash);
		$level = $this->getLevelFromHash($hash);
		if ($payment || $level) {
			return true;
		}
		return false;
	}

	public function sku() {
		$collision = true;
		while ($collision) {
			$sku = rand(1000000000, 1999999999);
			$collision = $this->skuCollision($sku);
		} return $sku;
	}

	private function skuCollision($sku) {
		return $this->getLevel($sku);
	}

	function uninstall() {
		if (!function_exists("wp_delete_post")) {
			return;
		}

		if ($placeholder = get_page_by_path("miembropress")) {
			wp_delete_post($placeholder->ID);
		}
	}

	function getPluginVersion() {
		$plugin_folder = @get_plugins( '/' . plugin_basename( dirname( 'miembro-press/miembro-press.php' ) ) );

		$plugin_file = basename( ( 'miembro-press/miembro-press.php' ) );
		$plugin_version = $plugin_folder[$plugin_file]['Version'];
		return $plugin_version;
	}

	function maybeInstall() {
		if ($this->getPluginVersion() != $this->setting("version")) {
			$this->install();
		}
	}

	function install() {
		global $wpdb;
		require_once(constant("ABSPATH") . 'wp-admin/includes/upgrade.php');
		if ($wpdb->get_var("SHOW TABLES LIKE '" . $this->levelTable . "'") != $this->levelTable) {
			dbDelta("CREATE TABLE IF NOT EXISTS `".$this->levelTable."` (`ID` int(11) NOT NULL AUTO_INCREMENT, `level_name` varchar(64) NOT NULL, `level_hash` varchar(6) NOT NULL, `level_all` TIN".chr(89)."INT(1) NOT NULL DEFAULT '1', `gdpr_active` TINYINT(1) NOT NULL DEFAULT '0', `gdpr_url` varchar(254) NOT NULL DEFAULT 'https://miembropress.com', `gdpr_text` varchar(254) NOT NULL DEFAULT 'Acepto los términos y condiciones', `gdpr_color` varchar(10) NOT NULL DEFAULT '#333', `gdpr_size` int(10) NOT NULL DEFAULT '14', `level_comments` tinyint(1) NOT NULL DEFAULT '1', `level_page_register` int(11) DEFAULT NULL, `level_page_login` int(11) DEFAULT NULL, `level_expiration` int(11) DEFAULT NULL, PRIMAR".chr(89)." KE".chr(89)." (`ID`), UNIQUE KE".chr(89)." `level_hash` (`level_hash`), UNIQUE KE".chr(89)." `level_name` (`level_name`), KE".chr(89)." `level_expiration` (`level_expiration`)) DEFAULT CHARSET=utf8;"); $this->cleanup(); } if ($wpdb->get_var("SHOW TABLES LIKE '" . $this->levelSettingsTable . "'") != $this->levelSettingsTable) { dbDelta("CREATE TABLE IF NOT EXISTS `".$this->levelSettingsTable."` (`ID` int(11) NOT NULL AUTO_INCREMENT, `level_id` int(11) NOT NULL, `level_key` VARCHAR(255) NOT NULL, `level_value` TEXT, PRIMAR".chr(89)." KE".chr(89)." (`ID`), UNIQUE KE".chr(89)." `level_key` (`level_id`,`level_key`), KE".chr(89)." `level_id` (`level_id`)) DEFAULT CHARSET=utf8;"); } if ($wpdb->get_var("SHOW TABLES LIKE '" . $this->userTable . "'") != $this->userTable) { dbDelta("CREATE TABLE IF NOT EXISTS `".$this->userTable."` (`ID` int(11) NOT NULL AUTO_INCREMENT, `user_id` int(11) NOT NULL, `level_id` int(11) NOT NULL, `level_status` char(1) NOT NULL DEFAULT 'A', `level_txn` varchar(64) DEFAULT NULL, `level_subscribed` tinyint(1) NOT NULL DEFAULT '0', `level_date` datetime DEFAULT NULL, PRIMAR".chr(89)." KE".chr(89)." (`ID`), UNIQUE KE".chr(89)." `userlevel_id` (`user_id`,`level_id`), KE".chr(89)." `user_id` (`user_id`), KE".chr(89)." `level_id` (`level_id`), KE".chr(89)." `level_status` (`level_status`), KE".chr(89)." `level_txn` (`level_txn`), KE".chr(89)." `level_subscribed` (`level_subscribed`), KE".chr(89)." `level_date` (`level_date`)) DEFAULT CHARSET=utf8;"); } if ($wpdb->get_var("SHOW TABLES LIKE '" . $this->userSettingsTable . "'") != $this->userSettingsTable) { dbDelta("CREATE TABLE IF NOT EXISTS `".$this->userSettingsTable."` (`ID` int(11) NOT NULL AUTO_INCREMENT, `user_id` int(11) NOT NULL, `user_key` VARCHAR(255) NOT NULL, `user_value` TEXT, PRIMAR".chr(89)." KE".chr(89)." (`ID`), UNIQUE KE".chr(89)." `user_key` (`user_id`,`user_key`), KE".chr(89)." `user_id` (`user_id`), FULLTEXT KE".chr(89)." `user_value` (`user_value`)) DEFAULT CHARSET=utf8;"); } if ($wpdb->get_var("SHOW TABLES LIKE '" . $this->contentTable . "'") != $this->contentTable) { dbDelta("CREATE TABLE IF NOT EXISTS `".$this->contentTable."` (`ID` int(11) NOT NULL AUTO_INCREMENT, `level_id` int(11) NOT NULL, `post_id` int(11) NOT NULL, PRIMAR".chr(89)." KE".chr(89)." (`ID`), UNIQUE KE".chr(89)." `postlevel_id` (`level_id`,`post_id`), KE".chr(89)." `post_id` (`post_id`), KE".chr(89)." `level_id` (`level_id`)) DEFAULT CHARSET=utf8;"); $this->protectAllPosts(); } if ($wpdb->get_var("SHOW TABLES LIKE '" . $this->settingsTable . "'") != $this->settingsTable) { dbDelta("CREATE TABLE IF NOT EXISTS ".$this->settingsTable." (`ID` int(11) NOT NULL AUTO_INCREMENT, `option_name` varchar(64) NOT NULL, `option_value` longtext NOT NULL, PRIMAR".chr(89)." KE".chr(89)." (`ID`), UNIQUE KE".chr(89)." `option_name` (`option_name`)) DEFAULT CHARSET=utf8;"); } if ($wpdb->get_var("SHOW TABLES LIKE '" . $this->tempTable . "'") != $this->tempTable) {
			dbDelta("CREATE TABLE IF NOT EXISTS ".$this->tempTable." (`ID` int(11) NOT NULL AUTO_INCREMENT, `txn_id` varchar(64) NOT NULL, `level_id` int(11) NOT NULL DEFAULT '0', `level_status` char(1) NOT NULL DEFAULT 'A', `temp_metadata` longtext , `temp_created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMAR".chr(89)." KE".chr(89)." (`ID`), UNIQUE KE".chr(89)." `txn_id` (`txn_id`), KE".chr(89)." `created` (`temp_created`)) DEFAULT CHARSET=utf8;");
		}

		$this->setting("version", $this->getPluginVersion());
	}

	public function setting() {
		global $wpdb;
		global $miembropress;
		$list = null;
		$value = null;
		$args = func_get_args();
		if (count($args) >= 2) {
			@list($name, $value) = $args;
		} else {
			@list($name) = $args;
		}
		if (!is_array($args) || count($args) == 0) { return; }
		$return = null;
		if (count($args) == 1) {
			$name = reset($args);
			$return = $wpdb->get_var("SELECT option_value FROM ".$this->settingsTable." WHERE option_name = '".esc_sql($name)."'");
			if (is_serialized($return)) {
				$maybeUnserialize = @unserialize($return);
			} else {
				$maybeUnserialize = $return;
			}
			return $maybeUnserialize;
		} elseif (count($args) > 1 && $value === null) {
			MiembroPress::clearCache();
			$wpdb->query("DELETE FROM ".$this->settingsTable." WHERE option_name = '".esc_sql($name)."'");
		} else {
			MiembroPress::clearCache();
			if (is_array($value) || is_object($value)) {
				$value = serialize($value);
			}
			$wpdb->query("INSERT IGNORE INTO ".$this->settingsTable." SET option_name = '".esc_sql($name)."', option_value='".esc_sql(stripslashes($value))."'");
			$wpdb->query("UPDATE ".$this->settingsTable." SET option_value='".esc_sql($value)."' WHERE option_name = '".esc_sql(stripslashes($name))."'");
			$return = $value;
		}
		if ($return == null && $value == null) {
			if ($name == "order") {
				return "descending";
			}
		}
		return $return;
	}


	public function levelSetting() {
		global $wpdb;
		global $miembropress;
		$list = null;
		$value = null;
		$args = func_get_args();
		@list($levelID, $levelKey, $levelValue) = $args;
		if (!is_array($args) || count($args) == 0) { return; }
		$return = null;
		if (count($args) == 1) {
			$return = $wpdb->get_results("SELECT level_key, level_value FROM ".$this->levelSettingsTable ." WHERE level_id = ".intval($levelID));
			if (!$return) {
				return array();
			}
			$results = array();
			foreach ($return as $row) {
				$key = $row["level_key"];
				$value = $row["level_value"];
				$results[$key] = $value;
			}
			return $results;
		}
		if (count($args) == 2) {
			$return = $wpdb->get_var("SELECT level_value FROM ".$this->levelSettingsTable ." WHERE level_id = ".intval($levelID). " AND level_key = '".esc_sql($levelKey)."'");
			if ($return == serialize(false) || @unserialize($return) !== false) {
				$maybeUnserialize = @unserialize($return);
			} else {
				$maybeUnserialize = $return;
			}
			return stripslashes($maybeUnserialize);
		} elseif (count($args) == 3 && $levelValue === null) {
			MiembroPress::clearCache();
			$wpdb->query("DELETE FROM ".$this->levelSettingsTable." WHERE level_id = ".intval($levelID)." AND level_key = '".esc_sql($levelKey)."'");
		} else {
			MiembroPress::clearCache();
			if (is_array($value) || is_object($value)) {
				$value = serialize($value);
			}
			$wpdb->query("INSERT IGNORE INTO ".$this->levelSettingsTable." SET level_id = ".intval($levelID).", level_key = '".esc_sql($levelKey)."', level_value='".esc_sql(stripslashes($levelValue))."'"); $wpdb->query("UPDATE IGNORE ".$this->levelSettingsTable." SET level_id=".intval($levelID).", level_value = '".esc_sql($levelValue)."' WHERE level_key = '".esc_sql(stripslashes($levelKey))."'");
		}
		return $return;
	}

	public function userSearch($key, $value) {
		global $wpdb;
		global $miembropress;
		$userID = $wpdb->get_var("SELECT user_id FROM ".$this->userSettingsTable." WHERE user_key = '".esc_sql($key)."' AND user_value = '".esc_sql($value)."' LIMIT 1");
		if ($userID && is_numeric($userID)) {
			return get_user_by('ID', $userID);
		}
		return null;
	}

	public function userSetting() {
		global $wpdb;
		global $miembropress;
		$list = null;
		$value = null;
		$args = func_get_args();
		$userID = 0;
		$userKey = "";
		$userValue = "";
		if (count($args) >= 3) {
			@list($userID, $userKey, $userValue) = $args;
		} elseif (count($args) == 2) {
			@list($userID, $userKey) = $args;
		} elseif (count($args) == 1) {
			@list($userID) = $args;
		}

		if (!is_array($args) || count($args) == 0) {
			return;
		}

		$return = null;
		if (count($args) == 2) {
			$return = $wpdb->get_var("SELECT user_value FROM ".$this->userSettingsTable ." WHERE user_id = ".intval($userID). " AND user_key = '".esc_sql($userKey)."'");
			if (strpos($return, '{') !== false) {
				$maybeUnserialize = @unserialize($return);
			} else {
				$maybeUnserialize = $return;
			}
			return $maybeUnserialize;
		} elseif (count($args) == 2 && $userValue === null) {
			MiembroPress::clearCache();
			$wpdb->query("DELETE FROM ".$this->userSettingsTable." WHERE user_id = ".intval($userID)." AND user_key = '".esc_sql($userKey)."'");
		} else {
			MiembroPress::clearCache();
			if (is_array($userValue) || is_object($userValue)) {
				$userValue = serialize($userValue);
			}
			$wpdb->query("INSERT IGNORE INTO ".$this->userSettingsTable." SET user_id = ".intval($userID).", user_key = '".esc_sql($userKey)."', user_value='".esc_sql(stripslashes($userValue))."'");
			$wpdb->query("UPDATE IGNORE ".$this->userSettingsTable." SET user_id=".intval($userID).", user_value = '".esc_sql($userValue)."' WHERE user_key = '".esc_sql(stripslashes($userKey))."'");
		}
		return $return;
	}

	function onDeleteUser($userID) {
		global $wpdb;
		global $miembropress;
		MiembroPress::clearCache();
		$user = intval($userID);
		$wpdb->query("DELETE FROM ".$this->userTable." WHERE user_id = $user");
		$wpdb->query("DELETE FROM ".$this->userSettingsTable." WHERE user_id = $user");
	}

	function onDeletePost($postID) {
		global $wpdb;
		global $miembropress;
		MiembroPress::clearCache();
		$post = intval($postID);
		$wpdb->query("DELETE FROM ".$this->contentTable." WHERE post_id = $post");
	}

	function getLevels($user=null) {
		global $wpdb;
		$levels = array();
	    $query = "SELECT {$this->levelTable}.*, COUNT(u1.ID) as active, '0' as canceled FROM {$this->levelTable} LEFT JOIN {$this->userTable} u1 ON ({$this->levelTable}.ID = u1.level_id AND u1.level_status = 'A') GROUP By {$this->levelTable}.ID ORDER By level_name";
		$levelQuery = $wpdb->get_results( $query );
		foreach ($levelQuery as $levelKey => $l) {
			$id = $l->ID;
			$levels[$id] = $l;
			unset($levelQuery[$levelKey]);
		}
		$cancelQuery = $wpdb->get_results("SELECT COUNT(ID) AS total, level_id FROM ".$this->userTable." WHERE level_status = 'C' GROUP By level_id");
		$canceled = array();
		foreach ($cancelQuery as $c) {
			$cId = $c->level_id;
			if (!isset($levels[$cId])) { continue; }
			$levels[$cId]->canceled = $c->total;
		}
		$settingsQuery = $wpdb->get_results("SELECT level_key, level_value FROM ".$this->levelSettingsTable);
		if ($levels && is_array($levels) && count($levels) > 0) {
			uasort($levels, array(&$this, "levelSort"));
			return $levels;
		}
		return array();
	}

	function levelSort($a, $b) {
		return strnatcmp($a->level_name, $b->level_name);
	}

	function getLevel($levelID) {
		global $wpdb;
		$level = intval($levelID);
		return $wpdb->get_row("SELECT * FROM ".$this->levelTable." WHERE ID = $level");
	}

	function getPaymentFromHash($hash) {
		global $wpdb;
		$hash = preg_replace('@[^a-z0-9]@si', '', $hash);
		if (empty($hash)) {
			return;
		}
		if ($hash == $this->setting("paypal_secret")) {
			return new MiembroPressCartPayPal();
		} elseif ($hash == $this->setting("jvz_secret")) {
			return new MiembroPressCartJVZ();
		} elseif ($hash == $this->setting("clickbank_secret")) {
			return new MiembroPressCartClickbank();
		} elseif ($hash == $this->setting("warriorplus_secret")) {
			return new MiembroPressCartWarrior();
		} elseif ($hash == $this->setting("hotmart_secret")) {
			return new MiembroPressCartHotmart();
		}
	}

	function getLevelFromHash($hash) {
		global $wpdb;
		$hash = preg_replace('@[^a-z0-9]@si', '', $hash);
		return $wpdb->get_row("SELECT * FROM ".$this->levelTable." WHERE level_hash = '".esc_sql($hash)."'");
	}

	function preUserQuery($q) {
		global $wpdb;
		$custom = false;
		if (isset($q->query_vars["miembropress"]) && $q->query_vars["miembropress"] == "1") {
			$custom = true;
		}
		if (isset($q->query_vars["orderby"]) && $q->query_vars["orderby"] == "lastlogin") {
			$orderAddition = $this->userSettingsTable.".user_value DESC";
            $q->query_orderby = "ORDER BY " .$orderAddition;
		}
		if (isset($q->query_vars["cron"])) {
			$custom = true;
			$cron_timestamp = @intval($q->query_vars["cron"]);
			if ($cron_timestamp <= 1) {
				$cron_timestamp = time();
			}
			$cron_deadline = $cron_timestamp-86400+1800;
			$q->query_fields .= ", user_value";
			$q->query_where = " LEFT JOIN ".$this->userSettingsTable." ON (".$wpdb->users.".ID = ".$this->userSettingsTable.".user_id AND user_key = 'cronLast') " . $q->query_where . " AND (user_value IS NULL OR user_value < ".$cron_deadline.")";
		}

		if (isset($q->query_vars["orderby"]) && $q->query_vars["orderby"] == "lastlogin") {
			$custom = true;
			$q->query_where = " INNER JOIN ".$this->userSettingsTable." ON (".$wpdb->users.".ID = ".$this->userSettingsTable.".user_id AND user_key='loginLastTime') ". $q->query_where; $q->query_fields .= ", user_value ";
		}

		if (isset($q->query_vars["s"]) && !empty($q->query_vars["s"])) {
			$custom = true;
			if (isset($q->query_vars["level_status"])) {
				unset($q->query_vars["level_status"]);
			}
			if (isset($q->query_vars["level"])) {
				unset($q->query_vars["level"]);
			}
			if (isset($q->query_vars["levels"])) {
				unset($q->query_vars["levels"]);
			}
			$q->query_where = " LEFT JOIN ".$this->userTable." ON (".$wpdb->users.".ID = ".$this->userTable.".user_id AND meta_key IN ('first_name', 'last_name')) " . $q->query_where;
			$q->query_where = " LEFT JOIN ".$wpdb->usermeta." ON (ID = ".$wpdb->usermeta.".user_id) ". $q->query_where;
			if (strpos($q->query_vars["s"], ',')) {
				$q->query_where .= ' AND (';
				$wheres = array();
				foreach (explode(",", $q->query_vars["s"]) as $search) {
					$wheres[] = "(user_login LIKE '%".esc_sql($search)."%' OR user_nicename LIKE '%".esc_sql($search)."%' OR user_email LIKE '%".esc_sql($search)."%' OR level_txn = '".esc_sql($search)."' OR meta_value LIKE '%".esc_sql($search)."%')";
				}
				$q->query_where .= implode(" OR ", $wheres); $q->query_where .= ')';
			} else {
				$search = esc_sql($q->query_vars["s"]);
				$q->query_where .= ' AND (';
				$q->query_where .= "user_login LIKE '%".esc_sql($search)."%' OR user_nicename LIKE '%".esc_sql($search)."%' OR user_email LIKE '%".esc_sql($search)."%' OR level_txn = '".esc_sql($search)."' OR meta_value LIKE '%".esc_sql($search)."%'";
				$q->query_where .= ')';
			}
		}
		if (isset($q->query_vars["level_status"])) {
			$custom = true;
			$q->query_where .= ' AND level_status = "'.esc_sql($q->query_vars["level_status"]).'"';
		}
		if (isset($q->query_vars["levels"])) {
			$custom = true;
			$q->query_from .= ', '.$this->userTable;
			$q->query_where .= ' AND level_id IN ('.esc_sql($q->query_vars["levels"]).')';
			$q->query_where .= " AND user_id = ".$wpdb->users.".ID";
		}
		if (isset($q->query_vars["level"])) {
			$custom = true;
			$q->query_from .= ', '.$this->userTable;
			$q->query_where .= ' AND level_id = '.intval($q->query_vars["level"]);
			$q->query_where .= " AND user_id = ".$wpdb->users.".ID";
		}
		if (isset($q->query_vars["level_after"])) {
			$custom = true;
			$q->query_where .= ' AND level_date >= FROM_UNIXTIME('.intval($q->query_vars["level_after"]).')';
		}
		if (isset($q->query_vars["level_before"])) {
			$custom = true;
			$q->query_where .= ' AND level_date <= FROM_UNIXTIME('.intval($q->query_vars["level_before"]).')';
		}
		if ($custom) {
			$q->query_fields .= ', '.$wpdb->users.'.ID AS ID';
			$q->query_orderby = ' GROUP B'.chr(89).' '.$wpdb->users.'.ID '.$q->query_orderby;
		}
		if (current_user_can("administrator")) { }
		return $q;
	}


	function getMembers($query="") {
		$args = wp_parse_args($query);
		$users = get_users($args);
		$status = array();
		$levels = array();
		$search = null;
		if (isset($args["orderby"]) && $args["orderby"] == "first_name") {
			usort($users, array(&$this, "sortFirstname"));
		}
		if (isset($args["orderby"]) && $args["orderby"] == "last_name") {
			usort($users, array(&$this, "sortLastname"));
		}
		if (isset($args["orderby"]) && $args["orderby"] == "rand") {
			usort($users, array(&$this, "sortRandom"));
		}
		return $users;
	}

	function sortRandom($a, $b) {
		$coin = rand(0, 1);
		return ($coin == 0) ? -1 : 1;
	}

	function sortFirstname($a, $b) {
		return strcmp($a->first_name, $b->first_name);
	}

	function sortLastname($a, $b) {
		if ($a->last_name == $b->last_name) {
			return $this->sortFirstname($a, $b);
		}
		return strcmp($a->last_name, $b->last_name);
	}

	function getMembersSince($sinceTime=0, $stopAt=-1) {
		global $wpdb;
		$since = intval($sinceTime);
		if ($stopAt > -1) {
		}else {
			return $wpdb->get_results("SELECT level_id, COUNT(*) AS total FROM ".$this->userTable." WHERE level_date > FROM_UNIXTIME(".$since.") GROUP By level_id");
		}
	}

	function getMemberCount($status=null, $fromDate=null, $toDate=null) {
		global $wpdb;
		$where = array($wpdb->users.".ID = ".$this->userTable.".user_id");
		if ($status) { $where[] = "level_status = '".esc_sql($status)."'";}
		if ($fromDate) { $where[] = "level_date >= FROM_UNIXTIME(".intval($fromDate).")";}
		if ($toDate) { $where[] = "level_date < FROM_UNIXTIME(".intval($toDate).")";}
		if (count($where) > 0) {
			$where = "WHERE " . implode(" AND ", $where);
		} else {
			$where = reset($where);
		}
		$wpdb->query("SELECT ".$this->userTable.". * FROM ".$this->userTable.", ".$wpdb->users." $where GROUP B".chr(89)." user_id ORDER B".chr(89)." level_date ASC");
		return intval($wpdb->num_rows);
	}

	function deleteUser($userID) {
		global $wpdb;
		global $miembropress;
		MiembroPress::clearCache();
		$current_user = wp_get_current_user();
		$user = intval($userID);
		if ($user == $current_user->ID) { return; }
		wp_delete_user($user);
		$this->onDeleteUser($user);
	}

	function deleteTemp($tempID) {
		global $wpdb;
		global $miembropress;
		MiembroPress::clearCache();
		return $wpdb->query("DELETE FROM ".$this->tempTable." WHERE id = ".intval($tempID));
	}

	function completeTemp($tempID, $overwrite=array()) {
		global $miembropress;
		if ($temp = $miembropress->model->getTempFromID($tempID)) {
			$metadata = $temp->temp_metadata;
			foreach ($overwrite as $key=>$value) {
				$metadata[$key] = $value;
			}
			$vars = array( "action" => "miembropress_register", "miembropress_temp" => $temp->txn_id, "miembropress_username" => $metadata["username"], "miembropress_level" => intval($metadata["level"]), "miembropress_firstname" => $metadata["firstname"], "miembropress_lastname" => $metadata["lastname"], "miembropress_email" => $metadata["email"], "miembropress_password1" => "" );
			foreach ($overwrite as $key => $value) {
				if (strpos($key, "social_") === 0) {
					$vars[$key] = $value;
				}
			}
			if ($wp_user = get_user_by('email', $metadata["email"])) {
				$add = $this->add($wp_user->ID, $metadata["level"], $temp->txn_id);
				if ($add) {
					$miembropress->model->removeTemp($temp->txn_id);
					if (!is_admin() && !current_user_can("manage_options")) {
						wp_set_auth_cookie($wp_user->ID);
						do_action('wp_login', $wp_user->user_login, $wp_user);
						$_POST['log'] = $wp_user->user_login;
						header("Location:".home_url());
						die();
					}
				}
			}
			if ($wp_user = get_user_by('login', $metadata["username"])) {
				$vars["miembropress_username"] = $metadata["email"];
			}
			return $miembropress->admin->create($vars);
		} else {
			return new WP_Error("notemp", "Invalid temp ID.");
		}
	}

	function updateLevelDate($userID, $levelID, $timestamp) {
		global $wpdb;
		if (!$userID || !$levelID) {return false; }
		$user = intval($userID);
		$level = intval($levelID);
		$newDate = @intval(strtotime($timestamp));
		if ($newDate <= 1) { return false; }
		$theDate = gmdate(chr(89)."-m-d H:i:s", $newDate);
		return $wpdb->query("UPDATE ".$this->userTable." SET level_date = '".esc_sql($theDate)."' WHERE user_id = $user AND level_id = $level");
	}

	function updateTransaction($userID, $levelID, $newTransaction) {
		global $wpdb;
		if (!$userID || !$levelID) { return false; }
		$user = intval($userID);
		$level = intval($levelID);
		$newTransaction = trim(stripslashes($newTransaction));
		return $wpdb->query("UPDATE ".$this->userTable." SET level_txn = '".esc_sql($newTransaction)."' WHERE user_id = $user AND level_id = $level");
	}

	function setSubscribed($userID, $levelID, $status=true) {
		global $wpdb;
		global $miembropress;
		MiembroPress::clearCache();
		if (!$userID || !$levelID) { return false; }
		$user = intval($userID);
		$level = intval($levelID);
		$newStatus = ($status) ? 1 : 0;
		return $wpdb->query("UPDATE ".$this->userTable." SET level_subscribed = '".esc_sql($newStatus)."' WHERE user_id = $user AND level_id = $level");
	}

	function getTempFromID($id) {
		global $wpdb;
		if (!$id) { return false; }
		$temp = $wpdb->get_row("SELECT * FROM ".$this->tempTable." WHERE ID = '".intval($id)."'");
		if (isset($temp->temp_metadata)) {
			if (strpos($temp->temp_metadata, '{') !== false) {
				$temp->temp_metadata = unserialize($temp->temp_metadata);
			}
		}
		return $temp;
	}

	function getTempFromTransaction($transaction) {
		global $wpdb;
		if (!$transaction) { return false; }
		$temp = $wpdb->get_row("SELECT * FROM ".$this->tempTable." WHERE txn_id = '".esc_sql($transaction)."'");
		if (isset($temp->temp_metadata)) {
			if (strpos($temp->temp_metadata, '{') !== false) {
				$temp->temp_metadata = unserialize($temp->temp_metadata);
			}
		}
		return $temp;
	}

	function getUserIdFromTransaction($transaction) {
		global $wpdb;
		if (!$transaction) { return false; }
		$query = "SELECT user_id FROM ".$this->userTable." WHERE level_txn = '".esc_sql($transaction)."'";
		return intval($wpdb->get_var($query));
	}

	function getLevelsFromTransaction($transaction) {
		global $wpdb;
		if (!$transaction) { return false; }
		$query = "SELECT level_id FROM ".$this->userTable." WHERE level_txn = '".esc_sql($transaction)."'";
		return $wpdb->get_col($query);
	}

	function getTemps() {
		global $wpdb;
		$results = $wpdb->get_results("SELECT * FROM ".$this->tempTable." ORDER B".chr(89)." temp_created DESC");
		foreach ($results as $resultKey => $result) {
			$results[$resultKey]->meta = unserialize($result->temp_metadata);
			unset($result->temp_metadata);
		}
		return $results;
	}

	function getTempCount() {
		global $wpdb;
		return intval($wpdb->get_var("SELECT COUNT(*) FROM ".$this->tempTable));
	}

	function createTemp($transaction, $level, $metadata) {
		global $wpdb;
		if (!$transaction || !$level) { return; }
		$insert = $wpdb->query("INSERT IGNORE INTO ".$this->tempTable." SET txn_id = '".esc_sql($transaction)."', level_id = ".intval($level).", temp_metadata = '".esc_sql(serialize($metadata))."', temp_created = UTC_TIMESTAMP()");
		return $transaction;
	}

	function cancelTemp($transaction) {
		global $wpdb;
		$wpdb->query("UPDATE ".$this->tempTable." SET level_status = 'C' WHERE txn_id = '".esc_sql($transaction)."'");
	}

	function removeTemp($transaction) {
		global $wpdb;
		if (!$transaction) { return; }
		$wpdb->query("DELETE FROM ".$this->tempTable." WHERE txn_id = '".esc_sql($transaction)."'");
	}

	function protectAllPosts($levelID=-1) {
		foreach ($this->allPosts() as $postID) {
			if ($postID == 0) { continue; }
			$this->protect($postID, $levelID);
		}
	}

	function countLevels() {
		global $wpdb;
		return intval($wpdb->get_var("SELECT COUNT(*) FROM ".$this->levelTable));
	}

	function createLevel($name, $all=false, $comments=true, $hash=null) {
		global $wpdb;
		global $miembropress;
		MiembroPress::clearCache();
		$name = preg_replace('@[^a-z0-9\-\_\ ]@si', '', $name);
		$hash = preg_replace('@[^a-z0-9]@si', '', $hash);
		if ($this->hashCollision($hash)) { return; }
		if (!$hash) { $hash = $this->hash(); }
		if (!$all) {
			$all = 0;
		} else {
			$all = 1;
		}
		if (!$comments) {
			$comments = 0;
		} else {
			$comments = 1;
		}

		$sku = $this->sku();
		return $wpdb->query('INSERT IGNORE INTO '.$this->levelTable.' SET ID = '.intval($sku).', level_name="'.esc_sql($name).'", level_hash="'.esc_sql($hash).'", level_all='.intval($all).", level_comments=".intval($comments));
	}

	function deleteLevel($id) {
		global $miembropress;
		MiembroPress::clearCache();
		if (!is_numeric($id)) { return; }
		global $wpdb;
		$level = intval($id);
		$wpdb->query("DELETE FROM ".$this->levelTable." WHERE ID = $level");
		$wpdb->query("DELETE FROM ".$this->contentTable." WHERE level_id = $level");
		$wpdb->query("DELETE FROM ".$this->userTable." WHERE level_id = $level");
	}

	function editLevel($id, $data) {
		global $wpdb;
		global $miembropress;
		MiembroPress::clearCache();
		$level = intval($id);
		if ($level == 0) { return; }
		if (!is_array($data)) { return; }
		$name = $data["level_name"];
		$name = preg_replace('@[^a-z0-9\-\_\ ]@si', '', $name);
		$all = $data["level_all"];
		$gdpr = $data["gdpr_active"];
		if (!$all) {
			$all = 0;
		} else {
			$all = 1;
		}

		if (!$gdpr) {
			$gdpr = 0;
		} else {
			$gdpr = 1;
		}

		$comments = $data["level_comments"];
		if (!$comments) {
			$comments = 0;
		} else {
			$comments = 1;
		}

		$register = 0;
		$login = 0;
		$expiration = 0;
		if (isset($data["level_page_register"])) { $register = $data["level_page_register"];}
		if (isset($data["level_page_login"])) {$login = $data["level_page_login"];}
		if (isset($data["level_expiration"])) { $expiration = intval($data["level_expiration"]); }
		$expiration = max(0, $expiration); $oldLevel = $this->getLevel($id);
		if ($oldLevel->level_all == 1 && $all == 0) { $this->protectAllPosts($id); }
		if ($name && $level) {
			$wpdb->query('UPDATE '.$this->levelTable.' SET level_name="'.esc_sql($name).'", level_all='.intval($all).', gdpr_active='.intval($gdpr).', level_comments='.intval($comments).', level_page_register = '.$register.', level_page_login = '.$login.', level_expiration = '.$expiration.' WHERE ID='.$level);
		}
	}

	function subscribe($userID, $levelID) {
		global $wpdb;
		global $miembropress;
		MiembroPress::clearCache();
		$user = intval($userID);
		$level = intval($levelID);
		$wpdb->query("UPDATE ".$this->userTable." SET level_subscribed=1 WHERE user_id = $user AND level_id = $level");
	}

	function unsubscribe($userID, $levelID) {
		global $wpdb;
		global $miembropress;
		MiembroPress::clearCache();
		$user = intval($userID);
		$level = intval($levelID);
		$wpdb->query("UPDATE ".$this->userTable." SET level_subscribed=0 WHERE user_id = $user AND level_id = $level");
	}

	function add($userID, $levelID, $transaction=null, $dateAdded=null) {
		global $wpdb;
		global $miembropress;
		MiembroPress::clearCache();
		if ($levelID === null || !is_numeric($levelID)) { return; }
		$user = intval($userID); $level = intval($levelID);
		if (!is_numeric($dateAdded) || @intval($dateAdded) <= 1) {
			$dateAdded = strtotime($dateAdded);
		}
		if (!is_numeric($dateAdded) || @intval($dateAdded) <= 1) {
			$dateAdded = time();
		}
		$dateAdd = gmdate(chr(89)."-m-d H:i:s", $dateAdded);
		$wpdb->query("INSERT IGNORE INTO ".$this->userTable." SET user_id = $user, level_id = $level, level_status='A', level_txn = '".esc_sql($transaction)."', level_date = '".esc_sql($dateAdd)."'");
		do_action('miembropress_add_user_levels', $user, array($level));
		if (!is_plugin_active("wishlist-member/wishlist-member.php")) {
			do_action('wishlistmember_add_user_levels', $user, array($level));
		}
		$affected = ($wpdb->rows_affected > 0);
		if ($affected) { $delay = @intval($miembropress->model->levelSetting($levelID, "delay"));
			if ($delay > 0) {
			}elseif ($action = $miembropress->model->levelSetting($levelID, "add")) {
				$this->add($userID, $action, $transaction, $dateAdd);
			}elseif ($action = $miembropress->model->levelSetting($levelID, "move")) {
				$this->move($userID, $action, $transaction, $dateAdd);
			}
		}
		$this->uncancel($userID, $levelID);
		return $affected;
	}

	function move($userID, $levelID, $transaction=null, $dateAdded=null) {
		global $wpdb;
		global $miembropress;
		MiembroPress::clearCache();
		if ($levelID === null || !is_numeric($levelID)) { return; }
		$user = intval($userID);
		$level = intval($levelID);
		$wpdb->query("DELETE FROM ".$this->userTable." WHERE user_id = $user");
		$this->add($userID, $levelID, $transaction, $dateAdded);
	}

	function remove($userID, $levelID) {
		global $wpdb;
		global $miembropress;
		MiembroPress::clearCache();
		if ($levelID === null || !is_numeric($levelID)) { return; }
		$user = intval($userID);
		$level = intval($levelID);
		$wpdb->query("DELETE FROM ".$this->userTable." WHERE user_id = $user AND level_id = $level");
		do_action('miembropress_remove_user_levels', $user, array($level));
		if (!is_plugin_active("wishlist-member/wishlist-member.php")) {
			do_action('wishlistmember_remove_user_levels', $user, array($level));
		}
	}

	function cancel($userID, $levelID) {
		global $wpdb;
		global $miembropress;
		MiembroPress::clearCache();
		if ($levelID === null || !is_numeric($levelID)) { return; }
		$user = intval($userID);
		$level = intval($levelID);
		$wpdb->query("UPDATE ".$this->userTable." SET level_status='C' WHERE user_id = $user AND level_id = $level");
		do_action('miembropress_cancel_user_levels', $user, array($level));
		if (!is_plugin_active("wishlist-member/wishlist-member.php")) {
			do_action('wishlistmember_cancel_user_levels', $user, array($level));
		}
	}

	function uncancel($userID, $levelID) {
		global $wpdb;
		global $miembropress;
		MiembroPress::clearCache();
		if ($levelID === null || !is_numeric($levelID)) { return; }
		$user = intval($userID);
		$level = intval($levelID);
		$wpdb->query("UPDATE ".$this->userTable." SET level_status='A' WHERE user_id = $user AND level_id = $level");
		do_action('miembropress_uncancel_user_levels', $user, array($level));
		if (!is_plugin_active("wishlist-member/wishlist-member.php")) {
			do_action('wishlistmember_uncancel_user_levels', $user, array($level));
		}
	}

	function protect($postID, $levelID=-1) {
		global $wpdb;
		global $miembropress;
		MiembroPress::clearCache();
		$level = intval($levelID);
		$post = intval($postID);
		$wpdb->query("INSERT IGNORE INTO ".$this->contentTable." SET level_id = $level, post_id = $post");
	}

	function unprotect($postID, $levelID=-1) {
		global $wpdb;
		global $miembropress;
		MiembroPress::clearCache();
		$level = intval($levelID);
		$post = intval($postID);
		$wpdb->query("DELETE FROM ".$this->contentTable." WHERE level_id = $level AND post_id = $post");
	}

	function getPosts($userID=0) {
		global $wpdb;
		$user = intval($userID);
		$levels = $wpdb->get_col("SELECT level_id FROM ".$this->levelTable.", ".$this->userTable." WHERE ".$this->levelTable.".ID = ".$this->userTable.".level_id AND user_id = $user AND level_status='A' AND level_all=1 GROUP B".chr(89)." level_id");
		if (current_user_can("administrator")) {
			return $this->allPosts();
		}
		if (count($levels) > 0) {
			return $this->allPosts();
		}
		$unprotected = $this->allPosts($wpdb->get_col("SELECT post_id FROM ".$this->contentTable." WHERE level_id = -1"));
		$protected = $wpdb->get_col("SELECT post_id FROM ".$this->contentTable.", ".$this->userTable." WHERE user_id = $user AND level_status='A' AND (".$this->contentTable.".level_id = ".$this->userTable.".level_id)");
		$login_pages = $wpdb->get_col("SELECT level_page_login FROM ".$this->levelTable.", ".$this->userTable." WHERE user_id = $user AND level_status='A' AND (".$this->levelTable.".ID = ".$this->userTable.".level_id)");
		$redirect_pages = $wpdb->get_col("SELECT level_page_login FROM ".$this->levelTable.", ".$this->userTable." WHERE user_id = $user AND level_status='A' AND (".$this->levelTable.".ID = ".$this->userTable.".level_id)");
		$return = array_merge($protected, $unprotected, $login_pages, $redirect_pages);
		$return = array_unique(array_values($return));
		return $return;
	}

	function allPosts($diff=null) {
		global $wpdb;
		$all = $wpdb->get_col("SELECT ID FROM ".$wpdb->posts." WHERE post_status = 'publish'");
		if ($diff) {
			return array_diff($all, $diff);
		}
		return $all;
	}

	function getBlockedPages($userID=-1) { }

	function getLevelAccess($userID) { }

	function getDaysOnLevel($userID, $levelID, $now=null) {
		global $wpdb;
		$userID = @intval($userID);
		$levelID = @intval($levelID);
		if ($now == null) {
			$now = time();
		}
		$now = @intval($now);
		$level_date = $wpdb->get_var("SELECT level_date FROM ".$this->userTable." WHERE user_id=".$userID." AND level_id=".$levelID);
		$date = strtotime($level_date);
		if ($date <= 1) { return null; }
		$days = floor(($now-$date)/86400);
		return $days;
	}

	function timestampToDays($timestamp) {
		if (!is_numeric($timestamp)) {
			$timestamp = strtotime($timestamp);
		}
		return floor((time()-$timestamp)/86400);
	}

	function getLevelInfo($userID, $status=null) {
		global $wpdb;
		$user = intval($userID);
		$sql = "SELECT user_id, level_id, level_status, level_txn, level_subscribed, level_date, UNIX_TIMESTAMP(level_date) AS level_timestamp, level_name, level_comments, level_page_register, level_page_login, level_expiration FROM ".$this->userTable." LEFT JOIN ".$this->levelTable." ON ".$this->userTable.".level_id = ".$this->levelTable.".ID WHERE user_id = $user ";
		if ($status == "A") {
			$sql .= "AND level_status = 'A' ";
		} elseif ($status == "S") {
			$sql .= "AND level_subscribed = 1 AND level_status = 'A' ";
		} elseif ($status == "U") {
			$sql .= "AND level_subscribed = 0 AND level_status = 'A' ";
		} else { }
		$sql .= "ORDER B".chr(89)." level_name ASC";
		$results = $wpdb->get_results($sql);
		if (!$results) { return array(); }
		$return = array();
		foreach ($results as $result) {
			$return[$result->level_id] = $result;
		}
		return $return;
	}

	function getPostAccess($levelID) {
		global $wpdb;
		$level = intval($levelID);
		$levelInfo = $this->getLevel($levelID);
		if ($level == -1) {
			$protected = $wpdb->get_col("SELECT post_id FROM ".$this->contentTable." WHERE level_id = $level");
			return $this->allPosts($protected);
			return $diff;
		}
		$return = $wpdb->get_col("SELECT post_id FROM ".$this->contentTable." WHERE level_id = $level");
		if (isset($levelInfo->level_page_register) && $levelInfo->level_page_register) {
			$return[] = intval($levelInfo->level_page_register);
		}
		if (isset($levelInfo->level_page_login) && $levelInfo->level_page_login) {
			$return[] = intval($levelInfo->level_page_login);
		}
		return $return;
	}

	function getPageAccess($levelID) { }

	function isProtected($postID) {
		global $wpdb;
		$post = intval($postID);
		return intval($wpdb->get_var("SELECT COUNT(*) FROM ".$this->contentTable." WHERE level_id = -1 AND post_id = $post"));
	}

	function getLevelsDefault() {
		global $wpdb;
		$return = $wpdb->get_col("SELECT level_id FROM ".$this->contentTable." WHERE level_all=1");
	}

	function getLevelsFromPost($postID) {
		global $wpdb;
		$post = intval($postID);
		$result = $wpdb->get_results("SELECT level_id, level_name FROM ".$this->contentTable.", ".$this->levelTable." WHERE post_id = $post AND level_id = ".$this->levelTable.".ID");
		foreach ($this->getLevels() as $level) {
			if (isset($level->level_page_register) && $level->level_page_register && $level->level_page_register == $postID) {
				$result[] = (object) array("level_id" => $level->ID, "level_name" => $level->level_name);
			} elseif (isset($level->level_page_login) && $level->level_page_login && $level->level_page_login == $postID) {
				$result[] = (object) array("level_id" => $level->ID, "level_name" => $level->level_name);
			}
		}
		$return = array();
		foreach ($result as $r) {
			$return[$r->level_id] = $r->level_name;
		}
		return $return;
	}

	function getLevelName($levelID) {
		global $wpdb;
		$levelID = intval($levelID);
		return $wpdb->get_var("SELECT level_name FROM ".$this->levelTable." WHERE ID = $levelID LIMIT 1");
	}

	function hasAccess($userID, $levelID, $includeCanceled=false) {
		global $wpdb;
		$user = intval($userID);
		$level = intval($levelID);
		if ($includeCanceled) {
			return intval($wpdb->get_var("SELECT COUNT(*) FROM ".$this->userTable." WHERE level_id = $level AND user_id = $user LIMIT 1"));
		} else {
			return intval($wpdb->get_var("SELECT COUNT(*) FROM ".$this->userTable." WHERE level_id = $level AND user_id = $user AND level_status = 'A' LIMIT 1"));
		}
	}

	function getAutoresponder($level) {
		$autoresponders = $this->setting("autoresponders");
		if (!is_array($autoresponders)) { return null; }
		if (isset($autoresponders[$level])) { return $autoresponders[$level]; }
		return null;
	}

	function setAutoresponder($level, $code="", $email="", $firstname="", $lastname="") {
		$autoresponders = $this->setting("autoresponders");
		if (!is_array($autoresponders)) { $autoresponders = array(); }
		if (!$code) {
			unset($autoresponders[$level]);
		} else {
			$autoresponders[$level] = array( "code" => stripslashes($code), "email" => $email, "firstname" => $firstname, "lastname" => $lastname );
		}
		$this->setting("autoresponders", $autoresponders);
	}
}

?>