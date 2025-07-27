<?php declare(strict_types=1);

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

