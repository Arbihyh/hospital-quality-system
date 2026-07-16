<template>
  <div class="box">
    <el-row :gutter="16">
      <!-- 左侧菜单 -->
      <el-col :span="8">
        <div class="box_wrapper" :style="{'height': boxWrapperHeight}">
          <el-input placeholder="输入关键字进行过滤" v-model="filterText"></el-input>
          <el-tree
            class="filter-tree"
            node-key="id"
            highlight-current
            :data="menuList"
            :props="defaultProps"
            :filter-node-method="filterNode"
            ref="tree"
            @node-click="handleNodeClick"
            :current-node-key="ruleId"
            :default-expanded-keys="[ruleId]"
          >
             <span class="custom-tree-node" slot-scope="{ node, data }">
              <span :class="{'green': greenColorMenus.includes(data.id)}">{{ node.label }}</span>
            </span>
          </el-tree>
        </div>
      </el-col>
      <!-- 右侧列表 -->
      <el-col :span="16">
        <div class="box_wrapper" :style="{'height': boxWrapperHeight}">
          <el-form :inline="true" :model="formInline" class="demo-form-inline">
            <el-form-item label="查询时间">
              <el-date-picker
                v-model="formInline.year"
                :clearable="false"
                type="year"
                :picker-options="pickerOptions"
                format="yyyy年"
                value-format="yyyy"
                placeholder="选择年份"
              ></el-date-picker>
            </el-form-item>
            <el-form-item>
              <el-button type="primary" @click="onSearch">查询</el-button>
            </el-form-item>
            <el-form-item style="float: right;">
              <el-button v-if="$route.query.type === 'children'" @click="onBack">返回</el-button>
            </el-form-item>
          </el-form>
          <el-table :data="tableData" style="width: 100%">
            <el-table-column prop="name" label="名称" show-overflow-tooltip></el-table-column>
            <el-table-column prop="time" label="日期" width="200"></el-table-column>
            <el-table-column prop="" label="数据" width="200">
              <template slot-scope="scope">
                <span v-if="ruleId > 100" class="link" :class="{'pointer': scope.row.source !== '人工录入'}" @click="toCaseIndexPage(scope.row)">{{ scope.row[judgeNum] }}</span>
                <span v-else>{{ (scope.row.res * 100).toFixed(2) + '%' }}</span>
              </template>
            </el-table-column>
            <el-table-column prop="" label="来源" width="200">
              <template slot-scope="scope">
                <span :class="{'green': scope.row.source === '人工录入' }">{{ scope.row.source }}</span>
              </template>
            </el-table-column>
            <!-- 同一个tableData 不会存在 多个来源 -->
            <el-table-column v-if="tableData.length && tableData[0].source === '人工录入'" prop="" label="操作" width="120">
              <template slot="header" slot-scope="scope">
                <span>操作</span>
                <i class="el-icon-edit table_edit" v-if="scope._self.judgeEdit" @click="onChangeValue"></i>
              </template>
              <template slot-scope="scope">
                <!-- 全年不可修改 -->
                <!-- 非人工录入不可修改 -->
                <!-- 菜单未标记的不可修改 -->
                <el-button type="text" @click="onChangeValue(scope.row)" v-if="scope.row.source === '人工录入' && scope.row.time !== '全年'">修改</el-button>
                <span v-else>--</span>
              </template>
            </el-table-column>
          </el-table>
        </div>
      </el-col>
    </el-row>
    <ChangeCaseIndexValueDialogVue v-if="dialogData.bSwitch" :data="dialogData" @refresh="getList" />
  </div>
</template>

<script>
import ChangeCaseIndexValueDialogVue from './components/ChangeCaseIndexValueDialog.vue';
export default {
  components: {
    ChangeCaseIndexValueDialogVue
  },
  data() {
    return {
      menus: [
        {
          id: 1,
          name: '一、人力资源配置指标',
          children: [
            {
              id: 11,
              name: '指标一、住院病案管理人员月均负担出院患者病历数',
              children: [
                {
                  id: 111,
                  name: '出院患者病历总数',
                },
                {
                  id: 112,
                  name: '同期住院病案管理人员实际工作总月数',
                },
              ],
            },
            {
              id: 12,
              name: '指标二、门诊病案管理人员月均负担门诊患者病历数',
              children: [
                {
                  id: 121,
                  name: '门诊患者病历总数',
                },
                {
                  id: 122,
                  name: '同期门诊病案管理人员实际工作总月数',
                },
              ],
            },
            {
              id: 13,
              name: '指标三、病案编码人员月均负担出院患者病历数',
              children: [
                {
                  id: 131,
                  name: '出院患者病历总数',
                },
                {
                  id: 132,
                  name: '同期病案编码人员实际工作总月数',
                },
              ],
            },
          ],
        },
        {
          id: 2,
          name: '二、病历书写时效性指标',
          children: [
            {
              id: 21,
              name: '指标四、入院记录 24 小时内完成率',
              children: [
                {
                  id: 211,
                  name: '入院记录在患者入院24小时内完成的住院患者病历数',
                },
                {
                  id: 212,
                  name: '同期住院患者病历总数',
                },
              ],
            },
            {
              id: 22,
              name: '指标五、手术记录24小时内完成率',
              children: [
                {
                  id: 221,
                  name: '手术记录在术后24小时内完成的住院患者病历数',
                },
                {
                  id: 222,
                  name: '同期住院手术患者病历总数',
                },
              ],
            },
            {
              id: 23,
              name: '指标六、出院记录24小时内完成率',
              children: [
                {
                  id: 231,
                  name: '出院记录在患者出院后24小时内完成的病历数',
                },
                {
                  id: 232,
                  name: '同期出院患者病历总数',
                },
              ],
            },
            {
              id: 24,
              name: '指标七、病案首页24小时内完成率',
              children: [
                {
                  id: 241,
                  name: '病案首页在患者出院后24小时内完成的病历数',
                },
                {
                  id: 242,
                  name: '同期出院患者病历总数',
                },
              ],
            },
          ],
        },
        {
          id: 3,
          name: '三、重大检查记录符合率',
          children: [
            {
              id: 31,
              name: '指标八、CT/MRI检查记录符合率',
              children: [
                {
                  id: 311,
                  name: 'CT/MRI检查医嘱、报告单、病程记录相对应的住院病历数',
                },
                {
                  id: 312,
                  name: '同期接受CT，MRI检查的住院病历总数',
                },
              ],
            },
            {
              id: 32,
              name: '指标九、病理检查记录符合率',
              children: [
                {
                  id: 321,
                  name: '手术记录、病理检查报告单、病程记录相对应的住院患者病历数',
                },
                {
                  id: 322,
                  name: '同期开展病理检查的住院患者病历总数',
                },
              ],
            },
            {
              id: 33,
              name: '指标十、细菌培养检查记录符合率',
              children: [
                {
                  id: 331,
                  name: '细菌培养检查的医嘱、报告单、病程记录相对应的住院患者病历数',
                },
                {
                  id: 332,
                  name: '同期开展细菌培养检查的住院患者病历总数',
                },
              ],
            },
          ],
        },
        {
          id: 4,
          name: '四、诊疗行为记录符合率',
          children: [
            {
              id: 41,
              name: '指标十一、抗菌药物使用记录符合率',
              children: [
                {
                  id: 411,
                  name: '抗菌药物使用医嘱、病程记录相对应的住院患者病历数',
                },
                {
                  id: 412,
                  name: '同期使用抗菌药物的住院患者病历总数',
                },
              ],
            },
            {
              id: 42,
              name: '指标十二、恶性肿瘤化学治疗记录符合率',
              children: [
                {
                  id: 421,
                  name: '恶性肿瘤化学治疗医嘱、病程记录相对应的住院患者病历数',
                },
                {
                  id: 422,
                  name: '同期接受恶性肿瘤化学治疗的住院患者病历总数',
                },
              ],
            },
            {
              id: 43,
              name: '指标十三、恶性肿瘤放射治疗记录符合率',
              children: [
                {
                  id: 431,
                  name: '恶性肿瘤放射治疗医嘱(治疗单) 、病程记录相对应的住院患者病历数',
                },
                {
                  id: 432,
                  name: '同期开展恶性肿瘤放射治疗的住院患者病历总数',
                },
              ],
            },
            {
              id: 44,
              name: '指标十四、手术相关记录完整率',
              children: [
                {
                  id: 441,
                  name: '手术相关记录完整的住院手术患者病历数',
                },
                {
                  id: 442,
                  name: '同期住院手术患者病历总数',
                },
              ],
            },
            {
              id: 45,
              name: '指标十五、植入物相关记录符合率',
              children: [
                {
                  id: 451,
                  name: '植入物相关记录符合的住院患者病历数',
                },
                {
                  id: 452,
                  name: '同期使用植入物的住院患者病历总数',
                },
              ],
            },
            {
              id: 46,
              name: '指标十六、临床用血相关记录符合率',
              children: [
                {
                  id: 461,
                  name: '临床用血相关记录符合的住院患者病历数',
                },
                {
                  id: 462,
                  name: '同期存在临床用血的住院患者病历总数',
                },
              ],
            },
            {
              id: 47,
              name: '指标十七、医师查房记录完整率',
              children: [
                {
                  id: 471,
                  name: '医师查房记录完整的住院患者病历数',
                },
                {
                  id: 472,
                  name: '同期住院患者病历总数',
                },
              ],
            },
            {
              id: 48,
              name: '指标十八、患者抢救记录及时完成率',
              children: [
                {
                  id: 481,
                  name: '抢救记录及时完成的住院患者病历数',
                },
                {
                  id: 482,
                  name: '同期接受抢救的住院患者病历总数',
                },
              ],
            },
            {
              id: 49,
              name: 'MER-D&T-08-1 患者抢救成功率',
              children: [
                {
                  id: 491,
                  name: '抢救成功患者病历数',
                },
                {
                  id: 492,
                  name: '同期接受抢救的住院患者病历总数',
                },
              ],
            },
          ],
        },
        {
          id: 5,
          name: '五、病历归档质量指标',
          children: [
            {
              id: 51,
              name: '指标十九、出院患者病历2日归档率',
              children: [
                {
                  id: 511,
                  name: '2个工作日内完成归档的出院患者病历数',
                },
                {
                  id: 512,
                  name: '同期出院患者病历总数',
                },
              ],
            },
            {
              id: 52,
              name: '指标二十、出院患者病历归档完整率',
              children: [
                {
                  id: 521,
                  name: '归档病历内容完整的出院患者病历数',
                },
                {
                  id: 522,
                  name: '同期出院患者病历总数',
                },
              ],
            },
            {
              id: 53,
              name: '指标二十一、主要诊断填写正确率',
              children: [
                {
                  id: 531,
                  name: '病案首页中主要诊断填写正确的出院患者病历 数',
                },
                {
                  id: 532,
                  name: '同期出院患者病历总数',
                },
              ],
            },
            {
              id: 54,
              name: '指标二十二、主要诊断编码正确率',
              children: [
                {
                  id: 541,
                  name: '病案首页中主要诊断编码正确的出院患者病历数',
                },
                {
                  id: 542,
                  name: '同期出院患者病历总数',
                },
              ],
            },
            {
              id: 55,
              name: '指标二十三、主要手术填写正确率',
              children: [
                {
                  id: 551,
                  name: '病案首页中主要手术填写正确的出院患者病历 数',
                },
                {
                  id: 552,
                  name: '同期出院手术患者病历总数',
                },
              ],
            },
            {
              id: 56,
              name: '指标二十四、主要手术编码正确率',
              children: [
                {
                  id: 561,
                  name: '病案首页中主要手术编码正确的出院患者病历数',
                },
                {
                  id: 562,
                  name: '同期出院手术患者病历总数',
                },
              ],
            },
            {
              id: 57,
              name: '指标二十五、不合理复制病历发生率',
              children: [
                {
                  id: 571,
                  name: '出现不合理复制病历内容的出院患者病历数',
                },
                {
                  id: 572,
                  name: '同期出院患者病历总数',
                },
              ],
            },
            {
              id: 58,
              name: '指标二十六、知情同意书规范签署率',
              children: [
                {
                  id: 581,
                  name: '规范签署知情同意书的出院患者病历数',
                },
                {
                  id: 582,
                  name: '同期存在知情同意书签署的出院患者病历总数',
                },
              ],
            },
            {
              id: 59,
              name: '指标二十七、甲级病历率',
              children: [
                {
                  id: 591,
                  name: '甲级出院患者病历数',
                },
                {
                  id: 592,
                  name: '同期出院患者病历总数',
                },
              ],
            },
          ],
        },
      ],
      // 医院嵌入需显示指标4/6/7/8/10/11/12/13/15/16/18/20/22/24/26
      menus2: [
        {
          id: 2,
          name: '二、病历书写时效性指标',
          children: [
            {
              id: 21,
              name: '指标四、入院记录 24 小时内完成率',
              children: [
                {
                  id: 211,
                  name: '入院记录在患者入院24小时内完成的住院患者病历数',
                },
                {
                  id: 212,
                  name: '同期住院患者病历总数',
                },
              ],
            },
            {
              id: 23,
              name: '指标六、出院记录24小时内完成率',
              children: [
                {
                  id: 231,
                  name: '出院记录在患者出院后24小时内完成的病历数',
                },
                {
                  id: 232,
                  name: '同期出院患者病历总数',
                },
              ],
            },
            {
              id: 24,
              name: '指标七、病案首页24小时内完成率',
              children: [
                {
                  id: 241,
                  name: '病案首页在患者出院后24小时内完成的病历数',
                },
                {
                  id: 242,
                  name: '同期出院患者病历总数',
                },
              ],
            },
          ],
        },
        {
          id: 3,
          name: '三、重大检查记录符合率',
          children: [
            {
              id: 31,
              name: '指标八、CT/MRI检查记录符合率',
              children: [
                {
                  id: 311,
                  name: 'CT/MRI检查医嘱、报告单、病程记录相对应的住院病历数',
                },
                {
                  id: 312,
                  name: '同期接受CT，MRI检查的住院病历总数',
                },
              ],
            },
            {
              id: 33,
              name: '指标十、细菌培养检查记录符合率',
              children: [
                {
                  id: 331,
                  name: '细菌培养检查的医嘱、报告单、病程记录相对应的住院患者病历数',
                },
                {
                  id: 332,
                  name: '同期开展细菌培养检查的住院患者病历总数',
                },
              ],
            },
          ],
        },
        {
          id: 4,
          name: '四、诊疗行为记录符合率',
          children: [
            {
              id: 41,
              name: '指标十一、抗菌药物使用记录符合率',
              children: [
                {
                  id: 411,
                  name: '抗菌药物使用医嘱、病程记录相对应的住院患者病历数',
                },
                {
                  id: 412,
                  name: '同期使用抗菌药物的住院患者病历总数',
                },
              ],
            },
            {
              id: 42,
              name: '指标十二、恶性肿瘤化学治疗记录符合率',
              children: [
                {
                  id: 421,
                  name: '恶性肿瘤化学治疗医嘱、病程记录相对应的住院患者病历数',
                },
                {
                  id: 422,
                  name: '同期接受恶性肿瘤化学治疗的住院患者病历总数',
                },
              ],
            },
            {
              id: 43,
              name: '指标十三、恶性肿瘤放射治疗记录符合率',
              children: [
                {
                  id: 431,
                  name: '恶性肿瘤放射治疗医嘱(治疗单) 、病程记录相对应的住院患者病历数',
                },
                {
                  id: 432,
                  name: '同期开展恶性肿瘤放射治疗的住院患者病历总数',
                },
              ],
            },
            {
              id: 45,
              name: '指标十五、植入物相关记录符合率',
              children: [
                {
                  id: 451,
                  name: '植入物相关记录符合的住院患者病历数',
                },
                {
                  id: 452,
                  name: '同期使用植入物的住院患者病历总数',
                },
              ],
            },
            {
              id: 46,
              name: '指标十六、临床用血相关记录符合率',
              children: [
                {
                  id: 461,
                  name: '临床用血相关记录符合的住院患者病历数',
                },
                {
                  id: 462,
                  name: '同期存在临床用血的住院患者病历总数',
                },
              ],
            },
            {
              id: 48,
              name: '指标十八、患者抢救记录及时完成率',
              children: [
                {
                  id: 481,
                  name: '抢救记录及时完成的住院患者病历数',
                },
                {
                  id: 482,
                  name: '同期接受抢救的住院患者病历总数',
                },
              ],
            },
          ],
        },
        {
          id: 5,
          name: '五、病历归档质量指标',
          children: [
            {
              id: 51,
              name: '指标十九、出院患者病历2日归档率',
              children: [
                {
                  id: 511,
                  name: '2个工作日内完成归档的出院患者病历数',
                },
                {
                  id: 512,
                  name: '同期出院患者病历总数',
                },
              ],
            },
            {
              id: 53,
              name: '指标二十一、主要诊断填写正确率',
              children: [
                {
                  id: 531,
                  name: '病案首页中主要诊断填写正确的出院患者病历 数',
                },
                {
                  id: 532,
                  name: '同期出院患者病历总数',
                },
              ],
            },
            {
              id: 55,
              name: '指标二十三、主要手术填写正确率',
              children: [
                {
                  id: 551,
                  name: '病案首页中主要手术填写正确的出院患者病历 数',
                },
                {
                  id: 552,
                  name: '同期出院手术患者病历总数',
                },
              ],
            },
            {
              id: 57,
              name: '指标二十五、不合理复制病历发生率',
              children: [
                {
                  id: 571,
                  name: '出现不合理复制病历内容的出院患者病历数',
                },
                {
                  id: 572,
                  name: '同期出院患者病历总数',
                },
              ],
            },
          ],
        },
      ],
      cMenus: [
        {
          id: 34,
          name: '指标十、细菌培养检查记录符合率（子）',
          children: [
            {
              id: 341,
              name: '细菌培养检查的医嘱、报告单、病程记录相对应的住院患者病历数',
            },
            {
              id: 3432,
              name: '同期开展细菌培养检查的住院患者病历总数',
            },
          ],
        },
      ],
      formInline: {
        year: '',
      },
      tableData: [],
      filterText: '',
      defaultProps: {
        children: 'children',
        label: 'name',
      },
      ruleId: '',
      ruleName: '',
      time: new Date(),
      pickerOptions: {
        disabledDate(time) {
          const date = new Date();
          const year = date.getFullYear();
          const timeYear = time.getFullYear();
          return year < timeYear;
        },
      },
      greenColorMenus: [11, 112, 12, 121, 122, 13, 132],
      dialogData: {
        bSwitch: false,
        rows: []
      }
    };
  },
  created() {
    this.formInline.year = new Date().getFullYear().toString()
    if (this.$route.path === '/embedIndex-home') {
      this.ruleId = this.menus2[0].children[0].id
      this.ruleName = this.menus2[0].children[0].name
    } else {
      if (this.$route.query.type === 'children') {
        this.ruleId = this.cMenus[0].id
        this.ruleName = this.cMenus[0].name
      } else {
        this.ruleId = this.menus[0].children[0].id
        this.ruleName = this.menus[0].children[0].name
      }
    }
    this.getList()
  },
  computed: {
    // 判断是取分子还是分母
    judgeNum() {
      const str = `${this.ruleId}`;
      const length = str.length;
      const lastNum = str[length - 1];
      if (Number(lastNum) > 1) {
        // 分母
        return 'denominator';
      } else {
        // 分子
        return 'numerator';
      }
    },
    menuList() {
      if (this.$route.path === '/embedIndex-home') {
        return this.menus2
      } else {
        return this.$route.query.type === 'children' ? this.cMenus : this.menus
      }
    },
    judgeEdit() {
      return this.greenColorMenus.includes(this.ruleId) && !!this.tableData.length
    },
    boxWrapperHeight() {
      return this.$route.path === '/embedIndex-home' ? '815px' : '885px'
    }
  },
  watch: {
    filterText(val) {
      this.$refs.tree.filter(val);
    }
  },
  methods: {
    onChangeValue(row) {
      if (row.time) {
        // 单个修改
        const { time } = row
        const obj = {
          year: time.split('-')[0],
          month: time.split('-')[1],
          flag: Number(this.ruleId.toString().slice(0, 2)),
          type: this.judgeNum,
          num: row[this.judgeNum],
        }
        // 先清空在赋值，防止重复
        this.$set(this.dialogData, 'rows', [])
        this.dialogData.rows.push(obj)
      } else {
        // 批量修改
        // 先清空在赋值，防止重复
        this.$set(this.dialogData, 'rows', [])
        this.tableData.map(item => {
          const { time, source } = item
          if (source === '人工录入' && time != '全年') {
            const obj = {
              year: time.split('-')[0],
              month: time.split('-')[1],
              flag: Number(this.ruleId.toString().slice(0, 2)),
              type: this.judgeNum,
              num: item[this.judgeNum]
            }
            this.dialogData.rows.push(obj)
          }
        })
      }
      this.dialogData.bSwitch = true
    },
    // 返回
    onBack() {
      this.$router.go(-1)
    },
    // 病案指标列表跳转
    toCaseIndexPage(row) {
      const { time, source } = row;
      if ( source === '人工录入') {
        return
      }
      
      let path
      if (this.$route.path === '/embedIndex-home') {
        path = '/embedIndex-caseIndexList'
      } else {
        path = '/caseIndexList'
      }
      this.$router.push({ path, query: { year: this.formInline.year, time, ruleId: Number(`${this.ruleId}`.slice(0, 2)), type: this.judgeNum, name: this.ruleName } });
    },
    // 菜单筛选
    filterNode(value, data) {
      if (!value) return true;
      return data.name.indexOf(value) !== -1;
    },
    handleNodeClick(data) {
      const { id, name } = data;
      this.ruleId = id;
      this.ruleName = name;
      if (id > 10 && this.formInline.year) {
        this.getList();
      }
    },
    // 获取右侧列表数据
    getList() {
      const { year } = this.formInline;
      const params = {
        start_time: `${year}0101`,
        end_time: `${year}1231`,
        year,
        type: Number(`${this.ruleId}`.slice(0, 2)),
      };
      if (this.ruleId < 100) {
        params.request_source = 1
      } else {
        params.request_source = this.judgeNum === 'denominator' ? 2 : 3
      }
      this.$axios2.post('/get_assessment_indicators', params).then(res => {
        if (Array.isArray(res.data)) {
          res.data.map(item => {
            item.name = this.ruleName;
            item.ruleId = this.ruleId
          });
          this.tableData = res.data;
        } else {
          this.tableData = [];
        }
      });
    },
    // 查询
    onSearch() {
      const { year } = this.formInline;
      console.log(year)
      if (!year) {
        this.$message.error('请选择查询时间');
        return;
      }
      if (!this.ruleId) {
        this.$message.error('请选择查询指标');
        return;
      }
      this.getList();
    },
  },
};
</script>

<style lang="scss" scoped>
.table_edit {
  margin-left: 10px;
  cursor: pointer;
  &:hover {
    opacity: 0.6;
  }
}
.link {
  font-weight: 600;
  color: #409eff;
}

.pointer {
  cursor: pointer;
}
.box {
  padding: 0 16px 16px 16px;
  .box_wrapper {
    padding: 16px;
    background: #fff;
    border-radius: 5px;
    overflow-x: hidden;
    overflow-y: auto;
  }
}
.filter-tree {
  margin-top: 16px;
  ::v-deep .el-tree-node__content {
    height: 36px;
    line-height: 36px;
  }
}

::v-deep.el-table .el-table__header tr th {
  background: #f1f6ff;
  color: #13171e;
  border-bottom: 0px;
}
::v-deep.el-table .el-table__row td {
  color: #7e8bab;
  border-bottom: 1px solid #f4f4f4;
}
::v-deep.el-table .el-table__header tr th:first-child {
  border-radius: 5px 0px 0px 5px;
}
::v-deep.el-table .el-table__header tr th:nth-child(3) {
  border-radius: 0px 5px 5px 0px;
}
.green {
  color: #67C23A;
}
</style>