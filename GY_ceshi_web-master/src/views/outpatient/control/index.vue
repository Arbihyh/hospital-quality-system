<template>
  <div class="pages">
    <div class="block">
      <div class="blockCon">
        <div class="lefts">
          <el-dropdown>
            <el-button :class="formData.year && formData.year.name ? 'color-btn' : ''">
              {{ (formData.year && formData.year.name) || '按年' }}
              <i class="el-icon-arrow-down el-icon--right"></i>
            </el-button>
            <el-dropdown-menu slot="dropdown">
              <el-dropdown-item v-for="(item, index) in yearList" :key="index" @click.native="funSeleterYear(item)">{{ item.name }}</el-dropdown-item>
            </el-dropdown-menu>
          </el-dropdown>

          <el-dropdown>
            <el-button :disabled="formData.year && formData.year.name == ''" :class="formData.quarter && formData.quarter.name ? 'color-btn' : ''">
              {{ formData.quarter && formData.quarter.name || '按季' }}
              <i class="el-icon-arrow-down el-icon--right"></i>
            </el-button>
            <el-dropdown-menu slot="dropdown">
              <el-dropdown-item v-for="(item, index) in quarterList" :key="index" @click.native="funSeleterQuarter(item)">{{ item.name }}</el-dropdown-item>
            </el-dropdown-menu>
          </el-dropdown>
          <div class="selects">
            <el-date-picker v-model="formData.startTime" type="date" format="yyyy 年 MM 月 dd 日" value-format="yyyyMMdd" placeholder="开始日期"></el-date-picker>

            <el-date-picker
              v-model="formData.endTime"
              type="date"
              style="margin-left: 10px"
              format="yyyy 年 MM 月 dd 日"
              value-format="yyyyMMdd"
              placeholder="结束日期"
            ></el-date-picker>
          </div>
          <el-button class="btn1" type="primary" @click="funQuery">查询</el-button>
        </div>
      </div>
    </div>
    <div class="cardBox">
      <Title :title="'质量分析'" />
      <div>
        <el-row :gutter="20">
          <el-col :span="8">
            <div class="item-card one" @click="toPage2">
              <span class="name">门（急）诊应有病历数量</span>
              <span class="num">{{ countsData.should_be_total || 0 }}</span>
            </div>
          </el-col>
          <el-col :span="8">
            <div class="item-card two" @click="toPage(0)">
              <span class="name">门（急）诊病历数量</span>
              <span class="num">{{ countsData.omr_total || 0 }}</span>
            </div>
          </el-col>
          <el-col :span="8">
            <div class="item-card three" @click="toPage(1)">
              <span class="name">缺陷门（急）诊病历数量</span>
              <span class="num">{{ countsData.omr_defect_total || 0 }}</span>
            </div>
          </el-col>
        </el-row>
      </div>
    </div>
    <!-- 科室排名 -->
    <div class="chart">
      <Title :title="'科室排名'" />
      <div id="myChart1" style="width: 100%; height: 600px"></div>
    </div>

    <!-- 缺陷问题 -->
    <div class="chart">
      <Title :title="'缺陷问题'" />
      <ProblemTableBoxVue :data="caseList" />
    </div>
  </div>
</template>
  
  <script>
import * as echarts from 'echarts';
import { mapGetters } from 'vuex';
import Title from '@/components/Title';
import ProblemTableBoxVue from './components/ProblemTableBox.vue';
export default {
  components: {
    Title,
    ProblemTableBoxVue,
  },
  name: 'Dashboard',
  computed: {
    ...mapGetters(['name']),
  },
  data() {
    return {
      formData: {
        rangeDate: [],
        chooseDate: '',
        startTime: '',
        endTime: '',
        year: {
          name: '',
          id: '',
        },
        month: {
          name: '',
          id: '',
        },
        quarter: {
          name: '',
        },
        problem: 'all',
        defectFelg: 'all',
        type: '1',
      },
      homeData: {},
      quarterList: [],
      monthList: [],
      yearList: [],
      countsData: {
        omr_total: 0,
        omr_defect_total: 0,
      },
      caseList: []
    };
  },
  mounted() {
    this.storageSet('start_time', '');
    this.storageSet('end_time', '');
    this.formData.chooseDate = '30';
    this.chooseTime(this.formData.chooseDate);
    if (this.storageGet('homeFrom')) {
      this.formData = this.storageGet('homeFrom');
      console.log("this.formData",this.formData);
      this.storageRemove('homeFrom');
    }
    this.funQuery();
    this.selectInfo();
  },
  methods: {
    toPage(type) {
      // type = 1 查询错误门诊数据
      // type = 0 查询所有门诊数据
      this.$router.push({ path: '/outpatientMedicalRecordDefectNumber', query: { is_error: type }})
    },
    toPage2() {
      this.$router.push({ path: '/outpatientMedicalShouldDefectNumber' })
    },
    getCaseList() {
      let pramse = {
        start_time: this.formData.startTime,
        end_time: this.formData.endTime,
      };
      this.$axios.post('/omr_zk/defect_issues', pramse).then(res => {
        this.caseList = res.data
      });
    },
    getCounts() {
      let pramse = {
        start_time: this.formData.startTime,
        end_time: this.formData.endTime,
      };
      this.$axios.post('/omr_zk/analysis', pramse).then(res => {
        this.countsData = res.data;
      });
    },
    funSeleterYear(val) {
      this.formData.year = val;
      this.formData.type = '1';
      this.formData.endTime = this.goTimeTwe(val.end);
      this.formData.startTime = this.goTimeTwe(val.start);
    },
    funSeleterMonth(val) {
      this.formData.month = val;
      this.formData.type = '3';
      this.formData.year = {
        name: '',
      };
      this.formData.quarter = {
        name: '',
      };
      this.formData.endTime = this.goTimeTwe(val.end);
      this.formData.startTime = this.goTimeTwe(val.start);
    },
    funSeleterQuarter(val) {
      this.formData.type = '2';
      this.formData.quarter = val;
      this.formData.endTime = this.formData.year.name + this.zh(val.end);
      this.formData.startTime = this.formData.year.name + this.zh(val.start);
    },
    zh(str) {
      let arr = str.split('-');
      return arr.join('');
    },
    selectInfo() {
      // let pramse = {};
      this.$axios.post('/selectInfo').then(res => {
        //问题属性 level
        this.quarterList = res.data.quarter;
        // 季度
        this.monthList = res.data.month;
        //月
        this.yearList = res.data.year;
      });
    },
    // 选择时间段
    chooseTime(time) {
      this.formData.rangeDate = this.timesCalculation(time).slice(0, 2);
    },
    funQuery() {
      //查询
      // let type_id = '';
      // if (this.formData.type == '1') {
      //   type_id = this.formData.year.id || '';
      // } else if (this.formData.type == '2') {
      //   type_id = this.formData.quarter.id;
      // } else {
      //   type_id = this.formData.month.id;
      // }
      let pramse = {
        start_time: this.formData.startTime,
        end_time: this.formData.endTime,
      };
      this.storageSet('start_time', this.formData.startTime);
      this.storageSet('end_time', this.formData.endTime);
      this.storageSet('homeFrom', this.formData);
      this.initCharts1(pramse);
      this.getCounts();
      this.getCaseList()
    },
    initCharts1(pramse) {
      this.$axios.post('/omr_zk/ranking_department', pramse).then(res => {
        let dataName = [];
        let dataFleg = [];
        const total_error_medical = [];
        let dataNum = [];
        for (let item in res.data.slice(0, 10)) {
          dataName.push(res.data[item].name);
          dataFleg.push(res.data[item].total_medical);
          dataNum.push(res.data[item].item);
          total_error_medical.push(res.data[item].total_error_medical);
        }
        // 销毁上一次实例
        echarts.init(document.getElementById('myChart1')).dispose();
        // 构建新实例
        let myChart = echarts.init(document.getElementById('myChart1'));
        window.addEventListener('resize', function () {
          myChart.resize();
        });
        if (res.data.length) {
          myChart.setOption({
            toolbox: {
              feature: {
                saveAsImage: {
                  name: '科室排名',
                },
              },
            },
            tooltip: {
              trigger: 'axis',
              axisPointer: {
                type: 'cross',
                crossStyle: {
                  color: '#999',
                },
              },
            },
            legend: {
              show: true,
            },
            xAxis: {
              type: 'category',
              data: dataName,
              axisLabel: {
                rotate: 15,
              },
            },
            grid: {
              left: '3%',
              right: '3%',
              bottom: '3%',
              containLabel: true,
            },
            yAxis: {
              type: 'value',
            },
            series: [
              {
                data: dataFleg,
                type: 'bar',
                showBackground: true,
                barMaxWidth: 30,
                label: {
                  show: true,
                  position: 'top',
                },
                name: '病历数',
                backgroundStyle: {
                  color: 'rgba(180, 180, 180, 0.2)',
                },
              },
              {
                data: total_error_medical,
                type: 'bar',
                showBackground: true,
                barMaxWidth: 30,
                label: {
                  show: true,
                  position: 'top',
                },
                name: '缺陷病历数',
                backgroundStyle: {
                  color: 'rgba(180, 180, 180, 0.2)',
                },
              },
            ],
          });
        } else {
          myChart.setOption({
            title: {
              text: '暂无数据',
              x: 'center',
              y: 'center',
              textStyle: {
                fontSize: 14,
                fontWeight: 'normal',
              },
            },
          });
        }
      });
    },
  },
};
</script>
  
  <style lang="scss" scoped>
  .cardBox {
    .item-card {
      height: 90px;
      line-height: 50px;
      border-radius: 6px;
      padding: 20px;
      display: flex;
      justify-content: space-between;
      font-weight: 600;
      color: #fff;
      cursor: pointer;
      .name {
        font-size: 16px;
      }
      .num {
        font-size: 20px;
      }
      &.one {
        background: #38A1F1;
      }
      &.two {
        background: #30b48e;
      }
      &.three {
        background: #E6A23C;
      }
    }
  }
.pages {
  padding: 0 18px;
  background: #f4f4f4;
  .btnNAv {
    display: flex;
    justify-content: flex-end;
    padding-top: 30px;
    padding-bottom: 10px;
    a {
      padding: 15px 30px;
      color: #fff;
      border-radius: 10px;
      margin-left: 15px;
    }
    .bj {
      background: #35ae4a;
    }
    .bc {
      background: #dd7500;
    }
    .dc {
      background: #439ab6;
    }
    .fh {
      background: #185da6;
    }
  }
  .block {
    margin: 16px 0;
    background: #fff;
    padding: 25px 15px;
    border-radius: 5px;
    .blockCon {
      display: flex;
      justify-content: space-between;
      .lefts {
        display: flex;
      }
      .selects {
        margin: 0 20px;
        span {
          margin-right: 10px;
        }
      }
    }
    .ytext {
      font-size: 16px;
      color: #e48d53;
      font-weight: 400;
      line-height: 40px;
    }
  }
  .cardBox {
    margin: 0 0 16px 0;
    background: #fff;
    padding: 25px 15px;
    border-radius: 5px;
  }
  .cpoin {
    cursor: pointer;
  }
  .chart {
    margin-bottom: 16px;
    background-color: #fff;
    padding: 25px 15px;
  }
}
.color-btn {
  color: #409eff;
  border-color: #c6e2ff;
  background-color: #ecf5ff;
}
</style>
  