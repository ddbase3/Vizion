		<div id="datahawkschema"></div>

		<script>
			document.addEventListener('DOMContentLoaded', async () => {
				await AssetLoader.loadScriptAsync('<?php echo $this->_['resolve']('plugin/ClientStack/assets/dbdesigner/dbdesigner.min.js'); ?>');
				console.log('DbDesigner loaded');

				var data = <?php echo json_encode($this->_['data']); ?>;
				$('#datahawkschema').dbdesigner().initializeFromData(data);
			});
		</script>

		<style>
			#datahawkschema { position:relative; height:600px; border-radius:5px; box-shadow:0 0 10px #ddd; overflow:hidden; }
			#datahawkschema * { line-height:1em; }
		</style>

