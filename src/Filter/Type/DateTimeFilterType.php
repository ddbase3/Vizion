<?php declare(strict_types=1);

namespace Vizion\Filter\Type;

final class DateTimeFilterType extends DateFilterType {

	public static function getName(): string {
		return 'datetimefiltertype';
	}

	public function getType(): string {
		return 'datetime';
	}

	public function getAliases(): array {
		return ['datetime-local'];
	}
}
