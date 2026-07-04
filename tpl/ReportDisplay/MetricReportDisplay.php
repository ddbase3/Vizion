<?php
	$config = is_array($this->_['config'] ?? null) ? $this->_['config'] : [];
	$displayConfig = is_array($config['config'] ?? null) ? $config['config'] : [];
	$metrics = is_array($this->_['metrics'] ?? null) ? $this->_['metrics'] : [];
	$title = trim((string) ($displayConfig['title'] ?? $config['title'] ?? ''));
	$description = trim((string) ($displayConfig['description'] ?? $config['description'] ?? ''));
	$cardMinWidth = (int) ($displayConfig['cardMinWidth'] ?? 150);
	$cardMinWidth = max(120, min(320, $cardMinWidth));
?>
<link rel="stylesheet" href="<?php echo htmlspecialchars((string) $this->_['metricCssUrl'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" />

<div class="vizion-metric-report-shell" style="--vizion-metric-card-min-width: <?php echo $cardMinWidth; ?>px;">
	<?php if($title !== '' || $description !== ''): ?>
		<div class="vizion-metric-report-header">
			<?php if($title !== ''): ?>
				<h2 class="vizion-metric-report-title"><?php echo htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></h2>
			<?php endif; ?>
			<?php if($description !== ''): ?>
				<div class="vizion-metric-report-description"><?php echo htmlspecialchars($description, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></div>
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<div class="vizion-metric-card-grid">
		<?php foreach($metrics as $metric): ?>
			<?php
				$isOk = (bool) ($metric['ok'] ?? false);
				$label = (string) ($metric['label'] ?? '');
				$value = (string) ($metric['formattedValue'] ?? '—');
				$metricDescription = trim((string) ($metric['description'] ?? ''));
				$error = trim((string) ($metric['error'] ?? ''));
			?>
			<div class="vizion-metric-card<?php echo $isOk ? '' : ' vizion-metric-card-error'; ?>">
				<div class="vizion-metric-card-label"><?php echo htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></div>
				<div class="vizion-metric-card-value"><?php echo htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></div>
				<?php if($metricDescription !== ''): ?>
					<div class="vizion-metric-card-description"><?php echo htmlspecialchars($metricDescription, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></div>
				<?php endif; ?>
				<?php if(!$isOk && $error !== ''): ?>
					<div class="vizion-metric-card-error-message"><?php echo htmlspecialchars($error, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></div>
				<?php endif; ?>
			</div>
		<?php endforeach; ?>
	</div>
</div>
