<?php declare(strict_types=1);

namespace Vizion\Filter;

use Vizion\Api\IReportFilterService;

final class ReportFilterService implements IReportFilterService {

	/**
	 * @param array<int,array<string,mixed>> $fields
	 * @return array<int,array<string,mixed>>
	 */
	public function buildGridFilterFields(array $fields): array {
		$result = [];

		foreach($fields as $field) {
			$definition = $this->getFilterDefinition($field);

			if($definition === null || !isset($field['alias'])) {
				continue;
			}

			$result[] = $this->buildGridFilterField($field, $definition);
		}

		return $result;
	}

	/**
	 * @param array<int,array<string,mixed>> $fields
	 * @return array<string,mixed>
	 */
	public function buildInitialFilterValues(array $fields): array {
		$result = [];

		foreach($fields as $field) {
			$definition = $this->getFilterDefinition($field);

			if($definition === null || !isset($field['alias']) || !array_key_exists('initialValue', $definition)) {
				continue;
			}

			$result[(string) $field['alias']] = $definition['initialValue'];
		}

		return $result;
	}

	/**
	 * @param mixed $filtersPayload
	 * @param array<int,array<string,mixed>> $fields
	 * @return array<string,mixed>
	 */
	public function normalizeFilters(mixed $filtersPayload, array $fields): array {
		$result = [];

		if(!is_array($filtersPayload)) {
			return $result;
		}

		$fieldsByAlias = $this->buildFieldsByAlias($fields);

		foreach($filtersPayload as $alias => $value) {
			if(!is_string($alias) || !isset($fieldsByAlias[$alias])) {
				continue;
			}

			$definition = $this->getFilterDefinition($fieldsByAlias[$alias]);

			if($definition === null) {
				continue;
			}

			$normalized = $this->normalizeValue($value, $definition);

			if($this->isEmptyValue($normalized, $definition)) {
				continue;
			}

			$result[$alias] = $normalized;
		}

		return $result;
	}

	/**
	 * @param array<string,mixed> $filters
	 * @param array<int,array<string,mixed>> $fields
	 * @param array<string,mixed> $fieldDefs
	 * @return array<string,mixed>|null
	 */
	public function buildFilterWhere(array $filters, array $fields, array $fieldDefs): ?array {
		$conditions = [];
		$fieldsByAlias = $this->buildFieldsByAlias($fields);

		foreach($filters as $alias => $value) {
			if(!is_string($alias) || !isset($fieldsByAlias[$alias], $fieldDefs[$alias])) {
				continue;
			}

			$definition = $this->getFilterDefinition($fieldsByAlias[$alias]);

			if($definition === null) {
				continue;
			}

			$condition = $this->buildCondition($fieldDefs[$alias], $value, $definition);

			if($condition !== null) {
				$conditions[] = $condition;
			}
		}

		if(count($conditions) === 0) {
			return null;
		}

		return count($conditions) === 1
			? $conditions[0]
			: [
				'type' => 'op',
				'operator' => 'AND',
				'params' => $conditions
			];
	}

	/**
	 * @param array<string,mixed> $field
	 * @return array<string,mixed>|null
	 */
	private function getFilterDefinition(array $field): ?array {
		$config = isset($field['config']) && is_array($field['config']) ? $field['config'] : [];
		$filter = $config['filter'] ?? null;

		if(is_array($filter)) {
			if(($filter['enabled'] ?? false) !== true) {
				return null;
			}

			return $filter;
		}

		if($filter === true) {
			return [
				'enabled' => true,
				'visibility' => ($config['visible'] ?? true) === false ? 'optional' : 'always',
				'type' => (string) ($config['filterType'] ?? 'text'),
				'match' => (string) ($config['filterOperator'] ?? 'contains'),
				'defaultValue' => '',
				'initialValue' => '',
				'placeholder' => (string) ($config['filterPlaceholder'] ?? ($config['label'] ?? ($field['alias'] ?? ''))),
				'width' => isset($config['filterWidth']) && is_numeric($config['filterWidth']) ? (int) $config['filterWidth'] : 140,
				'options' => isset($config['filterOptions']) && is_array($config['filterOptions']) ? $config['filterOptions'] : []
			];
		}

		return null;
	}

	/**
	 * @param array<string,mixed> $field
	 * @param array<string,mixed> $definition
	 * @return array<string,mixed>
	 */
	private function buildGridFilterField(array $field, array $definition): array {
		$config = isset($field['config']) && is_array($field['config']) ? $field['config'] : [];
		$alias = (string) ($field['alias'] ?? '');
		$type = $this->normalizeType((string) ($definition['type'] ?? 'text'));
		$gridField = [
			'key' => $alias,
			'label' => (string) ($definition['label'] ?? ($config['label'] ?? $alias)),
			'type' => $type,
			'match' => $this->normalizeMatch((string) ($definition['match'] ?? ''), $type),
			'visibility' => $this->normalizeVisibility((string) ($definition['visibility'] ?? (($config['visible'] ?? true) === false ? 'optional' : 'always'))),
			'placeholder' => (string) ($definition['placeholder'] ?? ($config['filterPlaceholder'] ?? ($config['label'] ?? $alias))),
			'defaultValue' => array_key_exists('defaultValue', $definition)
				? $definition['defaultValue']
				: $this->getDefaultValue($type),
		];

		if(array_key_exists('initialValue', $definition)) {
			$gridField['initialValue'] = $definition['initialValue'];
		}

		$width = $definition['width'] ?? $config['filterWidth'] ?? null;
		if(is_numeric($width)) {
			$gridField['width'] = (int) $width;
		}

		foreach(['minWidth', 'maxWidth', 'min', 'max', 'step', 'control', 'source', 'emptyValue', 'format', 'valueFormat', 'submitFormat', 'storageFormat', 'queryFormat', 'mode', 'inputWidth', 'fromPlaceholder', 'toPlaceholder', 'minuteStep', 'closeOnSelect'] as $key) {
			if(array_key_exists($key, $definition)) {
				$gridField[$key] = $definition[$key];
			}
		}

		if(isset($definition['options']) && is_array($definition['options'])) {
			$gridField['options'] = $this->normalizeOptions($definition['options']);
		}

		return $gridField;
	}

	private function normalizeVisibility(string $visibility): string {
		return strtolower($visibility) === 'optional' ? 'optional' : 'always';
	}

	private function normalizeType(string $type): string {
		$type = strtolower(trim($type));

		return match($type) {
			'search' => 'search',
			'select' => 'select',
			'multiselect', 'multi_select', 'multi-select' => 'multiselect',
			'checkbox', 'bool', 'boolean' => 'checkbox',
			'radio' => 'radio',
			'int', 'integer', 'number', 'float', 'decimal' => 'number',
			'slider' => 'slider',
			'range', 'numberrange', 'number_range', 'number-range' => 'range',
			'date' => 'date',
			'datetime', 'datetime-local' => 'datetime',
			'daterange', 'date_range', 'date-range' => 'daterange',
			'datetimerange', 'datetime_range', 'datetime-range' => 'datetimerange',
			'custom' => 'custom',
			default => 'text'
		};
	}

	private function normalizeMatch(string $match, string $type): string {
		$match = strtolower(trim($match));

		if($match === '') {
			return match($type) {
				'multiselect' => 'in',
				'number', 'slider', 'date', 'datetime', 'select', 'radio', 'checkbox' => 'equals',
				'range', 'daterange', 'datetimerange' => 'between',
				default => 'contains'
			};
		}

		return match($match) {
			'=', 'eq', 'equals' => 'equals',
			'!=', '<>', 'neq', 'not_equals', 'not-equals' => 'notEquals',
			'contains', 'like' => 'contains',
			'startswith', 'starts_with', 'starts-with' => 'startsWith',
			'endswith', 'ends_with', 'ends-with' => 'endsWith',
			'in' => 'in',
			'between' => 'between',
			'>', 'gt' => 'gt',
			'>=', 'gte' => 'gte',
			'<', 'lt' => 'lt',
			'<=', 'lte' => 'lte',
			default => match($type) {
				'multiselect' => 'in',
				'number', 'slider', 'date', 'datetime', 'select', 'radio', 'checkbox' => 'equals',
				'range', 'daterange', 'datetimerange' => 'between',
				default => 'contains'
			}
		};
	}

	private function getDefaultValue(string $type): mixed {
		return match($type) {
			'checkbox' => false,
			default => $this->getEmptyValue($type)
		};
	}

	private function getEmptyValue(string $type): mixed {
		return match($type) {
			'checkbox' => false,
			'multiselect' => [],
			'range' => ['min' => '', 'max' => ''],
			'daterange', 'datetimerange' => ['from' => '', 'to' => ''],
			default => ''
		};
	}

	/**
	 * @param array<mixed> $options
	 * @return array<int,array<string,string>>
	 */
	private function normalizeOptions(array $options): array {
		$result = [];

		foreach($options as $key => $value) {
			if(is_array($value)) {
				$result[] = [
					'value' => (string) ($value['value'] ?? ''),
					'label' => (string) ($value['label'] ?? ($value['value'] ?? '')),
				];
				continue;
			}

			$result[] = [
				'value' => is_string($key) ? $key : (string) $value,
				'label' => (string) $value,
			];
		}

		return $result;
	}

	/**
	 * @param array<int,array<string,mixed>> $fields
	 * @return array<string,array<string,mixed>>
	 */
	private function buildFieldsByAlias(array $fields): array {
		$result = [];

		foreach($fields as $field) {
			if(isset($field['alias'])) {
				$result[(string) $field['alias']] = $field;
			}
		}

		return $result;
	}

	/**
	 * @param array<string,mixed> $definition
	 */
	private function normalizeValue(mixed $value, array $definition): mixed {
		$type = $this->normalizeType((string) ($definition['type'] ?? 'text'));

		return match($type) {
			'checkbox' => $this->normalizeBoolValue($value),
			'number', 'slider' => $this->normalizeNumberValue($value),
			'multiselect' => $this->normalizeMultiValue($value),
			'range' => $this->normalizeRangeValue($value),
			'date', 'datetime' => $this->normalizeDateValue($value, $definition, $type),
			'daterange', 'datetimerange' => $this->normalizeDateRangeValue($value, $definition, $type),
			default => $this->normalizeScalarTextValue($value)
		};
	}

	private function normalizeBoolValue(mixed $value): bool {
		if(is_bool($value)) {
			return $value;
		}

		if(is_numeric($value)) {
			return ((int) $value) === 1;
		}

		if(is_string($value)) {
			return in_array(strtolower(trim($value)), ['1', 'true', 'yes', 'ja', 'on'], true);
		}

		return false;
	}

	private function normalizeNumberValue(mixed $value): mixed {
		if($value === null || $value === '') {
			return '';
		}

		if(!is_scalar($value) || !is_numeric($value)) {
			return '';
		}

		$number = (string) $value;

		return str_contains($number, '.') ? (float) $number : (int) $number;
	}

	private function normalizeScalarTextValue(mixed $value): string {
		return is_scalar($value) ? trim((string) $value) : '';
	}

	/** @return array<int,mixed> */
	private function normalizeMultiValue(mixed $value): array {
		$values = is_array($value) ? $value : [$value];
		$result = [];

		foreach($values as $item) {
			if(!is_scalar($item)) {
				continue;
			}

			$item = trim((string) $item);

			if($item !== '') {
				$result[] = $item;
			}
		}

		return array_values(array_unique($result));
	}

	/** @return array<string,mixed> */
	private function normalizeRangeValue(mixed $value): array {
		if(!is_array($value)) {
			return ['min' => '', 'max' => ''];
		}

		return [
			'min' => $this->normalizeNumberValue($value['min'] ?? ''),
			'max' => $this->normalizeNumberValue($value['max'] ?? '')
		];
	}

	private function normalizeDateValue(mixed $value, array $definition, string $type): string {
		$text = $this->normalizeScalarTextValue($value);

		if($text === '') {
			return '';
		}

		$targetFormat = $this->getDateValueFormat($definition, $type);
		$formats = $this->getDateInputFormats($definition, $type, $targetFormat);

		foreach($formats as $format) {
			$parts = $this->parseDateValue($text, $format);

			if($parts !== null) {
				return $this->formatDateValue($parts, $targetFormat);
			}
		}

		return $text;
	}

	/** @return array<string,string> */
	private function normalizeDateRangeValue(mixed $value, array $definition, string $type): array {
		if(!is_array($value)) {
			return ['from' => '', 'to' => ''];
		}

		$valueType = $type === 'datetimerange' ? 'datetime' : 'date';

		return [
			'from' => $this->normalizeDateValue($value['from'] ?? '', $definition, $valueType),
			'to' => $this->normalizeDateValue($value['to'] ?? '', $definition, $valueType)
		];
	}

	private function getDateDisplayFormat(array $definition, string $type): string {
		if(isset($definition['format']) && is_scalar($definition['format'])) {
			return (string) $definition['format'];
		}

		return in_array($type, ['datetime', 'datetimerange'], true) ? 'YYYY-MM-DD HH:mm' : 'YYYY-MM-DD';
	}

	private function getDateValueFormat(array $definition, string $type): string {
		foreach(['valueFormat', 'submitFormat', 'storageFormat', 'queryFormat'] as $key) {
			if(isset($definition[$key]) && is_scalar($definition[$key])) {
				return (string) $definition[$key];
			}
		}

		return $this->getDateDisplayFormat($definition, $type);
	}

	/** @return array<int,string> */
	private function getDateInputFormats(array $definition, string $type, string $targetFormat): array {
		$formats = [
			$targetFormat,
			$this->getDateDisplayFormat($definition, $type),
			'YYYY-MM-DD',
			'DD.MM.YYYY'
		];

		if(in_array($type, ['datetime', 'datetimerange'], true)) {
			$formats[] = 'YYYY-MM-DD HH:mm';
			$formats[] = 'YYYY-MM-DDTHH:mm';
			$formats[] = 'DD.MM.YYYY HH:mm';
		}

		return array_values(array_unique($formats));
	}

	/** @return array<string,string>|null */
	private function parseDateValue(string $value, string $format): ?array {
		$tokens = [
			'YYYY' => '(\\d{4})',
			'MM' => '(\\d{2})',
			'DD' => '(\\d{2})',
			'HH' => '(\\d{2})',
			'mm' => '(\\d{2})'
		];
		$tokenNames = array_keys($tokens);
		$tokenOrder = [];
		$pattern = '/^';
		$index = 0;
		$length = strlen($format);

		while($index < $length) {
			$matchedToken = null;

			foreach($tokenNames as $token) {
				if(substr($format, $index, strlen($token)) === $token) {
					$matchedToken = $token;
					break;
				}
			}

			if($matchedToken !== null) {
				$pattern .= $tokens[$matchedToken];
				$tokenOrder[] = $matchedToken;
				$index += strlen($matchedToken);
				continue;
			}

			$pattern .= preg_quote($format[$index], '/');
			$index++;
		}

		$pattern .= '$/';

		if(preg_match($pattern, $value, $matches) !== 1) {
			return null;
		}

		$parts = [
			'YYYY' => '1970',
			'MM' => '01',
			'DD' => '01',
			'HH' => '00',
			'mm' => '00'
		];

		foreach($tokenOrder as $position => $token) {
			$parts[$token] = (string) $matches[$position + 1];
		}

		$timestamp = mktime((int) $parts['HH'], (int) $parts['mm'], 0, (int) $parts['MM'], (int) $parts['DD'], (int) $parts['YYYY']);

		if($timestamp === false) {
			return null;
		}

		if(date('Y', $timestamp) !== $parts['YYYY'] || date('m', $timestamp) !== $parts['MM'] || date('d', $timestamp) !== $parts['DD'] || date('H', $timestamp) !== $parts['HH'] || date('i', $timestamp) !== $parts['mm']) {
			return null;
		}

		return $parts;
	}

	/** @param array<string,string> $parts */
	private function formatDateValue(array $parts, string $format): string {
		return str_replace(
			['YYYY', 'MM', 'DD', 'HH', 'mm'],
			[$parts['YYYY'], $parts['MM'], $parts['DD'], $parts['HH'], $parts['mm']],
			$format
		);
	}

	/**
	 * @param array<string,mixed> $definition
	 */
	private function isEmptyValue(mixed $value, array $definition): bool {
		$type = $this->normalizeType((string) ($definition['type'] ?? 'text'));
		$emptyValue = array_key_exists('emptyValue', $definition)
			? $this->normalizeValue($definition['emptyValue'], $definition)
			: $this->getEmptyValue($type);

		if($this->valuesEqual($value, $emptyValue)) {
			return true;
		}

		if(is_array($value)) {
			return count(array_filter($value, fn($entry) => $entry !== '' && $entry !== null && $entry !== [])) === 0;
		}

		return $value === '' || $value === null;
	}

	private function valuesEqual(mixed $left, mixed $right): bool {
		return json_encode($left, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) === json_encode($right, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	}

	/**
	 * @param mixed $element
	 * @param array<string,mixed> $definition
	 * @return array<string,mixed>|null
	 */
	private function buildCondition(mixed $element, mixed $value, array $definition): ?array {
		$type = $this->normalizeType((string) ($definition['type'] ?? 'text'));
		$match = $this->normalizeMatch((string) ($definition['match'] ?? ''), $type);

		return match($match) {
			'equals' => $this->buildBinaryCondition($element, '=', $value),
			'notEquals' => $this->buildBinaryCondition($element, '<>', $value),
			'contains' => $this->buildLikeCondition($element, '%' . (string) $value . '%'),
			'startsWith' => $this->buildLikeCondition($element, (string) $value . '%'),
			'endsWith' => $this->buildLikeCondition($element, '%' . (string) $value),
			'in' => $this->buildInCondition($element, $value),
			'between' => $this->buildBetweenCondition($element, $value),
			'gt' => $this->buildBinaryCondition($element, '>', $value),
			'gte' => $this->buildBinaryCondition($element, '>=', $value),
			'lt' => $this->buildBinaryCondition($element, '<', $value),
			'lte' => $this->buildBinaryCondition($element, '<=', $value),
			default => $this->buildLikeCondition($element, '%' . (string) $value . '%')
		};
	}

	/** @return array<string,mixed> */
	private function buildBinaryCondition(mixed $element, string $operator, mixed $value): array {
		return [
			'type' => 'op',
			'operator' => $operator,
			'params' => [$element, $value]
		];
	}

	/** @return array<string,mixed> */
	private function buildLikeCondition(mixed $element, string $value): array {
		return $this->buildBinaryCondition($element, 'LIKE', $value);
	}

	/** @return array<string,mixed>|null */
	private function buildInCondition(mixed $element, mixed $value): ?array {
		$values = is_array($value) ? array_values($value) : [$value];
		$values = array_values(array_filter($values, fn($entry) => $entry !== '' && $entry !== null));

		if(count($values) === 0) {
			return null;
		}

		return [
			'type' => 'op',
			'operator' => 'IN',
			'params' => array_merge([$element], $values)
		];
	}

	/** @return array<string,mixed>|null */
	private function buildBetweenCondition(mixed $element, mixed $value): ?array {
		if(!is_array($value)) {
			return null;
		}

		$from = $value['min'] ?? $value['from'] ?? '';
		$to = $value['max'] ?? $value['to'] ?? '';
		$conditions = [];

		if($from !== '' && $from !== null) {
			$conditions[] = $this->buildBinaryCondition($element, '>=', $from);
		}

		if($to !== '' && $to !== null) {
			$conditions[] = $this->buildBinaryCondition($element, '<=', $to);
		}

		if(count($conditions) === 0) {
			return null;
		}

		return count($conditions) === 1
			? $conditions[0]
			: [
				'type' => 'op',
				'operator' => 'AND',
				'params' => $conditions
			];
	}
}
