<?php declare(strict_types=1);

namespace Vizion\Test\Service;

use Base3\Api\IModuleRegistry;
use PHPUnit\Framework\TestCase;
use Vizion\Service\FileReportConfigProvider;

final class FileReportConfigProviderTestDouble1 implements IModuleRegistry {
			/** @param array<string,string> $paths */
			public function __construct(private readonly array $paths) {}
			public function getModuleNames(): array { return array_keys($this->paths); }
			public function getModulePath(string $name): ?string { return $this->paths[$name] ?? null; }
			public function requireModulePath(string $name): string {
				$path = $this->getModulePath($name);
				if ($path === null) throw new \RuntimeException('Module not found: ' . $name);
				return $path;
			}
		}
