<?php declare(strict_types=1);

namespace Vizion\Filter\Type;

class NumberFilterType extends AbstractReportFilterType {

	public static function getName(): string {
		return 'numberfiltertype';
	}

	public function getType(): string {
		return 'number';
	}

	public function getAliases(): array {
		return ['int', 'integer', 'float', 'decimal'];
	}

	public function normalizeValue(mixed $value, array $definition): mixed {
		return $this->normalizeNumberValue($value);
	}

	protected function getDefaultMatch(): string {
		return 'equals';
	}
}
