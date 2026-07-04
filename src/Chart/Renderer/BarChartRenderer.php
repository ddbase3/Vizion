<?php declare(strict_types=1);

namespace Vizion\Chart\Renderer;

final class BarChartRenderer extends AbstractChartRenderer {

	public static function getName(): string {
		return 'barchartrenderer';
	}

	public function getChartType(): string {
		return 'bar';
	}
}
