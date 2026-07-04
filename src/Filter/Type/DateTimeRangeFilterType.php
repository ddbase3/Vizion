<?php declare(strict_types=1);

namespace Vizion\Filter\Type;

final class DateTimeRangeFilterType extends DateRangeFilterType {

	public static function getName(): string {
		return 'datetimerangefiltertype';
	}

	public function getType(): string {
		return 'datetimerange';
	}

	public function getAliases(): array {
		return ['datetime_range', 'datetime-range'];
	}

	protected function getDateValueType(): string {
		return 'datetime';
	}
}
