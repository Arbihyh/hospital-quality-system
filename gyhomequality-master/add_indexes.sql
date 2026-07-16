-- ============================================
-- 质控报告性能优化 - 数据库索引添加脚本
-- 执行时间：2025-12-22
-- ============================================

-- 1. indicator 表索引（优先使用年份和月份字段）
-- 1.1 年份字段索引
ALTER TABLE indicator ADD INDEX idx_indicator_aac01_year (AAC01_YEAR);

-- 1.2 月份字段索引
ALTER TABLE indicator ADD INDEX idx_indicator_aac01_month (AAC01_MONTH);

-- 1.3 年份+月份联合索引（最重要，用于单个月份和跨月份查询）
ALTER TABLE indicator ADD INDEX idx_indicator_aac01_year_month (AAC01_YEAR, AAC01_MONTH);

-- 1.4 日期时间字段索引（用于跨年查询的后备方案）
ALTER TABLE indicator ADD INDEX idx_indicator_aac01 (AAC01);


-- 2. patient_info 表索引
-- 2.1 出院时间字段索引（用于患者时间范围查询）
ALTER TABLE patient_info ADD INDEX idx_patient_info_aac01 (AAC01);


-- 3. case_quality 表索引
-- 3.1 住院号字段索引（用于缺陷记录关联查询）
ALTER TABLE case_quality ADD INDEX idx_case_quality_jzhm (JZHM);


-- 4. home_quality 表索引
-- 4.1 住院号字段索引（用于首页缺陷记录关联查询）
ALTER TABLE home_quality ADD INDEX idx_home_quality_zyh (ZYH);


-- ============================================
-- 说明：
-- 1. 如果索引已存在，执行时会报错，可以忽略或先删除再创建
-- 2. 如果字段不存在，执行时会报错，请先确认表结构
-- 3. 数据量大的表，索引创建可能需要几分钟时间
-- 4. 建议在业务低峰期执行
-- ============================================

-- 如果需要先删除已存在的索引（可选，谨慎执行）：
-- ALTER TABLE indicator DROP INDEX idx_indicator_aac01_year_month;
-- ALTER TABLE indicator DROP INDEX idx_indicator_aac01_year;
-- ALTER TABLE indicator DROP INDEX idx_indicator_aac01_month;
-- ALTER TABLE indicator DROP INDEX idx_indicator_aac01;
-- ALTER TABLE patient_info DROP INDEX idx_patient_info_aac01;
-- ALTER TABLE case_quality DROP INDEX idx_case_quality_jzhm;
-- ALTER TABLE home_quality DROP INDEX idx_home_quality_zyh;

