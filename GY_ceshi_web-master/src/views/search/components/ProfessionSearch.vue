<template>
  <div>
    <div class="content-box">
      <!-- 病历搜索 -->
      <div class="search-box">
        <div class="row-box">
          <el-row v-for="(item, index) of formInline2.field" :key="index" style="margin-bottom: 16px">
            <el-col :span="4">
              <el-select v-model="item.select_type" filterable placeholder="请选择">
                <el-option label="且" :value="0"></el-option>
                <el-option label="或" :value="1"></el-option>
                <el-option label="不包含" :value="2"></el-option>
              </el-select>
            </el-col>
            <el-col :span="7">
              <el-cascader
                v-model="item.key"
                :options="bl"
                ref="cascader"
                clearable
                filterable
                :show-all-levels="true"
                :props="{
                  label: 'name',
                  value: 'key',
                  expandTrigger: 'hover',
                  emitPath: false,
                }"
                @change="handleCascaderChange(item, index)"
              ></el-cascader>
            </el-col>
            <el-col :span="9">
              <el-input
                v-if="
                  item.type === 'input' &&
                  !['MD_ICD10_NAME', 'OD_ICD10_NAME', 'MD_ICD10_ID1', 'OD_ICD10_ID1', 'MO_ICD9_NAME', 'ICD09_NAME', 'MO_ICD9_ID1', 'ICD9_ID1'].includes(item.key)
                "
                v-model="item.value"
                clearable
                placeholder="请输入"
              ></el-input>
              <el-date-picker
                v-if="item.type === 'time'"
                v-model="item.value"
                type="datetimerange"
                range-separator="至"
                value-format="timestamp"
                :default-time="['00:00:00', '23:59:59']"
                start-placeholder="开始日期"
                end-placeholder="结束日期"
                style="width: 100%"
              ></el-date-picker>
              <el-select v-if="item.type === 'select'" v-model="item.value" placeholder="请选择" style="width: 100%" filterable>
                <el-option v-for="(sItem, sIndex) of item.selects" :key="sIndex" :label="sItem.value" :value="sItem.name"></el-option>
              </el-select>

              <div
                v-if="
                  item.type === 'range' ||
                  item.key === 'AAA29' ||
                  item.key === 'AAA04' ||
                  item.key === 'AAA40' ||
                  item.key === 'AAC04' ||
                  item.key === 'ADA0101' ||
                  item.key === 'ADA0101' ||
                  item.key === 'FYSL' ||
                  item.key === 'FYDJ' ||
                  item.key === 'ZJE'
                "
                style="width: 100%; display: flex; gap: 5px"
              >
                <el-input placeholder="最小" v-model="item.min"></el-input>
                <el-input placeholder="最大" v-model="item.max"></el-input>
              </div>
              <div v-if="['MD_ICD10_NAME', 'OD_ICD10_NAME', 'MD_ICD10_ID1', 'OD_ICD10_ID1', 'MO_ICD9_NAME', 'ICD09_NAME', 'MO_ICD9_ID1', 'ICD9_ID1'].includes(item.key)">
                <big-data-remote-select
                  className="width100"
                  :ref="`bigDataRemoteSelectRef_${index}`"
                  v-model="item.value"
                  :api-url="isContainICU(item.key)"
                  placeholder="请选择"
                ></big-data-remote-select>
              </div>
            </el-col>
            <el-col :span="4">
              <el-button type="primary" icon="el-icon-minus" :disabled="formInline2.field.length === 1" @click="onMinus(index)" style="margin-left: 16px"></el-button>
              <el-button type="primary" icon="el-icon-plus" @click="onPlus"></el-button>
            </el-col>
          </el-row>
        </div>
      </div>
      <!-- 信息搜索 -->
      <div class="info-search">
        <el-form :inline="true" :model="formInline" class="demo-form-inline" label-width="68px">
          <el-form-item label="住院号码">
            <el-input v-model="formInline.AAA28" clearable placeholder="请输入"></el-input>
          </el-form-item>
          <el-form-item label="出院科室">
            <el-select v-model="formInline.AAC02C" multiple collapse-tags clearable filterable :disabled="AAC11N_lock" placeholder="请选择">
              <el-option v-for="(item, index) in departmentList" :key="index" :label="item.name" :value="item.id" />
            </el-select>
          </el-form-item>
          <el-form-item label="出院时间">
            <el-date-picker
              v-model="formInline.AAC01_START"
              type="date"
              format="yyyy年MM月dd日"
              value-format="timestamp"
              placeholder="开始日期"
              :picker-options="pickerOptions"
              style="margin-right: 10px"
            />
            <el-date-picker v-model="formInline.AAC01_END" type="date" format="yyyy年MM月dd日" value-format="timestamp" placeholder="结束日期" :picker-options="pickerOptions" />
          </el-form-item>

          <el-form-item label="" class="more-btn">
            <el-button type="text" @click="showMore = !showMore">{{ showMore ? '收回' : '更多' }}</el-button>
          </el-form-item>
        </el-form>
        <el-collapse-transition>
          <div v-show="showMore" style="width: 1124px; margin: 0 auto">
            <el-form :inline="true" :model="formInline2" class="demo-form-inline2" label-width="68px">
              <el-form-item label="姓名">
                <el-input v-model="formInline2.AAA01" clearable placeholder="请输入"></el-input>
              </el-form-item>
              <el-form-item label="性别">
                <el-select v-model="formInline2.AAA02C" clearable filterable placeholder="请选择">
                  <el-option v-for="(item, index) in sexs" :key="index" :label="item.name" :value="item.id" />
                </el-select>
              </el-form-item>
              <el-form-item label="入院时间">
                <el-date-picker
                  v-model="formInline2.AAB01_START"
                  type="date"
                  format="yyyy年MM月dd日"
                  value-format="timestamp"
                  placeholder="开始日期"
                  :picker-options="pickerOptions"
                  style="margin-right: 10px"
                />
                <el-date-picker
                  v-model="formInline2.AAB01_END"
                  type="date"
                  format="yyyy年MM月dd日"
                  value-format="timestamp"
                  placeholder="结束日期"
                  :picker-options="pickerOptions"
                />
              </el-form-item>
              <el-form-item label="住院天数">
                <el-input placeholder="起始天数" v-model="formInline2.AAC04_START" clearable style="width: 230px; margin-right: 10px">
                  <template slot="append">天</template>
                </el-input>
                <el-input placeholder="终止天数" v-model="formInline2.AAC04_END" clearable style="width: 230px">
                  <template slot="append">天</template>
                </el-input>
              </el-form-item>
              <el-form-item label="年龄">
                <el-input placeholder="起始年龄" v-model="formInline2.ageStart" clearable style="width: 230px; margin-right: 10px">
                  <el-select v-model="formInline2.ageType" slot="append" placeholder="请选择">
                    <el-option label="天" :value="1"></el-option>
                    <el-option label="岁" :value="2"></el-option>
                  </el-select>
                </el-input>
                <el-input placeholder="终止年龄" v-model="formInline2.ageEnd" clearable style="width: 230px">
                  <el-select v-model="formInline2.ageType" slot="append" placeholder="请选择">
                    <el-option label="天" :value="1"></el-option>
                    <el-option label="岁" :value="2"></el-option>
                  </el-select>
                </el-input>
              </el-form-item>
              <el-form-item label="体温">
                <el-input placeholder="起始体温" v-model="formInline2.TIWEN_START" clearable style="width: 230px; margin-right: 10px">
                  <template slot="append">℃</template>
                </el-input>
                <el-input placeholder="终止体温" v-model="formInline2.TIWEN_END" clearable style="width: 230px; margin-right: 10px">
                  <template slot="append">℃</template>
                </el-input>
                <el-select v-model="formInline2.TIWEN_FIELD" multiple collapse-tags style="margin-left: 20px; width: 240px" placeholder="请选择">
                  <el-option v-for="item in tiwens" :key="item.id" :label="item.name" :value="item.id"></el-option>
                </el-select>
              </el-form-item>
            </el-form>
          </div>
        </el-collapse-transition>
        <div class="btn-group">
          <el-button type="primary" class="search-btn" @click="onSearch">检 索</el-button>
          <div class="btn-group-right">
            <el-button @click="onReset">重置条件</el-button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { min } from 'moment';
import BigDataRemoteSelect from '@/components/BigDataRemoteSelect';
export default {
  components: { BigDataRemoteSelect },
  data() {
    return {
      sexs: [
        {
          id: 1,
          name: '男',
        },
        {
          id: 2,
          name: '女',
        },
        {
          id: 0,
          name: '未知的性别',
        },
        {
          id: 9,
          name: '未说明的性别',
        },
      ],
      pickerOptions: {
        disabledDate(time) {
          return time.getTime() > Date.now();
        },
      },
      bl: [],
      tiwens: [],
      formInline: {
        AAA28: '',
        AAC02C: '',
        AAC01_START: '',
        AAC01_END: '',
      },
      formInline2: {
        AAA01: '',
        AAA02C: '',
        AAB01_START: '',
        AAB01_END: '',
        AAC04_START: '',
        AAC04_END: '',
        ageStart: '', // 自定义需根据ageType转化相应字段
        ageEnd: '', // 自定义需根据ageType转化相应字段
        ageType: 2,
        TIWEN_START: '',
        TIWEN_END: '',
        TIWEN_FIELD: '',
        field: [
          {
            select_type: 0,
            type: 'input',
            key: '',
            selects: '',
            value: '',
            min: '',
            max: '',
          },
        ],
      },
      departmentList: [],
      showMore: false,
      AAC11N_lock: false,
    };
  },
  created() {
    this.getSelects();
    this.getTiwensSelects();
    this.selectInfo();
  },
  methods: {
    isContainICU(key) {
      //诊断名称
      if (['MD_ICD10_NAME', 'OD_ICD10_NAME'].includes(key)) {
        return '/icd10DiagnosisList';
      }
      //诊断编码
      if (['MD_ICD10_ID1', 'OD_ICD10_ID1'].includes(key)) {
        return '/icd10DiagnosisCodeList';
      }

      //手术名称
      if (['MO_ICD9_NAME', 'ICD09_NAME'].includes(key)) {
        return '/icd09OperationNameList';
      }

      //手术编码
      if (['MO_ICD9_ID1', 'ICD9_ID1'].includes(key)) {
        return '/icd09OperationCodeList';
      }
    },
    // 获取选择得项
    handleCascaderChange(item, index) {
      console.log('this.formInline2.field', item, this.formInline2.field);
      const targetKeyList = ['MD_ICD10_NAME', 'OD_ICD10_NAME', 'MD_ICD10_ID1', 'OD_ICD10_ID1', 'MO_ICD9_NAME', 'ICD09_NAME', 'MO_ICD9_ID1', 'ICD9_ID1'];

      if (targetKeyList.includes(item.key)) {
        this.$nextTick(() => {
          const refName = `bigDataRemoteSelectRef_${index}`;
          const targetSelect = this.$refs[refName];
          const selectInstance = Array.isArray(targetSelect) ? targetSelect[0] : targetSelect;
          selectInstance.currentKeyword = '';
          selectInstance.initData();
        });
      }

      this.$nextTick();

      const node = this.$refs.cascader[this.$refs.cascader.length - 1].getCheckedNodes(false);
      this.$set(this.formInline2.field[index], 'type', node[0].data.type);
      const timeVal = node[0].data.type === 'time' ? [] : undefined;
      this.$set(this.formInline2.field[index], 'value', timeVal);
      this.$set(this.formInline2.field[index], 'selects', node[0].data.value);
    },
    // 获取select条件
    getSelects() {
      this.$axios3.post('/bl/serach_where', {}).then(res => {
        this.bl = res.data.bl || [];
        if (res.data.dep_name) {
          this.formInline.AAC02C = res.data.dep_name;
          this.AAC11N_lock = true;
        }
      });
    },
    getTiwensSelects() {
      this.$axios3.post('/bl/tiwenWhere', {}).then(res => {
        this.tiwens = res.data || [];
      });
    },
    // 获取科室
    selectInfo() {
      this.$axios.post('/selectInfo').then(res => {
        this.departmentList = res.data.department;
      });
    },
    // 新增一行
    onPlus() {
      this.formInline2.field.push({
        select_type: 0,
        value: '',
        type: 'input',
        selects: [],
        key: '',
        min: '',
        max: '',
      });
    },
    // 减一行
    onMinus(index) {
      if (index == 0) {
        if (this.formInline2.field.length >= 2) {
          this.formInline2.field.pop();
        }
        return;
      }
      this.formInline2.field.splice(index, 1);
    },
    // 搜索
    onSearch() {
      const { ageType, ageStart, ageEnd, TIWEN_START, TIWEN_END } = this.formInline2;
      if (TIWEN_START) {
        if (TIWEN_START < 0 || TIWEN_START > 60) {
          this.$message.error('体温开始温度（不能小于0，不能大于60）');
          return;
        }
      }
      if (TIWEN_END) {
        if (TIWEN_END < 0 || TIWEN_END > 60) {
          this.$message.error('体温结束温度（不能小于0，不能大于60）');
          return;
        }
      }
      if (TIWEN_START && TIWEN_END) {
        if (Number(TIWEN_START) > Number(TIWEN_END)) {
          this.$message.error('体温开始温度不能大于结束温度');
          return;
        }
      }
      if (ageType === 1) {
        this.formInline2.AAA40_START = ageStart;
        this.formInline2.AAA40_END = ageEnd;
      } else if (ageType === 2) {
        this.formInline2.AAA04_START = ageStart;
        this.formInline2.AAA04_END = ageEnd;
      }
      const params = {
        ...this.formInline,
        ...this.formInline2,
      };
      const { field } = JSON.parse(JSON.stringify(this.formInline2));
      field.map(item => {
        if (item.type === 'time') {
          item.value = item.value.join(',');
        }
      });
      params.field = field;
      this.$emit('search', params);
    },
    // 导出
    onExport() {
      const { ageType, ageStart, ageEnd } = this.formInline2;
      if (ageType === 1) {
        this.formInline2.AAA40_START = ageStart;
        this.formInline2.AAA40_END = ageEnd;
      } else if (ageType === 2) {
        this.formInline2.AAA04_START = ageStart;
        this.formInline2.AAA04_END = ageEnd;
      }
      const params = {
        ...this.formInline,
        ...this.formInline2,
      };
      const { field } = JSON.parse(JSON.stringify(this.formInline2));
      field.map(item => {
        if (item.type === 'time') {
          item.value = item.value.join(',');
        }
      });
      params.field = field;
      this.$emit('export', params);
    },
    // 重置
    onReset() {
      this.formInline = {
        AAA28: '',
        AAC02C: '',
        AAC01_START: '',
        AAC01_END: '',
      };

      this.formInline2 = {
        AAA01: '',
        AAA02C: '',
        AAB01_START: '',
        AAB01_END: '',
        AAC04_START: '',
        AAC04_END: '',
        ageStart: '', // 自定义需根据ageType转化相应字段
        ageEnd: '', // 自定义需根据ageType转化相应字段
        ageType: 2,
        TIWEN_START: '',
        TIWEN_END: '',
        TIWEN_FIELD: '',
        field: [
          {
            select_type: 0,
            key: '',
            value: '',
          },
        ],
      };

      this.$emit('reset');
    },
  },
};
</script>
<style lang="scss">
@import '~@/styles/index.model.scss';
</style>

<style lang="scss" scoped>
.content-box {
  background: #fff;
  border-radius: 5px;
  margin: 20px;
  .search-box {
    .row-box {
      width: 1000px;
      margin: 0 auto 16px;
    }
  }
  .info-search {
    margin-top: 20px;
    .demo-form-inline {
      width: 1124px;
      margin: 0 auto;
      position: relative;
      .more-btn {
        position: absolute;
        right: -70px;
      }
    }
  }
  .search-btn {
    width: 240px;
  }
}
::v-deep .el-input-group__append {
  width: 65px;
}
::v-deep .el-form--inline .el-form-item {
  margin-right: 20px;
}
.btn-group {
  text-align: center;
  position: relative;
  .btn-group-right {
    position: absolute;
    right: 0;
    top: 0;
  }
}
::v-deep .el-cascader {
  width: 100%;
  .el-input {
    .el-input__inner {
      height: 40px !important;
    }
  }
  .el-cascader__tags {
    .el-tag {
      max-width: 70%;
    }
  }
}
</style>