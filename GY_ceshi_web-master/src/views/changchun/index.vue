<template>
  <div class="pages">
    <div class="block">
      <div class="blockCon">
        <div class="lefts">
          <el-dropdown>
            <el-button :class="formData.year.name ? 'color-btn' : ''">
              {{ formData.year.name || '按年' }}
              <i class="el-icon-arrow-down el-icon--right"></i>
            </el-button>
            <el-dropdown-menu slot="dropdown">
              <el-dropdown-item v-for="(item, index) in yearList" :key="index" @click.native="funSeleterYear(item)">
                {{ item.name }}
              </el-dropdown-item>
            </el-dropdown-menu>
          </el-dropdown>

          <el-dropdown>
            <el-button :disabled="formData.year.name == ''" :class="formData.quarter.name ? 'color-btn' : ''">
              {{ formData.quarter.name || '按季' }}
              <i class="el-icon-arrow-down el-icon--right"></i>
            </el-button>
            <el-dropdown-menu slot="dropdown">
              <el-dropdown-item v-for="(item, index) in quarterList" :key="index"
                                @click.native="funSeleterQuarter(item)">{{ item.name }}
              </el-dropdown-item>
            </el-dropdown-menu>
          </el-dropdown>
          <div class="selects">
            <el-date-picker v-model="formData.startTime" type="date" format="yyyy 年 MM 月 dd 日"
                            value-format="yyyyMMdd" placeholder="开始日期"></el-date-picker>

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

    <!-- 缺陷问题 -->
    <div class="bg-card" style="margin-bottom: 24px; padding">
      <!-- <Title :title="'缺陷问题'" /> -->
      <CardTitle title="缺陷问题">
        <el-image
          class="title_arrow"
          :class="{'arrow_top': !quxian_show}"
          :src="require('../../assets/images/arrow-down.png')"
          fit="contain"
          @click="onToggleQuexianShow">
        </el-image>
      </CardTitle>
      <el-collapse-transition>
        <div v-show="quxian_show">
          <el-form :inline="true" :model="caseSearchData" class="demo-form-inline">
            <el-form-item label="">
              <el-select v-model="caseSearchData.department" filterable clearable placeholder="科室">
                <el-option v-for="(item, index) in departmentList" :label="item.name" :value="item.name"
                           :key="index"></el-option>
              </el-select>
            </el-form-item>
            <el-form-item>
              <el-button type="primary" @click="getCaseList">查询</el-button>
            </el-form-item>
          </el-form>
          <ProblemTableBoxVue :data="caseList"/>
        </div>
      </el-collapse-transition>


    </div>
  </div>
</template>

<script>
import {mapGetters} from 'vuex';
import Title from '@/components/Title';
import ProblemTableBoxVue from './components/ProblemTableBox.vue';
import MedicalRecordTableBoxVue from './components/MedicalRecordTableBox.vue';
import {medicalRecordDoctorExport} from '@/api/excel'

export default {
  components: {
    Title,
    ProblemTableBoxVue,
    MedicalRecordTableBoxVue,
  },
  name: 'Dashboard',
  computed: {
    ...mapGetters(['name'])
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
      caseSearchData: {
        department: ''
      },
      caseList: [],
      departmentList: [],
      doctorList: [], // 医生列表
      doctor_name: '',
      quxian_show: true,
      // 医师排名
      doctor_show: true,
      doctor_tableData: [],
      // 分页数据
      paginationDataDoctor: {
        page: 1,
        size: 10,
        total: 0
      },
    };
  },
  mounted() {
    this.storageSet('start_time', '');
    this.storageSet('end_time', '');
    this.formData.chooseDate = '30';
    this.chooseTime(this.formData.chooseDate);
    if (this.storageGet('homeFrom')) {
      this.formData = this.storageGet('homeFrom');
      this.storageRemove('homeFrom');
    }
    this.getDepartmentList()
    this.funQuery();
    this.selectInfo();
  },
  methods: {
    onToggleQuexianShow() {
      this.quxian_show = !this.quxian_show
    },
    // 获取部门集合
    getDepartmentList() {
      this.$axios.post('/get_omr_department_list').then(res => {
        this.departmentList = res.data;
      });
    },
    // 获取缺陷问题
    getCaseList() {
      let pramse = {
        start_time: this.formData.startTime,
        end_time: this.formData.endTime,
        department: this.caseSearchData.department
      };
      this.$axios.post('/case-quality/defect_issues', pramse).then(res => {
        this.caseList = res.data.list
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
      let type_id = '';
      if (this.formData.type == '1') {
        type_id = this.formData.year.id || '';
      } else if (this.formData.type == '2') {
        type_id = this.formData.quarter.id;
      } else {
        type_id = this.formData.month.id;
      }
      let pramse = {
        start_time: this.formData.startTime,
        end_time: this.formData.endTime,
      };
      this.storageSet('start_time', this.formData.startTime);
      this.storageSet('end_time', this.formData.endTime);
      this.storageSet('homeFrom', this.formData);
      this.getCaseList();
    }
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

.title-box {
  width: 100%;
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.medicalRecord-box {
  width: 100%;
  display: flex;
  margin-top: 16px;

  & > div {
    flex: 1;
  }

  .medicalRecord-list-box {
    flex: 1;
  }
}

// 卡片背景

.pages {
  .bg-card {
    padding: 20px;
    background: #fff;
    border-radius: 5px;
    overflow-x: hidden;
  }

  .mb20 {
    margin-bottom: 20px;
  }

  .bg185DA6 {
    background: #185DA6;
  }

  .text-right {
    text-align: right;
  }

  .text-center {
    text-align: center;
  }

  .link {
    text-decoration: underline;
    color: #FF786F;
    cursor: pointer;
  }

  .link2 {
    color: #004983;
    cursor: pointer;
  }

  .c_FF786F {
    color: #FF786F;
  }
}

.title_arrow {
  width: 10px;
  height: 11px;
  margin-left: 5px;
  cursor: pointer;

  &.arrow_top {
    transform: rotate(180deg);
  }
}

#tongji_pie {
  height: 200px;
}

#qxxq_pie {
  height: 600px;
  margin-top: 68px;
}

.xz-btn {
  width: 84px;
  height: 32px;
  text-align: center;
  line-height: 32px;
  background: #185DA6;
  border-radius: 6px;
  color: #fff;
  font-size: 14px;
  cursor: pointer;
  position: absolute;
  right: 20px;
  top: 0;
}
</style>
