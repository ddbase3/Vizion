<?php declare(strict_types=1);

namespace Vizion\Renderer;

use Base3\Api\IClassMap;
use RuntimeException;
use Vizion\Api\IReportValueRenderer;

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
