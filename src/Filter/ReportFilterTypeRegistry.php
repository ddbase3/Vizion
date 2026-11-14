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

namespace Base3\Vizion\Filter;

use Base3\CoreFoundation\Api\IClassMap;
use Base3\Vizion\Api\IReportFilterType;

final class ReportFilterTypeRegistry {

	public function __construct(private readonly IClassMap $classMap) {}

	public function getType(string $name): IReportFilterType {
		$name = $this->normalizeName($name);
		$type = $this->classMap->getInstanceByInterfaceName(IReportFilterType::class, $name);

		if($type instanceof IReportFilterType) {
			return $type;
		}

		$type = $this->findType($name);

		if($type instanceof IReportFilterType) {
			return $type;
		}

		$type = $this->findType('text');

		if($type instanceof IReportFilterType) {
			return $type;
		}

		throw new \RuntimeException('Missing Vizion text filter type implementation.');
	}

	/** @return array<int,IReportFilterType> */
	public function getTypes(): array {
		$instances = $this->classMap->getInstancesByInterface(IReportFilterType::class);
		$result = [];

		foreach($instances as $instance) {
			if($instance instanceof IReportFilterType) {
				$result[] = $instance;
			}
		}

		return $result;
	}

	private function findType(string $name): ?IReportFilterType {
		foreach($this->getTypes() as $candidate) {
			if($this->matches($name, $candidate::getName(), $candidate->getType(), $candidate->getAliases())) {
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
