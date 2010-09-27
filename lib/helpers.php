<?php
/**
 * Mollom helper functions
 */


/**
 * Checks if the mollom passthrough id is valid for this session.
 *
 * @param string $id
 * @return BOOL
 */
function mollom_validate_passthrough($token, $ts) {
	return validate_action_token(FALSE, $token, $ts);
}

/**
 * Concats a string base on the values of $vars
 * and ignoring values based upon a plugin hook.
 *
 * @param array $vars
 */
function mollom_get_content_string($vars) {
	global $CONFIG;
	static $var_whitelist;

	if (!is_array($var_whitelist)) {
		$var_whitelist = trigger_plugin_hook('mollom', 'get_var_whitelist', NULL, $CONFIG->mollom['var_whitelist']);
	}

	// build a content string to test against
	$content = '';

	// @todo could use array diff to do this, probably.
	foreach ($vars as $name => $value) {
		if (in_array($name, $var_whitelist)) {
			continue;
		}

		if (is_array($value)) {
			$content .= mollom_get_content_string($value);
		} else {
			$content .= "$value ";
		}
	}

	return $content;
}

/**
 * Checks $content against mollom.
 * Returns a Mollom result or FALSE if Mollom couldn't be contacted.
 *
 * @param string $content
 * @return array or FALSE
 */
function mollom_check_content($content) {
	$content = trim($content);
	if (empty($content)) {
		return TRUE;
	}

	if ($user = get_loggedin_user()) {
		$username = $user->username;
		$email = $user->email;
		$id = $user->guid;
	} else {
		$username = $email = $id = NULL;
	}

	try {
		$result = Mollom::checkContent(mollom_get_session_id(), NULL, $content, $username, NULL, $email, NULL, $id);
		// need to increment the content
		if (isset($result['spam']) && $result['spam'] == 'ham') {
			mollom_increment_volume_count('content');
		}
		return $result;
	} catch(Exception $e) {
		return FALSE;
	}
}


/**
 * Generates appropriate hidden inputs given an assoc array (like $_REQUEST)
 *
 * @param array $vars
 * @return string
 */
function mollom_make_hidden_input_html($vars, $array_name = '') {
	$inputs = '';
	foreach ($vars as $k => $v) {
		if ($array_name) {
			$k = "{$array_name}[$k]";
		}

		if (is_array($v)) {
			$inputs .= mollom_make_hidden_input_html($v, $k);
		} else {
			$inputs .= elgg_view('input/hidden', array(
				'internalname' => $k,
				'value' => $v
			));
		}
	}

	return $inputs;
}


/**
 * Validate a captcha through Mollom
 *
 * @param unknown_type $id
 * @param unknown_type $input
 * @return bool
 */
function mollom_validate_captcha($id, $input) {
	try {
		$r = Mollom::checkCaptcha($id, $input);
		// need to bump up the valid captcha counter
		if ($r) {
			mollom_increment_volume_count('captcha');
		}

		return $r;
	} catch(Exception $e) {
		return TRUE;
	}
}

/**
 * Checks if this URL is filtered for content.
 * Expands the action shortcuts into full URLs.
 *
 * @param unknown_type $url
 */
function mollom_is_checked_url($url) {
	global $CONFIG;
	static $lists = NULL;

	// sometimes actions are called without the full url.
	// naughty.
	if (!substr_count($url, $CONFIG->site->url)) {
		$url = "{$CONFIG->site->url}$url";
	}

	// sometimes people add extra trailing slashes.
	$url = preg_replace('|(?<!:)//|i', '/', $url);

	if (!$lists) {
		// whitelists ignore checking and override blacklists.
		// blacklists force checking

		// actions shortcut hooks
		$action_wl = trigger_plugin_hook('mollom', 'get_action_whitelist', NULL, $CONFIG->mollom['action_whitelist']);
		$action_bl = trigger_plugin_hook('mollom', 'get_action_blacklist', NULL, $CONFIG->mollom['action_blacklist']);

		// full URLs hooks
		$url_wl = trigger_plugin_hook('mollom', 'get_url_whitelist', NULL, $CONFIG->mollom['url_whitelist']);
		$lists['whitelist'] = $url_wl;

		$url_bl = trigger_plugin_hook('mollom', 'get_url_blacklist', NULL, $CONFIG->mollom['url_blacklist']);
		$lists['blacklist'] = $url_bl;

		$base_url = "{$CONFIG->site->url}action";
		foreach ($action_wl as $action) {
			$lists['whitelist'][] = preg_replace('|(?<!:)//|i', '/', "$base_url/$action");
		}

		foreach ($action_bl as $action) {
			$lists['blacklist'][] = preg_replace('|(?<!:)//|i', '/', "$base_url/$action");
		}
	}

	$list_types = array('whitelist', 'blacklist');

	foreach ($list_types as $list_type) {
		$is_checked_on_match = ($list_type == 'whitelist') ? FALSE : TRUE;
		$list = $lists[$list_type];

		foreach ($list as $check) {
			$regex = "|$check|i";

			if (preg_match($regex, $url)) {
				return $is_checked_on_match;
			}
		}
	}

	return FALSE;
}

/**
 * Are we currently trying to passthrough?
 */
function mollom_in_passthrough() {
	return get_input('mollom_passthrough');
}

/**
 * Are we currently in a valid passthrough?
 *
 * @return bool
 */
function mollom_in_valid_passthrough() {
	$mollom_ts = get_input('mollom_ts');
	$mollom_token = get_input('mollom_token');
	$mollom_id = get_input('mollom_id');
	$captcha_input = get_input('mollom_captcha');

	$passthrough = mollom_validate_passthrough($mollom_token, $mollom_ts);
	$captcha = mollom_validate_captcha($mollom_id, $captcha_input);

	if ($passthrough && $captcha) {
		return TRUE;
	}

	return FALSE;
}

/**
 * Sets a session ID if not already set.
 * Note this is saved outside the mollom session data.
 *
 * @param unknown_type $session_id
 * @return bool if set.
 */
function mollom_set_session_id($session_id) {
	if (!isset($_SESSION['mollom_session_id'])) {
		$_SESSION['mollom_session_id'] = $session_id;
		return TRUE;
	}

	return FALSE;
}

/**
 * Gets the current session ID.
 *
 * @return mixed Session ID if set, FALSE if not.
 */
function mollom_get_session_id() {
	if (isset($_SESSION['mollom_session_id'])) {
		return $_SESSION['mollom_session_id'];
	}

	return FALSE;
}

/**
 * Increments counter of $type by $plus
 * Do this instead of the API for atomic events that avoid race conditions
 *
 * @param string $type content || captcha
 * @param int $plus
 */
function mollom_increment_volume_count($type, $inc = 1) {
	global $CONFIG;
	$guid = mollom_get_plugin_guid();
	$name = sanitise_string($type . '_volume_count');
	$inc = sanitise_int($inc);

	$q = "INSERT INTO {$CONFIG->dbprefix}private_settings
		(entity_guid, name, value) VALUES ($guid, '$name', 1)
		ON DUPLICATE KEY UPDATE value = value + $inc";

	return update_data($q);
}

/**
 * Return the volume
 *
 * @param unknown_type $type
 */
function mollom_get_volume_count($type) {
	global $CONFIG;

	$name = $type . '_volume_count';
	return get_plugin_setting($name, 'mollom');
}

/**
 * Set a volume count.
 *
 * @param string $type content or captcha
 * @param int $count
 * @return bool on success
 */
function mollom_set_volume_count($type, $count) {
	global $CONFIG;

	$guid = mollom_get_plugin_guid();
	$name = sanitise_string($type . '_volume_count');
	$count = sanitise_int($count);

	$q = "INSERT INTO {$CONFIG->dbprefix}private_settings
		(entity_guid, name, value) VALUES ($guid, '$name', $count)
		ON DUPLICATE KEY UPDATE value = $count";

	return update_data($q);
}

/**
 * Get mollom's plugin id. Useful for direct SQL queries.
 *
 * @return int
 */
function mollom_get_plugin_guid() {
	global $CONFIG;
	// shouldn't need to cache because of the entity / query cache.

	$e = elgg_get_entities(array(
		'type' => 'object',
		'subtype' => 'plugin',
		'wheres' => array('oe.title = "mollom"'),
		'joins' => array("JOIN {$CONFIG->dbprefix}objects_entity oe on oe.guid = e.guid")
	));

	if ($e) {
		return $e[0]->guid;
	}

	return FALSE;
}

/**
 * Returns the max volume as set in plugin settings.
 *
 * @param unknown_type $type
 */
function mollom_get_volume_max($type) {
	$name = sanitise_string($type . '_volume_max');
	return get_plugin_setting($name, 'mollom');
}

/**
 * Set the volume max for $type.
 *
 * @param unknown_type $type
 * @param unknown_type $max
 */
function mollom_set_volume_max($type, $max = 100) {
	$name = sanitise_string($type . '_volume_max');
	return set_plugin_setting($name, $max, 'mollom');
}