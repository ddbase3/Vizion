		<div id="datahawkschema"></div>

		<script>
			async function initDbDesigner(data) {
				await AssetLoader.loadScriptAsync('<?php echo $this->_['resolve']('plugin/ClientStack/assets/dbdesigner/dbdesigner.min.js'); ?>');
				console.log('DbDesigner loaded');

				$('#datahawkschema').dbdesigner().initializeFromData(data);
			}

			var data = <?php echo json_encode($this->_['data']); ?>;

			if (document.readyState === 'loading') {
				document.addEventListener('DOMContentLoaded', () => {
					initDbDesigner(data);
				});
			} else {
				initDbDesigner(data);
			}
		</script>

		<style>
			#datahawkschema { position:relative; height:600px; border-radius:5px; <?php /* box-shadow:0 0 10px #ddd; */ ?>overflow:hidden; }
			#datahawkschema * { line-height:1em; }
		</style>

