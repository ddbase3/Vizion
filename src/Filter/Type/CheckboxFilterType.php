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
