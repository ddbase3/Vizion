# Vizion report export

## Architecture

Vizion owns report presentation and report export orchestration. It does not depend on DataHawk classes.

The runtime boundary is:

```text
Vizion report definition
        |
        v
ModularGridReportQueryBuilder
        |
        v
ResourceFoundation\Api\IQueryService
        |
        v
ResourceFoundation\Dto\QueryResult
        |
        v
ResourceFoundation\Api\IReportExporter
        |
        v
CSV / Excel HTML / XLSX / JSON / HTML UI exporters
```

DataHawk is one possible `IQueryService` implementation. It remains usable without Vizion.

`IReportExporter` lives in ResourceFoundation because query results and result transformation are shared contracts. Concrete exporters live in Vizion because they belong to reporting/output concerns.

There is no exporter factory. Exporters are discoverable BASE3 components and are resolved through `IClassMap` by their exact `getName()` value.

## Exporter names

Vizion currently provides these exporters with stable technical names:

| Exporter | `getName()` | Typical use |
| --- | --- | --- |
| `CsvReportExporter` | `csvreportexporter` | CSV download |
| `ExcelHtmlReportExporter` | `excelhtmlreportexporter` | Legacy Excel-compatible HTML download (`.xls`) |
| `XlsxReportExporter` | `xlsxreportexporter` | Native Office Open XML workbook (`.xlsx`) |
| `JsonReportExporter` | `jsonreportexporter` | JSON download/integration |
| `HtmlTableReportExporter` | `htmltablereportexporter` | Embeddable HTML table |
| `HtmlPageReportExporter` | `htmlpagereportexporter` | Standalone HTML page |
| `DataTableReportExporter` | `datatablereportexporter` | Interactive ClientStack DataTable output |
| `BarChartReportExporter` | `barchartreportexporter` | ClientStack Chart.js bar chart output |
| `PieChartReportExporter` | `piechartreportexporter` | ClientStack Chart.js pie chart output |

The UI download reports currently configure only CSV, native XLSX and JSON. The legacy Excel HTML exporter and the HTML/UI exporters remain available through `IClassMap` for consumers that explicitly configure or resolve them.

`ExcelHtmlReportExporter` keeps its original behavior and identity. It emits Excel-compatible HTML with the `.xls` extension. Native OOXML output is a separate component, `XlsxReportExporter`, with the technical name `xlsxreportexporter`. Existing exporter names are never repurposed for another format.

PDF is intentionally not implemented until a PDF rendering engine has been selected.

## Report configuration

A ModularGrid report becomes exportable by adding an `export` block:

```json
{
  "export": {
    "exporters": [
      "csvreportexporter",
      "xlsxreportexporter",
      "jsonreportexporter"
    ],
    "fields": [
      "course_title",
      "login",
      "fullname_de",
      "email",
      "usr_id"
    ],
    "defaultFields": [
      "course_title",
      "login",
      "fullname_de",
      "email"
    ]
  }
}
```

### `exporters`

The values are exact exporter `getName()` values. Vizion does not maintain a second format-name registry.

If an exporter is configured but not discoverable through `IClassMap`, it is not shown in the export UI and cannot be invoked by the endpoint.

### `fields`

`fields` is the explicit whitelist shown in the export field selector and accepted by the export endpoint. Every value is an alias from the report's normal `fields` array. Vizion does not infer exportability from visible grid columns.

This distinction is important because presentation and export values are not always the same thing. Grid renderers such as `linkvaluerenderer`, `emaillinkvaluerenderer` or `mapvaluerenderer` are UI-only. Exporters receive the raw `QueryResult` value selected by the configured field alias. For example, a `course_title` field may render as an ILIAS course link in the grid while exporting only the plain course title.

If an export must use a different value than the visible grid field, define that value once as a normal report field, usually with `config.visible = false`, and reference that alias from `export.fields`. Do not duplicate the query element inside the export block.

Direct URL/helper fields should simply not be included in `export.fields` when they are not intended for export. For example, `certificate_download_link` is intentionally excluded from the certificate report export whitelist.

### `defaultFields`

`defaultFields` is the configured initial checkbox selection and must be a subset of `export.fields`. Users may change the selection in the export dropdown before starting the export.

There is no implicit fallback from visible grid columns. Reports must deliberately configure their export field whitelist.

## Export UI

The ModularGrid `ExportPlugin` contributes one green control to `topLine1`. The report topline remains horizontal and single-line.

The opened dropdown has three tabs:

- Format
- Data
- Fields

The dropdown is allowed to use vertical space internally. Only the topline itself is constrained to a single horizontal row.

The ModularGrid plugin is UI-only. It does not serialize CSV/JSON itself. It sends the user's export choices to the Vizion export endpoint.

## Export scopes

### Current selection

Exports the currently selected report rows. Selection identifiers are sent to the server. Vizion re-executes the report through `IQueryService`, identifies the selected rows using the same server-side row-key algorithm used by the grid, and then applies the requested export field projection.

This keeps access control and report conditions on the server. Client-provided row contents are never accepted as export data.

### Current filtering

Uses the current ModularGrid search, filters and sorting, without paging limits.

### All data

Uses the report's base definition and static conditions without the user's current search or filters. Access restrictions applied by the active `IQueryService` still remain in force.

## Shared query construction

`ModularGridReportQueryBuilder` is the single implementation for ModularGrid request normalization and structured query construction.

Both normal grid paging and export use it. Search rules, filter translation, sorting, grouping, `where`, `having` and field definitions are therefore not copied into a second export path.

## Export endpoint

The existing `GeneralReportDisplay` route is reused:

```text
name=generalreportdisplay
out=export
report=<report id>
```

The request body contains:

```json
{
  "exporter": "csvreportexporter",
  "scope": "filtered",
  "fields": ["login", "email"],
  "selectedRowIds": [],
  "request": {
    "search": "example",
    "sort": [],
    "filters": {}
  }
}
```

Vizion validates the configured exporter, scope and field aliases before running the query.

## Adding another report exporter

1. Add an `IReportExporter` implementation under Vizion `src/Export/`.
2. Give it a stable lowercase `getName()` value.
3. Transform only `QueryResult`. Do not execute queries inside the exporter.
4. Add exporter tests.
5. Add the exact `getName()` value to report definitions that should expose it.
6. Add a UI label in `ModularGridReportDisplay::getExporterLabel()` only when a friendlier display label is desired.

No change to DataHawk is required.
