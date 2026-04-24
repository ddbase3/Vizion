<link rel="stylesheet" href="<?php echo htmlspecialchars((string) $this->_['modulargridCssUrl'], ENT_QUOTES); ?>" />

<style>
	.vizion-modulargrid-shell {
		width: 100%;
		max-width: 100%;
	}

	.vizion-modulargrid-panel {
		display: flex;
		align-items: center;
		flex-wrap: nowrap;
		gap: 8px;
		min-width: 0;
		width: 100%;
		padding: 8px 10px;
		border: 1px solid #e2e2e2;
		border-radius: 8px;
		background: #fff;
		overflow-x: auto;
	}

	.vizion-modulargrid-panel--filters {
		flex-wrap: wrap;
		align-items: flex-start;
		overflow-x: visible;
	}

	.vizion-modulargrid-panel--filters .mg-control-group {
		flex-wrap: wrap;
		align-items: center;
		row-gap: 8px;
	}

	.vizion-modulargrid-panel > * {
		flex: 0 0 auto;
	}

	.vizion-modulargrid-main {
		border: 1px solid #e2e2e2;
		border-radius: 8px;
		background: #fff;
		padding: 4px 0;
	}

	.vizion-modulargrid .mg-control-group {
		flex-direction: row;
		align-items: center;
		gap: 6px;
		min-width: auto;
	}

	.vizion-modulargrid .mg-label {
		white-space: nowrap;
		color: #666;
		font-size: 12px;
	}

	.vizion-modulargrid .mg-inline-buttons {
		flex-wrap: nowrap;
	}

	.vizion-modulargrid .mg-input,
	.vizion-modulargrid .mg-select,
	.vizion-modulargrid .mg-button {
		min-height: 28px;
		font-size: 13px;
	}

	.vizion-modulargrid .mg-input {
		width: auto;
	}

	.vizion-modulargrid input[type="search"].mg-input {
		width: 260px;
	}

	.vizion-modulargrid .mg-select {
		width: auto;
		min-width: 120px;
	}

	.vizion-modulargrid .mg-table-scroll {
		height: 540px;
		overflow: auto;
		padding-bottom: 4px;
	}

	.vizion-modulargrid .mg-table thead th {
		position: sticky;
		top: 0;
		z-index: 12;
		background: #fff;
	}

	.vizion-modulargrid .mg-table thead th.mg-cell-pinned {
		z-index: 14;
	}

	.vizion-modulargrid .mg-table th,
	.vizion-modulargrid .mg-table td {
		padding: 6px 8px;
		font-size: 13px;
		vertical-align: top;
	}

	.vizion-modulargrid-cell {
		display: block;
		max-width: 100%;
		white-space: normal;
		word-break: break-word;
	}

	.vizion-modulargrid-cell-mono {
		font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
		font-size: 12px;
	}

	.vizion-modulargrid-log {
		margin-top: 12px;
		padding: 8px 0 0 0;
		border-top: 1px solid #e2e2e2;
		font-size: 13px;
		color: #555;
	}

	.vizion-modulargrid-log strong {
		color: #222;
	}

	.vizion-modulargrid .mg-row-detail-value,
	.vizion-modulargrid .mg-row-detail-field-value {
		white-space: pre-wrap;
		word-break: break-word;
		font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
		font-size: 12px;
	}

	@media (max-width: 720px) {
		.vizion-modulargrid .mg-table-scroll {
			height: 420px;
		}
	}
</style>

<div class="vizion-modulargrid-shell">
	<div class="vizion-modulargrid">
		<div id="vizion-modulargrid-report"></div>
		<div id="vizion-modulargrid-report-log" class="vizion-modulargrid-log"></div>
	</div>
</div>

<script type="module">
	import {
		AjaxAdapter,
		ColumnVisibilityPlugin,
		FiltersPlugin,
		HeaderMenuPlugin,
		InfoPlugin,
		InfiniteScrollPlugin,
		ModularGrid,
		ResetPlugin,
		RowDetailPlugin,
		SearchPlugin,
		SessionStoragePlugin
	} from '<?php echo htmlspecialchars((string) $this->_['modulargridJsUrl'], ENT_QUOTES); ?>';

	const ENDPOINT_URL = <?php echo json_encode((string) $this->_['ajaxUrl'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
	const GRID_SELECTOR = '#vizion-modulargrid-report';
	const LOG_SELECTOR = '#vizion-modulargrid-report-log';
	const REPORT_COLUMNS = <?php echo json_encode($this->_['columns'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
	const FILTER_FIELDS = <?php echo json_encode($this->_['filterFields'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
	const REPORT_CONFIG = <?php echo json_encode($this->_['config'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
	const BATCH_SIZE = Number(REPORT_CONFIG?.config?.pageSize || 50);

	const layout = {
		type: 'stack',
		className: 'mg-layout-root',
		children: [
			{
				type: 'zone',
				key: 'topLine1',
				className: 'vizion-modulargrid-panel vizion-modulargrid-panel--main'
			},
			{
				type: 'zone',
				key: 'topLine2',
				className: 'vizion-modulargrid-panel vizion-modulargrid-panel--filters'
			},
			{
				type: 'view',
				key: 'main',
				className: 'vizion-modulargrid-main'
			},
			{
				type: 'zone',
				key: 'statusZone',
				className: 'vizion-modulargrid-panel vizion-modulargrid-panel--status'
			}
		]
	};

	function setLog(message) {
		const logElement = document.querySelector(LOG_SELECTOR);

		if (!logElement) {
			return;
		}

		logElement.innerHTML = `<strong>Last action:</strong> ${message}`;
	}

	function getText(value, placeholder = '—') {
		if (value === null || value === undefined || value === '') {
			return placeholder;
		}

		return String(value);
	}

	function formatValue(value, column) {
		if (value === null || value === undefined || value === '') {
			return '—';
		}

		const type = String(column.type || '').toLowerCase();

		if (type === 'int' || type === 'integer') {
			const number = Number(value);

			if (!Number.isNaN(number)) {
				return new Intl.NumberFormat(undefined, {
					maximumFractionDigits: 0
				}).format(number);
			}
		}

		if (type === 'float' || type === 'decimal' || type === 'number') {
			const number = Number(value);

			if (!Number.isNaN(number)) {
				return new Intl.NumberFormat(undefined, {
					maximumFractionDigits: 2
				}).format(number);
			}
		}

		if (type === 'date' || type === 'datetime') {
			const date = new Date(String(value).replace(' ', 'T'));

			if (!Number.isNaN(date.getTime())) {
				return new Intl.DateTimeFormat(undefined, {
					year: 'numeric',
					month: '2-digit',
					day: '2-digit',
					hour: type === 'datetime' ? '2-digit' : undefined,
					minute: type === 'datetime' ? '2-digit' : undefined
				}).format(date);
			}
		}

		if (typeof value === 'object') {
			return JSON.stringify(value, null, 2);
		}

		return String(value);
	}

	function renderCell(value, row, column) {
		const wrapper = document.createElement('span');
		const type = String(column.type || '').toLowerCase();
		const lines = Number(column.lines || 0);

		wrapper.className = 'vizion-modulargrid-cell';

		if (type === 'json' || type === 'code') {
			wrapper.classList.add('vizion-modulargrid-cell-mono');
		}

		wrapper.textContent = formatValue(value, column);

		if (lines > 0) {
			wrapper.style.display = '-webkit-box';
			wrapper.style.webkitLineClamp = String(lines);
			wrapper.style.webkitBoxOrient = 'vertical';
			wrapper.style.overflow = 'hidden';
		}

		return wrapper;
	}

	function buildFilterPayload(filters) {
		const result = {};

		Object.entries(filters || {}).forEach(([key, value]) => {
			if (value === '' || value === null || value === undefined) {
				return;
			}

			result[key] = value;
		});

		return result;
	}

	function buildSortTypes(columns) {
		const sortTypes = {};

		columns.forEach((column) => {
			sortTypes[column.key] = column.type || 'string';
		});

		return sortTypes;
	}

	function buildColumns(columns) {
		return columns.map((column) => {
			const gridColumn = {
				...column,
				headerMenu: {
					defaultSortKey: column.key,
					defaultSortDirection: 'asc',
					sortOptions: [
						{
							key: column.key,
							label: column.label || column.key
						}
					]
				},
				render(value, row) {
					return renderCell(value, row, column);
				}
			};

			if (Number(column.width || 0) > 0) {
				gridColumn.width = Number(column.width);
			}

			return gridColumn;
		});
	}

	function createDetailContent(row) {
		const wrapper = document.createElement('div');
		wrapper.className = 'mg-row-detail-fields';

		REPORT_COLUMNS.forEach((column) => {
			const field = document.createElement('div');
			field.className = 'mg-row-detail-field';

			const label = document.createElement('div');
			label.className = 'mg-row-detail-label';
			label.textContent = column.label || column.key;

			const value = document.createElement('div');
			value.className = 'mg-row-detail-value';
			value.textContent = formatValue(row[column.key], column);

			field.appendChild(label);
			field.appendChild(value);
			wrapper.appendChild(field);
		});

		return wrapper;
	}

	(async function() {
		const root = document.querySelector(GRID_SELECTOR);

		if (!root || root.dataset.initialized === '1') {
			return;
		}

		root.dataset.initialized = '1';

		const sortTypes = buildSortTypes(REPORT_COLUMNS);
		const defaultSortKey = REPORT_CONFIG?.config?.sortColumn || REPORT_COLUMNS[0]?.key || null;
		const defaultSortDirection = REPORT_CONFIG?.config?.sortDirection || 'asc';
		let grid = null;

		const adapter = new AjaxAdapter({
			url: ENDPOINT_URL,
			method: 'POST',
			rowsPath: 'data',
			totalPath: 'total',
			mapRequest(request) {
				const state = grid ? grid.getState() : {};
				const filters = buildFilterPayload(state.filters || {});
				const sortKey = request.sortKey || defaultSortKey;
				const sortDirection = request.sortDirection || defaultSortDirection;

				return {
					mode: 'page',
					page: request.page || 1,
					pageSize: request.pageSize || BATCH_SIZE,
					search: request.search || '',
					sort: sortKey
						? [
							{
								key: sortKey,
								dir: sortDirection,
								type: sortTypes[sortKey] || 'string'
							}
						]
						: [],
					filters,
					group: []
				};
			}
		});

		grid = new ModularGrid(GRID_SELECTOR, {
			layout,
			adapter,
			dataMode: 'server',
			server: {
				searchDebounceMs: 260,
				watchStateKeys: ['query', 'filters']
			},
			features: {
				paging: false
			},
			pageSize: BATCH_SIZE,
			sort: defaultSortKey
				? {
					key: defaultSortKey,
					direction: defaultSortDirection
				}
				: null,
			plugins: [
				SearchPlugin,
				FiltersPlugin,
				HeaderMenuPlugin,
				InfoPlugin,
				ColumnVisibilityPlugin,
				ResetPlugin,
				SessionStoragePlugin,
				RowDetailPlugin,
				InfiniteScrollPlugin
			],
			pluginOptions: {
				search: {
					zone: 'topLine1',
					order: 10,
					label: 'Search',
					placeholder: 'Search report rows'
				},
				filters: {
					zone: 'topLine2',
					order: 10,
					stateKey: 'filters',
					showClearButton: true,
					clearLabel: 'Clear filters',
					fields: FILTER_FIELDS
				},
				headerMenu: {
					showSortActions: true,
					showClearSortAction: true,
					showHideColumnAction: true
				},
				columnVisibility: {
					zone: ''
				},
				reset: {
					zone: 'topLine1',
					order: 20,
					label: 'Reset',
					sections: ['query', 'filters', 'columns', 'detailView']
				},
				sessionStorage: {
					key: `vizion-modulargrid-${REPORT_CONFIG?.report || 'report'}`,
					sections: ['query', 'filters', 'columns', 'detailView']
				},
				info: {
					zone: 'statusZone',
					order: 10,
					displayMode: 'loaded'
				},
				rowDetail: {
					rowIdKey: '__row_key',
					clearOnDataReload: true,
					detailRenderer(row) {
						return createDetailContent(row);
					}
				},
				infiniteScroll: {
					threshold: 180,
					pageSize: BATCH_SIZE,
					containerSelector: '.mg-table-scroll'
				}
			},
			columns: buildColumns(REPORT_COLUMNS)
		});

		grid.on('data:appended', ({ appendedCount, totalLoaded }) => {
			setLog(`Loaded ${appendedCount} more rows. ${totalLoaded} rows are currently loaded.`);
		});

		grid.on('detail:changed', (payload = {}) => {
			const activeRowId = payload.rowId || null;

			if (activeRowId) {
				setLog(`Opened detail for ${activeRowId}`);
			}
		});

		await grid.init();
		setLog(`Initial batch loaded. Scroll to append the next ${BATCH_SIZE} rows automatically.`);
	})();
</script>
