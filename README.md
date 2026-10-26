# Vizion

**Vizion** is a plugin for the [BASE3 Framework](https://github.com/ddbase3/Base3Framework) that brings data to life through powerful, visual displays. Whether it's dynamic tables or expressive charts, Vizion aims to simplify how data is displayed and understood across your applications.

> Think of Vizion as the visual language of your data.

## What is Vizion?

Vizion provides a growing collection of modular UI components for data visualization within the BASE3 ecosystem. From bar charts and pie charts to interactive data tables, each module is designed to be intuitive, flexible, and fully compatible with BASE3 pages.

## Features

* 📊 JSON-based report configuration with field-specific control options
* ⚙️ Declarative display type switching (e.g., `DataTableReportDisplay`, `BarChartReportDisplay`, ...)
* 🧩 Config loading via `IReportConfigProvider` (e.g., from file, DB or inline)
* 📁 The central `CompositeReportConfigProvider` discovers independent technical report scopes through ResourceFoundation `IReportConfigDefinitionProvider` implementations
* 🧭 User-facing reporting areas are resolved through ResourceFoundation `IReportingScopeRegistry`, so admin UIs expose domain labels such as `ILIAS` instead of technical provider scopes
* 🔄 Dynamic Ajax support through `getOutput('json')`
* 📐 Clean MVC rendering via `IMvcView` integration
* 🔎 Sorting, paging, filtering, column visibility and layout control for tables
* 🧠 Query execution through the neutral ResourceFoundation `IQueryService` contract
* 📤 Discoverable report exporters through ResourceFoundation `IReportExporter`

## Example: Minimal Config

```json
{
  "id": "testreport",
  "display": "datatablereportdisplay",
  "fields": [
    {
      "element": { "type": "fld", "table": "git_repository", "field": "full_name" },
      "alias": "repository",
      "config": { "label": "Repository", "visible": true }
    },
    {
      "element": { "type": "fld", "table": "git_branch", "field": "name" },
      "alias": "branch",
      "config": { "label": "Branch" }
    }
  ],
  "config": {
    "pageSize": 10
  },
  "where": {
    "type": "op",
    "operator": "=",
    "params": [
      { "type": "fld", "table": "git_branch", "field": "is_default" },
      true
    ]
  }
}
```

## Usage

Use the endpoint:

```
/generalreportdisplay.json?report=testreport
```

Returns:

```json
{
  "total": 42,
  "page": 1,
  "pageSize": 10,
  "totalPages": 5,
  "data": [ { ... }, { ... }, ... ]
}
```

Or embed visually:

```php
$display = new GeneralReportDisplay();
$display->setData('testreport');
echo $display->getOutput('html');
```

## Architecture

* `GeneralReportDisplay` is the dispatcher and also acts as a fallback `IDisplay`.
* `IReportConfigProvider` resolves the report config by report key.
* `CompositeReportConfigProvider` aggregates report-definition scopes discovered through `IClassMap` and resolves qualified `reporting-scope:report` identifiers through `IReportingScopeRegistry`.
* Feature plugins contribute their own report scope through `IReportConfigDefinitionProvider` and do not replace the central Vizion service.
* Unqualified report ids work while unique. Duplicate ids across scopes are addressed as `scope:report`.
* `IDisplay` implementations (e.g. `DataTableReportDisplay`) render based on config.
* Data is fetched lazily inside each Display class using `IQueryService`. DataHawk is a common implementation, but Vizion does not depend on DataHawk classes.
* ModularGrid paging and export share `ModularGridReportQueryBuilder`, so filter/search/sort query construction has one implementation.
* ModularGrid tree filters are configured through `treeFilters` and share the same query builder with search, normal filters and export. See `docs/tree-filters.md`.
* Exporter implementations are resolved through `IClassMap` by their exact `getName()` value. See `docs/report-export.md`.

## Roadmap

* ✔️ Working: jQueryDataTable (static and Ajax)
* ✔️ Working: configured CSV, native XLSX and JSON downloads for ModularGrid reports
* ✔️ Working: legacy Excel-compatible HTML export remains available separately as `excelhtmlreportexporter`
* ✔️ Working: reusable HTML table/page, DataTable, bar chart and pie chart result exporters
* 🔜 Coming: additional Chart.js report displays, Custom Formatters, Grouping, Subtotals
* 🎯 Vision: A visual query + dashboard builder with embeddable components

## License

GPL-3.0 License


## Documentation

- [Frequently Asked Questions](docs/faq.md)
- [Privacy and data processing](PRIVACY.md)

## Count projection for optional report fields

The modular-grid count query now projects a constant row marker and the existing window count, rather than every configured display column. Expressions referenced through a structured HAVING field alias remain projected. DISTINCT queries retain their complete projected tuple to preserve existing distinctness semantics. Grouping, HAVING predicates and the active query's WHERE filters remain unchanged. This avoids triggering optional lookup joins merely because an attribute was configured as a report column. Data and export queries keep their existing field-selection contracts.

## Stable ModularGrid selection and sparse field queries

A ModularGrid definition may declare `"rowKey": ["id"]` or a composite identity such as `"rowKey": ["order_id", "item_id"]`. Each entry must be an existing normal `fields[].alias`, together identifying each result row (including grouped rows). Hidden identity fields stay in the query even when they are not displayed. A stable selection identifier is encoded from just these values, not from all visible cells.

Selected-row export adds the decoded identity conditions directly to the report's structured WHERE expression before `IQueryService::executeQuery()`. No SQL strings are assembled in Vizion. Static report restrictions and query-service access constraints remain active. Existing reports without `rowKey` retain their previous selection semantics, while configured reports no longer require a full-table PHP selection pass.

Browser column visibility is sent as `fields` in page requests. The server adds identity fields and URL-renderer dependencies to the requested projection and continues to apply active search, filter, tree, grouping and sort conditions. Hide/show actions trigger report reloads through the existing ModularGrid column-state watcher. Report export fields remain a separately configured whitelist and are unaffected by grid visibility.
