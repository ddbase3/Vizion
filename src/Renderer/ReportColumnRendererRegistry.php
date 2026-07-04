<?php declare(strict_types=1);

namespace Vizion\Renderer;

use Base3\Api\IClassMap;
use Vizion\Api\IReportColumnRenderer;

/**
 * Resolves column renderers by technical ClassMap name or vdef renderer type.
 */
final class ReportColumnRendererRegistry {

	public function __construct(private readonly IClassMap $classMap) {}

	public function getRenderer(string $name): IReportColumnRenderer {
		$name = $this->normalizeName($name);
		$renderer = $this->classMap->getInstanceByInterfaceName(IReportColumnRenderer::class, $name);

		if($renderer instanceof IReportColumnRenderer) {
			return $renderer;
		}

		$renderer = $this->findRenderer($name);

		if($renderer instanceof IReportColumnRenderer) {
			return $renderer;
		}

		$renderer = $this->findRenderer('value');

		if($renderer instanceof IReportColumnRenderer) {
			return $renderer;
		}

		throw new \RuntimeException('Missing Vizion default column renderer implementation.');
	}

	/** @return array<int,IReportColumnRenderer> */
	public function getRenderers(): array {
		$instances = $this->classMap->getInstancesByInterface(IReportColumnRenderer::class);
		$result = [];

		foreach($instances as $instance) {
			if($instance instanceof IReportColumnRenderer) {
				$result[] = $instance;
			}
		}

		return $result;
	}

	private function findRenderer(string $name): ?IReportColumnRenderer {
		foreach($this->getRenderers() as $candidate) {
			if($this->matches($name, $candidate::getName(), $candidate->getRendererType(), $candidate->getAliases())) {
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
