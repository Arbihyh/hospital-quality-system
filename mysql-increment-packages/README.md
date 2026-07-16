# 病案首页查询 MySQL 增量包

本仓库用于存放病案首页查询模块从 Elasticsearch 查询迁移到 MySQL 查询过程中的增量包、修复说明和 MySQL 查询效率测试脚本。

## 增量包清单

| 文件 | 内容 | 影响范围 |
| --- | --- | --- |
| `packages/病案首页查询-MySQL增量包-20260716.zip` | 第一版 MySQL 查询增量包，将病案首页查询模块的数据查询从 ES 改为 MySQL。 | 后端服务层 |
| `packages/病案首页查询-MySQL第二个增量包-列表修复.zip` | 修复列表返回结构，补齐原 ES 文档中的嵌套关联数据，保证前端表格筛选和字段展示兼容。 | 后端服务层 |
| `packages/病案首页查询-MySQL第三个增量包-时间与第一其他诊断-20260716-155346.zip` | 修复入院时间显示为日期的问题，保留完整时分秒；修复“第一其他诊断名称/编码”搜索条件。 | 后端服务层 |
| `packages/病案首页查询-MySQL第四个增量包-住院次数修复-20260716.zip` | 修复住院次数列显示为 0 的问题，将 `patient_info.AAA29` 兼容同步到原 ES 嵌套结构 `patient_hospital_info[0].AAA29`。 | 后端服务层 |

## 优化内容说明

### 1. ES 查询迁移到 MySQL

原病案首页查询依赖 Elasticsearch 文档结构。迁移后通过 MySQL 查询 `patient_info` 主表，并按原前端使用习惯补齐关联表数据，包括：

- `patient_hospital_info`
- `patient_doctor_info`
- `main_diagnosis`
- `other_diagnosis`
- `main_operation`
- `secondary_operation`
- `patient_medical_info`
- `icu`
- `patient_add`

这样前端不用改动，仍然可以按原字段结构读取列表数据。

### 2. 列表字段兼容

MySQL 查询返回后，后端补齐了原 ES 列表常用字段，例如：

- 主要诊断名称、编码
- 主要手术名称、编码
- 出院科室
- 入院时间
- 住院次数
- 药品费用、材料费用
- 昏迷时间
- 手术判别

目标是减少前端改动，让 MySQL 返回结构尽量贴近原 ES 文档结构。

### 3. 入院时间修复

列表中 `入院时间` 原来被住院信息表里的日期字段覆盖，导致只显示 `YYYY-MM-DD`。修复后优先保留 `patient_info.AAB01` 的完整时间，例如：

```text
2026-05-25 05:24:15
```

### 4. 第一其他诊断搜索修复

前端搜索“第一其他诊断名称/编码”时，会传入 `DIA_ORDER = 1` 作为第一其他诊断的语义条件。但实际 MySQL 数据中第一其他诊断的 `DIA_ORDER` 可能不是固定 1，例如 2 或 5。

修复后后端按每个病案的最小其他诊断排序号识别“第一其他诊断”，支持：

- 第一其他诊断名称
- 第一其他诊断编码
- 精确查询
- 模糊查询

### 5. 住院次数修复

数据库字段定义：

```text
patient_info.AAA29 = 住院次数
```

测试库中真实数据存在，例如：

```text
1234567 AAA29=2
12345678 AAA29=1
123456 AAA29=1
```

页面显示为 0 的原因是前端可能读取原 ES 嵌套结构中的 `patient_hospital_info[0].AAA29`，而 MySQL 版最初只返回根字段 `AAA29`。

修复后同时返回：

```text
row.AAA29
row.patient_hospital_info[0].AAA29
```

## 查询效率测试脚本

脚本位置：

```text
scripts/mysql_query_benchmark.php
```

测试命令：

```bash
cd /data/api
php scripts/mysql_query_benchmark.php --iterations=100000 --warmup=1000 --mode=patient-by-id
php scripts/mysql_query_benchmark.php --iterations=100000 --warmup=1000 --mode=list-page --page-size=10
```

测试服务器结果：

| 模式 | 查询次数 | 总耗时 | QPS | 平均耗时 | P95 | P99 |
| --- | ---: | ---: | ---: | ---: | ---: | ---: |
| 单条主键查询 | 100000 | 32.264914 秒 | 3099.34 次/秒 | 0.3216 ms | 0.4016 ms | 0.4502 ms |
| 列表页查询，每次 10 条 | 100000 | 33.312497 秒 | 3001.88 次/秒 | 0.3321 ms | 0.4114 ms | 0.4684 ms |

说明：测试库当前样本数据量较小，以上结果主要反映 MySQL 简单查询和连接开销，不等同于生产环境大数据量、复杂条件、并发压力下的最终性能。

## 部署说明

增量包保持原项目路径结构。部署时只替换压缩包内包含的文件，不需要整包更新。

第四个增量包只包含：

```text
app/Services/MysqlQualitySearchService.php
```

第三个增量包包含：

```text
app/Services/MysqlQualitySearchService.php
app/Services/QualityService.php
```

## 测试服务器验证记录

已在测试服务器验证：

- MySQL 查询接口可返回列表数据
- 主要诊断、主要手术字段正常
- 入院时间包含时分秒
- 第一其他诊断名称/编码搜索正常
- 住院次数根字段和嵌套字段均返回正确值
- benchmark 脚本可执行
