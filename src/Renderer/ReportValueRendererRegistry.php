<?php declare(strict_types=1);

namespace Vizion\Renderer;

use Base3\Api\IClassMap;
use Vizion\Api\IReportValueRenderer;

/**
 * Resolves value renderers by technical ClassMap name or vdef renderer type.
 */
final class ReportValueRendererRegistry {

	public function __construct(private readonly IClassMap $classMap) {}

	public function getRenderer(string $name): IReportValueRenderer {
		$name = $this->normalizeName($name);
		$renderer = $this->classMap->getInstanceByInterfaceName(IReportValueRenderer::class, $name);

		if($renderer instanceof IReportValueRenderer) {
			return $renderer;
		}

		$renderer = $this->findRenderer($name);

		if($renderer instanceof IReportValueRenderer) {
			return $renderer;
		}

		$renderer = $this->findRenderer('text');

		if($renderer instanceof IReportValueRenderer) {
			return $renderer;
		}

		throw new \RuntimeException('Missing Vizion text value renderer implementation.');
	}

	/** @return array<int,IReportValueRenderer> */
	public function getRenderers(): array {
		$instances = $this->classMap->getInstancesByInterface(IReportValueRenderer::class);
		$result = [];

		foreach($instances as $instance) {
			if($instance instanceof IReportValueRenderer) {
				$result[] = $instance;
			}
		}

		return $result;
	}

	private function findRenderer(string $name): ?IReportValueRenderer {
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
