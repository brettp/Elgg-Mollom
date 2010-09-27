<?php
/**
 * Draws the intercept page
 *
 */

$intercept_content = elgg_view('mollom/intercepted', array(
	'request' => $_SESSION['mollom']['request'],
	'post_url' => $_SESSION['mollom']['post_url'],
	'result' => $_SESSION['mollom']['result'],
	'referrer' => $_SESSION['mollom']['referrer'],
	'mollom_session_id' => mollom_get_session_id()
));

$body = elgg_view_layout('one_column', $intercept_content);
page_draw(elgg_echo('mollom:intercept'), $body);