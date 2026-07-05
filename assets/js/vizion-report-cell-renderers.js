function getText(value, placeholder = '—') {
	if (value === null || value === undefined || value === '') {
		return placeholder;
	}

	return String(value);
}

function escapeRegexChar(value) {
	const specialChars = '.*+?^$()|[]\\{}';

	return String(value).split('').map(function(char) {
		return specialChars.includes(char) ? '\\' + char : char;
	}).join('');
}

function parseDateParts(value, format) {
	if (value === null || value === undefined || value === '') {
		return null;
	}

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
	const text = String(value).trim();

	while (index < format.length) {
		const token = tokens.find(function(candidate) {
			return format.slice(index).startsWith(candidate);
		});

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

	const parts = { YYYY: '1970', MM: '01', DD: '01', HH: '00', mm: '00' };

	tokenOrder.forEach(function(token, tokenIndex) {
		parts[token] = match[tokenIndex + 1];
	});

	return parts;
}

function formatDateParts(parts, format) {
	return String(format)
		.replaceAll('YYYY', parts.YYYY)
		.replaceAll('MM', parts.MM)
		.replaceAll('DD', parts.DD)
		.replaceAll('HH', parts.HH)
		.replaceAll('mm', parts.mm);
}

function formatDate(value, formatter) {
	const valueFormat = formatter.valueFormat || (formatter.type === 'datetime' ? 'YYYY-MM-DD HH:mm' : 'YYYY-MM-DD');
	const displayFormat = formatter.format || (formatter.type === 'datetime' ? 'DD.MM.YYYY HH:mm' : 'DD.MM.YYYY');
	const parts = parseDateParts(value, valueFormat) || parseDateParts(String(value).replace('T', ' '), 'YYYY-MM-DD HH:mm') || parseDateParts(value, 'YYYY-MM-DD');

	if (!parts) {
		const date = new Date(String(value).replace(' ', 'T'));

		if (!Number.isNaN(date.getTime())) {
			return new Intl.DateTimeFormat(undefined, {
				year: 'numeric',
				month: '2-digit',
				day: '2-digit',
				hour: formatter.type === 'datetime' ? '2-digit' : undefined,
				minute: formatter.type === 'datetime' ? '2-digit' : undefined
			}).format(date);
		}

		return String(value);
	}

	return formatDateParts(parts, displayFormat);
}

function formatEnum(value, formatter) {
	const valueText = String(value ?? '');
	const options = Array.isArray(formatter.options) ? formatter.options : [];
	const match = options.find(function(entry) {
		return String(entry && typeof entry === 'object' ? entry.value : entry) === valueText;
	});

	if (!match) {
		return getText(value);
	}

	return String(match && typeof match === 'object' ? (match.label ?? match.value ?? '') : match);
}

function formatValue(value, column = {}) {
	if (value === null || value === undefined || value === '') {
		return '—';
	}

	const formatter = column.formatter || {};
	const formatterType = String(formatter.type || column.type || '').toLowerCase();

	if (formatterType === 'enum') {
		return formatEnum(value, formatter);
	}

	if (formatterType === 'date' || formatterType === 'datetime') {
		return formatDate(value, formatter);
	}

	if (formatterType === 'int' || formatterType === 'integer') {
		const number = Number(value);

		if (!Number.isNaN(number)) {
			return new Intl.NumberFormat(undefined, { maximumFractionDigits: 0 }).format(number);
		}
	}

	if (formatterType === 'float' || formatterType === 'decimal' || formatterType === 'number') {
		const number = Number(value);

		if (!Number.isNaN(number)) {
			return new Intl.NumberFormat(undefined, {
				minimumFractionDigits: Number(formatter.minimumFractionDigits || 0),
				maximumFractionDigits: Number(formatter.maximumFractionDigits ?? 2)
			}).format(number);
		}
	}

	if (typeof value === 'object') {
		return JSON.stringify(value, null, 2);
	}

	return String(value);
}

function getRowValue(row, key, currentValue) {
	if (key === 'value') {
		return currentValue;
	}

	if (!row || typeof row !== 'object') {
		return undefined;
	}

	if (Object.prototype.hasOwnProperty.call(row, key)) {
		return row[key];
	}

	const parts = String(key).split('.');
	let current = row;

	for (const part of parts) {
		if (!part || !current || typeof current !== 'object' || !Object.prototype.hasOwnProperty.call(current, part)) {
			return undefined;
		}

		current = current[part];
	}

	return current;
}

function createTextElement(text, column = {}) {
	const wrapper = document.createElement('span');
	const lines = Number(column.lines || 0);

	wrapper.className = 'vizion-modulargrid-cell';

	if (column.monospace) {
		wrapper.classList.add('vizion-modulargrid-cell-mono');
	}

	wrapper.textContent = text;

	if (lines > 0) {
		wrapper.style.display = '-webkit-box';
		wrapper.style.webkitLineClamp = String(lines);
		wrapper.style.webkitBoxOrient = 'vertical';
		wrapper.style.overflow = 'hidden';
	}

	return wrapper;
}

function createHtmlElement(html, column = {}) {
	const wrapper = createTextElement('', column);
	wrapper.innerHTML = String(html ?? '');

	return wrapper;
}

function renderCell(value, row, column = {}) {
	if (column.html) {
		return createHtmlElement(value, column);
	}

	return createTextElement(formatValue(value, column), column);
}

function buildColumns(columns) {
	return (columns || []).map(function(column) {
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

export function createReportCellRendererTools() {
	return {
		buildColumns,
		formatValue,
		renderCell,
		getRowValue,
		createTextElement
	};
}
