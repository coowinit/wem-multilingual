# Repository Structure

当前目录结构对应：

```text
v0.1.0 Experimental Core
+
v0.1.1 Elementor Structured Translation Lab
```

---

## 1. 当前结构

```text
wem-multilingual/
├── wem-multilingual.php
├── README.md
├── STRUCTURE.md
│
├── includes/
│   ├── class-wem-ml-schema.php
│   ├── class-language-context.php
│   ├── class-slug-repository.php
│   ├── class-translation-repository.php
│   ├── class-source-unit-repository.php
│   ├── class-object-state.php
│   ├── class-router.php
│   ├── class-title-overlay.php
│   ├── class-seo.php
│   ├── class-cache-invalidator.php
│   │
│   └── elementor/
│       ├── class-elementor-adapter-registry.php
│       ├── class-elementor-source-discovery.php
│       └── class-elementor-runtime-overlay.php
│
├── admin/
│   ├── class-admin.php
│   ├── class-diagnostics.php
│   ├── class-elementor-lab.php
│   ├── class-elementor-source-sync-lab.php
│   ├── class-elementor-translation-lab.php
│   ├── class-elementor-runtime-validation.php
│   └── class-cache-environment-probe.php
│
└── docs/
    ├── 01-architecture-overview.md
    ├── 02-core-principles.md
    ├── 03-routing-and-seo.md
    ├── 04-translation-repository.md
    ├── 05-elementor-strategy.md
    ├── 06-taxonomy-deferred.md
    ├── 07-v0.1.0-plan.md
    ├── 08-v0.1.0-validation.md
    ├── 09-v0.1.1-elementor-validation.md
    │
    └── adr/
        ├── ADR-001-core-object-model.md
        ├── ADR-002-routing-model.md
        ├── ADR-003-translation-repository.md
        ├── ADR-004-elementor-strategy.md
        └── ADR-005-taxonomy-deferred.md
```

---

## 2. Core 层

### `wem-multilingual.php`

插件入口。

负责：

```text
版本常量
文件加载
Activation / Deactivation
Core / Adapter / Admin 初始化
```

### `includes/class-language-context.php`

负责当前请求语言上下文。

### `includes/class-router.php`

负责 Native Routing、translated slug 解析与 Page / Post 请求链。

### `includes/class-slug-repository.php`

保存：

```text
object
+
language
+
translated_slug
```

### `includes/class-translation-repository.php`

负责 Translation Repository 的基础读写、Hash、Stale 判断。

### `includes/class-source-unit-repository.php`

负责通用 Source Unit 持久化。

当前 Elementor 结构化字段也复用这一层。

### `includes/class-object-state.php`

负责：

```text
draft
published
```

语言版本发布边界。

### `includes/class-title-overlay.php`

v0.1.0 的 Page / Post `post_title` Runtime Overlay。

### `includes/class-seo.php`

负责：

```text
canonical
hreflang
html lang
SEO safety
```

### `includes/class-cache-invalidator.php`

统一对象级缓存失效服务。

当前已验证：

```text
WP Rocket targeted URL purge
SiteGround URL purge fallback
EN / ES object URLs
Old + New translated slug URLs
```

不会主动执行 full-domain purge。

---

## 3. Elementor Adapter 层

### `class-elementor-adapter-registry.php`

声明允许翻译的 Widget / Field。

当前：

```text
heading.title
button.text
```

### `class-elementor-source-discovery.php`

只读扫描：

```text
_elementor_data
```

并输出 Adapter 已批准的 Structured Source Field。

不写 Elementor JSON。

### `class-elementor-runtime-overlay.php`

负责 Elementor Structured Runtime Overlay。

当前已验证：

```text
heading.title
→ .elementor-heading-title

button.text
→ .elementor-button-text
```

使用 locator-scoped 结构化替换，不是按 Source Text 做全局替换。

---

## 4. Admin / Lab

### `class-admin.php`

v0.1.0 Core 实验管理页。

包含：

```text
Slug Repository
post_title Translation
Object Language State
```

并接入 State / Slug 的 targeted cache invalidation。

### `class-diagnostics.php`

v0.1.0 Routing / SEO / State 回归测试工具。

### `class-elementor-lab.php`

Elementor 原始只读 Source Discovery / 结构观察工具。

### `class-elementor-source-sync-lab.php`

把 Adapter-approved Source Field 显式同步到 Source Unit Repository。

Source 真正变化时自动 targeted purge。

### `class-elementor-translation-lab.php`

保存 Elementor Spanish Translation。

Translation 真正变化时自动 targeted purge。

### `class-elementor-runtime-validation.php`

Structured Runtime Validation。

当前验证：

```text
Heading.title
Button.text
EN
Normal ES
Cache-bypass ES
Runtime Trace
Hash / State
Diagnosis
```

### `class-cache-environment-probe.php`

检测：

```text
WP Rocket
SiteGround
Target URLs
```

并保留 Manual Targeted Purge 作为长期诊断工具。

---

## 5. Documentation

推荐阅读顺序：

```text
01 Architecture Overview
↓
02 Core Principles
↓
03 Routing and SEO
↓
04 Translation Repository
↓
05 Elementor Strategy
↓
07 v0.1.0 Plan
↓
08 v0.1.0 Validation
↓
09 v0.1.1 Elementor Validation
↓
ADR
```

---

## 6. 当前架构分层

```text
Core
├─ Language
├─ Routing
├─ Repository
├─ Object State
├─ SEO
└─ Cache Invalidation

Adapter
└─ Elementor

Lab / Diagnostics
├─ Core Diagnostics
├─ Elementor Discovery
├─ Source Sync
├─ Translation
├─ Runtime Validation
└─ Cache Diagnostics
```

原则：

> **实验页面长期保留用于教学、诊断和回归测试；未经验证的复杂能力不直接进入 Core。**
