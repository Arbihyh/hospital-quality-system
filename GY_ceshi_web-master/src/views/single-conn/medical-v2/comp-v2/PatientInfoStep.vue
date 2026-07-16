<template>
  <div class="step-content active" id="step1Content">
    <div v-show="pageFlag === '1'">
      <div class="card-head space">
        <div class="card-head-left">
          <span class="pill pill-purple">患者信息</span>
        </div>
        <div class="card-head-right">
          <button class="btn btn-light" @click="showHistory">历史记录</button>
        </div>
      </div>
      <el-form ref="formRef" :model="formData" :rules="rules" label-position="top" style="margin-bottom: 12px">
        <div style="display: flex; gap: 12px; margin-bottom: 8px">
          <el-form-item prop="bah" label="病案号" style="flex: 1; margin: 0">
            <el-input v-model="formData.bah" placeholder="请输入病案号" maxlength="20" />
          </el-form-item>
          <el-form-item prop="xm" label="姓名" style="flex: 1; margin: 0">
            <el-input v-model="formData.xm" placeholder="请输入患者姓名" maxlength="30" />
          </el-form-item>
        </div>

        <div style="display: flex; gap: 12px; margin-bottom: 8px">
          <el-form-item prop="department_id" label="科室" style="flex: 1; margin: 0">
            <el-select v-model="formData.department_id" placeholder="请选择科室" filterable clearable style="width: 100%">
              <el-option v-for="item in departmentList" :key="item.id" :label="item.name" :value="item.id" />
            </el-select>
          </el-form-item>
          <el-form-item label="病种" style="flex: 1; margin: 0">
            <el-select v-model="formData.disease_id" placeholder="请选择病种" filterable clearable style="width: 100%">
              <el-option v-for="item in diseaseList" :key="item.id" :label="item.name" :value="item.id" />
            </el-select>
          </el-form-item>
        </div>

        <div style="display: flex; gap: 12px; margin-bottom: 8px">
          <el-form-item label="性别" style="flex: 1; margin: 0">
            <el-select v-model="formData.xb" placeholder="请选择性别" clearable style="width: 100%">
              <el-option label="男" value="男" />
              <el-option label="女" value="女" />
            </el-select>
          </el-form-item>
          <el-form-item label="年龄" style="flex: 1; margin: 0">
            <el-input v-model="formData.nl" placeholder="请输入年龄" maxlength="3" oninput="value=value.replace(/[^\d]/g,'')" />
          </el-form-item>
        </div>
      </el-form>

      <div style="margin-bottom: 12px">
        <div class="form-label">
          <span class="required">*</span>
          选择文书类型
        </div>
        <div class="doc-select-grid" style="margin-top: 6px">
          <div v-for="(doc, idx) in taskNameList" :key="doc.id" style="margin-bottom: 8px">
            <div class="doc-select-item" :class="{ selected: formData.docType === doc.id }" @click="selectDocType(doc)">
              <div class="doc-select-index">{{ idx + 1 }}.</div>
              <div class="doc-select-name">{{ doc.title }}</div>
            </div>

            <div v-show="formData.docType === doc.id">
              <div
                v-for="item in recommendTemplateList"
                :key="item.id"
                class="template-preview"
                :class="{ selected: formData.templateType === 'recommend' }"
                @click="selectTemplate('recommend', item)"
                :style="getRecommendStyle()"
              >
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px">
                  <div style="display: flex; align-items: center; gap: 6px">
                    <div style="font-size: 13px; font-weight: 600; color: #1f2937">{{ item.name }}</div>
                    <span style="padding: 2px 6px; background: #fef3c7; color: #92400e; border-radius: 4px; font-size: 10px; font-weight: 600">推荐</span>
                  </div>
                  <button
                    @click.stop="openTemplateEditor(item)"
                    style="padding: 4px; background: #9ca3af; color: white; border: none; border-radius: 3px; font-size: 10px; cursor: pointer"
                  >
                    <i class="fas fa-edit"></i>
                  </button>
                </div>
                <div style="font-size: 12px; color: #7b8794; margin-bottom: 8px">
                  {{ item.name }}
                </div>
                <div style="display: flex; gap: 6px; margin-bottom: 8px">
                  <span v-for="(tag, tIdx) in buildTags(item)" :key="tIdx" :style="getTagStyle(tIdx)">{{ tag }}</span>
                </div>
              </div>

              <div
                v-for="item in generalTemplateList"
                :key="item.id"
                class="template-preview"
                :class="{ selected: formData.templateType === 'general' }"
                @click="selectTemplate('general', item)"
                :style="getGeneralStyle()"
              >
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px">
                  <div style="display: flex; align-items: center; gap: 6px">
                    <div style="font-size: 13px; font-weight: 600; color: #1f2937">{{ item.name }}</div>
                    <span style="padding: 2px 6px; background: #f3f4f6; color: #6b7280; border-radius: 4px; font-size: 10px; font-weight: 600">通用</span>
                  </div>
                  <button
                    @click.stop="openTemplateEditor(item)"
                    style="padding: 4px; background: #9ca3af; color: white; border: none; border-radius: 3px; font-size: 10px; cursor: pointer"
                  >
                    <i class="fas fa-edit"></i>
                  </button>
                </div>
                <div style="font-size: 12px; color: #7b8794; margin-bottom: 8px">
                  {{ item.name }}
                </div>
                <div style="display: flex; gap: 6px; margin-bottom: 8px">
                  <span v-for="(tag, tIdx) in buildTags(item)" :key="tIdx" :style="getTagStyle(tIdx)">{{ tag }}</span>
                </div>
              </div>

              <button class="btn-lg btn-lg-primary" style="margin-top: 12px; display: block; margin-left: auto" @click="handleNext">下一步</button>
            </div>
          </div>

          <div v-show="!formData.docType && taskNameList.length > 0" style="margin-top: 16px; display: flex; justify-content: flex-end">
            <button class="btn-lg btn-lg-primary" @click="handleNext">下一步</button>
          </div>
        </div>
      </div>
    </div>

    <div v-show="pageFlag === '2'" class="confirm-modal show"><TemplateConfig ref="templateConfigRef" @save="successTemp" @close="closeModal" /></div>
    <RecordHistory ref="recordHistoryRef" v-show="pageFlag === '3'" @close="pageFlag = '1'" />
  </div>
</template>

<script>
import TemplateConfig from './TemplateConfig.vue';
import RecordHistory from '../comp/RecordHistory.vue';

export default {
  name: 'PatientInfoStep',
  components: {
    TemplateConfig,
    RecordHistory,
  },
  props: {
    value: {
      type: Object,
      default: () => ({}),
    },
  },
  data() {
    return {
      pageFlag: '1',
      formData: {
        bah: '',
        xm: '',
        department_id: '',
        disease_id: '',
        xb: '',
        nl: '',
        docType: '',
        docName: '',
        templateType: 'recommend',
      },
      rules: {
        bah: [{ required: true, message: '请输入病案号', trigger: 'blur' }],
        xm: [{ required: true, message: '请输入姓名', trigger: 'blur' }],
        department_id: [{ required: true, message: '请选择科室', trigger: 'change' }],
      },
      staffLoginInfo: JSON.parse(localStorage.getItem('staffLoginInfo')),
      taskNameList: [],
      templateList: [],
      departmentList: [],
      diseaseList: [],
      recommendTemplateList: [],
      generalTemplateList: [],
      currentTemplate: null,
    };
  },
  watch: {
    value: {
      handler(val) {
        if (val) this.formData = { ...this.formData, ...val };
      },
      deep: true,
      immediate: true,
    },
  },
  mounted() {
    this.getTaskNameList();
    this.geteDeptsList();
    this.getDiseasesList();
  },
  methods: {
    storageGet(key) {
      try {
        return JSON.parse(localStorage.getItem(key)) || {};
      } catch (e) {
        return {};
      }
    },
    resetForm() {
      this.formData = {
        bah: '',
        xm: '',
        department_id: '',
        disease_id: '',
        xb: '',
        nl: '',
        docType: '',
        docName: '',
        templateType: 'recommend',
      };
      this.$refs.formRef.clearValidate();
    },
    getTaskNameList() {
      this.$axios2.get('/big_model/get_task_name', {}).then(res => {
        if (res.code == 200) {
          this.taskNameList = res.data || [];
        }
      });
    },
    getCustomTemplateList(templateId) {
      const params = {
        big_model_template_id: templateId,
        department_id: this.formData.department_id,
        disease_id: this.formData.disease_id,
        staff_code: this.staffLoginInfo.staff_code,
        page: 1,
        page_size: 100,
      };
      return new Promise(resolve => {
        this.$axios2.post(`/big_model/custom_template_list`, params).then(res => {
          if (res.code == 200) {
            const list = res.data?.list || [];
            this.recommendTemplateList = list.length > 0 ? [list[0]] : [];
            this.generalTemplateList = list.slice(1);
          } else {
            this.recommendTemplateList = [];
            this.generalTemplateList = [];
          }
          resolve();
        });
      });
    },
    successTemp() {
      this.closeModal();
      this.getCustomTemplateList(this.currentTemplate.big_model_template_id);
    },
    geteDeptsList() {
      this.$axios2
        .get('/big_model/get_custom_template_departments', {
          params: { status: 1, name: '' },
        })
        .then(res => {
          if (res.code == 200) this.departmentList = res.data || [];
        });
    },
    getDiseasesList() {
      this.$axios2
        .get('/big_model/get_custom_template_diseases', {
          params: { status: 1, name: '' },
        })
        .then(res => {
          if (res.code == 200) this.diseaseList = res.data || [];
        });
    },
    async selectDocType(doc) {
      this.formData.docType = doc.id;
      this.formData.docName = doc.name;
      this.formData.templateType = 'recommend';
      await this.getCustomTemplateList(doc.id);

      if (this.recommendTemplateList.length > 0) {
        this.currentTemplate = this.recommendTemplateList[0];
      } else if (this.generalTemplateList.length > 0) {
        this.currentTemplate = this.generalTemplateList[0];
      } else {
        this.currentTemplate = null;
      }
      this.$emit('update:value', this.formData);
    },
    selectTemplate(type, item) {
      this.formData.templateType = type;
      this.currentTemplate = item;
    },
    buildTags(item) {
      const tags = [];
      if (item.document_type) tags.push(item.document_type);
      if (item.disease_name) tags.push(item.disease_name);
      if (item.department_name) tags.push(item.department_name);
      return tags;
    },
    getTagStyle(idx) {
      const colors = [
        { bg: '#dbeafe', color: '#1e40af' },
        { bg: '#d1fae5', color: '#065f46' },
        { bg: '#e0e7ff', color: '#4f46e5' },
      ];
      const c = colors[idx % colors.length];
      return `padding:2px 6px; background:${c.bg}; color:${c.color}; border-radius:4px; font-size:10px; font-weight:600;`;
    },
    getRecommendStyle() {
      return this.formData.templateType === 'recommend'
        ? 'background:#eff6ff; border:2px solid #3b82f6; border-radius:8px; padding:12px; margin-top:8px; cursor:pointer; transition:all 0.2s ease;'
        : 'background:#f0f9ff; border:1px solid #e2e8f0; border-radius:8px; padding:12px; margin-top:8px; cursor:pointer; transition:all 0.2s ease;';
    },
    getGeneralStyle() {
      return this.formData.templateType === 'general'
        ? 'background:#f0fdf4; border:2px solid #10b981; border-radius:8px; padding:12px; margin-top:8px; cursor:pointer; transition:all 0.2s ease;'
        : 'background:#fafafa; border:1px solid #e5e7eb; border-radius:8px; padding:12px; margin-top:8px; cursor:pointer; transition:all 0.2s ease;';
    },
    openTemplateEditor(item) {
      this.pageFlag = '2';
      this.$nextTick(() => {
        this.$refs.templateConfigRef.initData(this.formData, item, this.taskNameList, this.departmentList, this.diseaseList);
      });
    },
    closeModal() {
      this.pageFlag = '1';
    },
    showHistory() {
      this.pageFlag = '3';
      this.$refs.recordHistoryRef.initData(this.formData);
    },
    validate() {
      return new Promise(resolve => {
        this.$refs.formRef.validate(valid => {
          if (!valid) return resolve(false);
          if (!this.formData.docType) {
            this.$message.warning('请选择文书类型');
            return resolve(false);
          }
          if (!this.currentTemplate) {
            this.$message.warning('暂无可用模板，无法进行下一步');
            return resolve(false);
          }

          resolve(true);
        });
      });
    },
    async handleNext() {
      const valid = await this.validate();
      console.log('this.currentTemplate', this.currentTemplate);
      if (valid) {
        this.$emit('next', {
          ...this.formData,
          template: this.currentTemplate,
        });

        this.$emit('publicData', this.formData, this.currentTemplate);
      }
    },
  },
};
</script>

<style scoped lang="scss">
::v-deep .el-form-item__label {
  text-align: left;
  margin-bottom: -6px;
  font-weight: 600;
  color: #4b5563;
}
::v-deep .el-form-item {
  margin-bottom: 0;
}
.step-content {
  width: 100%;
  height: 100%;
}
.confirm-modal {
  position: fixed;
  top: 0;
  left: 0;
  width: 100vw;
  height: 100vh;
  background: rgba(0, 0, 0, 0.5);
  visibility: hidden;
  align-items: center;
  justify-content: center;
}
.confirm-modal.show {
  display: flex;
  visibility: visible;
}

#step1Content {
  .form-group {
    margin-bottom: 0;
  }

  .pill {
    height: 24px;
    padding: 0 9px;
    border-radius: 6px;
    border: 1px solid transparent;
    font-size: 12px;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    white-space: nowrap;
  }
  .pill-purple {
    color: #fff;
    background: #6f42ef;
  }
  .pill-ring {
    height: 22px;
    padding: 0 8px;
    border-radius: 999px;
    border: 1px solid #dfe5ec;
    background: transparent;
    color: #8b95a5;
    font-size: 12px;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    white-space: nowrap;
  }

  .card-head {
    display: flex;
    align-items: center;
    gap: 6px;
    padding-right: 120px;
    margin-bottom: 12px;
    padding-top: 4px;
  }
  .card-head.space {
    justify-content: space-between;
    padding-right: 0;
  }
  .card-head-left {
    display: flex;
    align-items: center;
    gap: 6px;
    min-width: 0;
  }
  .form-label {
    font-size: 13px;
    margin-bottom: 4px;
    font-weight: 600;
  }
  .required {
    color: red;
    margin-right: 2px;
  }
  .form-label {
    display: flex;
    align-items: center;
    gap: 4px;
    font-size: 14px;
    font-weight: 600;
    color: #4b5563;
    margin-bottom: 8px;
  }
  .form-label .required {
    color: red;
    font-size: 14px;
  }

  .doc-select-item {
    padding: 8px 12px;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    display: flex;
    gap: 8px;
    cursor: pointer;
  }
  .doc-select-item.selected {
    background: #eff6ff;
    border-color: #3b82f6;
  }
  .template-preview.selected {
    border-width: 2px !important;
  }

  .btn {
    min-width: 52px;
    height: 26px;
    padding: 0 10px;
    border-radius: 13px;
    border: 1px solid transparent;
    font-size: 11px;
    font-weight: 700;
    cursor: pointer;
    outline: none;
  }
  .btn-light {
    background: #f3f5f7;
    color: #4b5563;
    border-color: #e1e7ef;
  }
  .btn-lg {
    padding: 8px 16px;
    border-radius: 6px;
    border: none;
    cursor: pointer;
  }
  .btn-lg-primary {
    background: #1677ff;
    color: #fff;
  }
}
</style>