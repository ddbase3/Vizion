# Vizion Chart Reports

Vizion chart reports render aggregated report data with Chart.js while using the same vdef filter definitions as ModularGrid and Matrix reports.

## Display type

Use:

```json
"display": "chartreportdisplay"
```

The display loads Chart.js from ClientStack through `IAssetResolver`:

```text
plugin/ClientStack/assets/chart/chart.js
```

No chart asset path should be hardcoded in a vdef.

## Minimal example

```json
{
  "display": "chartreportdisplay",
  "schema": "ilias_materialized",
  "table": "course_report_rows",
  "chart": {
    "type": "bar",
    "title": "Kurse nach Zeitstatus",
    "dimension": "timing_status",
    "measures": [
      {
        "alias": "count",
        "label": "Kurse",
        "aggregation": "COUNT"
      }
    ],
    "sort": {
      "by": "count",
      "direction": "desc"
    }
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
        }
      }
    },
    {
      "alias": "user_active",
      "element": {
        "type": "fld",
        "table": "course_report_rows",
        "field": "user_active"
      },
      "config": {
        "label": "Benutzer aktiv",
        "filter": {
          "enabled": true,
          "visibility": "always",
          "type": "select",
          "match": "equals",
          "defaultValue": "1",
          "initialValue": "1",
          "emptyValue": "",
          "width": 150,
          "options": [
            { "value": "", "label": "Alle Benutzer" },
            { "value": "1", "label": "Nur aktive" },
            { "value": "0", "label": "Nur inaktive" }
          ]
        }
      }
    }
  ]
}
```

## Chart configuration

The `chart` section is declarative and contains no SQL.

Supported initial keys:

```text
type       Chart.js chart type such as bar, line, pie, doughnut
title      Optional chart title
dimension  Field alias used as label/category dimension
measures   Aggregated values shown as datasets
sort       Optional sort instruction
limit      Optional maximum number of result rows
options    Optional Chart.js options object
```

A measure can use one of these semantic aggregations:

```text
COUNT
SUM
AVG
MIN
MAX
```

Examples:

```json
{ "alias": "count", "label": "Datensätze", "aggregation": "COUNT" }
```

```json
{ "alias": "courses", "label": "Kurse", "field": "no_of_courses", "aggregation": "SUM" }
```

Vizion translates the measure definitions into ResourceFoundation query JSON. The vdef does not contain SQL snippets.


## Field resolution for chart data

Chart dimensions and measures can be resolved in three ways. This keeps simple vdefs compact while still allowing DataHawk-style expressions when a chart needs more control.

1. Reference a field alias from the report `fields` list:

```json
"dimension": "main_course_title"
```

2. Provide an explicit ResourceFoundation/DataHawk-style `element`:

```json
"dimension": {
  "field": "certificate_acquired_month",
  "label": "Monat",
  "element": {
    "type": "fn",
    "function": "DATE_FORMAT",
    "params": [
      { "type": "fld", "table": "certificate_report_rows", "field": "certificate_acquired_date_en" },
      "%Y-%m"
    ]
  }
}
```

3. Reference a physical field of the report table directly. If no alias exists but the report has a top-level `table`, Vizion resolves the field as:

```json
{ "type": "fld", "table": "<report-table>", "field": "<name>" }
```

This is useful for prepared chart reports where the source table already is a chart-facing/materialized table. For complex logic, prefer explicit `element` objects or prepared DataHawk materializations over adding hidden SQL snippets to Vizion vdefs.

## Filtering

Chart reports use the same `filter` objects as grid reports. Active filters are normalized by `IReportFilterService` and translated into ResourceFoundation query conditions before the chart aggregation is executed.

The chart dimension should usually not be used as a default filter. A chart grouped by `timing_status` should normally filter by another field such as `user_active`, `course_title`, or an organizational field.

Chart reports intentionally do not render a free-text search bar. Filters are explicit report controls only. Optional filters are rendered in a compact one-line filter bar. ChronoPicker controls are supported through the same `control: "chronopicker"` mechanism used by grid reports.

## Extension points

Chart types are discoverable components implementing:

```text
Vizion\Api\IReportChartRenderer
```

Implementations must use a full lowercase class-style `getName()` value, for example:

```text
barchartrenderer
```

The short vdef type is returned by `getChartType()`, for example:

```text
bar
```

Project plugins may add custom chart renderers by implementing the interface and placing the class below their `src/` directory so BASE3 can discover it through the class map.

## Data modes

Chart reports support two data modes.

```json
"dataMode": "grouped"
```

`grouped` is the default. Vizion builds a grouped ResourceFoundation query from the configured `dimension` and aggregated `measures`. This is appropriate for simple counts and sums such as courses by timing status.

```json
"dataMode": "prepared"
```

`prepared` expects the source table or materialized report table to already contain one row per chart label and concrete numeric measure fields. Vizion does not add `group_by` and does not wrap measures in aggregation functions. This is the preferred mode for non-trivial charts, pre-bucketed time series, expensive calculations, or chart data that must be curated before display.

## Complete buckets

If the chart dimension has an enum formatter with `options`, Vizion initializes all configured option values as chart buckets before applying query results. Missing result rows are emitted with numeric measure value `0`. This keeps important zero categories visible, for example a timing status with no current rows.

Buckets can also be configured explicitly:

```json
"chart": {
  "dimensionBuckets": [
    { "value": "crs_timing_notset", "label": "Keine Zeitsteuerung" },
    { "value": "crs_timing_avail", "label": "Verfügbar" }
  ]
}
```

For numeric or date-like dimensions, the raw values are used as bucket values. Labels are still produced by the configured formatter in the browser.

## Expression dimensions

A dimension may provide an explicit ResourceFoundation `element` instead of referencing a field alias. This is useful for display-level grouped charts such as month buckets:

```json
"dimension": {
  "field": "certificate_acquired_month",
  "label": "Monat",
  "element": {
    "type": "fn",
    "function": "DATE_FORMAT",
    "params": [
      { "type": "fld", "table": "certificate_report_rows", "field": "certificate_acquired_date_en" },
      "%Y-%m"
    ]
  },
  "formatter": {
    "type": "date",
    "format": "MM.YYYY",
    "valueFormat": "YYYY-MM"
  },
  "bucketInterval": "month"
}
```

`bucketInterval: "month"` fills missing months between the lowest and highest returned month with zero-valued rows. This only fills gaps; it does not replace a proper materialized chart table when business logic becomes more complex.

## Prepared multi-series bar charts

Prepared mode can also render several numeric fields as separate datasets for each row label. This is useful for matrix or completion summaries where the materialized table already contains columns such as `not_attempted_count`, `in_progress_count`, `completed_count`, and `failed_count`.

Use a normal field alias for `sort.by` when the chart should be ordered by a column that is not itself a displayed measure, for example `participants_count`.

```json
"chart": {
  "type": "bar",
  "dataMode": "prepared",
  "dimension": "main_course_title",
  "indexAxis": "y",
  "stacked": true,
  "measures": [
    { "alias": "not_attempted", "label": "Nicht begonnen", "field": "not_attempted_count" },
    { "alias": "in_progress", "label": "In Bearbeitung", "field": "in_progress_count" },
    { "alias": "completed", "label": "Abgeschlossen", "field": "completed_count" },
    { "alias": "failed", "label": "Nicht bestanden", "field": "failed_count" }
  ],
  "sort": {
    "by": "participants_count",
    "direction": "desc"
  },
  "limit": 10
}
```
