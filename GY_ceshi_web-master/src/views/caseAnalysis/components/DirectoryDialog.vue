<template>
  <div>
    <el-dialog
     v-el-drag-dialog
      title="病历目录"
      :visible.sync="data.bSwitch"
      width="50%"
    >
    <el-row :gutter="10">
     <el-col class="" :span="5">
      <el-menu
      :default-active ="defaultActive"
      class="el-menu-vertical-demo"
      @open="handleOpen"
      @close="handleClose">
      <el-submenu index='BASY'>
        <template slot="title">
            <i class="el-icon-bank-card"></i>
          <span>病案首页</span>
        </template>
        <el-menu-item-group>
          <el-menu-item index="home" @click.native="onMenuClick('home')">首页</el-menu-item>
          <el-menu-item index="main_diagnosis" @click.native="onMenuClick('main_diagnosis')">主诊断</el-menu-item>
          <el-menu-item index="other_diagnosis" @click.native="onMenuClick('other_diagnosis')">其他诊断</el-menu-item>
          <el-menu-item index="main_operation" @click.native="onMenuClick('main_operation')">主手术</el-menu-item>
          <el-menu-item index="other_operation" @click.native="onMenuClick('other_operation')">其他手术</el-menu-item>
        </el-menu-item-group>
      </el-submenu>
      <el-submenu index="SSJL" @click.native="onMenuClick('SSJL')">
        <template slot="title">
          <span>手术记录</span>
        </template>
      </el-submenu>
      <el-submenu index="FYMX" @click.native="onMenuClick('FYMX')">
        <template slot="title">
          <span>住院费用</span>
        </template>
      </el-submenu>
      </el-menu>
    </el-col>
    <el-row class="inputClass">
        <el-input class="" placeholder="请输入查询的字段名称"  prefix-icon="el-icon-search"></el-input>
      </el-row>
  <el-col class="" :span="19" v-if="defaultActive==='home'">
  <template>
  <el-radio-group v-model="key">
    <el-radio style="margin:20px;" v-for="(item,index) in home" :label="item.key" :key="item.key">{{item.name}}</el-radio>
   </el-radio-group>
   </template>
     </el-col>
   <el-col class="" :span="19" v-if="defaultActive==='main_diagnosis'">
  <template>
    <el-radio-group v-model="key">
    <el-radio style="margin:20px;" v-for="(item,index) in main_diagnosis" :label="item.key" :key="item.key">{{item.name}}</el-radio>
     </el-radio-group>
     </template>
     </el-col>
     <el-col class="" :span="19" v-if="defaultActive==='other_diagnosis'">
    <template>
    <el-radio-group v-model="key">
    <el-radio style="margin:20px;" v-for="(item,index) in other_diagnosis" :label="item.key" :key="item.key">{{item.name}}</el-radio>
     </el-radio-group>
     </template>
     </el-col>
     <el-col class="" :span="19" v-if="defaultActive==='main_operation'">
    <template>
    <el-radio-group v-model="key">
    <el-radio style="margin:20px;" v-for="(item,index) in main_operation" :label="item.key" :key="item.key">{{item.name}}</el-radio>
     </el-radio-group>
     </template>
     </el-col>
     <el-col class="" :span="19" v-if="defaultActive==='other_operation'">
    <template>
    <el-radio-group v-model="key">
    <el-radio style="margin:20px;" v-for="(item,index) in other_operation" :label="item.key" :key="item.key">{{item.name}}</el-radio>
     </el-radio-group>
     </template>
     </el-col>
     <el-col class="" :span="19" v-if="defaultActive==='SSJL'">
    <template>
    <el-radio-group v-model="key">
    <el-radio style="margin:20px;" v-for="(item,index) in SSJL" :label="item.key" :key="item.key">{{item.name}}</el-radio>
     </el-radio-group>
     </template>
     </el-col>
     <el-col class="" :span="19" v-if="defaultActive==='FYMX'">
    <template>
    <el-radio-group v-model="key">
    <el-radio style="margin:20px;" v-for="(item,index) in FYMX" :label="item.key" :key="item.key">{{item.name}}</el-radio>
     </el-radio-group>
     </template>
     </el-col>
    </el-row>
      <span slot="footer" class="dialog-footer">
        <el-button type="primary" @click="submitForm(defaultActive)">确认</el-button>
      </span>
    </el-dialog>
  </div>
</template>
<script>
// import { editPassword } from '@/api/user'
const cityOptions=[{id:"1",name:"住院号码"},{id:"2",name:"姓名"},{id:"3",name:"年龄"},{id:"4",name:"住院次数"}]
export default {
  props: {
    data: {
      type: Object,
      default() {
        return {
          bSwitch: false,
          row: {}
        }
      }
    }
  },
  data() {
    return {
        key:'ZYH',
        // key2:'ZYH',
        // key3:'ZYH',
        // key4:'ZYH',
        // key5:'ZYH',
        // key6:'ZYH',
        // key7:'ZYH',
        home: [
                            {key: 'ZYH', name:'唯一标识', type: 'input', value: ''},
                            {key: 'AAA28', name: '住院号码', type: 'input', value: ''},
                            {key : 'AAA29', name: '住院次数', type: 'range', value: ''},
                            {key: 'AAA01', name: '患者姓名', type: 'input', value: ''},
                            {key: 'AAA02C', name: '患者性别', type: 'select', value:''},
                            {key: 'AAA04', name: '患者年龄', type: 'range', value: ''},
                            {key: 'AAA40', name: '患者天龄', type: 'range', value: ''},
                            {key: 'AAA08C', name: '婚姻状况', type: 'select', value :''},
                            {key:'AEM01C', name: '离院方式', type: 'select', value:''},
                            {key: 'AAB01', name: '入院时间', type: 'time', value: ''},
                            {key:'AAC04', name: '住院天数', type: 'range', value: ''},
                            {key: 'AAC11N', name: '出院科室', type: 'select', value:''},
                            {key: 'AAB06C', name: '入院途径', type: 'select', value:''},
                            {key: 'AAA26C', name:'医疗付费方式', type: 'select', value:''},
                            {key:'ADA01', name:'住院总费用', type: 'range', value: ''},
                            {key: 'ADA0101', name: '自付费用', type:'range', value:''},
                        ],
                        main_diagnosis: [
                            {key: 'M_ICD10_ID1', name: '主要诊断编码', type: 'select', value:''},
                            {key: 'M_ICD10_NAME', name: '主要诊断名称', type: 'select', value:''},
                            {key: 'M_RYQK', name: '入院病情', type: 'select', value :''},
                        ],
                        other_diagnosis: [
                            {key: 'O_ICD10_ID1', name: '其他诊断编码', type: 'select', value:''},
                            {key: 'O_ICD10_NAME', name: '其他诊断名称', type: 'select', value:''},
                            {key: 'O_RYQK', name: '入院病情（其他诊断）', type: 'select', value:''},
                        ],
                        main_operation:[
                            {key:'M_ICD9_ID1', name: '主手术编码', type:'select', value:''},
                            {key: 'M_ICD9_NAME', name: '主手术名称', type: 'select', value:''},
                            {key: 'M_OPE_DATE', name: '主手术操作日期', type: 'time', value: ''},
                            {key: 'M_OPE_LEVEL', name: '手术级别', type:'select', value:''},
                            {key: 'M_SSPB', name: '手术判别', type: 'select', value:''},
                            {key:'M_OPE_MAN_NAME', name: '术者', type: 'select', value:''},
                            {key: 'M_FRIST_ASSISTANT_NAME', name:'一助', type: 'select', value:''},
                            {key: 'M_SECOND_ASSISTANT_NAME', name: '二助', type: 'select', value:''},
                            {key: 'M_HOCUS_WAY_ID', name: '麻醉方式', type: 'select', value:''},
                            {key: 'M_QKDJ', name: '切口等级', type: 'select', value:''},
                            {key: 'M_YHDJ', name: '愈合等级', type: 'select', value:''},
                            {key: 'M_HOCUS_MAN_NAME', name: '麻醉医师', type: 'select', value:''},
                        ],
                        other_operation : [
                            {key: 'O_ICD9_ID1', name: '其他手术编码', type: 'select', value:''},
                            {key: 'O_ICD9_NAME', name: '其他手术名称', type: 'select', value:''},
                            {key: 'O_OPE_DATE', name: '其他手术操作日期', type: 'time', value: ''},
                            {key: 'O_OPE_LEVEL', name: '手术级别', type: 'select', value: ''},
                            {key:'O_SSPB', name: '手术判别', type: 'select', value:''},
                            {key: 'O_OPE_MAN_NAME', name: '术者', type: 'select', value:''},
                            {key: 'O_FRIST_ASSISTANT_NAME', name: '一助', type: 'select', value:''},
                            {key:'O_SECOND_ASSISTANT_NAME', name: '二助', type: 'select', value:''},
                            {key: 'O_HOCUS_WAY_ID', name: '麻醉方式', type: 'select', value:''},
                            {key: 'O_QKDJ', name: '切口等级', type: 'select', value: ''},
                            {key: 'O_YHDJ', name: '愈合等级', type: 'select', value:''},
                            {key: 'O_HOCUS_MAN_NAME', name: '麻醉医师', type: 'select', value:''},
                        ],
        BASY: [
                    {
                        key: 'home',
                        name: '首页',
                        
                    },
                    {
                        key: 'main_diagnosis',
                        name: '主诊断',
                       
                    },
                    {
                        key:'other_diagnosis',
                        name: '其他诊断',
                       
                    },
                    {
                        key:'main_operation',
                        name: '主手术',
                        
                    },
                    {
                    key: 'other_operation',
                        name: '其他手术',
                        
                    }
                  ],
                  SSJL : [
                        {key: 'SSNM', name: '手术内码', type: 'input', value: ''},
                        {key: 'SSMC', name: '手术名称', type: 'input', value: ''},
                        {key: 'SSDM', name:'手术代码', type: 'input', value: ''},
                        {key:'SSSJ', name: '手术时间', type: 'time', value: ''},
                    ],
                    FYMX: [
                        {key: 'JFRQ', name:'计费日期', type: 'time', value: ''},
                        {key: 'YBBM', name:'医保编码', type: 'input', value: ''},
                         {key: 'FYMC', name: '费用名称', type: 'input', value: ''},
                        {key: 'FYSL', name: '费用数量', type:  'range', value: ''},
                        {key: 'FYDJ', name: '费用单价', type: 'range', value: ''},
                        {key: 'ZJE', name: '总金额', type: 'range', value: ''},
                        {key:'YSGH', name: '医生工号', type: 'select', value: ''},
                        {key: 'ZLXZ', name: '诊疗小组', type: 'select', value:''},
                        {key: 'FYKS', name: '费用科室', type: 'select', value:''},
                        {key: 'ZXKS', name: '执行科室', type: 'select', value: ''},
                        {key: 'FYGB', name:  '费用归并', type: 'input', value: ''},
                        {key: 'SYFYGB', name:'首页费用归并', type: 'input', value: ''},
                        {key: 'YPLX', name: '药品类型', type: 'select', value:''},
                    ],
        List:[
               {key:"BASY",
                name:"病案首页",
                
                 },
                 {
                 key: 'SSJL', 
                 name: '手术记录',
                   
                 },
                {
                key: 'FYMX',
                name: '住院费用',
                    
                }
            
        ],
   desData:{
        List:[
               {key:"BASY",
                name:"病案首页",
                children: [
                        {
                        key: 'home',
                        name: '首页',
                        children: [
                            {key: 'ZYH', name:'唯一标识', type: 'input', value: ''},
                            {key: 'AAA28', name: '住院号码', type: 'input', value: ''},
                            {key : 'AAA29', name: '住院次数', type: 'range', value: ''},
                            {key: 'AAA01', name: '患者姓名', type: 'input', value: ''},
                            {key: 'AAA02C', name: '患者性别', type: 'select', value:''},
                            {key: 'AAA04', name: '患者年龄', type: 'range', value: ''},
                            {key: 'AAA40', name: '患者天龄', type: 'range', value: ''},
                            {key: 'AAA08C', name: '婚姻状况', type: 'select', value :''},
                            {key:'AEM01C', name: '离院方式', type: 'select', value:''},
                            {key: 'AAB01', name: '入院时间', type: 'time', value: ''},
                            {key:'AAC04', name: '住院天数', type: 'range', value: ''},
                            {key: 'AAC11N', name: '出院科室', type: 'select', value:''},
                            {key: 'AAB06C', name: '入院途径', type: 'select', value:''},
                            {key: 'AAA26C', name:'医疗付费方式', type: 'select', value:''},
                            {key:'ADA01', name:'住院总费用', type: 'range', value: ''},
                            {key: 'ADA0101', name: '自付费用', type:'range', value:''},
                        ],
                    },
                    {
                        key: 'main_diagnosis',
                        name: '主诊断',
                        children: [
                            {key: 'M_ICD10_ID1', name: '主要诊断编码', type: 'select', value:''},
                            {key: 'M_ICD10_NAME', name: '主要诊断名称', type: 'select', value:''},
                            {key: 'M_RYQK', name: '入院病情', type: 'select', value :''},
                        ],
                    },
                    {
                        key:'other_diagnosis',
                        name: '其他诊断',
                        children: [
                            {key: 'O_ICD10_ID1', name: '其他诊断编码', type: 'select', value:''},
                            {key: 'O_ICD10_NAME', name: '其他诊断名称', type: 'select', value:''},
                            {key: 'O_RYQK', name: '入院病情（其他诊断）', type: 'select', value:''},
                        ],
                    },
                    {
                        key:'main_operation',
                        name: '主手术',
                        children:[
                            {key:'M_ICD9_ID1', name: '主手术编码', type:'select', value:''},
                            {key: 'M_ICD9_NAME', name: '主手术名称', type: 'select', value:''},
                            {key: 'M_OPE_DATE', name: '主手术操作日期', type: 'time', value: ''},
                            {key: 'M_OPE_LEVEL', name: '手术级别', type:'select', value:''},
                            {key: 'M_SSPB', name: '手术判别', type: 'select', value:''},
                            {key:'M_OPE_MAN_NAME', name: '术者', type: 'select', value:''},
                            {key: 'M_FRIST_ASSISTANT_NAME', name:'一助', type: 'select', value:''},
                            {key: 'M_SECOND_ASSISTANT_NAME', name: '二助', type: 'select', value:''},
                            {key: 'M_HOCUS_WAY_ID', name: '麻醉方式', type: 'select', value:''},
                            {key: 'M_QKDJ', name: '切口等级', type: 'select', value:''},
                            {key: 'M_YHDJ', name: '愈合等级', type: 'select', value:''},
                            {key: 'M_HOCUS_MAN_NAME', name: '麻醉医师', type: 'select', value:''},
                        ],
                    },
                    {
                    key: 'other_operation',
                        name: '其他手术',
                        children : [
                            {key: 'O_ICD9_ID1', name: '其他手术编码', type: 'select', value:''},
                            {key: 'O_ICD9_NAME', name: '其他手术名称', type: 'select', value:''},
                            {key: 'O_OPE_DATE', name: '其他手术操作日期', type: 'time', value: ''},
                            {key: 'O_OPE_LEVEL', name: '手术级别', type: 'select', value: ''},
                            {key:'O_SSPB', name: '手术判别', type: 'select', value:''},
                            {key: 'O_OPE_MAN_NAME', name: '术者', type: 'select', value:''},
                            {key: 'O_FRIST_ASSISTANT_NAME', name: '一助', type: 'select', value:''},
                            {key:'O_SECOND_ASSISTANT_NAME', name: '二助', type: 'select', value:''},
                            {key: 'O_HOCUS_WAY_ID', name: '麻醉方式', type: 'select', value:''},
                            {key: 'O_QKDJ', name: '切口等级', type: 'select', value: ''},
                            {key: 'O_YHDJ', name: '愈合等级', type: 'select', value:''},
                            {key: 'O_HOCUS_MAN_NAME', name: '麻醉医师', type: 'select', value:''},
                        ]
                    }
                  ]
                 },
                 {
                 key: 'SSJL', 
                 name: '手术记录',
                    children : [
                        {key: 'SSNM', name: '手术内码', type: 'input', value: ''},
                        {key: 'SSMC', name: '手术名称', type: 'input', value: ''},
                        {key: 'SSDM', name:'手术代码', type: 'input', value: ''},
                        {key:'SSSJ', name: '手术时间', type: 'time', value: ''},
                    ]
                 },
                {
                key: 'FYMX',
                name: '住院费用',
                    children: [
                        {key: 'JFRQ', name:'计费日期', type: 'time', value: ''},
                        {key: 'YBBM', name:'医保编码', type: 'input', value: ''},
                         {key: 'FYMC', name: '费用名称', type: 'input', value: ''},
                        {key: 'FYSL', name: '费用数量', type:  'range', value: ''},
                        {key: 'FYDJ', name: '费用单价', type: 'range', value: ''},
                        {key: 'ZJE', name: '总金额', type: 'range', value: ''},
                        {key:'YSGH', name: '医生工号', type: 'select', value: ''},
                        {key: 'ZLXZ', name: '诊疗小组', type: 'select', value:''},
                        {key: 'FYKS', name: '费用科室', type: 'select', value:''},
                        {key: 'ZXKS', name: '执行科室', type: 'select', value: ''},
                        {key: 'FYGB', name:  '费用归并', type: 'input', value: ''},
                        {key: 'SYFYGB', name:'首页费用归并', type: 'input', value: ''},
                        {key: 'YPLX', name: '药品类型', type: 'select', value:''},
                    ]
                }
            
        ]
     },
      radio: 3,
      defaultActive:"1",
      tabsLabel:[{
        id:'one',
        name:'病案首页'
      },{
        id:'two',
        name:"住院费用"
      },{
        id:"three",
        name:"手术安排"
      }],
      activeName:"one",
       tabPosition: 'left',
       checkedCities:[],
       cities: cityOptions,
      ruleForm: {
        password: '',
        id: ''
      },
      rules: {
        password: [
          { required: true, message: '请输入', trigger: 'blur' }
        ]
      }
    }
  },
  created() {
    const { id } = this.data.row
    this.ruleForm.id = id
  },
  methods: {
    //点击菜单
    onMenuClick(val){
      this.$emit("onMenuClick",val)
    this.defaultActive=val
    },
    handleOpen(key, keyPath) {
        console.log(key, keyPath);
      },
      handleClose(key, keyPath) {
        console.log(key, keyPath);
      },
    submitForm(val) {
   
    //   this.$refs['ruleForm'].validate((valid) => {
    //     if (valid) {
        //   editPassword(this.ruleForm).then(res => {
        this.$emit('handleCascaderChange',val)
            // this.$message.success(res.msg || '操作成功')
            this.data.bSwitch = false
        //   })
    //     } else {
    //       return false
    //     }
    //   })
    }
  }
}
</script>

<style lang="scss" scoped>
.demo-ruleForm {
	width: 80%;
}
.el-button--primary {
    color: #FFF;
    background-color: #00C797;
    border-color: #00C797;
}
.el-button:focus, .el-button:hover {
    color: #409EFF !important;
    border-color: #c6e2ff !important;
    background-color: #ecf5ff !important;
}
.asideClass {
    background-color: #D3DCE6;
    color: #333;
    text-align: center;
    line-height: 200px;
  }
  
  .mainClass {
    background-color: #E9EEF3;
    color: #333;
    text-align: center;
    line-height: 160px;
  }
  ::v-deep .el-dialog__header{
      padding: 10px 20px;
      background: #00C797;
      color: #fff !important;
      .el-dialog__title{
        color: #fff;
      }
    }
   ::v-deep .el-icon-arrow-down:before {
   content: ""!important;
}
.inputClass{
  display: flex;
  flex-direction: column;
  justify-content: flex-start;
  margin-bottom:20px;
}
::v-deep .el-radio__input.is-checked .el-radio__inner {
    border-color: #00C797;
    background: #00C797;
    // color:#00C797 !important;
}
::v-deep .el-radio__input.is-checked+.el-radio__label {
    color: #00C797;
}
//EFF2F5
</style>
