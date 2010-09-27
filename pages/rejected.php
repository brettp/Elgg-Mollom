<?php
/**
 * Content rejected because of spam / quality / profanity score
 */

$content = elgg_view('mollom/rejected', array(
	'request' => $_SESSION['mollom']['request'],
	'post_url' => $_SESSION['mollom']['post_url'],
	'mollom_result' => $_SESSION['mollom']['result'],
	'referrer' => $_SESSION['mollom']['referrer'],
));

$body = elgg_view_layout('one_column', $content);
page_draw(elgg_echo('mollom:rejected'), $body);