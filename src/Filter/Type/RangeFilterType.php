<?php declare(strict_types=1);

namespace Vizion\Filter\Type;

class RangeFilterType extends AbstractReportFilterType {

	public static function getName(): string {
		return 'rangefiltertype';
	}

	public function getType(): string {
		return 'range';
	}

	public function getAliases(): array {
		return ['numberrange', 'number_range', 'number-range'];
	}

	public function getEmptyValue(array $definition): mixed {
		return array_key_exists('emptyValue', $definition) ? $definition['emptyValue'] : ['min' => '', 'max' => ''];
	}

	public function normalizeValue(mixed $value, array $definition): mixed {
		if(!is_array($value)) {
			return ['min' => '', 'max' => ''];
		}

		return [
			'min' => $this->normalizeNumberValue($value['min'] ?? ''),
			'max' => $this->normalizeNumberValue($value['max'] ?? '')
		];
	}

	public function buildCondition(mixed $element, mixed $value, array $definition): ?array {
		return $this->buildBetweenPartsCondition($element, $value, 'min', 'max');
	}

	protected function getDefaultMatch(): string {
		return 'between';
	}

	protected function buildBetweenPartsCondition(mixed $element, mixed $value, string $fromKey, string $toKey): ?array {
		if(!is_array($value)) {
			return null;
		}

		$conditions = [];
		$from = $value[$fromKey] ?? '';
		$to = $value[$toKey] ?? '';

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
