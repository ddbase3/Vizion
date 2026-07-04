<?php declare(strict_types=1);

namespace Vizion\Filter\Type;

class DateRangeFilterType extends RangeFilterType {

	use DateValueTrait;

	public static function getName(): string {
		return 'daterangefiltertype';
	}

	public function getType(): string {
		return 'daterange';
	}

	public function getAliases(): array {
		return ['date_range', 'date-range'];
	}

	public function getEmptyValue(array $definition): mixed {
		return array_key_exists('emptyValue', $definition) ? $definition['emptyValue'] : ['from' => '', 'to' => ''];
	}

	public function normalizeValue(mixed $value, array $definition): mixed {
		if(!is_array($value)) {
			return ['from' => '', 'to' => ''];
		}

		return [
			'from' => $this->normalizeDateValue($value['from'] ?? '', $definition, $this->getDateValueType()),
			'to' => $this->normalizeDateValue($value['to'] ?? '', $definition, $this->getDateValueType())
		];
	}

	public function buildCondition(mixed $element, mixed $value, array $definition): ?array {
		return $this->buildBetweenPartsCondition($element, $value, 'from', 'to');
	}

	protected function getDateValueType(): string {
		return 'date';
	}
}
