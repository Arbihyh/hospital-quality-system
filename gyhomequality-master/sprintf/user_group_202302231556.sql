CREATE TABLE `user_group`  (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `role` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '[]' COMMENT '所有权',
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '角色名',
  `desc` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT '' COMMENT '描述',
  `admin_id` int(11) NOT NULL COMMENT '创建人id',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `admin_group_admin_id_index`(`admin_id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 6 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '前台用户组表' ROW_FORMAT = Compact;

INSERT INTO `user_group` (`id`, `role`, `name`, `desc`, `admin_id`) VALUES (1, 'all', '默认用户组', '默认用户组', 1);
INSERT INTO `user_group` (`id`, `role`, `name`, `desc`, `admin_id`) VALUES (3, '[\"2\",\"5\",\"6\",\"7\",\"8\",\"14\",\"15\",\"28\"]', '开发测试组', '开发测试组', 1);
INSERT INTO `user_group` (`id`, `role`, `name`, `desc`, `admin_id`) VALUES (4, '[\"1\",\"2\",\"3\",\"29\"]', '组2', '组2', 1);
