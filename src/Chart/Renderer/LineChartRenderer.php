<?php declare(strict_types=1);

namespace Vizion\Chart\Renderer;

final class LineChartRenderer extends AbstractChartRenderer {

	public static function getName(): string {
		return 'linechartrenderer';
	}

	public function getChartType(): string {
		return 'line';
	}

	protected function getDefaultOptions(array $chartConfig, array $measures, array $dimension): array {
		$options = parent::getDefaultOptions($chartConfig, $measures, $dimension);
		$options['elements'] = [
			'line' => [
				'tension' => 0.25
			]
		];

		return $options;
	}
}
