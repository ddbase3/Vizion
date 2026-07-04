<?php declare(strict_types=1);

namespace Vizion\Renderer\Value;

use Vizion\Api\IReportValueRenderer;

/**
 * Renders one email-like value as a mailto link when an address is present.
 */
final class EmailLinkValueRenderer implements IReportValueRenderer {

	public static function getName(): string {
		return 'emaillinkvaluerenderer';
	}

	public function getRendererType(): string {
		return 'email-link';
	}

	public function getAliases(): array {
		return ['emaillink', 'email', 'mail', 'mailto'];
	}

	public function getClientRendererKey(array $rendererConfig): string {
		return 'vizion.value.emailLink';
	}

	public function getAssetPaths(array $rendererConfig): array {
		return [];
	}

	public function configureValue(array $column, array $field, array $rendererConfig): array {
		$column['valueRendererConfig'] = $this->extractRendererConfig($rendererConfig);

		return $column;
	}

	/** @return array<string,mixed> */
	private function extractRendererConfig(array $rendererConfig): array {
		unset($rendererConfig['type']);

		return $rendererConfig;
	}
}
