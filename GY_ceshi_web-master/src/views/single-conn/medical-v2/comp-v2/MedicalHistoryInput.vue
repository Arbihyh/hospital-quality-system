<template>
  <div class="history-step" id="step2Content">
    <div class="history-step__header card-head space">
      <div class="card-head-left">
        <span class="history-step__pill pill pill-purple">输入病史</span>
      </div>
      <div class="history-step__doc-type">
        文书：
        <span>{{ tempRow.document_type }}</span>
      </div>
    </div>

    <div class="history-step__input-card">
      <div class="history-step__input-header">
        <div class="history-step__input-title">输入方式</div>
        <div class="history-step__input-desc">语音识别或手动输入</div>
      </div>

      <!-- 录音区域 -->
      <div class="history-step__voice-card" @click="handleVoiceClick">
        <div class="history-step__voice-icon" :style="iconStyle">
          <i :class="voiceIconClass"></i>
        </div>
        <div class="history-step__voice-status">{{ voiceStatusText }}</div>
        <div class="history-step__voice-hint">{{ voiceHintText }}</div>
        <div class="history-step__voice-progress-bar">
          <div class="history-step__voice-progress" :style="{ width: voiceProgress + '%' }"></div>
        </div>
        <div
          v-if="isRecording"
          class="history-step__record-time"
        >已录音：{{ formatTime(recordSeconds) }} / 最长15分钟</div>
      </div>

      <!-- 操作按钮 -->
      <div class="history-step__voice-actions">
        <button class="btn btn-light-red" @click="simulateVoiceInput">录音结束</button>
        <button class="btn btn-light" @click="clearDoctorInput">清空输入</button>
      </div>
    </div>

    <!-- 病史描述 -->
    <div class="history-step__desc-wrapper">
      <div class="history-step__desc-header">
        <div class="history-step__desc-title">病史描述</div>
      </div>
      <textarea
        v-model="doctorInput"
        class="history-step__textarea gen-textarea"
        placeholder="请输入患者病史描述..."
      />
    </div>

    <!-- 底部按钮 -->
    <div class="history-step__actions">
      <!-- <button class="btn-lg btn-lg-default" @click="goToStep(1)" style="width: 120px">上一步</button> -->
      <button class="btn-lg btn-lg-primary" @click="handleNext" style="width: 120px">一键生成病历</button>
    </div>

    <div v-if="isTranscribing" class="transcribe-overlay">
      <div class="transcribe-dialog">
        <div class="transcribe-spinner"></div>
        <div class="transcribe-text">音频转文字处理中，请稍候...</div>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'MedicalHistoryInput',
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
        mrn: '',
        name: '',
        department_id: '',
        disease_id: '',
        gender: '',
        age: '',
        docType: '',
        docName: '',
      }),
    },
  },
  data() {
    return {
      doctorInput: '',
      isRecording: false,
      isPaused: false,
      voiceProgress: 0,
      timer: null,
      recordTimer: null,
      recordSeconds: 0,
      maxRecordSeconds: 15 * 60, // 15分钟
      mediaStream: null,
      mediaRecorder: null,
      audioChunks: [],
      audioBlob: null,
      isTranscribing: false,
    };
  },
  computed: {
    iconStyle() {
      if (this.isRecording && !this.isPaused) {
        return { background: '#ef4444' };
      } else if (this.isRecording && this.isPaused) {
        return { background: '#f59e0b' };
      } else {
        return { background: 'rgb(59, 130, 246)' };
      }
    },
    voiceIconClass() {
      return this.isRecording && !this.isPaused ? 'fas fa-microphone-slash' : 'fas fa-microphone';
    },
    voiceStatusText() {
      if (!this.isRecording) return '点击开始录音';
      if (this.isPaused) return '录音已暂停';
      return '正在录音...';
    },
    voiceHintText() {
      return '点击此区域开始录音，再次点击暂停，单次录音最长 15 分钟';
    },
  },
  methods: {
    formatTime(seconds) {
      const m = String(Math.floor(seconds / 60)).padStart(2, '0');
      const s = String(seconds % 60).padStart(2, '0');
      return `${m}:${s}`;
    },

    handleVoiceClick() {
      if (!this.isRecording) {
        this.startVoiceRecording();
      } else if (this.isPaused) {
        this.resumeVoiceRecording();
      } else {
        this.pauseVoiceRecording();
      }
    },

    async startVoiceRecording() {
      this.isRecording = true;
      this.isPaused = false;
      this.voiceProgress = 0;
      this.recordSeconds = 0;
      this.audioChunks = [];

      this.$emit('toast', '录音已开始，单次最长 15 分钟', 'success');

      try {
        this.mediaStream = await navigator.mediaDevices.getUserMedia({ audio: true });
        this.mediaRecorder = new MediaRecorder(this.mediaStream);
        this.mediaRecorder.ondataavailable = e => {
          if (e.data.size > 0) this.audioChunks.push(e.data);
        };
        this.mediaRecorder.start();
      } catch (err) {
        console.error('麦克风错误:', err);
        this.$emit('toast', '录音失败：无法获取麦克风权限', 'error');
        this.isRecording = false;
        this.$message.warning('无法获取麦克风权限');
        return;
      }

      // 进度条按 15 分钟总长度计算
      this.timer = setInterval(() => {
        if (!this.isPaused) {
          this.voiceProgress = (this.recordSeconds / this.maxRecordSeconds) * 100;
        }
      }, 100);

      // 15 分钟超时自动停止
      this.recordTimer = setInterval(() => {
        if (!this.isPaused) {
          this.recordSeconds++;
          if (this.recordSeconds >= this.maxRecordSeconds) {
            this.$emit('toast', '已达到15分钟，自动结束录音', 'info');
            this.stopVoiceRecording().then(() => this.simulateVoiceInput());
          }
        }
      }, 1000);
    },

    pauseVoiceRecording() {
      this.isPaused = true;
      this.$emit('toast', '录音已暂停', 'info');
    },

    resumeVoiceRecording() {
      this.isPaused = false;
      this.$emit('toast', '录音已继续', 'success');
    },

    stopVoiceRecording() {
      return new Promise(resolve => {
        clearInterval(this.timer);
        clearInterval(this.recordTimer);
        this.timer = null;
        this.recordTimer = null;

        if (this.mediaRecorder && this.mediaRecorder.state !== 'inactive') {
          this.mediaRecorder.onstop = () => {
            this.audioBlob = new Blob(this.audioChunks, { type: 'audio/wav' });
            this.isRecording = false;
            this.isPaused = false;
            resolve(true);
          };
          this.mediaRecorder.stop();
        } else {
          this.isRecording = false;
          this.isPaused = false;
          resolve(true);
        }

        if (this.mediaStream) {
          this.mediaStream.getTracks().forEach(t => t.stop());
          this.mediaStream = null;
        }
      });
    },

    async simulateVoiceInput() {
      await this.stopVoiceRecording();

      if (!this.audioBlob || this.audioBlob.size === 0) {
        this.$emit('toast', '未检测到有效录音', 'error');
        return;
      }

      this.isTranscribing = true;
      const formData = new FormData();
      formData.append('file', this.audioBlob, 'recording.wav');
      formData.append('language', 'zh');

      this.$axios2
        .post('/big_model/audio_transcription', formData)
        .then(res => {
          if (res.data?.text) {
            this.doctorInput += res.data.text;
            this.$emit('toast', '语音识别完成', 'success');
          } else {
            this.$emit('toast', '未识别到语音内容', 'warning');
          }
        })
        .catch(err => {
          console.error('转换失败', err);
          this.$emit('toast', '语音转文字失败，请重试', 'error');
        })
        .finally(() => {
          this.isTranscribing = false;
        });
    },

    clearDoctorInput() {
      this.doctorInput = '';
      this.audioChunks = [];
      this.audioBlob = null;
      this.voiceProgress = 0;
      this.recordSeconds = 0;
      this.$emit('clear-input');
      this.$emit('toast', '输入已清空', 'info');
    },

    goToStep(step) {
      this.$emit('go-step', step);
    },

    async handleNext() {
      if (this.isRecording) {
        await this.stopVoiceRecording();
        await this.simulateVoiceInput();
      }

      if (!this.doctorInput.trim()) {
        this.$emit('toast', '请输入病史描述', 'error');
        return;
      }

      const params = {
        tempate_id: this.searchData.docType,
        custom_template_id: this.tempRow.id,
        bah: this.searchData.bah,
        xm: this.searchData.xm,
        department_id: this.searchData.department_id,
        disease_id: this.searchData.disease_id,
        xb: this.searchData.xb,
        nl: this.searchData.nl,
        content: this.doctorInput,
      };
      this.$emit('next', params);
    },

    beforeDestroy() {
      clearInterval(this.timer);
      clearInterval(this.recordTimer);
      if (this.mediaStream) {
        this.mediaStream.getTracks().forEach(t => t.stop());
      }
    },
  },
};
</script>

<style lang="scss" scoped>
$color-primary: rgb(59, 130, 246);
$color-border: #e2e8f0;
$color-bg-light: #f8fafc;
$text-1: #333;
$text-3: #999;

.gen-textarea {
  width: 100%;
  border: 1px solid #d8dee7;
  border-radius: 8px;
  background: #fff;
  padding: 16px;
  font-size: 14px;
  color: var(--text-1);
  outline: none;
  min-height: 110px;
  resize: vertical;
  font-family: 'Microsoft YaHei', 'PingFang SC', Arial, sans-serif;
  line-height: 1.6;
}
.btn-lg {
  min-width: 110px;
  height: 36px;
  padding: 0 10px;
  border-radius: 18px;
  font-size: 12px;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.2s ease;
}
.btn-lg-primary {
  background: linear-gradient(135deg, #1b64b0, #155290);
  color: #fff;
}
.btn-lg-default {
  background: #fff;
  color: #4b5563;
  border: 1px solid #d8dee7;
}
.btn {
  min-width: 52px;
  height: 26px;
  padding: 0 10px;
  border-radius: 13px;
  font-size: 11px;
  font-weight: 700;
  cursor: pointer;
}
.btn-light {
  background: #f3f5f7;
  color: #4b5563;
  border: 1px solid #e1e7ef;
}
.btn-light-red {
  background: #dd524c;
  color: #fff;
  border: 1px solid #e1e7ef;
}
.pill {
  height: 24px;
  padding: 0 9px;
  border-radius: 6px;
  font-size: 12px;
  font-weight: 700;
  display: inline-flex;
  align-items: center;
}
.pill-purple {
  color: #fff;
  background: #6f42ef;
}

.card-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 12px;
}
.history-step {
  &__header {
    display: flex;
    justify-content: space-between;
    align-items: center;
  }
  &__doc-type {
    font-size: 12px;
    color: $text-3;
  }
  &__input-card {
    background: $color-bg-light;
    border: 1px solid $color-border;
    border-radius: 12px;
    padding: 16px;
    margin-bottom: 16px;
  }
  &__input-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 12px;
  }
  &__input-title {
    font-size: 14px;
    font-weight: 600;
    color: $text-1;
  }
  &__input-desc {
    font-size: 12px;
    color: $text-3;
  }
  &__voice-card {
    background: #fff;
    border: 1px dashed #cbd5e1;
    border-radius: 10px;
    padding: 16px;
    text-align: center;
    margin-bottom: 12px;
    cursor: pointer;
  }
  &__voice-icon {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 8px;
    i {
      font-size: 16px;
    }
  }
  &__voice-status {
    font-size: 12px;
    font-weight: 600;
    color: $text-1;
  }
  &__voice-hint {
    font-size: 10px;
    color: $text-3;
    margin: 4px 0 8px;
  }
  &__voice-progress-bar {
    width: 100%;
    height: 3px;
    background: $color-border;
    border-radius: 2px;
    overflow: hidden;
  }
  &__voice-progress {
    height: 100%;
    background: rgb(59, 130, 246);
    transition: width 0.3s linear;
  }
  &__record-time {
    margin-top: 8px;
    font-size: 11px;
    color: #666;
  }
  &__voice-actions {
    display: flex;
    gap: 8px;
    justify-content: flex-end;
  }
  &__desc-wrapper {
    margin-bottom: 16px;
  }
  &__desc-title {
    font-size: 14px;
    font-weight: 600;
    margin-bottom: 8px;
  }
  &__textarea {
    min-height: 180px;
  }
  &__actions {
    display: flex;
    gap: 8px;
    justify-content: flex-end;
  }
}

.transcribe-overlay {
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
.transcribe-dialog {
  width: 260px;
  height: 160px;
  background: #fff;
  border-radius: 16px;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 18px;
  box-shadow: 0 8px 30px rgba(0, 0, 0, 0.2);
}
.transcribe-spinner {
  width: 50px;
  height: 50px;
  border: 4px solid #e6edff;
  border-top-color: #2f6bff;
  border-radius: 50%;
  animation: spin 0.7s linear infinite;
}
.transcribe-text {
  font-size: 15px;
  color: #333;
  font-weight: 500;
}
@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}
</style>