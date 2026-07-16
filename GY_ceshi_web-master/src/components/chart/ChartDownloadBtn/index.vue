<!-- 图表下载按钮 -->
<template>
  <span class="span-btn" @click="handleDownload">
    <svg t="1765250968962" class="icon" viewBox="0 0 1024 1024" version="1.1" xmlns="http://www.w3.org/2000/svg" p-id="5503" width="25" height="25">
      <path
        d="M896 672c-17.066667 0-32 14.933333-32 32v128c0 6.4-4.266667 10.666667-10.666667 10.666667H170.666667c-6.4 0-10.666667-4.266667-10.666667-10.666667v-128c0-17.066667-14.933333-32-32-32s-32 14.933333-32 32v128c0 40.533333 34.133333 74.666667 74.666667 74.666667h682.666666c40.533333 0 74.666667-34.133333 74.666667-74.666667v-128c0-17.066667-14.933333-32-32-32z"
        fill="#666666" p-id="5504"></path>
      <path
        d="M488.533333 727.466667c6.4 6.4 14.933333 8.533333 23.466667 8.533333s17.066667-2.133333 23.466667-8.533333l213.333333-213.333334c12.8-12.8 12.8-32 0-44.8-12.8-12.8-32-12.8-44.8 0l-157.866667 157.866667V170.666667c0-17.066667-14.933333-32-32-32s-34.133333 14.933333-34.133333 32v456.533333L322.133333 469.333333c-12.8-12.8-32-12.8-44.8 0-12.8 12.8-12.8 32 0 44.8l211.2 213.333334z"
        fill="#666666" p-id="5505"></path>
    </svg>
  </span>
</template>

<script>
import { download } from '@/utils/echarts-utils';
export default {
  name: 'ChartDownloadBtn',
  props: {
    chartInstance: {
      type: Object,
      required: true,
      validator: val => val !== null && val !== undefined 
    },
    
    chartName: {
      type: String,
      required: true
    }
  },
  methods: {
    async handleDownload() {
      try {
        if (!this.chartInstance) {
          this.$message.warning('图表加载中，请稍等片刻再下载');
          return;
        }
        await download(this.chartInstance, this.chartName, {
          type: 'png',
          pixelRatio: 2, 
          backgroundColor: '#fff', 
        });
        this.$message.success(`${this.chartName}下载成功`);
      } catch (error) {
        // this.$message.error(error.message || `${this.chartName}下载失败`);
      }
    }
  }
};
</script>

<style lang="scss" scoped>
.span-btn {
  cursor: pointer;
  display: inline-block;
  user-select: none; 
}
.icon {
  vertical-align: middle;
}
</style>