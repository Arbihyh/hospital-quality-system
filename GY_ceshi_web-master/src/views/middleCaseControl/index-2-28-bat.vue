<template>
  <div class="pages">
    <div class="block">
      <div class="blockCon">
        <div class="lefts">
          <!-- <el-dropdown>
            <el-button :class="formData.year && formData.year.name ? 'color-btn' : ''">
              {{ (formData.year && formData.year.name) || '按年' }}
              <i class="el-icon-arrow-down el-icon--right"></i>
            </el-button>
            <el-dropdown-menu slot="dropdown">
              <el-dropdown-item v-for="(item, index) in yearList" :key="index" @click.native="funSeleterYear(item)">{{ item.name }}</el-dropdown-item>
            </el-dropdown-menu>
          </el-dropdown> -->

          <!-- <el-dropdown>
            <el-button :disabled="formData.year && formData.year.name == ''" :class="formData.quarter && formData.quarter.name ? 'color-btn' : ''">
              {{ (formData.quarter && formData.quarter.name) || '按季' }}
              <i class="el-icon-arrow-down el-icon--right"></i>
            </el-button>
            <el-dropdown-menu slot="dropdown">
              <el-dropdown-item v-for="(item, index) in quarterList" :key="index" @click.native="funSeleterQuarter(item)">{{ item.name }}</el-dropdown-item>
            </el-dropdown-menu>
          </el-dropdown> -->
          <!-- <div class="selects">
            <el-date-picker v-model="formData.startTime" type="date" format="yyyy 年 MM 月 dd 日" value-format="yyyyMMdd"
              placeholder="开始日期"></el-date-picker>

            <el-date-picker v-model="formData.endTime" type="date" style="margin-left: 10px" format="yyyy 年 MM 月 dd 日"
              value-format="yyyyMMdd" placeholder="结束日期"></el-date-picker>
          </div> -->
          <!-- <el-button class="btn1" type="primary" @click="funQuery">查询</el-button> -->
          <el-form :model="formData" ref="filterFormRef">
            <el-row>
              <el-col :span="8">
                <el-form-item label-width="80px" label="入院时间" prop="startTime">

                  <div style="width: 94%;display:flex;gap:5px">
                    <el-form-item prop="startTime">
                      <el-date-picker style="width: 100%" v-model="formData.startTime" type="date" placeholder="出院开始日期"
                        :picker-options="pickerOptions" value-format="yyyyMMdd" format="yyyy年MM月dd日">
                      </el-date-picker>
                    </el-form-item>
                    <el-form-item prop="endTime">
                      <el-date-picker style="width: 100%" v-model="formData.endTime" type="date" placeholder="出院结束日期"
                        :picker-options="[]" value-format="yyyyMMdd" format="yyyy年MM月dd日">
                      </el-date-picker>
                    </el-form-item>
                  </div>

                </el-form-item>
              </el-col>
              <el-col :span="4">
                <el-form-item label-width="80px" label="患者状态" prop="status">
                  <el-select v-model="formData.status" placeholder="请选择">
                    <el-option v-for="item in patientStatus" :key="item.value" :label="item.label"
                      :value="item.value"></el-option>
                  </el-select>
                </el-form-item>
              </el-col>

              <el-col :span="4">
                <el-form-item label-width="80px" label="病案号" prop="bah">
                  <el-input style="width: 94%" placeholder="请输入病案编号" v-model="formData.bah" clearable></el-input>
                </el-form-item>
              </el-col>

              <el-col :span="4">
                <el-form-item label-width="80px" label="病人科室" prop="KS_CODE">
                  <el-cascader style="width: 94%" placeholder="请选择科室" v-model="formData.KS_CODE" :options="ksArray"
                    filterable :props="cascaderProps" clearable collapse-tags></el-cascader>
                </el-form-item>
              </el-col>

              <el-col :span="3">
                <el-form-item>
                  <div style=" margin-left: 20px; width: 94%; display: flex; justify-content: flex-end">
                    <el-button class="btn1" type="primary" @click="funQuery">查询</el-button>
                    <el-button @click="reset">重置</el-button>
                  </div>
                </el-form-item>
              </el-col>
            </el-row>
          </el-form>
        </div>
      </div>
    </div>
    <div class="cardBox">
      <Title :title="'质量分析'" />
      <div class="contentBox">
        <div class="left">
          <div class="l">
            <div class="i" @click="qualityAnalysis()">
              <div class="ba">质控病历数量</div>
              <div class="num cpoin">{{ countsData.case_total }}</div>
            </div>
            <div class="i" @click="defectNum()" style="background: #38a1f1">
              <div class="ba">缺陷病历数量</div>
              <div class="num cpoin">{{ countsData.defect_case_total }}</div>
            </div>
          </div>
        </div>
      </div>
    </div>
    <!-- 科室排名 -->
    <!-- <div class="chart">
      <Title :title="'科室排名'" />
      <div id="myChart1" style="width: 100%; height: 600px"></div>
    </div> -->

    <!-- 缺陷问题 -->
    <div class="chart">
      <Title :title="'缺陷问题'" />
      <!-- <el-form :model="caseListFormData" inline>
        <el-form-item label="科室" prop="dep_id">
          <el-select style="width: 100%" v-model="caseListFormData.dep_id" filterable clearable placeholder="请选择">
            <el-option v-for="item of departments" :key="item.dep_id" :label="item.name" :value="item.dep_id" />
          </el-select>
        </el-form-item>
        <el-form-item label="" prop="">
          <el-button type="primary" @click="onSubmit">查询</el-button>
        </el-form-item>
      </el-form> -->
      <ProblemTableBoxVue :formData="formData" :data="caseList" @onToPage="toPage" />
    </div>
  </div>
</template>

<script>
import * as echarts from 'echarts';
import { mapGetters } from 'vuex';
import Title from '@/components/Title';
import ProblemTableBoxVue from './components/ProblemTableBox.vue';
import moment from 'moment/moment';

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
    const that = this
    return {
      formData: {
        status: '1',
        bah: '',
        KS_CODE: [],
        rangeDate: [],
        chooseDate: '',
        startTime : moment().subtract(3, 'years').format('YYYYMMDD'),
        endTime: moment().format('YYYYMMDD'),
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
      pickerOptions: {
        disabledDate: (time) => {
          if (this.formData.endTime != "") {
            return time.getTime() > Date.now();
          }
        },
        shortcuts: [{
          text: '今天',
          onClick(picker) {
            picker.$emit('pick', moment().format('YYYYMMDD'));
            that.formData.endTime = moment().format('YYYYMMDD')
          }
        }, {
          text: '近7天',
          onClick(picker) {
            picker.$emit('pick', moment().subtract(7, 'days').format('YYYYMMDD'));
            that.formData.endTime = moment().format('YYYYMMDD')
          }
        }, {
          text: '近30天',
          onClick(picker) {
            picker.$emit('pick', moment().subtract(30, 'days').format('YYYYMMDD'));
            that.formData.endTime = moment().format('YYYYMMDD')
          }
        },
        {
          text: '近3年',
          onClick(picker) {
            picker.$emit('pick', moment().subtract(3, 'years').format('YYYYMMDD'));
            that.formData.endTime = moment().format('YYYYMMDD')
          }
        },
        {
          text: '一季度',
          onClick(picker) {
            picker.$emit('pick', moment().startOf('year').format('YYYYMMDD'));
            that.formData.endTime = moment().startOf('year').add(3, 'M').subtract(1, 'days').format('YYYYMMDD')
          }
        }, {
          text: '二季度',
          onClick(picker) {
            picker.$emit('pick', moment().startOf('year').add(3, 'M').format('YYYYMMDD'));
            that.formData.endTime = moment().startOf('year').add(6, 'M').subtract(1, 'days').format('YYYYMMDD')
          }
        }, {
          text: '三季度',
          onClick(picker) {
            picker.$emit('pick', moment().startOf('year').add(6, 'M').format('YYYYMMDD'));
            that.formData.endTime = moment().startOf('year').add(9, 'M').subtract(1, 'days').format('YYYYMMDD')
          }
        }, {
          text: '四季度',
          onClick(picker) {
            picker.$emit('pick', moment().startOf('year').add(9, 'M').format('YYYYMMDD'));
            that.formData.endTime = moment().startOf('year').add(12, 'M').subtract(1, 'days').format('YYYYMMDD')
          }
        }, {
          text: moment().add(-2, 'Y').format("YYYY"),
          onClick(picker) {
            picker.$emit('pick', moment().add(-2, 'Y').startOf('year').format('YYYYMMDD'));
            that.formData.endTime = moment().add(-2, 'Y').endOf('year').format('YYYYMMDD')
          }
        }, {
          text: moment().add(-1, 'Y').format("YYYY"),
          onClick(picker) {
            picker.$emit('pick', moment().add(-1, 'Y').startOf('year').format('YYYYMMDD'));
            that.formData.endTime = moment().add(-1, 'Y').endOf('year').format('YYYYMMDD')
          }
        }, {
          text: moment().format("YYYY"),
          onClick(picker) {
            console.log(that.formData)
            picker.$emit('pick', moment().startOf('year').format('YYYYMMDD'));
            console.log(moment().endOf('year').format('YYYYMMDD'))
            that.formData.endTime = moment().endOf('year').format('YYYYMMDD')
          }
        }]
      },
      cascaderProps: {
        multiple: true,      // 开启多选模式
        label: 'dep_name',
        value: 'dep_id',
        children: 'children',
        checkStrictly: true, // 允许独立选择任意层级
        emitPath: false,     // 是否返回完整路径（true 返回路径数组，false 只返回末节点值）
      },
      ksArray: [],
      homeData: {},
      quarterList: [],
      monthList: [],
      yearList: [],
      countsData: {
        case_total: 0,
        defect_case_total: 0,
      },
      caseList: [],
      caseListFormData: {
        dep_id: '',
      },
      patientStatus: [
        {
          value: '1',
          label: '全部（在院 + 当天出院）',
        },
        {
          value: '2',
          label: '在院',
        },
        {
          value: '3',
          label: '当天出院',
        },
        {
          value: '4',
          label: '常规出院（非当日）',
        },
      ],
      departments: [],
    };
  },
  //  watch: {
  //   'formData.status': {
  //     handler(newVal, oldVal) {
  //       if (newVal !== oldVal) this.saveFormData();
  //     },
  //     deep: true
  //   },
  //   'formData.bah': {
  //     handler(newVal, oldVal) {
  //       if (newVal !== oldVal) this.saveFormData();
  //     },
  //     deep: true
  //   },
  //   'formData.KS_CODE': {
  //     handler(newVal, oldVal) {
  //       if (newVal !== oldVal) this.saveFormData();
  //     },
  //     deep: true
  //   }
  // },
  mounted() {
    this.getSearchOptions()
    this.storageSet('start_time', '');
    this.storageSet('end_time', '');
    this.storageSet('caseControlStatus', '1');
    this.storageSet('caseControlBah', '');
    this.storageSet('caseControlKsCode', []);
    // this.formData.chooseDate = '30';
    // this.chooseTime(this.formData.chooseDate);
    if (this.storageGet('homeFrom')) {
      this.formData = this.storageGet('homeFrom');
      this.storageRemove('homeFrom');
    }
    this.formData.startTime = moment().subtract(3, 'years').format('YYYYMMDD')
    this.formData.endTime = moment().format('YYYYMMDD')
    this.funQuery();
    this.selectInfo();
    this.getDeportmentList();
    this.formData.status = '1'
    
  },
  methods: {
    reset() {
      this.formData.status = '1'
      this.formData.bah = ''
      this.formData.KS_CODE = []
      this.formData.startTime = moment().subtract(3, 'years').format('YYYYMMDD')
      this.formData.endTime = moment().format('YYYYMMDD')
      this.storageSet('caseControlStatus', '1');
      this.storageSet('caseControlBah', '');
      this.storageSet('caseControlKsCode', []);
    },
    getSearchOptions() {
      this.$axios.post('CaseHistory/Terminal/getSearchOptions', {}).then(res => {
        this.ksArray = res.data.ksArray;
      });
    },
    // 抽取公共保存逻辑
    saveFormData(type) {
      this.storageSet('start_time', this.formData.startTime);
      this.storageSet('end_time', this.formData.endTime);
      this.storageSet('caseControlStatus', this.formData.status);
      this.storageSet('caseControlBah', this.formData.bah);
      this.storageSet('caseControlKsCode', this.formData.KS_CODE);
    },
    defectNum() {
      this.saveFormData(1);
      // this.goto('/middleDefectNumber');
      this.$router.push({
        path: '/middleCaseNumber',
        query: {
          is_defect: 1
        },
      });
    },

    qualityAnalysis() {
      this.saveFormData(0);
      // this.goto('/middleCaseNumber');
      this.$router.push({
        path: '/middleCaseNumber',
        query: {
          is_defect: 0
        },
      });
    },

    toPage(row) {
      this.$router.push({
        path: '/middleDefectNumber',
        query: {
          rule_id: row.rule_id + "",
          startTime: this.formData.startTime,
          endTime: this.formData.endTime,
        },
      });
    },
    getDeportmentList() {
      this.$axios
        .get('/user/depDropDown')
        .then(res => {
          const { data } = res;
          this.departments = data;
        })
        .catch(error => {
          console.log(error);
        });
    },
    onSubmit() {
      this.getCaseList();
    },
    getCaseList() {
      let pramse = {
        // ...this.caseListFormData,
        status: this.formData.status,
        AAA28: this.formData.bah,
        dep_id: this.formData.KS_CODE,
        start_time: this.formData.startTime,
        end_time: this.formData.endTime,
      };
      this.$axios2.post('/case-quality/defect_issues', pramse).then(res => {
        this.caseList = res.data.list;
      });
    },
    getCounts() {
      let pramse = {
        status: this.formData.status,
        AAA28: this.formData.bah,
        dep_id: this.formData.KS_CODE,
        start_time: this.formData.startTime,
        end_time: this.formData.endTime,
      };
      this.$axios2.post('/case-quality/analysis', pramse).then(res => {
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
      // this.initCharts1(pramse);
      this.getCounts();
      this.getCaseList();
    },
    initCharts1(pramse) {
      this.$axios2.post('/case-quality/ranking_department', pramse).then(res => {
        let dataName = [];
        let dataFleg = [];
        const total_error_medical = [];
        let dataNum = [];
        for (let item in res.data.list.slice(0, 10)) {
          dataName.push(res.data.list[item].name);
          dataFleg.push(res.data.list[item].total_medical);
          dataNum.push(res.data.list[item].item);
          total_error_medical.push(res.data.list[item].total_error_medical);
        }
        // 销毁上一次实例
        echarts.init(document.getElementById('myChart1')).dispose();
        // 构建新实例
        let myChart = echarts.init(document.getElementById('myChart1'));
        window.addEventListener('resize', function () {
          myChart.resize();
        });
        if (res.data.list.length) {
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
    margin-bottom: 20px;
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
              font-size: 16px;
              font-weight: 400;
              color: #fff;
            }

            .num {
              font-size: 24px;
              font-weight: bold;
              color: #fff;
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
            width: 195px;
            height: 56px;
            background: #eaf4ff;
            border-radius: 4px;
            margin: 0 0 9px 0;
            display: flex;
            align-items: center;
            justify-content: center;

            .icon {
              width: 22px;
              margin: 6px 0 0 27px;
            }

            .t {
              font-size: 18px;
              font-weight: 400;
              color: #333333;
              text-align: left;
              margin-left: 10px;
            }

            .rt {
              margin-left: 10px;
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