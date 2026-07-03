<?php
	$json = static function($value): string {
		return json_encode(
			$value,
			JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR
		);
	};
?>
<link rel="stylesheet" href="<?php echo htmlspecialchars((string) $this->_['modulargridCssUrl'], ENT_QUOTES); ?>" />

<style>
	.vizion-matrix-report-shell {
		max-width: 100%;
	}

	.vizion-matrix-report-grid .vizion-matrix-report-panel {
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

	.vizion-matrix-report-grid .vizion-matrix-report-panel--filters {
		flex-wrap: nowrap;
		align-items: center;
		overflow-x: auto;
	}

	.vizion-matrix-report-grid .vizion-matrix-report-panel--filters .mg-control-group {
		flex-wrap: nowrap;
		align-items: center;
		row-gap: 0;
	}

	.vizion-matrix-report-grid .vizion-matrix-report-panel > * {
		flex: 0 0 auto;
	}

	.vizion-matrix-report-grid .vizion-matrix-report-main {
		border: 1px solid #e2e2e2;
		border-radius: 8px;
		background: #fff;
		padding: 4px 0;
	}

	.vizion-matrix-report-grid .mg-control-group {
		flex-direction: row;
		align-items: center;
		gap: 6px;
		min-width: auto;
	}

	.vizion-matrix-report-grid .mg-label {
		white-space: nowrap;
		color: #666;
		font-size: 12px;
	}

	.vizion-matrix-report-grid .mg-inline-buttons,
	.vizion-matrix-report-grid .mg-compact-filters {
		flex-wrap: nowrap;
	}

	.vizion-matrix-report-grid .mg-compact-filter-picker .mg-select {
		width: 145px;
		min-width: 145px;
	}

	.vizion-matrix-report-grid .mg-compact-multiselect-summary {
		max-width: 150px;
	}

	.vizion-matrix-report-grid .mg-input,
	.vizion-matrix-report-grid .mg-select,
	.vizion-matrix-report-grid .mg-button {
		min-height: 28px;
		font-size: 13px;
	}

	.vizion-matrix-report-grid input[type="search"].mg-input {
		width: 260px;
	}

	.vizion-matrix-report-grid .mg-table-scroll {
		height: 540px;
		overflow: auto;
		padding-bottom: 4px;
	}

	.vizion-matrix-report-grid .mg-table thead th {
		position: sticky;
		top: 0;
		z-index: 12;
		background: #fff;
	}

	.vizion-matrix-report-grid .mg-table th,
	.vizion-matrix-report-grid .mg-table td {
		padding: 6px 8px;
		font-size: 13px;
		vertical-align: top;
	}

	.vizion-matrix-report-cell {
		display: block;
		max-width: 100%;
		white-space: normal;
		word-break: break-word;
	}

	.vizion-matrix-report-detail-status {
		padding: 10px 12px;
		font-size: 13px;
		color: #555;
	}

	.vizion-matrix-report-detail-status-error {
		color: #9b1c1c;
	}

	.vizion-matrix-report-detail {
		display: grid;
		gap: 12px;
		min-width: 0;
		padding: 4px 0;
	}

	.vizion-matrix-report-detail-header {
		display: flex;
		align-items: flex-start;
		justify-content: space-between;
		gap: 12px;
		min-width: 0;
	}

	.vizion-matrix-report-detail-title {
		font-size: 16px;
		font-weight: 600;
		color: #222;
		line-height: 1.25;
	}

	.vizion-matrix-report-detail-summary {
		margin-top: 2px;
		font-size: 13px;
		color: #666;
		line-height: 1.35;
	}

	.vizion-matrix-report-detail-actions {
		display: inline-flex;
		gap: 6px;
		flex: 0 0 auto;
	}

	.vizion-matrix-report-button {
		appearance: none;
		border: 1px solid #cfcfcf;
		border-radius: 4px;
		background: #fff;
		color: #222;
		cursor: pointer;
		font: inherit;
		font-size: 12px;
		line-height: 1.3;
		padding: 4px 8px;
		white-space: nowrap;
	}

	.vizion-matrix-report-button:hover {
		background: #f5f5f5;
	}

	.vizion-matrix-report-table-scroll {
		max-width: 100%;
		max-height: 620px;
		overflow: auto;
		border: 1px solid #e2e2e2;
		border-radius: 8px;
		background: #fff;
	}

	.vizion-matrix-report-table {
		width: max-content;
		min-width: 100%;
		border-collapse: separate;
		border-spacing: 0;
		font-size: 12px;
	}

	.vizion-matrix-report-table th,
	.vizion-matrix-report-table td {
		padding: 5px 7px;
		border-right: 1px solid #eeeeee;
		border-bottom: 1px solid #eeeeee;
		vertical-align: top;
		background: #fff;
	}

	.vizion-matrix-report-table thead th {
		position: sticky;
		top: 0;
		z-index: 4;
		background: #f8f8f8;
		font-weight: 600;
		white-space: nowrap;
	}

	.vizion-matrix-report-table th:first-child,
	.vizion-matrix-report-table td:first-child {
		position: sticky;
		left: 0;
		z-index: 3;
		min-width: 260px;
		max-width: 340px;
		background: #fff;
	}

	.vizion-matrix-report-table thead th:first-child {
		z-index: 6;
		background: #f8f8f8;
	}

	.vizion-matrix-report-table th:nth-child(n+4),
	.vizion-matrix-report-table td:nth-child(n+4) {
		min-width: 125px;
		max-width: 180px;
	}

	.vizion-matrix-report-table-empty {
		padding: 16px;
		color: #666;
		text-align: center;
	}

	.vizion-matrix-report-status {
		display: inline-flex;
		align-items: center;
		justify-content: center;
		max-width: 100%;
		padding: 2px 7px;
		border: 1px solid #d6d6d6;
		border-radius: 999px;
		background: #fafafa;
		color: #444;
		font-size: 11px;
		line-height: 1.3;
		white-space: nowrap;
	}

	.vizion-matrix-report-status-completed {
		border-color: #97c99f;
		background: #eff8f0;
		color: #245d2a;
	}

	.vizion-matrix-report-status-in-progress {
		border-color: #d5bd75;
		background: #fff7df;
		color: #6d5200;
	}

	.vizion-matrix-report-status-failed {
		border-color: #dca0a0;
		background: #fff0f0;
		color: #7b1f1f;
	}

	.vizion-matrix-report-cell-sub {
		margin-top: 2px;
		font-size: 11px;
		line-height: 1.25;
		color: #777;
	}

	.vizion-matrix-report-log {
		margin-top: 12px;
		padding: 8px 0 0 0;
		border-top: 1px solid #e2e2e2;
		font-size: 13px;
		color: #555;
	}

	.vizion-matrix-report-log strong {
		color: #222;
	}

	@media (max-width: 720px) {
		.vizion-matrix-report-grid .mg-table-scroll {
			height: 420px;
		}
	}
</style>

<div class="vizion-matrix-report-shell">
	<div class="vizion-matrix-report-grid">
		<div id="vizion-matrix-report"></div>
		<div id="vizion-matrix-report-log" class="vizion-matrix-report-log"></div>
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

	const ENDPOINT_URL = <?php echo $json((string) $this->_['ajaxUrl']); ?>;
	const GRID_SELECTOR = '#vizion-matrix-report';
	const LOG_SELECTOR = '#vizion-matrix-report-log';
	const REPORT_COLUMNS = <?php echo $json($this->_['columns']); ?>;
	const FILTER_FIELDS = <?php echo $json($this->_['filterFields']); ?>;
	const FILTER_INITIAL_VALUES = <?php echo $json($this->_['filterInitialValues']); ?>;
	const REPORT_CONFIG = <?php echo $json($this->_['config']); ?>;
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
			{ type: 'zone', key: 'topLine1', className: 'vizion-matrix-report-panel vizion-matrix-report-panel--main' },
			{ type: 'zone', key: 'topLine2', className: 'vizion-matrix-report-panel vizion-matrix-report-panel--filters' },
			{ type: 'view', key: 'main', className: 'vizion-matrix-report-main' },
			{ type: 'zone', key: 'statusZone', className: 'vizion-matrix-report-panel vizion-matrix-report-panel--status' }
		]
	};

	function setLog(message) {
		const logElement = document.querySelector(LOG_SELECTOR);
		if (logElement) {
			logElement.innerHTML = '<strong>Last action:</strong> ' + escapeHtml(getText(message));
		}
	}

	function escapeHtml(value) {
		return String(value).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
	}

	function getText(value, placeholder = '—') {
		if (value === null || value === undefined || value === '') {
			return placeholder;
		}
		return String(value);
	}

	function createElement(tagName, className = '', text = null) {
		const element = document.createElement(tagName);
		if (className !== '') {
			element.className = className;
		}
		if (text !== null && text !== undefined) {
			element.textContent = String(text);
		}
		return element;
	}

	function createButton(label, onClick) {
		const button = document.createElement('button');
		button.type = 'button';
		button.className = 'vizion-matrix-report-button';
		button.textContent = label;
		button.addEventListener('click', (event) => {
			event.preventDefault();
			event.stopPropagation();
			onClick(button);
		});
		return button;
	}

	function formatValue(value, column) {
		if (value === null || value === undefined || value === '') {
			return '—';
		}

		const type = String(column.type || '').toLowerCase();

		if (type === 'int' || type === 'integer' || type === 'number') {
			const number = Number(value);
			if (!Number.isNaN(number)) {
				return new Intl.NumberFormat(undefined, { maximumFractionDigits: type === 'number' ? 2 : 0 }).format(number);
			}
		}

		if (typeof value === 'object') {
			return getText(value.label || value.value || '');
		}

		return String(value);
	}

	function renderCell(value, row, column) {
		const wrapper = document.createElement('span');
		wrapper.className = 'vizion-matrix-report-cell';
		wrapper.textContent = formatValue(value, column);
		return wrapper;
	}

	function getFilterField(key) {
		return FILTER_FIELDS.find((field) => field.key === key) || null;
	}

	function valueSignature(value) {
		try {
			return JSON.stringify(value);
		} catch (error) {
			return String(value);
		}
	}

	function valuesEqual(left, right) {
		return valueSignature(left) === valueSignature(right);
	}

	function getEmptyFilterValue(field) {
		if (field && Object.prototype.hasOwnProperty.call(field, 'emptyValue')) {
			return field.emptyValue;
		}

		const type = String(field?.type || '').toLowerCase();

		if (type === 'multiselect') {
			return [];
		}

		if (type === 'range') {
			return { min: '', max: '' };
		}

		if (type === 'daterange' || type === 'datetimerange') {
			return { from: '', to: '' };
		}

		if (type === 'checkbox') {
			return false;
		}

		return '';
	}

	function isEmptyFilterValue(key, value) {
		const field = getFilterField(key);
		const emptyValue = getEmptyFilterValue(field);

		if (valuesEqual(value, emptyValue)) {
			return true;
		}

		if (Array.isArray(value)) {
			return value.length === 0;
		}

		if (value && typeof value === 'object') {
			return Object.values(value).every((entry) => entry === '' || entry === null || entry === undefined || (Array.isArray(entry) && entry.length === 0));
		}

		return value === '' || value === null || value === undefined;
	}

	function buildFilterPayload(filters) {
		const result = {};

		Object.entries(filters || {}).forEach(([key, value]) => {
			if (!isEmptyFilterValue(key, value)) {
				result[key] = value;
			}
		});

		return result;
	}

	function renderRangeFilter(api) {
		const wrapper = document.createElement('div');
		const value = api.value && typeof api.value === 'object' ? api.value : {};
		const minInput = document.createElement('input');
		const maxInput = document.createElement('input');

		wrapper.className = 'mg-inline-buttons mg-compact-filter-control';
		minInput.type = 'number';
		maxInput.type = 'number';
		minInput.className = 'mg-input';
		maxInput.className = 'mg-input';
		minInput.placeholder = 'Min';
		maxInput.placeholder = 'Max';
		minInput.value = value.min ?? '';
		maxInput.value = value.max ?? '';

		[minInput, maxInput].forEach((input) => {
			input.dataset.key = api.field.key;
			input.dataset.filterKey = api.field.key;
			input.style.width = '82px';
			['min', 'max', 'step'].forEach((attribute) => {
				if (api.field[attribute] !== undefined && api.field[attribute] !== null) {
					input.setAttribute(attribute, String(api.field[attribute]));
				}
			});
		});

		const update = () => api.setValue({ min: minInput.value, max: maxInput.value });
		minInput.addEventListener('change', update);
		maxInput.addEventListener('change', update);

		wrapper.appendChild(minInput);
		wrapper.appendChild(maxInput);

		return wrapper;
	}

	function renderDateRangeFilter(api) {
		const wrapper = document.createElement('div');
		const value = api.value && typeof api.value === 'object' ? api.value : {};
		const fromInput = document.createElement('input');
		const toInput = document.createElement('input');
		const inputType = api.field.valueType === 'datetimerange' ? 'datetime-local' : 'date';

		wrapper.className = 'mg-inline-buttons mg-compact-filter-control';
		fromInput.type = inputType;
		toInput.type = inputType;
		fromInput.className = 'mg-input';
		toInput.className = 'mg-input';
		fromInput.value = value.from ?? '';
		toInput.value = value.to ?? '';

		[fromInput, toInput].forEach((input) => {
			input.dataset.key = api.field.key;
			input.dataset.filterKey = api.field.key;
			input.style.width = inputType === 'datetime-local' ? '180px' : '135px';
		});

		const update = () => api.setValue({ from: fromInput.value, to: toInput.value });
		fromInput.addEventListener('change', update);
		toInput.addEventListener('change', update);

		wrapper.appendChild(fromInput);
		wrapper.appendChild(toInput);

		return wrapper;
	}

	function buildGridFilterFields(fields) {
		return (fields || []).map((field) => {
			const nextField = Object.assign({}, field);
			const type = String(field.type || '').toLowerCase();

			if (type === 'range') {
				nextField.type = 'custom';
				nextField.valueType = 'range';
				nextField.renderControl = renderRangeFilter;
			}

			if (type === 'daterange' || type === 'datetimerange') {
				nextField.type = 'custom';
				nextField.valueType = type;
				nextField.renderControl = renderDateRangeFilter;
			}

			return nextField;
		});
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
			const gridColumn = Object.assign({}, column, {
				headerMenu: {
					defaultSortKey: column.key,
					defaultSortDirection: 'asc',
					sortOptions: [{ key: column.key, label: column.label || column.key }]
				},
				render(value, row) {
					return renderCell(value, row, column);
				}
			});

			if (Number(column.width || 0) > 0) {
				gridColumn.width = Number(column.width);
			}

			return gridColumn;
		});
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

	async function copyMatrixRow(row) {
		if (!row) {
			setLog('Kein Datensatz zum Kopieren vorhanden.');
			return;
		}

		try {
			await copyPayloadToClipboard(createClipboardRecord(row));
			setLog('Datensatz ' + getText(row.__row_key, '') + ' wurde in die Zwischenablage kopiert.');
		} catch (error) {
			setLog('Datensatz konnte nicht kopiert werden: ' + getText(error && error.message, String(error)));
		}
	}

	async function copySelectedMatrixRows(selectedRows) {
		const rows = Array.isArray(selectedRows) ? selectedRows : [];

		if (rows.length === 0) {
			setLog('Keine ausgewählten Datensätze zum Kopieren vorhanden.');
			return;
		}

		try {
			await copyPayloadToClipboard(rows.map(createClipboardRecord));
			setLog(String(rows.length) + ' ausgewählte Datensätze wurden in die Zwischenablage kopiert.');
		} catch (error) {
			setLog('Ausgewählte Datensätze konnten nicht kopiert werden: ' + getText(error && error.message, String(error)));
		}
	}

	async function postJson(payload) {
		const response = await fetch(ENDPOINT_URL, {
			method: 'POST',
			headers: { 'Content-Type': 'application/json' },
			body: JSON.stringify(payload)
		});

		if (!response.ok) {
			throw new Error('Request failed with status ' + String(response.status));
		}

		const json = await response.json();
		if (json && json.ok === false) {
			throw new Error(getText(json.error, 'Request failed.'));
		}

		return json;
	}

	async function loadRemoteDetail(context) {
		const row = context && context.row ? context.row : null;
		const parameter = getText(REPORT_CONFIG?.detail?.parameter, 'id');
		const id = Number(row && (row[parameter] || row.id));

		if (!id) {
			throw new Error('Missing matrix detail parameter.');
		}

		const response = await postJson({ mode: 'matrix-detail', [parameter]: id, id });

		if (!response || !response.found || !response.detail) {
			throw new Error(getText(response && response.error, 'No matrix detail returned.'));
		}

		return response.detail;
	}

	function createDetailLoadingPlaceholder(context) {
		const row = context && context.row ? context.row : null;
		const wrapper = createElement('div', 'vizion-matrix-report-detail-status');
		wrapper.textContent = 'Loading matrix for ' + getText(row && (row.main_course_title || row.title || row.id), 'row') + ' ...';
		return wrapper;
	}

	function createDetailErrorPlaceholder(context, error) {
		const wrapper = createElement('div', 'vizion-matrix-report-detail-status vizion-matrix-report-detail-status-error');
		wrapper.textContent = 'Matrix could not be loaded: ' + getText(error && error.message, String(error || 'unknown error'));
		return wrapper;
	}

	function getStatusClass(status) {
		const normalized = String(status || '').toUpperCase().replace(/_/g, '-');
		if (normalized === 'COMPLETED') return 'completed';
		if (normalized === 'IN-PROGRESS') return 'in-progress';
		if (normalized === 'FAILED') return 'failed';
		return 'not-attempted';
	}

	function renderStatusCell(value, status = '') {
		const wrapper = document.createElement('div');
		const pill = document.createElement('span');
		const effectiveStatus = status || (value && typeof value === 'object' ? value.status : '');
		pill.className = 'vizion-matrix-report-status vizion-matrix-report-status-' + getStatusClass(effectiveStatus);
		pill.textContent = getText(value && typeof value === 'object' ? value.label : value);
		wrapper.appendChild(pill);

		if (value && typeof value === 'object') {
			const sub = [];
			if (value.percentage) sub.push(value.percentage);
			if (value.mark) sub.push(value.mark);
			if (sub.length > 0) {
				wrapper.appendChild(createElement('div', 'vizion-matrix-report-cell-sub', sub.join(' / ')));
			}
		}

		return wrapper;
	}

	function renderMatrixTable(payload) {
		const tableScroll = createElement('div', 'vizion-matrix-report-table-scroll');
		const table = document.createElement('table');
		table.className = 'vizion-matrix-report-table';
		const columns = Array.isArray(payload.columns) ? payload.columns : [];
		const rows = Array.isArray(payload.rows) ? payload.rows : [];
		const thead = document.createElement('thead');
		const headRow = document.createElement('tr');

		columns.forEach((column) => {
			const th = document.createElement('th');
			th.textContent = column.label || column.key;
			headRow.appendChild(th);
		});

		thead.appendChild(headRow);
		table.appendChild(thead);

		const tbody = document.createElement('tbody');
		if (rows.length === 0 || columns.length === 0) {
			const emptyRow = document.createElement('tr');
			const emptyCell = document.createElement('td');
			emptyCell.colSpan = Math.max(columns.length, 1);
			emptyCell.className = 'vizion-matrix-report-table-empty';
			emptyCell.textContent = 'No matrix data found.';
			emptyRow.appendChild(emptyCell);
			tbody.appendChild(emptyRow);
		} else {
			rows.forEach((row) => {
				const tr = document.createElement('tr');
				columns.forEach((column) => {
					const td = document.createElement('td');
					const value = row[column.key];
					if (column.type === 'status') {
						td.appendChild(renderStatusCell(value, row[column.statusKey] || ''));
					} else {
						td.textContent = getText(value);
					}
					tr.appendChild(td);
				});
				tbody.appendChild(tr);
			});
		}

		table.appendChild(tbody);
		tableScroll.appendChild(table);
		return tableScroll;
	}

	function toggleDetailFullscreen(button) {
		const detail = button.closest('.mg-row-detail');
		if (!detail) return;
		if (!document.fullscreenElement && detail.requestFullscreen) {
			detail.requestFullscreen();
			return;
		}
		if (document.fullscreenElement && document.exitFullscreen) {
			document.exitFullscreen();
		}
	}

	function renderMatrixDetail(context) {
		const payload = context && context.payload ? context.payload : null;
		if (!payload || typeof payload !== 'object') {
			return document.createTextNode(getText(payload));
		}

		const wrapper = createElement('div', 'vizion-matrix-report-detail');
		const header = createElement('div', 'vizion-matrix-report-detail-header');
		const headerText = document.createElement('div');
		const actions = createElement('div', 'vizion-matrix-report-detail-actions');

		headerText.appendChild(createElement('div', 'vizion-matrix-report-detail-title', getText(payload.headline, 'Matrix')));
		headerText.appendChild(createElement('div', 'vizion-matrix-report-detail-summary', getText(payload.summary, '')));
		actions.appendChild(createButton('Fullscreen', (button) => toggleDetailFullscreen(button)));
		header.appendChild(headerText);
		header.appendChild(actions);
		wrapper.appendChild(header);
		wrapper.appendChild(renderMatrixTable(payload));
		return wrapper;
	}

	(async function() {
		const root = document.querySelector(GRID_SELECTOR);
		if (!root || root.dataset.initialized === '1') return;
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
					sort: sortKey ? [{ key: sortKey, dir: sortDirection, type: sortTypes[sortKey] || 'string' }] : [],
					filters,
					group: []
				};
			}
		});

		grid = new ModularGrid(GRID_SELECTOR, {
			layout,
			adapter,
			dataMode: 'server',
			server: { searchDebounceMs: 260, watchStateKeys: ['query', 'filters'] },
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
				search: { zone: 'topLine1', order: 10, label: 'Search', placeholder: 'Search matrix headlines' },
				compactFilters: {
					zone: 'topLine2',
					order: 10,
					stateKey: 'filters',
					visibilityStateKey: 'filterVisibility',
					showClearButton: true,
					addLabel: '',
					addPlaceholder: 'Filter wählen',
					pickerWidth: 145,
					pickerMinWidth: 145,
					clearLabel: 'Filter löschen',
					fields: buildGridFilterFields(FILTER_FIELDS),
					initialValues: FILTER_INITIAL_VALUES
				},
				headerMenu: { showSortActions: true, showClearSortAction: true, showHideColumnAction: true },
				selection: {
					rowIdKey: '__row_key'
				},
				bulkActions: {
					zone: 'topLine1',
					order: 30,
					rowIdKey: '__row_key',
					selectedLabel: 'Ausgewählt',
					emptyText: 'Keine Auswahl',
					items: [
						{
							key: 'copy-selected-clipboard',
							label: 'Auswahl kopieren',
							onClick(context) {
								copySelectedMatrixRows(context.selectedRows || []);
							}
						},
						{
							key: 'clear-selection',
							label: 'Auswahl löschen',
							command: 'clearSelection'
						}
					]
				},
				rowActions: {
					headerMenu: {
						enabled: true,
						buttonLabel: '...',
						items: [
							{
								type: 'columnVisibility',
								label: 'Spalten',
								showReset: true,
								resetLabel: 'Spalten zurücksetzen'
							}
						]
					},
					items: [
						{
							key: 'copy-clipboard',
							label: 'In Zwischenablage kopieren',
							onClick(context) {
								copyMatrixRow(context.row);
							}
						}
					]
				},
				reset: { zone: 'topLine1', order: 40, label: 'Reset', sections: ['query', 'filters', 'filterVisibility', 'columns', 'selection', 'detailView'] },
				sessionStorage: { key: 'vizion-matrix-report-' + (REPORT_CONFIG?.report || 'report') + '-' + FILTER_STORAGE_SIGNATURE, sections: ['query', 'filters', 'filterVisibility', 'columns', 'selection', 'detailView'] },
				info: { zone: 'statusZone', order: 10, displayMode: 'loaded' },
				rowDetail: {
					rowIdKey: '__row_key',
					clearOnDataReload: true,
					asyncDetail: {
						load(context) { return loadRemoteDetail(context); },
						renderLoading(context) { return createDetailLoadingPlaceholder(context); },
						renderError(context) { return createDetailErrorPlaceholder(context, context.error); },
						render(context) { return renderMatrixDetail(context); }
					}
				},
				infiniteScroll: { threshold: 180, pageSize: BATCH_SIZE, containerSelector: '.mg-table-scroll' }
			},
			columns: buildColumns(REPORT_COLUMNS)
		});

		grid.on('data:appended', ({ appendedCount, totalLoaded }) => setLog('Loaded ' + appendedCount + ' more rows. ' + totalLoaded + ' rows are currently loaded.'));
		grid.on('detail:changed', (payload = {}) => {
			if (payload.rowId) setLog('Opened matrix detail for ' + payload.rowId);
		});

		await grid.init();
		setLog('Initial batch loaded. Scroll to append the next ' + BATCH_SIZE + ' rows automatically.');
	})();
</script>
