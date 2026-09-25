# ADR-002：路由与 Slug 模型

## Status

Accepted for v0.1.0

## Decision

不修改：

```php
$_SERVER['REQUEST_URI']
```

采用：

```text
WordPress Native Rewrite
+
Query Vars
+
Object Slug Overlay
+
Permalink Filters
```

Source of Truth：

```text
object_id
+
language
+
translated_slug
```

完整 Public Path：

```text
Derived Data / Rebuildable Index
```

## Why

避免：

```text
缓存层 URI 与 PHP 内部 URI 不一致
完整路径失效
父级变化导致大量脏 Route
```

## v0.1.0

仅验证：

```text
Page / Post Object Slug
```

不做：

```text
CPT Base Translation
Taxonomy Route
```
