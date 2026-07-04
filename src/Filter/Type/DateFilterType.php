<?php declare(strict_types=1);

namespace Vizion\Filter\Type;

class DateFilterType extends AbstractReportFilterType {

	use DateValueTrait;

	public static function getName(): string {
		return 'datefiltertype';
	}

	public function getType(): string {
		return 'date';
	}

	public function normalizeValue(mixed $value, array $definition): mixed {
		return $this->normalizeDateValue($value, $definition, $this->getType());
	}

	protected function getDefaultMatch(): string {
		return 'equals';
	}
}
