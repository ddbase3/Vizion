<?php
	$schemaContainerId = 'datahawkschema_' . uniqid();
?>
<div id="<?php echo $schemaContainerId; ?>" class="datahawkschema"></div>

<script>
	(function() {
		var containerId = <?php echo json_encode($schemaContainerId); ?>;
		var data = <?php echo json_encode($this->_['data']); ?>;
		var scriptUrl = <?php echo json_encode($this->_['resolve']('plugin/ClientStack/assets/dbdesigner/dbdesigner.min.js')); ?>;

		async function boot() {
			await AssetLoader.loadScriptAsync(scriptUrl);
			console.log('DbDesigner loaded for #' + containerId);
			$('#' + containerId).dbdesigner().initializeFromData(data);
		}

		if (document.readyState === 'loading') {
			document.addEventListener('DOMContentLoaded', boot, { once: true });
		} else {
			boot();
		}
	})();
</script>

<style>
	#<?php echo $schemaContainerId; ?> {
		position: relative;
		height: 600px;
		border-radius: 5px;
		overflow: hidden;
	}

	#<?php echo $schemaContainerId; ?> * {
		line-height: 1em;
	}
</style>
