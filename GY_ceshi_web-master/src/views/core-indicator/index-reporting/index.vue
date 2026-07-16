<!-- 指标上报 -->
 <template>
  <div class="page-container">
    <div class="title">
      <H2Title title="医疗质量安全核心制度上报" fontSize="28px"></H2Title>
    </div>
    <div class="content">
      <TabBtnGroup :active-key="activeKey" :width="'100px'" :tab-list="tabBtnGroup" @tab-change="changeActiveKey" />
      <div class="content-step" v-if="activeKey === '1'">
        <StepProcess :current-step="currentStep" :step-list="stepList" :containerWidth="'(calc(60vw))'" @step-change="handleStepChange" />
      </div>
    </div>

    <!-- 上报数据 -->
    <div class="report-data" v-if="activeKey === '1'">
      <ReportTable></ReportTable>
    </div>
    <!-- 上报历史 -->
    <History v-if="activeKey === '2'"></History>
    <!-- 接口配置 -->
    <InterfaceConfig v-if="activeKey === '3'"></InterfaceConfig>
  </div>
</template>
 
 <script>
import H2Title from '@/components/records-form/comp/h2-title.vue';
import TabBtnGroup from '@/components/TabBtnGroup';
import StepProcess from '@/components/StepProcess';
import History from './history.vue';
import InterfaceConfig from './interface.vue';
import ReportTable from './reportTable.vue';

export default {
  name: 'IndexReport',
  components: {
    H2Title,
    TabBtnGroup,
    History,
    InterfaceConfig,
    StepProcess,
    ReportTable,
  },
  data() {
    return {
      activeKey: '1',
      currentStep: 1,
      tabBtnGroup: [
        { key: '1', label: '指标上报' },
        { key: '2', label: '上报历史' },
        { key: '3', label: '接口配置' },
      ],
      stepList: [{ title: '指标采集' }, { title: '人工复核' }, { title: '确认上报' }],
    };
  },
  mounted() {},
  methods: {
    changeActiveKey(key) {
      this.activeKey = key;
    },
    handleStepChange(clickStep) {
      console.log('点击的步骤：', clickStep);
      this.currentStep = clickStep;
    },
  },
};
</script>
 
<style lang="scss" scoped>
.page-container {
  margin: 0;
  padding: 18px;
  overflow: hidden;
  height: 100vh;
  background-color: rgba(255, 255, 255, 1);

  .title {
    margin: 34px 0px;
  }

  .content {
    margin: 0px 20px;

    .content-step {
      margin: 20px 0px;
    }
  }
}
</style>