<?php declare(strict_types=1);

namespace Vizion\Chart\Renderer;

use Vizion\Api\IReportChartRenderer;

abstract class AbstractChartRenderer implements IReportChartRenderer {

	public function getAliases(): array {
		return [];
	}

	public function buildClientConfig(array $chartConfig, array $measures, array $dimension): array {
		$options = isset($chartConfig['options']) && is_array($chartConfig['options'])
			? $chartConfig['options']
			: [];

		return [
			'type' => $this->getChartType(),
			'title' => isset($chartConfig['title']) && is_scalar($chartConfig['title']) ? (string) $chartConfig['title'] : '',
			'dimension' => $dimension,
			'measures' => $measures,
			'options' => $this->mergeOptions($this->getDefaultOptions($chartConfig, $measures, $dimension), $options)
		];
	}

	/** @return array<string,mixed> */
	protected function getDefaultOptions(array $chartConfig, array $measures, array $dimension): array {
		return [
			'responsive' => true,
			'maintainAspectRatio' => false,
			'plugins' => [
				'legend' => [
					'display' => count($measures) > 1
				],
				'title' => [
					'display' => isset($chartConfig['title']) && trim((string) $chartConfig['title']) !== '',
					'text' => isset($chartConfig['title']) && is_scalar($chartConfig['title']) ? (string) $chartConfig['title'] : ''
				]
			]
		];
	}

	/** @param array<string,mixed> $base @param array<string,mixed> $extra @return array<string,mixed> */
	protected function mergeOptions(array $base, array $extra): array {
		foreach($extra as $key => $value) {
			if(is_array($value) && isset($base[$key]) && is_array($base[$key])) {
				$base[$key] = $this->mergeOptions($base[$key], $value);
				continue;
			}

			$base[$key] = $value;
		}

		return $base;
	}
}
