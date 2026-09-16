# Privacy and data processing in Vizion

This document describes privacy-relevant behavior of the Vizion component itself. Vizion is a report presentation and orchestration plugin. The actual data source, authorization model, and long-term persistence of source data belong to the configured resource/query backend and host application.

## Component role

Vizion resolves report definitions, builds structured ResourceFoundation queries, executes them through `IQueryService`, renders results in browser-facing displays, and can transform results through discoverable `IReportExporter` implementations.

Vizion can therefore process any data made available by an active report definition and the configured query service, including personal or sensitive information.

## Types of data Vizion can process

Depending on report configuration, Vizion may process:

- report identifiers and reporting scopes
- table and field definitions
- query result rows
- search text
- filter values
- tree-filter selections
- sort definitions
- paging state
- selected row identifiers
- chart dimensions and measures
- matrix parameters and detail results
- metric values
- export field selections and export scopes
- schema metadata
- optional query debug SQL

Vizion does not limit report content to anonymous or aggregate information.

## Data-source and authorization boundary

Vizion executes report and hierarchy queries through `ResourceFoundation\Api\IQueryService`.

The query-service contract is therefore the main data-access boundary. Vizion does not implement a second independent row-level authorization system around query results.

The tree-filter implementation follows the same boundary. Tree loading, selected-node validation, descendant lookup, membership lookup, and the final report query all use `IQueryService`.

Deployments should ensure that the active query service enforces the required user, tenant, role, object, or domain restrictions for every query scope exposed to Vizion.

## Report definitions

Vizion report definitions can reference schemas, tables, fields, filters, expressions, tree sources, export fields, and presentation behavior.

Definitions can be provided through discoverable `IReportConfigDefinitionProvider` implementations. The package also contains file-based providers that read JSON definitions from `local/Vizion` directories.

Report definitions are configuration, not report-result storage. They may nevertheless reveal internal schema names, field names, or business semantics and should be managed with appropriate repository and filesystem access.

## Request data

Interactive report displays read request information through BASE3 `IRequest`.

JSON request bodies can contain:

- paging state
- search text
- sorting
- filters
- tree-filter values
- export format selection
- export scope
- selected row IDs
- selected export fields

These values can themselves be personal or confidential when, for example, a user searches for a name, identifier, email address, organizational unit, or other sensitive term.

## JSON report responses

Vizion can return report rows as JSON. The exact fields are defined by the active report and query result.

`GeneralReportDisplay` and the report displays also return exception messages in error responses. Depending on the underlying exception, these messages can contain technical details. Production deployments should avoid exposing report endpoints to users who should not receive such diagnostics and should control upstream exception content where necessary.

## Browser session storage

Current ModularGrid, Matrix, and Chart templates configure browser `sessionStorage` for UI state.

Depending on the display, stored state can include:

- query state
- search terms
- filter values
- filter visibility
- tree-filter selections
- column state
- selected row keys
- detail-view state

This state remains in the browser session storage associated with the page origin and browser tab/session according to browser behavior. Vizion does not copy this state to its own server-side state store.

No use of browser `localStorage` was found in the reviewed Vizion report source.

Deployments should consider that filter values and search terms can contain personal data even when full report rows are not stored in `sessionStorage`.

## Clipboard operations

ModularGrid and Matrix displays expose user-triggered actions for copying report row data to the system clipboard.

After a user copies data, the clipboard is outside Vizion's server-side control. Users should only copy report content into destinations authorized to receive it.

## Report exports

ModularGrid can generate downloadable exports. Export requests can operate on selected rows, current filtering, or all report data allowed by the report definition and query service.

Vizion does not trust client-provided row bodies for selected-row export. It re-executes the report server-side and filters the server result using selected row identifiers.

Export fields are restricted to the report's configured whitelist.

Once an export is downloaded, the resulting file is under the control of the browser, user, device, and downstream storage location. Retention and sharing of downloaded files are no longer controlled by Vizion.

## Export formats and result content

The current Vizion source contains concrete exporters for CSV, Excel-compatible HTML, JSON, standalone HTML, HTML tables, DataTable HTML, bar-chart HTML, and pie-chart HTML.

Other plugins can provide additional `IReportExporter` implementations.

All exporters receive a `QueryResult`, so exported content can include personal or sensitive data when the result contains it.

## JSON export and debug SQL

`JsonReportExporter` includes `debugSql` in the exported JSON when that property is present on the `QueryResult`.

Debug SQL can reveal schema names, table names, field names, identifiers, filter values, or other query context depending on the active query implementation.

Installations that expose JSON export should decide whether query results in that context are allowed to carry debug SQL.

## SQL diagnostic logging

Current Vizion code logs query diagnostics through the configured BASE3 `ILogger` in at least these paths:

- `ReportDataService` logs `REPORTDATA RES | <debugSql>` after report execution
- `ModularGridReportDisplay` logs `MODULARGRID EXPORT | <debugSql>` for exports while SQL logging is enabled

Vizion itself does not define the logger backend, log storage path, rotation, or retention period.

Because debug SQL may contain values derived from report conditions or user filters, the configured logger should be treated as potentially containing personal or confidential data.

## Row keys

For interactive selection and exports, Vizion creates row keys by normalizing the row values, hashing the JSON representation with SHA-1, and exposing a shortened hash prefixed with `report-row-`.

The key is an identifier derived from row content, not a substitute for authorization. Selected-row export always re-runs the server query and matches the resulting server-side keys.

## Tree filters

Tree filter controls load hierarchy data lazily. Normal report reloads submit the selected node ID rather than resending the complete hierarchy.

A tree definition can expose node identifiers and labels to the browser. Those values may be personal or organizational data depending on the configured hierarchy.

Selected nodes are validated through `IQueryService`; unavailable selections are rejected.

## Charts and metrics

Chart and metric reports process query results server-side and send the resulting values needed for display to the browser.

Chart data can contain category labels and aggregated or pre-aggregated values. Metric cards can expose counts, sums, or selected scalar values. Aggregation does not automatically guarantee anonymity, especially for small groups or identifying labels.

Privacy decisions for a report must therefore consider the actual report definition, not only its display type.

## Matrix reports

Matrix reports load detail data when a master row is expanded. The master-row parameter is sent to the detail endpoint, and the resulting prepared row, column, and cell payload is rendered in the browser.

This means detail data is disclosed only when requested, but it remains subject to the same query-service authorization boundary as other report data.

## Schema metadata display

`DataHawkSchemaDisplay` renders query-schema metadata, including table names, field names, primary keys, positions, and join relationships for available reporting scopes.

The display does not query business rows itself, but schema information can still reveal internal system structure. Access to this display should be limited to users intended to receive that metadata.

No component-specific permission check is implemented inside `DataHawkSchemaDisplay` itself.

## Authentication and authorization

Vizion does not implement user authentication.

It also does not contain a universal Vizion-specific permission check around every report display. Data-level authorization is expected to be enforced by the active query service and by the host routing or UI context that exposes the report.

A report being technically discoverable or having a valid report identifier must not be treated as an authorization decision by itself.

## CSRF protection

The current browser templates use JSON POST requests for interactive report loading, hierarchy loading, detail loading, and export operations.

No Vizion-specific CSRF token is added by these report templates in the reviewed source.

Where CSRF protection is required, it must be supplied by the host request layer, surrounding application, or another appropriate security boundary.

## Asset loading

Vizion resolves browser assets through `IAssetResolver`. Built-in report displays reference local plugin assets and ClientStack assets, such as ModularGrid, ChronoPicker, DataTable, and Chart.js.

The reviewed Vizion source does not directly configure a third-party analytics endpoint or remote tracking service.

Project-specific renderers can declare additional asset paths. The privacy properties of those project assets must be assessed where they are implemented.

## Network communication

Vizion itself communicates with its own report endpoints in the browser using `fetch()` and obtains server-side data through `IQueryService`.

Whether `IQueryService` accesses a local database or a remote service is outside Vizion's implementation boundary.

The concrete query backend should document any network transfer or external processing it performs.

## Server-side persistence

Vizion does not define its own database migration or persistent report-result repository in this package.

Report definitions can be file-backed, and logs can be written through the configured `ILogger`. Source data and materialized tables belong to the active query backend.

Vizion therefore does not define a general server-side retention period for report rows.

## Error messages

`GeneralReportDisplay` returns exception messages in JSON, export, and HTML error responses. Several report displays also return exception messages inside JSON error payloads.

Exception text can disclose technical context if lower layers include detailed query, schema, or backend information in their messages. Production installations should ensure that exposed errors are appropriate for the intended audience.

## Data minimization

Report authors control much of Vizion's data minimization through the report definition.

Recommended measures include:

- select only fields needed by the report
- keep export field whitelists narrower than the full query when possible
- exclude helper URLs or internal identifiers from exports unless required
- prefer prepared aggregate or materialized read models where row-level detail is unnecessary
- restrict tree sources to nodes users need to see
- avoid carrying debug SQL into end-user exports
- avoid logging detailed SQL when not needed operationally

## Retention and deletion

Vizion itself does not define retention for underlying source records.

A deployment should consider retention for:

- source data in the active query backend
- materialized report data maintained by that backend
- report definition files or settings
- BASE3 logger output containing debug SQL
- browser sessionStorage state
- downloaded exports
- clipboard contents created by user actions
- proxy, web-server, or infrastructure logs that may record report URLs or requests

Deletion of source data must be handled by the system that owns that data. Vizion will reflect the result of subsequent queries.

## Installation review checklist

Before exposing reports containing personal or confidential data, verify at least:

- which reporting scopes and reports are visible
- which query scopes each report uses
- that `IQueryService` enforces the required access restrictions
- which fields are returned to the browser
- which fields are exportable
- whether debug SQL is populated
- whether debug SQL logging is required
- who can access schema visualization
- whether host-level CSRF protection covers report POST endpoints
- whether report error messages are suitable for the audience
- which browser state is stored in `sessionStorage`
- how downloaded exports are handled by organizational policy
