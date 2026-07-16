<template>
  <div class="pages">
    <!-- <div class="btnNAv">
      <a href="javascript:;" class="bj">编辑</a>
      <a href="javascript:;" class="bc">保存</a>
      <a href="javascript:;" class="dc">导出详情</a>
      <a href="javascript:;" class="fh">返回</a>
    </div> -->
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
            <el-button class="btn1" type="primary" @click="funQuery">查询</el-button>
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
            <div class="i" @click="funGoto">
              <div class="ba">病案数量</div>
              <div class="num">{{ homeData.total }}</div>
              <!-- <img class="icon" src="@/assets/404_images/404_cloud.png" alt="" /> -->
            </div>
            <div class="i" style="background: #af8af1">
              <div class="ba">缺陷病案占比</div>
              <div class="num">{{ homeData.averageError }}%</div>
              <!-- <img class="icon" src="@/assets/404_images/404_cloud.png" alt="" /> -->
            </div>
            <div class="i" @click="funGototo()" style="background: #38a1f1">
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
              <span class="t">优（>90分）</span>
              <span class="rt">{{ homeData.highest_score }}</span>
            </div>
            <div class="i" style="position: relative">
              <!-- <img class="icon" src="@/assets/404_images/404_cloud.png" alt="" /> -->
              <div class="t">中</div>
              <div class="rt" style="color: #ff0000">
                {{ homeData.middle ? homeData.middle : 0 }}
              </div>
              <!-- <span class="textMsg" style="color: #409eff">
                <i class="el-icon-s-order" style="color: #409eff"></i>
                <el-popover placement="top-start" title="" width="245" trigger="hover">
                  <div slot v-html="testMsg"></div>
                  <span slot="reference">等级计算说明</span>
                </el-popover>
              </span> -->
            </div>
            <div class="i">
              <!-- <img class="icon" src="@/assets/404_images/404_cloud.png" alt="" /> -->
              <span class="t">良（70-90分）</span>
              <span class="rt" style="color: #1da436">
                {{ homeData.good }}
              </span>
            </div>
            <div class="i">
              <!-- <img class="icon" src="@/assets/404_images/404_cloud.png" alt="" /> -->
              <span class="t">差（&lt;70分）</span>
              <span class="rt" style="color: #ff0000">
                {{ homeData.minimum_score }}
              </span>
            </div>
            <div class="textMsg" style="color: #409eff">
            <i class="el-icon-s-order" style="color: #409eff"></i>
            <el-popover placement="top-start" title="" width="245" trigger="hover">
              <div slot v-html="testMsg"></div>
              <span slot="reference">等级计算说明</span>
            </el-popover>
          </div>
          </div>
        </div>
        <div class="right">
          <div id="myChart" style="width: 100%; height: 200px"></div>
        </div>
      </div>
    </div>

    <div class="chart">
      <Title :title="'科室排名'" />
      <div id="myChart1" style="width: 100%; height: 600px"></div>
    </div>
    <div class="chart">
      <Title :title="'主诊组排名'" />
      <div id="myChart2" style="width: 100%; height: 600px"></div>
    </div>
    <div class="chart">
      <Title :title="'主治医师排名'" />
      <div id="myChart3" style="width: 100%; height: 500px"></div>
    </div>
    <div class="chart">
      <Title :title="'住院医师排名'" />
      <div id="myChart4" style="width: 100%; height: 500px"></div>
    </div>
    <div class="chart">
      <Title :title="'编码员排名'" />
      <div id="myChart5" style="width: 100%; height: 500px"></div>
    </div>
  </div>
</template>

<script>
import * as echarts from 'echarts';
import { mapGetters } from 'vuex';
import Title from '@/components/Title';
export default {
  components: {
    Title,
  },
  name: 'Dashboard',
  computed: {
    ...mapGetters(['name']),
  },
  data() {
    return {
      testMsg: '优：≥97分；</br>' + '良：90~96分且不出现A类错误；</br>' + '中：75~89分且不出现A类错误；</br>' + '差：＜75分。',
      formData: {
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
        problem: 'all',
        defectFelg: 'all',
        type: '1',
      },
      homeData: {},
      quarterList: [],
      monthList: [],
      yearList: [],
    };
  },
  mounted() {
    this.storageSet('endTime', '');
    this.storageSet('startTime', '');
    // 默认获取当前本年日期
    this.getdaTe();
    this.formData.chooseDate = '30';
    this.chooseTime(this.formData.chooseDate);
    this.funQuery();
    this.selectInfo();
  },
  methods: {
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
    // 点击重置条件
    getResult() {
      this.formData.year.name = '';
      this.formData.month.name = '';
      this.formData.type = '1';
      this.formData.quarter.name = '';
      this.formData.startTime = '';
      this.formData.endTime = '';
      this.funQuery();
      this.selectInfo()
    },
    // 点击缺陷病案
    funGototo(){
      this.storageSet('endTime', this.formData.endTime || '');
      this.storageSet('startTime', this.formData.startTime || '');
      this.goto('/defectList')
    },
    funGoto(){
      this.storageSet('endTime', this.formData.endTime || '');
      this.storageSet('startTime', this.formData.startTime || '');
      this.goto('/medicalRecords')
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
      // end
      // start
      // let data = this.goTimeTwe(val.end)
      // console.log(data)
      this.formData.endTime = this.goTimeTwe(val.end);
      this.formData.startTime = this.goTimeTwe(val.start);
    },
    // 点击月份下下拉框
    funSeleterMonth(val) {
      this.formData.month = val;
      this.formData.type = '3';
          this.formData.endTime =this.formData.year.name+val.end;
      this.formData.startTime = this.formData.year.name+val.start;
    },
    // 点击按季度下拉框
    funSeleterQuarter(val) {
      this.formData.type = '2';
      this.formData.quarter = val;
           this.formData.endTime =this.formData.year.name+this.zh(val.end);
      this.formData.startTime = this.formData.year.name+this.zh(val.start);
    },
    zh(str){
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
      let type_id = '';
      if (this.formData.type == '1') {
        type_id = this.formData.year.id || '';
      } else if (this.formData.type == '2') {
        type_id = this.formData.quarter.id;
      } else {
        type_id = this.formData.month.id;
      }
      let pramse = {
        // type: this.formData.type, //按年/按月/按季
        // type_id: type_id,
        start_time: this.formData.startTime, //开始时间 时间戳or时间
        end_time: this.formData.endTime, //结束时间
      };
      this.initCharts1(pramse);
      this.initCharts2(pramse);
      this.initCharts3(pramse);
      this.initCharts4(pramse);
      this.initCharts5(pramse);
      this.funAggregate(pramse);
    },
    funAggregate(pramse) {
      this.$axios.post('/homeCensus', pramse).then(res => {
        console.log(res);
        this.homeData = res.data;
        // let chartsData = [res.data.before, res.data.last, res.data.new];
        let year = [res.data.before.year, res.data.last.year, res.data.new.year];
        //         * 'averageError' => '病案数量',
        let newData1 = [res.data.before.averageError, res.data.last.averageError, res.data.new.averageError];
        let newData2 = [res.data.before.averageScore, res.data.last.averageScore, res.data.new.averageScore];
        let newData3 = [res.data.before.averageScore, res.data.last.averageScore, res.data.new.averageScore];
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
    initCharts1(pramse) {
      this.$axios.post('/ranking_department', pramse).then(res => {
        console.log(res.data.list);
        let dataName = [];
        let dataFleg = [];
        const total_error_medical = [];
        let dataNum = [];
        for (let item in res.data.list) {
          dataName.push(res.data.list[item].name);
          dataFleg.push(res.data.list[item].total_medical);
          dataNum.push(res.data.list[item].item);
          total_error_medical.push(res.data.list[item].total_error_medical);
        }
        let myChart = echarts.init(document.getElementById('myChart1'));
        window.addEventListener('resize', function () {
          myChart.resize();
        });
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
              name: '病案数',
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
              name: '缺陷病案数',
              backgroundStyle: {
                color: 'rgba(180, 180, 180, 0.2)',
              },
            },
          ],
        });
      });
    },
    initCharts2(pramse) {
      this.$axios.post('/ranking_attending_group', pramse).then(res => {
        const dataName = [];
        const dataFleg = [];
        const total_error_medical = [];
        // const dataNum = [];
        for (const item in res.data) {
          dataName.push(res.data[item].name);
          dataFleg.push(res.data[item].total_medical);
          total_error_medical.push(res.data[item].total_error_medical);
          // dataNum.push(res.data[item].item);
        }
        const myChart = echarts.init(document.getElementById('myChart2'));
        window.addEventListener('resize', function () {
          myChart.resize();
        });
        myChart.setOption({
          toolbox: {
            feature: {
              saveAsImage: {
                name: '主诊组排名',
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
              name: '病案数',
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
              name: '缺陷病案数',
              backgroundStyle: {
                color: 'rgba(180, 180, 180, 0.2)',
              },
            },
          ],
        });
      });
    },
    initCharts3(pramse) {
      this.$axios.post('/ranking_indications', pramse).then(res => {
        let pramseMode = [['product', '病案数', '问题病案数']];
        for (let item in res.data.list) {
          // dataName.push(res.data[item].name);
          // dataFleg.push(res.data[item].total_medical);
          // dataNum.push(res.data[item].total_error_medical);
          pramseMode.push([res.data.list[item].name, res.data.list[item].total_medical, res.data.list[item].total_error_medical]);
        }
        console.log(res);
        let myChart = echarts.init(document.getElementById('myChart3'));
        window.addEventListener('resize', function () {
          myChart.resize();
        });
        myChart.setOption({
          toolbox: {
            feature: {
              saveAsImage: {
                name: '主治医师排名',
              },
            },
          },
          legend: {},
          tooltip: {},
          dataset: {
            source: pramseMode,
          },
          // grid: {
          //   left: "3%",
          //   right: "3%",
          //   bottom: "3%",
          //   containLabel: true,
          // },
          xAxis: { type: 'category' },
          yAxis: {},
          series: [
            {
              label: {
                show: true,
                position: 'top',
              },
              barMaxWidth: 30,
              type: 'bar',
            },
            {
              label: {
                show: true,
                position: 'top',
              },
              barMaxWidth: 30,
              type: 'bar',
            },
          ],
        });
      });
    },
    initCharts4(pramse) {
      this.$axios.post('/ranking_hospital', pramse).then(res => {
        let pramseMode = [['product', '病案数', '问题病案数']];
        for (let item in res.data.list) {
          // dataName.push(res.data[item].name);
          // dataFleg.push(res.data[item].total_medical);
          // dataNum.push(res.data[item].total_error_medical);
          pramseMode.push([res.data.list[item].hospital_name, res.data.list[item].total_medical, res.data.list[item].total_error_medical]);
        }
        let myChart = echarts.init(document.getElementById('myChart4'));
        window.addEventListener('resize', function () {
          myChart.resize();
        });
        myChart.setOption({
          toolbox: {
            feature: {
              saveAsImage: {
                name: '住院医师排名',
              },
            },
          },
          legend: {},
          tooltip: {},
          dataset: {
            source: pramseMode,
          },
          grid: {
            left: '3%',
            right: '3%',
            bottom: '3%',
            containLabel: true,
          },
          xAxis: { type: 'category' },
          yAxis: {},
          series: [
            {
              label: {
                show: true,
                position: 'top',
              },
              barMaxWidth: 30,
              type: 'bar',
            },
            {
              label: {
                show: true,
                position: 'top',
              },
              barMaxWidth: 30,
              type: 'bar',
            },
          ],
        });
      });
    },
    initCharts5(pramse) {
      this.$axios.post('/ranking_coder', pramse).then(res => {
        let pramseMode = [];
        for (let item in res.data.list) {
          // dataName.push(res.data[item].name);
          // dataFleg.push(res.data[item].total_medical);
          // dataNum.push(res.data[item].total_error_medical);
          pramseMode.push({
            product: res.data.list[item].name,
            病案数: res.data.list[item].total_medical,
            问题病案数: res.data.list[item].total_error_medical,
          });
        }
        console.log(pramseMode);
        console.log('liuyucheng');
        let myChart = echarts.init(document.getElementById('myChart5'));
        window.addEventListener('resize', function () {
          myChart.resize();
        });
        myChart.setOption({
          toolbox: {
            feature: {
              saveAsImage: {
                name: '编码员排名',
              },
            },
          },
          legend: {},
          tooltip: {},
          dataset: {
            dimensions: ['product', '病案数', '问题病案数'],
            source: pramseMode,
          },
          grid: {
            left: '3%',
            right: '3%',
            bottom: '3%',
            containLabel: true,
          },
          xAxis: { type: 'category' },
          yAxis: {},
          series: [
            {
              label: {
                show: true,
                position: 'top',
              },
              barMaxWidth: 30,
              type: 'bar',
            },
            {
              label: {
                show: true,
                position: 'top',
              },
              barMaxWidth: 30,
              type: 'bar',
            },
          ],
        });
      });
    },
  },
};
</script>

<style lang="scss" scoped>
.pages {
  // margin: 0 auto;
  // width: 1200px;
  margin: 0 auto;
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
    margin-bottom: 20px;
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
    .ytext {
      font-size: 16px;
      color: #e48d53;
      font-weight: 400;
      line-height: 40px;
      text-align: right;
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
            cursor: pointer;
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
          margin: 0 9% 0 0;
          .i {
            width: 180px;
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
