# WEM Multilingual

> 🌍 面向 WordPress B2B 企业官网的 SEO 优先多语言基础插件  
> **当前阶段：Architecture Baseline → v0.1.0 Experimental Core**  
> **当前状态：研究与架构已收敛，尚未进入生产可用阶段**

---

## 项目简介

**WEM Multilingual** 是一套面向 WordPress 企业官网的轻量多语言架构实验。

它不是为了重新实现一个功能庞大的 WPML，而是围绕我们真实长期维护的 B2B / 外贸企业站，优先解决几个最重要的问题：

```text
语言 URL 独立
SEO 结构正确
默认语言源数据保持干净
译文保存在本地数据库
Elementor 页面结构尽量只维护一份
Translation Provider 不参与访客实时请求
后续功能可以通过 Adapter 逐步扩展
```

当前项目仍处于 **Experimental Core** 阶段。

> **请不要将当前版本直接用于生产站。**

---

# 1. 为什么要做 WEM Multilingual

WordPress 多语言并不只是“把文字翻译一下”。

一个真正可长期维护的多语言系统至少涉及：

```text
语言上下文
URL / Routing
WordPress Object Model
Translation Storage
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

随后又进行了两轮独立架构审计，重点挑战：

```text
Single Source 是否合理？
Elementor 应该在哪里翻译？
Taxonomy 应该如何建模？
URL 是否应该改写 REQUEST_URI？
完整 Path 是否应该长期持久化？
HTML DOM Translation 是否值得成为 Core？
```

最终才形成当前的 Architecture Baseline。

---

# 2. 项目定位

主要适用场景：

- WordPress 企业官网
- B2B 外贸站
- Elementor
- Page / Post
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
             │
             ▼
      Structured Overlay
             │
       ┌─────┴────────────┐
       │                  │
       ▼                  ▼
 WordPress Native      Adapter Layer
     Fields                │
                           ├─ Elementor
                           ├─ Taxonomy
                           ├─ Menu
                           └─ Media
                            │
                            ▼
                         SEO Layer
                            │
                            ▼
                    Localized Response
```

---

# 5. 当前已经确定的核心原则

## 5.1 Source Data Must Stay Clean

默认语言 Source Data 不被多语言插件污染。

不把译文或语言标记直接写入：

```text
post_title
post_content
Elementor _elementor_data
```

停用插件后，默认语言站仍应保持正常。

---

## 5.2 Local Translation Repository

译文保存在 WordPress 本地数据库。

访客请求阶段：

```text
不调用 OpenAI
不调用 DeepL
不调用 Gemini
不调用 LibreTranslate
```

Translation Provider 只用于后台生产译文。

---

## 5.3 SEO First

语言 URL 是 Core，而不是附加功能。

例如：

```text
English
/about-us/

Spanish
/es/sobre-nosotros/
```

Published 语言版本需要正确管理：

```text
self canonical
hreflang
x-default
html lang
sitemap（后续）
```

---

## 5.4 Page / Post：Single Source + Structured Overlay

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

主要目的：

> 避免企业站中 Elementor 页面结构被复制成多份，并在长期修改中逐渐漂移。

---

## 5.5 Hybrid Object Model

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

当前方向：

```text
WordPress Native Rewrite
+
Query Vars
+
Object Slug Overlay
+
Permalink Filters
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
Page #125
language = es
slug = sobre-nosotros
```

完整 URL：

```text
/es/sobre-nosotros/
```

属于：

```text
Derived Data
```

后期可以建立可重建的 Route Index，但不把完整 Path 当作不可替代的数据真相。

详细见：

- [URL、Routing 与 SEO](docs/03-routing-and-seo.md)
- [ADR-002：路由与 Slug 模型](docs/adr/ADR-002-routing-model.md)

---

# 8. Translation Repository 的核心思想

当前模型：

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

最重要的一句话：

> **Context 定身份，Hash 定版本。**

例如：

```text
context_key
post:125:title
```

Source：

```text
Composite Decking
```

后来改成：

```text
Premium Composite Decking
```

Translation Unit 仍然是同一个。

变化的是：

```text
source_hash
```

如果：

```text
source_hash
!=
translated_from_hash
```

即可推导：

```text
Source Changed / Stale
```

详细见：

- [Translation Repository](docs/04-translation-repository.md)
- [ADR-003：Translation Repository](docs/adr/ADR-003-translation-repository.md)

---

# 9. Elementor 当前策略

Elementor **不进入 v0.1.0 Core**。

计划在：

```text
v0.1.1
Elementor Structured Translation Lab
```

单独验证。

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

具体 Runtime Hook、Widget Identity、迁移策略仍需要通过实验确定。

详细见：

- [Elementor Structured Translation 策略](docs/05-elementor-strategy.md)
- [ADR-004：Elementor 策略](docs/adr/ADR-004-elementor-strategy.md)

---

# 10. Taxonomy 为什么暂缓

第一轮和第二轮审计都证明：

```text
Single-Source Post
+
Multi-Term Taxonomy
```

并不是一句：

> “用 Multi-Term 就解决了。”

这么简单。

仍然需要真正验证：

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

# 11. v0.1.0 Experimental Core

v0.1.0 不是产品版。

它只回答：

> **Single Source Multilingual Core 能不能成立？**

## IN SCOPE

```text
English + Spanish

Language Context
/es/ Prefix

Native Rewrite Rule
Custom Query Vars

Object Slug Overlay
Incoming Object Resolution
Outgoing Permalink Filter

Translation Repository
Source Hash
translated_from_hash
Stale Detection

Object Language State

post_title Structured Translation

self canonical
hreflang
x-default
html lang

Disable Safety
Basic Cache Isolation
```

## OUT OF SCOPE

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

详细见：

- [v0.1.0 Experimental Core 开发规划](docs/07-v0.1.0-plan.md)

---

# 12. 版本路线

```text
Architecture Baseline
        │
        ▼
v0.1.0
Experimental Core
│
├── Language Context
├── Native Rewrite
├── Object Slug Overlay
├── Translation Repository
├── Object Language State
├── post_title Overlay
├── SEO Core
└── Disable Safety
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

# 13. 推荐阅读顺序

如果以后重新回顾这个项目，建议不要直接从代码开始看。

按照下面顺序阅读：

### 第一步：先理解为什么这样设计

1. [架构总览](docs/01-architecture-overview.md)
2. [核心设计原则](docs/02-core-principles.md)

### 第二步：理解 Core

3. [URL、Routing 与 SEO](docs/03-routing-and-seo.md)
4. [Translation Repository](docs/04-translation-repository.md)

### 第三步：理解为什么有些功能没有进入 Core

5. [Elementor 策略](docs/05-elementor-strategy.md)
6. [Taxonomy：暂缓与风险](docs/06-taxonomy-deferred.md)

### 第四步：准备开发

7. [v0.1.0 Experimental Core 开发规划](docs/07-v0.1.0-plan.md)

### 第五步：遇到架构疑问时查 ADR

- [ADR-001：核心对象模型](docs/adr/ADR-001-core-object-model.md)
- [ADR-002：路由与 Slug 模型](docs/adr/ADR-002-routing-model.md)
- [ADR-003：Translation Repository](docs/adr/ADR-003-translation-repository.md)
- [ADR-004：Elementor 策略](docs/adr/ADR-004-elementor-strategy.md)
- [ADR-005：Taxonomy 延后决策](docs/adr/ADR-005-taxonomy-deferred.md)

---

# 14. ADR 的使用规则

后续如果出现这种问题：

```text
为什么不改 REQUEST_URI？

为什么 Elementor 不直接保存目标语言 JSON？

为什么 Taxonomy 不进 v0.1.0？

为什么 Route 不保存完整 Path？

为什么 AI Provider 不参与前台请求？
```

优先：

```text
查看现有 ADR
```

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

# 15. 后续代码也必须遵守文档边界

未来新增源码后，任何功能先判断：

```text
它属于 Core？
还是 Adapter？
还是 Lab？
```

### Core

只有：

```text
Language
Routing
Repository
Object State
SEO
```

这一类所有站点都需要的底层能力。

### Adapter

例如：

```text
Elementor
WEM Content Fields
WEM SEO
Theme
Media
```

### Lab

尚未证明的复杂能力：

```text
Taxonomy
Complex Elementor Identity
HTML Fallback
Search
```

原则：

> **没有通过实验验证的复杂能力，不直接污染 Core。**

---

# 16. 仓库结构

```text
wem-multilingual/
├── README.md
├── STRUCTURE.md
└── docs/
    ├── 01-architecture-overview.md
    ├── 02-core-principles.md
    ├── 03-routing-and-seo.md
    ├── 04-translation-repository.md
    ├── 05-elementor-strategy.md
    ├── 06-taxonomy-deferred.md
    ├── 07-v0.1.0-plan.md
    └── adr/
        ├── ADR-001-core-object-model.md
        ├── ADR-002-routing-model.md
        ├── ADR-003-translation-repository.md
        ├── ADR-004-elementor-strategy.md
        └── ADR-005-taxonomy-deferred.md
```

后续进入代码阶段后，再逐步增加：

```text
wem-multilingual.php
includes/
admin/
tests/
```

---

# 17. 当前项目状态

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
   ⏳

v0.1.0 Validation
   ⏳

v0.1.1 Elementor Lab
   ⏳
```

---

# 18. 当前仓库的真正价值

这个仓库不仅是未来插件的 README。

更重要的是保存：

> **我们为什么这样设计。**

半年以后回来看代码，真正有价值的不只是：

```text
这个函数怎么写
```

而是：

```text
为什么这里选择 Single Source？
为什么 URL 使用 Slug Overlay？
为什么不修改 REQUEST_URI？
为什么 Elementor 被放到 Adapter？
为什么 Taxonomy 被延后？
```

这套文档就是 WEM Multilingual 后续所有开发、重构和新增功能的架构参考基线。

---

## 当前结论

> **Architecture Core Ready — 可以进入 v0.1.0 Experimental Core 实现阶段。**

但所有新增功能仍应坚持：

> **先验证地基，再扩展能力；先记录决策，再修改架构。**
