# WEM Multilingual

> 🌍 面向 WordPress B2B 企业官网的 SEO 优先多语言基础插件  
> **当前阶段：v0.1.0 Experimental Core 已完成验证**  
> **当前状态：核心架构实验通过，尚未进入生产可用阶段**

---

## 项目简介

**WEM Multilingual** 是一套面向 WordPress 企业官网的轻量多语言架构实验。

它不是为了重新实现一个功能庞大的 WPML，而是围绕真实长期维护的 B2B / 外贸企业站，优先验证几个最重要的问题：

```text
语言 URL 独立
SEO 结构正确
默认语言源数据保持干净
译文保存在本地数据库
语言版本发布状态独立控制
运行时 Overlay 不污染 Source Object
后续复杂能力通过 Adapter / Lab 逐步扩展
```

`v0.1.0 Experimental Core` 已完成真实 WordPress 环境中的 T01–T10 验收。

> **当前版本仍是实验核心，不建议直接用于生产站。**

---

# 1. 为什么要做 WEM Multilingual

WordPress 多语言并不只是“把文字翻译一下”。

一个真正可长期维护的多语言系统至少涉及：

```text
Language Context
URL / Routing
WordPress Object Model
Translation Storage
Object Language State
Structured Overlay
SEO
Elementor
Taxonomy
Menu
Cache
Translation Provider
```

本项目在正式编码前，先研究并拆解了多种成熟方案，包括：

- WPML
- Polylang
- TranslatePress
- wpLingua
- MultilingualPress
- Falang
- WPGlobus
- Weglot
- GTranslate
- Loco Translate / LocoAI
- LibreTranslate
- translate.js

随后进行了两轮独立架构审计，重点挑战：

```text
Single Source 是否合理？
Elementor 应该在哪里翻译？
Taxonomy 应该如何建模？
URL 是否应该改写 REQUEST_URI？
完整 Path 是否应该长期持久化？
HTML DOM Translation 是否值得成为 Core？
```

最终形成 Architecture Baseline，并在 `v0.1.0 Experimental Core` 中完成实际验证。

---

# 2. 项目定位

主要适用场景：

- WordPress 企业官网
- B2B 外贸站
- Page / Post
- Elementor（后续 Adapter）
- 后续扩展 CPT / Taxonomy
- 自定义结构化字段
- SEO Title / Meta Description / Schema / Sitemap
- Cloudflare / SiteGround / 页面缓存
- 少量主要语言，例如 English / Spanish / German / French
- 多站长期维护

本项目的目标不是：

```text
第一版支持所有 WordPress 插件
第一版兼容 WooCommerce
第一版实现完整翻译团队工作流
第一版实现自动 AI 翻译所有内容
第一版支持几十种语言
```

而是：

> **先把多语言网站最底层的对象、路由、译文、状态和 SEO 逻辑做正确。**

---

# 3. 一句话理解当前架构

可以把 WEM Multilingual 当前思想记成：

> **源数据只有一份，译文独立存在；知道数据身份时结构化处理，不知道时不要猜。**

以及：

> **Context 定身份，Hash 定版本，State 定发布边界。**

再加一条：

> **URL 不走机器翻译，SEO 不做事后补丁；Provider 负责生成译文，Core 负责管理多语言。**

---

# 4. 当前核心架构

```text
                 Public Request
                        │
                        ▼
                Language Context
                        │
                        ▼
                  Route Layer
                        │
              ┌─────────┴─────────┐
              │                   │
              ▼                   ▼
          Incoming              Outgoing
          Rewrite              Permalink
              │                   │
              └─────────┬─────────┘
                        ▼
                 Object Resolver
                        │
             ┌──────────┴──────────┐
             │                     │
             ▼                     ▼
     Translation Repository   Object State
             │                     │
             └──────────┬──────────┘
                        ▼
                 Runtime Overlay
                        │
                        ▼
                     SEO Core
                        │
                        ▼
                Localized Response
```

---

# 5. 当前已经验证的核心原则

## 5.1 Source Data Must Stay Clean

默认语言 Source Data 不被多语言插件污染。

不把译文直接写入：

```text
post_title
post_content
Elementor _elementor_data
```

在 v0.1.0 中已实际验证：

```text
Spanish 标题通过 Runtime Overlay 显示
wp_posts.post_title 仍保持 English
停用插件后 English Source 页面继续正常
```

---

## 5.2 Local Translation Repository

译文保存在 WordPress 本地数据库。

当前核心表：

```text
wp_wem_ml_strings
wp_wem_ml_translations
wp_wem_ml_object_state
wp_wem_ml_object_slugs
```

访客请求阶段不调用 Translation Provider。

```text
OpenAI
DeepL
Gemini
LibreTranslate
```

等 Provider 以后只负责后台生产译文，不参与前台实时请求。

---

## 5.3 Context = Identity / Hash = Version

Source Unit 使用稳定 Context 表示“是谁”：

```text
post:44:title
```

Source 内容变化后，不创建新的 Translation Unit，只更新：

```text
source_text
source_hash
normalized_hash
```

当：

```text
source_hash
!=
translated_from_hash
```

即可判断：

```text
Stale
```

v0.1.0 已验证：

```text
Current
→ Source 修改
→ source-drift / Stale
→ 更新译文
→ Current
```

---

## 5.4 State = Publication Boundary

“有译文”不等于“已公开”。

当前对象语言状态：

```text
draft
published
```

验证结果：

```text
draft
→ Spanish URL = 404
→ 不进入 hreflang
→ 不输出 Spanish canonical
→ 不应用 Title Overlay

published
→ Spanish URL = 200
→ Route resolved
→ Title Overlay applied
→ SEO Signals 正常输出
```

---

## 5.5 SEO First

语言 URL 是 Core，而不是附加功能。

例如：

```text
English
/about-evodek/

Spanish
/es/acerca-de-evodek/
```

Published 语言版本已经验证：

```text
self canonical
hreflang=en
hreflang=es
hreflang=x-default
html lang
```

Draft 语言版本则不会作为正式 SEO 页面暴露。

---

## 5.6 Page / Post：Single Source + Structured Overlay

当前核心模型：

```text
一个 WordPress Page / Post
+
多个语言 Translation Overlay
```

而不是：

```text
EN Post
ES Post
DE Post
```

目的：

> 避免企业站中页面结构被复制成多份，并在长期修改中逐渐漂移。

---

## 5.7 Hybrid Object Model

**Single Source 不是适用于所有 WordPress Object 的统一规则。**

当前方向：

```text
Page / Post
→ Single Source + Overlay

Elementor
→ Adapter-based Structured Translation
   v0.1.1 单独实验

Taxonomy
→ 暂缓
   独立 Architecture Lab

Menu
→ 后续 Per-language Menu

Media
→ Shared Attachment + Localized Metadata / Asset
```

---

# 6. 当前明确不采用的方案

除非未来新的 ADR 明确推翻，否则不要重新引入：

```text
修改 $_SERVER['REQUEST_URI']

把完整 Public Path
作为长期唯一 Route Source of Truth

前台访客实时调用 Translation Provider

把目标语言译文写回 Elementor JSON

把全局 DOM Parser
作为多语言 Core

v0.1.0 引入 HTML Fallback

所有 WordPress Object
强制使用同一 Single Source 模型
```

这些不是“暂时没写”，而是当前架构阶段已经主动排除的方向。

---

# 7. Routing 的核心思想

不通过修改：

```php
$_SERVER['REQUEST_URI']
```

实现多语言路由。

当前实现：

```text
WordPress Native Rewrite
+
Custom Query Vars
+
Object Slug Overlay
+
Incoming Object Resolution
+
Outgoing Permalink Filters
+
Object Language State Gate
```

业务真相：

```text
object_id
+
language
+
translated_slug
```

例如：

```text
Page #44
language = es
slug = acerca-de-evodek
```

完整 Public URL：

```text
/es/acerca-de-evodek/
```

属于 Derived Data，而不是长期唯一数据真相。

详细见：

- [URL、Routing 与 SEO](docs/03-routing-and-seo.md)
- [ADR-002：路由与 Slug 模型](docs/adr/ADR-002-routing-model.md)

---

# 8. Translation Repository

核心关系：

```text
Source Unit
    │
    │ 1:N
    ▼
Translation
    ├─ es
    ├─ de
    └─ fr
```

v0.1.0 当前只验证：

```text
post_title
English → Spanish
```

并已跑通：

```text
同步 Source Unit
保存 Spanish Translation
Hash 比较
Stale Detection
重新翻译
恢复 Current
```

详细见：

- [Translation Repository](docs/04-translation-repository.md)
- [ADR-003：Translation Repository](docs/adr/ADR-003-translation-repository.md)

---

# 9. Diagnostics / Validation Lab

v0.1.0 开发过程中保留了后台 Diagnostics，用作长期教学、诊断和回归测试工具。

当前可以自动验证：

```text
Routing
Object State
Translated Slug
Localized Permalink
Title Overlay
Source / Translation Hash
HTTP 200 / 404
Debug Headers
Canonical
hreflang
html lang
Draft SEO Safety
Published SEO Signals
```

Diagnostics 会根据当前 State 自动切换验收模式：

```text
DRAFT
PUBLISHED
```

并输出：

```text
PASS
WARN
FAIL
```

这套 Lab 后续版本继续保留，不作为临时代码删除。

---

# 10. v0.1.0 Final Validation

`v0.1.0 Experimental Core` 已完成 T01–T10 验收：

| Test | 内容 | 结果 |
|---|---|---|
| T01 | Default Language | ✅ PASS |
| T02 | Spanish / Native Routing | ✅ PASS |
| T03 | Translated Slug + Title Overlay | ✅ PASS |
| T04 | Source Clean | ✅ PASS |
| T05 | Stale Detection | ✅ PASS |
| T06 | SEO Signals | ✅ PASS |
| T07 | Draft Language Safety | ✅ PASS |
| T08 | Published Language | ✅ PASS |
| T09 | Disable Safety | ✅ PASS |
| T10 | Basic Cache Isolation | ✅ PASS |

详细测试证据与开发过程中发现的问题见：

- [v0.1.0 Experimental Core 开发规划](docs/07-v0.1.0-plan.md)
- [v0.1.0 Final Validation](docs/08-v0.1.0-validation.md)

---

# 11. 本轮验证中解决过的重要问题

v0.1.0 的价值不只是“最终 PASS”，还包括开发过程中暴露并修正的问题：

```text
WordPress canonical redirect 抢占 Spanish URL
Draft Route Gate 只标记状态但没有真正 404
后台 Diagnostics 注册时机过早导致 Fatal Error
Source Unit 与真实 Source Title 漂移
SiteGround / Cloudflare 回环请求缓存干扰
插件停用后 Rewrite Rule 残留
静态 <html lang="en"> 无法响应语言上下文
```

这些问题都已经进入实际测试闭环，而不是停留在理论设计。

---

# 12. 当前已知边界

虽然 v0.1.0 核心实验通过，但仍然明确不包含：

```text
post_content
Elementor
Taxonomy
Menu
Media
AI Provider
Translation Memory
Glossary
HTML Parser
DOM Parser
the_content Translation
CPT Base Translation
Occurrence
Search
REST Multilingual
AJAX
WooCommerce
Multisite
Different Domains
```

另外，Basic Cache Isolation 当前验证的是：

```text
English / Spanish 不串语言
SiteGround / Cloudflare 正常请求链稳定
```

测试环境中的：

```text
CF-Cache-Status = DYNAMIC
```

因此尚未证明 Cloudflare HTML `HIT` 场景下的边缘缓存行为。

---

# 13. Elementor 当前策略

Elementor **不进入 v0.1.0 Core**。

下一阶段：

```text
v0.1.1
Elementor Structured Translation Lab
```

当前原则：

```text
Adapter Registry
+
Read-only Source Discovery
+
Widget-scoped Runtime Overlay
```

禁止：

```text
修改 _elementor_data
全局 DOM Translator
把 the_content 字符串替换作为 Core
```

第一批实验建议：

```text
Heading
Button
```

再考虑：

```text
Text Editor
Image
Icon List
```

详细见：

- [Elementor Structured Translation 策略](docs/05-elementor-strategy.md)
- [ADR-004：Elementor 策略](docs/adr/ADR-004-elementor-strategy.md)

---

# 14. Taxonomy 为什么仍然暂缓

第一轮和第二轮审计都证明：

```text
Single-Source Post
+
Multi-Term Taxonomy
```

不是一句“用 Multi-Term 就解决了”这么简单。

仍需要真正验证：

```text
wp_term_relationships
WP_Query tax_query
get_the_terms()
Term Archive
REST
Breadcrumb
SEO
Elementor Taxonomy Widget
第三方 Filter Plugin
直接 SQL
```

因此：

> **Taxonomy 不进入 v0.1.0，也不在当前阶段假装已经有最终答案。**

详细见：

- [Taxonomy：暂缓与风险](docs/06-taxonomy-deferred.md)
- [ADR-005：Taxonomy 延后决策](docs/adr/ADR-005-taxonomy-deferred.md)

---

# 15. 版本路线

```text
Architecture Baseline
        │
        ▼
v0.1.0 Experimental Core
        │
        ├─ Language Context             ✅
        ├─ Native Rewrite               ✅
        ├─ Object Slug Overlay          ✅
        ├─ Translation Repository       ✅
        ├─ Object Language State        ✅
        ├─ post_title Overlay           ✅
        ├─ SEO Core                     ✅
        ├─ Disable Safety               ✅
        └─ Basic Cache Isolation        ✅
        │
        ▼
v0.1.1
Elementor Structured Translation Lab
        │
        ▼
Later
Taxonomy Architecture Lab
        │
        ▼
Menu / Media / Provider / TM / Search
```

---

# 16. 推荐阅读顺序

如果以后重新回顾这个项目，建议不要直接从代码开始看。

### 第一步：理解为什么这样设计

1. [架构总览](docs/01-architecture-overview.md)
2. [核心设计原则](docs/02-core-principles.md)

### 第二步：理解 Core

3. [URL、Routing 与 SEO](docs/03-routing-and-seo.md)
4. [Translation Repository](docs/04-translation-repository.md)

### 第三步：理解为什么有些功能没有进入 Core

5. [Elementor 策略](docs/05-elementor-strategy.md)
6. [Taxonomy：暂缓与风险](docs/06-taxonomy-deferred.md)

### 第四步：看 v0.1.0 实际怎么落地

7. [v0.1.0 Experimental Core 开发规划](docs/07-v0.1.0-plan.md)
8. [v0.1.0 Final Validation](docs/08-v0.1.0-validation.md)

### 第五步：遇到架构疑问时查 ADR

- [ADR-001：核心对象模型](docs/adr/ADR-001-core-object-model.md)
- [ADR-002：路由与 Slug 模型](docs/adr/ADR-002-routing-model.md)
- [ADR-003：Translation Repository](docs/adr/ADR-003-translation-repository.md)
- [ADR-004：Elementor 策略](docs/adr/ADR-004-elementor-strategy.md)
- [ADR-005：Taxonomy 延后决策](docs/adr/ADR-005-taxonomy-deferred.md)

---

# 17. ADR 的使用规则

后续如果出现这种问题：

```text
为什么不改 REQUEST_URI？
为什么 Elementor 不直接保存目标语言 JSON？
为什么 Taxonomy 不进 v0.1.0？
为什么 Route 不保存完整 Path？
为什么 AI Provider 不参与前台请求？
```

优先查看现有 ADR。

如果新的实验结果证明旧决策不再成立：

```text
不要直接删除旧结论
```

而应：

```text
新增 ADR
↓
说明新证据
↓
标记旧 ADR 为 Superseded
```

这样仓库可以保留真正的架构演进历史。

---

# 18. 后续代码必须继续遵守边界

任何新增能力先判断：

```text
它属于 Core？
还是 Adapter？
还是 Lab？
```

### Core

```text
Language
Routing
Repository
Object State
SEO
```

### Adapter

```text
Elementor
WEM Content Fields
WEM SEO
Theme
Media
```

### Lab

```text
Taxonomy
Complex Elementor Identity
HTML Fallback
Search
```

原则：

> **没有通过实验验证的复杂能力，不直接污染 Core。**

---

# 19. 当前仓库结构

```text
wem-multilingual/
├── wem-multilingual.php
├── README.md
├── STRUCTURE.md
├── includes/
├── admin/
└── docs/
    ├── 01-architecture-overview.md
    ├── 02-core-principles.md
    ├── 03-routing-and-seo.md
    ├── 04-translation-repository.md
    ├── 05-elementor-strategy.md
    ├── 06-taxonomy-deferred.md
    ├── 07-v0.1.0-plan.md
    ├── 08-v0.1.0-validation.md
    └── adr/
        ├── ADR-001-core-object-model.md
        ├── ADR-002-routing-model.md
        ├── ADR-003-translation-repository.md
        ├── ADR-004-elementor-strategy.md
        └── ADR-005-taxonomy-deferred.md
```

---

# 20. 当前项目状态

```text
Research
   ✅

First Architecture Audit
   ✅

Second Architecture Audit
   ✅

Architecture Baseline
   ✅

v0.1.0 Development Plan
   ✅

v0.1.0 Implementation
   ✅

v0.1.0 Validation
   ✅

v0.1.1 Elementor Lab
   ⏳
```

---

# 21. 当前仓库的真正价值

这个仓库不仅保存插件源码。

更重要的是保存：

> **我们为什么这样设计，以及这些判断是否真的被实验验证过。**

半年以后回来看代码，真正有价值的不只是：

```text
这个函数怎么写
```

而是：

```text
为什么这里选择 Single Source？
为什么 URL 使用 Slug Overlay？
为什么不修改 REQUEST_URI？
为什么 Translation 和 Publication State 要分开？
为什么 Elementor 被放到 Adapter？
为什么 Taxonomy 被延后？
哪些结论已经通过真实网站验证？
```

---

## 当前结论

> **v0.1.0 Experimental Core Validation Complete。**

在当前实验边界内，已经证明：

```text
Single Source
+
Local Translation Repository
+
Native Routing
+
Translated Slug
+
Object Language State
+
Runtime Overlay
+
SEO Core
```

这条核心链路可以成立。

但它仍然只是下一阶段开发的架构基线，不等于生产版完成。

后续继续坚持：

> **先验证地基，再扩展能力；先记录决策，再修改架构。**
