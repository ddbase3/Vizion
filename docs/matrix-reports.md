# Matrix reports

Vizion matrix reports render a two-level report without project-specific display code.

The first level is a normal ModularGrid backed by a stable report table. A row detail loads a configured subreport by Ajax and renders the subreport payload as a plain HTML matrix table.

## Displays

The implementation is split into two Vizion displays:

```text
matrixreportdisplay
matrixtablereportdisplay
```

`matrixreportdisplay` owns the master grid and the row-detail Ajax endpoint.

`matrixtablereportdisplay` is the detail subreport. It receives the parent report config and one runtime parameter, loads configured row, column and cell sources, and returns a normalized matrix-table payload.

This keeps the parent display generic and allows other reports to use the same master/detail pattern later.

## Configuration shape

A matrix report config starts like other Vizion report configs:

```json
{
	"display": "matrixreportdisplay",
	"schema": "ilias_materialized",
	"table": "matrix_headline_rows",
	"fields": []
}
```

The `fields` array describes the first-level headline grid. It uses the same DataHawk field expression style as other Vizion reports.

The `detail` block describes the subreport:

```json
{
	"detail": {
		"display": "matrixtablereportdisplay",
		"parameter": "main_course_obj_id",
		"headline": {},
		"columns": {},
		"rows": {},
		"cells": {},
		"table": {}
	}
}
```

The parameter is read from the expanded master row and passed to the subreport Ajax request.

## Data shape

The detail subreport expects normalized materialized sources:

```text
headline source  one row for the selected master item
columns source   one row per dynamic matrix column
rows source      one row per matrix row
cells source     one row per row/column value
```

The browser only pivots the already prepared row/column/cell payload for one selected master row. The full report is not rendered as one wide grid.

## Why the detail is plain HTML

The second level intentionally does not create a ModularGrid inside another ModularGrid. The detail table is a compact HTML table because it is loaded only for one expanded master row and because matrix columns are dynamic per master row.

This keeps the first level fast and avoids nested grid state, nested infinite scrolling and nested column management.
