# ADR-003：Translation Repository

## Status

Accepted for v0.1.0

## Decision

采用：

```text
Source Units
+
Translations
```

核心关系：

```text
Context = Identity
Hash = Source Version
Translation = Target Value
Review = Trust
```

建议核心表：

```text
wp_wem_ml_strings
wp_wem_ml_translations
```

Object Language State 独立管理。

## Source Changed

通过：

```text
source_hash
!=
translated_from_hash
```

推导。

旧译文不自动删除。

## Provider

Provider 不属于 Runtime Core。

v0.1.0：

```text
Manual Translation Only
```
