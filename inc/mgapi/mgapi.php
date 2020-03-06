<?php

class MGAPI {
	public static function GetOption($option) {
		if ($option == "non_members_error_page") {
			return home_url("wp-login.php");
		}
	}

	public static function GetLevels() {
		global $miembropress;
		return array_map(array("MGAPI", "GetLevelsMap"), $miembropress->model->getLevels());
	}

	public static function GetLevelsMap($level) {
		$slug = strtolower(preg_replace('@[^A-Z0-9]+@si', '-', $level->level_name));
		return array( 'name' => $level->level_name, 'url' => $level->level_hash, 'loginredirect' => '---', 'afterregredirect' => '---', 'noexpire' => ($level->level_expiration > 0) ? 0 : 1, 'upgradeTo' => '0', 'upgradeAfter' => '0', 'upgradeMethod' => '0', 'count' => $level->active, 'role' => 'subscriber', 'levelOrder' => '', 'slug' => $slug, 'ID' => $level->ID );
	}

	public static function GetContentByLevel($contentType="all", $level) {
		global $miembropress;
		$content = $miembropress->model->getPostAccess($level);
		if ($contentType == "posts") {
			$content = get_posts(array("posts_per_page"=>-1, "post_type" => "post", "include" => $content));
			$content = array_map(create_function('$p', 'return $p->ID;'), $content);
		} elseif ($contentType == "pages") {
			$content = get_pages(array("posts_per_page"=>-1, "post_type" => "page", "include" => $content));
			$content = array_map(create_function('$p', 'return $p->ID;'), $content);
		}
		return $content;
	}

	public static function AddUser($username, $email, $password, $firstname="", $lastname="") { }
	public static function EditUser($id, $email="", $password="", $firstname="", $lastname="", $displayname="", $nickname="") { }
	public static function DeleteUser($id, $reassign=null) { }
	public static function GetUserLevels($user, $levels="all", $return="names", $addpending=false, $addsequential=false, $cancelled=0 ) {
		global $miembropress;
		if ($cancelled == 1) {
			$info = $miembropress->model->getLevelInfo($user, "*");
		} else {
			$info = $miembropress->model->getLevelInfo($user, "A");
		}

		if ($return == "skus") {
			$info = array_map(create_function('$l', 'return $l->level_id;'), $info);
		} else {
			$info = array_map(create_function('$l', 'return $l->level_name;'), $info);
		}

		if ($levels != "all") {
			$theLevels = @explode(",", $levels);
			if (count($theLevels) == 0) {
				$theLevels = array($levels);
			}
			foreach ($info as $key => $level) {
				if (!in_array($key, $theLevels)) {
					unset($info[$key]);
				}
			}
		}
		return $info;
	}

	public static function UserLinks($userID) {
		$info = MGAPI::GetUserLevels();
		var_export($info);
	}

	public static function AddUserLevels($user, $levels, $txid="", $autoresponder=false) {
		global $miembropress;
		$user = @intval($user);
		if (!get_user_by("id", $user)) { return false; }
		foreach ($levels as $level) {
			$add = $miembropress->model->add($user, $level, $txid);
			if ($autoresponder) {
				$membegenius->model->subscribe($user, $level);
			}
		}
		return true;
	}

	public static function DeleteUserLevels($user, $levels, $autoresponder=true) { }
	public static function GetMembers() { }
	public static function MergedMembers($levels, $strippending) { }
	public static function GetMemberCount($level) { }
	public static function MakePending($id) { }
	public static function MakeActive($id) { }
	public static function MakeSequential($id) { }
	public static function MakeNonSequential($id) { }
	public static function MoveLevel($id, $lev) { }
	public static function CancelLevel($id, $lev) { }
	public static function UnCancelLevel($id, $lev) { }
	public static function GetPostLevels($id) {
		return MGAPI::GetPageLevels($id);
	}

	public static function AddPostLevels($id, $levels){
		return MGAPI::AddPageLevels($id, $levels);
	}

	public static function DeletePostLevels($id, $levels) { }
	public static function GetPageLevels($id) {
		global $miembropress; return $miembropress->model->getLevelsFromPost($id);
	}

	public static function AddPageLevels($id, $levels) {
		global $miembropress; $thePage = get_posts(array("include" => array($id), "post_type" => array("page", "post")));
		if (!$thePage) { return false; }
		foreach ($levels as $level) { $miembropress->model->protect($id, $level); }
		return true;
	}

	public static function DeletePageLevels($id, $levels) { }
	public static function GetCategoryLevels($id) { }
	public static function AddCategoryLevels($id, $levels) { }
	public static function DeleteCategoryLevels($id, $levels) { }
	public static function GetCommentLevels($id) { }
	public static function AddCommentLevels($id, $levels) { }
	public static function DeleteCommentLevels($id, $levels) { }
	public static function PrivateTags($content) { }
	public static function ShowWLMWidget($widgetargs) { }
	public static function SetProtect($id, $yesNo="") {
		global $miembropress;
		if ($yesNo == "") { $yesNo = chr(89); }
		$thePage = get_posts(array("include" => array($id), "post_type" => array("page", "post")));
		if (!$thePage) { return false; }
		if ($yesNo == chr(89)) {
			$miembropress->model->protect($id, -1);
		} else {
			$miembropress->model->unprotect($id, -1);
		}
	}

	public static function IsProtected($postID) {
		global $miembropress; return $miembropress->model->isProtected($postID);
	}
}

?>