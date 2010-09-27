<?php
/**
 * Break out of frames, ajax, etc
 *
 * Double whammy approach.
 * (Don't use header() because that doesn't work for ajax)
 */

if (!isset($reject) || !$reject) {
	$url = $CONFIG->site->url . 'pg/mollom/intercepted';
} else {
	$url = $CONFIG->site->url . 'pg/mollom/rejected';
}
?>
<html>
<head>
<meta http-equiv="refresh" content="2;url=<?php echo $url; ?>">
</head>
<body>

<script type="text/javascript">
	window.parent.location = '<?php echo $url; ?>';
</script>

<?php echo elgg_echo('mollom:please_wait'); ?>
</body>
</html>