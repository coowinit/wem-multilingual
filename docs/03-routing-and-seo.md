# 03. URL、Routing 与 SEO

## 1. URL 目标

v0.1.0：

```text
English
/about-us/

Spanish
/es/sobre-nosotros/
```

后续 CPT：

```text
/products/composite-decking/

/es/productos/tarima-compuesta/
```

---

## 2. 不修改 `$_SERVER['REQUEST_URI']`

使用 WordPress 原生：

```text
add_rewrite_rule()
query_vars
parse_request / request
permalink filters
```

---

## 3. Route Source of Truth

业务真相：

```text
object_id
+
language
+
translated_slug
```

后续：

```text
post_type / taxonomy
+
language
+
translated_base
```

完整 public path：

```text
Derived Data
```

不是唯一业务真相。

---

## 4. Route Index

以后可以维护：

```text
/es/productos/tarima-compuesta/
→ product #125
```

作为：

```text
Derived Cache / Search Index
```

特点：

```text
可删除
可重建
不作为写入真相
```

---

## 5. Incoming

```text
/es/sobre-nosotros/
↓
language = es
slug = sobre-nosotros
↓
resolve object_id
↓
WP Query Vars
```

---

## 6. Outgoing

WordPress 生成：

```text
/about-us/
```

Spanish Context 下：

```text
/es/sobre-nosotros/
```

使用：

```text
page_link
post_link
post_type_link
term_link（后续）
```

等官方 Filter。

---

## 7. SEO

每个 Published 语言版本：

```text
self canonical
hreflang
x-default
html lang
```

Spanish：

```html
<link rel="canonical"
      href="https://example.com/es/sobre-nosotros/">
```

并输出：

```text
en
es
x-default
```

---

## 8. Object Language State

不能通过：

```text
有没有 Translation Row
```

判断页面是否发布。

需要独立：

```text
object_id
language
status
```

例如：

```text
125
es
draft

125
de
published
```

只有 Published 才：

```text
进入正式 Language Switcher
输出 hreflang
允许索引
```

---

## 9. v0.1.0 暂不做

```text
Translated CPT Base
Taxonomy URL
Sitemap Advanced
Language Domains
IP Redirect
```
