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

final class RangeFilterControl implements IReportFilterControl {

	public static function getName(): string {
		return 'rangefiltercontrol';
	}

	public function getControl(): string {
		return 'range';
	}

	public function getAliases(): array {
		return ['numberrange', 'number_range', 'number-range'];
	}

	public function configureGridField(array $gridField, array $field, array $definition): array {
		$gridField['renderControlKey'] = 'vizion.range';
		$gridField['valueType'] = 'range';

		return $gridField;
	}

	public function getAssetPaths(array $definition): array {
		return ['plugin/Vizion/assets/js/vizion-report-filter-controls.js'];
	}
}
