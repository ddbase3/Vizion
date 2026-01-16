<?php declare(strict_types=1);

namespace Vizion\Test\ReportDisplay;

use Base3\Api\IClassMap;

final class TestClassMap implements IClassMap {

	public array $calls = [];
	public mixed $returnValue = null;

	public function getInstanceByInterfaceName(string $interface, string $name): mixed {
		$this->calls[] = [
			'method' => 'getInstanceByInterfaceName',
			'interface' => $interface,
			'name' => $name,
		];

		return $this->returnValue;
	}

	public function instantiate(string $class) {
		return null;
	}

	public function &getInstances(array $criteria = []) {
		$empty = [];
		return $empty;
	}

	public function getPlugins() {
		return [];
	}
}
