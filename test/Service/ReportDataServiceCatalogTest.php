<?php declare(strict_types=1);

namespace Vizion\Test\Service;

use Base3\Api\IClassMap;
use Base3\Logger\Api\ILogger;
use PHPUnit\Framework\TestCase;
use ResourceFoundation\Api\IQueryService;
use ResourceFoundation\Api\IReportConfigDefinitionProvider;
use ResourceFoundation\Api\IReportingScopeRegistry;
use ResourceFoundation\Dto\ReportingScopeDefinition;
use Vizion\Api\IReportConfigProvider;
use Vizion\Api\IReportFilterService;
use Vizion\Api\IReportTreeFilterService;
use Vizion\Service\ModularGridReportQueryBuilder;
use Vizion\Service\ReportDataService;

final class ReportDataServiceCatalogTest extends TestCase {

	public function testListsOnlySupportedEnabledReportsFromConfiguredScope(): void {
		$service = $this->service([
			'alpha' => ['definition' => $this->config('Alpha Report')],
			'bravo' => ['enabled' => 'on', 'definition' => $this->config('Bravo Report')],
			'charlie' => ['enabled' => 'false', 'definition' => $this->config('Hidden')],
			'delta' => ['definition' => ['display' => 'chartreportdisplay', 'config' => ['title' => 'Chart']]],
			'echo' => ['enabled' => false, 'definition' => $this->config('Disabled')]
		]);
		$result = $service->listReports('global', '', 1);
		$this->assertSame('global', $result['reportingScope']);
		$this->assertSame('Global reports', $result['label']);
		$this->assertSame(2, $result['matchCount']);
		$this->assertTrue($result['truncated']);
		$this->assertSame('alpha', $result['reports'][0]['id']);
		$this->assertSame('global:alpha', $result['reports'][0]['qualifiedId']);
		$this->assertSame(2, $result['reports'][0]['fieldCount']);
	}

	public function testSearchRanksDescriptionFieldLabelAndTreeFilterTokens(): void {
		$alpha = $this->config('Alpha');
		$alpha['description'] = 'Catalog of remote courses';
		$alpha['treeFilters'] = [['key' => 'branch', 'label' => 'Locations', 'description' => 'Northern district']];
		$beta = $this->config('Beta');
		$beta['fields'][0]['config']['label'] = 'Northern department';
		$service = $this->service(['alpha' => ['definition' => $alpha], 'beta' => ['definition' => $beta]]);
		$result = $service->listReports('global', 'north', 10);
		$this->assertSame(2, $result['matchCount']);
		$this->assertSame(['alpha', 'beta'], array_column($result['reports'], 'id'));
		$this->assertSame('branch', $result['reports'][0]['treeFilters'][0]['key']);
		$this->assertSame('north', $result['search']);
		$this->assertFalse($result['truncated']);
	}

	public function testMissingReportingScopeAndUnknownScopeFailBeforeEnumeration(): void {
		$service = $this->service([]);
		try {
			$service->listReports(' ');
			$this->fail('A reporting scope must be required.');
		} catch(\InvalidArgumentException $exception) {
			$this->assertStringContainsString('Missing reporting scope', $exception->getMessage());
		}
		try {
			$service->listReports('unavailable');
			$this->fail('Unknown scope must not enumerate reports.');
		} catch(\RuntimeException $exception) {
			$this->assertStringContainsString('not found', $exception->getMessage());
		}
	}

	public function testDuplicateReportIdentifiersAcrossProviderScopesAreRejected(): void {
		$first = $this->definitionProvider('first', ['report' => ['definition' => $this->config('First')]]);
		$second = $this->definitionProvider('second', ['report' => ['definition' => $this->config('Second')]]);
		$service = $this->service([], [$first, $second], ['first', 'second']);
		try {
			$service->listReports('global');
			$this->fail('Ambiguous report name was exposed.');
		} catch(\RuntimeException $exception) {
			$this->assertStringContainsString('not unique inside reporting scope', $exception->getMessage());
		}
	}

	public function testMalformedEnabledDefinitionIsNotSilentlyIgnored(): void {
		$service = $this->service(['broken' => ['definition' => 'not-an-array']]);
		try {
			$service->listReports('global');
			$this->fail('Malformed report definitions must be rejected.');
		} catch(\RuntimeException $exception) {
			$this->assertStringContainsString('must contain a definition array', $exception->getMessage());
		}
	}

	public function testDescribeReportExposesFieldsFiltersTreeMetadataAndDefaults(): void {
		$config = $this->config('User overview');
		$config['description'] = ' User data '; 
		$config['fields'][0]['config'] = ['label' => 'Record ID', 'description' => ' Internal ', 'type' => 'number', 'sortable' => true, 'search' => false];
		$config['treeFilters'] = [['key' => 'group', 'label' => 'Groups', 'description' => ' Parent membership ', 'emptyLabel' => 'All groups']];
		$config['config']['pageSize'] = 35;
		$config['config']['sortColumn'] = 'id';
		$config['config']['sortDirection'] = 'desc';
		$provider = $this->createStub(IReportConfigProvider::class);
		$provider->method('getConfig')->willReturn($config);
		$filters = $this->createStub(IReportFilterService::class);
		$filters->method('buildGridFilterFields')->willReturn([
			['key' => 'group', 'label' => 'Groups', 'type' => 'select', 'options' => [['value' => 1, 'label' => 'One']], 'initialValue' => 1],
			['key' => ''],
			'ignored'
		]);
		$service = $this->service([], null, ['custom'], $provider, $filters);
		$result = $service->describeReport('custom:report');
		$this->assertSame('custom:report', $result['id']);
		$this->assertSame('User overview', $result['label']);
		$this->assertSame('User data', $result['description']);
		$this->assertSame('Record ID', $result['fields'][0]['label']);
		$this->assertSame('Internal', $result['fields'][0]['description']);
		$this->assertFalse($result['fields'][0]['searchable']);
		$this->assertTrue($result['fields'][0]['sortable']);
		$this->assertSame(1, $result['filters'][0]['initialValue']);
		$this->assertSame('All groups', $result['treeFilters'][0]['emptyLabel']);
		$this->assertSame(['pageSize' => 35, 'sortColumn' => 'id', 'sortDirection' => 'desc'], $result['defaults']);
	}

	private function config(string $name): array {
		return ['display' => 'modulargridreportdisplay', 'table' => 'people', 'description' => '',
			'config' => ['title' => $name], 'fields' => [
				['alias' => 'id', 'element' => ['type' => 'fld', 'table' => 'people', 'field' => 'id'], 'config' => ['label' => 'ID']],
				['alias' => 'name', 'element' => ['type' => 'fld', 'table' => 'people', 'field' => 'name'], 'config' => ['label' => 'Name']]
			]
		];
	}

	private function definitionProvider(string $scope, array $datasets): IReportConfigDefinitionProvider {
		$provider = $this->createStub(IReportConfigDefinitionProvider::class);
		$provider->method('getScope')->willReturn($scope);
		$provider->method('getDefinitions')->willReturn($datasets);
		return $provider;
	}

	private function service(array $datasets, ?array $providers = null, array $scopes = ['custom'], ?IReportConfigProvider $configProvider = null, ?IReportFilterService $filters = null): ReportDataService {
		$map = $this->createStub(IClassMap::class);
		$map->method('getInstancesByInterface')->willReturn($providers ?? [$this->definitionProvider('custom', $datasets)]);
		$scopeRegistry = $this->createStub(IReportingScopeRegistry::class);
		$scopeRegistry->method('get')->willReturnCallback(static fn(string $id): ?ReportingScopeDefinition => $id === 'global' ? new ReportingScopeDefinition('global', 'Global reports', reportScopes: $scopes) : null);
		$filters = $filters ?? $this->createStub(IReportFilterService::class);
		$trees = $this->createStub(IReportTreeFilterService::class);
		return new ReportDataService($map, $scopeRegistry, $configProvider ?? $this->createStub(IReportConfigProvider::class),
			$this->createStub(IQueryService::class), $filters, $trees, new ModularGridReportQueryBuilder($filters, $trees), $this->createStub(ILogger::class));
	}
}
