<?php
	$schemaContainerId = 'datahawkschema_' . uniqid();
	$scopeData = is_array($this->_['scopeData'] ?? null) ? $this->_['scopeData'] : [];
	$scopes = is_array($this->_['scopes'] ?? null) ? $this->_['scopes'] : [];
	$selectedScope = (string)($this->_['selectedScope'] ?? '');
	$translations = is_array($this->_['translations'] ?? null) ? $this->_['translations'] : [];
	$t = static function(string $key, string $fallback) use ($translations): string {
		$value = trim((string)($translations[$key] ?? ''));
		return $value !== '' ? $value : $fallback;
	};
	$scopeContainers = [];
	foreach ($scopes as $index => $scope) {
		$scopeContainers[(string)$scope] = $schemaContainerId . '_' . $index;
	}
?>
<div class="datahawkschema-shell">
	<?php if (count($scopes) > 1): ?>
		<div class="datahawkschema-toolbar">
			<label for="<?php echo $schemaContainerId; ?>_scope"><?php echo htmlspecialchars($t('scope', 'Scope'), ENT_QUOTES); ?></label>
			<select id="<?php echo $schemaContainerId; ?>_scope">
				<?php foreach ($scopes as $scope): ?>
					<option value="<?php echo htmlspecialchars((string)$scope, ENT_QUOTES); ?>"<?php echo (string)$scope === $selectedScope ? ' selected' : ''; ?>><?php echo htmlspecialchars((string)$scope, ENT_QUOTES); ?></option>
				<?php endforeach; ?>
			</select>
		</div>
	<?php endif; ?>

	<?php foreach ($scopeContainers as $scope => $containerId): ?>
		<div id="<?php echo $containerId; ?>" class="datahawkschema" data-scope="<?php echo htmlspecialchars($scope, ENT_QUOTES); ?>"></div>
	<?php endforeach; ?>
</div>

<script>
	(function() {
		var scopeData = <?php echo json_encode($scopeData); ?>;
		var scopeContainers = <?php echo json_encode($scopeContainers); ?>;
		var selectedScope = <?php echo json_encode($selectedScope); ?>;
		var scopeSelectId = <?php echo json_encode($schemaContainerId . '_scope'); ?>;
		var scriptUrl = <?php echo json_encode($this->_['resolve']('plugin/ClientStack/assets/dbdesigner/dbdesigner.min.js')); ?>;
		var initialized = {};

		function showScope(scope) {
			Object.keys(scopeContainers).forEach(function(candidate) {
				var element = document.getElementById(scopeContainers[candidate]);
				if(element) {
					element.style.display = candidate === scope ? 'block' : 'none';
				}
			});

			if(!scopeContainers[scope] || initialized[scope]) {
				return;
			}

			var containerId = scopeContainers[scope];
			$('#' + containerId).dbdesigner().initializeFromData(scopeData[scope] || {data: [], foreignKeys: []});
			initialized[scope] = true;
		}

		async function boot() {
			await AssetLoader.loadScriptAsync(scriptUrl);

			var scopeSelect = document.getElementById(scopeSelectId);
			if(scopeSelect) {
				scopeSelect.addEventListener('change', function() {
					showScope(scopeSelect.value);
				});
			}

			if(!selectedScope || !scopeContainers[selectedScope]) {
				selectedScope = Object.keys(scopeContainers)[0] || '';
			}

			if(selectedScope) {
				showScope(selectedScope);
			}
		}

		if (document.readyState === 'loading') {
			document.addEventListener('DOMContentLoaded', boot, { once: true });
		} else {
			boot();
		}
	})();
</script>

<style>
	.datahawkschema-toolbar {
		display: flex;
		align-items: center;
		gap: 8px;
		margin-bottom: 12px;
	}

	.datahawkschema-toolbar select {
		min-width: 220px;
		padding: 6px 8px;
	}

	.datahawkschema {
		position: relative;
		height: 600px;
		border-radius: 5px;
		overflow: hidden;
		display: none;
	}

	.datahawkschema * {
		line-height: 1em;
	}
</style>
