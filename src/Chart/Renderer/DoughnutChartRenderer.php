<?php declare(strict_types=1);

namespace Vizion\Chart\Renderer;

final class DoughnutChartRenderer extends AbstractChartRenderer {

	public static function getName(): string {
		return 'doughnutchartrenderer';
	}

	public function getChartType(): string {
		return 'doughnut';
	}

	public function getAliases(): array {
		return ['donut'];
	}
	protected function getDefaultOptions(array $chartConfig, array $measures, array $dimension): array {
		$options = parent::getDefaultOptions($chartConfig, $measures, $dimension);
		$options['plugins']['legend']['display'] = true;

		return $options;
	}
}
