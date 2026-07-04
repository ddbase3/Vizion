<?php declare(strict_types=1);

namespace Vizion\Api;

use Base3\Api\IBase;

/**
 * Defines reusable value-formatting metadata for Vizion presentations.
 *
 * Formatters describe how raw values should be displayed as text in grids,
 * chart labels, chart tooltips, exports, and other presentations. They do not
 * fetch data and do not render DOM elements. Cell renderers and chart renderers
 * can consume the formatter metadata to present the same value consistently.
 *
 * Implementations are discoverable through IClassMap by this interface and
 * IBase::getName(). getName() must be the globally unique technical class name,
 * usually the full lowercase class name such as textvalueformatter. The short
 * vdef formatter name is returned by getFormatterType(). Project plugins may
 * add their own formatters for domain-specific values.
 */
interface IReportValueFormatter extends IBase {

	/**
	 * Returns the canonical short formatter type used in vdefs.
	 *
	 * Examples are text, number, date, and enum. This value is intentionally
	 * separate from IBase::getName() so class map names remain globally unique.
	 *
	 * @return string Lowercase vdef formatter type
	 */
	public function getFormatterType(): string;

	/**
	 * Returns alternate vdef formatter names accepted for this formatter.
	 *
	 * @return array<int,string> Lowercase or case-insensitive alias names
	 */
	public function getAliases(): array;

	/**
	 * Builds client-side formatter configuration for a field.
	 *
	 * The returned array must be serializable to JSON and must not contain
	 * callables. Browser-side code resolves the formatter key and configuration in
	 * the Vizion renderer modules.
	 *
	 * @param array<string,mixed> $field Original vdef field definition
	 * @param array<string,mixed> $formatterConfig vdef formatter config
	 * @return array<string,mixed> Client-side formatter config
	 */
	public function buildClientConfig(array $field, array $formatterConfig): array;
}
