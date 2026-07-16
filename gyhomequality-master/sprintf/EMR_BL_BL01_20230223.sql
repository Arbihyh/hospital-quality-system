ALTER TABLE `quality`.`EMR_BL_BL01`
ADD COLUMN `is_defect` tinyint(4) NULL DEFAULT 0 COMMENT '数据是否有缺陷，根据病例质控结果判断';
ALTER TABLE `quality`.`EMR_BL_BL01`
ADD COLUMN `updated_at` date NOT NULL DEFAULT '0000-00-00';
ALTER TABLE `quality`.`EMR_BL_BL01`
ADD COLUMN `analysis_case` text COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '病例解析之后的json数据';


ALTER TABLE `error_rule`
ADD COLUMN `status` tinyint(1) NOT NULL DEFAULT '0' COMMENT '状态 0启用 1:停用';

-- 2023年3月6日23:11:37  修改字段长度
ALTER TABLE `quality`.`EMR_BL_BL01`
MODIFY COLUMN `analysis_case` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '病例解析之后的json数据' AFTER `updated_at`;

-- 解析出的初步诊断
ALTER TABLE `quality`.`EMR_BL_BL01`
ADD COLUMN `diagnose_list` varchar(2000) NOT NULL DEFAULT '' COMMENT '解析出的初步诊断' AFTER `analysis_case`;
ALTER TABLE `quality`.`EMR_BL_BL01`
ADD COLUMN `analysis_index` int(0) NOT NULL DEFAULT 0 COMMENT '解析次数，三次失败将不在解析，则排查原因' AFTER `diagnose_list`;


--- 指标需求相关的数据表变动
ALTER TABLE `quality`.`yzb` ADD INDEX `idx_zyh`(`ZYH`);
ALTER TABLE `quality`.`yzb` ADD COLUMN `is_has_kjyw` tinyint(255) NOT NULL DEFAULT 0 COMMENT '医嘱名称是否包含抗菌药物';
ALTER TABLE `quality`.`yzb` ADD COLUMN `is_has_hlyw` tinyint(255) NOT NULL DEFAULT 0 COMMENT '医嘱名称是否包含化疗药物';
ALTER TABLE `quality`.`yzb` ADD COLUMN `is_clean` tinyint(255) NOT NULL DEFAULT 0 COMMENT '数据是否清洗过';
ALTER TABLE `quality`.`patient_info` ADD COLUMN `denominator_bl` tinyint(1) NOT NULL DEFAULT 0 COMMENT '病理指标的分母标识，1满足分母要求';
ALTER TABLE `quality`.`patient_info` ADD COLUMN `numerator_bl` tinyint(1) NOT NULL DEFAULT 0 COMMENT '病理指标的分子标识，1满足分子要求';
ALTER TABLE `quality`.`patient_info` ADD COLUMN `denominator_kjyw` tinyint(1) NOT NULL DEFAULT 0 COMMENT '抗菌药物使用记录符合率指标的分母标识，1满足分母要求';
ALTER TABLE `quality`.`patient_info` ADD COLUMN `numerator_kjyw` tinyint(1) NOT NULL DEFAULT 0 COMMENT '抗菌药物使用记录符合率指标的分子标识，1满足分子要求';
ALTER TABLE `quality`.`patient_info` ADD COLUMN `denominator_exzlhxzl` tinyint(1) NOT NULL DEFAULT 0 COMMENT '恶性肿瘤化学治疗记录符合率指标的分母标识，1满足分母要求';
ALTER TABLE `quality`.`patient_info` ADD COLUMN `numerator_exzlhxzl` tinyint(1) NOT NULL DEFAULT 0 COMMENT '恶性肿瘤化学治疗记录符合率指标的分子标识，1满足分子要求';
ALTER TABLE `quality`.`patient_info` ADD COLUMN `denominator_exzlfszl` tinyint(1) NOT NULL DEFAULT 0 COMMENT '恶性肿瘤放射治疗记录符合率指标的分母标识，1满足分母要求';
ALTER TABLE `quality`.`patient_info` ADD COLUMN `numerator_exzlfszl` tinyint(1) NOT NULL DEFAULT 0 COMMENT '恶性肿瘤放射治疗记录符合率指标的分子标识，1满足分子要求';
ALTER TABLE `quality`.`patient_info` ADD COLUMN `numerator_bhlbl` tinyint(1) NOT NULL DEFAULT 0 COMMENT '不合理复制病历发生率的分子，1满足分子要求';
ALTER TABLE `quality`.`patient_info` ADD COLUMN `denominator_bhlbl` tinyint(1) NULL DEFAULT 1 COMMENT '不合理复制病历发生率的分母，1满足分子要求' AFTER `numerator_bhlbl`;
ALTER TABLE `quality`.`patient_info` ADD COLUMN `bhlbl_content` varbinary(255) NOT NULL DEFAULT '' COMMENT '不合理复制病历发生率的原因';
ALTER TABLE `quality`.`patient_info` ADD COLUMN `denominator_operation` varbinary(255) NOT NULL DEFAULT 0 COMMENT '手术相关记录完整率分母';
ALTER TABLE `quality`.`patient_info` ADD COLUMN `numerator_operation` varbinary(255) NOT NULL DEFAULT 0 COMMENT '手术相关记录完整率分子';
----新增字段
ALTER TABLE `quality`.`yzb`
ADD COLUMN `kjyw_name` varchar(255) NOT NULL DEFAULT '' COMMENT '抗菌药物名称' AFTER `is_clean`,
ADD COLUMN `hlyw_name` varchar(255) NOT NULL DEFAULT '' COMMENT '化疗药物名称' AFTER `kjyw_name`;
ALTER TABLE `quality`.`yzb`
ADD COLUMN `is_fangliao` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否有化疗关键字' AFTER `hlyw_name`;
ALTER TABLE `quality`.`yzb`
ADD COLUMN `is_operation` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否是手术医嘱';
ALTER TABLE `quality`.`EMR_BL_BLXG`
ADD COLUMN `is_operation` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否包含术前小结及术前讨论结论记录',
ADD COLUMN `is_fangliao` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否包含放疗关键字',
ADD COLUMN `is_has_kjyw` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否包含抗菌药物',
ADD COLUMN `is_has_hlyw` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否包含化疗药物';
ALTER TABLE `quality`.`EMR_BL_BL01`
ADD COLUMN `bcts` text NOT NULL COMMENT '病程-病程特色' AFTER `analysis_index`;

ALTER TABLE `quality`.`EMR_BL_BL01`
ADD COLUMN `operation_time` int(11) DEFAULT '0' COMMENT '手术时间',
ADD COLUMN `bc_content` text NOT NULL COMMENT '病程-非首次病程的内容',
ADD COLUMN `ryjl_xbs` text NOT NULL COMMENT '入院记录-现病史';

ALTER TABLE `quality`.`fee_detailed` ADD COLUMN `is_bingli` tinyint(1) NULL DEFAULT 0 COMMENT '是否包含病理诊断费用';
ALTER TABLE `quality`.`fee_detailed` ADD COLUMN `is_clean` tinyint(1) NULL DEFAULT 0 COMMENT '是否清理信息';
