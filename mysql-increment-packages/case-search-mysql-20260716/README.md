# 病例查询模块 MySQL 改造说明

## 一、这次改了什么

本次只改“全病例/病例查询”相关接口，前端调用方式和返回结构保持不变。

涉及接口：

- `POST /api/normalSearch`：全病例普通搜索
- `POST /api/search`：全病例高级搜索

涉及代码：

- `gyhomequality-master/app/Http/Controllers/Api/QualityController.php`
  - 引入 `MysqlCaseSearchService`
  - `normalSearch()` 开头切换到 MySQL 查询服务
  - `searchData()` 开头切换到 MySQL 查询服务
  - 原 ES 逻辑保留在 return 后面，方便对照和回滚

- `gyhomequality-master/app/Services/MysqlCaseSearchService.php`
  - 新增病例查询 MySQL 实现
  - 负责普通搜索、高级搜索、动态 field 条件、明细组装和返回结构兼容

增量包：

- `mysql-increment-packages/case-search-mysql-20260716/case-search-mysql-20260716.zip`

## 二、为什么要改

原病例查询模块依赖 Elasticsearch：

- 普通搜索先查 `other_detailed`，再按 `MED_REC_ID` 查 `quality`
- 高级搜索还依赖 ES nested 查询、高亮、动态 field 条件
- 一旦 ES 同步延迟、索引缺失、mapping 异常，页面就可能查不到或和 MySQL 原始数据不一致

Boss 当前要求“还是改 MySQL”，核心原因是：

1. MySQL 是业务原始数据源，结果更容易和数据库数据对齐。
2. 后续排查问题时，可以直接对照表数据，不需要再判断 ES 同步是否延迟。
3. 减少对 ES 索引和 mapping 的依赖，降低测试环境/生产环境维护成本。
4. 前端不变，后端把 ES 查询逻辑迁移到 MySQL，改动范围可控。

## 三、MySQL 查询覆盖范围

当前 MySQL 版已覆盖：

- 普通关键词搜索：
  - 病历内容：`EMR_BL_BL01 + EMR_BL_BLXG`
  - 医嘱名称：`yzb.YZMC`
  - 费用名称：`fee_detailed.FYMC`

- 高级搜索：
  - 医嘱 field：`key=49`
  - 病历类别 field：`BLLB`，例如 `292`
  - 全部病历类别：`key=全部`
  - 科室：`AAC11N`
  - 其他诊断名称/编码：`other_diagnosis`
  - 主手术/其他手术名称/编码：`main_operation`、`secondary_operation`
  - 入院时间、出院方式、年龄等基础范围条件

返回结构保持：

- `data.total`
- `data.total_page`
- `data.list`
- `data.detail.EMR_BL_BL01`
- `data.detail.FeeDetailed`
- `data.detail.YZB`

## 四、测试验证结果

测试服务器路径：

- `/data/quality/homeQuality`

备份目录：

- `/data/quality/backup_case_search_mysql_20260716183733`

已验证：

- `php -l /data/api/app/Services/MysqlCaseSearchService.php`：通过
- `php -l /data/api/app/Http/Controllers/Api/QualityController.php`：通过
- `POST /api/normalSearch` 空条件：返回 200
- `POST /api/search` 空条件：返回 200
- 普通搜索医嘱关键词 `转科`：返回住院号 `123456`，与 `yzb` 原表一致
- 高级搜索医嘱 field `key=49,value=转科`：返回住院号 `123456`，与 `yzb` 原表一致
- 普通搜索病历内容 `张三`：返回结果集合包含原始住院号，EMR 明细正常
- 高级搜索病历类别 `BLLB=292,value=张三`：返回结果集合包含原始住院号，EMR 明细正常
- 高级搜索其他诊断 `乳头多瘤空泡病毒感染`：返回住院号和病人信息与 MySQL 原表一致
- 高级搜索主手术 `白内障超声乳化联合人工晶体植入术`：返回住院号和病人信息与 MySQL 原表一致

## 五、回滚方式

如果测试或上线后发现异常，可以直接回滚这两个文件：

- `app/Http/Controllers/Api/QualityController.php`
- `app/Services/MysqlCaseSearchService.php`

测试服务器已有备份：

- `/data/quality/backup_case_search_mysql_20260716183733`

回滚思路：

1. 用备份里的 `QualityController.php` 覆盖当前文件。
2. 删除或保留不用的 `MysqlCaseSearchService.php` 均可；控制器不调用它就不会影响业务。
3. 执行 `php -l` 检查语法。
4. 再调用 `/api/normalSearch`、`/api/search` 做接口验证。

## 六、注意事项

- MySQL 查询和 ES 查询的排序机制不完全一样，关键词命中多条时，第一条可能和 ES 不完全一致；判断数据准确性应看结果集合是否包含目标病例，以及明细是否对应。
- ES 的高亮是搜索引擎能力，MySQL 版目前对返回的命中字段做了兼容高亮，满足前端展示结构。
- 后续如果要继续迁移其他 ES 模块，建议继续采用“新增 MySQL service + 控制器最小接入 + 保留旧 ES 代码”的方式，便于回滚和定位问题。