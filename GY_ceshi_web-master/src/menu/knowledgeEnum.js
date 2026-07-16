
const KNOWLEDGE_TYPE = {
  OPERATION: '手术操作',       // 手术类型  
  DISEASE: '医疗疾病',         // 疾病类型
  EXAMINATION: '检验检查', // 检查检验类型
  MEDICINE: '药品'         // 药品类型
};

const FIELD_MAPPING_TABLE = {
  [KNOWLEDGE_TYPE.OPERATION]: [
    { originalKey: 'department', title: '科室' },
    { originalKey: 'operation_name', title: '手术名称' },
    { originalKey: 'principle', title: '原理' },
    { originalKey: 'notes', title: '注意事项' },
    { originalKey: 'indication', title: '适应症' },
    { originalKey: 'contraindication', title: '禁忌症' },
    { originalKey: 'operation_method_and_procedure', title: '操作方法程序' },
    { originalKey: 'complication', title: '并发症' },
    { originalKey: 'time', title: '时间' },
    { originalKey: 'purpose', title: '目的' },
    { originalKey: 'follow_up_visit', title: '随访' },
    { originalKey: 'deal', title: '处理' },
    { originalKey: 'reason', title: '原因' },
    { originalKey: 'clinical_manifestations_diagnosis', title: '临床表现和诊断' },
    { originalKey: 'precaution', title: '预防' },
    { originalKey: 'donor_selection', title: '供体选择' },
    { originalKey: 'treatment', title: '治疗' },
    { originalKey: 'common_laser_equipment', title: '常用激光医疗设备' },
    { originalKey: 'preoperative_preparation', title: '术前准备' },
    { originalKey: 'preopreative_examination', title: '术前检查' },
    { originalKey: 'postoperative_management', title: '术后处理' },
    { originalKey: 'postoperative_care', title: '术后护理' },
    { originalKey: 'surgical_instruments', title: '手术器械' },
    { originalKey: 'anesthesia', title: '麻醉' },
    { originalKey: 'position', title: '体位' },
    { originalKey: 'apparatus', title: '特殊器械' },
    { originalKey: 'admission_passage', title: '入路' },
    { originalKey: 'operation_plain', title: '手术计划' },
    { originalKey: 'radiological_dose', title: '放射剂量' },
    { originalKey: 'operation_result', title: '手术结果' },
    { originalKey: 'remark', title: '其他' },
    { originalKey: 'treatment_methods', title: '用药方法' }
  ],

  [KNOWLEDGE_TYPE.DISEASE]: [
    { originalKey: 'department_1', title: '一级科室' },
    { originalKey: 'department_2', title: '二级科室' },
    { originalKey: 'name', title: '疾病名称' },
    { originalKey: 'nameEn', title: '英文名称' },
    { originalKey: 'alias', title: '别名' },
    { originalKey: 'sickOverview', title: '疾病概述' },
    { originalKey: 'clinicalFeature', title: '临床表现' },
    { originalKey: 'diagnosis', title: '诊断' },
    { originalKey: 'treatment', title: '治疗' },
    { originalKey: 'regularMedication', title: '相关药品' },
    { originalKey: 'pathogenesis', title: '发病机制' },
    { originalKey: 'inspection', title: '相关检查' },
    { originalKey: 'relevantOperation', title: '相关操作' },
    { originalKey: 'laboratoryInspection', title: '实验室检查' },
    { originalKey: 'icd', title: 'icd' },
    { originalKey: 'etiology', title: '病因' },
    { originalKey: 'pathology', title: '病理' },
    { originalKey: 'auxiliaryExamination', title: '其他辅助检查' },
    { originalKey: 'antidiastole', title: '鉴别诊断' },
    { originalKey: 'prognosis', title: '预后' },
    { originalKey: 'complicationsOverview', title: '并发症' },
    { originalKey: 'epidemiology', title: '流行病学' },
    { originalKey: 'precaution', title: '预防' },
    { originalKey: 'symptom', title: '症状' },
    { originalKey: 'examination', title: '查体' },
    { originalKey: 'updated_at', title: '更新时间' },
    { originalKey: 'created_at', title: '创建时间' }
  ],

  [KNOWLEDGE_TYPE.EXAMINATION]: [
    { originalKey: 'id', title: '西医知识库检查检验' },
    { originalKey: 'type', title: '类型（0无、1检查、2检验）' },
    { originalKey: 'department', title: '科室' },
    { originalKey: 'name', title: '名称' },
    { originalKey: 'alias', title: '别名' },
    { originalKey: 'overview', title: '概述' },
    { originalKey: 'principle', title: '原理' },
    { originalKey: 'reagent', title: '试剂' },
    { originalKey: 'operation', title: '操作方法' },
    { originalKey: 'clinicalSignificance', title: '临床意义' },
    { originalKey: 'normalValue', title: '正常值' },
    { originalKey: 'annotation', title: '附注' },
    { originalKey: 'precautions', title: '注意事项' },
    { originalKey: 'process', title: '检查过程' },
    { originalKey: 'related_symptoms', title: '相关症状' },
    { originalKey: 'related_diseases', title: '相关疾病' },
    { originalKey: 'sex', title: '性别' }
  ],

  [KNOWLEDGE_TYPE.MEDICINE]: [
    { originalKey: 'name', title: '通用名称' },
    { originalKey: 'pname', title: '商品名称' },
    { originalKey: 'donghua_medicine_name', title: '名称' },
    { originalKey: 'medicineNature', title: '药品性质' },
    { originalKey: 'pinyin', title: '汉语拼音' },
    { originalKey: 'productionEnterprise', title: '生产企业' },
    { originalKey: 'relateSick', title: '相关疾病' },
    { originalKey: 'indication', title: '适应症' },
    { originalKey: 'notes', title: '注意事项' },
    { originalKey: 'untowardEffect', title: '不良反应' },
    { originalKey: 'oldUse', title: '老人用药' },
    { originalKey: 'chilldUse', title: '儿童用药' },
    { originalKey: 'medicineInteractions', title: '药物相互作用' },
    { originalKey: 'medicinePregnant', title: '孕妇及哺乳期妇女用药' },
    { originalKey: 'character', title: '性状' },
    { originalKey: 'contraindication', title: '禁忌' },
    { originalKey: 'specification', title: '规格' },
    { originalKey: 'pharmacologyToxicology', title: '药理毒理' },
    { originalKey: 'periodValidity', title: '有效期' },
    { originalKey: 'storage', title: '贮藏' },
    { originalKey: 'majorConstituent', title: '主要成分' },
    { originalKey: 'usageDosage', title: '用法用量' },
    { originalKey: 'approvalNumber', title: '批准文号' },
    { originalKey: 'updated_at', title: '更新时间' }
  ]
};

export const KnowledgeEnum = {
  TYPE: KNOWLEDGE_TYPE,
  FIELD_MAPPING: FIELD_MAPPING_TABLE,
  getFieldMappingByType: function (type) {
    return this.FIELD_MAPPING[type] || [];
  }
};
export function convertKnowledgeData(originalData, dataType) {
  const fieldMapping = KnowledgeEnum.getFieldMappingByType(dataType);
  if (!fieldMapping.length) {
    console.warn('无效的数据类型，无法获取字段映射表');
    return [];
  }

  const result = [];
  fieldMapping.forEach((item, index) => {
    const labelValue = originalData[item.originalKey] ?? '';
    const targetId = `YC${String(index + 1).padStart(3, '0')}`;

    result.push({
      id: targetId,
      title: item.title,
      label: labelValue
    });
  });

  return result;
}