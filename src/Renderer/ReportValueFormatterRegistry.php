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

namespace Base3\Vizion\Renderer;

use Base3\CoreFoundation\Api\IClassMap;
use Base3\Vizion\Api\IReportValueFormatter;

final class ReportValueFormatterRegistry {

	public function __construct(private readonly IClassMap $classMap) {}

	public function getFormatter(string $name): IReportValueFormatter {
		$name = $this->normalizeName($name);
		$formatter = $this->classMap->getInstanceByInterfaceName(IReportValueFormatter::class, $name);

		if($formatter instanceof IReportValueFormatter) {
			return $formatter;
		}

		$formatter = $this->findFormatter($name);

		if($formatter instanceof IReportValueFormatter) {
			return $formatter;
		}

		$formatter = $this->findFormatter('text');

		if($formatter instanceof IReportValueFormatter) {
			return $formatter;
		}

		throw new \RuntimeException('Missing Vizion text value formatter implementation.');
	}

	/** @return array<int,IReportValueFormatter> */
	public function getFormatters(): array {
		$instances = $this->classMap->getInstancesByInterface(IReportValueFormatter::class);
		$result = [];

		foreach($instances as $instance) {
			if($instance instanceof IReportValueFormatter) {
				$result[] = $instance;
			}
		}

		return $result;
	}

	private function findFormatter(string $name): ?IReportValueFormatter {
		foreach($this->getFormatters() as $candidate) {
			if($this->matches($name, $candidate::getName(), $candidate->getFormatterType(), $candidate->getAliases())) {
				return $candidate;
			}
		}

		return null;
	}

	/** @param array<int,string> $aliases */
	private function matches(string $name, string $technicalName, string $semanticName, array $aliases): bool {
		$names = array_merge([$technicalName, $semanticName], $aliases);

		foreach($names as $candidate) {
			if($name === $this->normalizeName($candidate)) {
				return true;
			}
		}

		return false;
	}

	private function normalizeName(string $name): string {
		return strtolower(trim($name));
	}
}
