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
use Base3\Vizion\Api\IReportRowRenderer;

/**
 * Resolves row renderers by technical ClassMap name or vdef renderer type.
 */
final class ReportRowRendererRegistry {

	public function __construct(private readonly IClassMap $classMap) {}

	public function getRenderer(string $name): ?IReportRowRenderer {
		$name = $this->normalizeName($name);
		$renderer = $this->classMap->getInstanceByInterfaceName(IReportRowRenderer::class, $name);

		if($renderer instanceof IReportRowRenderer) {
			return $renderer;
		}

		foreach($this->getRenderers() as $candidate) {
			if($this->matches($name, $candidate::getName(), $candidate->getRendererType(), $candidate->getAliases())) {
				return $candidate;
			}
		}

		return null;
	}

	/** @return array<int,IReportRowRenderer> */
	public function getRenderers(): array {
		$instances = $this->classMap->getInstancesByInterface(IReportRowRenderer::class);
		$result = [];

		foreach($instances as $instance) {
			if($instance instanceof IReportRowRenderer) {
				$result[] = $instance;
			}
		}

		return $result;
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
