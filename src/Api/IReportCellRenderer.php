<?php declare(strict_types=1);

namespace Vizion\Api;

use Base3\Api\IBase;

/**
 * Defines a grid-cell renderer for Vizion report presentations.
 *
 * Cell renderers describe how one table/grid cell should be rendered in the
 * browser. They may use formatter metadata but should not implement data
 * fetching or filter/query logic. Examples are text, date, email link, status
 * badge, or project-specific ILIAS object links.
 *
 * Implementations are discoverable through IClassMap by this interface and
 * IBase::getName(). getName() must be the globally unique technical class name,
 * usually the full lowercase class name such as textcellrenderer. The short vdef
 * renderer name is returned by getRendererType(). Project plugins can provide
 * additional renderers without modifying Vizion or ModularGrid.
 */
interface IReportCellRenderer extends IBase {

	/**
	 * Returns the canonical short renderer type used in vdefs.
	 *
	 * Examples are text, date, email-link, and json. This value is not used as the
	 * class map name.
	 *
	 * @return string Lowercase vdef renderer type
	 */
	public function getRendererType(): string;

	/**
	 * Returns alternate vdef renderer names accepted for this renderer.
	 *
	 * @return array<int,string> Lowercase or case-insensitive alias names
	 */
	public function getAliases(): array;

	/**
	 * Returns the key used by the browser-side renderer registry.
	 *
	 * Built-in Vizion renderers use keys such as vizion.text or vizion.date.
	 * Project renderers should use a project-specific prefix, for example
	 * base3iliaslab.iliasRefLink.
	 *
	 * @param array<string,mixed> $rendererConfig vdef renderer config
	 * @return string Client renderer registry key
	 */
	public function getClientRendererKey(array $rendererConfig): string;

	/**
	 * Applies renderer metadata to a ModularGrid column definition.
	 *
	 * The input column already contains common keys such as key, label, type,
	 * width, visible, rendererKey, and formatter. Implementations may add
	 * renderer-specific configuration such as target, urlField, labelField, map, or
	 * icon settings. The result must be JSON-serializable.
	 *
	 * @param array<string,mixed> $column Common grid column definition
	 * @param array<string,mixed> $field Original vdef field definition
	 * @param array<string,mixed> $rendererConfig vdef renderer config
	 * @return array<string,mixed> Grid column definition
	 */
	public function configureColumn(array $column, array $field, array $rendererConfig): array;
}
