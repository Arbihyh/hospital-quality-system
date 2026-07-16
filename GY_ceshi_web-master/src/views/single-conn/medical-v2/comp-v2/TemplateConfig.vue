<template>
  <div class="template-config__container">
    <!-- 头部 -->
    <div class="template-config__header">
      <div>
        <h1 class="template-config__header-title">病历文书模板配置</h1>
      </div>
      <div class="template-config__doc-badge">{{ docTypeText }}</div>
    </div>

    <div class="template-config__tab-nav">
      <button class="template-config__tab-item" :class="{ 'is-active': activeTab === 'base' }" @click="switchTab('base')">基础</button>
      <button class="template-config__tab-item" :class="{ 'is-active': activeTab === 'preview' }" @click="switchTab('preview')">预览</button>
    </div>

    <div class="template-config__content">
      <!-- 基础配置 -->
      <section class="template-config__panel" :class="{ 'is-active': activeTab === 'base' }">
        <div class="template-config__card">
          <div class="template-config__card-head">基础配置</div>

          <el-form ref="baseForm" :model="baseForm" :rules="baseRules" label-position="top" style="margin: 0">
            <!-- 文书类型 接口下拉 -->
            <el-form-item label="文书类型" prop="docType" style="margin-bottom: 12px">
              <el-select v-model="baseForm.docType" placeholder="请选择文书类型" clearable style="width: 100%">
                <el-option v-for="item in taskNameList" :key="item.id" :label="item.name" :value="item.id" />
              </el-select>
            </el-form-item>

            <el-form-item label="模版名称" prop="templateName" style="margin-bottom: 12px">
              <el-input v-model="baseForm.templateName" placeholder="请输入模版名称" />
            </el-form-item>

            <!-- 科室 接口下拉 -->
            <el-form-item label="适用科室" prop="department_id" style="margin-bottom: 12px">
              <el-select v-model="baseForm.department_id" placeholder="请选择科室" filterable clearable style="width: 100%">
                <el-option v-for="item in deptList" :key="item.id" :label="item.name" :value="item.id" />
              </el-select>
            </el-form-item>

            <!-- 病种 接口下拉 -->
            <el-form-item label="适用病种" style="margin-bottom: 0">
              <el-select v-model="baseForm.disease_id" placeholder="请选择病种" filterable clearable style="width: 100%">
                <el-option v-for="item in diseaseList" :key="item.id" :label="item.name" :value="item.id" />
              </el-select>
            </el-form-item>
          </el-form>
        </div>
      </section>

      <!-- 预览 -->
      <section class="template-config__panel" :class="{ 'is-active': activeTab === 'preview' }">
        <div class="template-config__card">
          <div class="template-config__card-head">最终模板预览</div>
          <textarea v-model="content" ref="genOutputRef" class="gen-textarea" :readonly="!isEditable"></textarea>
        </div>
      </section>
    </div>

    <div class="template-config__footer">
      <div class="template-config__footer-btns">
        <button class="template-config__btn template-config__btn--primary" v-if="activeTab === 'preview'" @click="openEditMode">编辑</button>
        <div style="display: flex; gap: 10px; margin-left: auto">
          <button class="template-config__btn template-config__btn--secondary" @click="closeModal1">取消</button>
          <button class="template-config__btn template-config__btn--secondary" @click="resetAll">重置</button>
          <button class="template-config__btn template-config__btn--primary" @click="saveTemplate">保存模板</button>
        </div>
      </div>
    </div>

    <!-- 新增/编辑弹窗 -->
    <div class="template-config__modal-mask" v-if="modalVisible" :class="{ 'is-show': modalVisible }">
      <div class="template-config__modal">
        <div class="template-config__modal-header">
          <span>新增模板项</span>
          <button class="template-config__modal-close" @click="closeModal2">×</button>
        </div>
        <div class="template-config__modal-body">
          <div class="template-config__form-row">
            <label class="template-config__form-label">标题</label>
            <input v-model="editForm.title" class="template-config__form-control" placeholder="例如：主诉" />
          </div>
          <div class="template-config__form-row">
            <label class="template-config__form-label">内容规则</label>
            <textarea v-model="editForm.rule" class="template-config__form-control" placeholder="例如：不超过20字" />
          </div>
          <div class="template-config__form-row">
            <label class="template-config__form-label">示例内容</label>
            <textarea v-model="editForm.example" class="template-config__form-control" placeholder="例如：发热、咳嗽3天" />
          </div>
          <label class="template-config__checkbox-row">
            <input v-model="editForm.required" type="checkbox" />
            <span>该模板项为必填</span>
          </label>
        </div>
        <div class="template-config__modal-footer">
          <button class="template-config__btn template-config__btn--secondary" @click="closeModal2">取消</button>
          <button class="template-config__btn template-config__btn--primary" @click="saveModal">保存</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'TemplateConfig',
  data() {
    return {
      activeTab: 'base',
      isEditable: false,
      baseForm: {
        docType: '',
        templateName: '',
        department_id: '',
        disease_id: '',
      },
      baseRules: {
        docType: [{ required: true, message: '请选择文书类型', trigger: 'change' }],
        templateName: [{ required: true, message: '请输入模版名称', trigger: 'blur' }],
        department_id: [{ required: true, message: '请选择适用科室', trigger: 'change' }],
      },
      taskNameList: [],
      deptList: [],
      diseaseList: [],
      itemList: [],
      modalVisible: false,
      editIndex: -1,
      editForm: { title: '', rule: '', example: '', required: false },
      content: '',
      tempItem: {},
      staffLoginInfo: localStorage.getItem('staffLoginInfo'),
    };
  },
  computed: {
    docTypeText() {
      const target = this.taskNameList.find(t => t.id === this.baseForm.docType);
      return target ? target.name : '未选择';
    },
  },
  mounted() {
    // this.getTaskNameList();
  },
  methods: {
    initData(form, item, taskNames, departments, diseases) {
      this.$nextTick(() => {
        this.tempItem = item;
        this.taskNameList = taskNames || [];
        this.deptList = departments || [];
        this.diseaseList = diseases || [];

        this.baseForm.docType = item.big_model_template_id || '';
        this.baseForm.templateName = item?.name || '';
        this.baseForm.department_id = item.department_id || '';
        this.baseForm.disease_id = item.disease_id || '';

        this.content = item?.content || '';
        this.activeTab = 'base';
      });
    },

    switchTab(tab) {
      if (this.activeTab === 'base' && tab !== 'base') {
        this.$refs.baseForm.validate(valid => {
          if (valid) this.activeTab = tab;
          else this.$message.warning('请完善基础配置');
        });
      } else {
        this.activeTab = tab;
      }
    },
    openEditMode() {
      this.isEditable = true;
    },
    openAddModal() {
      this.editForm = { title: '', rule: '', example: '', required: false };
      this.editIndex = -1;
      this.modalVisible = true;
    },
    editItem(idx) {
      this.editForm = { ...this.itemList[idx] };
      this.editIndex = idx;
      this.modalVisible = true;
    },
    closeModal1() {
      this.$emit('close');
    },
    closeModal2() {
      this.modalVisible = false;
    },
    saveModal() {
      const item = { ...this.editForm };
      this.editIndex === -1 ? this.itemList.push(item) : this.itemList.splice(this.editIndex, 1, item);
      this.closeModal2();
    },
    deleteItem(idx) {
      this.itemList.splice(idx, 1);
    },
    resetAll() {
      this.baseForm = {
        docType: '',
        templateName: '',
        department_id: '',
        disease_id: '',
      };
      this.activeTab = 'base';
      this.content = '';
      this.itemList = [];
    },
    saveTemplate() {
      this.$refs.baseForm.validate(valid => {
        if (!valid) {
          this.$message.warning('请完善基础配置');
          return;
        }

        const params = {
          id: this.tempItem.id,
          name: this.baseForm.templateName,
          content: this.content,
          document_type: this.baseForm.docType,
          department_id: this.baseForm.department_id,
          disease_id: this.baseForm.disease_id,
          staff_code: this.staffLoginInfo.staff_code,
          staff_name: this.staffLoginInfo.staff_name,
        };

        this.$axios2.post('/big_model/edit_custom_template', params).then(res => {
          this.$emit('save');
          this.isEditable = false;
          this.$message.success('保存成功');
        });
      });
    },
  },
};
</script>

<style lang="scss" scoped>
$primary-color: #1b64b0;
$danger-color: #dc2626;
$gray-light: #f3f4f6;
$gray-border: #e2e8f0;
$gray-text: #6b7280;
$text-dark: #374151;

@mixin flex-center {
  display: flex;
  align-items: center;
  justify-content: center;
}
@mixin flex-between {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.gen-textarea {
  width: 100%;
  border: 1px solid #d8dee7;
  border-radius: 8px;
  background: #fff;
  padding: 16px;
  font-size: 14px;
  color: #1f2937;
  outline: none;
  min-height: 560px;
  resize: vertical;
  line-height: 1.6;
  overflow: auto;
  white-space: pre-wrap;
  word-break: break-word;
}

.template-config {
  &__container {
    width: 90%;
    height: 90%;
    background: #fff;
    box-shadow: 0 12px 30px rgba(15, 23, 42, 0.16);
    display: flex;
    flex-direction: column;
    border: 1px solid $gray-border;
    overflow: hidden;
  }

  &__header {
    height: 58px;
    padding: 10px 14px;
    background: linear-gradient(135deg, $primary-color, #2f80ed);
    color: #fff;
    @include flex-between;
    flex-shrink: 0;

    &-title {
      font-size: 18px;
      margin: 0;
    }
  }

  &__doc-badge {
    font-size: 12px;
    border: 1px solid rgba(255, 255, 255, 0.35);
    background: rgba(255, 255, 255, 0.18);
    border-radius: 999px;
    padding: 5px 8px;
  }

  &__tab-nav {
    display: flex;
    border-bottom: 1px solid $gray-border;
  }
  &__tab-item {
    flex: 1;
    padding: 10px 16px;
    background: none;
    border: none;
    cursor: pointer;
    text-align: center;
    transition: all 0.2s;

    &.is-active {
      color: $primary-color;
      font-weight: bold;
      border-bottom: 2px solid $primary-color;
    }
  }

  &__content {
    padding: 16px;
    flex: 1;
    overflow-y: auto;
  }
  &__panel {
    display: none;
    &.is-active {
      display: block;
    }
  }

  &__card {
    border: 1px solid $gray-border;
    border-radius: 8px;
    padding: 14px;
  }
  &__card-head {
    font-weight: bold;
    margin-bottom: 12px;
    @include flex-between;
  }

  &__btn {
    padding: 4px 10px;
    border-radius: 4px;
    font-size: 12px;
    cursor: pointer;
    border: none;

    &--primary {
      background: $primary-color;
      color: #fff;
    }
    &--secondary {
      background: $gray-light;
      color: $text-dark;
    }
    &--danger {
      background: $danger-color;
      color: #fff;
    }
  }

  &__footer {
    padding: 14px 16px;
    border-top: 1px solid $gray-border;
    &-btns {
      display: flex;
      align-items: center;
      gap: 10px;
    }
  }

  &__modal-mask {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    display: none;
    @include flex-center;
    z-index: 999;

    &.is-show {
      display: flex;
    }
  }
  &__modal {
    background: #fff;
    width: 460px;
    border-radius: 8px;
    overflow: hidden;
  }
  &__modal-header {
    padding: 12px 14px;
    border-bottom: 1px solid $gray-border;
    @include flex-between;
  }
  &__modal-close {
    background: none;
    border: none;
    font-size: 20px;
    cursor: pointer;
  }
  &__modal-body {
    padding: 14px;
  }
  &__modal-footer {
    padding: 12px 14px;
    border-top: 1px solid $gray-border;
    display: flex;
    justify-content: flex-end;
    gap: 10px;
  }
}

.template-item {
  &__list {
    margin-top: 8px;
  }
  &__empty-tip {
    font-size: 12px;
    color: $gray-text;
    padding: 10px 0;
  }
  &__card {
    border: 1px solid $gray-border;
    border-radius: 6px;
    padding: 10px 12px;
    margin-bottom: 8px;
  }
  &__head {
    @include flex-between;
    margin-bottom: 6px;
  }
  &__title-wrap {
    font-weight: 600;
    font-size: 14px;
    display: flex;
    align-items: center;
    gap: 6px;
  }
  &__sort {
    width: 22px;
    height: 22px;
    line-height: 22px;
    text-align: center;
    border-radius: 6px;
    color: #fff;
    background: $primary-color;
    font-size: 12px;
    flex-shrink: 0;
  }
  &__tag {
    font-size: 11px;
    padding: 2px 6px;
    border-radius: 4px;
    background: $gray-light;
    color: $gray-text;

    &.is-required {
      background: #fef2f2;
      color: $danger-color;
    }
  }
  &__format {
    display: block;
    font-size: 13px;
    line-height: 1.6;
    background: #f8fafc;
    border: 1px dashed #cbd5e1;
    border-radius: 9px;
    padding: 8px;
    color: #0f172a;
    margin: 6px 0 10px;
    word-break: break-all;
    white-space: pre-wrap;
    overflow-wrap: break-word;
  }
  &__format-label {
    color: $primary-color;
    font-weight: bold;
  }
  &__example {
    font-size: 12px;
    color: $gray-text;
    margin-bottom: 8px;
  }
  &__btn-group {
    display: flex;
    gap: 8px;
  }
}
</style>