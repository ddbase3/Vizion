# Vizion

**Vizion** is a plugin for the [BASE3 Framework](https://github.com/ddbase3/Base3Framework) that brings data to life through powerful, visual PageModules. Whether it's dynamic tables or expressive charts, Vizion aims to simplify how data is displayed and understood across your applications.

> Think of Vizion as the visual language of your data.

## What is Vizion?

Vizion provides a growing collection of modular UI components for data visualization within the BASE3 ecosystem. From bar charts and pie charts to interactive data tables, each module is designed to be intuitive, flexible, and fully compatible with BASE3 pages.

## Features

📊 JSON-based report configuration with field-specific control options
⚙️ Declarative display type switching (e.g., `DataTableReportDisplay`, `BarChartReportDisplay`, ...)
🧩 Config loading via `IReportConfigProvider` (e.g., from file, DB or inline)
🔄 Dynamic Ajax support through `getOutput('json')`
📐 Clean MVC rendering via `IMvcView` integration
🔎 Sorting, paging, filtering, column visibility and layout control for tables
🧠 Query backend powered by [DataHawk](https://github.com/ddbase3/DataHawk)

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

* `GeneralReportDisplay` is the dispatcher and also acts as a fallback `IDisplay`
* `IReportConfigProvider` resolves the report config by report key
* `IDisplay` implementations (e.g. `DataTableReportDisplay`) render based on config
* Data is fetched lazily inside each Display class using `IReportQueryService`

## Roadmap

✔️ Working: jQueryDataTable (static and Ajax)
🔜 Coming: Chart.js (Bar, Pie, Line), Custom Formatters, Export, Grouping, Subtotals
🎯 Vision: A visual query + dashboard builder with embeddable components

## License

LGPL License

