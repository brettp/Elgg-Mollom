<?php
/**
 * Standard captcha interface using Mollom as the captcha
 *
 * @uses string $vars['mollom_sesion_id'] Optional mollom sessions id
 */

$type = isset($vars['type']) ? $vars['type'] : 'visual';
$mollom_session_id = (isset($vars['mollom_session_id'])) ? $vars['mollom_session_id'] : NULL;

$captcha = Mollom::getImageCaptcha($mollom_session_id);
$captcha_html = $captcha['html'];

// make sure we're grabbing the audio captcha in the same session
if (!$mollom_session_id) {
	$mollom_session_id = $captcha['session_id'];
}

$audio_captcha = Mollom::getAudioCaptcha($mollom_session_id);
$audio_captcha_html = $audio_captcha['html'];

$captcha_html .= elgg_view('input/hidden', array(
	'internalname' => 'mollom_id',
	'value' => $captcha['session_id'],
));

$input_html = elgg_view('input/text', array(
	'internalname' => 'mollom_captcha',
	'class' => 'mollom_captcha_input'
));

//echo "<p>$captcha_html<br />$audio_captcha_html</p>";
//echo "<p>$input_html</p>";
?>
<div class="captcha">
	<label>
		<?php echo elgg_echo('mollom:enter_captcha'); ?><br /><br />
		<div class="captcha-right visual_captcha">
			<?php echo $captcha_html; ?>
		</div>
		<div class="captcha-right audio_captcha">
			<?php echo $audio_captcha_html; ?>
		</div>

		<br />
		<div class="captcha-left">
			<?php echo $input_html; ?>
		</div>
	</label>
</div>