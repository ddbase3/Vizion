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

final class ChronoPickerFilterControl implements IReportFilterControl {

	public static function getName(): string {
		return 'chronopickerfiltercontrol';
	}

	public function getControl(): string {
		return 'chronopicker';
	}

	public function getAliases(): array {
		return ['chrono'];
	}

	public function configureGridField(array $gridField, array $field, array $definition): array {
		$type = (string) ($gridField['type'] ?? $definition['type'] ?? 'date');
		$gridField['valueType'] = $type;
		$gridField['renderControlKey'] = in_array($type, ['daterange', 'datetimerange'], true)
			? 'vizion.chronopickerDateRange'
			: 'vizion.chronopickerDate';

		return $gridField;
	}

	public function getAssetPaths(array $definition): array {
		return [
			'plugin/ClientStack/assets/chronopicker/styles/chronopicker.css',
			'plugin/ClientStack/assets/chronopicker/index.js',
			'plugin/Vizion/assets/js/vizion-report-filter-controls.js'
		];
	}
}
