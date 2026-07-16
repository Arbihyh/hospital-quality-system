CREATE TABLE `user`  (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(56) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '用户名',
  `password` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '密码',
  `status` tinyint(4) NOT NULL DEFAULT 0 COMMENT '状态0：正常1：停用',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `group_id` int(11) NULL DEFAULT NULL,
  `token` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `login_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `login_ip` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '登陆ip',
  `desc` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '描述',
  `phone` varchar(11) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '手机号码',
  `realname` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '真实姓名',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `user_name_index`(`name`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 19 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = Compact;

INSERT INTO `user` (`id`, `name`, `password`, `status`, `created_at`, `updated_at`, `group_id`, `token`, `login_at`, `login_ip`, `desc`, `phone`, `realname`) VALUES (1, 'jiankun', '5apgfa1Y', 0, '2022-09-17 08:30:21', '2023-02-23 12:03:08', 1, '9ccfa42294152c301edc7bdc7cfbda8e', '2023-02-23 12:03:08', '127.0.0.1', NULL, NULL, NULL);
INSERT INTO `user` (`id`, `name`, `password`, `status`, `created_at`, `updated_at`, `group_id`, `token`, `login_at`, `login_ip`, `desc`, `phone`, `realname`) VALUES (2, 'ruifeng', 'KaIw65sN', 0, '2023-01-10 17:36:24', '2023-02-22 19:57:16', 1, 'c7808e6848d09e6849e7f7d97eeaa6ff', '2023-02-22 19:57:16', '127.0.0.1', NULL, NULL, NULL);
INSERT INTO `user` (`id`, `name`, `password`, `status`, `created_at`, `updated_at`, `group_id`, `token`, `login_at`, `login_ip`, `desc`, `phone`, `realname`) VALUES (3, 'guohong', 'R8wRyrYf', 0, '2023-01-10 17:36:58', '2023-02-18 21:18:19', 1, '46ce1ec9c4b5c1a01f1ab9c26880f97c', '2023-02-18 21:18:19', '127.0.0.1', NULL, NULL, NULL);
INSERT INTO `user` (`id`, `name`, `password`, `status`, `created_at`, `updated_at`, `group_id`, `token`, `login_at`, `login_ip`, `desc`, `phone`, `realname`) VALUES (4, 'chenzong', 'nnyGGta1', 0, '2023-01-10 17:37:39', '2023-02-20 17:13:10', 1, 'f731fc2e12ab8f8aad4beb45885c8b95', '2023-02-20 17:13:10', '127.0.0.1', NULL, NULL, NULL);
INSERT INTO `user` (`id`, `name`, `password`, `status`, `created_at`, `updated_at`, `group_id`, `token`, `login_at`, `login_ip`, `desc`, `phone`, `realname`) VALUES (5, 'admin1', '8E0XYy81', 0, '2023-01-12 01:46:54', '2023-01-12 01:46:54', 1, NULL, '2023-02-15 06:49:12', NULL, NULL, NULL, NULL);
INSERT INTO `user` (`id`, `name`, `password`, `status`, `created_at`, `updated_at`, `group_id`, `token`, `login_at`, `login_ip`, `desc`, `phone`, `realname`) VALUES (6, 'admin2', 'w7ZWK87o', 0, '2023-01-12 01:46:54', '2023-01-12 01:46:54', 1, NULL, '2023-02-15 06:49:12', NULL, NULL, NULL, NULL);
INSERT INTO `user` (`id`, `name`, `password`, `status`, `created_at`, `updated_at`, `group_id`, `token`, `login_at`, `login_ip`, `desc`, `phone`, `realname`) VALUES (12, 'lwl', '123456', 0, '2023-02-15 07:28:49', '2023-02-19 11:50:09', 3, '80ed2b9ed2b331eb32d7741720d63b73', '2023-02-19 11:50:09', '127.0.0.1', NULL, NULL, NULL);
INSERT INTO `user` (`id`, `name`, `password`, `status`, `created_at`, `updated_at`, `group_id`, `token`, `login_at`, `login_ip`, `desc`, `phone`, `realname`) VALUES (17, 'eeeee', 'ssss', 0, '2023-02-19 09:48:43', '2023-02-19 17:49:34', 1, NULL, '2023-02-19 09:48:43', NULL, NULL, '15748789651', '本琦圾');
