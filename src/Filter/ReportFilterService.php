<?php declare(strict_types=1);

namespace Vizion\Filter;

use Base3\Api\IClassMap;
use Vizion\Api\IReportFilterService;
use Vizion\Api\IReportFilterType;

final class ReportFilterService implements IReportFilterService {

	private ReportFilterTypeRegistry $typeRegistry;
	private ReportFilterControlRegistry $controlRegistry;

	public function __construct(IClassMap $classMap) {
		$this->typeRegistry = new ReportFilterTypeRegistry($classMap);
		$this->controlRegistry = new ReportFilterControlRegistry($classMap);
	}

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

	public function buildInitialFilterValues(array $fields): array {
		$result = [];

		foreach($fields as $field) {
			$definition = $this->getFilterDefinition($field);

			if($definition === null || !isset($field['alias']) || !array_key_exists('initialValue', $definition)) {
				continue;
			}

			$type = $this->getType($definition);
			$result[(string) $field['alias']] = $type->normalizeValue($definition['initialValue'], $definition);
		}

		return $result;
	}

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

			$type = $this->getType($definition);
			$normalized = $type->normalizeValue($value, $definition);

			if($type->isEmptyValue($normalized, $definition)) {
				continue;
			}

			$result[$alias] = $normalized;
		}

		return $result;
	}

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

			$type = $this->getType($definition);
			$condition = $type->buildCondition($fieldDefs[$alias], $value, $definition);

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

	private function getType(array $definition): IReportFilterType {
		return $this->typeRegistry->getType((string) ($definition['type'] ?? 'text'));
	}

	/** @param array<string,mixed> $field @return array<string,mixed>|null */
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

	/** @param array<string,mixed> $field @param array<string,mixed> $definition @return array<string,mixed> */
	private function buildGridFilterField(array $field, array $definition): array {
		$config = isset($field['config']) && is_array($field['config']) ? $field['config'] : [];
		$alias = (string) ($field['alias'] ?? '');
		$type = $this->getType($definition);
		$typeName = $type->getType();
		$definition['type'] = $typeName;
		$definition['match'] = $type->normalizeMatch((string) ($definition['match'] ?? ''));

		$gridField = [
			'key' => $alias,
			'label' => (string) ($definition['label'] ?? ($config['label'] ?? $alias)),
			'type' => $typeName,
			'match' => $definition['match'],
			'visibility' => $this->normalizeVisibility((string) ($definition['visibility'] ?? (($config['visible'] ?? true) === false ? 'optional' : 'always'))),
			'placeholder' => (string) ($definition['placeholder'] ?? ($config['filterPlaceholder'] ?? ($config['label'] ?? $alias))),
			'defaultValue' => $type->normalizeValue($type->getDefaultValue($definition), $definition),
			'emptyValue' => $type->normalizeValue($type->getEmptyValue($definition), $definition),
		];

		if(array_key_exists('initialValue', $definition)) {
			$gridField['initialValue'] = $type->normalizeValue($definition['initialValue'], $definition);
		}

		$width = $definition['width'] ?? $config['filterWidth'] ?? null;
		if(is_numeric($width)) {
			$gridField['width'] = (int) $width;
		}

		foreach(['minWidth', 'maxWidth', 'min', 'max', 'step', 'control', 'source', 'format', 'valueFormat', 'submitFormat', 'storageFormat', 'queryFormat', 'mode', 'inputWidth', 'fromPlaceholder', 'toPlaceholder', 'minuteStep', 'closeOnSelect', 'selectedLabel', 'emptyLabel', 'valueLabelPrefix', 'valueLabelSuffix'] as $key) {
			if(array_key_exists($key, $definition)) {
				$gridField[$key] = $definition[$key];
			}
		}

		if(isset($definition['options']) && is_array($definition['options'])) {
			$gridField['options'] = $this->normalizeOptions($definition['options']);
		}

		$gridField = $type->configureGridField($gridField, $field, $definition);
		$controlName = $this->getControlName($definition, $typeName);
		$control = $this->controlRegistry->getControl($controlName);

		return $control->configureGridField($gridField, $field, $definition);
	}

	private function normalizeVisibility(string $visibility): string {
		return strtolower($visibility) === 'optional' ? 'optional' : 'always';
	}

	private function getControlName(array $definition, string $typeName): string {
		if(isset($definition['control']) && is_scalar($definition['control']) && trim((string) $definition['control']) !== '') {
			return (string) $definition['control'];
		}

		return match($typeName) {
			'range' => 'range',
			'daterange', 'datetimerange' => 'daterange',
			default => 'native'
		};
	}

	/** @param array<mixed> $options @return array<int,array<string,string>> */
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

	/** @param array<int,array<string,mixed>> $fields @return array<string,array<string,mixed>> */
	private function buildFieldsByAlias(array $fields): array {
		$result = [];

		foreach($fields as $field) {
			if(isset($field['alias'])) {
				$result[(string) $field['alias']] = $field;
			}
		}

		return $result;
	}
}
