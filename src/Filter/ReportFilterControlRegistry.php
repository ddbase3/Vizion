<?php declare(strict_types=1);

namespace Vizion\Filter;

use Base3\Api\IClassMap;
use Vizion\Api\IReportFilterControl;

final class ReportFilterControlRegistry {

	public function __construct(private readonly IClassMap $classMap) {}

	public function getControl(string $name): IReportFilterControl {
		$name = $this->normalizeName($name);
		$control = $this->classMap->getInstanceByInterfaceName(IReportFilterControl::class, $name);

		if($control instanceof IReportFilterControl) {
			return $control;
		}

		$control = $this->findControl($name);

		if($control instanceof IReportFilterControl) {
			return $control;
		}

		$control = $this->findControl('native');

		if($control instanceof IReportFilterControl) {
			return $control;
		}

		throw new \RuntimeException('Missing Vizion native filter control implementation.');
	}

	/** @return array<int,IReportFilterControl> */
	public function getControls(): array {
		$instances = $this->classMap->getInstancesByInterface(IReportFilterControl::class);
		$result = [];

		foreach($instances as $instance) {
			if($instance instanceof IReportFilterControl) {
				$result[] = $instance;
			}
		}

		return $result;
	}

	private function findControl(string $name): ?IReportFilterControl {
		foreach($this->getControls() as $candidate) {
			if($this->matches($name, $candidate::getName(), $candidate->getControl(), $candidate->getAliases())) {
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
