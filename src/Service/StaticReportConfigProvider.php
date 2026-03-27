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

use Vizion\Api\IReportConfigProvider;

class StaticReportConfigProvider implements IReportConfigProvider {

	public function getConfig(string $report): array {
		if ($report !== "example") {
			throw new \Exception("Report not found: $report");
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
						"label" => "Repository",
						"sortable" => true,
						"filter" => [ "type" => "text", "placeholder" => "Filter Repository" ]
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
						"label" => "Language",
						"sortable" => true,
						"filter" => [ "type" => "text", "placeholder" => "Filter Language" ]
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
						"label" => "License",
						"sortable" => true,
						"filter" => [ "type" => "text", "placeholder" => "Filter License" ]
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
						"label" => "Owner",
						"sortable" => true,
						"filter" => [ "type" => "text", "placeholder" => "Filter Owner" ]
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
						"label" => "Branch",
						"sortable" => true,
						"filter" => [ "type" => "text", "placeholder" => "Filter Branch" ]
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
						"label" => "Size",
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
}

