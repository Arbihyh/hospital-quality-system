CREATE TABLE `operation_relations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT COMMENT '自增ID',
  `fee_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '项目名称',
  `code` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '编码',
  `operation_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '手术名称',
  `fee_unit` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '计价单位',
  `price` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '价格',
  `status` tinyint(4) NOT NULL DEFAULT '0' COMMENT '01',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1219 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- quality.user_log definition

CREATE TABLE `user_log` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `path` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '路由',
  `method` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '方法',
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '标题',
  `content` longtext COLLATE utf8mb4_unicode_ci COMMENT '内容(就记请求参数吧)',
  `username` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '前台用户昵称',
  `user_id` int(11) NOT NULL COMMENT '前台用户id',
  `user_agent` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'user_agent',
  `ip` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '请求ip',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  PRIMARY KEY (`id`) USING BTREE
) ENGINE=MyISAM AUTO_INCREMENT=113 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='前台用户日志表';


alter table user add column `group_id` int(11) DEFAULT NULL;
alter table user add column `token` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL;
alter table user add column  `login_ip` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '登陆ip';
alter table user add column  `desc` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '描述';
alter table user add column  `phone` varchar(11) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '手机号码';
alter table user add column  `realname` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '真实姓名';