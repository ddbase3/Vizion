<?php declare(strict_types=1);

namespace Vizion\Renderer;

use Base3\Api\IClassMap;
use Vizion\Api\IReportRowRenderer;

/**
 * Resolves row renderers by technical ClassMap name or vdef renderer type.
 */
final class ReportRowRendererRegistry {

	public function __construct(private readonly IClassMap $classMap) {}

	public function getRenderer(string $name): ?IReportRowRenderer {
		$name = $this->normalizeName($name);
		$renderer = $this->classMap->getInstanceByInterfaceName(IReportRowRenderer::class, $name);

		if($renderer instanceof IReportRowRenderer) {
			return $renderer;
		}

		foreach($this->getRenderers() as $candidate) {
			if($this->matches($name, $candidate::getName(), $candidate->getRendererType(), $candidate->getAliases())) {
				return $candidate;
			}
		}

		return null;
	}

	/** @return array<int,IReportRowRenderer> */
	public function getRenderers(): array {
		$instances = $this->classMap->getInstancesByInterface(IReportRowRenderer::class);
		$result = [];

		foreach($instances as $instance) {
			if($instance instanceof IReportRowRenderer) {
				$result[] = $instance;
			}
		}

		return $result;
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
