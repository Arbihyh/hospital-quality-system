CREATE TABLE `admin`  (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT '' COMMENT '昵称',
  `account` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '账号',
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '密码',
  `salt` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '盐值',
  `group_id` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '权限组id',
  `token` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '登陆token',
  `login_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '登陆时间',
  `login_ip` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '登陆ip',
  `desc` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '描述',
  `phone` varchar(11) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '手机号码',
  `realname` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '真实名称',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `admin_name_index`(`name`) USING BTREE,
  INDEX `admin_account_index`(`account`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 11 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = Compact;

INSERT INTO `admin` (`id`, `name`, `account`, `password`, `salt`, `group_id`, `token`, `login_at`, `login_ip`, `desc`, `phone`, `realname`) VALUES (1, 'jocker', 'jocker', '562537', '562537', '1', 'fe763fca4b9dd56cfed32c3dfd287a32', '2023-02-20 23:28:26', '127.0.0.1', NULL, NULL, NULL);
INSERT INTO `admin` (`id`, `name`, `account`, `password`, `salt`, `group_id`, `token`, `login_at`, `login_ip`, `desc`, `phone`, `realname`) VALUES (2, 'lwl', 'lwl', '147258', '147258', '1', '9ea9d9f6fa96593fbd35e39d639623fb', '2023-02-22 14:53:20', '127.0.0.1', '开发测试', NULL, NULL);
INSERT INTO `admin` (`id`, `name`, `account`, `password`, `salt`, `group_id`, `token`, `login_at`, `login_ip`, `desc`, `phone`, `realname`) VALUES (4, 'moquan', 'test', '123456', '123456', '8', '', '2023-02-14 11:47:17', NULL, 'ioioioi', '15896475872', NULL);
INSERT INTO `admin` (`id`, `name`, `account`, `password`, `salt`, `group_id`, `token`, `login_at`, `login_ip`, `desc`, `phone`, `realname`) VALUES (6, '0102', '0102', '0102', '0102', '1', NULL, '2023-02-17 08:27:44', NULL, NULL, NULL, NULL);
INSERT INTO `admin` (`id`, `name`, `account`, `password`, `salt`, `group_id`, `token`, `login_at`, `login_ip`, `desc`, `phone`, `realname`) VALUES (7, '运维', 'test2', '147258', '147258', '1', NULL, '2023-02-19 02:48:25', NULL, '描述', NULL, NULL);
