<?php declare(strict_types=1);

namespace Vizion\Test\Service;

use Base3\Api\IClassMap;
use PHPUnit\Framework\TestCase;
use ResourceFoundation\Api\IReportConfigDefinitionProvider;
use ResourceFoundation\Api\IReportingScopeRegistry;
use ResourceFoundation\Dto\ReportingScopeDefinition;
use Vizion\Service\CompositeReportConfigProvider;
use Vizion\Service\FileReportConfigDefinitionProvider;

final class CompositeReportConfigProviderTest extends TestCase {

	public function testFindsReportUsingProviderScopeOrUnqualifiedIdentifier(): void {
		$provider = $this->composite([new FileReportConfigDefinitionProvider()]);
		$qualified = $provider->getConfig('vizion:example');
		$this->assertSame('datatablereportdisplay', $qualified['display']);
		$this->assertSame($qualified, $provider->getConfig('example'));
		$this->assertSame($qualified, $provider->getConfig(' vizion:example '));
	}

	public function testRejectsInvalidAndPathLikeReportIdentifiers(): void {
		$provider = $this->composite([]);
		foreach(['', '  ', '../config', 'a/b', 'vizion:', ':example', 'Vizion:example', 'vizion:other/path'] as $name) {
			try {
				$provider->getConfig($name);
				$this->fail('Invalid report identifier was accepted: ' . $name);
			} catch(\InvalidArgumentException $exception) {
				$this->assertNotEmpty($exception->getMessage());
			}
		}
	}

	public function testMissingScopeAndReportAreDistinctErrors(): void {
		$provider = $this->composite([new FileReportConfigDefinitionProvider()]);
		foreach([
			['unknown:example', 'Report scope not found'],
			['vizion:missing', 'Report not found'],
			['missing', 'Report not found']
		] as [$identifier, $reason]) {
			try {
				$provider->getConfig($identifier);
				$this->fail('Missing report unexpectedly resolved.');
			} catch(\RuntimeException $exception) {
				$this->assertStringContainsString($reason, $exception->getMessage());
			}
		}
	}

	public function testDisabledDefinitionsAreNotExposed(): void {
		$provider = $this->definitionProvider('custom', [
			'hidden' => ['enabled' => false, 'definition' => ['display' => 'hidden']],
			'off' => ['enabled' => '0', 'definition' => ['display' => 'off']],
			'visible' => ['enabled' => 'yes', 'definition' => ['display' => 'active']]
		]);
		$config = $this->composite([$provider]);
		$this->assertSame(['display' => 'active'], $config->getConfig('custom:visible'));
		foreach(['custom:hidden', 'custom:off'] as $identifier) {
			try {
				$config->getConfig($identifier);
				$this->fail('Disabled report must remain hidden.');
			} catch(\RuntimeException $exception) {
				$this->assertStringContainsString('Report not found', $exception->getMessage());
			}
		}
	}

	public function testEnabledDefinitionWithoutArrayIsRejected(): void {
		$provider = $this->definitionProvider('custom', ['wrong' => ['definition' => 'not-array']]);
		try {
			$this->composite([$provider])->getConfig('custom:wrong');
			$this->fail('Non-array definition was accepted.');
		} catch(\RuntimeException $exception) {
			$this->assertStringContainsString('must contain a definition array', $exception->getMessage());
		}
	}

	public function testDuplicateUnqualifiedReportRequiresScope(): void {
		$first = $this->definitionProvider('first', ['summary' => ['definition' => ['table' => 'first']]]);
		$second = $this->definitionProvider('second', ['summary' => ['definition' => ['table' => 'second']]]);
		$config = $this->composite([$first, $second]);
		$this->assertSame('first', $config->getConfig('first:summary')['table']);
		$this->assertSame('second', $config->getConfig('second:summary')['table']);
		try {
			$config->getConfig('summary');
			$this->fail('Ambiguous report identifier should be rejected.');
		} catch(\RuntimeException $exception) {
			$this->assertStringContainsString('not unique', $exception->getMessage());
		}
	}

	public function testReportingScopeResolvesOnlyAssociatedProviderScopes(): void {
		$first = $this->definitionProvider('first', ['report' => ['definition' => ['table' => 'first']]]);
		$second = $this->definitionProvider('second', ['report' => ['definition' => ['table' => 'second']]]);
		$reportingScope = new ReportingScopeDefinition('public', 'Public', reportScopes: ['second']);
		$provider = $this->composite([$first, $second], $reportingScope);
		$this->assertSame(['table' => 'second'], $provider->getConfig('public:report'));
	}

	public function testReportingScopeRejectsAmbiguousOrAbsentDefinitions(): void {
		$providers = [
			$this->definitionProvider('first', ['report' => ['definition' => ['table' => 'first']]]),
			$this->definitionProvider('second', ['report' => ['definition' => ['table' => 'second']]])
		];
		$provider = $this->composite($providers, new ReportingScopeDefinition('combined', 'Combined', reportScopes: ['first', 'second']));
		try {
			$provider->getConfig('combined:report');
			$this->fail('Ambiguous report must be rejected.');
		} catch(\RuntimeException $exception) {
			$this->assertStringContainsString('not unique inside reporting scope', $exception->getMessage());
		}
		$other = $this->composite($providers, new ReportingScopeDefinition('empty', 'Empty', reportScopes: ['nonexisting']));
		try {
			$other->getConfig('empty:report');
			$this->fail('Unknown definition should not resolve.');
		} catch(\RuntimeException $exception) {
			$this->assertStringContainsString('Report not found', $exception->getMessage());
		}
	}

	private function definitionProvider(string $scope, array $definitions): IReportConfigDefinitionProvider {
		$provider = $this->createStub(IReportConfigDefinitionProvider::class);
		$provider->method('getScope')->willReturn($scope);
		$provider->method('getDefinitions')->willReturn($definitions);
		return $provider;
	}

	private function composite(array $providers, ?ReportingScopeDefinition $scope = null): CompositeReportConfigProvider {
		$map = $this->createStub(IClassMap::class);
		$map->method('getInstancesByInterface')->willReturn($providers);
		$scopes = $this->createStub(IReportingScopeRegistry::class);
		$scopes->method('get')->willReturnCallback(static fn(string $id): ?ReportingScopeDefinition => $scope !== null && $scope->id === $id ? $scope : null);
		return new CompositeReportConfigProvider($map, $scopes);
	}
}
