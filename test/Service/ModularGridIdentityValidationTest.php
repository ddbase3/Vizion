<?php declare(strict_types=1);

namespace Vizion\Test\Service;

use PHPUnit\Framework\TestCase;
use Vizion\Api\IReportFilterService;
use Vizion\Api\IReportTreeFilterService;
use Vizion\Service\ModularGridReportQueryBuilder;

final class ModularGridIdentityValidationTest extends TestCase {

	public function testMissingOrUnknownIdentityDefinitionsAreRejected(): void {
		$builder = $this->builder();
		foreach([[], ['rowKey' => []], ['rowKey' => ['not_a_field']], ['rowKey' => 'id'], ['rowKey' => [0]]] as $override) {
			$config = array_replace($this->report(), $override);
			if($override === []) {
				unset($config['rowKey']);
			}
			try {
				$builder->buildRowKey($config, ['id' => 42]);
				$this->fail('An invalid row identity was accepted.');
			} catch(\InvalidArgumentException $exception) {
				$this->assertNotEmpty($exception->getMessage());
			}
		}
	}

	public function testIdentityRequiresExistingScalarOrNullValues(): void {
		$builder = $this->builder();
		$config = $this->report();
		foreach([[], ['id' => []], ['id' => new \stdClass()]] as $row) {
			try {
				$builder->buildRowKey($config, $row);
				$this->fail('Invalid row identity value was accepted.');
			} catch(\InvalidArgumentException $exception) {
				$this->assertStringContainsString('valid identity value', $exception->getMessage());
			}
		}
	}

	public function testSelectedRowExportRejectsMissingAndOversizedKeyLists(): void {
		$builder = $this->builder();
		$config = $this->report();
		foreach([[], array_fill(0, 5001, 'report-key-WzFd')] as $keys) {
			try {
				$builder->buildSelectionWhere($config, $keys);
				$this->fail('Invalid selected-row export was accepted.');
			} catch(\InvalidArgumentException $exception) {
				$this->assertStringContainsString('at most 5000 keys', $exception->getMessage());
			}
		}
	}

	public function testNonCanonicalAndMalformedSelectionIdentifiersAreRejected(): void {
		$builder = $this->builder();
		$valid = $builder->buildRowKey($this->report(), ['id' => 1]);
		foreach(['plain-key', 'report-key-not-base64?', 'report-key-' . str_repeat('A', 2049), $valid . '=', 'report-key-e30'] as $key) {
			try {
				$builder->buildSelectionWhere($this->report(), [$key]);
				$this->fail('Forged selected row key was accepted.');
			} catch(\InvalidArgumentException $exception) {
				$this->assertNotEmpty($exception->getMessage());
			}
		}
	}

	public function testSingleNullIdentityProducesIsNullPredicate(): void {
		$builder = $this->builder();
		$key = $builder->buildRowKey($this->report(), ['id' => null]);
		$this->assertSame(['type' => 'op', 'operator' => 'IS NULL', 'params' => [
			['type' => 'fld', 'table' => 'items', 'field' => 'id']
		]], $builder->buildSelectionWhere($this->report(), [$key]));
	}

	public function testUnknownFieldAliasesDoNotExpandProjection(): void {
		$builder = $this->builder();
		$definitions = $builder->buildFieldDefs($this->report()['fields']);
		$this->assertSame(['name', 'id'], $builder->normalizeFieldAliases([' name ', 'id', 'name', 'unknown', ''], $definitions));
		$this->assertSame(['id', 'name'], array_keys($definitions));
	}

	private function report(): array {
		return [
			'table' => 'items', 'rowKey' => ['id'],
			'fields' => [
				['alias' => 'id', 'element' => ['type' => 'fld', 'table' => 'items', 'field' => 'id']],
				['alias' => 'name', 'element' => ['type' => 'fld', 'table' => 'items', 'field' => 'name']]
			]
		];
	}

	private function builder(): ModularGridReportQueryBuilder {
		return new ModularGridReportQueryBuilder($this->createStub(IReportFilterService::class), $this->createStub(IReportTreeFilterService::class));
	}
}
