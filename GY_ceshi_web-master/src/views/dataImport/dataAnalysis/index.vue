<template>
  <div class="pages">
    <div class="block">
      <div class="name-box">
        <span>医院名称</span>
        <el-select v-model="hospital_name" filterable class="selects" placeholder="医院名称">
          <el-option v-for="(item, index) in HospitalList" :label="item" :value="item" :key="index"></el-option>
        </el-select>
      </div>
      <div class="block-time-box">
        <span>出院时间</span>
        <div style="margin-left: 12px;">
          <el-date-picker v-model="formData.startTime" type="date" format="yyyy 年 MM 月 dd 日" value-format="yyyyMMdd" placeholder="开始日期"></el-date-picker>
          <el-date-picker v-model="formData.endTime" type="date" style="margin-left: 10px" format="yyyy 年 MM 月 dd 日" value-format="yyyyMMdd" placeholder="结束日期"></el-date-picker>
        </div>
      </div>
      <div class="block-but-box">
        <el-button @click="funQuery" type="primary">查询</el-button>
      </div>
    </div>
    <div class="data-block-box">
      <div class="bars">
        <div class="items" @click="funGoto">
          <p class="bar">首页总病例：{{ homeData.total }}</p>
          <p class="names">日均例数：{{ homeData.day_avg }}</p>
        </div>
        <div class="items" @click="funGototo()">
          <p class="bar">缺陷总例数：{{ homeData.errorMedical }}</p>
          <p class="names">缺陷例数占比：{{ homeData.averageError }}%</p>
        </div>
        <div class="items">
          <p class="bar">平均得分：{{ homeData.averageScore }}</p>
          <p class="names">最低分：{{ homeData.min_score }}</p>
        </div>
      </div>
      <div class="lv-block">
        <div class="lv-items-box">
          <div class="lv-1">
            <span>
              <span class="lv-t">优</span><span class="lv-num">{{ homeData.highest_score }}</span>
            </span>
            <span>占比{{ homeData.you_ratio }}%</span>
          </div>
          <div class="lv-2">
            <span>
              <span class="lv-t">良</span><span class="lv-num">{{ homeData.good ? homeData.good : 0 }}</span>
            </span>
            <span>占比{{ homeData.liang_ratio }}%</span>
          </div>
          <div class="lv-3">
            <span>
              <span class="lv-t">中</span><span class="lv-num">{{ homeData.middle ? homeData.middle : 0 }}</span>
            </span>
            <span>占比{{ homeData.zhong_ratio }}%</span>
          </div>
          <div class="lv-4">
            <span>
              <span class="lv-t">差</span><span class="lv-num">{{ homeData.minimum_score }}</span>
            </span>
            <span>占比{{ homeData.cha_ratio }}%</span>
          </div>
        </div>
        <div class="lv-block-sm">
          <i class="el-icon-info"></i>
          <span>等级说明</span>
        </div>
      </div>
    </div>
    <div class="cardBox">
      <div style="margin-bottom: 13px;">
        <el-button-group>
          <el-button style="background-color: #409EFF;color:#fff;">缺陷问题（前十）</el-button>
          <el-button @click="toPage">缺陷问题</el-button>
        </el-button-group>
      </div>
      <el-select v-model="formData.problem" filterable placeholder="请选择" style="margin-right: 20px">
        <el-option v-for="(item, index) in departmentList" :label="item.name" :value="item.id" :key="index"></el-option>
      </el-select>
      <el-select v-model="formData.defectFelg" placeholder="全部" style="margin-right: 20px">
        <el-option v-for="(item, index) in levelList" :label="item.name" :value="item.id" :key="index"></el-option>
      </el-select>
      <el-button type="primary" @click="initCharts1">查询</el-button>

      <div class="tableBox1">
        <div class="left">
          <el-table height="400px" :data="errorDataList" style="width: 100%;">
            <el-table-column type="index" label="序号"></el-table-column>
            <el-table-column prop="desc" align="center" label="缺陷描述"></el-table-column>
            <el-table-column prop="field" align="center" label="缺陷字段"></el-table-column>
            <el-table-column prop="level" align="center" label="缺陷分级">
              <template slot-scope="scope">
                <span>{{ scope.row.level == '0' ? '强制' : '建议' }}</span>
              </template>
            </el-table-column>
            <el-table-column prop="count" align="center" label="缺陷数量">
              <template slot-scope="scope">
                <span class="blue" @click="GoDefectList(scope.row)" style="cursor: pointer;">
                  {{ scope.row.count }}
                </span>
              </template>
            </el-table-column>
          </el-table>
        </div>
        <div class="right">
          <div id="myChart1" style="width: 100%; height: 500px"></div>
        </div>
      </div>
    </div>
  </div>
</template>
<script>
import * as echarts from 'echarts';
export default {
  name: 'dataAnalysis',
  components: {

  },
  data() {
    return {
      hospital_name:"",// 医院名称
      HospitalList: [], //医院名称列表
      tableData: [],
      errorDataList: [], // 缺陷问题列表
      departmentList:[],
      homeData: {},
      formData: {
        problem: 'all',
        defectFelg: 'all',
        rangeDate: [],
        chooseDate: '',
        startTime: '',
        endTime: '',
        year: {
          name: '',
        },
        month: {
          name: '',
        },
        quarter: {
          name: '',
        },
        type: '1',
      },
      levelList: [],
    
    };
  },
  mounted() {
    this.storageSet('endTime', '');
    this.storageSet('startTime', '');
    // 默认获取当前本年日期
    this.getdaTe();
    this.formData.chooseDate = 3;
    this.chooseTime(this.formData.chooseDate);

    // 获取医院名称
    this.getHospitalList(this.funQuery);

  },

  methods: {
    funQuery() {
      //查询
      this.funAggregate();
      this.initCharts1();
      this.selectInfo();
    },
    funGoto() {
      this.storageSet('endTime', this.formData.endTime || '');
      this.storageSet('startTime', this.formData.startTime || '');
      this.goto(`/medicalRecords?pageType=bl_import&hospital_name=${this.hospital_name}`);
    },
    // 点击重置条件
    getResult() {
      this.formData.year.name = '';
      this.formData.month.name = '';
      this.formData.type = '1';
      this.formData.quarter.name = '';
      this.formData.startTime = '';
      this.formData.endTime = '';
      this.funQuery();
    },
    // 获取默认当前日期
    getdaTe(){
      let date = new Date();
      let yy = date.getFullYear();
      let mm = date.getMonth() + 1;
      let dd = date.getDate();
      mm = mm > 9 ? mm : '0' + mm;
      dd = dd > 9 ? dd : '0' + dd;
      this.formData.startTime = yy + '0101';
      this.formData.endTime = `${yy}${mm}${dd}`;
      this.storageSet('endTime', this.formData.endTime || '');
      this.storageSet('startTime', this.formData.startTime || '');
    },
    // 选择时间段
    chooseTime(time) {
      console.error(time);
      this.formData.chooseDate = time;
      // this.funAggregate();
      return;
    },
    // 获取医院名称
    getHospitalList(f) {
      this.$axios.post('/home_quality/getHospitalList').then(res => {
        console.log(res.data)
        this.HospitalList = res.data;
        this.hospital_name = res.data[0];
        return f()
      });
    },

    selectInfo() {
      this.$axios.post('/selectInfo').then(res => {
        this.departmentList = res.data.department;
        //出院科室 department
        this.levelList = res.data.level;
        //问题属性 level
        this.quarterList = res.data.quarter;
        // 季度
        this.monthList = res.data.month;
        //月
        this.yearList = res.data.year;
      });
    },

    funAggregate() {
      let type_id = '';
      if (this.formData.type == '1') {
        type_id = this.formData.year.id || '';
      } else if (this.formData.type == '2') {
        type_id = this.formData.quarter.id;
      } else {
        type_id = this.formData.month.id;
      }
      let pramse = {
        hospital_name: this.hospital_name,
        type: this.formData.type, //按年/按月/按季
        // type_id: type_id,
        year: this.formData.year.name,
        quarter: this.formData.quarter.name,
        month: this.formData.month.name,
        start_time: this.formData.startTime, //开始时间 时间戳or时间
        end_time: this.formData.endTime, //结束时间
      };
      this.$axios.post('/homeCensus', pramse).then(res => {
        this.homeData = res.data;
        let year = [res.data.before.year, res.data.last.year, res.data.new.year];
        let newData1 = [res.data.before.averageError, res.data.last.averageError, res.data.new.averageError];
        let newData2 = [res.data.before.averageScore, res.data.last.averageScore, res.data.new.averageScore];
        let myChart = echarts.init(document.getElementById('myChart'));
        this.chart = myChart
        window.addEventListener('resize', function () {
          myChart.resize();
        });
        myChart.setOption({
          toolbox: {
            feature: {
              saveAsImage: {
                name: '图表',
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
            data: ['病案数量', '缺陷病案数量'],
          },
          grid: {
            left: '3%',
            right: '3%',
            bottom: '3%',
            containLabel: true,
          },
          xAxis: [
            {
              type: 'category',
              data: year,
              axisPointer: {
                type: 'shadow',
              },
            },
          ],
          yAxis: [
            {
              type: 'value',
              name: '数量',
            },
          ],
          series: [
            {
              name: '病案数量',
              type: 'bar',
              barMaxWidth: 30,
              tooltip: {
                valueFormatter: function (value) {
                  return value;
                },
              },
              label: {
                show: true,
                position: 'top',
              },
              data: newData1,
            },
            {
              name: '缺陷病案数量',
              type: 'bar',
              barMaxWidth: 30,
              tooltip: {
                valueFormatter: function (value) {
                  return value;
                },
              },
              label: {
                show: true,
                position: 'top',
              },
              data: newData2,
            },
          ],
        });
      });
    },


    initCharts1() {
      let type_id = '';
      if (this.formData.type == '1') {
        type_id = this.formData.year.id || '';
      } else if (this.formData.type == '2') {
        type_id = this.formData.quarter.id;
      } else {
        type_id = this.formData.month.id;
      }
      let pramse = {
        hospital_name: this.hospital_name, // 医院名称
        type: this.formData.type, //按年/按月/按季
        // type_id: type_id,
        year: this.formData.year.name,
        quarter: this.formData.quarter.name,
        month: this.formData.month.name,
        start_time: this.formData.startTime, //开始时间 时间戳or时间
        end_time: this.formData.endTime, //结束时间
        level: this.formData.defectFelg,
        AAC11C: this.formData.problem,
        page: 1,
      };
      this.$axios.post('/errorData', pramse).then(res => {
        this.errorDataList = res.data.list;
        this.errorDataFelg = res.data.count;
        this.nextListFelg = res.data.next;
        console.log(this.errorDataFelg);
        // base,diagnosis,cost
        let myChartData = [
          {
            value: this.errorDataFelg.other,
            name: '其他' + this.errorDataFelg.other + '%',
          },
          {
            value: this.errorDataFelg.base,
            name: '患者基本信息' + this.errorDataFelg.base + '%',
          },
          {
            value: this.errorDataFelg.diagnosis,
            name: '诊疗信息' + this.errorDataFelg.diagnosis + '%',
          },
          {
            value: this.errorDataFelg.cost,
            name: '费用信息' + this.errorDataFelg.cost + '%',
          },
        ];
        console.log(myChartData);
        let myChart = echarts.init(document.getElementById('myChart1'));
        this.chart1 = myChart
        window.addEventListener('resize', function () {
          myChart.resize();
        });
        myChart.setOption({
          toolbox: {
            feature: {
              saveAsImage: {
                name: '图表',
              },
            },
          },
          title: {
            left: 'center',
          },
          legend: {
            top: '13%',
            left: 'center',
          },
          tooltip: {
            trigger: 'item',
          },
          grid: {
            left: '3%',
            right: '3%',
            bottom: '3%',
            containLabel: true,
          },
          series: [
            {
              name: 'Access From',
              type: 'pie',
              radius: '50%',
              data: myChartData,
              emphasis: {
                itemStyle: {
                  shadowBlur: 10,
                  shadowOffsetX: 0,
                  shadowColor: 'rgba(0, 0, 0, 0.5)',
                },
              },
            },
          ],
        });
      });
    },
    // 缺陷问题
    toPage() {
      this.$router.push({ path: '/defectProblem', query: { hospital_name: this.hospital_name }  })
    },
      /**
     * 携带缺陷id跳转缺陷列表页面条件查询
     * @param {缺陷id} base.error_rule
     */
     GoDefectList(error) {
      this.storageSet('endTime', this.formData.endTime || '');
      this.storageSet('startTime', this.formData.startTime || '');
      this.$router.push({ 
        path: '/defectList',
        query: { 
          hospitalName: this.hospital_name,
          error_rule:error.error_rule 
        }}
      );
    },
    // 点击缺陷病案
    funGototo(){
      this.storageSet('endTime', this.formData.endTime || '');
      this.storageSet('startTime', this.formData.startTime || '');
      this.goto(`/defectList?hospitalName=${this.hospital_name}`)
    },
 
  },
};
</script>
<style scoped>
::v-deep.el-pagination.is-background .btn-next,
::v-deep.el-pagination.is-background .btn-prev,
::v-deep.el-pagination.is-background .el-pager li {
  margin: 0 5px;
  background-color: #fff;
  color: #606266;
  min-width: 30px;
  border-radius: 2px;
  border: 1px solid #dfe3f3;
  line-height: 27px;
}
::v-deep.el-pagination.is-background .el-pager li:not(.disabled).active {
  background: #7e8bab;
}
::v-deep.el-table .el-table__row td {
  color: #7e8bab;
  border-bottom: 1px solid #f4f4f4;
}
::v-deep.el-table .el-table__header tr th:first-child {
  border-radius: 10px 0px 0px 10px;
}
::v-deep.el-table .el-table__header tr th:last-child {
  border-radius: 0px 10px 10px 0px;
}
::v-deep.el-table .el-table__header tr th {
  background: #f1f6ff;
  color: #13171e;
  border-bottom: 0px;
}
</style>
<style lang="scss" scoped>
.pages{
  margin: 0 16px 16px 16px;
}
.tableBox {
  background: #fff;
  padding: 19px;
  border-radius: 5px;
}
.bars {
  display: flex;
  margin-bottom: 16px;
  .items {
    flex: 1;
    margin-right: 10px;
    height: 86px;
    border-radius: 5px;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    padding-left: 30px;
    .names {
      margin-top: 12px;
      font-size: 12px;
      color: #fff;
      font-weight: 400;
    }
    p {
      margin: 0;
    }
    .bar {
      font-size: 24px;
      color: #fff;
    }
  }
  .items:nth-child(1) {
    background: url('../../../assets/images/b1.png') no-repeat;
    background-size: 100% 100%;
  }
  .items:nth-child(2) {
    background: url('../../../assets/images/b2.png') no-repeat;
    background-size: 100% 100%;
  }
  .items:nth-child(3) {
    margin-right: 0px;
    background: url('../../../assets/images/b3.png') no-repeat;
    background-size: 100% 100%;
  }
}

.block {
  background: #fff;
  display: flex;
  align-items: center;
  border-radius: 5px;
  height: 75px;
  margin-bottom: 16px;
  padding-left: 20px;
  .name-box{
    width: auto;
    display: flex;
    align-items: center;
    justify-content: center;
    ::v-deep .el-select{
      margin-left: 10px;
    }
  }
  .block-time-box{
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
  }
  .block-but-box{
    width: 120px;
    display: flex;
    align-items: center;
    justify-items: center;
  }
  .demonstration {
    color: #333333;
  }
  .sc {
    margin: 0 10px;
    background: #35ae4a;
  }
  .dc {
    background: #d38000;
    color: #fff;
  }
  .pickers {
    margin: 0 30px;
  }
  .lsxd {
    margin-left: 20px;
  }
 
}
.data-block-box{
  background: #fff;
  border-radius: 5px;
  margin-bottom: 16px;
  padding: 16px;
}

.lv-block{
  background: #fff;
  display: flex;
  align-items: center;
  border-radius: 5px;
  height: 60px;
}
.lv-block-sm{
  width: 96px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 14px;
  color: #D38D14;
}
.lv-items-box{
  height: 100%;
  flex: 1;
  display: flex;
  align-items: center;
}
.lv-items-box>div{
  height: 100%;
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: space-between;
  font-size: 14px;
  margin-left: 10px;
  padding: 0 10px;
  background: #EAF4FF;
  .lv-t{
    font-size: 20px;
  }
  .lv-num{
    margin: 0 10px;
    font-size: 15px;
    font-weight: bold;
    color: #38a1f2;
  }
}
.lv-items-box>div.lv-1{
  margin-left: 0;
}
// .lv-items-box>div.lv-1 .lv-num{
//   color: #38a1f2;
// }
// .lv-items-box>div.lv-2 .lv-num{
//   color: #ff0000;
// }
// .lv-items-box>div.lv-3 .lv-num{
//   color: rgb(29, 164, 54);
// }
// .lv-items-box>div.lv-4 .lv-num{
//   color: rgb(0, 0, 0);
// }
.footers {
  display: flex;
  align-items: center;
  margin-top: 20px;
  justify-content: center;
  span {
    margin-left: 20px;
  }
}
.cardBox {
  margin: 0 0 16px 0;
  background: #fff;
  padding: 25px 15px;
  border-radius: 5px;
  .contentBox {
    display: flex;
    .left {
      display: flex;
      flex: 1;
      .l {
        // max-width: 500px;
        // width: 400px;
        display: flex;
        flex: 1;
        flex-wrap: wrap;
        .i {
          width: calc(50% - 20px);
          margin: 5px 0;
          min-width: 150px;
          margin-right: 20px;
          height: 86px;
          background: #30b48e;
          border-radius: 5px;
          position: relative;
          overflow: hidden;
          display: flex;
          align-items: center;
          justify-content: space-around;
          text-align: center;
          .ba {
            // flex: 1;
            width: 70px;
            font-size: 16px;
            font-weight: 400;
            color: #fff;
            // margin: 18px 0 0 38px;
          }
          .num {
            font-size: 24px;
            font-weight: bold;
            color: #fff;
            // margin: 15px 0 0 38px;
            // flex: 1;
            // margin-left: 20px;
          }
          .icon {
            width: 50px;
            position: absolute;
            top: 18px;
            right: 25px;
          }
        }
      }
      .r {
        margin: 0 1% 0 0;
        .i {
          // width: 280px;
          flex: 1;
          min-width: 180px;
          height: 56px;
          background: #eaf4ff;
          border-radius: 4px;
          margin: 0 0 9px 0;
          display: flex;
          align-items: center;
          justify-content: center;
          // position: relative;
          .icon {
            width: 22px;
            margin: 6px 0 0 27px;
          }
          .t {
            // flex: 1;
            font-size: 18px;
            font-weight: 400;
            color: #333333;
            text-align: left;
            margin-left: 10px;
            // margin: 5px 0 0 27px;
          }
          .rt {
            margin-left: 10px;
            // flex: 1;
            // position: absolute;
            // right: 15px;
            // top: 10px;
            font-weight: bold;
            color: #38a1f2;
            font-size: 18px;
            text-align: left;
          }
        }

        .i:nth-child(1) {
          background: #93d2f3;
        }

        .i:nth-child(2) {
          background: #f4ce98;
        }

        .i:nth-child(3) {
          background: #de868f;
        }
      }
    }
    .right {
      width: 521px;
    }
  }

  .tableBox1 {
    padding-right: 521px;
    position: relative;
    margin-top: 16px;
    .left {
      .top {
        width: 700px;
        display: flex;
        justify-content: space-between;
        margin: 13px 0;
        .lt {
          display: flex;
          .item {
            // padding: 0 20px 0 0;
            width: 190px;
            display: flex;
            justify-content: space-around;
            background: #f1f6ff;
            border-radius: 4px;
            display: flex;
            align-items: center;
            margin: 0 11px 0 0;
            .icon {
              width: 18px;
              margin: 12px 0;
            }
            .con {
              font-size: 12px;
              font-weight: 400;
              color: #333333;
              white-space: nowrap;
            }
            .baifen {
              font-size: 16px;
              font-weight: bold;
              color: #185da6;
              margin: 0 0 0 8px;
            }
          }
        }
      }
    }
    .right {
      width: 521px;
      position: absolute;
      right: 0;
      top: 0;
    }
  }

  .tableBox2 {
    .top {
      display: flex;
      justify-content: space-between;
      margin: 13px 0;
    }
  }
}
</style>
