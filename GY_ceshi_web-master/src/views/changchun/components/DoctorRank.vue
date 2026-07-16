<template>
  <div style="padding-top: 20px;">
    <div id="rank_chart" style="width: 100%; height: 520px;"></div>
  </div>
</template>

<script>
export default {
  props: {
    data: {
      type: Array,
      default() {
        return []
      }
    }
  },
  computed: {
    yNames() {
      const arr = []
      this.data.map(item => {
        arr.push(item.key)
      })
      return arr
    },
    qxCounts() {
      const arr = []
      this.data.map(item => {
        arr.push(item.defect_doc_count)
      })
      return arr
    },
    blCounts() {
      const arr = []
      this.data.map(item => {
        arr.push(item.doc_count)
      })
      return arr
    }
  },
  mounted() {
    this.renderChart()
  },
  methods: {
    renderChart() {
      var myChart = this.$echarts.init(document.getElementById('rank_chart'))
      var option = {
        tooltip: {
          trigger: 'axis',
          axisPointer: {
            type: 'shadow'
          }
        },
        color: ['#54C5A0', '#5B8EC3'],
        legend: {},
        grid: {
          left: '3%',
          right: '4%',
          bottom: '3%',
          containLabel: true
        },
        xAxis: {
          type: 'value'
        },
        yAxis: {
          type: 'category',
          data: this.yNames,
          axisLine: {
            lineStyle: {
              color: '#CFCFCF'
            }
          },
          axisTick: {
            show: false
          },
          axisLabel: {
            color: '#333333'
          }
        },
        series: [
          {
            name: '缺陷病历数',
            type: 'bar',
            stack: 'total',
            barWidth: '20',
            data: this.qxCounts
          },
          {
            name: '病历总数',
            type: 'bar',
            stack: 'total',
            barWidth: '20',
            data: this.blCounts
          }
        ]
      };
      myChart.setOption(option)
      // 窗口大小改变 重新渲染
      window.addEventListener('resize', () => {
        myChart.resize()
      })
    }
  }
}
</script>

<style lang="scss" scoped>

</style>
