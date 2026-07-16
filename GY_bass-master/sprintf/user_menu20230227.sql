DROP TABLE IF EXISTS `user_menu`;
CREATE TABLE `user_menu`  (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `title` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '菜单名称',
  `parent_id` bigint(20) NOT NULL DEFAULT 0 COMMENT '上级菜单ID',
  `path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT '' COMMENT '页面路径',
  `component` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT '' COMMENT '组件路径',
  `name` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT '' COMMENT '组件名称',
  `redirect` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT '' COMMENT '跳转',
  `hidden` tinyint(1) NOT NULL DEFAULT 0 COMMENT '导航栏展示0:不展示 1:展示',
  `always_show` tinyint(1) NOT NULL DEFAULT 0 COMMENT '始终显示根菜单 0:不显示 1：显示',
  `keep_alive` tinyint(1) NOT NULL DEFAULT 0 COMMENT '缓存页面 0:不缓存 1:缓存',
  `icon` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT '' COMMENT '图标',
  `url` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT '' COMMENT '接口权限',
  `type` tinyint(1) NULL DEFAULT 1 COMMENT '类型(1:菜单 2:按扭)',
  `visible` tinyint(1) NOT NULL DEFAULT 1 COMMENT '是否显示（1显示 0隐藏）',
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '状态（1正常 0停用）',
  `remark` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT '' COMMENT '备注',
  `sort` int(11) UNSIGNED NOT NULL DEFAULT 0 COMMENT '排序',
  PRIMARY KEY (`id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 35 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '前台用户菜单表' ROW_FORMAT = Compact;


INSERT INTO `user_menu` VALUES (1, '首页', 0, '/', 'dashboard', 'Dashboard', '/dashboard', 0, 0, 0, 'dashboard', '', 1, 1, 1, '', 0);
INSERT INTO `user_menu` VALUES (2, '全病历质控', 0, '/allcase', '', '', '/allcase', 0, 1, 0, 'el-icon-s-help', '', 1, 1, 1, '', 0);
INSERT INTO `user_menu` VALUES (3, '病历质控', 2, '/allcase', 'allcase', 'allcase', '', 0, 0, 0, 'table', '', 1, 1, 1, '', 0);
INSERT INTO `user_menu` VALUES (4, '病案数量', 2, '/caseNumber', 'allcase/caseNumber', 'caseNumber', '', 1, 0, 0, '', '', 1, 1, 1, '', 0);
INSERT INTO `user_menu` VALUES (5, '缺陷病案', 2, '/defectNumber', 'allcase/defectNumber', 'defectNumber', '', 1, 0, 0, '', '', 1, 1, 1, '', 0);
INSERT INTO `user_menu` VALUES (6, '出院记录', 2, '/homePage', 'allcase/homePage', 'homePage', '', 1, 0, 0, '', '', 1, 1, 1, '', 0);
INSERT INTO `user_menu` VALUES (7, '病历搜索引擎', 0, '/search', 'search', 'Search', '/search', 0, 0, 0, 'el-icon-s-help', '', 1, 1, 1, '', 0);
INSERT INTO `user_menu` VALUES (8, '首页数据质控', 0, '/data', '', 'data', '/data/front', 0, 1, 0, '', '', 1, 1, 1, '', 0);
INSERT INTO `user_menu` VALUES (9, '质控前首页质量分析', 8, '/data/front', 'data/frontHome/index', 'front', '', 0, 0, 1, '', '', 1, 1, 1, '', 0);
INSERT INTO `user_menu` VALUES (10, '质控后首页质量分析', 8, '/data/after', 'data/afterHome/index', 'after', '', 0, 0, 1, 'tree', '', 1, 1, 1, '', 0);
INSERT INTO `user_menu` VALUES (11, '病案首页质控查询', 8, '/data/query', 'data/query/index', 'query', '', 0, 0, 1, 'tree', '', 1, 1, 1, '', 0);
INSERT INTO `user_menu` VALUES (12, '病案首页缺陷分析', 8, '/data/analysis', 'data/analysis/index', 'analysis', '', 0, 0, 0, 'tree', '', 1, 1, 1, '', 0);
INSERT INTO `user_menu` VALUES (13, '质量分析缺陷病案', 8, '/defectList', 'data/medicalRecords/defectList', 'front', '', 1, 0, 1, '', '', 1, 1, 1, '', 0);
INSERT INTO `user_menu` VALUES (14, '质量分析病案数量', 8, '/medicalRecords', 'data/medicalRecords', 'front', '', 1, 0, 1, '', '', 1, 1, 1, '', 0);
INSERT INTO `user_menu` VALUES (15, '病案数', 8, '/errorList', 'data/medicalRecords/errorList', 'front', '', 1, 0, 0, '', '', 1, 1, 1, '', 0);
INSERT INTO `user_menu` VALUES (16, '总缺陷', 8, '/department', 'data/medicalRecords/department', 'front', '', 1, 0, 0, '', '', 1, 1, 1, '', 0);
INSERT INTO `user_menu` VALUES (17, '总排名', 8, '/TotalRankingList', 'data/frontHome/TotalRankingList', 'TotalRankingList', '', 1, 0, 0, '', '', 1, 1, 1, '', 0);
INSERT INTO `user_menu` VALUES (18, '编码员', 8, '/codeList', 'data/medicalRecords/codeList', 'codeList', '', 1, 0, 0, '', '', 1, 1, 1, '', 0);
INSERT INTO `user_menu` VALUES (19, '病案首页质控详情', 8, '/details', 'data/query/details', 'details', '', 1, 0, 0, '', '', 1, 1, 1, '', 0);
INSERT INTO `user_menu` VALUES (20, '费用明细', 8, '/ChargeDetails', 'data/query/ChargeDetails', 'ChargeDetails', '', 1, 0, 0, '', '', 1, 1, 1, '', 0);
INSERT INTO `user_menu` VALUES (21, '医保结算清单', 0, '/SettlementList', 'SettlementList', 'SettlementList', '/SettlementIndex', 0, 1, 0, 'el-icon-s-help', '', 1, 1, 1, '', 0);
INSERT INTO `user_menu` VALUES (22, '结算清单质量分析', 21, '/SettlementIndex', 'SettlementList/index', 'SettlementIndex', '', 0, 0, 0, 'table', '', 1, 1, 1, '', 0);
INSERT INTO `user_menu` VALUES (23, '医保上报', 21, '/yb', 'reportingCenter/report/yb/index', 'yb', '', 0, 0, 0, 'table', '', 1, 1, 1, '', 0);
INSERT INTO `user_menu` VALUES (24, '医保上报历史', 21, '/ybhistory', 'reportingCenter/history/yb/index', 'yb', '', 0, 0, 0, '', '', 1, 1, 1, '', 0);
INSERT INTO `user_menu` VALUES (25, '结算清单数量', 21, '/StatementList', 'SettlementList/StatementList', 'StatementList', '', 1, 0, 0, '', '', 1, 1, 1, '', 0);
INSERT INTO `user_menu` VALUES (26, '医保结算单病案数量', 21, '/SetDetails', 'SettlementList/SetDetails', 'SetDetails', '', 1, 0, 0, '', '', 1, 1, 1, '', 0);
INSERT INTO `user_menu` VALUES (27, '医保结算单病案数量', 21, '/StatementListquery', 'SettlementList/StatementListquery', 'StatementListquery', '', 1, 0, 0, '', '', 1, 1, 1, '', 0);
INSERT INTO `user_menu` VALUES (28, '缺陷结算清单数量', 21, '/defectStatementList', 'SettlementList/defectStatementList', 'defectStatementList', '', 1, 0, 0, '', '', 1, 1, 1, '', 0);
INSERT INTO `user_menu` VALUES (29, '上报中心', 0, '/reportingCenter', '', 'reportingCenter', '', 0, 0, 0, 'nested', '', 1, 1, 1, '', 0);
INSERT INTO `user_menu` VALUES (30, '国考上报', 29, '/reportingCenter/gk', 'reportingCenter/report/gk/index', 'gk', '', 0, 0, 0, 'table', '', 1, 1, 1, '', 0);
INSERT INTO `user_menu` VALUES (31, '卫统上报', 29, '/reportingCenter/wt', 'reportingCenter/report/wt/index', 'wt', '', 0, 0, 0, 'table', '', 1, 1, 1, '', 0);
INSERT INTO `user_menu` VALUES (32, '绩效考核', 0, '/assessment', '', 'assessment', '', 0, 0, 0, 'el-icon-s-help', '', 1, 1, 1, '', 0);
INSERT INTO `user_menu` VALUES (33, '指标', 32, '/assessment/assessment', 'assessment', 'assessment', '', 0, 0, 0, 'table', '', 1, 1, 1, '', 0);
INSERT INTO `user_menu` VALUES (34, '单病种质量', 32, '/assessment/quality', 'assessment/quality.vue', 'quality', '', 0, 0, 0, 'tree', '', 1, 1, 1, '', 0);
