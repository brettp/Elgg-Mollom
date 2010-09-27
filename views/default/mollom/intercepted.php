<?php
/**
 * Intercept page for a fishy action
 */

$request = $vars['request'];
$result = $vars['result'];
$post_url = $vars['post_url'];
$referrer = $vars['referrer'];
$mollom_session_id = $vars['mollom_session_id'];

$ts = time();
$token = generate_action_token($ts);

$request['mollom_ts'] = $ts;
$request['mollom_token'] = $token;
$request['mollom_passthrough'] = TRUE;

// need to pass this to avoid problems with intercepting actions
// that direct back to referrer.
$request['referrer'] = $referrer;

$inputs = mollom_make_hidden_input_html($request);

$form_body = $inputs;
$form_body .= elgg_view_title(elgg_echo('mollom:intercept'));
$form_body .= elgg_view('mollom/captcha', array(
	'mollom_session_id' => $mollom_session_id
));

$form_body .= elgg_view('input/submit', array(
	'value' => elgg_echo('submit')
));

$form_body = "<div class=\"mollom_intercept_content\">$form_body</div>";

//$message = elgg_echo('mollom:suspicious');

// allow it to post itself because mollom will hook that action.
echo elgg_view('input/form', array(
	'body' => $form_body,
	'action' => $post_url
));