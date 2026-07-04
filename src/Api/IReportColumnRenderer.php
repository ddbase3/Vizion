<?php declare(strict_types=1);

namespace Vizion\Api;

use Base3\Api\IBase;

/**
 * Renders the complete cell area for one grid column.
 *
 * Column renderers work above value renderers. They may combine several row
 * values, create two-line layouts, add badges, apply conditional cell classes,
 * or otherwise control the full cell structure. Simple conversion of one value
 * into a link or visual value belongs to IReportValueRenderer instead.
 *
 * Implementations are discovered through IClassMap by this interface and
 * IBase::getName(). The getName() value must stay the unique technical class
 * name in lowercase. The short vdef name is returned by getRendererType().
 */
interface IReportColumnRenderer extends IBase {

	/**
	 * Returns the canonical short column renderer type used in vdefs.
	 */
	public function getRendererType(): string;

	/**
	 * Returns alternate vdef names accepted for this column renderer.
	 *
	 * @return array<int,string>
	 */
	public function getAliases(): array;

	/**
	 * Returns the browser-side column renderer registry key.
	 *
	 * @param array<string,mixed> $rendererConfig vdef columnRenderer config
	 */
	public function getClientRendererKey(array $rendererConfig): string;

	/**
	 * Returns additional browser modules required by this column renderer.
	 *
	 * @param array<string,mixed> $rendererConfig vdef columnRenderer config
	 * @return array<int,string>
	 */
	public function getAssetPaths(array $rendererConfig): array;

	/**
	 * Adds column-renderer metadata to a grid column definition.
	 *
	 * @param array<string,mixed> $column Common grid column definition
	 * @param array<string,mixed> $field Original vdef field definition
	 * @param array<string,mixed> $rendererConfig vdef columnRenderer config
	 * @return array<string,mixed>
	 */
	public function configureColumn(array $column, array $field, array $rendererConfig): array;
}
