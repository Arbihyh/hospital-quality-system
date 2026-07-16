<template>
  <!-- 改用 ref 引用 DOM，比 id 更可靠，避免随机 id 冲突 -->
  <div class="chart-container" ref="echartsDom" :style="{ height: height || '300px', width: '100%' }"></div>
</template>

<script>
// 直接导入 echarts 模块，与 Overview 保持一致
import * as echarts from 'echarts';

export default {
  name: 'EchartsWrap',
  props: {
    // 图表配置项
    option: {
      type: Object,
      required: true
    },
    // 图表高度（保留原有配置）
    height: {
      type: String,
      default: '300px'
    }
  },
  data() {
    return {
      chartInstance: null // 存储 echarts 实例
    };
  },
  watch: {
    // 监听配置项变化，更新图表（深度监听，与 Overview 保持一致）
    option: {
      deep: true,
      handler() {
        this.initChart();
      }
    }
  },
  mounted() {
    // 确保 DOM 完全挂载后初始化图表
    this.$nextTick(() => {
      this.initChart();
      // 窗口大小变化自适应
      window.addEventListener('resize', this.resizeChart);
    });
  },
  destroyed() {
    // 销毁图表实例，移除事件监听，防止内存泄漏
    window.removeEventListener('resize', this.resizeChart);
    this.destroyChart();
  },
  methods: {
    // 初始化图表（仿照 Overview 的实现逻辑）
    initChart() {
      // 先获取 DOM 元素（通过 ref 更可靠）
      const el = this.$refs.echartsDom;
      if (!el) return;

      // 先销毁旧实例，防止重复渲染
      if (this.chartInstance) {
        this.chartInstance.dispose();
      }

      // 创建新的 echarts 实例
      this.chartInstance = echarts.init(el);
      // 设置图表配置项
      this.chartInstance.setOption(this.option);
    },
    // 图表自适应窗口大小
    resizeChart() {
      if (this.chartInstance) {
        this.chartInstance.resize();
      }
    },
    // 销毁图表实例
    destroyChart() {
      if (this.chartInstance) {
        this.chartInstance.dispose();
        this.chartInstance = null;
      }
    }
  }
};
</script>

<style scoped>
.chart-container {
  width: 100%;
  box-sizing: border-box;
}
</style>