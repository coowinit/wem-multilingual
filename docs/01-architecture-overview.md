# 01. 架构总览

## 1. 目标

WEM Multilingual 的核心目标是建立一个：

```text
SEO-first
Local-first
Single-source
Adapter-friendly
```

的 WordPress 多语言基础设施。

---

## 2. 多语言系统与翻译引擎分离

### Multilingual Core

负责：

```text
当前语言
语言 URL
对象状态
翻译存储
URL 解析
SEO
Runtime Overlay
```

### Translation Provider

只负责：

```text
Source
→
Target
```

可替换：

```text
Manual
OpenAI
DeepL
Gemini
LibreTranslate
```

Provider 不参与访客实时请求。

---

## 3. 对象模型

当前不是 Universal Single Source，而是 Hybrid Object Model。

```text
Page / Post
→ Single Source + Overlay

Elementor
→ Structured Adapter

Taxonomy
→ Deferred Architecture Lab

Menu
→ Per-language（后续）

Media
→ Shared + Localized Override
```

---

## 4. 请求链

```text
GET /es/sobre-nosotros/
        │
        ▼
Language Context = es
        │
        ▼
Native Rewrite / Query Vars
        │
        ▼
Object Resolver
        │
        ▼
Source Page Object
        │
        ▼
Translation Repository
        │
        ▼
Structured Runtime Overlay
        │
        ▼
SEO URL / canonical / hreflang
        │
        ▼
Spanish Response
```

---

## 5. 后台链

```text
Source Object
    ↓
Source Scan
    ↓
Translation Unit
    ↓
Manual / Provider
    ↓
Needs Review
    ↓
Reviewed
    ↓
Language Published
```

---

## 6. 为什么 v0.1.0 不做全部功能

因为必须把风险隔离。

如果第一版同时加入：

```text
Elementor
Taxonomy
Menu
AI
HTML Parser
```

一旦失败，很难判断真正错误来自：

```text
Routing
Repository
Object Model
Elementor
Cache
SEO
```

所以先证明核心骨架，再增加适配层。
