<!-- 会诊记录单 -->
<template>
  <div class="page-container">
    <!-- 页眉 -->
    <PageHeader :hospitalName="systemSetting.web_name" pageTitle="会诊记录单" :basicInfoList="basicInfoList" />
    <div>
      <div class="content">
        <!-- 会诊类型 -->
        <div class="content-section">
          <div class="content-info">
            <KeyValueCheckbox keyName="会诊类型" :options="hobbyOptions" :selectedValue="selectedValues" @change="handleChange" />
          </div>
        </div>
        <!-- 会诊目的 患者病情及诊疗情况 -->
        <div class="content-section">
          <div class="content-info">
            <div v-for="(item, index) in checkContentList" :key="index">
              <div class="top20">
                <H3Title :title="item.title" keyAlign="left" />
              </div>
              <div class="content-info-item">
                <keyvalueParagraph :valueName="item.value" />
              </div>
            </div>
          </div>
        </div>
        <!-- 邀请会诊科室 -->
        <div class="content-section">
          <div class="top20">
            <keyvalueParagraph textIndent="no-indent" keyName="邀请会诊科室" :valueName="YQKSDM" />
          </div>
        </div>

        <!--申请会诊科室 申请医师 申请时间 -->
        <div class="content-section">
          <div class="apply-for-info top20">
            <keyvalueParagraph textIndent="no-indent" v-for="(item, index) in applyForBasicList" :key="index" :keyName="item.title" :valueName="item.value" />
          </div>
        </div>
      </div>

      <div class="page-footer">
        <!-- 会诊意见 -->
        <div class="signature">
          <H3Title title="会诊意见：" keyAlign="left" />
          <div class="content-info-item">
            <keyvalueParagraph :valueName="ideaOption" />
          </div>
        </div>
        <!-- 会诊基础信息 -->
        <div class="apply-for-info">
          <keyvalueParagraph textIndent="no-indent" v-for="(item, index) in consultationForBasicList" :key="index" :keyName="item.title" :valueName="item.value" />
        </div>
        <!-- 签名信息 -->
        <div class="signature-info">
          <keyvalueParagraph textIndent="no-indent" v-for="(item, index) in signatureInfoList" :key="index" :keyName="item.title" :valueName="item.value" />
        </div>
        <!-- <div>
          <keyvalueParagraph textIndent="no-indent" keyName="院外会诊医师所在医疗机构名称" :valueName="patientInfo.a" />
        </div> -->
      </div>
    </div>
  </div>
</template>

<script>
import PageHeader from '../comp/page-header.vue';
import KeyValueCheckbox from '../comp/key-value-checkbox.vue';
import KeyValueItem from '../comp/key-value-item.vue';
import keyvalueParagraph from '../comp/key-value-paragraph.vue';
import H3Title from '../comp/h3-title.vue';
import { mapState } from 'vuex';

export default {
  name: 'ConsultationRecordForm',
  components: {
    PageHeader,
    KeyValueItem,
    H3Title,
    keyvalueParagraph,
    KeyValueCheckbox,
  },
  props: {
    patientInfo: {
      type: Object,
      default: () => ({}),
    },
    basicInfoList: {
      type: Array,
      default: () => [],
    },
  },
  data() {
    return {
      hobbyOptions: [
        { label: '普通会诊', value: '1' },
        { label: '急会诊', value: '2' },
        // { label: '请外院会诊', value: '3' },
        // { label: '远程会诊', value: '4' },
      ],
    };
  },
  computed: {
    ...mapState({
      systemSetting: state => state.app.systemSetting,
    }),
    selectedValues() {
      let JJBZ = String(this.patientInfo['JJBZ'] || '').trim();
      if (JJBZ === '0' || JJBZ === '1') {
        JJBZ = '1';
      }
      return [JJBZ];
    },

    // 检查内容
    checkContentList() {
      const HZMD = this.patientInfo['HZMD'] || '';
      const BQZL = this.patientInfo['BQZL'] || '';
      return [
        { title: '会诊目的：', value: HZMD },
        { title: '患者病情及诊疗情况：', value: BQZL },
      ];
    },
    YQKSDM() {
      return this.patientInfo['YQDX'] || '';
    },
    applyForBasicList() {
      const SQKS = this.patientInfo['SQKS'] || '';
      const SQYS = this.patientInfo['SQYS'] || '';
      const SQSJ = this.patientInfo['SQSJ'] || '';
      return [
        { title: '申请会诊科室', value: SQKS },
        { title: '申请医师', value: SQYS },
        { title: '申请时间', value: SQSJ },
      ];
    },
    ideaOption() {
      return this.patientInfo.HZYJ || '';
    },

    consultationForBasicList() {
      const KSDM = this.patientInfo['KSDM'] || '';
      const SSYS = this.patientInfo['SSYS'] || '';
      const HZSJ = this.patientInfo['HZSJ'] || '';
      return [
        { title: '会诊科室', value: KSDM },
        { title: '会诊医师', value: SSYS },
        { title: '会诊到达时间', value: HZSJ },
      ];
    },
    // 签名信息
    signatureInfoList() {
      const QMYS = this.patientInfo['QMYS'] || '';
      const QMSJ = this.patientInfo['QMSJ'] || '';
      return [
        { title: '签名医师', value: QMYS },
        { title: '首次签名时间', value: QMSJ },
      ];
    },
  },
  mounted() {
    console.log('ideaOption', this.ideaOption);
  },
  methods: {
    handleChange(val) {
      console.log('handleChange', val);
    },
  },
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

.page-container .content .content-section .content-info .content-info-item {
  margin-top: 5px;
}

.page-container .page-footer {
  margin-top: 40px;
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

.signature-info {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 1px 10px;
}

.apply-for-info {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 1px 10px;
}

.top20 {
  margin-top: 15px;
}
</style>