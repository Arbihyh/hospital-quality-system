# 病例查询 MySQL 增量包

分支：`feat/search`

## 修改内容

- 新增 `gyhomequality-master/app/Services/MysqlCaseSearchService.php`
- 修改 `gyhomequality-master/app/Http/Controllers/Api/QualityController.php`
  - `normalSearch()` 切换到 MySQL 查询服务
  - `searchData()` 切换到 MySQL 查询服务
  - 原 ES 代码保留在 return 后，便于回滚参考

## 涉及接口

- `POST /api/normalSearch`
- `POST /api/search`

## 测试服务器验证

测试路径：`/data/quality/homeQuality`
备份目录：`/data/quality/backup_case_search_mysql_20260716183733`

已验证：

- `php -l` 两个 PHP 文件均通过
- 空条件 `normalSearch/search` 返回 200
- 医嘱关键词 `转科` 普通搜索和高级搜索均返回正确住院号 `123456`
- 病历内容 `张三`、其他诊断、主手术按原始 MySQL 数据反查，结果集合均能对应