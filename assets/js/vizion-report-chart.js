function asArray(value) {
	return Array.isArray(value) ? value : [];
}

function getText(value, fallback) {
	if (value === null || value === undefined || value === '') {
		return fallback;
	}

	return String(value);
}

function normalizeDataset(dataset, index) {
	const nextDataset = Object.assign({}, dataset);
	const measure = nextDataset.vizionMeasure || {};

	nextDataset.label = nextDataset.label || measure.label || measure.alias || 'Dataset ' + String(index + 1);
	nextDataset.data = asArray(nextDataset.data).map((value) => Number(value) || 0);

	delete nextDataset.vizionMeasure;

	return nextDataset;
}

function buildTooltipLabel(formatValue, datasetMeasures) {
	return function(context) {
		const label = context.dataset && context.dataset.label ? String(context.dataset.label) : '';
		const rawValue = context.raw;
		const measure = datasetMeasures[context.datasetIndex] || {};
		const formatter = measure.formatter || { type: 'number' };
		const formatted = formatValue(rawValue, { formatter });

		return label ? label + ': ' + formatted : formatted;
	};
}

function buildChartOptions(baseOptions, payload, formatValue) {
	const options = Object.assign({}, baseOptions || {});
	const chart = payload && payload.chart ? payload.chart : {};
	const dimension = chart.dimension || {};
	const datasets = asArray(payload && payload.datasets ? payload.datasets : []);
	const datasetMeasures = datasets.map((dataset) => dataset.vizionMeasure || {});

	options.plugins = Object.assign({}, options.plugins || {});
	options.plugins.tooltip = Object.assign({}, options.plugins.tooltip || {});
	options.plugins.tooltip.callbacks = Object.assign({}, options.plugins.tooltip.callbacks || {});
	options.plugins.tooltip.callbacks.label = buildTooltipLabel(formatValue, datasetMeasures);

	options.plugins.tooltip.callbacks.title = function(items) {
		const item = Array.isArray(items) && items.length > 0 ? items[0] : null;
		return item ? String(item.label || '') : '';
	};

	if (!options.scales && chart.type !== 'pie' && chart.type !== 'doughnut') {
		options.scales = {
			x: {
				ticks: {}
			},
			y: {
				beginAtZero: true
			}
		};
	}

	return options;
}

function buildChartJsConfig(payload, fallbackConfig, formatValue) {
	const chart = payload && payload.chart ? payload.chart : fallbackConfig || {};
	const rawLabels = asArray(payload && payload.labels ? payload.labels : []);
	const dimension = chart.dimension || {};
	const labels = rawLabels.map((label) => formatValue(label, { formatter: dimension.formatter || { type: 'text' } }));
	const datasets = asArray(payload && payload.datasets ? payload.datasets : []).map((dataset, index) => normalizeDataset(dataset, index));

	return {
		type: chart.type || fallbackConfig.type || 'bar',
		data: {
			labels,
			datasets
		},
		options: buildChartOptions(chart.options || fallbackConfig.options || {}, payload, formatValue)
	};
}

function setLog(logElement, message) {
	if (!(logElement instanceof HTMLElement)) {
		return;
	}

	logElement.textContent = message;
}

async function fetchChartPayload(endpointUrl, requestPayload) {
	const response = await fetch(endpointUrl, {
		method: 'POST',
		headers: {
			'Content-Type': 'application/json'
		},
		body: JSON.stringify(requestPayload || {})
	});

	if (!response.ok) {
		throw new Error('Chart request failed with status ' + String(response.status) + '.');
	}

	const payload = await response.json();

	if (!payload || payload.ok === false) {
		throw new Error(payload && payload.error ? String(payload.error) : 'Chart request returned an invalid response.');
	}

	return payload;
}

export function createReportChartController(options = {}) {
	const Chart = options.Chart;
	const canvas = options.canvas;
	const endpointUrl = String(options.endpointUrl || '');
	const chartConfig = options.chartConfig || {};
	const formatValue = typeof options.formatValue === 'function' ? options.formatValue : ((value) => getText(value, '—'));
	const getRequestPayload = typeof options.getRequestPayload === 'function' ? options.getRequestPayload : (() => ({}));
	const logElement = options.logElement || null;
	let chart = null;

	if (typeof Chart !== 'function') {
		throw new Error('Chart.js is not available.');
	}

	if (!(canvas instanceof HTMLCanvasElement)) {
		throw new Error('A chart canvas element is required.');
	}

	async function reload() {
		setLog(logElement, 'Diagramm wird geladen...');

		try {
			const payload = await fetchChartPayload(endpointUrl, getRequestPayload());
			const nextConfig = buildChartJsConfig(payload, chartConfig, formatValue);

			if (chart && typeof chart.destroy === 'function') {
				chart.destroy();
			}

			chart = new Chart(canvas.getContext('2d'), nextConfig);
			setLog(logElement, 'Diagramm geladen: ' + String(payload.total || 0) + ' Gruppen.');
			return payload;
		} catch (error) {
			setLog(logElement, error instanceof Error ? error.message : String(error));
			throw error;
		}
	}

	function destroy() {
		if (chart && typeof chart.destroy === 'function') {
			chart.destroy();
		}

		chart = null;
	}

	return {
		reload,
		destroy
	};
}
