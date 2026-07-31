<?php declare(strict_types=1);

/***********************************************************************
 * This file is part of Vizion for BASE3 Framework.
 *
 * Vizion extends the BASE3 framework with modular, visual display
 * components for reports and structured data. It provides flexible
 * renderers such as interactive tables and charts, driven by
 * declarative configuration and seamlessly integrated into BASE3 pages.
 *
 * Developed by Daniel Dahme
 * Licensed under GPL-3.0
 * https://www.gnu.org/licenses/gpl-3.0.en.html
 *
 * https://base3.de/v/vizion
 * https://github.com/ddbase3/Vizion
 **********************************************************************/

namespace Vizion\Service;

use Base3\Translation\Api\ITranslation;
use Vizion\Api\IReportConfigProvider;

class StaticReportConfigProvider implements IReportConfigProvider {

	public function __construct(private readonly ITranslation $translation) {}

	public function getConfig(string $report): array {
		if ($report !== "example") {
			throw new \Exception($this->t('error_report_not_found', 'Report not found: %s', $report));
		}

		return [
			"display" => "datatablereportdisplay",
			"config" => [
				"paging" => true,
				"columnSelector" => true,
				"sortColumn" => "repository_name",
				"sortDirection" =>  "asc"
			],
			"fields" => [
				[
					"alias" => "repository_name",
					"element" => [
						"type" => "fld",
						"table" => "git_repository",
						"field" => "name"
					],
					"config" => [
						"label" => $this->t('column_repository', 'Repository'),
						"sortable" => true,
						"filter" => [ "type" => "text", "placeholder" => $this->t('filter_repository', 'Filter repository') ]
					]
				],
				[
					"alias" => "language",
					"element" => [
						"type" => "fld",
						"table" => "git_repository",
						"field" => "language"
					],
					"config" => [
						"label" => $this->t('column_language', 'Language'),
						"sortable" => true,
						"filter" => [ "type" => "text", "placeholder" => $this->t('filter_language', 'Filter language') ]
					]
				],
				[
					"alias" => "license",
					"element" => [
						"type" => "fld",
						"table" => "git_license",
						"field" => "name"
					],
					"config" => [
						"label" => $this->t('column_license', 'License'),
						"sortable" => true,
						"filter" => [ "type" => "text", "placeholder" => $this->t('filter_license', 'Filter license') ]
					]
				],
				[
					"alias" => "owner",
					"element" => [
						"type" => "fld",
						"table" => "git_owner",
						"field" => "login"
					],
					"config" => [
						"label" => $this->t('column_owner', 'Owner'),
						"sortable" => true,
						"filter" => [ "type" => "text", "placeholder" => $this->t('filter_owner', 'Filter owner') ]
					]
				],
				[
					"alias" => "default_branch",
					"element" => [
						"type" => "fld",
						"table" => "git_branch",
						"field" => "name"
					],
					"config" => [
						"label" => $this->t('column_branch', 'Branch'),
						"sortable" => true,
						"filter" => [ "type" => "text", "placeholder" => $this->t('filter_branch', 'Filter branch') ]
					]
				],
				[
					"alias" => "size",
					"element" => [
						"type" => "fld",
						"table" => "git_repository",
						"field" => "size"
					],
					"config" => [
						"label" => $this->t('column_size', 'Size'),
						"sortable" => true,
						"filter" => [ "type" => "numberrange" ]
					]
				]
			],
			"table" => "git_repository",
			"where" => [
				"type" => "op",
				"operator" => "=",
				"params" => [
					[ "type" => "fld", "table" => "git_branch", "field" => "is_default" ],
					true
				]
			]
		];
	}

	private function t(string $key, string $fallback, mixed ...$values): string {
		$text = $this->translation->translate('Display', 'vizion_report_display', $key, $fallback);

		return $values === [] ? $text : vsprintf($text, $values);
	}
}

