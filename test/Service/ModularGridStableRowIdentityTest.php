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

namespace Base3\Vizion\Test\Service;

use PHPUnit\Framework\TestCase;
use Base3\Vizion\Api\IReportFilterService;
use Base3\Vizion\Api\IReportTreeFilterService;
use Base3\Vizion\Service\ModularGridReportQueryBuilder;

final class ModularGridStableRowIdentityTest extends TestCase {

	private function builder(): ModularGridReportQueryBuilder {
		return new ModularGridReportQueryBuilder(
			$this->createStub(IReportFilterService::class),
			$this->createStub(IReportTreeFilterService::class)
		);
	}

	private function report(): array {
		return [
			'table' => 'items',
			'rowKey' => ['account_id', 'item_id'],
			'fields' => [
				['alias' => 'account_id', 'element' => ['type' => 'fld', 'table' => 'items', 'field' => 'account_id']],
				['alias' => 'item_id', 'element' => ['type' => 'fld', 'table' => 'items', 'field' => 'item_id']],
				['alias' => 'title', 'element' => ['type' => 'fld', 'table' => 'items', 'field' => 'title']]
			]
		];
	}

	public function testRowIdentityDoesNotDependOnPresentationValues(): void {
		$builder = $this->builder();
		$config = $this->report();
		$a = $builder->buildRowKey($config, ['account_id' => '7', 'item_id' => '12', 'title' => 'before']);
		$b = $builder->buildRowKey($config, ['account_id' => '7', 'item_id' => '12', 'title' => 'after']);
		$this->assertSame($a, $b);
		$this->assertStringStartsWith('report-key-', $a);
	}

	public function testSelectedIdsBecomeOrdinaryStructuredWherePredicates(): void {
		$builder = $this->builder();
		$config = $this->report();
		$one = $builder->buildRowKey($config, ['account_id' => '7', 'item_id' => '12']);
		$two = $builder->buildRowKey($config, ['account_id' => '8', 'item_id' => '15']);
		$where = $builder->buildSelectionWhere($config, [$one, $two]);
		$this->assertSame('OR', $where['operator']);
		$this->assertSame('AND', $where['params'][0]['operator']);
		$this->assertSame('=', $where['params'][0]['params'][0]['operator']);
		$this->assertSame('7', $where['params'][0]['params'][0]['params'][1]);
		$this->assertSame('12', $where['params'][0]['params'][1]['params'][1]);
	}

	public function testSingleIdentityCanFilterMultipleRowsUsingIn(): void {
		$builder = $this->builder();
		$config = $this->report();
		$config['rowKey'] = ['item_id'];
		$keys = [
			$builder->buildRowKey($config, ['item_id' => '12']),
			$builder->buildRowKey($config, ['item_id' => '15'])
		];
		$where = $builder->buildSelectionWhere($config, $keys);
		$this->assertSame('IN', $where['operator']);
		$this->assertSame(['type' => 'fld', 'table' => 'items', 'field' => 'item_id'], $where['params'][0]);
		$this->assertSame(['12', '15'], array_slice($where['params'], 1));
	}

	public function testSingleAndNullIdentityUseSupportedQueryOperators(): void {
		$builder = $this->builder();
		$config = $this->report();
		$key = $builder->buildRowKey($config, ['account_id' => null, 'item_id' => 12]);
		$where = $builder->buildSelectionWhere($config, [$key]);
		$this->assertSame('IS NULL', $where['params'][0]['operator']);
		$this->assertSame('=', $where['params'][1]['operator']);
	}

	public function testForgedKeysDoNotProduceWhereExpressions(): void {
		$this->expectException(\InvalidArgumentException::class);
		$this->builder()->buildSelectionWhere($this->report(), ['report-key-{}']);
	}

	public function testDataQueryProjectsOnlyRequestedAndIdentityFields(): void {
		$builder = $this->builder();
		$config = $this->report();
		$query = $builder->buildDataQuery($config, $config, [], ['title', 'account_id', 'item_id']);
		$this->assertSame(['account_id', 'item_id', 'title'], array_column($query['fields'], 'alias'));
		$query = $builder->buildDataQuery($config, $config, [], ['account_id', 'item_id']);
		$this->assertSame(['account_id', 'item_id'], array_column($query['fields'], 'alias'));
	}

	public function testUnknownOrRepeatedIdentityAliasesAreRejected(): void {
		$builder = $this->builder();
		$config = $this->report();
		$config['rowKey'] = ['account_id', 'account_id'];
		$this->expectException(\InvalidArgumentException::class);
		$builder->getRowKeyAliases($config);
	}
}
