<template>
  <div class="input-result-panel">
    <div class="gen-card nav-card">
      <div class="nav-left">
        <span class="pill pill-purple">已选</span>
        <span class="pill pill-outline">{{ title }}</span>
      </div>
      <button @click="$emit('back')" class="back-btn">返回</button>
    </div>

    <div class="gen-card">
      <div class="card-head card-head-row">
        <div class="left-label">
          <span class="pill pill-purple">输入</span>
          <!-- <span class="pill pill-outline">点击输入，生成病历</span> -->
        </div>

        <div class="right-buttons">
          <button class="btn" :class="inputMode === 'voice' ? 'btn-primary' : 'btn-light'" @click="switchToVoiceInput">
            <svg class="icon" viewBox="0 0 1024 1024" width="14" height="14">
              <path
                d="M512 512a153.6 153.6 0 0 0 153.6-153.6V153.6a153.6 153.6 0 0 0-307.2 0v204.8a153.6 153.6 0 0 0 153.6 153.6z"
                :fill="inputMode === 'voice' ? '#ffffff' : '#4D5562'"
              ></path>
              <path
                d="M870.4 358.4V256h-102.4v102.4A256 256 0 0 1 256 358.4V256H153.6v102.4a358.4 358.4 0 0 0 307.2 354.304V921.6H256v102.4h512v-102.4H512V712.704A358.4 358.4 0 0 0 870.4 358.4z"
                :fill="inputMode === 'voice' ? '#ffffff' : '#4D5562'"
              ></path>
            </svg>
            语音
          </button>
          <button class="btn" :class="inputMode === 'text' ? 'btn-primary' : 'btn-light'" @click="switchToTextInput">
            <svg class="icon" viewBox="0 0 1024 1024" width="14" height="14">
              <path
                d="M170.666667 213.333333v597.333334h682.666666V213.333333H170.666667zM128 128h768a42.666667 42.666667 0 0 1 42.666667 42.666667v682.666666a42.666667 42.666667 0 0 1-42.666667 42.666667H128a42.666667 42.666667 0 0 1-42.666667-42.666667V170.666667a42.666667 42.666667 0 0 1 42.666667-42.666667z m128 170.666667h85.333333v85.333333H256V298.666667z m0 170.666666h85.333333v85.333334H256v-85.333334z m0 170.666667h512v85.333333H256v-85.333333z m213.333333-170.666667h85.333334v85.333334h-85.333334v-85.333334z m0-170.666666h85.333334v85.333333h-85.333334V298.666667z m213.333334 0h85.333333v85.333333h-85.333333V298.666667z m0 170.666666h85.333333v85.333334h-85.333333v-85.333334z"
                :fill="inputMode === 'text' ? '#ffffff' : '#4D5562'"
              ></path>
            </svg>
            文字
          </button>
        </div>
      </div>

      <!-- ====================== 语音输入面板 ====================== -->
      <div v-if="inputMode === 'voice'" class="voice-input-panel">
        <div class="gen-label">
          <!-- <textarea v-model="voiceText" class="gen-textarea" placeholder=""></textarea> -->
          <!-- 语音输入 -->
        </div>
        <div class="voice-record-row">
          <div class="voice-left">
            <button class="voice-record-btn" :class="{ recording: isRecording }" :disabled="isTranscribing" @click="toggleRecording">
              <svg t="1776350076687" class="icon" viewBox="0 0 1024 1024" version="1.1" xmlns="http://www.w3.org/2000/svg" p-id="6363" width="20" height="20">
                <path
                  d="M512 704c106.04 0 192-85.96 192-192V192c0-106.04-85.96-192-192-192S320 85.96 320 192v320c0 106.04 85.96 192 192 192z m320-320h-32c-17.68 0-32 14.32-32 32v96c0 149.6-128.98 269.64-281.58 254.76C353.42 753.78 256 634.22 256 500.6V416c0-17.68-14.32-32-32-32H192c-17.68 0-32 14.32-32 32v80.32c0 179.28 127.94 339.1 304 363.38V928H352c-17.68 0-32 14.32-32 32v32c0 17.68 14.32 32 32 32h320c17.68 0 32-14.32 32-32v-32c0-17.68-14.32-32-32-32h-112v-67.54C731.42 836.94 864 689.8 864 512v-96c0-17.68-14.32-32-32-32z"
                  fill="#448EF7"
                  p-id="6364"
                ></path>
              </svg>
            </button>
            <div class="voice-info">
              <div class="voice-status" :style="{ color: voiceStatusColor }">
                {{ voiceStatusText }}
              </div>
              <div v-if="voiceStatusText != '录音完成'" class="wave-active">
                <div class="item"></div>
                <div class="item"></div>
                <div class="item"></div>
                <div class="item"></div>
                <div class="item"></div>
              </div>
            </div>
          </div>
          <div v-if="hasRecordContent && !isRecording" class="voice-controls">
            <button class="btn btn-light" @click="playRecording">
              <svg t="1776349048291" class="icon" viewBox="0 0 1024 1024" version="1.1" xmlns="http://www.w3.org/2000/svg" p-id="5258" width="16" height="16">
                <path
                  d="M739.2 528.64l-326.4 188.416a19.2 19.2 0 0 1-28.8-16.64V323.584a19.2 19.2 0 0 1 28.8-16.64l326.4 188.416a19.2 19.2 0 0 1 0 33.28zM960 512A448 448 0 1 1 64 512a448 448 0 0 1 896 0zM128 512a384 384 0 1 0 768 0A384 384 0 0 0 128 512z"
                  p-id="5259"
                ></path>
              </svg>
              播放录音
            </button>
          </div>
        </div>
        <div class="gen-label">
          <textarea v-model="voiceText" class="gen-textarea" placeholder="语音输入"></textarea>
        </div>
      </div>

      <!-- ====================== 文字输入面板 ====================== -->
      <div v-else class="gen-field">
        <textarea v-model="buildMedicalRecordData.content" class="gen-textarea" placeholder="请输入内容"></textarea>
      </div>

      <div class="gen-actions-input">
        <button @click="handleClear" class="btn btn-light">清空</button>
        <button @click="generate_medical_record()" class="btn btn-primary">生成</button>
      </div>
    </div>

    <div class="gen-card">
      <div class="card-head">
        <span class="pill pill-purple">结果</span>
        <span class="pill pill-outline">自动生成结果</span>
      </div>
      <div class="gen-field">
        <textarea v-model="generateMedicalRecordData.content" placeholder="点击“生成”后，病历会出现在这里" class="gen-textarea result"></textarea>
      </div>

      <div class="gen-actions">
        <button @click="openFeedback" class="btn btn-light">反馈</button>
        <div class="right-group">
          <button @click="copyMedicalRecord()" class="btn btn-light">复制</button>
          <button @click="save_medical_record()" class="btn btn-light">保存</button>
          <button @click="$emit('backfill')" class="btn btn-primary">回填</button>
        </div>
      </div>
    </div>

    <!-- 反馈弹窗 -->
    <el-dialog title="反馈 — 收集使用效果" :visible.sync="showFeedbackDialog" width="520px" append-to-body class="feedback-dialog">
      <el-form :model="feedback" label-width="80px" class="feedback-form">
        <el-form-item label="反馈人">
          <el-input v-model="feedback.user" placeholder="请输入姓名"></el-input>
        </el-form-item>
        <el-form-item label="反馈科室">
          <el-input v-model="feedback.dept" placeholder="请输入科室"></el-input>
        </el-form-item>
        <el-form-item label="使用体验">
          <el-radio-group v-model="feedback.experience">
            <el-radio label="满意">满意</el-radio>
            <el-radio label="一般">一般</el-radio>
            <el-radio label="不满意">不满意</el-radio>
          </el-radio-group>
        </el-form-item>
        <el-form-item label="优化建议">
          <el-input v-model="feedback.suggestion" type="textarea" rows="4" placeholder="请输入优化建议"></el-input>
        </el-form-item>
      </el-form>
      <div slot="footer" class="dialog-footer">
        <el-button @click="showFeedbackDialog = false">取消</el-button>
        <el-button type="primary" @click="submitFeedback">提交</el-button>
      </div>
    </el-dialog>
  </div>
</template>

<script>
export default {
  name: 'RecordInputResult',
  props: {
    taskId: [String, Number],
    title: String,
    inputValue: String,
    resultValue: String,
  },
  data() {
    return {
      showFeedbackDialog: false,
      feedback: {
        user: '',
        dept: '',
        experience: '',
        suggestion: '',
      },
      buildMedicalRecordData: {
        template_id: '',
        template_title: '',
        content: '',
        fields: [],
      },
      generateMedicalRecordData: {
        template_id: '',
        template_title: '',
        content: '',
        api_time: '',
      },

      // ====================== 语音/文字 模式 ======================
      inputMode: 'voice',
      isRecording: false,
      voiceStatusText: '点击开始录音',
      voiceStatusColor: '#595959',
      hasRecordContent: false,
      voiceText: '',

      mediaStream: null,
      mediaRecorder: null,
      audioChunks: [],
      audioBlob: null,
      isTranscribing: false,
    };
  },
  mounted() {
    this.build_medical_record_data();
  },
  methods: {
    openFeedback() {
      this.feedback = { user: '', dept: '', experience: '', suggestion: '' };
      this.showFeedbackDialog = true;
    },
    submitFeedback() {
      if (!this.feedback.user) return this.$message.warning('请输入反馈人');
      if (!this.feedback.dept) return this.$message.warning('请输入反馈科室');
      if (!this.feedback.experience) return this.$message.warning('请选择使用体验');
      this.$message.success('反馈成功');
      this.showFeedbackDialog = false;
    },
    copyMedicalRecord() {
      const text = this.generateMedicalRecordData.content || '';
      if (!navigator.clipboard) {
        const textarea = document.createElement('textarea');
        textarea.value = text;
        document.body.appendChild(textarea);
        textarea.select();
        document.execCommand('copy');
        document.body.removeChild(textarea);
        this.$message.success('复制成功');
        return;
      }
      navigator.clipboard
        .writeText(text)
        .then(() => this.$message.success('复制成功'))
        .catch(() => this.$message.error('复制失败'));
    },
    build_medical_record_data() {
      this.$axios2
        .post('/big_model/build_medical_record_data', {
          zyh: this.$route.query.id,
          tempate_id: this.taskId,
        })
        .then(res => {
          this.buildMedicalRecordData = res.data;
        });
    },
    generate_medical_record() {
      let params = {
        tempate_id: this.buildMedicalRecordData.template_id,
      };
      console.log(this.buildMedicalRecordData);
      console.log('this.inputMode', this.inputMode);
      if (this.inputMode === 'voice') {
        params.content = this.voiceText;
      }
      if (this.inputMode === 'text') {
        params.content = this.buildMedicalRecordData.content;
      }
      this.$axios2.post('/big_model/generate_medical_record', params).then(res => {
        this.generateMedicalRecordData = res.data;
      });
    },
    save_medical_record() {
      this.$axios2
        .post('/big_model/save_medical_record', {
          zyh: this.$route.query.id,
          title: this.generateMedicalRecordData.template_title,
          content: this.generateMedicalRecordData.content,
          code: '',
        })
        .then(() => {
          this.$message.success('保存成功');
          window.focus();
        });
    },

    switchToVoiceInput() {
      this.inputMode = 'voice';
    },
    switchToTextInput() {
      this.inputMode = 'text';
    },

    toggleRecording() {
      this.isRecording ? this.stopRecording() : this.startRecording();
    },

    async startRecording() {
      try {
        if (!this.mediaStream) {
          this.mediaStream = await navigator.mediaDevices.getUserMedia({ audio: true });
        }

        this.mediaRecorder = new MediaRecorder(this.mediaStream);
        this.audioChunks = [];
        this.mediaRecorder.ondataavailable = e => {
          if (e.data.size > 0) this.audioChunks.push(e.data);
        };
        this.mediaRecorder.onstop = () => {
          this.audioBlob = new Blob(this.audioChunks, { type: 'audio/wav' });
          this.sendAudioToASR();
        };
        this.mediaRecorder.start();

        this.isRecording = true;
        if (this.voiceStatusText == '录音完成' || this.voiceStatusText == '识别完成') {
          this.voiceStatusText = '继续录音...';
        } else {
          this.voiceStatusText = '正在聆听...';
        }

        this.voiceStatusColor = '#ff4d4f';
        this.hasRecordContent = true;
      } catch (err) {
        console.error('麦克风错误:', err);
        this.voiceStatusText = '请允许麦克风权限';
        this.voiceStatusColor = '#ff4d4f';
      }
    },

    stopRecording() {
      this.isRecording = false;
      this.voiceStatusText = '录音完成';
      this.voiceStatusColor = '#52c41a';

      if (this.mediaRecorder && this.mediaRecorder.state !== 'inactive') {
        this.mediaRecorder.stop();
      }
    },

    sendAudioToASR() {
      if (!this.audioBlob) return;

      // 开始识别
      this.isTranscribing = true;
      const formData = new FormData();
      this.voiceStatusText = '语音识别中...';
      this.voiceStatusColor = '#1890ff';
      formData.append('file', this.audioBlob, 'recording.wav');
      formData.append('language', 'zh');
      this.$axios2
        .post('/big_model/audio_transcription', formData)
        .then(res => {
          if (res.data?.text) {
            this.voiceText += res.data.text;
            // this.buildMedicalRecordData.content = this.voiceText;
            this.voiceStatusText = '识别完成';
            this.voiceStatusColor = '#52c41a';
          }
        })
        .catch(err => {
          console.error('语音转文字失败', err);
          this.voiceStatusText = '识别失败';
          this.voiceStatusColor = '#ff4d4f';
        })
        .finally(() => {
          this.isTranscribing = false;
        });
    },

    playRecording() {
      if (!this.audioBlob) return this.$message.info('暂无录音');
      const audio = new Audio(URL.createObjectURL(this.audioBlob));
      audio.play().catch(() => this.$message.error('播放失败'));
    },

    handleClear() {
      this.buildMedicalRecordData.content = '';
      this.voiceText = '';
      this.audioChunks = [];
      this.audioBlob = null;
      this.hasRecordContent = false;
      this.voiceStatusText = '点击开始录音';
      this.voiceStatusColor = '#595959';
      this.$message.success('已清空');
    },
  },
};
</script>
<style scoped>
.gen-card {
  background: #fff;
  border: 1px solid #e5eaf1;
  border-radius: 12px;
  padding: 14px;
  margin-bottom: 12px;
}
.nav-card {
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.nav-left {
  display: flex;
  align-items: center;
  gap: 10px;
}
.type-name {
  font-size: 14px;
  font-weight: bold;
  color: #333;
}
.back-btn {
  padding: 6px 12px;
  border: 1px solid #e5eaf1;
  border-radius: 6px;
  background: #fff;
  cursor: pointer;
}
.pill {
  height: 24px;
  padding: 0 10px;
  border-radius: 6px;
  font-size: 12px;
  font-weight: bold;
  display: inline-flex;
  align-items: center;
  margin-right: 6px;
}
.pill-purple {
  background: #6f42ef;
  color: #fff;
}
.pill-outline {
  background: #f7faff;
  color: #2f6bff;
  border: 1px solid #cfe0ff;
}

.gen-textarea {
  width: 100%;
  border: 1px solid #dcdfe6;
  border-radius: 8px;
  padding: 12px;
  font-size: 14px;
  min-height: 200px;
  resize: vertical;
  line-height: 1.6;
  box-sizing: border-box;
  transition: all 0.2s ease-in-out;
  outline: none;
}
.gen-textarea:focus {
  border-color: #2f6bff;
  box-shadow: 0 0 0 2px rgba(47, 107, 255, 0.15);
}
.gen-textarea.result {
  background: #fafbfc;
  color: #4b5563;
  cursor: default;
}

.gen-actions-input {
  display: flex;
  justify-content: flex-end;
  gap: 8px;
  margin-top: 10px;
}
.gen-actions {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-top: 10px;
}
.right-group {
  display: flex;
  gap: 8px;
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
  transition: all 0.2s ease;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 4px;
}
.btn-light {
  background: #f3f5f7;
  color: var(--text-2);
  border-color: #e1e7ef;
}
.btn-primary {
  background: #1e56cc;
  color: #fff;
  border-color: #1e56cc;
}
.btn-primary:hover {
  background: #2563eb;
}

.feedback-dialog {
  --el-dialog-width: 520px;
}
.feedback-form {
  padding: 10px 0;
}
.dialog-footer {
  text-align: right;
}

.card-head-row {
  display: flex;
  /* justify-content: space-between; */
  align-items: center;
  margin-bottom: 12px;
}
.left-label {
  display: flex;
  align-items: center;
  gap: 8px;
}
.right-buttons {
  display: flex;
  gap: 8px;
}

.voice-input-panel {
  margin-bottom: 12px;
}
.gen-label {
  font-size: 12px;
  color: #7b8794;
  margin-bottom: 6px;
}
.voice-record-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 10px 0;
}
.voice-left {
  display: flex;
  align-items: center;
  gap: 10px;
}
.voice-record-btn {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  border: 2px solid #1890ff;
  background: white;
  color: #1890ff;
  font-size: 18px;
  cursor: pointer;
  transition: all 0.3s ease;
}
.voice-record-btn.recording {
  border-color: #ff4d4f;
  color: #ff4d4f;
  animation: pulse 1.5s infinite;
}
.voice-info {
  display: flex;
  flex-direction: column;
  gap: 4px;
}
.voice-status {
  font-size: 12px;
}
.voice-controls {
  display: flex;
  gap: 6px;
}

.waveform {
  display: flex;
  gap: 2px;
  height: 16px;
  align-items: flex-end;
}
.wave-bar {
  width: 2px;
  background: #1890ff;
  border-radius: 1px;
  animation: wave 1s infinite ease-in-out;
}

/* 波形动画容器 - 垂直居中 */
.voice-info {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  justify-content: center;
  gap: 2px;
}

/* 正在录音：动画波形 */
.wave-active {
  display: flex;
  align-items: flex-end;
  gap: 2px;
  height: 16px;
}
.wave-active .item {
  width: 2px;
  background: #1890ff;
  border-radius: 1px;
  animation: voiceWave 0.8s infinite ease-in-out;
}
.wave-active .item:nth-child(1) {
  height: 8px;
  animation-delay: 0s;
}
.wave-active .item:nth-child(2) {
  height: 12px;
  animation-delay: 0.1s;
}
.wave-active .item:nth-child(3) {
  height: 10px;
  animation-delay: 0.2s;
}
.wave-active .item:nth-child(4) {
  height: 14px;
  animation-delay: 0.3s;
}
.wave-active .item:nth-child(5) {
  height: 11px;
  animation-delay: 0.4s;
}

.wave-idle {
  display: flex;
  align-items: flex-end;
  gap: 2px;
  height: 16px;
}
.wave-idle .item {
  width: 2px;
  background: #1890ff;
  border-radius: 1px;
}
.wave-idle .item:nth-child(1) {
  height: 8px;
}
.wave-idle .item:nth-child(2) {
  height: 12px;
}
.wave-idle .item:nth-child(3) {
  height: 10px;
}
.wave-idle .item:nth-child(4) {
  height: 14px;
}
.wave-idle .item:nth-child(5) {
  height: 11px;
}

@keyframes voiceWave {
  0% {
    transform: scaleY(0.4);
  }
  50% {
    transform: scaleY(1);
  }
  100% {
    transform: scaleY(0.4);
  }
}
</style>