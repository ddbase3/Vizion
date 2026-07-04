<?php declare(strict_types=1);

namespace Vizion\Api;

use Base3\Api\IBase;

/**
 * Renders one formatted field value inside a report cell.
 *
 * Value renderers are the smallest rendering unit in Vizion. They receive one
 * field value plus the complete row context and describe how this value should
 * appear in the browser. Examples are plain text, an email link, or a
 * project-specific link to a domain object.
 *
 * Value renderers must not decide the complete cell layout. Multi-line cells,
 * value combinations, badges, or conditional cell classes belong to column
 * renderers. Whole-row presentation belongs to row renderers.
 *
 * Implementations are discovered through IClassMap by this interface and
 * IBase::getName(). The getName() value must stay the unique technical class
 * name in lowercase. The short vdef name is returned by getRendererType().
 */
interface IReportValueRenderer extends IBase {

	/**
	 * Returns the canonical short renderer type used in vdefs.
	 *
	 * Examples: text, email-link, ilias-course-link.
	 *
	 * @return string Lowercase vdef renderer type
	 */
	public function getRendererType(): string;

	/**
	 * Returns alternate vdef names accepted for this value renderer.
	 *
	 * @return array<int,string>
	 */
	public function getAliases(): array;

	/**
	 * Returns the browser-side value renderer registry key.
	 *
	 * Built-in Vizion renderers use keys such as vizion.value.text. Project
	 * renderers should use a project-specific prefix, for example
	 * base3iliaslab.value.iliasCourseLink.
	 *
	 * @param array<string,mixed> $rendererConfig vdef valueRenderer config
	 */
	public function getClientRendererKey(array $rendererConfig): string;

	/**
	 * Returns additional browser modules required by this value renderer.
	 *
	 * Paths are AssetResolver paths and are loaded by the report display before
	 * ModularGrid is initialized. This lets project plugins add renderers without
	 * changing Vizion browser code.
	 *
	 * @param array<string,mixed> $rendererConfig vdef valueRenderer config
	 * @return array<int,string>
	 */
	public function getAssetPaths(array $rendererConfig): array;

	/**
	 * Adds value-renderer metadata to a grid column definition.
	 *
	 * The returned array must be JSON-serializable. Metadata added here must only
	 * describe rendering of this field value, not the surrounding column layout.
	 *
	 * @param array<string,mixed> $column Common grid column definition
	 * @param array<string,mixed> $field Original vdef field definition
	 * @param array<string,mixed> $rendererConfig vdef valueRenderer config
	 * @return array<string,mixed>
	 */
	public function configureValue(array $column, array $field, array $rendererConfig): array;
}
