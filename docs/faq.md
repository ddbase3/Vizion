# Vizion FAQ

## What is Vizion?

Vizion is the BASE3 reporting and data-visualization plugin. It turns declarative report definitions and ResourceFoundation query results into interactive tables, modular grids, charts, matrices, metrics, and export representations.

Vizion owns report presentation and report orchestration. It does not implement the underlying resource database itself.

## How does Vizion obtain report data?

Vizion depends on the neutral `ResourceFoundation\Api\IQueryService` contract. Report displays and services build structured query definitions and pass them to the active `IQueryService` implementation.

Vizion therefore does not depend on a specific query-engine class. DataHawk is one possible implementation, but it is not hard-coded into Vizion's report execution path.

## How are reports configured?

Reports are declarative arrays, commonly loaded from JSON definitions. A definition can describe:

- display type
- schema and table
- fields and expressions
- labels and presentation options
- filters
- tree filters
- sorting and paging
- grouping
- metrics
- chart dimensions and measures
- matrix detail sources
- export configuration

`IReportConfigProvider` is the runtime contract used to resolve one report configuration.

## How are report definitions discovered?

`CompositeReportConfigProvider` discovers `ResourceFoundation\Api\IReportConfigDefinitionProvider` implementations through the BASE3 class map.

Each provider owns one technical report scope. Vizion can resolve a unique unqualified report ID, a technical `scope:report` ID, or a user-facing reporting-scope-qualified ID provided through `IReportingScopeRegistry`.

## Does Vizion still support file-based report definitions?

Yes. `FileReportConfigDefinitionProvider` reads JSON report definitions from Vizion's `local/Vizion` directory. `FileReportConfigProvider` also supports locating a unique `<report>.json` file under plugin `local/Vizion` directories.

Report identifiers are validated before file lookup.

## What display types are included?

The current source includes report displays for:

- general report dispatch
- data tables
- ModularGrid
- charts
- matrices
- matrix detail tables
- metrics

The exact runtime set can be extended through discoverable BASE3 display implementations.

## What does `GeneralReportDisplay` do?

`GeneralReportDisplay` resolves the requested report configuration, reads its configured display name, discovers that `IDisplay` through `IClassMap`, passes the report configuration to it, and delegates rendering.

It is also the shared endpoint used for HTML, JSON, tree-filter data, and report exports.

## How does Ajax reporting work?

Interactive displays can request report data through JSON endpoints. For example, ModularGrid reads JSON request bodies for paging, search, filters, sorting, tree filters, and export options.

The browser submits state, while the server rebuilds the structured query and executes it through `IQueryService`.

## Does Vizion accept client-side row data as authoritative export data?

No. For selected-row export, the browser sends selected row identifiers. Vizion re-executes the report on the server, recreates the server-side row keys, selects the matching rows, and then projects the configured export fields.

The report export documentation explicitly keeps access restrictions and report conditions on the server instead of trusting client-provided row contents.

## What export scopes are supported by ModularGrid?

The export endpoint accepts three scopes:

- `selected`, the current selected rows
- `filtered`, the current search, filters, and sorting without normal paging limits
- `all`, the report's base definition without the user's current search and filters

In all cases the query is executed through the active `IQueryService`.

## How are export fields controlled?

A report's `export.fields` list is an explicit whitelist of field aliases accepted by the export endpoint and shown to the user. `defaultFields` controls the initial selection.

Vizion does not automatically treat every visible grid column as exportable.

## Which exporters are part of this Vizion source tree?

The current repository contains exporters for:

- CSV
- Excel-compatible HTML
- JSON
- standalone HTML pages
- HTML tables
- interactive DataTable HTML
- bar-chart HTML
- pie-chart HTML

Exporter implementations use the shared ResourceFoundation `IReportExporter` contract and are discovered through `IClassMap` by their exact `getName()` value.

Additional exporters can be provided by other plugins and become available only when they are discoverable and explicitly configured for a report.

## Does an exporter execute its own query?

No. Vizion follows the ResourceFoundation export contract: query execution produces a `QueryResult`, and the exporter transforms that result.

This keeps data access separate from output-format generation.

## What is special about the JSON exporter?

`JsonReportExporter` serializes result columns and rows. If the incoming `QueryResult` contains `debugSql`, the JSON export includes that value as well.

Installations that do not want debug SQL in exports should ensure the active query service does not populate it for those contexts, or use an exporter policy appropriate to the deployment.

## How do ModularGrid filters work?

Field filter definitions are normalized by `IReportFilterService`. `ModularGridReportQueryBuilder` combines search, filters, sorting, paging, configured conditions, and tree-filter conditions into ResourceFoundation query definitions.

Normal report paging and exports intentionally share this query builder so the filtering logic is not duplicated.

## What are tree filters?

Tree filters are hierarchy-aware report filters configured through a top-level `treeFilters` array. Vizion supports several filter models, including:

- nested-set range matching
- descendant node matching
- materialized-path membership
- membership-table matching

Tree loading, selected-node validation, and report filtering all use `IQueryService`.

An unavailable selected node is rejected instead of silently becoming an unfiltered report.

## Does the browser receive the whole tree with every report request?

No. Tree nodes are loaded lazily when the tree control is opened. Normal report reloads send only the selected node identifier.

## How do chart reports work?

`ChartReportDisplay` uses structured report fields and chart configuration. A chart can define a dimension, one or more aggregated measures, sorting, a limit, and Chart.js options.

Chart measures support semantic aggregations such as `COUNT`, `SUM`, `AVG`, `MIN`, and `MAX`.

Chart filters use the same filter definitions as other reports.

## Where does Chart.js come from?

Vizion resolves the Chart.js asset through `IAssetResolver` from ClientStack. The chart report definition does not hard-code a public asset URL.

## What are metric reports?

Metric reports render compact KPI cards. Metrics can use full structured queries or shorthand modes such as first value, row count, or sum.

The metric definition uses ResourceFoundation-style structured queries rather than raw SQL strings.

## What are matrix reports?

Matrix reports use a master/detail model. The first level is a normal grid. Expanding a row loads a configured detail report through Ajax and renders the prepared row, column, and cell payload as a compact matrix table.

The detail level intentionally avoids nesting one full ModularGrid inside another.

## Can report formatting and rendering be extended?

Yes. Vizion separates several presentation layers:

- value formatters
- value renderers
- column renderers
- row renderers
- chart renderers
- filter types and controls

Implementations are discoverable through the BASE3 class map. Project-specific renderer modules can also provide browser-side renderer registration through declared asset paths.

## What is the difference between a value formatter and a value renderer?

A formatter describes semantic formatting, for example text, number, date, datetime, email, or enum handling.

A value renderer controls how one formatted value is rendered in the UI, for example plain text or an email link.

Column and row renderers operate at broader presentation scopes.

## Does Vizion store report state in the browser?

Yes. The interactive ModularGrid, Matrix, and Chart templates configure browser `sessionStorage` for report UI state.

Depending on the display, stored sections can include:

- query state
- filter values
- filter visibility
- tree-filter selections
- column state
- selected row keys
- detail-view state

The storage key includes the report identifier and a signature derived from the relevant filter configuration.

## Does Vizion use `localStorage`?

No use of `localStorage` is present in the current Vizion source reviewed for these report displays. The implemented persistent browser state uses `sessionStorage`.

## Can users copy report data to the clipboard?

Yes. ModularGrid and Matrix displays include explicit row actions that copy report information to the system clipboard when the user chooses that action.

## Does Vizion persist query results on the server?

Vizion itself does not define a result database or migration. It obtains data through `IQueryService`, renders or exports it, and can log query diagnostics through the configured BASE3 logger.

Any persistent source tables or materializations belong to the active query backend, not Vizion.

## Does Vizion log SQL?

Some current Vizion paths log the `debugSql` value returned by `IQueryService` through `ILogger`.

`ReportDataService` logs report execution debug SQL, and ModularGrid export logs export debug SQL while its `logSql` flag is enabled.

The actual storage location and retention of those log records depend on the configured BASE3 logger.

## Does Vizion implement its own user authentication or authorization?

No. Vizion does not implement a user directory or authentication layer.

Data access is delegated to `IQueryService`. The tree-filter documentation also states that host or project query-security constraints apply because hierarchy queries and nested subqueries use the same query service.

Report routes and displays must still be exposed only in a host context appropriate for the data they present.

## Does Vizion add its own CSRF token to report POST requests?

The current report templates send JSON POST requests for interactive data, tree loading, and exports, but no Vizion-specific CSRF token mechanism is implemented in this source.

If the host deployment requires CSRF protection for these endpoints, that protection must be provided at the appropriate request or host boundary.

## What is the schema display?

`DataHawkSchemaDisplay` is a Vizion display for visualizing query-schema metadata. It reads the available reporting scopes and query-schema scopes and sends table names, field names, primary keys, positions, and join relationships to the browser-side database designer.

Despite its class name, it depends on ResourceFoundation query-schema contracts rather than directly on DataHawk implementation classes.

## Does the schema display expose actual row data?

No row queries are performed by the schema display itself. It exposes schema metadata. Schema metadata can still be operationally sensitive and should only be shown in intended administrative or diagnostic contexts.

## Where can I find more detailed report documentation?

Vizion includes dedicated documents for:

- [chart reports](chart-reports.md)
- [JSON reports](json-reports.md)
- [matrix reports](matrix-reports.md)
- [metric reports](metric-reports.md)
- [report export](report-export.md)
- [report extension architecture](report-extension-architecture.md)
- [tree filters](tree-filters.md)
