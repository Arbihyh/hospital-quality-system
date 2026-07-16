<template>
  <div style="padding: 0 16px 16px 16px;">
    <div class="block">
      <div class="blockCon">
        <div class="lefts">
          <!-- <el-button-group> -->
          <!-- <el-button>按年</el-button> -->
          <el-dropdown>
            <el-button :class="formData.year.name ? 'color-btn' : ''">
              {{ formData.year.name || '按年' }}
              <i class="el-icon-arrow-down el-icon--right"></i>
            </el-button>
            <el-dropdown-menu slot="dropdown">
              <el-dropdown-item v-for="(item, index) in yearList" :key="index" @click.native="funSeleterYear(item)">{{ item.name }}</el-dropdown-item>
            </el-dropdown-menu>
            <!-- <el-dropdown-item @click.native="funSeleterYear({ name: '2009', num: '2009' })">2009</el-dropdown-item> -->
            <!-- </el-dropdown-menu> -->
          </el-dropdown>

          <el-dropdown>
            <el-button :class="formData.quarter.name ? 'color-btn' : ''">
              {{ formData.quarter.name || '按季' }}
              <i class="el-icon-arrow-down el-icon--right"></i>
            </el-button>
            <el-dropdown-menu slot="dropdown">
              <el-dropdown-item v-for="(item, index) in quarterList" :key="index" @click.native="funSeleterQuarter(item)">{{ item.name }}</el-dropdown-item>
            </el-dropdown-menu>
          </el-dropdown>

          <el-dropdown>
            <el-button :class="formData.month.name ? 'color-btn' : ''">
              {{ formData.month.name || '按月' }}
              <i class="el-icon-arrow-down el-icon--right"></i>
            </el-button>
            <!-- <el-dropdown-menu slot="dropdown"> -->
            <el-dropdown-menu slot="dropdown">
              <el-dropdown-item v-for="(item, index) in monthList" :key="index" @click.native="funSeleterMonth(item)">{{ item.name }}</el-dropdown-item>
            </el-dropdown-menu>
            <!-- <el-dropdown-item @click.native="funSeleterMonth({ name: '2009', num: '2009' })">2009</el-dropdown-item> -->
            <!-- </el-dropdown-menu> -->
          </el-dropdown>
          <!-- <el-button>按季</el-button>
          <el-button>按月</el-button> -->
          <!-- </el-button-group> -->
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
          <el-button type="primary" @click="funQuery">查询</el-button>
          <el-button plain type="primary">查看报告</el-button>
        </div>
      </div>
    </div>
    <div class="cardBox">
      <Title :title="'结算清单质量分析'" />
      <div class="contentBox">
        <div class="left">
          <div class="l">
            <div class="i" @click="goto('StatementList')">
              <div class="ba">结算清单数量</div>
              <div class="num">{{ homeData.total }}</div>
              <!-- <img class="icon" src="@/assets/404_images/404_cloud.png" alt="" /> -->
            </div>
            <div class="i" style="background: #af8af1">
              <div class="ba">缺陷结算清单数量占比</div>
              <div class="num">{{ homeData.averageError }}</div>
              <!-- <img class="icon" src="@/assets/404_images/404_cloud.png" alt="" /> -->
            </div>
            <div class="i" @click="goto('defectStatementList')" style="background: #38a1f1">
              <div class="ba">缺陷结算清单数量</div>
              <div class="num">{{ homeData.errorMedical }}</div>
              <!-- <img class="icon" src="@/assets/404_images/404_cloud.png" alt="" /> -->
            </div>
            <div class="i" style="background: #f6a069">
              <div class="ba">结算清单平均得分</div>
              <div class="num">{{ homeData.averageScore }}</div>
              <!-- <img class="icon" src="@/assets/404_images/404_cloud.png" alt="" /> -->
            </div>
          </div>
          <div class="r">
            <div class="i">
              <!-- <img class="icon" src="@/assets/404_images/404_cloud.png" alt="" /> -->
              <div class="t">优（>90分）</div>
              <div class="rt">{{ homeData.highest_score }}</div>
            </div>
            <div class="i">
              <!-- <img class="icon" src="@/assets/404_images/404_cloud.png" alt="" /> -->
              <div class="t">良（70-90分）</div>
              <div class="rt" style="color: #1da436">
                {{ homeData.good }}
              </div>
            </div>
            <div class="i">
              <!-- <img class="icon" src="@/assets/404_images/404_cloud.png" alt="" /> -->
              <div class="t">差（&lt;70分）</div>
              <div class="rt" style="color: #ff0000">
                {{ homeData.minimum_score }}
              </div>
            </div>
          </div>
        </div>
        <div class="right">
          <div id="myChart" style="width: 100%; height: 200px"></div>
        </div>
      </div>
    </div>

    <div class="cardBox">
      <Title :title="'缺陷问题'" />
      <el-select v-model="formData.problem" placeholder="请选择" style="margin-right: 20px">
        <el-option v-for="(item, index) in departmentList" :label="item.name" :value="item.id" :key="index"></el-option>
      </el-select>
      <el-select v-model="formData.defectFelg" placeholder="全部" style="margin-right: 20px">
        <el-option v-for="(item, index) in levelList" :label="item.name" :value="item.id" :key="index"></el-option>
      </el-select>
      <el-button type="primary" @click="initCharts1">查询</el-button>

      <div class="tableBox1">
        <div class="left">
          <div class="top">
            <div class="lt">
              <div class="item">
                <img class="icon" src="@/assets/404_images/404_cloud.png" alt="" />
                <div class="con">患者基本信息</div>
                <div class="baifen">{{ errorDataFelg.base }}%</div>
              </div>
              <div class="item">
                <img class="icon" src="@/assets/404_images/404_cloud.png" alt="" />
                <div class="con">住院诊疗信息</div>
                <div class="baifen">{{ errorDataFelg.diagnosis }}%</div>
              </div>
              <div class="item">
                <img class="icon" src="@/assets/404_images/404_cloud.png" alt="" />
                <div class="con">医疗收费信息</div>
                <div class="baifen">{{ errorDataFelg.cost }}%</div>
              </div>
            </div>
            <el-button type="primary" icon="el-icon-download" class="export-btn">导出数据</el-button>
          </div>
          <!-- <el-table :data="errorDataList" style="width: 100%"> -->
          <el-table height="400px" v-el-table-infinite-scroll="getDataNextPage" :data="errorDataList">
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
                <span class="blue" @click="GoDefectList(scope.row)">{{ scope.row.count }}</span>
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
import Title from '@/components/Title';
import * as echarts from 'echarts';
import elTableInfiniteScroll from 'el-table-infinite-scroll';
export default {
  components: {
    Title,
  },
  directives: {
    'el-table-infinite-scroll': elTableInfiniteScroll,
  },
  data() {
    return {
      value1: '',
      tableData: [],
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
      errorDataList: [],
      ranking: [],
      diagnosisRanking: [],
      errorDataFelg: {},
      homeData: {},
      indications: [],
      hospitalData: [],
      coderData: [],
      departmentList: [],
      levelList: [],
      quarterList: [],
      monthList: [],
      yearList: [],
      nextListFelg:0,
      page:2
    };
  },
  mounted() {
    this.formData.chooseDate = 3;
    this.initCharts1();
    this.chooseTime(this.formData.chooseDate);
    this.funAggregate();
    this.selectInfo();
    // this.funQueryCoder();
  },
  methods: {
    getDataNextPage() {
      if(this.nextListFelg == 0){
      // this.$message.success('已加载全部数据');
        return
      }
      let type_id = '';
      if (this.formData.type == '1') {
        type_id = this.formData.year.id || '';
      } else if (this.formData.type == '2') {
        type_id = this.formData.quarter.id;
      } else {
        type_id = this.formData.month.id;
      }
      let pramse = {
        type: this.formData.type, //按年/按月/按季
        type_id: type_id,
        start_time: this.formData.startTime, //开始时间 时间戳or时间
        end_time: this.formData.endTime, //结束时间
        level: this.formData.defectFelg,
        AAC11C: this.formData.problem,
        page:this.page
      }
      this.$axios.post('/errorData', pramse).then(res => {
        for (let item in res.data.list) {
        this.errorDataList.push(res.data.list[item])
        }
        console.log(this.errorDataList)
        this.page = this.page + 1
        this.nextListFelg = res.data.next
    })
      // this.$message.success('加载下一页');
    },
    funSeleterYear(val) {
      this.formData.year = val;
      this.formData.type = '1';
      this.formData.month = {
        name: '',
      };
      this.formData.quarter = {
        name: '',
      };
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
    },
    funSeleterQuarter(val) {
      this.formData.type = '2';
      this.formData.quarter = val;
      this.formData.year = {
        name: '',
      };
      this.formData.month = {
        name: '',
      };
    },
    jump(path, query) {
      this.$router.push(path, query);
    },
    /**
     * 携带缺陷id跳转缺陷列表页面条件查询
     * @param {缺陷id} base.error_rule
     */
    GoDefectList(error) {
      this.$router.push(`/defectList?error_rule=${error.error_rule}`);
    },
    selectInfo() {
      // let pramse = {};
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
    funGoto() {
      this.goto('/medicalRecords');
    },
 
    funQueryHospital() {
      let pramse = {
          source:1,
        startTime: this.formData.startTime, //开始时间 时间戳or时间
        endTime: this.formData.endTime, //结束时间
      };
      this.$axios.post('/ranking_hospital', pramse).then(res => {
        this.hospitalData = res.data;
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
        type: this.formData.type, //按年/按月/按季
        type_id: type_id,
        start_time: this.formData.startTime, //开始时间 时间戳or时间
        end_time: this.formData.endTime, //结束时间
        level: this.formData.defectFelg,
        AAC11C: this.formData.problem,
        page:1
      };
      this.$axios.post('/errorData', pramse).then(res => {
        this.errorDataList = res.data.list;
        this.errorDataFelg = res.data.count;
        this.nextListFelg = res.data.next
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
        // for (let item in this.errorDataList) {
        //   myChartData.push({
        //     value: this.errorDataList[item].count,
        //     name: this.errorDataList[item].field,
        //   });
        // }
        let myChart = echarts.init(document.getElementById('myChart1'));
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
    funQueryIndications() {
      let pramse = {
        source:1,
        startTime: this.formData.startTime, //开始时间 时间戳or时间
        endTime: this.formData.endTime, //结束时间
      };
      this.$axios.post('/ranking_indications', pramse).then(res => {
        this.indications = res.data;
      });
    },
  
    funAggregate() {
      let pramse = {
        source:1,
        type: this.formData.chooseDate, //按年/按月/按季
        startTime: this.formData.startTime, //开始时间 时间戳or时间
        endTime: this.formData.endTime, //结束时间
      };
      this.$axios.post('/homeCensus', pramse).then(res => {
        console.log('平均', res);
        this.homeData = res.data;
        // let chartsData = [res.data.before, res.data.last, res.data.new];
        let year = [res.data.before.year, res.data.last.year, res.data.new.year];
        //         * 'averageError' => '病案数量',
        let newData1 = [res.data.before.averageError, res.data.last.averageError, res.data.new.averageError];
        let newData2 = [res.data.before.averageScore, res.data.last.averageScore, res.data.new.averageScore];
        let newData3 = [res.data.before.outstanding, res.data.last.outstanding, res.data.new.outstanding];
        // * 'errorMedical' => ‘平均缺陷’,
        // * 'averageScore' => '平均得分',
        // * 'averageError' => '平均缺陷',
        // * 'highest_score' => '最高得分',
        // * 'minimum_score' => '最低得分',
        let myChart = echarts.init(document.getElementById('myChart'));
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
            data: ['结算清单数量', '结算清单缺陷数量', '结算清单平均分'],
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
              // axisLabel: {
              //   formatter: "value",
              // },
            },
            {
              type: 'value',
              name: '得分',
              // axisLabel: {
              //   formatter: "value",
              // },
            },
          ],
          series: [
            {
              name: '结算清单数量',
              barMaxWidth: 30,
              type: 'bar',
              tooltip: {
                valueFormatter: function (value) {
                  return value;
                },
              },
              data: newData1,
            },
            {
              name: '结算清单缺陷数量',
              barMaxWidth: 30,
              type: 'bar',
              tooltip: {
                valueFormatter: function (value) {
                  return value;
                },
              },
              data: newData2,
            },
            {
              name: '结算清单平均分',
              type: 'line',
              yAxisIndex: 1,
              tooltip: {
                valueFormatter: function (value) {
                  return value;
                },
              },
              data: newData3,
            },
          ],
        });
      });
    },
    // 选择时间段
    chooseTime(time) {
      console.error(time);
      this.formData.chooseDate = time;
      this.funAggregate();
      return;
      // this.formData.rangeDate = this.timesCalculation(time).slice(0, 2);
    },
    funQuery() {
      //查询
      // this.initCharts();
      this.funAggregate();
      this.funQueryRanking();
      this.funQueryIndications();
      this.funQueryHospital();
    },
    handleClick() {},
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
.ytext {
  font-size: 16px;
  color: #e48d53;
  font-weight: 400;
  line-height: 40px;
}
.block {
  margin-bottom: 16px;
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
        display: flex;
        flex: 1;
        flex-wrap: wrap;
        .i {
          width: calc(50% - 20px);
          margin-right: 20px;
          height: 86px;
          background: #30b48e;
          border-radius: 5px;
          position: relative;
          overflow: hidden;
          display: flex;
          align-items: center;
          justify-content: center;
          text-align: center;
          .ba {
            flex: 1;
            font-size: 17px;
            text-align: left;
            padding-left: 10px;
            font-weight: 400;
            color: #fff;
            // margin: 18px 0 0 38px;
          }
          .num {
            font-size: 24px;
            font-weight: bold;
            color: #fff;
            // margin: 15px 0 0 38px;
            flex: 1;
            margin-left: 20px;
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
        margin: 0 9% 0 0;
        .i {
          width: 280px;
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
            flex: 1;
            font-size: 18px;
            font-weight: 400;
            color: #333333;
            text-align: left;
            margin-left: 10px;
            // margin: 5px 0 0 27px;
          }
          .rt {
            margin-left: 10px;
            flex: 1;
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
    display: flex;
    .left {
      flex: 1;
      .top {
        display: flex;
        justify-content: space-between;
        margin: 13px 0;
        .lt {
          display: flex;
          .item {
            padding: 0 20px 0 0;
            background: #f1f6ff;
            border-radius: 4px;
            display: flex;
            align-items: center;
            margin: 0 11px 0 0;
            .icon {
              width: 18px;
              margin: 12px;
            }
            .con {
              font-size: 12px;
              font-weight: 400;
              color: #333333;
            }
            .baifen {
              font-size: 16px;
              font-weight: bold;
              color: #185da6;
              margin: 0 0 0 15px;
            }
          }
        }
      }
    }
    .right {
      width: 521px;
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
.blueju {
  background: #a0d0f0;
  color: #000;
  border: 1px solid #d8d9da;
}
.whiteju {
  border: 2px solid #d8d9da;
  display: flex;
  align-items: center;
  height: 40px;
}
.footers {
  display: flex;
  justify-content: flex-end;
  margin-top: 20px;
}
</style>
