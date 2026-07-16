<template>
  <div>
    <el-form style="width: 100%" ref="filterListFormRef" :model="formData" class="demo-form-inline" label-suffix=":"
      label-width="90px">
      <el-row :gutter="24">
        <el-col :span="7">
          <el-radio-group v-model="formData.in_hospital" @input="onSubmit" style="margin-bottom:15px">
            <el-radio-button label="全部"></el-radio-button>
            <el-radio-button label="出院"></el-radio-button>
            <el-radio-button label="在院"></el-radio-button>
          </el-radio-group>
        </el-col>
        <el-col :span="7" :offset="10">
          <el-form-item label="" prop="">
            <el-row type="flex" justify="end">
              <el-button type="text" @click="openCollectListModal" icon="el-icon-star-on">常用查询条件</el-button>
            </el-row>
          </el-form-item>
        </el-col>
      </el-row>
      <el-row :gutter="24">
        <el-col :span="7">
          <el-form-item label="出院日期">
            <div style="width: 100%;display: flex;gap: 5px;">
              <el-form-item prop="AAC01_START">
                <el-date-picker style="width: 100%" v-model="formData.AAC01_START" type="date" placeholder="开始日期"
                  :picker-options="AAC01PickerOptions" value-format="yyyyMMdd" format="yyyy年MM月dd日">
                </el-date-picker>
              </el-form-item>
              <el-form-item prop="AAC01_END">
                <el-date-picker style="width: 100%" v-model="formData.AAC01_END" type="date" placeholder="结束日期"
                  value-format="yyyyMMdd" format="yyyy年MM月dd日">
                </el-date-picker>
              </el-form-item>
            </div>
          </el-form-item>
        </el-col>
        <el-col :span="5">
          <el-form-item label="出院科室" prop="BRKS">
            <el-cascader style="width: 100%;" placeholder="请选择" v-model="formData.BRKS"
              :options="searchOptions.ksArray" filterable :props="searchOptions.cascaderProps" clearable collapse-tags
              @change="ksChange">
            </el-cascader>
          </el-form-item>
        </el-col>
        <el-col :span="5">
          <el-form-item label="出院病区" prop="BRBQ">
            <el-cascader style="width: 100%;" placeholder="请选择" v-model="formData.BRBQ"
              :options="searchOptions.bqArray" filterable :props="searchOptions.cascaderProps" clearable collapse-tags>
            </el-cascader>
          </el-form-item>
        </el-col>
        <el-col :span="7">
          <el-form-item label="病案号" prop="AAA28">
            <el-input style="width: 100%" v-model="formData.AAA28" placeholder="请输入"></el-input>
          </el-form-item>
        </el-col>
      </el-row>
      <el-row :gutter="24">
        <el-col :span="7">
          <el-form-item label="入院日期">
            <div style="width: 100%;display: flex;gap: 5px;">
              <el-form-item prop="AAB01_START">
                <el-date-picker style="width: 100%" v-model="formData.AAB01_START" type="date" placeholder="开始日期"
                  :picker-options="AAB01PickerOptions" value-format="yyyyMMdd" format="yyyy年MM月dd日">
                </el-date-picker>
              </el-form-item>
              <el-form-item prop="AAB01_END">
                <el-date-picker style="width: 100%" v-model="formData.AAB01_END" type="date" placeholder="结束日期"
                  value-format="yyyyMMdd" format="yyyy年MM月dd日">
                </el-date-picker>
              </el-form-item>
            </div>
          </el-form-item>
        </el-col>
        <el-col :span="5">
          <el-form-item label="医嘱名称" prop="yzmc">
            <el-input v-model="formData.yzmc" placeholder="请输入"></el-input>
          </el-form-item>
        </el-col>
        <el-col :span="5">
          <el-form-item label="费用名称" prop="fymc">
            <el-input v-model="formData.fymc" placeholder="请输入"></el-input>
          </el-form-item>
        </el-col>
        <el-col :span="7">
          <el-form-item label="住院天数" prop="">
            <div style="width: 100%;display: flex;gap: 5px;">
              <el-form-item prop="min_day">
                <el-input style="width: 100%;" v-model="formData.min_day">
                  <template slot="append">天</template>
                </el-input>
              </el-form-item>
              -
              <el-form-item prop="max_day">
                <el-input style="width: 100%;" v-model="formData.max_day">
                  <template slot="append">天</template>
                </el-input>
              </el-form-item>
            </div>
          </el-form-item>
        </el-col>
      </el-row>
      <el-row :gutter="24" v-show="expand">
        <el-col :span="7">
          <el-form-item label="非计划手术" prop="fjhss">
            <el-select style="width: 100%" v-model="formData.fjhss" placeholder="请选择">
              <el-option v-for="item in searchOptions.unplannedSurgeryArray" :key="item.value" :label="item.label" :value="item.value">
              </el-option>
            </el-select>
          </el-form-item>
        </el-col>
        <el-col :span="5">
          <el-form-item label="手术安排" prop="ssap">
            <el-select style="width: 100%" v-model="formData.ssap" placeholder="请选择">
              <el-option v-for="item in searchOptions.surgicalPlanningArray" :key="item.value" :label="item.label" :value="item.value">
              </el-option>
            </el-select>
          </el-form-item>
        </el-col>
        <el-col :span="5">
          <el-form-item label="离院方式" prop="AEM01C">
            <el-select v-model="formData.AEM01C" clearable filterable placeholder="请选择" style="width: 100%;">
              <el-option v-for="(item, index) in searchOptions.lyTypeArray" :label="item.name" :value="item.id" :key="index"></el-option>
            </el-select>
          </el-form-item>
        </el-col>
        <el-col :span="7">
          <el-form-item label="总费用" prop="">
            <div style="width: 100%;display: flex;gap: 5px;">
              <el-form-item prop="min_cost">
                <el-input style="width: 100%;" v-model="formData.min_cost">
                  <template slot="append">元</template>
                </el-input>
              </el-form-item>
              -
              <el-form-item prop="max_cost">
                <el-input style="width: 100%;" v-model="formData.max_cost">
                  <template slot="append">元</template>
                </el-input>
              </el-form-item>
            </div>
          </el-form-item>
        </el-col>
      </el-row>
      <el-row :gutter="24" v-show="expand">
        <el-col :span="7">
          <el-form-item label="病历模版" prop="blmb">
            <el-cascader style="width: 100%;" placeholder="请选择" v-model="formData.blmb" collapse-tags
              :options="searchOptions.blmbArray" filterable clearable :props="{emitPath: false, multiple: true}">
            </el-cascader>
          </el-form-item>
        </el-col>
      </el-row>
      <el-row :gutter="24">
        <el-col :span="7">
          <el-form-item label="" prop="">
            <el-row type="flex" justify="start" style="margin-left: -90px">
              <el-button type="text" :icon="`el-icon-arrow-${expand? 'up' : 'down'}`" @click="expand = !expand">{{
                expand ? '收起' : '展开'}}</el-button>
                <el-button type="warning" plain icon="el-icon-star-off" @click="openCollectModal">收藏</el-button>
            </el-row>
          </el-form-item>
        </el-col>
        <el-col :span="7" :offset="10">
          <el-form-item label="" prop="">
            <el-row type="flex" justify="end">
              <el-button type="primary" @click="onSubmit">查询</el-button>
              <el-button @click="onReset">重置</el-button>
            </el-row>
          </el-form-item>
        </el-col>
      </el-row>
    </el-form>
    <CollectModalBox ref="CollectModalBoxRef"/>
    <CollectListModalBox ref="CollectListModalBoxRef"/>
  </div>
</template>
<script>
import moment from 'moment/moment';
import CollectModalBox from './CollectModal.vue'
import CollectListModalBox from './CollectListModal.vue'


export default {
  components: {
    CollectModalBox,
    CollectListModalBox
  },
  emits: ['search', 'reset'],
  data() {
    const that = this
    return {
      formData: {
        in_hospital: '全部',
        AAC01_START: '', //1
        AAC01_END: '',//1
        BRKS: [],//1
        BRBQ: [],//1
        AAA28: '',//1
        AAB01_START:  moment().startOf('year').format('YYYYMMDD'),//1
        AAB01_END: moment().endOf('year').format('YYYYMMDD'),//1
        yzmc: '',
        fymc: '',
        min_day: '',//1
        max_day: '',//1
        fjhss: '',//1
        ssap: '',//1
        AEM01C: '',
        min_cost: '',//1
        max_cost: '',//1
        blmb: '', //1
        order_value: '', //1
        order_key: ''//1
      },
      searchOptions: {
        ksArray: [],//科室options
        bqArray: [],//病区options
        lyTypeArray: [], //离院方式
        blmbArray: [], //病历模版
        unplannedSurgeryArray: [{
          label: '全部', value: ''
        },{
          label: '是', value: 'Y'
        },{
          label: '否', value: 'N'
        }],
        surgicalPlanningArray: [{
          label: '全部', value: ''
        },{
          label: '有', value: 'Y'
        },{
          label: '无', value: 'N'
        }],
        cascaderProps: {
          multiple: true,      // 开启多选模式
          label: 'dep_name',
          value: 'dep_id',
          children: 'children',
          checkStrictly: true, // 允许独立选择任意层级
          emitPath: false,     // 是否返回完整路径（true 返回路径数组，false 只返回末节点值）
        },
      },
      AAC01PickerOptions: {
        disabledDate: (time) => {
          if (that.formData.AAC01_END != "") {
            return time.getTime() > Date.now();
          }
        },
        shortcuts: [{
          text: '今天',
          onClick(picker) {
            picker.$emit('pick', moment().format('YYYYMMDD'));
            that.formData.AAC01_END = moment().format('YYYYMMDD')
          }
        }, {
          text: '近7天',
          onClick(picker) {
            picker.$emit('pick', moment().subtract(7, 'days').format('YYYYMMDD'));
            that.formData.AAC01_END = moment().format('YYYYMMDD')
          }
        }, {
          text: '近30天',
          onClick(picker) {
            picker.$emit('pick', moment().subtract(30, 'days').format('YYYYMMDD'));
            that.formData.AAC01_END = moment().format('YYYYMMDD')
          }
        }, {
          text: '一季度',
          onClick(picker) {
            picker.$emit('pick', moment().startOf('year').format('YYYYMMDD'));
            that.formData.AAC01_END = moment().startOf('year').add(3, 'M').subtract(1, 'days').format('YYYYMMDD')
          }
        }, {
          text: '二季度',
          onClick(picker) {
            picker.$emit('pick', moment().startOf('year').add(3, 'M').format('YYYYMMDD'));
            that.formData.AAC01_END = moment().startOf('year').add(6, 'M').subtract(1, 'days').format('YYYYMMDD')
          }
        }, {
          text: '三季度',
          onClick(picker) {
            picker.$emit('pick', moment().startOf('year').add(6, 'M').format('YYYYMMDD'));
            that.formData.AAC01_END = moment().startOf('year').add(9, 'M').subtract(1, 'days').format('YYYYMMDD')
          }
        }, {
          text: '四季度',
          onClick(picker) {
            picker.$emit('pick', moment().startOf('year').add(9, 'M').format('YYYYMMDD'));
            that.formData.AAC01_END = moment().startOf('year').add(12, 'M').subtract(1, 'days').format('YYYYMMDD')
          }
        }, {
          text: moment().add(-2, 'Y').format("YYYY"),
          onClick(picker) {
            picker.$emit('pick', moment().add(-2, 'Y').startOf('year').format('YYYYMMDD'));
            that.formData.AAC01_END = moment().add(-2, 'Y').endOf('year').format('YYYYMMDD')
          }
        }, {
          text: moment().add(-1, 'Y').format("YYYY"),
          onClick(picker) {
            picker.$emit('pick', moment().add(-1, 'Y').startOf('year').format('YYYYMMDD'));
            that.formData.AAC01_END = moment().add(-1, 'Y').endOf('year').format('YYYYMMDD')
          }
        }, {
          text: moment().format("YYYY"),
          onClick(picker) {
            picker.$emit('pick', moment().startOf('year').format('YYYYMMDD'));
            that.formData.AAC01_END = moment().endOf('year').format('YYYYMMDD')
          }
        }]
      },
      AAB01PickerOptions: {
        disabledDate: (time) => {
          if (that.formData.AAB01_END != "") {
            return time.getTime() > Date.now();
          }
        },
        shortcuts: [{
          text: '今天',
          onClick(picker) {
            picker.$emit('pick', moment().format('YYYYMMDD'));
            that.formData.AAB01_END = moment().format('YYYYMMDD')
          }
        }, {
          text: '近7天',
          onClick(picker) {
            picker.$emit('pick', moment().subtract(7, 'days').format('YYYYMMDD'));
            that.formData.AAB01_END = moment().format('YYYYMMDD')
          }
        }, {
          text: '近30天',
          onClick(picker) {
            picker.$emit('pick', moment().subtract(30, 'days').format('YYYYMMDD'));
            that.formData.AAB01_END = moment().format('YYYYMMDD')
          }
        }, {
          text: '一季度',
          onClick(picker) {
            picker.$emit('pick', moment().startOf('year').format('YYYYMMDD'));
            that.formData.AAB01_END = moment().startOf('year').add(3, 'M').subtract(1, 'days').format('YYYYMMDD')
          }
        }, {
          text: '二季度',
          onClick(picker) {
            picker.$emit('pick', moment().startOf('year').add(3, 'M').format('YYYYMMDD'));
            that.formData.AAB01_END = moment().startOf('year').add(6, 'M').subtract(1, 'days').format('YYYYMMDD')
          }
        }, {
          text: '三季度',
          onClick(picker) {
            picker.$emit('pick', moment().startOf('year').add(6, 'M').format('YYYYMMDD'));
            that.formData.AAB01_END = moment().startOf('year').add(9, 'M').subtract(1, 'days').format('YYYYMMDD')
          }
        }, {
          text: '四季度',
          onClick(picker) {
            picker.$emit('pick', moment().startOf('year').add(9, 'M').format('YYYYMMDD'));
            that.formData.AAB01_END = moment().startOf('year').add(12, 'M').subtract(1, 'days').format('YYYYMMDD')
          }
        }, {
          text: moment().add(-2, 'Y').format("YYYY"),
          onClick(picker) {
            picker.$emit('pick', moment().add(-2, 'Y').startOf('year').format('YYYYMMDD'));
            that.formData.AAB01_END = moment().add(-2, 'Y').endOf('year').format('YYYYMMDD')
          }
        }, {
          text: moment().add(-1, 'Y').format("YYYY"),
          onClick(picker) {
            picker.$emit('pick', moment().add(-1, 'Y').startOf('year').format('YYYYMMDD'));
            that.formData.AAB01_END = moment().add(-1, 'Y').endOf('year').format('YYYYMMDD')
          }
        }, {
          text: moment().format("YYYY"),
          onClick(picker) {
            picker.$emit('pick', moment().startOf('year').format('YYYYMMDD'));
            that.formData.AAB01_END = moment().endOf('year').format('YYYYMMDD')
          }
        }]
      },
      expand: false
    }
  },
  created() {
    this.getSearchOptions()
  },
  methods: {
    handleSortChange(column) {
      const { prop, order } = column;
      if (order === 'descending') {
          this.formData.order_value = 'desc';
      } else if (order === 'ascending') {
          this.formData.order_value = 'asc';
      } else {
          this.formData.order_value = 'desc';
      }
      this.formData.order_key = prop
      this.$emit('search')
    },
    onSubmit() {
      this.$emit('search')
    },
    onReset() {
      this.formData.order_value = ''
      this.formData.order_key = ''
      this.$refs.filterListFormRef.resetFields();
      this.$emit('reset')
    },

    handleBlmbOptions(data) {
      // 递归转换函数
      const convert = (node) => {
        const treeNode = {
          value: node.key,
          label: node.value
        };
        
        if (node.child && Object.keys(node.child).length > 0) {
          treeNode.children = Object.values(node.child).map(child => convert(child));
        }
        
        return treeNode;
      };

      // 处理根节点
      return Object.values(data).map(root => convert(root));
    },

    getSearchOptions() {
      this.$axios.post('CaseHistory/Terminal/getQxBlSearchOptions', {}).then(res => {
        this.searchOptions.ksArray = this.cancelChildren(res.data.ksArray);//科室
        this.searchOptions.bqArray = this.cancelChildren(res.data.bqArray);//病区
        this.searchOptions.lyTypeArray = res.data.lyTypeArray
        this.searchOptions.blmbArray = this.handleBlmbOptions(res.data.BLMB);
      })
    },
    //科室change事件
    ksChange() {
      this.formData.BRBQ = [];
      this.$axios.post('CaseHistory/Terminal/getBqOptions', { 'KS_CODE': this.formData.BRKS }).then(res => {
        this.searchOptions.bqArray = this.cancelChildren(res.data.bqArray);//病区
      })
    },
    //  将下拉框为空的children属性设置为undefined
    cancelChildren(arr) {
      if (!arr) {
        return [];
      }
      return arr.map(item => {
        if (item.children.length == 0) {
          item.children = undefined;
        } else {
          item.children.map(childreItem => {
            if (childreItem.children.length == 0) {
              childreItem.children = undefined;
            }
            return childreItem
          })
        }
        return item
      })
    },
    openCollectModal() {
      this.$refs.CollectModalBoxRef.openModal();
    },
    openCollectListModal() {
      this.$refs.CollectListModalBoxRef.openModal();
    }
  }
}
</script>

<style lang="scss" scoped></style>
