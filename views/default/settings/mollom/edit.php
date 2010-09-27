<?php
/**
 * Settings for Mollom
 */

$pub_key = get_plugin_setting('public_key', 'mollom');
$priv_key = get_plugin_setting('private_key', 'mollom');
$use_content_filter = get_plugin_setting('use_content_filter', 'mollom');
$immediately_reject_spam = get_plugin_setting('immediately_reject_spam', 'mollom');
$use_as_captcha = get_plugin_setting('use_as_captcha', 'mollom');

$content_volume_count = mollom_get_volume_count('content');
$content_volume_max = mollom_get_volume_max('content');

$captcha_volume_count = mollom_get_volume_count('captcha');
$captcha_volume_max = mollom_get_volume_max('captcha');

$api_warning = '';

if ($pub_key && $priv_key) {
	$validated = FALSE;
	try {
		Mollom::setPublicKey($pub_key);
		Mollom::setPrivateKey($priv_key);
		$validated = Mollom::verifyKey();
	} catch (Exception $e) {
		$validated = FALSE;
	}

	if (!$validated) {
		$api_warning = '<h2 style="color: red">' . elgg_echo('mollom:settings:api_warning') . '</h2>';
	}
}

$pub_key_label = elgg_echo('mollom:settings:pub_key');
$pub_key_html = elgg_view('input/text', array(
	'internalname' => 'params[public_key]',
	'value' => $pub_key
));

$priv_key_label = elgg_echo('mollom:settings:priv_key');
$priv_key_html = elgg_view('input/text', array(
	'internalname' => 'params[private_key]',
	'value' => $priv_key
));

// still problems here.
//$use_as_captcha_html = elgg_view('input/checkboxes', array(
//	'internalname' => 'params[use_as_captcha]',
//	'options' => array(elgg_echo('mollom:use_as_captcha') => TRUE)
//));

$checked = ($use_content_filter) ? 'checked = "checked"' : '';
$use_content_filter_html = "
<p>
	<input type=\"hidden\" name=\"params[use_content_filter]\" value=\"0\" />
	<input type=\"checkbox\" name=\"params[use_content_filter]\" value=\"1\" $checked/>
	" . elgg_echo('mollom:settings:use_content_filter') . "
	<span class=\"mollom_settings_tip\">" . elgg_echo('mollom:settings:use_content_filter_desc') . "</span>
</p>
";

$checked = ($immediately_reject_spam) ? 'checked = "checked"' : '';
$immediately_reject_spam_html = "
<p>
	<input type=\"hidden\" name=\"params[immediately_reject_spam]\" value=\"0\" />
	<input type=\"checkbox\" name=\"params[immediately_reject_spam]\" value=\"1\" $checked/>
	" . elgg_echo('mollom:settings:immediately_reject_spam') . "
	<span class=\"mollom_settings_tip\">" . elgg_echo('mollom:settings:immediately_reject_spam_desc') . "</span>
</p>
";

// @todo strictness, profanity & quality threshold

$checked = ($use_as_captcha) ? 'checked = "checked"' : '';
$use_as_captcha_html = "
<p>
	<input type=\"hidden\" name=\"params[use_as_captcha]\" value=\"0\" />
	<input type=\"checkbox\" name=\"params[use_as_captcha]\" value=\"1\" $checked/>
	" . elgg_echo('mollom:settings:use_as_captcha') . "
	<span class=\"mollom_settings_tip\">" . elgg_echo('mollom:settings:use_as_captcha_desc') . "</span>
</p>
";

// captcha
$captcha_volume_max_input = elgg_view('input/text', array(
	'internalname' => 'params[captcha_volume_max]',
	'value' => $captcha_volume_max,
	'class' => 'mollom_settings_int'
));

$captcha_volume_count_input = elgg_view('input/text', array(
	'internalname' => 'params[captcha_volume_count]',
	'value' => $captcha_volume_count,
	'class' => 'mollom_settings_int'
));

$captcha_volume_html = sprintf(elgg_echo('mollom:settings:captcha_volume'), $captcha_volume_count_input, $captcha_volume_max_input);

// content
$content_volume_max_input = elgg_view('input/text', array(
	'internalname' => 'params[content_volume_max]',
	'value' => $content_volume_max,
	'class' => 'mollom_settings_int'
));


$content_volume_count_input = elgg_view('input/text', array(
	'internalname' => 'params[content_volume_count]',
	'value' => $content_volume_count,
	'class' => 'mollom_settings_int'
));

$content_volume_html = sprintf(elgg_echo('mollom:settings:content_volume'), $content_volume_count_input, $content_volume_max_input);

?>

<?php echo $api_warning; ?>

<p><?php echo elgg_echo('mollom:settings'); ?></p>

<label>
	<?php echo $pub_key_label . $pub_key_html; ?>
</label>

<label>
	<?php echo $priv_key_label . $priv_key_html; ?>
</label>

<p><?php echo $content_volume_html; ?></p>
<p><?php echo $captcha_volume_html; ?></p>

<label>
	<?php echo $use_content_filter_html; ?>
</label>

<label>
	<?php echo $immediately_reject_spam_html; ?>
</label>

<label>
	<?php echo $use_as_captcha_html; ?>
</label>