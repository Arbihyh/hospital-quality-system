<template>
  <div class="preview-step" id="step3Content">
    <div class="preview-step__header card-head space">
      <div class="card-head-left">
        <span class="pill pill-purple">文书预览</span>
      </div>
      <div class="preview-step__tip">医生审核后保存 / 回填</div>
    </div>

    <div class="doc-gen-header" style="margin-bottom: 12px">
      <div class="doc-gen-info" id="docGenTags">
        <div style="display: flex; gap: 12px; margin-bottom: 8px">
          <span class="doc-gen-tag">文书：{{tempRow.document_type}}</span>
          <span class="doc-gen-tag doc-gen-tag-gray">患者：{{searchData.bah}}</span>
        </div>
        <div style="display: flex; gap: 12px">
          <span class="doc-gen-tag doc-gen-tag-orange">时间：{{genTime}}</span>
        </div>
      </div>
    </div>

    <div class="preview-step__content-area">
      <textarea
        v-model="content"
        ref="genOutputRef"
        class="gen-textarea"
        readonly
        style="min-height: 260px; height: 360px"
      ></textarea>
    </div>

    <div class="preview-step__actions">
      <div class="preview-step__action-row">
        <button class="btn btn-light" @click="handleFeedback">反馈</button>
        <div class="preview-step__btn-group">
          <button class="btn btn-light" @click="copyDocument">复制</button>
          <button class="btn btn-light" @click="saveDocument">保存</button>
          <button class="btn btn-primary" @click="writeBackDocument">回填</button>
        </div>
      </div>

      <!-- <div class="preview-step__action-row">
        <div class="preview-step__btn-group">
          <button class="btn-lg btn-lg-default" @click="goToStep(2)">上一步</button>
        </div>
        <div class="preview-step__btn-group">
          <button class="btn-lg btn-lg-new-patient" @click="handleNewPatient">
            <i class="fas fa-user-plus" style="margin-right: 4px"></i>
            新建患者
          </button>
          <button class="btn-lg btn-lg-success" @click="handleNextDoc">
            <i class="fas fa-plus" style="margin-right: 4px"></i>
            下一文书
          </button>
        </div>
      </div> -->

      
    </div>

    <div v-if="showGenerating" class="gen-overlay">
      <div class="gen-dialog">
        <div class="gen-spinner-wrapper">
          <div class="gen-spinner"></div>
        </div>
        <div class="gen-text">正在生成病历，请稍后……</div>
      </div>
    </div>

    <FeedbackModal :visible="showFeedback" @close="closeFeedback" @submit="submitFeedback" />
  </div>
</template>

<script>
import FeedbackModal from './FeedbackModal.vue';
export default {
  name: 'DocumentPreview',
  components: {
    FeedbackModal,
  },
  props: {
    tempRow: {
      type: Object,
      default: () => ({
        big_model_template_id: 0,
        content: '',
        department: '',
        department_id: '',
        department_name: '',
        disease_id: '',
        disease_name: '',
        document_type: '',
        id: '',
        is_customized: '',
        name: '',
        public_template_id: '',
        source_template_id: '',
        staff_code: '',
        staff_name: '',
        status: '',
        template_scope: '',
      }),
    },
    searchData: {
      type: Object,
      default: () => ({
        bah: '',
        xm: '',
        department_id: '',
        disease_id: '',
        xb: '',
        nl: '',
        docType: '',
        docName: '',
      }),
    },
    genTime: {
      type: String,
      default: '2026-05-04 22:39:52',
    },
    contentObj: {
      type: Object,
      default: () => ({}),
    },
  },
  data() {
    return {
      genTime: '',
      content: '',
      showFeedback: false,
      showGenerating: false,
      staffLoginInfo: JSON.parse(localStorage.getItem('staffLoginInfo')) || {},
    };
  },
  methods: {
    handleFeedback() {
      this.showFeedback = true;
    },

    showLoadingDialog() {
      console.log('showLoadingDialog');
      this.showGenerating = true;
      this.$axios2.post('/big_model/generate_medical_record', this.contentObj).then(res => {
        this.genTime = res.data.generate_date;
        this.content = res.data.content;
        this.showGenerating = false;
      });
    },
    copyDocument() {
      const text = this.content?.trim();
      if (!text) {
        this.$message.warning('暂无内容可复制');
        return;
      }

      const textarea = this.$refs.genOutputRef;
      if (!textarea) return;

      textarea.focus();
      textarea.setSelectionRange(0, textarea.value.length);
      navigator.clipboard
        .writeText(text)
        .then(() => {
          this.$message.success('复制成功！');
        })
        .catch(() => {
          this.$message.error('复制失败，请手动复制');
        });
    },

    saveDocument() {
      this.$axios2
        .post('/big_model/save_medical_record', {
          zyh: this.searchData.bah,
          title: this.tempRow.name,
          content: this.content,
          code: this.staffLoginInfo.staff_code,

          tempate_id: this.searchData.docType,
          custom_template_id: this.tempRow.id,
          xm: this.searchData.xm,
          department_id: this.searchData.department_id,
          disease_id: this.searchData.disease_id,
          xb: this.searchData.xb,
          nl: this.searchData.nl,
        })
        .then(() => {
          this.$message.success('保存成功');
          window.focus();
        });
    },
    writeBackDocument() {
      // this.$emit('write-back');
    },
    goToStep(step) {
      this.$emit('go-step', step);
    },
    handleNewPatient() {
      this.$emit('new-patient');
    },
    handleNextDoc() {
      this.$emit('next-doc');
    },

    closeFeedback() {
      this.showFeedback = false;
    },
    submitFeedback(data) {
      console.log('反馈内容', data);
      this.showFeedback = false;
    },
  },
};
</script>

<style lang="scss" scoped>
$text-1: #1f2937;
$text-2: #6b7280;
$text-3: #9ca3af;
$border-color: #e5e7eb;
$pill-purple: #a855f7;
$tag-orange: #f97316;
$tag-gray: #6b7280;
$tag-bg: #f3f4f6;
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

.doc-gen-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  margin-bottom: 10px;
  padding-bottom: 10px;
  border-bottom: 1px solid #edf2f7;
}
.doc-gen-info {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}
.doc-gen-tag {
  height: 22px;
  padding: 0 8px;
  border-radius: 999px;
  border: 1px solid #cfe0ff;
  background: #cfe0ff;
  color: #2f6bff;
  font-size: 11px;
  font-weight: 600;
  display: inline-flex;
  align-items: center;
  gap: 4px;
}
.doc-gen-tag-gray {
  border-color: #cbd5e1;
  background: #f1f5f9;
  color: #475569;
}
.doc-gen-tag-orange {
  border-color: #fb923c;
  background: #fffbeb;
  color: #c2410c;
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
  box-shadow: 0 1px 0 rgba(31, 41, 55, 0.02);
  min-height: 110px;
  resize: vertical;
  font-family: 'Microsoft YaHei', 'PingFang SC', Arial, sans-serif;
  line-height: 1.6;
  overflow: auto;
  white-space: pre-wrap;
  word-break: break-word;
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
.btn-primary {
  background: #1b64b0;
  color: #fff;
  border-color: #1b64b0;
}

.btn-lg {
  min-width: 100px;
  height: 36px;
  padding: 0 10px;
  border-radius: 18px;
  font-size: 12px;
  font-weight: 700;
  cursor: pointer;
  outline: none;
  border: 1px solid transparent;
  transition: all 0.2s ease;
}
.btn-lg-primary {
  background: linear-gradient(135deg, #1b64b0, #155290);
  color: #fff;
  border-color: #1b64b0;
}
.btn-lg-primary:hover {
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(27, 100, 176, 0.3);
}
.btn-lg-default {
  background: #fff;
  color: #4b5563;
  border-color: #d8dee7;
}
.btn-lg-default:hover {
  border-color: #cfe0ff;
  color: #1b64b0;
}
.btn-lg-new-patient {
  background: #eff6ff;
  color: #1e40af;
  border-color: #bfdbfe;
}
.btn-lg-new-patient:hover {
  background: #dbeafe;
  border-color: #93c5fd;
}
.btn-lg-success {
  background: linear-gradient(135deg, #0f7b6c, #0c6458);
  color: #fff;
  border-color: #0f7b6c;
}
.btn-lg-success:hover {
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(15, 123, 108, 0.3);
}

.preview-step {
  &__header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 12px;
  }

  &__tip {
    font-size: 12px;
    color: $text-3;
  }

  &__info-header {
    margin-bottom: 12px;
  }

  &__info-tags {
    width: 100%;
  }

  &__info-row {
    display: flex;
    gap: 12px;
    margin-bottom: 8px;
  }

  &__tag {
    font-size: 12px;
    padding: 4px 8px;
    border-radius: 4px;
    background: $tag-bg;
    color: $text-1;

    &.doc-gen-tag-orange {
      background: #fff7ed;
      color: $tag-orange;
    }

    &.doc-gen-tag-gray {
      background: #f9fafb;
      color: $tag-gray;
    }
  }

  &__content-area {
    margin-bottom: 16px;
  }

  &__textarea {
    min-height: 260px;
    height: 360px;
    width: 100%;
    box-sizing: border-box;
  }

  &__actions {
    display: flex;
    flex-direction: column;
    gap: 12px;
  }

  &__action-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
  }

  &__btn-group {
    display: flex;
    gap: 8px;
  }
}

.gen-overlay {
  position: fixed;
  left: 0;
  top: 0;
  width: 100vw;
  height: 100vh;
  background: rgba(0, 0, 0, 0.45);
  z-index: 99999;
  display: flex;
  align-items: center;
  justify-content: center;
}

.gen-dialog {
  width: 260px;
  height: 160px;
  background: #fff;
  border-radius: 16px;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 20px;
  box-shadow: 0 8px 30px rgba(0, 0, 0, 0.2);
}

.gen-spinner-wrapper {
  width: 50px;
  height: 50px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.gen-spinner {
  width: 46px;
  height: 46px;
  border: 4px solid #e6edff;
  border-top-color: #2f6bff;
  border-radius: 50%;
  animation: gen-rotate 0.7s linear infinite;
}

.gen-text {
  font-size: 15px;
  color: #333;
  font-weight: 500;
}

@keyframes gen-rotate {
  to {
    transform: rotate(360deg);
  }
}
</style>