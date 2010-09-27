<?php
/**
 * Elgg Mollom plugin
 *
 * Connects to Mollom's anti-spam services.
 * Provides a standard captcha that can be used as a drop-in replacement for the default captcha.
 * Can also use their content filtering services to intercept actions as they go through.
 * You can whitelist actions to avoid intercept or you can black list actions to force through filter.
 * You can also add standard URLs to scan for naughty $_REQUEST content (since not all posting is done through actions).
 *
 * General flow for content filter:
 * 1. URL / Action is requested
 * 2. If URL is in the list to filter continue, else return.
 * 3. Build content array from $_REQUEST and send to mollom.
 * 4. If content is suspicious continue, else return.
 * 5. Save $_REQUEST, $_SERVER['ACCESS_URI'], and $_SERVER['HTTP_REFERER'] to $_SESSION
 * 	('request', 'post_url' and 'referrer')
 * 6. Include breakout.php, which forwards to pg/mollom/intercepted or pg/mollom/rejected
 * 7. pg/mollom/intercepted pulls in everything to hidden vars and posts to $_SESSION['post_url'] if captcha is good
 *
 * Why not hook into the create/update events? The hooks for creating / updating aren't sophisticated enough to allow an intercept
 * event.  You can intercept the event, but there's no way easy way to continue it.  Depending upon the hook,
 * bits of the entity might be left laying around.
 *
 * Also thought about disabling the entity / metadata / annotation if something was naughty, but it'd be weird to have entities
 * dropping off.
 *
 * ALSO thought about queuing the changes into an object and only replying them once validated, but this runs into
 * the same problem with the hooks not being good enough...
 *
 * @todo Mollom's stats page and my counters regularly disagree about how many API calls have been made.
 * @todo Option to only scan content with URLs in it. Would reduce API calls.
 */

/**
 * Start Mollom
 */
function mollom_init() {
	global $CONFIG;
	require 'lib/helpers.php';
	require dirname(__FILE__) . '/vendors/mollom.php';

	// set up a few basic settings
	run_function_once('mollom_run_once');

	// css
	elgg_extend_view('css', 'mollom/css');

	// reset the volume counters
	register_plugin_hook('cron', 'daily', 'mollom_daily_cron');

	// basic config
	$CONFIG->mollom = array();
	$CONFIG->mollom['public_key'] = NULL;
	$CONFIG->mollom['private_key'] = NULL;
	$CONFIG->mollom['servers'] = NULL;
	$CONFIG->mollom['captcha_actions'] = array();
	$CONFIG->mollom['use_as_captcha'] = FALSE;
	$CONFIG->mollom['use_content_filter'] = FALSE;
	$CONFIG->mollom['immediately_reject_spam'] = FALSE;

	// vars to ignore when building a content array
	// hooked with mollom:get_var_whitelist
	$CONFIG->mollom['var_whitelist'] = array(
		'__elgg_ts',
		'__elgg_token',
		'access_id',
		'submit',
		'action',
		'Elgg',
		'XDEBUG_SESSION',
		'comments_select',
		'mollom_id',
		'mollom_captcha',
		'mollom_passthrough',
	);

	// regex of actions to always allow in content filter
	// hooked with mollom:get_action_whitelist
	// merged automatically with actions explicitly registered for a captcha
	// (if mollom is used as a captcha)
	// white lists override black lists
	$CONFIG->mollom['action_whitelist'] = array(
		'login',
		'logout',
	);

	// regex of actions to force through content filtering (if enabled)
	// hooked with mollom:get_action_blacklist
	$CONFIG->mollom['action_blacklist'] = array(

	);

	// not all post operations go through actions, so allow
	// plugins to register regex for URLs to check against.
	// can also be used to canvas actions, like in the admin section.
	$CONFIG->mollom['url_whitelist'] = array(
		$CONFIG->site->url . 'action/admin/.*',
		$CONFIG->site->url . 'action/plugins/settings/.*',
		'action/.*/upload$',
	);

	// hooked with mollom:get_url_blacklist
	$CONFIG->mollom['url_blacklist'] = array(
		'/mod/messageboard/ajax_endpoint/load.php',
		'action/.*/.*edit([^/]*)?$',
		'action/.*/.*save([^/]*)?$',
		'action/.*/.*new([^/]*)?$',
		'action/.*/.*post([^/]*)?$',
	);

	// hook into save plugin action for mollom to validate the keys
	register_plugin_hook('action', 'plugins/settings/save', 'mollom_check_api_keys');

	// abort if nothing's been set up.
	$pub_key = get_plugin_setting('public_key', 'mollom');
	$priv_key = get_plugin_setting('private_key', 'mollom');
	if (!($pub_key && $priv_key)) {
		return NULL;
	}

	// for the breakout forward.
	register_page_handler('mollom', 'mollom_page_handler');

	// mollom-specific stuff
	try {
		Mollom::setPublicKey($pub_key);
		Mollom::setPrivateKey($priv_key);
	} catch (Exception $e) {
		// don't try to register any hooks, since mollom is misconfigured.
		return NULL;
	}

	// check the server list cache
	$last_cache = get_plugin_setting('last_servers_cache', 'mollom');
	$servers = unserialize(get_plugin_setting('servers', 'mollom'));
	$cache_max = strtotime('-1 month');

	if (!$servers || !$last_cache || $last_cache < $cache_max) {
		try {
			$servers = Mollom::getServerList();
		} catch(Exception $e) {
			// abort if can't update and queue to update again
			register_error(elgg_echo('mollom:error:cannot_update_servers'));
			set_plugin_setting('servers', NULL, 'mollom');
			set_plugin_setting('last_servers_cache', NULL, 'mollom');

			// @todo increment a fail count and abort after 5 or something
			return TRUE;
		}

		set_plugin_setting('servers', serialize($servers), 'mollom');
		set_plugin_setting('last_servers_cache', time(), 'mollom');
	}

	Mollom::setServerList($servers);

	$CONFIG->mollom['public_key'] = $pub_key;
	$CONFIG->mollom['private_key'] = $priv_key;
	$CONFIG->mollom['servers'] = $servers;
	$CONFIG->mollom['immediately_reject_spam'] = get_plugin_setting('immediately_reject_spam', 'mollom');
	$CONFIG->mollom['use_content_filter'] = get_plugin_setting('use_content_filter', 'mollom');
	$CONFIG->mollom['use_as_captcha'] = get_plugin_setting('use_as_captcha', 'mollom');

	$count = mollom_get_volume_count('content');
	$max = mollom_get_volume_max('content');

	// check against content filter and within volume restrictions?
	if ($CONFIG->mollom['use_content_filter'] && $count < $max) {
		// scans all actions's post content for naughtiness
		// @todo "all" priorities always run after specific ones.
		register_plugin_hook('action', 'all', 'mollom_url_check', 1);
	}

	// check against profanity filter?

	// check against quality filter?

	// set up use as a captcha by registering optional views
	if ($CONFIG->mollom['use_as_captcha'] && mollom_get_volume_count('captcha') < mollom_get_volume_max('captcha')) {
		// view type is failsafe during system:init
		//$view_type = elgg_get_viewtype();
		$view_type = 'default';
		$view_location = dirname(__FILE__) . "/views_optional/";
		set_view_location('input/captcha', $view_location, $view_type);

		// include the default actions in captcha
		$actions = array(
			'register',
			'user/requestnewpassword'
		);

		$actions = trigger_plugin_hook('actionlist', 'captcha', NULL, $actions);
		$CONFIG->mollom['captcha_actions'] = $actions;

		// add these to the white list so you don't get double-captcha'd
		$CONFIG->mollom['content_filter_whitelist'] = array_merge($CONFIG->mollom['content_filter_whitelist'], $actions);

		if ($actions && is_array($actions)) {
			foreach ($actions as $action) {
				register_plugin_hook('action', $action, 'mollom_captcha_verify_action_hook');
			}
		}
	}
}

/**
 * Runs daily to reset the volume counters
 *
 */
function mollom_daily_cron() {
	mollom_set_volume_count('content', 0);
	mollom_set_volume_count('captcha', 0);

	return NULL;
}

/**
 * Set up the volume counts and maxes.
 *
 * @return true
 */
function mollom_run_once() {
	// need to do these manually or bad things happen
	set_plugin_setting('content_volume_count', 0, 'mollom');
	set_plugin_setting('content_volume_max', 100, 'mollom');

	set_plugin_setting('captcha_volume_count', 0, 'mollom');
	set_plugin_setting('captcha_volume_max', 100, 'mollom');

	return TRUE;
}

/**
 * Scans actions for any fishy content.
 *
 * @param unknown_type $hook
 * @param unknown_type $type
 * @param unknown_type $value
 * @param unknown_type $params
 * @return FALSE or NULL - FALSE if action should be stopped, NULL if action is OK here.
 * Important to return NULL to not conflict with other hooks.
 */
function mollom_url_check($hook, $action, $value, $params) {
	global $CONFIG;

	// don't try if not correctly installed or we're an admin.
	if (!$CONFIG->mollom['use_content_filter'] || isadminloggedin()) {
		return NULL;
	}

	// if this is a passthrough, check passthrough token and validate captcha
	$in_passthrough = $passthrough_problem = FALSE;

	if (mollom_in_passthrough()) {
		if (mollom_in_valid_passthrough()) {
			// lie about the referrer and pass along to main URL
			// do this so forward() works correctly
			$_SERVER['HTTP_REFERER'] = $_SESSION['mollom']['referrer'];
			unset($_SESSION['mollom']);
			return NULL;
		} else {
			$passthrough_problem = TRUE;
		}
	}

	if ($passthrough_problem) {
		register_error(elgg_echo('mollom:errors:captcha_problem'));
		$spam = $result = NULL;
	} else {
		if (!mollom_is_checked_url($_SERVER['REQUEST_URI'])) {
			return NULL;
		}

		if (!$content = mollom_get_content_string($_REQUEST)) {
			return NULL;
		}

		// if Mollom problems, allow so we don't lose data.
		if (!$result = mollom_check_content($content)) {
			return NULL;
		}

		// force a passthrough if bad
		$spam = $result['spam'];

		// save the session now that we have it.
		mollom_set_session_id($result['session_id']);
	}

	// clear out the session if this is spammy and we're not in a passthrough
	// means it's a new problem and not a bad captcha.
	if (!$in_passthrough) {
		unset($_SESSION['mollom']);
	}

	// draw the intercept page if needed
	if ($passthrough_problem || $spam == 'unsure' || ($spam == 'spam' && !$CONFIG->mollom['immediately_reject_spam'])) {
		if (!isset($_SESSION['mollom'])) {
			$_SESSION['mollom'] = array(
				'request' => $_REQUEST,
				'result' => $result,
				// since we always post to the correct URL this will be ok.
				'post_url' => $_SERVER['REQUEST_URI'],
				// need to pass the referrer along since after a post the referrer will be wrong.
				'referrer' => $_SERVER['HTTP_REFERER'],
			);
		}

		$reject = FALSE;
		mollom_increment_volume_count('intercepted');
		include dirname(__FILE__) . '/pages/breakout.php';
		exit;
	} elseif ($spam == 'spam' && $CONFIG->mollom['immediately_reject_spam']) {
		$reject = TRUE;
		mollom_increment_volume_count('rejected');
		include dirname(__FILE__) . '/pages/breakout.php';
		exit;
	}

	unset($_SESSION['mollom']);

	return NULL;
}

/**
 * Support standard captchas
 *
 * @param $type
 * @param $type
 * @param $return
 * @param $params
 */
function mollom_captcha_verify_action_hook($hook, $action, $return, $params) {
	$id = get_input('mollom_id');
	$input = get_input('mollom_captcha');

	$result = mollom_validate_captcha($id, $input);

	if ($result) {
		return NULL;
	}

	register_error(elgg_echo('mollom:errors:captcha_problem'));

	// @todo forward back to origin.
	// handle sticky forms here?
	return FALSE;
}

/**
 * Check API keys on plugin settings save.
 *
 * @param unknown_type $hook
 * @param unknown_type $action
 * @param unknown_type $value
 * @param unknown_type $params
 */
function mollom_check_api_keys($hook, $action, $value, $params) {
	if (get_input('plugin') == 'mollom') {
		$validated = FALSE;
		$params = get_input('params');
		$pub_key = (isset($params['public_key'])) ? $params['public_key'] : NULL;
		$priv_key = (isset($params['private_key'])) ? $params['private_key'] : NULL;

		if ($pub_key && $priv_key) {
			try {
				Mollom::setPublicKey($pub_key);
				Mollom::setPrivateKey($priv_key);
				$validated = Mollom::verifyKey();
			} catch (Exception $e) {
				$validated = FALSE;
			}
		}

		if (!$validated) {
			register_error(elgg_echo('mollom:settings:api_warning'));
		}

		return NULL;
	}
}

/**
 * Serves the breakout intercept page.
 *
 * @param unknown_type $page
 */
function mollom_page_handler($page) {
	if (isset($page[0])) {
		$request = $_SESSION['mollom']['request'];
		$post_url = $_SESSION['mollom']['post_url'];
		$referrer = $_SERVER['HTTP_REFERER'] = $_SESSION['mollom']['referrer'];
		$mollom_result = $_SESSION['mollom']['result'];

		switch($page[0]) {
			case 'intercepted':
				include 'pages/intercepted.php';
				exit;

			case 'rejected':
				include 'pages/rejected.php';
				exit;
		}
	}

	forward();
}

// set up mollom
register_elgg_event_handler('init', 'system', 'mollom_init', 100000);
