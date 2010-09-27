<?php
/**
 * Mollom language strings.
 *
 * @package Mollom
 */

$english = array(
	'mollom:intercept' => 'Please validate your post.',
	'mollom:enter_captcha' => 'Type the words into the box below to continue.',

	'mollom:rejected_content' => 'The content you are trying to post has been identified as spam and will not be saved.  If you feel this is an error, please contact an administrator.',
	'mollom:go_back' => 'Return to previous page.',
	'mollom:rejected' => 'Content not accepted.',
	'mollom:please_wait' => 'Please wait...',

	// settings
	'mollom:settings' => 'You must register for an account at <a href="http://mollom.com">Mollom</a> before you can use these services.  '
		. 'Once registered, enter your public and private APIs keys.  Mollom.com accounts have a daily volume limit for captcha and content '
		. 'filter requests. Exceeding this limit could cause Mollom to cancel your account.  Enter accurate values for the max to avoid problems '
		. 'with the service.  (Don\'t change the "used" fields unless there is a problem resetting your daily volume count.)',
	'mollom:settings:pub_key' => 'Public Key',
	'mollom:settings:priv_key' => 'Private Key',
	'mollom:settings:use_content_filter' => 'Filter and intercept content.',
	'mollom:settings:use_content_filter_desc' => 'Content will be sent to Mollom\'s servers for inspection.  If it is suspicious, the user will be asked to enter a captcha before continuing.',
	'mollom:settings:use_as_captcha' => 'Use Mollom as the default captcha.',
	'mollom:settings:use_as_captcha_desc' => 'Use Mollom\'s captcha services as the default captcha in supported plugins.',
	'mollom:settings:api_warning' => 'WARNING: Mollom cannot verify your API keys.  No functions will work until valid keys are entered.  Please check the values and try again.',
	'mollom:settings:immediately_reject_spam' => 'Immediately reject content reported as spam. (Requries above.)',
	'mollom:settings:immediately_reject_spam_desc' => 'Enabling this options will immediately reject all content reported as spam. This can help with content from human spam farms, but could cause false positives.  When disabled, users who attempt to post content that is reported as spam will be asked to enter a captcha to continue.',

	'mollom:settings:captcha_volume' => 'Captcha volume: %s used of %s max.',
	'mollom:settings:content_volume' => 'Content volume: %s used of %s max.',

	// errors
	'mollom:errors:captcha_problem' => 'The text you entered does not match the text in the image.  Please try again.',
	'mollom:error:cannot_update_servers' => 'Error updating Mollom servers.  Please contact a system administrator.',

);

add_translation('en', $english);