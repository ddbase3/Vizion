<?php declare(strict_types=1);

namespace Vizion\Api;

use Base3\Api\IBase;

/**
 * Describes whole-row rendering for future Vizion grid presentations.
 *
 * Row renderers are intended for complete-row concerns such as conditional row
 * classes, warning states, disabled rows, or project-specific row decorations.
 * They are intentionally separate from value renderers and column renderers.
 *
 * The current ModularGrid integration prepares this extension point but does
 * not require a row renderer for normal reports.
 */
interface IReportRowRenderer extends IBase {

	/**
	 * Returns the canonical short row renderer type used in vdefs.
	 */
	public function getRendererType(): string;

	/**
	 * Returns alternate vdef names accepted for this row renderer.
	 *
	 * @return array<int,string>
	 */
	public function getAliases(): array;

	/**
	 * Returns the browser-side row renderer registry key.
	 *
	 * @param array<string,mixed> $rendererConfig vdef rowRenderer config
	 */
	public function getClientRendererKey(array $rendererConfig): string;

	/**
	 * Returns additional browser modules required by this row renderer.
	 *
	 * @param array<string,mixed> $rendererConfig vdef rowRenderer config
	 * @return array<int,string>
	 */
	public function getAssetPaths(array $rendererConfig): array;

	/**
	 * Builds row-renderer metadata from the complete report config.
	 *
	 * @param array<string,mixed> $reportConfig Complete report vdef
	 * @param array<string,mixed> $rendererConfig vdef rowRenderer config
	 * @return array<string,mixed>
	 */
	public function buildClientConfig(array $reportConfig, array $rendererConfig): array;
}
