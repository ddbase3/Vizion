<?php declare(strict_types=1);

namespace Vizion\Filter\Type;

final class MultiSelectFilterType extends AbstractReportFilterType {

	public static function getName(): string {
		return 'multiselectfiltertype';
	}

	public function getType(): string {
		return 'multiselect';
	}

	public function getAliases(): array {
		return ['multi_select', 'multi-select'];
	}

	public function getEmptyValue(array $definition): mixed {
		return array_key_exists('emptyValue', $definition) ? $definition['emptyValue'] : [];
	}

	public function normalizeValue(mixed $value, array $definition): mixed {
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

	public function buildCondition(mixed $element, mixed $value, array $definition): ?array {
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

	protected function getDefaultMatch(): string {
		return 'in';
	}
}
