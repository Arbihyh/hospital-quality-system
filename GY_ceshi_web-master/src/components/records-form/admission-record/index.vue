<!-- 入院记录 -->
<template>
  <div class="page-container">
    <!-- 页眉 -->
    <PageHeader :hospitalName="systemSetting.web_name" pageTitle="入院记录" :basicInfoList="basicInfoList" />
    <div>
      <!-- 基础信息 -->
      <div class="basic-info-section">
        <div class="basic-info">
          <KeyValueItem v-for="(item, index) in patientGeneralInfoList" :key="index" :keyName="item.title" :valueName="item.value" class="info-item" />
        </div>
      </div>

      <div class="content">
        <!-- 记录内容 -->
        <div class="content-section">
          <div class="content-info">
            <keyvalueParagraph :indentValue="true" v-for="(item, index) in recordContentList" :key="index" :keyName="item.title" :valueName="item.value" />
          </div>
        </div>
        <!-- 检查内容 -->
        <div class="content-section">
          <div class="content-info">
            <div v-for="(item, index) in checkContentList" :key="index">
              <div class="content-info-item">
                <H3Title :title="item.title" />
              </div>
              <div class="content-info-item">
                <keyvalueParagraph :indentValue="true" :valueName="item.value" />
              </div>
            </div>
          </div>
        </div>
        <!-- 诊断内容 -->
        <div class="content-section">
          <div class="content-info">
            <keyvalueParagraph :indentValue="true" v-for="(item, index) in diagnosisContentList" :key="index" :keyName="item.title" :valueName="item.value" />
          </div>
        </div>
      </div>

      <div class="page-footer">
        <!-- 医师签名 -->
        <div class="signature">
          <KeyValueItem keyName="签名医师" :valueName="signaturePhysician" />
        </div>
        <!-- 时间信息 -->
        <div class="time-info">
          <KeyValueItem v-for="(item, index) in timeInfoList" :key="index" :keyName="item.title" :valueName="item.value" />
        </div>
        <!-- 书写医师 -->
        <div>
          <KeyValueItem keyName="书写医师" :valueName="writingPhysician" />
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import PageHeader from '../comp/page-header.vue';
import KeyValueItem from '../comp/key-value-item.vue';
import keyvalueParagraph from '../comp/key-value-paragraph.vue';
import H3Title from '../comp/h3-title.vue';
import { mapState } from 'vuex';

export default {
  name: 'AdmissionRecordNew',
  components: {
    PageHeader,
    KeyValueItem,
    H3Title,
    keyvalueParagraph,
  },
  props: {
    patientInfo: {
      type: Object,
      default: () => ({}),
    },
  },

  computed: {
    ...mapState({
      systemSetting: state => state.app.systemSetting,
    }),
    basicInfoList() {
      const list = this.patientInfo['基本信息'] || [];
      return list.filter(item => item.title).sort((a, b) => (a.sort || 999) - (b.sort || 999));
    },
    patientGeneralInfoList() {
      const generalObj = this.patientInfo['一般项目'] || {};
      const list = Object.values(generalObj);
      return list.filter(item => item.title).sort((a, b) => (a.sort || 999) - (b.sort || 999));
    },
    // 记录内容
    recordContentList() {
      const list = this.patientInfo['记录内容'] || [];
      console.log('recordContentList', list);
      return list.filter(item => item.title).sort((a, b) => (a.sort || 999) - (b.sort || 999));
    },
    // 检查内容
    checkContentList() {
      const list = this.patientInfo['检查内容'] || [];
      return list.filter(item => item.title).sort((a, b) => (a.sort || 999) - (b.sort || 999));
    },
    // 诊断内容
    diagnosisContentList() {
      const signatureKeywords = ['诊断', '初步诊断'];
      const list = this.patientInfo['诊断内容'] || [];
      return list
        .filter(item => {
          if (!item.title) return false;
          const title = item.title.toLowerCase();
          return signatureKeywords.some(keyword => title.includes(keyword.toLowerCase()));
        })
        .sort((a, b) => (a.sort || 999) - (b.sort || 999));
    },
    signaturePhysician() {
      return this.patientInfo['doctor_name'] || '';
    },

    // 书写医师信息
    writingPhysician() {
      return this.patientInfo['sxys_name'] || '';
    },

    // 时间信息
    timeInfoList() {
      const CJSJ = this.patientInfo['CJSJ'] || [];
      const ZXSJ = this.patientInfo['ZXSJ'] || [];
      const WCSJ = this.patientInfo['WCSJ'] || [];
      const YWSJ = this.patientInfo['YWSJ'] || [];
      return [
        { title: '创建时间', value: CJSJ },
        { title: '首次签名时间', value: ZXSJ },
        { title: '业务时间', value: YWSJ },
        { title: '末次签名时间', value: WCSJ },
      ];
    },
  },
  mounted() {},
};
</script>

<style scoped>
.page-container {
  margin: 0 30px;
  min-height: 100vh;
  position: relative;
  padding: 20px;
  box-sizing: border-box;
}

.page-container .basic-info-section {
  margin-top: 20px;
}

.page-container .basic-info-section .basic-info {
  width: 700px;
  margin: 0 auto;
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 1px 20px;
}

@media (max-width: 768px) {
  .page-container .basic-info-section .basic-info {
    width: 100%;
    max-width: 400px;
    grid-template-columns: 1fr;
  }
}

.page-container .basic-info-section .basic-info .info-item {
  display: flex;
  align-items: center;
  padding: 8px 0;
  border-radius: 6px;
  min-height: 40px;
}

.page-container .content {
  margin-top: 40px;
}

.page-container .content .content-section .content-info {
  margin-top: 20px;
}

.page-container .content .content-section .content-info .content-info-item {
  margin-top: 10px;
}

.page-container .page-footer {
  margin-top: 80px;
}

.info-item :deep(.key-value-item) {
  display: flex;
  align-items: center;
  width: 100%;
}

.info-item :deep(.key) {
  font-weight: bold;
  margin-right: 8px;
  color: #333;
  width: 80px;
  flex-shrink: 0;
  text-align: left;
}

.info-item :deep(.value) {
  color: #666;
  flex: 1;
  text-align: left;
}

/* 设置时间样式 */
.time-info {
  margin-top: 25px;
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 1px 10px;
}
</style>