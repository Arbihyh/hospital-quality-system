CREATE TABLE `admin_menu`  (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '菜单名称',
  `parent_id` bigint(20) NOT NULL DEFAULT 0 COMMENT '上级菜单ID',
  `component` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT '' COMMENT '组件路径',
  `icon` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT '' COMMENT '图标',
  `url` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT '' COMMENT '接口权限',
  `type` tinyint(1) NULL DEFAULT NULL COMMENT '类型(1:菜单 2:按扭)',
  `visible` tinyint(1) NOT NULL DEFAULT 1 COMMENT '是否显示（1显示 0隐藏）',
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '状态（1正常 0停用）',
  `remark` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT '' COMMENT '备注',
  `sort` int(11) UNSIGNED NOT NULL DEFAULT 0 COMMENT '排序',
  PRIMARY KEY (`id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 32 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '后台菜单表' ROW_FORMAT = Compact;

INSERT INTO `admin_menu` (`id`, `name`, `parent_id`, `component`, `icon`, `url`, `type`, `visible`, `status`, `remark`, `sort`) VALUES (1, '系统管理', 0, 'system', 'system', '', 1, 1, 1, NULL, 0);
INSERT INTO `admin_menu` (`id`, `name`, `parent_id`, `component`, `icon`, `url`, `type`, `visible`, `status`, `remark`, `sort`) VALUES (2, '管理员', 1, 'system/admin/index', 'user', 'admin/admin/adminList', 1, 1, 1, NULL, 1);
INSERT INTO `admin_menu` (`id`, `name`, `parent_id`, `component`, `icon`, `url`, `type`, `visible`, `status`, `remark`, `sort`) VALUES (3, '添加', 2, '', 'example', 'admin/admin/addAdmin', 2, 1, 1, NULL, 0);
INSERT INTO `admin_menu` (`id`, `name`, `parent_id`, `component`, `icon`, `url`, `type`, `visible`, `status`, `remark`, `sort`) VALUES (4, '修改', 2, '', 'example', 'admin/admin/editAdmin', 2, 1, 1, NULL, 0);
INSERT INTO `admin_menu` (`id`, `name`, `parent_id`, `component`, `icon`, `url`, `type`, `visible`, `status`, `remark`, `sort`) VALUES (5, '删除', 2, '', 'example', 'admin/admin/delAdmin', 2, 1, 1, NULL, 0);
INSERT INTO `admin_menu` (`id`, `name`, `parent_id`, `component`, `icon`, `url`, `type`, `visible`, `status`, `remark`, `sort`) VALUES (6, '管理员组', 1, 'system/group/index', 'user-group', 'admin/admin/adminGroupList', 1, 1, 1, NULL, 2);
INSERT INTO `admin_menu` (`id`, `name`, `parent_id`, `component`, `icon`, `url`, `type`, `visible`, `status`, `remark`, `sort`) VALUES (7, '添加', 6, '', 'example', 'admin/admin/addAdminGroup', 2, 1, 1, NULL, 0);
INSERT INTO `admin_menu` (`id`, `name`, `parent_id`, `component`, `icon`, `url`, `type`, `visible`, `status`, `remark`, `sort`) VALUES (8, '修改', 6, '', 'example', 'admin/admin/editAdminGroup', 2, 1, 1, NULL, 0);
INSERT INTO `admin_menu` (`id`, `name`, `parent_id`, `component`, `icon`, `url`, `type`, `visible`, `status`, `remark`, `sort`) VALUES (9, '删除', 6, '', 'example', 'admin/admin/delAdminGroup', 2, 1, 1, NULL, 0);
INSERT INTO `admin_menu` (`id`, `name`, `parent_id`, `component`, `icon`, `url`, `type`, `visible`, `status`, `remark`, `sort`) VALUES (10, '菜单列表', 6, '', 'example', 'admin/admin/rbacList', 3, 1, 1, NULL, 0);
INSERT INTO `admin_menu` (`id`, `name`, `parent_id`, `component`, `icon`, `url`, `type`, `visible`, `status`, `remark`, `sort`) VALUES (11, '配置菜单', 6, '', 'example', 'admin/admin/configMenu', 3, 1, 1, NULL, 0);
INSERT INTO `admin_menu` (`id`, `name`, `parent_id`, `component`, `icon`, `url`, `type`, `visible`, `status`, `remark`, `sort`) VALUES (12, '管理员日志', 1, 'system/log/index', 'article', 'admin/admin/adminLogList', 1, 1, 1, NULL, 3);
INSERT INTO `admin_menu` (`id`, `name`, `parent_id`, `component`, `icon`, `url`, `type`, `visible`, `status`, `remark`, `sort`) VALUES (14, '验证规则', 0, 'rule', 'rule', '', 1, 1, 1, NULL, 1);
INSERT INTO `admin_menu` (`id`, `name`, `parent_id`, `component`, `icon`, `url`, `type`, `visible`, `status`, `remark`, `sort`) VALUES (15, '添加', 28, '', 'example', 'admin/rule/addErrorRule', 2, 1, 1, NULL, 0);
INSERT INTO `admin_menu` (`id`, `name`, `parent_id`, `component`, `icon`, `url`, `type`, `visible`, `status`, `remark`, `sort`) VALUES (16, '修改', 28, '', 'example', 'admin/admin/editErrorRule', 2, 1, 1, NULL, 0);
INSERT INTO `admin_menu` (`id`, `name`, `parent_id`, `component`, `icon`, `url`, `type`, `visible`, `status`, `remark`, `sort`) VALUES (17, '删除', 28, '', 'example', 'admin/admin/delErrorRule', 2, 1, 1, NULL, 0);
INSERT INTO `admin_menu` (`id`, `name`, `parent_id`, `component`, `icon`, `url`, `type`, `visible`, `status`, `remark`, `sort`) VALUES (18, '用户管理', 0, 'user', 'user', '', 1, 1, 1, NULL, 2);
INSERT INTO `admin_menu` (`id`, `name`, `parent_id`, `component`, `icon`, `url`, `type`, `visible`, `status`, `remark`, `sort`) VALUES (19, '用户管理', 18, 'user/list/index', 'user', 'admin/user/userList', 1, 1, 1, NULL, 0);
INSERT INTO `admin_menu` (`id`, `name`, `parent_id`, `component`, `icon`, `url`, `type`, `visible`, `status`, `remark`, `sort`) VALUES (20, '添加', 19, '', 'example', 'admin/user/addUser', 2, 1, 1, NULL, 0);
INSERT INTO `admin_menu` (`id`, `name`, `parent_id`, `component`, `icon`, `url`, `type`, `visible`, `status`, `remark`, `sort`) VALUES (21, '修改', 19, '', 'example', 'admin/user/editUser', 2, 1, 1, NULL, 0);
INSERT INTO `admin_menu` (`id`, `name`, `parent_id`, `component`, `icon`, `url`, `type`, `visible`, `status`, `remark`, `sort`) VALUES (22, '删除', 19, '', 'example', 'admin/user/delUser', 2, 1, 1, NULL, 2);
INSERT INTO `admin_menu` (`id`, `name`, `parent_id`, `component`, `icon`, `url`, `type`, `visible`, `status`, `remark`, `sort`) VALUES (23, '用户组', 18, 'user/group/index', 'user-group', 'admin/user/userGroupList', 1, 1, 1, NULL, 0);
INSERT INTO `admin_menu` (`id`, `name`, `parent_id`, `component`, `icon`, `url`, `type`, `visible`, `status`, `remark`, `sort`) VALUES (24, '添加', 23, '', 'example', 'admin/user/addUserGroup', 2, 1, 1, NULL, 0);
INSERT INTO `admin_menu` (`id`, `name`, `parent_id`, `component`, `icon`, `url`, `type`, `visible`, `status`, `remark`, `sort`) VALUES (25, '修改', 23, '', 'example', 'admin/user/editUserGroup', 2, 1, 1, NULL, 0);
INSERT INTO `admin_menu` (`id`, `name`, `parent_id`, `component`, `icon`, `url`, `type`, `visible`, `status`, `remark`, `sort`) VALUES (26, '删除', 23, '', 'example', 'admin/user/delUserGroup', 2, 1, 1, NULL, 0);
INSERT INTO `admin_menu` (`id`, `name`, `parent_id`, `component`, `icon`, `url`, `type`, `visible`, `status`, `remark`, `sort`) VALUES (27, '配置菜单', 23, '', 'example', 'admin/user/rbacMenu', 3, 1, 1, NULL, 0);
INSERT INTO `admin_menu` (`id`, `name`, `parent_id`, `component`, `icon`, `url`, `type`, `visible`, `status`, `remark`, `sort`) VALUES (28, '基础规则', 14, 'rule/basic/index', 'basic', 'admin/rule/errorList', 1, 1, 1, '', 0);
INSERT INTO `admin_menu` (`id`, `name`, `parent_id`, `component`, `icon`, `url`, `type`, `visible`, `status`, `remark`, `sort`) VALUES (30, '用户日志', 18, 'user/log/index', 'article', 'admin/user/userLogList', 1, 1, 1, '', 0);
INSERT INTO `admin_menu` (`id`, `name`, `parent_id`, `component`, `icon`, `url`, `type`, `visible`, `status`, `remark`, `sort`) VALUES (31, '收费项目', 14, 'rule/charges/index', 'charges', 'admin/rule/errorList', 1, 1, 1, '', 0);
