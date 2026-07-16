<!-- 柱状图模块 -->
<template>
  <div ref="chartRef" :style="{ width: '100%', height: `${height}px` }"></div>
</template>

<script>
export default {
  name: 'EChartsColumnar',
  props: {
    chartData: {
      type: Object,
      required: true,
      default: () => ({
        xAxisData: [],
        seriesData: [],
      }),
    },
    height: {
      type: Number,
      default: 360,
    },
    unit: {
      type: String,
      default: '%',
    },
    barColor: {
      type: String,
      default: '#5087ec',
    },
  },
  data() {
    return {
      chartInstance: null,
      resizeObserver: null,
    };
  },
  watch: {
    chartData: {
      deep: true,
      handler() {
        this.initChart();
      },
    },
  },
  mounted() {
    this.$nextTick(() => {
      this.initChart();
    });

    this.resizeObserver = new ResizeObserver(() => {
      this.chartInstance && this.chartInstance.resize();
    });
    this.resizeObserver.observe(this.$refs.chartRef);
  },
  beforeDestroy() {
    this.destroyChart();
    window.removeEventListener('resize', this.resizeChart);
    this.resizeObserver && this.resizeObserver.disconnect();
  },
  
  methods: {
    initChart() {
      const chartDom = this.$refs.chartRef;
      if (!chartDom) {
        console.warn('Echarts柱状图容器不存在');
        return;
      }
      this.destroyChart();
      this.chartInstance = this.$echarts.init(chartDom);
      const option = {
        grid: {
          left: '3%',
          right: '4%',
          bottom: '10%',
          top: '10%',
          containLabel: true,
        },
        xAxis: {
          type: 'category',
          data: this.chartData.xAxisData,
          axisLabel: {
            fontSize: 14,
            color: '#666',
          },
          axisLine: {
            lineStyle: {
              fontSize: 14,
            },
          },
          axisTick: {
            lineStyle: {
              fontSize: 16,
            },
          },
        },
        yAxis: {
          type: 'value',
          show: false,
          // axisLabel: {
          //   formatter: `{value} ${this.unit}`,
          //   fontSize: 16,
          //   color: '#666'
          // },
          axisLine: {
            show: false,
            // lineStyle: {
            //   fontSize: 16
            // }
          },

          axisTick: {
            show: false,
            // lineStyle: {
            //   fontSize: 16
            // }
          },
        },
        series: [
          {
            data: this.chartData.seriesData,
            type: 'bar',
            barWidth: '30%',
            barCategoryGap: '20%',
            label: {
              show: true,
              position: 'top',
              offset: [0, -5],
              color: this.barColor,
              fontSize: 18,
              formatter: `{c} ${this.unit}`,
            },
            itemStyle: {
              color: this.barColor,
            },
          },
        ],
      };
      this.chartInstance.setOption(option);
      window.addEventListener('resize', this.resizeChart);
      this.$emit('get-chart-instance', this.chartInstance);
    },
    resizeChart() {
      this.chartInstance && this.chartInstance.resize();
    },
    destroyChart() {
      if (this.chartInstance) {
        this.chartInstance.dispose();
        this.chartInstance = null;
      }
    },
  },
};
</script>

<style lang="scss" scoped></style>