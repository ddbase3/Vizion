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

final class NativeFilterControl implements IReportFilterControl {

	public static function getName(): string {
		return 'nativefiltercontrol';
	}

	public function getControl(): string {
		return 'native';
	}

	public function getAliases(): array {
		return ['default', 'modulargrid'];
	}

	public function configureGridField(array $gridField, array $field, array $definition): array {
		return $gridField;
	}

	public function getAssetPaths(array $definition): array {
		return [];
	}
}
