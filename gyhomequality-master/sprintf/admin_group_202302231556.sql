CREATE TABLE `admin_group`  (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `role` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '[]' COMMENT '所有权',
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '角色名',
  `desc` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '描述',
  `admin_id` int(11) NOT NULL COMMENT '创建人id',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `admin_group_admin_id_index`(`admin_id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 9 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = Compact;

INSERT INTO `admin_group` (`id`, `role`, `name`, `desc`, `admin_id`) VALUES (1, 'all', '超级管理员组', '超级管理员组', 1);
INSERT INTO `admin_group` (`id`, `role`, `name`, `desc`, `admin_id`) VALUES (4, '[\"1\",\"2\",\"3\",\"4\",\"5\",\"6\",\"7\",\"8\",\"9\",\"10\",\"11\",\"12\",\"14\",\"15\",\"16\",\"17\",\"28\",\"18\",\"19\",\"20\",\"21\",\"22\",\"23\",\"24\",\"25\",\"26\",\"27\",\"29\"]', '开发测试组', 'fdsfdsgs', 2);
INSERT INTO `admin_group` (`id`, `role`, `name`, `desc`, `admin_id`) VALUES (8, '[\"1\",\"2\",\"3\",\"4\",\"5\",\"6\",\"7\",\"8\",\"9\",\"10\",\"11\",\"12\",\"14\",\"28\",\"15\",\"16\",\"17\",\"18\",\"19\",\"20\",\"21\",\"22\",\"23\",\"24\",\"25\",\"26\",\"27\",\"30\"]', 'eeee', 'rrr', 2);
