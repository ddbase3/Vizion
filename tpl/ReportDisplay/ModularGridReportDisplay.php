<?php
	$containerId = 'vizion_modulargrid_' . uniqid();
	$gridId = $containerId . '_grid';
	$logId = $containerId . '_log';
	$json = static function($value): string {
		return json_encode(
			$value,
			JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR
		);
	};
	$translations = is_array($this->_['translations'] ?? null) ? $this->_['translations'] : [];
	$modularGridStrings = $this->getBricks('clientstack_modulargrid');
	$modularGridStrings = is_array($modularGridStrings) ? $modularGridStrings : [];
	$chronoPickerStrings = $this->getBricks('clientstack_chronopicker');
	$chronoPickerStrings = is_array($chronoPickerStrings) ? $chronoPickerStrings : [];
?>
<link rel="stylesheet" href="<?php echo htmlspecialchars((string) $this->_['modulargridCssUrl'], ENT_QUOTES); ?>" />
<link rel="stylesheet" href="<?php echo htmlspecialchars((string) $this->_['chronoPickerCssUrl'], ENT_QUOTES); ?>" />

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
		flex-wrap: nowrap;
		align-items: center;
		overflow-x: auto;
	}

	.vizion-modulargrid-panel--filters .mg-control-group {
		flex-wrap: nowrap;
		align-items: center;
		row-gap: 0;
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

	.vizion-modulargrid .mg-compact-filters {
		flex-wrap: nowrap;
	}

	.vizion-modulargrid .mg-compact-filter-picker .mg-select {
		width: 145px;
		min-width: 145px;
	}

	.vizion-modulargrid .mg-compact-multiselect-summary {
		max-width: 150px;
	}

	.mg-chrono-range {
		display: inline-flex;
		align-items: center;
		gap: 4px;
		min-width: 0;
	}

	.mg-chrono-range .mg-input {
		width: 122px;
		min-width: 0;
	}

	.mg-chrono-range-separator {
		color: #777;
		font-size: 12px;
		line-height: 1;
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

<div id="<?php echo htmlspecialchars($containerId, ENT_QUOTES); ?>" class="vizion-modulargrid-shell">
	<div class="vizion-modulargrid">
		<div id="<?php echo htmlspecialchars($gridId, ENT_QUOTES); ?>"></div>
		<div id="<?php echo htmlspecialchars($logId, ENT_QUOTES); ?>" class="vizion-modulargrid-log"></div>
	</div>
</div>

<script type="module">
	import {
		AjaxAdapter,
		BulkActionsPlugin,
		CompactFiltersPlugin,
		HeaderMenuPlugin,
		InfoPlugin,
		InfiniteScrollPlugin,
		ModularGrid,
		ResetPlugin,
		RowActionsPlugin,
		RowDetailPlugin,
		SearchPlugin,
		SelectionPlugin,
		SessionStoragePlugin
	} from '<?php echo htmlspecialchars((string) $this->_['modulargridJsUrl'], ENT_QUOTES); ?>';

	const chronoPickerModule = await import(new URL(<?php echo $json((string) $this->_['chronoPickerJsUrl']); ?>, document.baseURI).href);
	const filterControlsModule = await import(new URL(<?php echo $json((string) $this->_['filterControlsJsUrl']); ?>, document.baseURI).href);
	const cellRenderersModule = await import(new URL(<?php echo $json((string) $this->_['cellRenderersJsUrl']); ?>, document.baseURI).href);
	const ENDPOINT_URL = <?php echo $json((string) $this->_['ajaxUrl']); ?>;
	const GRID_SELECTOR = <?php echo $json('#' . $gridId); ?>;
	const LOG_SELECTOR = <?php echo $json('#' . $logId); ?>;
	const REPORT_COLUMNS = <?php echo $json($this->_['columns']); ?>;
	const FILTER_FIELDS = <?php echo $json($this->_['filterFields']); ?>;
	const FILTER_INITIAL_VALUES = <?php echo $json($this->_['filterInitialValues']); ?>;
	const REPORT_CONFIG = <?php echo $json($this->_['config']); ?>;
	const TRANSLATIONS = <?php echo $json($translations); ?>;
	const MODULAR_GRID_STRINGS = <?php echo $json($modularGridStrings); ?>;
	const CHRONO_PICKER_STRINGS = <?php echo $json($chronoPickerStrings); ?>;

	function tr(key, fallback) {
		const value = String(TRANSLATIONS[key] || '').trim();
		return value !== '' ? value : fallback;
	}
	const reportFilterTools = filterControlsModule.createReportFilterTools({
		ChronoPicker: chronoPickerModule.ChronoPicker,
		DatePickerPlugin: chronoPickerModule.DatePickerPlugin,
		DateTimePlugin: chronoPickerModule.DateTimePlugin,
		KeyboardPlugin: chronoPickerModule.KeyboardPlugin,
		chronoStrings: CHRONO_PICKER_STRINGS,
		strings: {
			rangeMin: tr('range_min', 'Min'),
			rangeMax: tr('range_max', 'Max')
		}
	});
	const reportCellTools = cellRenderersModule.createReportCellRendererTools();
	const BATCH_SIZE = Number(REPORT_CONFIG?.config?.pageSize || 50);

	function createShortHash(value) {
		const text = String(value || '');
		let hash = 0;

		for (let index = 0; index < text.length; index += 1) {
			hash = ((hash << 5) - hash) + text.charCodeAt(index);
			hash |= 0;
		}

		return (hash >>> 0).toString(36);
	}

	function createFilterStorageSignature(fields, initialValues) {
		return createShortHash(JSON.stringify({
			fields: fields || [],
			initialValues: initialValues || {}
		}));
	}

	const FILTER_STORAGE_SIGNATURE = createFilterStorageSignature(FILTER_FIELDS, FILTER_INITIAL_VALUES);

	const layout = {
		type: 'stack',
		className: 'mg-layout-root',
		children: [
			{ type: 'zone', key: 'topLine1', className: 'vizion-modulargrid-panel vizion-modulargrid-panel--main' },
			{ type: 'zone', key: 'topLine2', className: 'vizion-modulargrid-panel vizion-modulargrid-panel--filters' },
			{ type: 'view', key: 'main', className: 'vizion-modulargrid-main' },
			{ type: 'zone', key: 'statusZone', className: 'vizion-modulargrid-panel vizion-modulargrid-panel--status' }
		]
	};

	function setLog(message) {
		const logElement = document.querySelector(LOG_SELECTOR);

		if (!logElement) {
			return;
		}

		logElement.innerHTML = '<strong>' + tr('last_action', 'Last action:') + '</strong> ' + message;
	}

	function getText(value, placeholder = '—') {
		if (value === null || value === undefined || value === '') {
			return placeholder;
		}

		return String(value);
	}

	function buildSortTypes(columns) {
		const sortTypes = {};

		columns.forEach((column) => {
			sortTypes[column.key] = column.type || 'string';
		});

		return sortTypes;
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
			value.textContent = reportCellTools.getRenderedText(row, column, row[column.key]);

			field.appendChild(label);
			field.appendChild(value);
			wrapper.appendChild(field);
		});

		return wrapper;
	}

	function createClipboardRecord(row) {
		const record = {};

		REPORT_COLUMNS.forEach((column) => {
			if (!column || !column.key) {
				return;
			}

			record[column.key] = row ? row[column.key] : null;
		});

		Object.entries(row || {}).forEach(([key, value]) => {
			if (String(key).startsWith('__') || Object.prototype.hasOwnProperty.call(record, key)) {
				return;
			}

			record[key] = value;
		});

		return record;
	}

	async function writeClipboardText(text) {
		if (navigator.clipboard && window.isSecureContext) {
			await navigator.clipboard.writeText(text);
			return;
		}

		const textarea = document.createElement('textarea');
		textarea.value = text;
		textarea.setAttribute('readonly', 'readonly');
		textarea.style.position = 'fixed';
		textarea.style.left = '-9999px';
		document.body.appendChild(textarea);
		textarea.select();

		try {
			document.execCommand('copy');
		} finally {
			document.body.removeChild(textarea);
		}
	}

	async function copyPayloadToClipboard(payload) {
		await writeClipboardText(JSON.stringify(payload, null, 2));
	}

	async function copyReportRow(row) {
		if (!row) {
			setLog(tr('no_record_to_copy', 'No record is available to copy.'));
			return;
		}

		try {
			await copyPayloadToClipboard(createClipboardRecord(row));
			setLog(tr('record_copied', 'Record %s was copied to the clipboard.').replace('%s', getText(row.__row_key, '')));
		} catch (error) {
			setLog(tr('record_copy_failed', 'The record could not be copied: %s').replace('%s', getText(error && error.message, String(error))));
		}
	}

	async function copySelectedReportRows(selectedRows) {
		const rows = Array.isArray(selectedRows) ? selectedRows : [];

		if (rows.length === 0) {
			setLog(tr('no_selected_records_to_copy', 'No selected records are available to copy.'));
			return;
		}

		try {
			await copyPayloadToClipboard(rows.map(createClipboardRecord));
			setLog(tr('selected_records_copied', '%s selected records were copied to the clipboard.').replace('%s', String(rows.length)));
		} catch (error) {
			setLog(tr('selected_records_copy_failed', 'The selected records could not be copied: %s').replace('%s', getText(error && error.message, String(error))));
		}
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
				const filters = reportFilterTools.buildFilterPayload(state.filters || {}, FILTER_FIELDS);
				const sortKey = request.sortKey || defaultSortKey;
				const sortDirection = request.sortDirection || defaultSortDirection;

				return {
					mode: 'page',
					page: request.page || 1,
					pageSize: request.pageSize || BATCH_SIZE,
					search: request.search || '',
					sort: sortKey ? [{ key: sortKey, dir: sortDirection, type: sortTypes[sortKey] || 'string' }] : [],
					filters,
					group: []
				};
			}
		});

		grid = new ModularGrid(GRID_SELECTOR, {
			strings: MODULAR_GRID_STRINGS,
			layout,
			adapter,
			dataMode: 'server',
			server: {
				searchDebounceMs: 260,
				watchStateKeys: ['query', 'filters']
			},
			features: { paging: false },
			pageSize: BATCH_SIZE,
			sort: defaultSortKey ? { key: defaultSortKey, direction: defaultSortDirection } : null,
			plugins: [
				SearchPlugin,
				CompactFiltersPlugin,
				HeaderMenuPlugin,
				InfoPlugin,
				SelectionPlugin,
				RowActionsPlugin,
				BulkActionsPlugin,
				ResetPlugin,
				SessionStoragePlugin,
				RowDetailPlugin,
				InfiniteScrollPlugin
			],
			pluginOptions: {
				search: { zone: 'topLine1', order: 10, label: tr('search', 'Search'), placeholder: tr('search_report_rows', 'Search report rows') },
				compactFilters: {
					zone: 'topLine2',
					order: 10,
					stateKey: 'filters',
					visibilityStateKey: 'filterVisibility',
					showClearButton: true,
					addLabel: '',
					addPlaceholder: tr('choose_filter', 'Choose filter'),
					pickerWidth: 145,
					pickerMinWidth: 145,
					clearLabel: tr('clear_filters', 'Clear filters'),
					fields: reportFilterTools.buildGridFilterFields(FILTER_FIELDS),
					initialValues: FILTER_INITIAL_VALUES
				},
				headerMenu: { showSortActions: true, showClearSortAction: true, showHideColumnAction: true },
				selection: { rowIdKey: '__row_key' },
				bulkActions: {
					zone: 'topLine1',
					order: 30,
					rowIdKey: '__row_key',
					selectedLabel: tr('selected', 'Selected'),
					emptyText: tr('no_selection', 'No selection'),
					items: [
						{ key: 'copy-selected-clipboard', label: tr('copy_selection', 'Copy selection'), onClick(context) { copySelectedReportRows(context.selectedRows || []); } },
						{ key: 'clear-selection', label: tr('clear_selection', 'Clear selection'), command: 'clearSelection' }
					]
				},
				rowActions: {
					headerMenu: {
						enabled: true,
						buttonLabel: '...',
						items: [{ type: 'columnVisibility', label: tr('columns', 'Columns'), showReset: true, resetLabel: tr('reset_columns', 'Reset columns') }]
					},
					items: [{ key: 'copy-clipboard', label: tr('copy_to_clipboard', 'Copy to clipboard'), onClick(context) { copyReportRow(context.row); } }]
				},
				reset: { zone: 'topLine1', order: 40, label: tr('reset', 'Reset'), sections: ['query', 'filters', 'filterVisibility', 'columns', 'selection', 'detailView'] },
				sessionStorage: { key: 'vizion-modulargrid-' + (REPORT_CONFIG?.report || 'report') + '-' + FILTER_STORAGE_SIGNATURE, sections: ['query', 'filters', 'filterVisibility', 'columns', 'selection', 'detailView'] },
				info: { zone: 'statusZone', order: 10, displayMode: 'loaded' },
				rowDetail: { rowIdKey: '__row_key', clearOnDataReload: true, detailRenderer(row) { return createDetailContent(row); } },
				infiniteScroll: { threshold: 180, pageSize: BATCH_SIZE, containerSelector: '.mg-table-scroll' }
			},
			columns: reportCellTools.buildColumns(REPORT_COLUMNS)
		});

		grid.on('data:appended', ({ appendedCount, totalLoaded }) => {
			setLog(tr('loaded_more_rows', 'Loaded %s more rows. %s rows are currently loaded.').replace('%s', String(appendedCount)).replace('%s', String(totalLoaded)));
		});

		grid.on('detail:changed', (payload = {}) => {
			const activeRowId = payload.rowId || null;

			if (activeRowId) {
				setLog(tr('opened_detail', 'Opened detail for %s').replace('%s', activeRowId));
			}
		});

		await grid.init();
		setLog(tr('initial_batch_loaded', 'Initial batch loaded. Scroll to append the next %s rows automatically.').replace('%s', String(BATCH_SIZE)));
	})();
</script>
