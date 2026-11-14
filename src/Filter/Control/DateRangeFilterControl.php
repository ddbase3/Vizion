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

namespace Base3\Vizion\Filter\Control;

use Base3\Vizion\Api\IReportFilterControl;

final class DateRangeFilterControl implements IReportFilterControl {

	public static function getName(): string {
		return 'daterangefiltercontrol';
	}

	public function getControl(): string {
		return 'daterange';
	}

	public function getAliases(): array {
		return ['date-range', 'date_range', 'native-daterange', 'native_date_range'];
	}

	public function configureGridField(array $gridField, array $field, array $definition): array {
		$type = (string) ($gridField['type'] ?? $definition['type'] ?? 'daterange');
		$gridField['renderControlKey'] = 'vizion.dateRange';
		$gridField['valueType'] = $type;

		return $gridField;
	}

	public function getAssetPaths(array $definition): array {
		return ['plugin/Vizion/assets/js/vizion-report-filter-controls.js'];
	}
}
