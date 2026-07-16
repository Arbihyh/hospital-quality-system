<template>
  <div>
    <DraggableDialog :dialogKey="`${Date.now()}`">
      <el-dialog
        title="添加质控信息"
        :visible.sync="data.bSwitch"
        :close-on-click-modal="false"
        width="900px"
        top="7vh"
      >
        <el-form
          :model="ruleForm"
          :rules="rules"
          ref="ruleFormRef"
          label-width="100px"
          class="demo-ruleForm"
          label-suffix=":"
        >
          <el-row :gutter="24">
            <el-col :span="20">
              <el-form-item label="质控模板" prop="ruleArray">
                <el-row type="flex" align="middle">
                  <el-cascader
                    popper-class="custom-cascader-panel"
                    ref="zkInfoRef"
                    :options="zkInfoArray"
                    style="width: 100%; "
                    v-model="ruleForm.ruleArray"
                    placeholder="请选择质控规则"
                    filterable
                    clearable
                    @change="handleRuleIdChange"
                  >
                    <template #default="{ node, data }">
                      <span v-if="node.level === 1">{{ data.label }}</span>
                      <el-tooltip
                        v-else
                        :content="data.label"
                        placement="top-start"
                        :visible="showTooltip"
                      >
                        <span>{{ data.label }}</span>
                      </el-tooltip>
                    </template>
                  </el-cascader>
                  <i
                    class="el-icon-plus"
                    style="font-size: 28px;margin-left: 10px;"
                    @click="addRule"
                  ></i>
                </el-row>
              </el-form-item>
            </el-col>
          </el-row>
          <el-row :gutter="24">
            <el-col :span="8">
              <el-form-item label="病案号">
                <el-input v-model="baseInfo.AAA28" disabled placeholder="请输入病案号"></el-input>
              </el-form-item>
            </el-col>
            <el-col :span="8">
              <el-form-item label="床位号">
                <el-input v-model="baseInfo.CH" disabled placeholder="请输入床位号"></el-input>
              </el-form-item>
            </el-col>
            <el-col :span="8">
              <el-form-item label="患者姓名">
                <el-input v-model="baseInfo.BRXM" disabled placeholder="请输入患者姓名"></el-input>
              </el-form-item>
            </el-col>
          </el-row>
          <el-row :gutter="24">
            <el-col :span="8">
              <el-form-item label="接收端" prop="cate">
                <el-select
                  v-model="ruleForm.cate"
                  filterable
                  clearable
                  placeholder="请选择接收端"
                  style="width: 100%"
                >
                  <el-option
                    v-for="item of receiving"
                    :key="item.id"
                    :label="item.name"
                    :value="item.id"
                  />
                </el-select>
              </el-form-item>
            </el-col>
            <el-col :span="8">
              <el-form-item label="接收人" prop="JSR">
                <el-select
                  v-model="ruleForm.JSR"
                  filterable
                  clearable
                  multiple
                  :filter-method="filterRecipient"
                  placeholder="请选择接收人"
                  style="width: 100%"
                  @change="handleJSRChange"
                >
                  <el-option
                    v-for="item of recipient"
                    :key="item.code"
                    :label="`${item.name} ${item.code}`"
                    :value="`${item.code}`"
                  />
                </el-select>
              </el-form-item>
            </el-col>
            <el-col :span="8">
              <el-form-item label="接收科室" prop="JSKS">
                <el-select
                  v-model="ruleForm.JSKS"
                  filterable
                  clearable
                  multiple
                  :filter-method="filterDeportments"
                  placeholder="请选择接收科室"
                  style="width: 100%"
                  disabled
                >
                  <el-option
                    v-for="(item,index) of deportments"
                    :key="index"
                    :label="`${item.name}`"
                    :value="`${item.dep_id}`"
                  />
                </el-select>
              </el-form-item>
            </el-col>
          </el-row>
          <el-row :gutter="24">
            <el-col :span="8">
              <el-form-item label="质控人" prop="ZKR">
                <el-input v-model="ruleForm.ZKR" placeholder="请输入质控人"></el-input>
              </el-form-item>
            </el-col>
            <el-col :span="8">
              <el-form-item label="扣分">
                <el-input v-model="ruleFormDisabled.score" placeholder="请输入扣分" disabled></el-input>
              </el-form-item>
            </el-col>
            <el-col :span="8">
              <el-form-item label="整改期限" prop="correction_date">
                <el-input
                  style="width: 100%;"
                  v-model="ruleForm.correction_date"
                  controls-position="right"
                  :min="0"
                  :max="100"
                  placeholder="请输入整改期限"
                >
                  <template slot="append">小时</template>
                </el-input>
              </el-form-item>
            </el-col>
          </el-row>
          <el-row :gutter="24">
            <!-- <el-col :span="8">
            <el-form-item label="状态" placeholder="请输入状态">
              <el-select v-model="ruleFormDisabled.level" disabled>
                <el-option v-for="(item,index) of addRuleOptions.levelArray" :key="index" :label="item.name" :value="item.id"></el-option>
              </el-select>
            </el-form-item>
            </el-col>-->
            <el-col :span="24">
              <el-form-item label="质控目录" prop="error_field">
                <el-input v-model="ruleForm.error_field" placeholder="请输入质控目录"></el-input>
              </el-form-item>
            </el-col>
          </el-row>
          <el-row :gutter="24">
            <el-col :span="24">
              <el-form-item label="质控内容" prop="notice">
                <el-input type="textarea" v-model="ruleForm.notice" placeholder="请填写质控内容"></el-input>
              </el-form-item>
            </el-col>
          </el-row>
          <el-row :gutter="24">
            <el-col :span="24">
              <el-form-item label="质控依据" prop="basis">
                <el-input type="textarea" v-model="ruleForm.basis" placeholder="请填写质控依据"></el-input>
              </el-form-item>
            </el-col>
          </el-row>
        </el-form>
        <span slot="footer" class="dialog-footer">
          <el-button type="primary" :loading="loading" @click="onSubmit">发送整改通知</el-button>
        </span>
      </el-dialog>
    </DraggableDialog>

    <el-dialog title="添加质控规则" center :visible.sync="addRuleDialog" width="460px">
      <el-form :inline="true" :rules="zkRules" :model="addRuleForm" ref="addRuleFormRef">
        <el-form-item label="质控类型" prop="zk_type">
          <el-radio v-model="addRuleForm.zk_type" label="1">首页质控</el-radio>
          <el-radio v-model="addRuleForm.zk_type" label="2">病历质控</el-radio>
        </el-form-item>
        <el-form-item label="规则名称" prop="field">
          <el-input v-model="addRuleForm.field" placeholder="请输入规则名称" style="width: 300px;"></el-input>
        </el-form-item>
        <el-form-item label="错误扣分" prop="down">
          <el-input
            v-model="addRuleForm.down"
            placeholder="请输入 0.5 到 100 之间的数字"
            style="width: 300px;line-height: 0px;"
          ></el-input>
          <span
            style="font-size: 12px;display: flex;line-height: 15px;padding-top: 5px;"
          >请输入0.5-100之间的数字,1位小数</span>
        </el-form-item>
        <el-form-item label="错误级别" prop="level">
          <el-select v-model="addRuleForm.level" placeholder="请选择错误级别" style="width: 300px;">
            <el-option
              v-for="(item,index) in addRuleOptions.levelArray"
              :key="index"
              :label="item.name"
              :value="item.id"
            ></el-option>
          </el-select>
        </el-form-item>
        <el-form-item label="缺陷分类" prop="type" v-if="addRuleForm.zk_type == 1">
          <el-select v-model="addRuleForm.type" placeholder="请选择缺陷分类" style="width: 300px;">
            <el-option
              v-for="(item,index) in addRuleOptions.typeArray"
              :key="index"
              :label="item.name"
              :value="item.id"
            ></el-option>
          </el-select>
        </el-form-item>
        <el-form-item label="缺陷类型" prop="error_type" v-if="addRuleForm.zk_type == 1">
          <el-select v-model="addRuleForm.error_type" placeholder="请选择缺陷类型" style="width: 300px;">
            <el-option
              v-for="(item,index) in addRuleOptions.errorTypeArray"
              :key="index"
              :label="item.name"
              :value="item.id"
            ></el-option>
          </el-select>
        </el-form-item>
        <el-form-item label="缺陷类别" prop="category" v-if="addRuleForm.zk_type == 1">
          <el-select v-model="addRuleForm.category" placeholder="请选择缺陷类别" style="width: 300px;">
            <el-option
              v-for="(item,index) in addRuleOptions.categoryArray"
              :key="index"
              :label="item.name"
              :value="item.id"
            ></el-option>
          </el-select>
        </el-form-item>
        <!-- <el-form-item label="质控描述" prop="desc" style="margin-left: 10px;">
          <el-input type="textarea" v-model="addRuleForm.desc" placeholder="请填写质控描述" style="width: 300px;"></el-input>
        </el-form-item>-->
      </el-form>
      <el-footer
        style="display: flex; justify-content: center; align-items: center; padding: 20px 0;"
      >
        <el-button @click="addRule">取消</el-button>
        <el-button type="primary" @click="addRuleSubmit">提交</el-button>
      </el-footer>
    </el-dialog>
  </div>
</template>

<script>
import { getRuleData, getBlInfo, addCaseQuality, getStaffListData, getCaseCate, getSelectValue, getBrry } from '@/api/qc';
import DraggableDialog from '@/components/draggable-dialog';
export default {
  components: { DraggableDialog },
  props: {
    data: {
      type: Object,
      default() {
        return {
          bSwitch: false,
          text: '',
          blbh: '',
        };
      },
    },
    catalogName: {
      type: String,
      default() {
        return '';
      },
    },

    // AAA28: {
    //   type: String,
    //   default() {
    //     return '';
    //   },
    // },
    MED_REC_ID: {
      type: [String, Number], // 根据实际类型定义
      required: true, // 是否必传
    },
    // CWH: {
    //   type: [String, Number], // 根据实际类型定义
    //   required: true,
    // },
    // AAA29: {
    //   type: [String, Number], // 根据实际类型定义
    //   required: true,
    // },
    // JSKS: {
    //   type: [String, Number], // 根据实际类型定义
    //   required: true,
    // },
    currentTreeItem: {
      type: Object, // 根据实际类型定义
      required: false,
    },
    currentTreeBLBH: {
      type: [String, Number],
      required: false,
    },
  },
  data() {
    // 自定义质控规则-错误扣分校验规则
    const validateValue = (rule, value, callback) => {
      if (value === '' || value === null) {
        return callback(new Error('请输入数值'));
      }

      if (isNaN(value)) {
        return callback(new Error('必须为有效数字'));
      }

      // 校验小数位数
      const decimalPart = value.toString().split('.')[1];
      if (decimalPart && decimalPart.length > 1) {
        return callback(new Error('最多只能有1位小数'));
      }

      // 校验数值范围
      if (value < 0.5 || value > 100) {
        return callback(new Error('数值必须在0.5到100之间'));
      }

      callback();
    };
    return {
      //添加质控
      zkTypeArray: [
        { label: '首页质控', value: 1 },
        { label: '病历质控', value: 2 },
      ],
      addRuleOptions: {
        levelArray: [
          { id: 1, name: '强制' },
          { id: 2, name: '建议' },
        ],
        typeArray: [
          { id: 1, name: '患者基本信息' },
          { id: 2, name: '诊疗信息' },
          { id: 3, name: '费用信息' },
        ],
        errorTypeArray: [
          { id: 1, name: '逻辑性' },
          { id: 2, name: '规范性' },
          { id: 3, name: '编码' },
        ],
        categoryArray: [
          { id: 1, name: 'A类' },
          { id: 2, name: 'B类' },
          { id: 3, name: 'C类' },
          { id: 4, name: 'D类' },
        ],
      },
      zkRules: {
        zk_type: [{ required: true, message: '请选择质控类型', trigger: 'xx' }],
        field: [{ required: true, message: '请填写规则名称', trigger: 'xx' }],
        down: [
          { required: true, message: '请输入数值', trigger: 'xx' },
          { validator: validateValue, trigger: 'xx' },
        ],
        level: [{ required: true, message: '请选择缺陷类型', trigger: 'xx' }],
        type: [{ required: true, message: '请选择缺陷分类', trigger: 'xx' }],
        error_type: [{ required: true, message: '请选择缺陷类型', trigger: 'xx' }],
        category: [{ required: true, message: '请选择缺陷类别', trigger: 'xx' }],
      },
      zkInfoArray: [], //
      addRuleDialog: false,
      addRuleForm: { zk_type: '1' },
      //
      loading: false,
      selectData: {
        rule: [],
        category: [],
        title: [],
        type: [],
        level: [],
        zgjb: [],
      },
      deportments: [],
      recipient: [],
      caseCates: [],
      receiving: [],
      categoryOptions: [],
      ruleForm: {
        ruleArray: [], // 质控模板
        cate: '', // 接收端
        JSR: [], // 接收人
        JSKS: [], // 接收科室
        ZKR: localStorage.getItem('realname') || '',
        correction_date: '', // 整改期限
        error_field: this.catalogName, // 质控目录
        // ZKR_CODE: '', // 质控人工号
        basis: '', // 质控依据
        notice: this.data.text, // 质控内容
        MED_REC_ID: this.MED_REC_ID,
        blbh: this.data.blbh || '',
        ZKR_CODE: localStorage.getItem('loginName') || '',
      },
      ruleFormDisabled: {
        level: '',
        score: '',
      },
      rules: {
        ruleArray: [{ required: true, message: '请选择', trigger: 'blur' }],
        cate: [{ required: true, message: '请选择', trigger: 'blur' }],
        JSR: [{ required: true, message: '请选择', trigger: 'blur' }],
        JSKS: [{ required: true, message: '请选择', trigger: 'blur' }],
        ZKR: [{ required: true, message: '请输入', trigger: 'blur' }],
        correction_date: [{ required: true, message: '请输入', trigger: 'blur' }],
        error_field: [{ required: true, message: '请输入', trigger: 'blur' }],
        basis: [{ required: true, message: '请输入', trigger: 'blur' }],
        notice: [{ required: true, message: '请输入', trigger: 'change' }],
      },
      baseInfo: {},
    };
  },

  mounted() {
    this.getRuleData();
    this.getStaff();
    this.getDeportmentList();
    this.getCaseCateList();
    // this.receivingEnd();
    this.zkSelectValue();
    this.getZkInfo();
    this.getBlsy();
    this.getBaseInfo();
  },
  methods: {
    handleJSRChange(e) {
      let depIds = [];
      this.recipient.forEach(element => {
        if (e.includes(element.code)) {
          depIds.push(`${element.dep_id}`);
        }
      });
      this.ruleForm.JSKS = depIds;
    },
    handleRuleIdChange(e) {
      this.$nextTick(() => {
        const nodeData = this.$refs.zkInfoRef.getCheckedNodes(true);
        console.log('选中', nodeData);
        this.ruleFormDisabled.level = nodeData[0].data.level;
        this.ruleFormDisabled.score = nodeData[0].parent.value == 1 ? nodeData[0].data.down : nodeData[0].data.score;
        this.receivingEnd(nodeData[0].parent.value);
      });
    },
    success(msg) {
      this.$message({
        message: msg,
        type: 'success',
      });
    },

    getBaseInfo() {
      getBrry({ zyh: this.MED_REC_ID }).then(res => {
        if (res.code == 200) {
          this.baseInfo = res.data || {};
        }
      });
    },

    getBlsy() {
      console.log('===================currentTreeItem', this.currentTreeItem);
      // let params = this.$props.currentTreeItem && this.$props.currentTreeItem.blbh ? {
      //   blbh: this.$props.currentTreeItem.blbh,
      //   zyh: this.MED_REC_ID
      // } : {
      //   zyh: this.MED_REC_ID
      // }
      const params = {
        blbh: this.data.blbh,
        zyh: this.MED_REC_ID,
      };
      this.$axios
        .get('/get_bl_blsy', {
          params,
        })
        .then(res => {
          if (res.code == 200) {
            if (Array.isArray(res.data) && !!res.data.length) {
              this.ruleForm.JSR = res.data.map(item => `${item.SYYS}`);
              this.ruleForm.JSKS = res.data.map(item => `${item.dep_id}`);
            }
          }
        });
    },
    //获取人工质控规则
    getZkInfo() {
      this.$axios.post('/artificial/department/getZkInfo', {}).then(res => {
        this.zkInfoArray = res.data;
      });
    },
    //添加质控弹窗状态
    addRule() {
      this.addRuleDialog = !this.addRuleDialog;
    },
    //添加质控规则提交
    addRuleSubmit() {
      this.$refs.addRuleFormRef.validate(valid => {
        if (valid) {
          let params = this.addRuleForm;
          this.$axios.post('/artificial/department/addRule', params).then(res => {
            if (res.code == 200) {
              this.getZkInfo();
              this.addRule();
              this.success(res.msg);
            }
          });
        } else {
          console.log('error submit!!');
          return false;
        }
      });
    },
    // 质控分类
    querySearchCategory(queryString, cb) {
      var categorys = this.selectData.category;
      var results = queryString ? categorys.filter(this.createFilterCategory(queryString)) : categorys;
      // 调用 callback 返回建议列表的数据
      cb(results);
    },
    createFilterCategory(queryString) {
      return restaurant => {
        return restaurant.toLowerCase().indexOf(queryString.toLowerCase()) === 0;
      };
    },
    // 质控项目
    querySearchTitle(queryString, cb) {
      var titles = this.selectData.title;
      var results = queryString ? titles.filter(this.createFilterTitle(queryString)) : titles;
      // 调用 callback 返回建议列表的数据
      cb(results);
    },
    createFilterTitle(queryString) {
      return restaurant => {
        return restaurant.toLowerCase().indexOf(queryString.toLowerCase()) === 0;
      };
    },
    handleSelectTitle(item) {
      this.$set(this.ruleForm, 'title', item);
    },

    // 获取规则数据
    getRuleData() {
      getRuleData().then(res => {
        this.$set(this, 'selectData', res.data);
      });
    },
    // 部门
    getDeportmentList() {
      this.$axios
        .get('/user/depDropDown')
        .then(res => {
          this.deportments = res.data;
        })
        .catch(error => {
          console.log(error);
        });
    },

    filterDeportments(val) {
      if (val) {
        // 过滤逻辑，根据输入的值模糊匹配姓名和工号
        this.deportments = this.deportments.filter(item => {
          return item.name.includes(val) || item.id.includes(val);
        });
      } else {
        // 若输入为空，显示所有选项
        this.getDeportmentList();
      }
    },
    // 类别
    getCaseCateList() {
      this.$axios
        .post('/bl_zk/getCaseCate')
        .then(res => {
          const { data } = res;

          if (data.length) {
            this.caseCates = data;
          }
        })
        .catch(error => {
          console.log(error);
        });
    },
    // 提交
    onSubmit() {
      this.$refs.ruleFormRef.validate(valid => {
        if (valid) {
          this.$axios
            .post('artificial/department/addQualityControlInfo', {
              ...this.ruleForm,
              JSR: this.ruleForm.JSR.join(','),
              JSKS: this.ruleForm.JSKS.join(','),
            })
            .then(res => {
              if (res.code == 200) {
                this.$emit('close');
                this.success('发送成功');
              }
            });
        } else {
          console.log('error submit!!');
          return false;
        }
      });
    },

    // 接收人
    getStaff() {
      getStaffListData().then(res => {
        const { data = [] } = res;
        this.recipient = data;
      });
    },
    filterRecipient(val) {
      if (val) {
        // 过滤逻辑，根据输入的值模糊匹配姓名和工号
        this.recipient = this.recipient.filter(item => {
          return item.name.includes(val) || item.code.includes(val);
        });
      } else {
        // 若输入为空，显示所有选项
        this.getStaff();
      }
    },
    // 接收端
    receivingEnd(rule) {
      console.log('rule', rule);
      getCaseCate().then(res => {
        const { data } = res;
        this.receiving = [];
        if (data.length) {
          // data.forEach(ele => {
          //   this.receiving.push({
          //     id: ele.id,
          //     name: ele.name,
          //   });
          // });
          if (rule == 1) {
            this.receiving = data.filter(item => item.name != '医生端（病历）');
          } else if (rule == 2) {
            this.receiving = data.filter(item => item.name == '医生端（病历）');
          } else {
            this.receiving = [];
          }
        }
      });
    },
    // 质控目录
    zkSelectValue() {
      getSelectValue()
        .then(res => {
          // 将接口返回的数据转换为 el-cascader 所需的格式
          this.categoryOptions = this.transformData(res.data);
        })
        .catch(error => {
          console.error('获取选择值时出现错误:', error);
        });
    },
    // 转换数据格式
    transformData(data) {
      return data.map(item => ({
        id: item.id,
        label: item.field_name, // el-cascader 显示的字段
        children: item.child ? this.transformData(item.child) : null, // 递归处理子节点
      }));
    },

    // 处理选中的值
    handleCategoryChange(value) {
      // 根据选中的值执行逻辑
      if (value.length > 0) {
        const selectedIds = value; // 选中的 ID 数组
        const selectedNames = this.getSelectedNames(value); // 选中的名称数组
      }
    },
    // 获取选中的名称
    getSelectedNames(selectedIds) {
      let options = this.categoryOptions;
      const names = [];
      selectedIds.forEach(id => {
        const item = options.find(opt => opt.id === id);
        if (item) {
          names.push(item.label);
          if (item.children) options = item.children;
        }
      });
      return names;
    },
    customFilterMethod(node, keyword) {
      if (node.label.toLowerCase().includes(keyword.toLowerCase())) {
        return true;
      }

      // 递归检查子节点
      if (node.children && node.children.length > 0) {
        return node.children.some(child => this.customFilterMethod(child, keyword));
      }
      return false;
    },
    handleCategoryChange(value) {
      console.log('选中的值:', value);
    },
  },
};
</script>
<style>
.custom-cascader-panel .el-cascader-menu__wrap {
  height: 400px !important;
  overflow-y: auto !important;
  width: max-content !important;
  max-width: 500px !important;
  min-width: inherit !important;
}
.custom-cascader-panel {
  max-height: 420px !important;
  overflow: hidden !important;
}
</style>

<style lang="scss" scoped>
//::v-deep .el-form-item__content{line-height: 0px;}
.demo-ruleForm {
  width: 95%;
}

::v-deep .el-dialog__header {
  background-color: hsl(205.32deg 43.43% 49.22%);
}

::v-deep .el-dialog__title {
  color: #fff;
}

::v-deep .el-dialog__title {
  color: #fff;
}

::v-deep .el-dialog__close {
  color: #fff;
  border: 1px solid #fff;
  border-radius: 20px;
}

::v-deep .el-select .el-input__inner {
  overflow: hidden;
  white-space: nowrap;
}

::v-deep .el-select .el-select__tags {
  display: flex;
  flex-wrap: nowrap;
  overflow-x: auto;
  max-width: 100%;
}

::v-deep .el-select .el-select__tags::-webkit-scrollbar {
  display: none;
}

::v-deep .el-select .el-select__tags {
  -ms-overflow-style: none;
  scrollbar-width: none;
}

::v-deep .el-select .el-tag {
  margin-right: 4px;
  white-space: nowrap;
}
</style>
