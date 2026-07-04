# Vizion report extension architecture

Vizion keeps report data fetching, value formatting, and browser rendering separate. This lets reusable Vizion reports stay project-neutral while project plugins add domain-specific presentation behavior through BASE3 ClassMap discovery.

## Rendering layers

### Value formatter

A value formatter describes the semantic value format. It is used by grids, charts, and other report presentations.

Examples:

- `text`
- `number`
- `date`
- `datetime`
- `email`
- `enum`

PHP implementations implement:

```php
Vizion\Api\IReportValueFormatter
```

The technical `getName()` value is the lowercase class name, for example `emailvalueformatter`. The vdef-facing name is returned by `getFormatterType()`.

### Value renderer

A value renderer renders one formatted field value. It may use the complete row context, but it must not decide the whole cell layout.

Examples:

- `text`
- `email-link`
- project-specific `ilias-course-link`

PHP implementations implement:

```php
Vizion\Api\IReportValueRenderer
```

Value renderers may return required browser asset paths. The display resolves and imports those modules before ModularGrid is initialized, so project plugins can add renderers without changing Vizion JavaScript.

Vdef example:

```json
{
  "alias": "email",
  "config": {
    "label": "E-Mail",
    "formatter": {
      "type": "email"
    },
    "valueRenderer": {
      "type": "email-link"
    }
  }
}
```

Project-specific value renderer example:

```json
{
  "alias": "course_title",
  "config": {
    "label": "Kurs",
    "valueRenderer": {
      "type": "ilias-course-link",
      "refIdField": "ref_id",
      "objectType": "crs"
    }
  }
}
```

### Column renderer

A column renderer controls the complete cell area for one column. It may combine several values, create two-line layouts, add badges, or apply conditional classes. It is not the right layer for a simple one-value link.

PHP implementations implement:

```php
Vizion\Api\IReportColumnRenderer
```

Vdef example for future use:

```json
{
  "alias": "course_title",
  "config": {
    "columnRenderer": {
      "type": "two-line",
      "primaryField": "course_title",
      "secondaryField": "institution"
    }
  }
}
```

### Row renderer

A row renderer is reserved for whole-row presentation concerns such as row classes, warning states, or disabled rows.

PHP implementations implement:

```php
Vizion\Api\IReportRowRenderer
```

The interface is prepared so report displays can opt into row-level rendering without mixing row behavior into value or column renderers.

## Browser registration

Vizion loads its built-in browser renderer registry from:

```text
plugin/Vizion/assets/js/vizion-report-cell-renderers.js
```

Additional project renderer modules are discovered from PHP renderer implementations through `getAssetPaths()`. A project module exports:

```js
export function registerReportRenderers(tools) {
    tools.registerValueRenderer('project.value.example', rendererFunction);
}
```

The tools object exposes:

- `registerValueRenderer(key, fn)`
- `registerColumnRenderer(key, fn)`
- `registerRowRenderer(key, fn)`
- `formatValue(value, column)`
- `renderValue(value, row, column)`
- `getRowValue(row, key, currentValue)`
- `createTextValue(context)`

## ClassMap naming

Discoverable renderer and formatter implementations must use full lowercase technical class names for `getName()`:

```php
EmailLinkValueRenderer::getName() // emaillinkvaluerenderer
```

Short vdef names belong to explicit type methods:

```php
EmailLinkValueRenderer::getRendererType() // email-link
```

This prevents ClassMap collisions while preserving concise vdefs.
