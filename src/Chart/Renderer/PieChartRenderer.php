<?php declare(strict_types=1);

namespace Vizion\Chart\Renderer;

final class PieChartRenderer extends AbstractChartRenderer {

	public static function getName(): string {
		return 'piechartrenderer';
	}

	public function getChartType(): string {
		return 'pie';
	}

	public function getAliases(): array {
		return ['polar'];
	}
	protected function getDefaultOptions(array $chartConfig, array $measures, array $dimension): array {
		$options = parent::getDefaultOptions($chartConfig, $measures, $dimension);
		$options['plugins']['legend']['display'] = true;

		return $options;
	}
}
