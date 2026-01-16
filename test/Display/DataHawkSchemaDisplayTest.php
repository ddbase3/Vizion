<?php declare(strict_types=1);

namespace Vizion\Test\Display;

use PHPUnit\Framework\TestCase;
use Vizion\Display\DataHawkSchemaDisplay;
use Base3\Api\IMvcView;
use Base3\Api\IAssetResolver;
use ResourceFoundation\Api\IQuerySchemaProvider;

if (!defined('DIR_PLUGIN')) {
	define('DIR_PLUGIN', '/plugins/');
}

final class DataHawkSchemaDisplayTest extends TestCase {

	public function testGetNameReturnsExpectedValue(): void {
		$this->assertSame('datahawkschemadisplay', DataHawkSchemaDisplay::getName());
	}

	public function testGetHelpReturnsExpectedValue(): void {
		$viewState = $this->makeViewState();
		$display = new DataHawkSchemaDisplay(
			$this->createMvcViewStub($viewState),
			$this->createStub(IQuerySchemaProvider::class),
			$this->createStub(IAssetResolver::class)
		);

		$this->assertSame('Help of DataHawkSchemaDisplay', $display->getHelp());
	}

	public function testGetOutputAssignsViewVariablesAndBuildsSchemaData(): void {
		$schemaProvider = $this->createStub(IQuerySchemaProvider::class);
		$schemaProvider->method('getSchema')->willReturn([
			$this->makeTable(
				name: 'users',
				domain: 'crm',
				fields: [
					$this->makeField('id', 'int', false, true),
					$this->makeField('email', 'string', true, false),
				],
				joins: [
					$this->makeJoin(['users.id' => 'orders.user_id']),
				],
				position: ['x' => 10]
			),
		]);

		$assetState = ['resolved' => []];
		$assetResolver = $this->createAssetResolverStub($assetState);

		$viewState = $this->makeViewState();
		$viewState['output'] = 'HTML';
		$view = $this->createMvcViewStub($viewState);

		$display = new DataHawkSchemaDisplay($view, $schemaProvider, $assetResolver);

		$out = $display->getOutput('html');
		$this->assertSame('HTML', $out);

		$this->assertSame(DIR_PLUGIN . 'Vizion', $viewState['path']);
		$this->assertSame('Display/DataHawkSchemaDisplay.php', $viewState['template']);

		$this->assertArrayHasKey('data', $viewState['assigned']);
		$this->assertArrayHasKey('resolve', $viewState['assigned']);

		$payload = $viewState['assigned']['data'];
		$this->assertIsArray($payload);

		$this->assertIsArray($payload['data'] ?? null);
		$this->assertCount(1, $payload['data']);

		$table = $payload['data'][0];
		$this->assertSame('users', $table['id']);
		$this->assertSame('users', $table['name']);

		$this->assertSame(['id'], $table['primaryKeys']);

		$this->assertSame(
			[
				['name' => 'id', 'type' => 'int'],
				['name' => 'email', 'type' => '?string'],
			],
			$table['fields']
		);

		$this->assertSame(
			['x' => 10, 'y' => 100],
			$table['position']
		);

		$this->assertIsArray($payload['foreignKeys'] ?? null);
		$this->assertCount(1, $payload['foreignKeys']);

		$fk = $payload['foreignKeys'][0];
		$this->assertSame('users', $fk['from']['tableId']);
		$this->assertSame('users', $fk['from']['tableName']);
		$this->assertSame('id', $fk['from']['fieldName']);

		$this->assertSame('orders', $fk['to']['tableId']);
		$this->assertSame('orders', $fk['to']['tableName']);
		$this->assertSame('user_id', $fk['to']['fieldName']);

		$resolve = $viewState['assigned']['resolve'];
		$this->assertIsCallable($resolve);

		$this->assertSame('/assets/css/app.css', $resolve('css/app.css'));
		$this->assertSame(['css/app.css'], $assetState['resolved']);
	}

	public function testGetOutputFiltersByDomainWhenDisplayDataHasDomain(): void {
		$schemaProvider = $this->createStub(IQuerySchemaProvider::class);
		$schemaProvider->method('getSchema')->willReturn([
			$this->makeTable(name: 't1', domain: 'crm', fields: [], joins: [], position: []),
			$this->makeTable(name: 't2', domain: 'sales', fields: [], joins: [], position: []),
		]);

		$assetResolver = $this->createStub(IAssetResolver::class);
		$assetResolver->method('resolve')->willReturnCallback(fn(string $p) => '/assets/' . $p);

		$viewState = $this->makeViewState();
		$view = $this->createMvcViewStub($viewState);

		$display = new DataHawkSchemaDisplay($view, $schemaProvider, $assetResolver);
		$display->setData(['domain' => 'crm']);

		$display->getOutput('html');

		$payload = $viewState['assigned']['data'] ?? null;
		$this->assertIsArray($payload);

		$this->assertCount(1, $payload['data']);
		$this->assertSame('t1', $payload['data'][0]['id']);
	}

	private function makeViewState(): array {
		return [
			'path' => '.',
			'template' => 'default',
			'assigned' => [],
			'output' => '',
		];
	}

	private function createMvcViewStub(array &$state): IMvcView {
		$view = $this->createStub(IMvcView::class);

		$view->method('setPath')->willReturnCallback(function(string $path = '.') use (&$state): void {
			$state['path'] = $path;
		});

		$view->method('setTemplate')->willReturnCallback(function(string $template = 'default') use (&$state): void {
			$state['template'] = $template;
		});

		$view->method('assign')->willReturnCallback(function(string $key, $value) use (&$state): void {
			$state['assigned'][$key] = $value;
		});

		$view->method('loadTemplate')->willReturnCallback(function() use (&$state): string {
			return (string)$state['output'];
		});

		return $view;
	}

	private function createAssetResolverStub(array &$state): IAssetResolver {
		$ar = $this->createStub(IAssetResolver::class);

		$ar->method('resolve')->willReturnCallback(function(string $path) use (&$state): string {
			$state['resolved'][] = $path;
			return '/assets/' . $path;
		});

		return $ar;
	}

	private function makeTable(
		string $name,
		string $domain,
		array $fields,
		array $joins,
		array $position
	): object {
		$t = new \stdClass();
		$t->name = $name;
		$t->domain = $domain;
		$t->fields = $fields;
		$t->joins = $joins;
		$t->position = $position;
		return $t;
	}

	private function makeField(string $name, string $type, bool $nullable, bool $primaryKey): object {
		$f = new \stdClass();
		$f->name = $name;
		$f->type = $type;
		$f->nullable = $nullable;
		$f->primaryKey = $primaryKey;
		return $f;
	}

	private function makeJoin(array $on): object {
		$j = new \stdClass();
		$j->on = $on;
		return $j;
	}
}
