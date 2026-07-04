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

function isEmptyFilterValue(key, value, fields) {
	const field = (fields || []).find((entry) => entry && entry.key === key) || null;
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

function createChronoInput(api, part, value, commitValue, dependencies) {
	const { ChronoPicker, DatePickerPlugin, DateTimePlugin, KeyboardPlugin } = dependencies;
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
		plugins: [DatePickerPlugin, DateTimePlugin, KeyboardPlugin].filter((plugin) => plugin),
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

function renderChronoDateFilter(api, dependencies) {
	const control = createChronoInput(api, 'value', api.value || '', (nextValue) => {
		api.setValue(nextValue);
	}, dependencies);

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

function renderChronoDateRangeFilter(api, dependencies) {
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

	const fromControl = createChronoInput(api, 'from', value.from || '', (nextValue) => setPart('from', nextValue), dependencies);
	const toControl = createChronoInput(api, 'to', value.to || '', (nextValue) => setPart('to', nextValue), dependencies);
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

export function createReportFilterTools(dependencies = {}) {
	const controls = {
		'vizion.range': renderRangeFilter,
		'vizion.dateRange': renderDateRangeFilter,
		'vizion.chronopickerDate': (api) => renderChronoDateFilter(api, dependencies),
		'vizion.chronopickerDateRange': (api) => renderChronoDateRangeFilter(api, dependencies)
	};

	return {
		buildGridFilterFields(fields) {
			return (fields || []).map((field) => {
				const nextField = Object.assign({}, field);
				const key = String(nextField.renderControlKey || '');

				if (key && controls[key]) {
					nextField.renderControl = controls[key];
				}

				return nextField;
			});
		},

		buildFilterPayload(filters, fields) {
			const result = {};

			Object.entries(filters || {}).forEach(([key, value]) => {
				if (!isEmptyFilterValue(key, value, fields)) {
					result[key] = value;
				}
			});

			return result;
		}
	};
}
