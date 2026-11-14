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
use RuntimeException;
use Base3\Vizion\Api\IReportValueRenderer;

/**
 * Resolves value renderers by their BASE3 technical name.
 */
final class ReportValueRendererRegistry {

	/** @var array<string,IReportValueRenderer> */
	private array $cache = [];

	public function __construct(private readonly IClassMap $classMap) {}

	public function getRenderer(string $name): IReportValueRenderer {
		$name = strtolower(trim($name));

		if($name === '') {
			$name = 'textvaluerenderer';
		}

		if(isset($this->cache[$name])) {
			return $this->cache[$name];
		}

		$renderer = $this->classMap->getInstanceByInterfaceName(IReportValueRenderer::class, $name);

		if(!$renderer instanceof IReportValueRenderer) {
			throw new RuntimeException('Unknown Vizion value renderer: ' . $name);
		}

		$this->cache[$name] = $renderer;

		return $renderer;
	}
}
