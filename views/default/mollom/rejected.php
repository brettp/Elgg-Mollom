<?php
/**
 * Content rejection notice.
 */

$title = elgg_view_title(elgg_echo('mollom:rejected'));
$message = elgg_echo('mollom:rejected_content');
$back_text = elgg_echo('mollom:go_back');
?>

<div class="mollom_intercept_content">
	<?php echo $title . $message; ?>

	<a href="javascript:history.back(-1)"><?php echo $back_text; ?></a>
</div>
