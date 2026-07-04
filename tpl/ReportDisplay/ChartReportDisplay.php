<?php
	$containerId = 'vizion_chart_' . uniqid();
	$filterId = $containerId . '_filters';
	$canvasId = $containerId . '_canvas';
	$logId = $containerId . '_log';
	$json = static function($value): string {
		return json_encode(
			$value,
			JSON_UNESCAPED_UNICODE
			| JSON_UNESCAPED_SLASHES
			| JSON_INVALID_UTF8_SUBSTITUTE
			| JSON_THROW_ON_ERROR
		);
	};
?>
<link rel="stylesheet" href="<?php echo htmlspecialchars($this->_['modulargridCssUrl']); ?>" />
<link rel="stylesheet" href="<?php echo htmlspecialchars($this->_['chronoPickerCssUrl']); ?>" />
<link rel="stylesheet" href="<?php echo htmlspecialchars($this->_['chartCssUrl']); ?>" />

<div id="<?php echo htmlspecialchars($containerId); ?>" class="vizion-chart-report-shell">
	<div id="<?php echo htmlspecialchars($filterId); ?>" class="vizion-chart-filter-shell"></div>
	<div class="vizion-chart-panel">
		<canvas id="<?php echo htmlspecialchars($canvasId); ?>" class="vizion-chart-canvas"></canvas>
	</div>
	<div id="<?php echo htmlspecialchars($logId); ?>" class="vizion-chart-log"></div>
</div>

<script type="module">
	import * as modularGridModule from '<?php echo htmlspecialchars($this->_['modulargridJsUrl']); ?>';
	import * as chronoPickerModule from '<?php echo htmlspecialchars($this->_['chronoPickerJsUrl']); ?>';
	import * as filterControlsModule from '<?php echo htmlspecialchars($this->_['filterControlsJsUrl']); ?>';
	import * as cellRenderersModule from '<?php echo htmlspecialchars($this->_['cellRenderersJsUrl']); ?>';
	import * as chartToolsModule from '<?php echo htmlspecialchars($this->_['chartToolsJsUrl']); ?>';

	const AjaxAdapter = modularGridModule.AjaxAdapter;
	const CompactFiltersPlugin = modularGridModule.CompactFiltersPlugin;
	const ModularGrid = modularGridModule.ModularGrid;
	const SessionStoragePlugin = modularGridModule.SessionStoragePlugin;
	const ChronoPicker = chronoPickerModule.ChronoPicker;
	const DatePickerPlugin = chronoPickerModule.DatePickerPlugin;
	const DateTimePlugin = chronoPickerModule.DateTimePlugin;
	const KeyboardPlugin = chronoPickerModule.KeyboardPlugin;
	const createReportFilterTools = filterControlsModule.createReportFilterTools;
	const createReportCellRendererTools = cellRenderersModule.createReportCellRendererTools;
	const createReportChartController = chartToolsModule.createReportChartController;

	const ENDPOINT_URL = <?php echo $json($this->_['ajaxUrl']); ?>;
	const FILTER_SELECTOR = <?php echo $json('#' . $filterId); ?>;
	const CANVAS_SELECTOR = <?php echo $json('#' . $canvasId); ?>;
	const LOG_SELECTOR = <?php echo $json('#' . $logId); ?>;
	const REPORT_CONFIG = <?php echo $json($this->_['config']); ?>;
	const FILTER_FIELDS = <?php echo $json($this->_['filterFields']); ?>;
	const FILTER_INITIAL_VALUES = <?php echo $json($this->_['filterInitialValues']); ?>;
	const CHART_CONFIG = <?php echo $json($this->_['chartConfig']); ?>;
	const CHART_JS_URL = <?php echo $json($this->_['chartJsUrl']); ?>;

	const filterTools = createReportFilterTools({
		ChronoPicker,
		DatePickerPlugin,
		DateTimePlugin,
		KeyboardPlugin
	});
	const cellTools = createReportCellRendererTools();

	const filterLayout = {
		type: 'stack',
		className: 'mg-layout-root vizion-chart-filter-layout',
		children: [
			{
				type: 'zone',
				key: 'topLine2',
				className: 'vizion-chart-filter-panel vizion-chart-filter-panel-filters'
			},
			{
				type: 'view',
				key: 'main',
				className: 'vizion-chart-filter-state-view'
			}
		]
	};

	function valueSignature(value) {
		try {
			return JSON.stringify(value);
		} catch (error) {
			return String(value);
		}
	}

	function buildStateSignature(state) {
		return valueSignature({
			filters: state && state.filters ? state.filters : {}
		});
	}

	function buildChartFilterPayload(state) {
		return filterTools.buildFilterPayload(state && state.filters ? state.filters : {}, FILTER_FIELDS);
	}

	(async function() {
		const canvas = document.querySelector(CANVAS_SELECTOR);
		const logElement = document.querySelector(LOG_SELECTOR);

		if (!(canvas instanceof HTMLCanvasElement)) {
			return;
		}

		await import(new URL(CHART_JS_URL, document.baseURI).href);

		const filterGrid = new ModularGrid(FILTER_SELECTOR, {
			layout: filterLayout,
			data: [],
			features: {
				paging: false
			},
			plugins: [
				CompactFiltersPlugin,
				SessionStoragePlugin
			],
			pluginOptions: {
				compactFilters: {
					zone: 'topLine2',
					order: 10,
					stateKey: 'filters',
					visibilityStateKey: 'filterVisibility',
					showClearButton: true,
					addLabel: '',
					addPlaceholder: 'Filter',
					pickerWidth: 140,
					clearLabel: 'Filter löschen',
					fields: filterTools.buildGridFilterFields(FILTER_FIELDS),
					initialValues: FILTER_INITIAL_VALUES
				},
				sessionStorage: {
					key: 'vizion-chart-' + String(REPORT_CONFIG.report || 'report') + '-' + valueSignature({ filters: FILTER_FIELDS, chart: CHART_CONFIG }),
					sections: ['filters', 'filterVisibility']
				}
			},
			columns: []
		});

		await filterGrid.init();

		const chartController = createReportChartController({
			Chart: globalThis.Chart,
			canvas,
			endpointUrl: ENDPOINT_URL,
			chartConfig: CHART_CONFIG,
			formatValue: cellTools.formatValue,
			logElement,
			getRequestPayload() {
				const state = filterGrid.getState();
				return {
					mode: 'chart',
					filters: buildChartFilterPayload(state)
				};
			}
		});

		let lastSignature = buildStateSignature(filterGrid.getState());
		let reloadTimer = null;

		filterGrid.on('state:changed', (eventPayload) => {
			const current = eventPayload && eventPayload.current ? eventPayload.current : {};
			const nextSignature = buildStateSignature(current);

			if (nextSignature === lastSignature) {
				return;
			}

			lastSignature = nextSignature;

			if (reloadTimer) {
				window.clearTimeout(reloadTimer);
			}

			reloadTimer = window.setTimeout(() => {
				reloadTimer = null;
				chartController.reload();
			}, 180);
		});

		await chartController.reload();
	})();
</script>
