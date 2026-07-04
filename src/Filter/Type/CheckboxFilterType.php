<?php declare(strict_types=1);

namespace Vizion\Filter\Type;

final class CheckboxFilterType extends AbstractReportFilterType {

	public static function getName(): string {
		return 'checkboxfiltertype';
	}

	public function getType(): string {
		return 'checkbox';
	}

	public function getAliases(): array {
		return ['bool', 'boolean'];
	}

	public function getEmptyValue(array $definition): mixed {
		return array_key_exists('emptyValue', $definition) ? $definition['emptyValue'] : false;
	}

	public function normalizeValue(mixed $value, array $definition): mixed {
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

	protected function getDefaultMatch(): string {
		return 'equals';
	}
}
