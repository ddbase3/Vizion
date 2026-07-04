<?php
	$json = static function($value): string {
		return json_encode(
			$value,
			JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR
		);
	};

	$chronoPickerCssUrl = str_replace(
		'/modulargrid/styles/modulargrid.css',
		'/chronopicker/styles/chronopicker.css',
		(string) $this->_['modulargridCssUrl']
	);
	$chronoPickerJsUrl = str_replace(
		'/modulargrid/index.js',
		'/chronopicker/index.js',
		(string) $this->_['modulargridJsUrl']
	);
?>
<link rel="stylesheet" href="<?php echo htmlspecialchars((string) $this->_['modulargridCssUrl'], ENT_QUOTES); ?>" />
<link rel="stylesheet" href="<?php echo htmlspecialchars($chronoPickerCssUrl, ENT_QUOTES); ?>" />

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

<div class="vizion-modulargrid-shell">
	<div class="vizion-modulargrid">
		<div id="vizion-modulargrid-report"></div>
		<div id="vizion-modulargrid-report-log" class="vizion-modulargrid-log"></div>
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

	const chronoPickerModule = await import(new URL(<?php echo $json($chronoPickerJsUrl); ?>, document.baseURI).href);
	const ChronoPicker = chronoPickerModule.ChronoPicker;
	const DatePickerPlugin = chronoPickerModule.DatePickerPlugin;
	const DateTimePlugin = chronoPickerModule.DateTimePlugin;
	const KeyboardPlugin = chronoPickerModule.KeyboardPlugin;

	const ENDPOINT_URL = <?php echo $json((string) $this->_['ajaxUrl']); ?>;
	const GRID_SELECTOR = '#vizion-modulargrid-report';
	const LOG_SELECTOR = '#vizion-modulargrid-report-log';
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

		logElement.innerHTML = '<strong>Last action:</strong> ' + message;
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

	function getChronoMode(field) {
		const explicitMode = String(field.mode || '').toLowerCase();

		if (explicitMode === 'datetime') {
			return 'datetime';
		}

		const valueType = String(field.valueType || field.type || '').toLowerCase();
		return valueType === 'datetime' || valueType === 'datetimerange' ? 'datetime' : 'date';
	}

	function getChronoDisplayFormat(field) {
		if (field.format) {
			return String(field.format);
		}

		return getChronoMode(field) === 'datetime' ? 'YYYY-MM-DD HH:mm' : 'YYYY-MM-DD';
	}

	function getChronoValueFormat(field) {
		if (field.valueFormat) {
			return String(field.valueFormat);
		}

		if (field.submitFormat) {
			return String(field.submitFormat);
		}

		if (field.storageFormat) {
			return String(field.storageFormat);
		}

		if (field.queryFormat) {
			return String(field.queryFormat);
		}

		return getChronoDisplayFormat(field);
	}

	function getChronoPlaceholder(field, part) {
		if (part === 'from' && field.fromPlaceholder) {
			return String(field.fromPlaceholder);
		}

		if (part === 'to' && field.toPlaceholder) {
			return String(field.toPlaceholder);
		}

		if (field.placeholder) {
			return String(field.placeholder);
		}

		return getChronoDisplayFormat(field);
	}

	function escapeRegexChar(value) {
		const specialChars = '.*+?^$()|[]\\{}';

		return String(value).split('').map((char) => {
			return specialChars.includes(char) ? '\\' + char : char;
		}).join('');
	}

	function parseChronoParts(value, format) {
		if (value === null || value === undefined || value === '') {
			return null;
		}

		const text = String(value).trim();
		const tokens = ['YYYY', 'MM', 'DD', 'HH', 'mm'];
		const tokenPatterns = {
			YYYY: '(\\d{4})',
			MM: '(\\d{2})',
			DD: '(\\d{2})',
			HH: '(\\d{2})',
			mm: '(\\d{2})'
		};
		const tokenOrder = [];
		let pattern = '^';
		let index = 0;

		while (index < format.length) {
			const token = tokens.find((candidate) => format.slice(index).startsWith(candidate));

			if (token) {
				pattern += tokenPatterns[token];
				tokenOrder.push(token);
				index += token.length;
				continue;
			}

			pattern += escapeRegexChar(format[index]);
			index += 1;
		}

		pattern += '$';

		const match = new RegExp(pattern).exec(text);

		if (!match) {
			return null;
		}

		const parts = {
			YYYY: '1970',
			MM: '01',
			DD: '01',
			HH: '00',
			mm: '00'
		};

		tokenOrder.forEach((token, tokenIndex) => {
			parts[token] = match[tokenIndex + 1];
		});

		const year = Number(parts.YYYY);
		const month = Number(parts.MM);
		const day = Number(parts.DD);
		const hour = Number(parts.HH);
		const minute = Number(parts.mm);
		const date = new Date(year, month - 1, day, hour, minute, 0, 0);

		if (date.getFullYear() !== year || date.getMonth() !== month - 1 || date.getDate() !== day || date.getHours() !== hour || date.getMinutes() !== minute) {
			return null;
		}

		return parts;
	}

	function formatChronoParts(parts, format) {
		return String(format)
			.replaceAll('YYYY', parts.YYYY)
			.replaceAll('MM', parts.MM)
			.replaceAll('DD', parts.DD)
			.replaceAll('HH', parts.HH)
			.replaceAll('mm', parts.mm);
	}

	function convertChronoValue(value, sourceFormat, targetFormat) {
		if (value === null || value === undefined || value === '') {
			return '';
		}

		if (sourceFormat === targetFormat) {
			return String(value);
		}

		const parts = parseChronoParts(value, sourceFormat);

		if (!parts) {
			return String(value);
		}

		return formatChronoParts(parts, targetFormat);
	}

	function createChronoInput(api, part, value, commitValue) {
		const input = document.createElement('input');
		const mode = getChronoMode(api.field);
		const displayFormat = getChronoDisplayFormat(api.field);
		const valueFormat = getChronoValueFormat(api.field);
		const displayValue = convertChronoValue(value || '', valueFormat, displayFormat);
		let lastCommittedValue = value || '';

		input.type = 'text';
		input.className = 'mg-input mg-compact-filter-control cp-input-bound';
		input.placeholder = getChronoPlaceholder(api.field, part);
		input.value = displayValue;
		input.name = api.field.key + '_' + part;
		input.dataset.key = api.field.key;
		input.dataset.filterKey = api.field.key;
		input.dataset.mgFocusKey = 'filter-filters-' + api.field.key + '-' + part;

		if (api.field.inputWidth) {
			input.style.width = String(api.field.inputWidth) + 'px';
		}

		function commit(nextDisplayValue) {
			const nextValue = convertChronoValue(nextDisplayValue || '', displayFormat, valueFormat);

			if (nextValue === lastCommittedValue) {
				return;
			}

			lastCommittedValue = nextValue;
			commitValue(nextValue);
		}

		const picker = new ChronoPicker(input, {
			mode,
			displayMode: 'popover',
			value: input.value || '',
			format: displayFormat,
			min: api.field.min || null,
			max: api.field.max || null,
			minuteStep: Number(api.field.minuteStep || 1),
			closeOnSelect: Object.prototype.hasOwnProperty.call(api.field, 'closeOnSelect') ? api.field.closeOnSelect === true : mode === 'date',
			plugins: [
				DatePickerPlugin,
				DateTimePlugin,
				KeyboardPlugin
			],
			onChange(nextDisplayValue) {
				input.value = nextDisplayValue || '';
				commit(input.value);
			}
		});
		const changeHandler = () => commit(input.value || '');
		const keydownHandler = (event) => {
			if (event.key === 'Enter') {
				commit(input.value || '');
			}
		};

		input.addEventListener('change', changeHandler);
		input.addEventListener('keydown', keydownHandler);
		picker.init();

		return {
			input,
			destroy() {
				input.removeEventListener('change', changeHandler);
				input.removeEventListener('keydown', keydownHandler);

				if (typeof picker.destroy === 'function') {
					picker.destroy();
				}
			}
		};
	}

	function renderChronoDateFilter(api) {
		const control = createChronoInput(api, 'value', api.value || '', (nextValue) => {
			api.setValue(nextValue);
		});

		if (api.field.width && !api.field.inputWidth) {
			control.input.style.width = String(api.field.width) + 'px';
		}

		return {
			element: control.input,
			destroy() {
				control.destroy();
			}
		};
	}

	function renderChronoDateRangeFilter(api) {
		const wrapper = document.createElement('div');
		let value = api.value && typeof api.value === 'object' ? Object.assign({}, api.value) : { from: '', to: '' };

		wrapper.className = 'mg-chrono-range mg-compact-filter-control';

		if (api.field.width) {
			wrapper.style.width = String(api.field.width) + 'px';
		}

		function setPart(part, partValue) {
			const nextValue = Object.assign({}, value);
			nextValue[part] = partValue || '';
			value = nextValue;
			api.setValue({
				from: value.from || '',
				to: value.to || ''
			});
		}

		const fromControl = createChronoInput(api, 'from', value.from || '', (nextValue) => setPart('from', nextValue));
		const toControl = createChronoInput(api, 'to', value.to || '', (nextValue) => setPart('to', nextValue));
		const separator = document.createElement('span');
		separator.className = 'mg-chrono-range-separator';
		separator.textContent = '–';

		wrapper.appendChild(fromControl.input);
		wrapper.appendChild(separator);
		wrapper.appendChild(toControl.input);

		return {
			element: wrapper,
			destroy() {
				fromControl.destroy();
				toControl.destroy();
			}
		};
	}

	function buildGridFilterFields(fields) {
		return (fields || []).map((field) => {
			const nextField = Object.assign({}, field);
			const type = String(field.type || '').toLowerCase();
			const control = String(field.control || '').toLowerCase();
			const useChronoPicker = control === 'chronopicker' || control === 'chrono';

			if ((type === 'date' || type === 'datetime') && useChronoPicker) {
				nextField.valueType = type;
				nextField.renderControl = renderChronoDateFilter;
			}

			if (type === 'range') {
				nextField.valueType = 'range';
				nextField.renderControl = renderRangeFilter;
			}

			if (type === 'daterange' || type === 'datetimerange') {
				nextField.valueType = type;
				nextField.renderControl = useChronoPicker ? renderChronoDateRangeFilter : renderDateRangeFilter;
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
			});

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

	async function copySelectedReportRows(selectedRows) {
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
				search: {
					zone: 'topLine1',
					order: 10,
					label: 'Search',
					placeholder: 'Search report rows'
				},
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
				headerMenu: {
					showSortActions: true,
					showClearSortAction: true,
					showHideColumnAction: true
				},
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
								copySelectedReportRows(context.selectedRows || []);
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
								copyReportRow(context.row);
							}
						}
					]
				},
				reset: {
					zone: 'topLine1',
					order: 40,
					label: 'Reset',
					sections: ['query', 'filters', 'filterVisibility', 'columns', 'selection', 'detailView']
				},
				sessionStorage: {
					key: 'vizion-modulargrid-' + (REPORT_CONFIG?.report || 'report') + '-' + FILTER_STORAGE_SIGNATURE,
					sections: ['query', 'filters', 'filterVisibility', 'columns', 'selection', 'detailView']
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
			setLog('Loaded ' + appendedCount + ' more rows. ' + totalLoaded + ' rows are currently loaded.');
		});

		grid.on('detail:changed', (payload = {}) => {
			const activeRowId = payload.rowId || null;

			if (activeRowId) {
				setLog('Opened detail for ' + activeRowId);
			}
		});

		await grid.init();
		setLog('Initial batch loaded. Scroll to append the next ' + BATCH_SIZE + ' rows automatically.');
	})();
</script>
