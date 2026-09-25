# 06. Taxonomy：暂缓与风险

> 状态：**明确移出 v0.1.0；最终实现尚未确定**

## 1. 为什么暂缓

Page/Post 采用：

```text
Single Source
```

而 Taxonomy 如果采用：

```text
Multi-Term
```

会产生一个关键关系问题：

```text
Post #125
│
├─ EN Term #10
└─ ES Term #20
```

到底应该：

```text
关联 #10
关联 #10 + #20
还是查询时做映射？
```

目前没有一个方案在所有 WordPress 生态场景中都完美。

---

## 2. 候选方案

### A. Multi-Term Bridge

```text
Post 同时关联所有语言 Term
```

优点：

```text
tax_query 原生
```

风险：

```text
get_the_terms 返回多语言
第三方直接 SQL 看见全部关系
后台 Term 选择复杂
```

---

### B. Source Term + Query Mapping

```text
Post 只关联 Source Term
```

Target Term：

```text
→ 映射回 Source Term
→ 再查询 Post
```

优点：

```text
Post-Term 数据干净
```

风险：

```text
需要 Query Translation
第三方直接 SQL 绕过
```

---

### C. Shadow Taxonomy Query

在：

```text
WP_Query / parse_tax_query
```

阶段将目标语言 Term 映射为 Source Term。

仍存在：

```text
直接 SQL
Term Count
REST
SEO
第三方 Filter Plugin
```

等兼容问题。

---

## 3. 当前决定

不是：

```text
已经选择 A / B / C
```

而是：

> **Taxonomy 必须建立独立 Architecture Lab。**

在：

```text
v0.1.0 Core
+
v0.1.1 Elementor Lab
```

稳定后再进入。

---

## 4. Taxonomy Lab 必须测试

```text
wp_term_relationships
WP_Query tax_query
get_the_terms()
get_term()
get_term_link()
Archive
Breadcrumb
REST
SEO
Elementor Widget
Filter Plugin
Direct SQL Compatibility
```

在真实测试完成前，不写“最终 Taxonomy 架构”。
