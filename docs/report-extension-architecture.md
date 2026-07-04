# Vizion Report Extension Architecture

Vizion report displays are split into server-side semantics and browser-side presentation.

## Filters

Filters are defined in vdefs through the `config.filter` object. The filter `type` describes value and query semantics. The optional `control` describes the browser control.

Examples:

```json
{
  "filter": {
    "enabled": true,
    "type": "daterange",
    "match": "between",
    "control": "chronopicker",
    "format": "DD.MM.YYYY",
    "valueFormat": "YYYY-MM-DD"
  }
}
```

Server-side filter types implement `Vizion\Api\IReportFilterType` and controls implement `Vizion\Api\IReportFilterControl`. Implementations are discovered through `IClassMap` by interface and technical `IBase::getName()` values. These technical names are lowercase class names such as `textfiltertype` or `chronopickerfiltercontrol`; short vdef names such as `text` or `chronopicker` are exposed separately through `getType()` / `getControl()` and aliases.

Built-in filter types include:

- `text`
- `select`
- `radio`
- `number`
- `slider`
- `checkbox`
- `multiselect`
- `range`
- `date`
- `datetime`
- `daterange`
- `datetimerange`

Built-in controls include:

- `native`
- `range`
- `daterange`
- `chronopicker`

The vdef remains declarative. It must not contain SQL. Filter types translate semantic matches such as `contains`, `equals`, `in`, and `between` into ResourceFoundation query arrays.

## Value formatters and cell renderers

Value formatters implement `Vizion\Api\IReportValueFormatter`. They describe text formatting that can later be shared by grids, charts, tooltips, exports, and dashboards.

Cell renderers implement `Vizion\Api\IReportCellRenderer`. They describe browser-side grid cell rendering. Formatter and renderer implementations also keep technical `getName()` values separate from short vdef names via `getFormatterType()` and `getRendererType()`.

A field may define:

```json
{
  "config": {
    "formatter": {
      "type": "date",
      "format": "DD.MM.YYYY",
      "valueFormat": "YYYY-MM-DD"
    },
    "renderer": {
      "type": "date"
    }
  }
}
```

Built-in formatters include:

- `text`
- `number`
- `date`
- `enum`

Built-in cell renderers include:

- `text`
- `number`
- `date`
- `email-link`
- `json`

## Browser registries

Browser-side filter control functions live in:

```text
assets/js/vizion-report-filter-controls.js
```

Browser-side cell rendering functions live in:

```text
assets/js/vizion-report-cell-renderers.js
```

The PHP interfaces produce JSON-serializable keys such as `renderControlKey` and `rendererKey`. The templates load these registries through `IAssetResolver` and attach the actual functions at runtime.

## Project extensions

Project plugins can add implementations under their own `src/` trees. As long as they implement the relevant Vizion interface and have a stable technical `getName()` following BASE3 class map conventions, Vizion can find them through `IClassMap`. Short vdef names belong in `getType()`, `getControl()`, `getFormatterType()`, `getRendererType()`, or aliases, not in `getName()`.

Project-specific browser code should live in project plugin assets and be loaded through `IAssetResolver`. Do not put project-only controls or renderers into ModularGrid.

## Chart report presentation

Chart reports are the next presentation target after ModularGrid and Matrix. They use the same filter definitions and formatter metadata, but render aggregated data through Chart.js.

New chart-specific extension contracts:

```text
Vizion\Api\IReportChartRenderer
Vizion\Api\IReportChartService
```

`IReportChartRenderer` implementations are discoverable by class map. Their `getName()` value is the technical lowercase class name, such as `barchartrenderer`; the short vdef chart type is returned separately by `getChartType()`.

`ChartReportDisplay` is selected through:

```json
"display": "chartreportdisplay"
```

The display loads Chart.js through the asset resolver from ClientStack and uses `IReportFilterService` for the same filter pipeline as grid reports.

## Chart report architecture

Vizion chart reports use the same report definition, filter definitions, and value formatter metadata as grid reports. A chart report is selected by a vdef with:

```json
{
  "display": "chartreportdisplay",
  "schema": "ilias_materialized",
  "table": "course_report_rows",
  "chart": {
    "type": "bar",
    "dimension": "timing_status",
    "measures": [
      {
        "aggregation": "count",
        "alias": "course_count",
        "label": "Kurse"
      }
    ]
  },
  "fields": []
}
```

The chart layer is split into these responsibilities:

* `IReportChartService` builds chart client config, ResourceFoundation query JSON, and chart payloads.
* `IReportChartRenderer` implementations translate semantic chart types into Chart.js presentation metadata.
* `ChartReportDisplay` renders the chart shell and uses the existing Vizion filter controls for report filters.
* `assets/js/vizion-report-chart.js` loads chart data through POST JSON and creates the Chart.js instance.

Chart renderers are discoverable through `IClassMap` by `IReportChartRenderer`. Their `getName()` values must be full lowercase class names, for example `barchartrenderer`; the short vdef type is returned by `getChartType()`.

Built-in chart types currently include:

* `bar`
* `line`
* `pie`
* `doughnut`

Chart queries are built from semantic vdef structures. The JSON configuration must not contain SQL fragments. Filters are normalized through `IReportFilterService`, so chart and grid reports use the same filter behavior.

### Example: filtered status chart

```json
{
  "display": "chartreportdisplay",
  "schema": "ilias_materialized",
  "table": "course_report_rows",
  "chart": {
    "type": "bar",
    "title": "Kurse nach Zeitstatus",
    "dimension": "timing_status",
    "order": "value-desc",
    "measures": [
      {
        "aggregation": "count",
        "alias": "course_count",
        "label": "Kurse"
      }
    ]
  },
  "fields": [
    {
      "alias": "timing_status",
      "element": {
        "type": "fld",
        "table": "course_report_rows",
        "field": "timing_status"
      },
      "config": {
        "label": "Zeitstatus",
        "formatter": {
          "type": "enum",
          "options": [
            { "value": "crs_timing_notset", "label": "Keine Zeitsteuerung" },
            { "value": "crs_timing_avail", "label": "Verfügbar" }
          ]
        },
        "filter": {
          "enabled": true,
          "visibility": "optional",
          "type": "multiselect",
          "match": "in",
          "options": [
            { "value": "crs_timing_notset", "label": "Keine Zeitsteuerung" },
            { "value": "crs_timing_avail", "label": "Verfügbar" }
          ]
        }
      }
    }
  ]
}
```
