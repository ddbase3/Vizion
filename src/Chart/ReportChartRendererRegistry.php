<?php declare(strict_types=1);

namespace Vizion\Chart;

use Base3\Api\IClassMap;
use Vizion\Api\IReportChartRenderer;

final class ReportChartRendererRegistry {

	public function __construct(private readonly IClassMap $classMap) {}

	public function getRenderer(string $name): IReportChartRenderer {
		$name = $this->normalizeName($name);
		$renderer = $this->classMap->getInstanceByInterfaceName(IReportChartRenderer::class, $name);

		if($renderer instanceof IReportChartRenderer) {
			return $renderer;
		}

		$renderer = $this->findRenderer($name);

		if($renderer instanceof IReportChartRenderer) {
			return $renderer;
		}

		$renderer = $this->findRenderer('bar');

		if($renderer instanceof IReportChartRenderer) {
			return $renderer;
		}

		throw new \RuntimeException('Missing Vizion bar chart renderer implementation.');
	}

	/** @return array<int,IReportChartRenderer> */
	public function getRenderers(): array {
		$instances = $this->classMap->getInstancesByInterface(IReportChartRenderer::class);
		$result = [];

		foreach($instances as $instance) {
			if($instance instanceof IReportChartRenderer) {
				$result[] = $instance;
			}
		}

		return $result;
	}

	private function findRenderer(string $name): ?IReportChartRenderer {
		foreach($this->getRenderers() as $candidate) {
			if($this->matches($name, $candidate::getName(), $candidate->getChartType(), $candidate->getAliases())) {
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
