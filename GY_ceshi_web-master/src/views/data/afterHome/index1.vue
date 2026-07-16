<template>
  <div style="padding: 0 16px 16px 16px;">
    <div class="block">
      <div class="blockCon">
        <div class="lefts">
          <!-- <el-button-group> -->
          <!-- <el-button>按年</el-button> -->
          <div style="display: flex;width:auto;">
            <el-dropdown>
              <el-button :class="formData.year.name ? 'color-btn' : ''">
                {{ formData.year.name || '按年' }}
                <i class="el-icon-arrow-down el-icon--right"></i>
              </el-button>
              <el-dropdown-menu slot="dropdown">
                <el-dropdown-item v-for="(item, index) in yearList" :key="index" @click.native="funSeleterYear(item)">{{ item.name }}</el-dropdown-item>
              </el-dropdown-menu>
            </el-dropdown>

            <el-dropdown>
              <el-button :disabled="formData.year.name == ''" :class="formData.quarter.name ? 'color-btn' : ''">
                {{ formData.quarter.name || '按季' }}
                <i class="el-icon-arrow-down el-icon--right"></i>
              </el-button>
              <el-dropdown-menu slot="dropdown">
                <el-dropdown-item v-for="(item, index) in quarterList" :key="index" @click.native="funSeleterQuarter(item)">{{ item.name }}</el-dropdown-item>
              </el-dropdown-menu>
            </el-dropdown>

            <!-- <el-dropdown>
              <el-button :disabled="formData.year.name == ''" :class="formData.month.name ? 'color-btn' : ''">
                {{ formData.month.name || '按月' }}
                <i class="el-icon-arrow-down el-icon--right"></i>
              </el-button>
              <el-dropdown-menu slot="dropdown">
                <el-dropdown-item v-for="(item, index) in monthList" :key="index" @click.native="funSeleterMonth(item)">{{ item.name }}</el-dropdown-item>
              </el-dropdown-menu>
            </el-dropdown> -->
            <div class="selects">
              <el-date-picker
                v-model="formData.startTime"
                style="width: 150px;"
                type="month"
                format="yyyy 年 MM 月"
                value-format="yyyyMMdd"
                placeholder="开始日期"
              ></el-date-picker>

              <el-date-picker
                v-model="formData.endTime"
                type="month"
                style="margin-left: 10px; width: 150px;"
                format="yyyy 年 MM 月"
                value-format="yyyyMMdd"
                placeholder="结束日期"
              ></el-date-picker>
            </div>
            <el-button type="primary" @click="funQuery">查询</el-button>
            <el-button @click="getResult">重置条件</el-button>
          </div>
          <div style="margin-right:120px">
            <el-button type="primary" plain>查看报告</el-button>
          </div>
        </div>
        <div class="ytext">基于我院病案首次质控结果得出页面展示结果</div>
      </div>
    </div>
    <div class="cardBox">
      <Title :title="'质量分析'" />
      <div class="contentBox">
        <div class="left">
          <div class="l">
            <div class="i" @click="funGoto" style="cursor: pointer;">
              <div class="ba">病案数量</div>
              <div class="num">{{ homeData.total }}</div>
              <!-- <img class="icon" src="@/assets/404_images/404_cloud.png" alt="" /> -->
            </div>
            <div class="i" style="background: #af8af1">
              <div class="ba">缺陷病案占比</div>
              <div class="num">{{ homeData.averageError }}%</div>
              <!-- <img class="icon" src="@/assets/404_images/404_cloud.png" alt="" /> -->
            </div>
            <div class="i" @click="funGototo()" style="cursor: pointer; background: #38a1f1">
              <div class="ba">缺陷病案</div>
              <div class="num">{{ homeData.errorMedical }}</div>
              <!-- <img class="icon" src="@/assets/404_images/404_cloud.png" alt="" /> -->
            </div>
            <div class="i" style="background: #f6a069">
              <div class="ba">平均得分</div>
              <div class="num">{{ homeData.averageScore }}</div>
              <!-- <img class="icon" src="@/assets/404_images/404_cloud.png" alt="" /> -->
            </div>
          </div>
          <div class="r">
            <div class="i">
              <!-- <img class="icon" src="@/assets/404_images/404_cloud.png" alt="" /> -->
              <div class="t">
                <i class="el-icon-s-claim"></i>
                优
              </div>
              <div class="rt">{{ homeData.highest_score }}</div>
            </div>
            <div class="i" style="position: relative">
              <!-- <img class="icon" src="@/assets/404_images/404_cloud.png" alt="" /> -->
              <div class="t">中</div>
              <div class="rt" style="color: #ff0000">
                {{ homeData.ZHONG ? homeData.ZHONG : 0 }}
              </div>
            </div>
          <!-- </div> -->

          <!-- <div class="r"> -->
            <div class="i" style="background: lightpink">
              <!-- <img class="icon" src="@/assets/404_images/404_cloud.png" alt="" /> -->
              <div class="t">良</div>
              <div class="rt" style="color: #1da436">
                {{ homeData.good ? homeData.good : 0 }}
              </div>
            </div>
            <div class="i" style="background: #f56c6c">
              <!-- <img class="icon" src="@/assets/404_images/404_cloud.png" alt="" /> -->
              <div class="t">差</div>
              <div class="rt" style="color: #000">
                {{ homeData.minimum_score }}
              </div>
            </div>
            <span class="textMsg" style="color: #409eff">
                <i class="el-icon-s-order" style="color: #409eff"></i>
                <el-popover placement="top-start" title="" width="245" trigger="hover">
                  <div slot v-html="testMsg"></div>
                  <span slot="reference">等级计算说明</span>
                </el-popover>
              </span>
          </div>
        </div>
        <div class="right">
          <div id="myChart" style="width: 100%; height: 200px"></div>
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
          <div class="top">
            <div class="lt">
              <div class="item">
                <img class="icon" src="@/assets/404_images/404_cloud.png" alt="" />
                <div class="con">患者基本信息</div>
                <div class="baifen">{{ errorDataFelg.base }}%</div>
              </div>
              <div class="item">
                <img class="icon" src="@/assets/404_images/404_cloud.png" alt="" />
                <div class="con">诊疗信息</div>
                <div class="baifen">{{ errorDataFelg.diagnosis }}%</div>
              </div>
              <div class="item">
                <img class="icon" src="@/assets/404_images/404_cloud.png" alt="" />
                <div class="con">费用信息</div>
                <div class="baifen">{{ errorDataFelg.cost }}%</div>
              </div>
            </div>
            <el-button type="primary" icon="el-icon-download" @click="funExport('缺陷问题统计', '/errorData')" class="export-btn">导出数据</el-button>
          </div>
          <!-- <el-table :data="errorDataList" style="width: 100%"> -->
          <el-table height="400px" v-el-table-infinite-scroll="getDataNext" :data="errorDataList">
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
                  <!-- <i style="color: red; font-size: 18px; margin-left: 2px" class="el-icon-view"></i> -->
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
import Title from '@/components/Title';
import * as echarts from 'echarts';
import { downloadFile } from '@/httpFile';
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
      testMsg: '优：≥97分；</br>' + '良：90~96分且不出现A类错误；</br>' + '中：75~89分且不出现A类错误；</br>' + '差：＜75分。',
      formData: {
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
        problem: 'all',
        defectFelg: 'all',
        type: '1',
      },
      tableData: [],
      departmentList: [],
      levelList: [],
      errorDataFelg: {},
      errorDataList: [],
      homeData: {},
      options: [],
      value: '',
      quarterList: [],
      monthList: [],
      yearList: [],
      nextListFelg: 0,
      page: 2,
      chart: null,
      chart1: null,
      timer: null,
    };
  },
  mounted() {
    this.storageSet('endTime', '');
    this.storageSet('startTime', '');
    // 默认获取当前本年日期
    this.getdaTe();
    this.funQuery();
    this.selectInfo();
  },
  activated() {
    this.chart.resize()
    this.chart1.resize()
  },
  methods: {
     // 缺陷问题（临床）
     toPage() {
      this.$router.push({ path: '/defectProblem',query:{ type_name:'lc'} });
    },
    // 点击缺陷病案
    funGototo(){
      this.storageSet('endTime', this.formData.endTime || '');
      this.storageSet('startTime', this.formData.startTime || '');
      this.goto('/defectList')
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
    getDataNext(){
      if(this.timer) clearTimeout(this.timer); //清上一次的计数器
      this.timer = setTimeout(() =>{
        this.getDataNextPage()
      },500)
    },
    getDataNextPage() {
      if (this.nextListFelg == 0) {
        // this.$message.success('已加载全部数据');
        return;
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
        // type_id: type_id,
        year: this.formData.year.name,
        quarter: this.formData.quarter.name,
        month: this.formData.month.name,
        start_time: this.formData.startTime, //开始时间 时间戳or时间
        end_time: this.formData.endTime, //结束时间
        level: this.formData.defectFelg,
        AAC11C: this.formData.problem,
        page: this.page,
      };
      this.$axios.post('/errorData', pramse).then(res => {
        for (let item in res.data.list) {
          this.errorDataList.push(res.data.list[item]);
        }
        console.log(this.errorDataList);
        this.page = this.page + 1;
        this.nextListFelg = res.data.next;
      });
      // this.$message.success('加载下一页');
    },
    funExport(fileName, httpUrl) {
      let type_id = '';
      if (this.formData.type == '1') {
        type_id = this.formData.year.id || '';
      } else if (this.formData.type == '2') {
        type_id = this.formData.quarter.id;
      } else {
        type_id = this.formData.month.id;
      }
      // let fileName ='缺陷问题统计'
      let pramse = {
        is_export: '1',
        type: this.formData.type, //按年/按月/按季
        // type_id: type_id,
        year: this.formData.year.name,
        quarter: this.formData.quarter.name,
        month: this.formData.month.name,
        start_time: this.formData.startTime, //开始时间 时间戳or时间
        end_time: this.formData.endTime, //结束时间
      };
      if (httpUrl == '/errorData') {
        pramse.level = this.formData.defectFelg;
        pramse.AAC11C = this.formData.problem;
      }
      this.funExeclPost(fileName, pramse, httpUrl, 'xlsx');
    },
    funExeclPost(fileName, pramse, httpUrl, format) {
      //导出
      let httpUrls = '/api' + httpUrl;
      downloadFile(httpUrls, pramse, format, fileName).then(res => {
        console.log(res);
      });
    },
    selectInfo() {
      // let pramse = {};
      this.$axios.post('/selectInfo').then(res => {
        // this.payList = res.data.pay;
        // console.log(this.payList);
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
    GoDefectList(error) {
      this.storageSet('endTime', this.formData.endTime || '');
      this.storageSet('startTime', this.formData.startTime || '');
      this.$router.push(`/defectList?error_rule=${error.error_rule}`);
    },
    funQuery() {
      this.funAggregate();
      this.initCharts1();
    },
    getResult() {
      this.formData.year.name = '';
      this.formData.month.name = '';
      this.formData.type = '1';
      this.formData.quarter.name = '';
      this.formData.startTime = '';
      this.formData.endTime = '';
      this.funQuery();
      this.selectInfo();
    },
    funSeleterYear(val) {
      this.formData.year = val;
      this.formData.type = '1';
      // this.formData.month = {
      //   name: '',
      // };
      // this.formData.quarter = {
      //   name: '',
      // };
      this.formData.endTime = this.goTimeTwe(val.end);
      this.formData.startTime = this.goTimeTwe(val.start);
    },
    funSeleterMonth(val) {
      this.formData.month = val;
      this.formData.type = '3';
         this.formData.endTime =this.formData.year.name+val.end;
      this.formData.startTime = this.formData.year.name+val.start;
    },
    // 点击季度
    funSeleterQuarter(val) {
      this.formData.type = '2';
      this.formData.quarter = val;
      this.formData.endTime =this.formData.year.name+this.zh(val.end);
      this.formData.startTime = this.formData.year.name+this.zh(val.start);
      // this.formData.endTime = this.goTimeTwe(this.formData.year.name+val.end);
      // this.formData.startTime = this.goTimeTwe(this.formData.year.name+val.start);
    },
    zh(str){
      let arr = str.split('-');
      return arr.join('');
    },
    funGoto(type) {
      this.storageSet('endTime', this.formData.endTime || '');
      this.storageSet('startTime', this.formData.startTime || '');
      this.goto('/medicalRecords');
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
      if ((this.formData.startTime && !this.formData.endTime) || (!this.formData.startTime && this.formData.endTime)) {
        this.$message.error('请完成时间区间选择');
        return;
      }
      let pramse = {
        // type: this.formData.type, //按年/按月/按季
        // type_id: type_id,
        // year:this.formData.year.name,
        //  quarter:this.formData.quarter.name,
        //  month:this.formData.month.name,
        start_time: this.formData.startTime, //开始时间 时间戳or时间
        end_time: this.formData.endTime, //结束时间
        qa_status: '1',
      };
      this.$axios.post('/homeCensus', pramse).then(res => {
        console.log('平均', res);
        this.homeData = res.data;
        // let chartsData = [res.data.before, res.data.last, res.data.new];
        let year = [res.data.before.year, res.data.last.year, res.data.new.year];
        //         * 'averageError' => '病案数量',
        let newData1 = [res.data.before.averageError, res.data.last.averageError, res.data.new.averageError];
        let newData2 = [res.data.before.averageScore, res.data.last.averageScore, res.data.new.averageScore];
        // let newData3 = [res.data.before.outstanding, res.data.last.outstanding, res.data.new.outstanding];
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
              // axisLabel: {
              //   formatter: "value",
              // },
            },
            // {
            //   type: 'value',
            //   name: '得分',
            //   // axisLabel: {
            //   //   formatter: "value",
            //   // },
            // },
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
            // {
            //   name: '平均得分',
            //   type: 'line',
            //   yAxisIndex: 1,
            //   tooltip: {
            //     valueFormatter: function (value) {
            //       return value;
            //     },
            //   },
            //   label: {
            //     show: true,
            //     position: 'top',
            //   },
            //   data: newData3,
            // },
          ],
        });
      });
    },
    // https://echarts.apache.org/examples/zh/editor.html?c=mix-line-bar
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
        // type_id: type_id,
        year: this.formData.year.name,
        quarter: this.formData.quarter.name,
        month: this.formData.month.name,
        start_time: this.formData.startTime, //开始时间 时间戳or时间
        end_time: this.formData.endTime, //结束时间
        qa_status: '1',
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
        // for (let item in this.errorDataList) {
        //   myChartData.push({
        //     value: this.errorDataList[item].count,
        //     name: this.errorDataList[item].field,
        //   });
        // }
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
  text-align: right;
}
.block {
  margin-bottom: 16px;
  background: #fff;
  padding: 25px 15px;
  border-radius: 5px;
  .blockCon {
    // display: flex;
    justify-content: space-between;
    .lefts {
      display: flex;
      align-items: center;
      justify-content: space-between;
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
    .left {
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
              font-size: 16px;
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
.color-btn {
  color: #409eff;
  border-color: #c6e2ff;
  background-color: #ecf5ff;
}
.blue{
  // font-size: 20px;
  font-weight: 600;
  color: #409eff;
  text-decoration:underline;
}
</style>
