<?php declare(strict_types=1);

namespace Vizion\Api;

use Base3\Api\IBase;

/**
 * Defines how a Vizion report filter should be rendered on the client side.
 *
 * Filter controls are separate from IReportFilterType. The type describes the
 * server-side value and query semantics; the control describes the browser UI.
 * For example, a daterange filter can be rendered by native date inputs or by
 * ChronoPicker while keeping the same server-side between semantics.
 *
 * Implementations are discovered through the BASE3 class map by this interface
 * and their IBase::getName() value. getName() must be the globally unique
 * technical class name, usually the full lowercase class name such as
 * chronopickerfiltercontrol. The short vdef control name is returned by
 * getControl(), and additional accepted names are returned by getAliases().
 */
interface IReportFilterControl extends IBase {

	/**
	 * Returns the canonical short control name used in vdefs.
	 *
	 * Examples are native, range, daterange, and chronopicker. This value is not
	 * used as the class map name.
	 *
	 * @return string Lowercase vdef control name
	 */
	public function getControl(): string;

	/**
	 * Returns alternate vdef control names accepted for this control.
	 *
	 * @return array<int,string> Lowercase or case-insensitive alias names
	 */
	public function getAliases(): array;

	/**
	 * Applies client-control metadata to a grid filter field.
	 *
	 * Implementations may set renderControlKey, control, valueType, or other
	 * browser-facing configuration values. They must not add PHP callables or
	 * JavaScript source code. The final JavaScript function is attached by the
	 * Vizion report filter-control module in the template.
	 *
	 * @param array<string,mixed> $gridField Filter field produced by the filter type
	 * @param array<string,mixed> $field Original vdef field definition
	 * @param array<string,mixed> $definition Normalized vdef filter definition
	 * @return array<string,mixed> Filter field for ModularGrid
	 */
	public function configureGridField(array $gridField, array $field, array $definition): array;

	/**
	 * Returns logical plugin asset paths required by this control.
	 *
	 * Paths must be logical asset paths such as
	 * plugin/ClientStack/assets/chronopicker/index.js. The display or asset layer
	 * resolves them through IAssetResolver. Return an empty array if no additional
	 * assets are required.
	 *
	 * @param array<string,mixed> $definition Normalized vdef filter definition
	 * @return array<int,string> Logical asset paths
	 */
	public function getAssetPaths(array $definition): array;
}
