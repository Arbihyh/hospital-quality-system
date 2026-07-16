# 医院病案质量控制系统

医院病案管理与质量控制平台，采用前后端分离架构。后端基于 Laravel（PHP），前端基于 Vue.js，支持病案搜索、质控指标统计、首页质量管理等核心功能。

---

## 模块说明

### 后端服务

| 模块 | 目录 | 说明 |
|------|------|------|
| 病案搜索 | `GY_bass-master` | 提供聚合搜索、专业检索接口，前缀 `/bass` |
| 病案指标/质控 | `GY_bazb-master` | 病例质控、指标统计、超声质控、出院/入院记录分析，前缀 `/bazb` |
| 医学病历 | `GY_yxbl-master` | 医学病历管理相关接口 |
| 病案首页质控 | `gyhomequality-master` | 病案首页缺陷检测与质量报告，已完成 MySQL 性能优化（查询耗时降低 75-88%） |

所有后端均为 Laravel 框架，通过 Artisan 命令行执行定时质控任务。

### 前端应用

| 模块 | 目录 | 说明 |
|------|------|------|
| 医院端前端 | `GY_ceshi_web-master` | Vue.js，支持聚合搜索、聚合指标、医院评审、医生站等页面 |
| 管理后台 | `Y_ceshi_admin-master` | Vue.js 管理端，供运维/管理人员使用 |

### 补丁包

`patches/` 目录存放增量更新包，用于 MySQL 版本升级时的代码替换：

- `病案首页查询-MySQL增量包-20260716.zip` — 初始 MySQL 适配
- `病案首页查询-MySQL增量包-列表修复增量包-*.zip` — 列表查询修复
- `病案首页查询-MySQL第二个增量包-列表修复.zip` — 第二次列表修复

---

## 架构概览

```
前端 (Vue.js)
├── GY_ceshi_web   →  /api (基础) + /bazb (指标) + /bass (搜索)
└── Y_ceshi_admin  →  管理后台

后端 (Laravel)
├── GY_bass        →  病案搜索服务
├── GY_bazb        →  病案指标 & 质控服务
├── GY_yxbl        →  医学病历服务
└── gyhomequality  →  病案首页质控服务
```

---

## 快速开始

### 后端（以 gyhomequality 为例）

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve
```

### 前端（以 GY_ceshi_web 为例）

```bash
npm install
# 测试环境
npm run build:stage
# 生产环境
npm run build:prod
```

---

## 常用 Artisan 命令（GY_bazb）

```bash
# 格式化出院/入院记录
php artisan command:analysisCase > zb_log/analysisCase.log &

# 清洗病程记录（75%雷同校验）
php artisan command:bc > zb_log/bc.log &

# 超声质控
php artisan command:chaosheng > zb_log/chaosheng.log &

# 入院记录质控
php artisan command:quality-ry > zb_log/quality-ry.log &

# 病例质控
php artisan command:quality > zb_log/quality.log &
```

---

## 嵌入页面路由（GY_ceshi_web）

| 名称 | 路由 | 需要登录 |
|------|------|----------|
| 聚合搜索 | `hospital-search` | 是 |
| 聚合指标 | `embedIndex-home` | 是 |
| 聚合医院评审 | `reviewIndex-home` | 是 |
| 医生站聚合搜索 | `whitelist-search` | 否 |
| 住院病历专业检索 | `whitelist-search-specialty` | 是 |

---

## 数据库说明

- 后端主要使用 MySQL（已针对大数据量做索引优化）
- 关键优化索引：`indicator.AAC01`、`patient_info.AAC01`、`case_quality.JZHM`、`home_quality.ZYH`
- 执行迁移：`php artisan migrate`

---

## 技术栈

- **后端**：PHP 7.x / Laravel，RESTful API
- **前端**：Vue.js 2.x，Webpack，Element UI
- **数据库**：MySQL
- **部署**：Nginx 反向代理，多服务分端口部署
