<template>
  <div class="page-container">
    <!-- 登录页 -->
    <div v-if="isShowLoginPage">
      <LoginCheck @login-success="handleLoginSuccess" />
    </div>

    <div v-if="isShowGenEditorPage" class="content-container">
      <div class="qc-card" style="margin-bottom: 0">
        <div class="step-container">
          <div class="step-wizard" id="stepWizard">
            <div class="step-item" @click="goToStep(1)" style="cursor: pointer">
              <div
                class="step-dot"
                :class="{ active: currentStep === 1, done: currentStep > 1 }"
                data-step="1"
              >{{ currentStep > 1 ? '✓' : 1 }}</div>
              <span class="step-label">患者信息</span>
            </div>

            <div class="step-line" id="stepLine1" :class="{ done: currentStep > 1 }"></div>

            <div class="step-item" @click="goToStep(2)" style="cursor: pointer">
              <div
                class="step-dot"
                :class="{ active: currentStep === 2, done: currentStep > 2 }"
                data-step="2"
              >{{ currentStep > 2 ? '✓' : 2 }}</div>
              <span class="step-label">输入病史</span>
            </div>

            <div class="step-line" id="stepLine2" :class="{ done: currentStep > 2 }"></div>

            <div class="step-item" @click="goToStep(3)" style="cursor: pointer">
              <div
                class="step-dot"
                :class="{ active: currentStep === 3, done: currentStep > 3 }"
                data-step="3"
              >3</div>
              <span class="step-label">文书预览</span>
            </div>
          </div>
        </div>
      </div>

      <!-- 步骤内容区域 -->
      <div class="step-content" :class="{ active: currentStep === 1 }">
        <PatientInfoStep
          ref="patientInfoStepRef"
          :value="patientForm"
          @publicData="publicData"
          @next="nextStep"
          @show-history="showHistory"
          @open-template="openTemplateEditor"
        />
      </div>
      <div class="step-content" :class="{ active: currentStep === 2 }">
        <MedicalHistoryInput
          ref="medicalHistoryInputRef"
          :temp-row="tempRow"
          :search-data="searchData"
          @next="nextStep"
          @go-step="goToStep"
          @simulate-voice="simulateVoiceInput"
          @clear-input="clearDoctorInput"
        />
      </div>
      <div class="step-content" :class="{ active: currentStep === 3 }">
        <DocumentPreview
          ref="docPreviewRef"
          :temp-row="tempRow"
          :search-data="searchData"
          :content-obj="nextStepData"
          @feedback="handleFeedback"
          @copy="copyDocument"
          @save="saveDocument"
          @write-back="writeBackDocument"
          @go-step="goToStep"
          @new-patient="handleNewPatient"
          @next-doc="handleNextDoc"
        />
      </div>

      <div class="bottom-bar">
        <div class="preview-step__btn-group">
          <button class="btn-lg btn-lg-default" @click="backStep">上一步</button>
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
      </div>
    </div>
  </div>
</template>

<script>
import LoginCheck from './‌LoginCheck.vue';
import PatientInfoStep from './PatientInfoStep.vue';
import MedicalHistoryInput from './MedicalHistoryInput.vue';
import DocumentPreview from './DocumentPreview.vue';

export default {
  name: 'GenEditorV2',
  components: {
    LoginCheck,
    PatientInfoStep,
    MedicalHistoryInput,
    DocumentPreview,
  },
  data() {
    return {
      pageFlag: localStorage.getItem('staffLoginInfo') == '' || localStorage.getItem('staffLoginInfo') == null ? '1' : '2',
      currentStep: 1,
      content: '',
      gen_data: '',
      currentTime: '',
      patientForm: {},
      loginCheckData: {
        staff_code: '',
        staff_name: '',
        department: '',
      },
      nextStepData: {},
      searchData: {},
      tempRow: {},
    };
  },
  watch: {
    currentStep(val) {
      if (val === 3) {
        this.$nextTick(() => {
          this.$refs.docPreviewRef?.showLoadingDialog();
        });
      }
    },
  },
  computed: {
    isShowLoginPage() {
      return this.pageFlag === '1';
    },
    isShowGenEditorPage() {
      return this.pageFlag === '2';
    },
  },
  created() {
    this.getCurrentTime();
  },
  mounted() {
    console.log('GenEditorV2 mounted', this.pageFlag);
  },
  methods: {
    getCurrentTime() {
      const now = new Date();
      const year = now.getFullYear();
      const month = String(now.getMonth() + 1).padStart(2, '0');
      const day = String(now.getDate()).padStart(2, '0');
      const hour = String(now.getHours()).padStart(2, '0');
      const min = String(now.getMinutes()).padStart(2, '0');
      const sec = String(now.getSeconds()).padStart(2, '0');

      this.currentTime = `${year}-${month}-${day} ${hour}:${min}:${sec}`;
    },
    setPageFlag() {
      this.pageFlag = '2';
    },
    // 登录成功
    handleLoginSuccess(data) {
      this.pageFlag = '2';
      this.loginCheckData = data;
      this.$message.success({
        message: '登录成功,欢迎：' + data.staff_name,
        center: true,
      });
      this.$emit('loginSuccess', data);
    },

    handleNewPatient() {
      this.currentStep = 1;
      this.$refs.patientInfoStepRef.resetForm();
    },
    handleNextDoc() {
      this.currentStep = 1;
      this.$refs.patientInfoStepRef.resetForm();
    },
    backStep(){
      this.currentStep -= 1;
      if(this.currentStep === 0){
        this.currentStep = 1;
      }

      
    },

    showLogin() {
      this.pageFlag = '1';
    },

    publicData(searchData, item) {
      this.searchData = searchData;
      this.tempRow = item;
    },

    nextStep(data) {
      // this.gen_data = data.generate_date;
      // this.content = data.content;
      this.nextStepData = data;
      if (this.currentStep < 3) {
        this.currentStep++;
      }
      // if(this.currentStep === 2){
      //   this.$refs.medicalHistoryInputRef.clearDoctorInput();
      // }
    },

    prevStep() {
      if (this.currentStep > 1) {
        this.currentStep--;
      }
    },

    goToStep(step) {
      if (step <= this.currentStep) {
        this.currentStep = step;
      }
    },

    showToast(msg, type) {
      console.log(msg);
    },
  },
};
</script>

<style scoped lang="scss">
.step-container {
  padding: 8px 12px 12px;
  border-bottom: 1px solid #e2e8f0;
  margin-bottom: 12px;
  // padding-bottom: 170px; 
}
.bottom-bar {
  position: fixed;
  left: 24px; 
  right: 24px; 
  bottom: 0; 
  z-index: 999; 
  display: flex;
  justify-content: space-between;
  align-items: center;
  background: #fff;
  border-top: 1px solid #e5eaf1;
  height: 50px;
  flex-shrink: 0;
  // padding: 0 20px;

  .preview-step__btn-group {
    display: flex;
    gap: 8px;

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
  }
}
.step-wizard {
  display: flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 0;
}
.step-item {
  display: flex;
  align-items: center;
  gap: 12px;
}
.step-dot {
  width: 20px;
  height: 20px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 11px;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.25s ease;
  border: 2px solid #d8dee7;
  background: #fff;
  color: #8b95a5;
}
.step-dot.active {
  border-color: #1b64b0;
  background: #1b64b0;
  color: #fff;
  box-shadow: 0 0 0 4px rgba(27, 100, 176, 0.15);
}
.step-dot.done {
  border-color: #0f7b6c;
  background: #0f7b6c;
  color: #fff;
}
.step-line {
  width: 40px;
  height: 2px;
  background: #d8dee7;
  transition: background 0.25s ease;
  margin: 0 10px;
}
.step-line.done {
  background: #0f7b6c;
}
.step-label {
  font-size: 13px;
  color: var(--text-3);
  white-space: nowrap;
  font-weight: 600;
}
.step-dot.active + .step-label {
  color: #1b64b0;
}
.step-dot.done + .step-label {
  color: #0f7b6c;
}

.step-content {
  display: none;
  // min-height: 300px;
  // padding: 20px 0;
  // border-top: 1px solid #f0f0f0;
  // border-bottom: 1px solid #f0f0f0;
  margin-bottom: 10px;
}
.step-content.active {
  display: block;
}

.page-container {
  background: #f5f7fa;
}
.content-container {
  padding: 24px;
  border: 1px solid #e5eaf1;
  border-radius: 12px;
  box-shadow: 0 2px 8px rgba(31, 41, 55, 0.04);
  background: #fff;
}
.qc-card {
  margin-bottom: 0;
}

.step-buttons {
  display: flex;
  justify-content: center;
  gap: 16px;
  margin-top: 20px;
  button {
    padding: 8px 24px;
    border: 1px solid #d9d9d9;
    border-radius: 6px;
    background: #fff;
    cursor: pointer;
    font-size: 14px;
  }
  .next-btn {
    background: #1677ff;
    color: #fff;
    border-color: #1677ff;
  }
}
</style>