<?php declare(strict_types=1);

/***********************************************************************
 * This file is part of Vizion for BASE3 Framework.
 *
 * Vizion provides reporting and data visualization for BASE3 projects.
 * It supports grids, interactive tables, charts and data exporters.
 * Visual components consume structured reporting and query results.
 *
 * Developed by Daniel Dahme
 * Licensed under GPL-3.0
 * https://www.gnu.org/licenses/gpl-3.0.en.html
 *
 * https://base3.de/v/vizion
 * https://github.com/ddbase3/Vizion
 **********************************************************************/

namespace Base3\Vizion\Filter\Type;

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
