<template>
  <div ref="box" class="box" :class="{ nocopy: $route.meta.nocopy }">
    <CaseQualityBox :MED_REC_ID="this.$route.query.id" ref="CaseQualityBoxRef" :width="400" @onUpdate="getCaseQualityResults()" />
  </div>
</template>

<script>
// import CaseQualityBox from '../single-conn/medical-record/CaseQualityBox2.vue';
import CaseQualityBox from '../single-conn/medical-v2/index.vue';
export default {
  components: {
    CaseQualityBox,
  },
  data() {
    return {
      data: null,
      timer: null,
      intervalTimer: null,
      startTime: null, 
    };
  },
  // mounted() {
  //   if (this.$route.query.id) {
  //     const self = this;
  //     self.$refs.CaseQualityBoxRef.getTableData(true);
  //     // this.timer = setInterval(() => {
  //     //   self.$refs.CaseQualityBoxRef.getTableData(true);
  //     // }, 2000);
  //   }
  // },


   mounted() {
    if(this.$route.query.id) {
      const self = this
      self.$refs.CaseQualityBoxRef.getTableData(true);
      // 记录启动时间
      self.startTime = Date.now();
      // 设置5秒一次的定时器
      self.intervalTimer = setInterval(() => {
        const elapsedTime = Date.now() - self.startTime;
        if (elapsedTime > 20000) {
          clearInterval(self.intervalTimer);
          self.intervalTimer = null;
          console.log('已超过20秒，停止定时刷新');
          return;
        }
        self.$refs.CaseQualityBoxRef.getTableData(true);
      }, 5000);
    }
  },
  beforeDestroy() {
    clearInterval(this.timer);
    clearInterval(this.intervalTimer); 
  },
  methods: {},
};
</script>

<style lang="scss" scoped>
.app-main {
  flex: 1;
  display: flex;
  flex-direction: column;
  width: 100%;
  // height: 100% !important;
}
</style>