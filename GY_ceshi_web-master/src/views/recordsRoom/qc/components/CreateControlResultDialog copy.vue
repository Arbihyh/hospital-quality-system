<template>
  <div>
    <el-dialog
      title="添加质控信息"
      :visible.sync="data.bSwitch"
      :close-on-click-modal="false"
      width="900px"
      top="7vh">
      <el-form :model="ruleForm" :rules="rules" ref="ruleForm" label-width="100px" class="demo-ruleForm" label-suffix=":">
        <el-row :gutter="0">
          <el-col :span="12">
            <el-form-item label="质控模板" prop="rule_id">
              <el-select v-model="ruleForm.rule_id" clearable filterable @change="handleRuleIdChange" placeholder="请选择">
                <el-option v-for="item of selectData.rule" :key="item.id" :label="item.category" :value="item.id"></el-option>
              </el-select>
            </el-form-item>
          </el-col>
        </el-row>
        <el-row :gutter="0">
          <el-col :span="8">
            <el-form-item label="住院号码" prop="ZYH">
              <el-input v-model="ruleForm.ZYH" disabled placeholder="请输入"></el-input>
            </el-form-item>
          </el-col>
          <el-col :span="8">
            <el-form-item label="质控人" prop="ZKR">
              <el-input v-model="ruleForm.ZKR" placeholder="请输入"></el-input>
            </el-form-item>
          </el-col>
          <el-col :span="8">
            <el-form-item label="质控科室" prop="ZKKS">
              <el-select v-model="ruleForm.ZKKS" filterable clearable placeholder="请选择" style="width: 100%;">
                <el-option v-for="item of deportments" :key="item.id" :label="item.name" :value="item.name" />
              </el-select>
            </el-form-item>
          </el-col>
          <el-col :span="8">
            <el-form-item label="接收人" prop="JSR">
              <el-input v-model="ruleForm.JSR" placeholder="请输入"></el-input>
            </el-form-item>
          </el-col>
          <el-col :span="8">
            <el-form-item label="接收科室" prop="JSKS">
              <el-input v-model="ruleForm.JSKS" placeholder="请输入"></el-input>
            </el-form-item>
          </el-col>
          <el-col :span="8">
            <el-form-item label="状态" prop="level">
              <el-select v-model="ruleForm.level" placeholder="请选择">
                <el-option v-for="item of selectData.level" :key="item.id" :label="item.name" :value="item.id"></el-option>
              </el-select>
            </el-form-item>
          </el-col>
          <el-col :span="8">
            <el-form-item label="扣分" prop="score">
              <el-input-number v-model="ruleForm.score" controls-position="right" :min="0" :max="100" placeholder="请输入" style="width: 100%;"></el-input-number>
            </el-form-item>
          </el-col>
          <el-col :span="8">
            <el-form-item label="整改级别" prop="ZGJB">
              <el-select v-model="ruleForm.ZGJB" placeholder="请选择">
                <el-option v-for="item of selectData.zgjb" :key="item" :label="item" :value="item"></el-option>
              </el-select>
            </el-form-item>
          </el-col>
          <el-col :span="8">
            <el-form-item label="整改期限" prop="ZGQX">
              <el-date-picker
                v-model="ruleForm.ZGQX"
                type="datetime"
                value-format="timestamp"
                placeholder="请选择"
                :picker-options="{ disabledDate: time => time.getTime() < Date.now() }"
                style="width: 100%;"
                >
              </el-date-picker>
            </el-form-item>
          </el-col>
          <el-col :span="8">
            <el-form-item label="质控分类" prop="category">
              <el-autocomplete
                class="inline-input"
                v-model="ruleForm.category"
                :fetch-suggestions="querySearchCategory"
                placeholder="请输入"
                @select="handleSelectCategory"
              >
                <template slot-scope="{ item }">
                  <div class="name">{{ item }}</div>
                </template>
              </el-autocomplete>
            </el-form-item>
          </el-col>

          <el-col :span="8">
            <el-form-item label="质控项目" prop="title">
              <el-autocomplete
                class="inline-input"
                v-model="ruleForm.title"
                :fetch-suggestions="querySearchTitle"
                placeholder="请输入"
                @select="handleSelectTitle"
              >
                <template slot-scope="{ item }">
                  <div class="name">{{ item }}</div>
                </template>
              </el-autocomplete>
            </el-form-item>
          </el-col>
          <el-col :span="8">
            <el-form-item label="质控类型" prop="type">
              <el-autocomplete
                class="inline-input"
                v-model="ruleForm.type"
                :fetch-suggestions="querySearchType"
                placeholder="请输入"
                @select="handleSelectType"
              >
                <template slot-scope="{ item }">
                  <div class="name">{{ item }}</div>
                </template>
              </el-autocomplete>
            </el-form-item>
          </el-col>
        </el-row>
        <el-row :gutter="0">
          <el-col :span="12">
            <el-form-item label="质控类别" prop="cate">
              <el-select v-model="ruleForm.cate" clearable filterable @change="handleRuleIdChange" placeholder="请选择">
                <el-option v-for="item of caseCates" :key="item.id" :label="item.name" :value="item.id"></el-option>
              </el-select>
            </el-form-item>
          </el-col>
        </el-row>
        <el-row :gutter="0">
          <el-col :span="24">
            <el-form-item label="质控内容" prop="basis">
              <el-input type="textarea" v-model="ruleForm.basis" :autosize="{ minRows: 4 }" placeholder="请输入"></el-input>
            </el-form-item>
          </el-col>
          <el-col :span="24">
            <el-form-item label="错误描述" prop="notice">
              <el-input type="textarea" v-model="ruleForm.notice" :autosize="{ minRows: 4 }" placeholder="请输入"></el-input>
            </el-form-item>
          </el-col>
        </el-row>
      </el-form>
      <span slot="footer" class="dialog-footer">
        <el-button type="primary" @click="onSubmit" :loading="loading">发送整改通知</el-button>
        <el-button @click="data.bSwitch = false">关 闭</el-button>
      </span>
    </el-dialog>
  </div>
</template>

<script>
import { getRuleData, getBlInfo, addCaseQuality } from '@/api/qc'

export default {
  props: {
    data: {
      type: Object,
      default() {
        return {
          bSwitch: false,
          text: '',
          blbh: ''
        }
      }
    },
    AAA28: {
      type: String,
      default() {
        return false;
      },
    },
  },
  data() {
    return {
      loading: false,
      selectData: {
        rule: [],
        category: [],
        title: [],
        type: [],
        level: [],
        zgjb: []
      },
      deportments: [],
      caseCates: [],
      ruleForm: {
        AAA28: '',
        ZYH: '',
        ZKR: '',
        ZKKS: '',
        JSR: '',
        JSKS: '',
        ZGJB: '',
        category: '',
        title: '',
        rule_id: '',
        type: '',
        notice: '',
        basis: '',
        level: '',
        ZGQX: '',
        score: undefined,
        cate: 1
      },
      rules: {
        AAA28: [
          { required: true, message: '请输入', trigger: 'blur' }
        ],
        ZKR: [
          { required: true, message: '请输入', trigger: 'blur' }
        ],
        ZKKS: [
          { required: true, message: '请输入', trigger: 'blur' }
        ],

        JSR: [
          { required: true, message: '请输入', trigger: 'blur' }
        ],
        JSKS: [
          { required: true, message: '请输入', trigger: 'blur' }
        ],
        ZGJB: [
          { required: true, message: '请选择', trigger: 'blur' }
        ],
        cate: [
          { required: true, message: '请选择', trigger: 'blur' }
        ],
        category: [
          { required: true, message: '请选择', trigger: 'change' }
        ],
        title: [
          { required: true, message: '请选择', trigger: 'change' }
        ],
        notice: [
          { required: true, message: '请输入', trigger: 'blur' }
        ],
        basis: [
          { required: true, message: '请输入', trigger: 'blur' }
        ],
        score: [
          { required: true, message: '请输入', trigger: 'blur' }
        ],
        level: [
          { required: true, message: '请选择', trigger: 'blur' }
        ],
      }
    }
  },
  mounted() {
    this.getRuleData()
    this.getBlInfo()
    this.getDeportmentList()
    this.getCaseCateList()
    this.$set(this.ruleForm, 'basis', this.data.text)
    this.$set(this.ruleForm, 'ZYH', this.AAA28)
  },
  methods: {
    // 质控分类
    querySearchCategory(queryString, cb) {
      var categorys = this.selectData.category;
      var results = queryString ? categorys.filter(this.createFilterCategory(queryString)) : categorys;
      // 调用 callback 返回建议列表的数据
      cb(results);
    },
    createFilterCategory(queryString) {
      return (restaurant) => {
        return (restaurant.toLowerCase().indexOf(queryString.toLowerCase()) === 0);
      };
    },
    handleSelectCategory(item) {
      this.$set(this.ruleForm, 'category', item)
    },
    // 质控项目
    querySearchTitle(queryString, cb) {
      var titles = this.selectData.title;
      var results = queryString ? titles.filter(this.createFilterTitle(queryString)) : titles;
      // 调用 callback 返回建议列表的数据
      cb(results);
    },
    createFilterTitle(queryString) {
      return (restaurant) => {
        return (restaurant.toLowerCase().indexOf(queryString.toLowerCase()) === 0);
      };
    },
    handleSelectTitle(item) {
      this.$set(this.ruleForm, 'title', item)
    },
    // 质控类型
    querySearchType(queryString, cb) {
      var types = this.selectData.type;
      var results = queryString ? types.filter(this.createFilterType(queryString)) : types;
      // 调用 callback 返回建议列表的数据
      cb(results);
    },
    createFilterType(queryString) {
      return (restaurant) => {
        return (restaurant.toLowerCase().indexOf(queryString.toLowerCase()) === 0);
      };
    },
    handleSelectType(item) {
      this.$set(this.ruleForm, 'type', item)
    },
    // 获取规则数据
    getRuleData() {
      getRuleData().then(res => {
        this.$set(this, 'selectData', res.data)
      })
    },
    // 获取病历相关信息
    getBlInfo() {
      getBlInfo({ blbh: this.data.blbh }).then(res => {
        const { AAA28, JSKS, JSR, ZYH } = res.data
        this.$set(this.ruleForm, 'AAA28', AAA28)
        // this.$set(this.ruleForm, 'ZYH', ZYH)
        this.$set(this.ruleForm, 'JSKS', JSKS)
        this.$set(this.ruleForm, 'JSR', JSR)
      })
    },
    // 质控模板变化
    handleRuleIdChange(val) {
      if (val) {
        const item = this.selectData.rule.filter(item => item.id)[0]
        const { category, level, notice, score, type, title } = item
        this.$set(this.ruleForm, 'category', category)
        this.$set(this.ruleForm, 'level', level)
        this.$set(this.ruleForm, 'notice', notice)
        this.$set(this.ruleForm, 'score', score)
        this.$set(this.ruleForm, 'type', type)
        this.$set(this.ruleForm, 'title', title)
      }
    },

    // 部门
    getDeportmentList() {
      this.$axios.get('/user/depDropDown').then(res => {
        const { data } = res
        if (data.length) {
          data.forEach(ele => {
            this.deportments.push({
              id: ele.dep_id,
              name: ele.name
            })
          });
        }
      }).catch(error => {
        console.log(error)
      })
    },
    // 类别
    getCaseCateList() {
      this.$axios.post('/bl_zk/getCaseCate').then(res => {
        const { data } = res

        if (data.length) {
          this.caseCates = data
        }
      }).catch(error => {
        console.log(error)
      })
    },
    // 提交
    onSubmit() {
      this.$refs['ruleForm'].validate((valid) => {
        if (valid) {
          const {
            ZYH,
            ZKR,
            ZKKS,
            JSR,
            JSKS,
            ZGJB,
            category,
            title,
            rule_id,
            type,
            notice,
            basis,
            level,
            ZGQX,
            score,
            cate
          } = this.ruleForm
          const params = {
            ZYH,
            ZKR,
            ZKKS,
            JSR,
            JSKS,
            ZGJB,
            category,
            title,
            rule_id,
            type,
            notice,
            basis,
            level,
            ZGQX: ZGQX/1000,
            score,
            cate
          }
          this.loading=true
          addCaseQuality(params).then(res => {
            const { m } = res
            this.$message.success(m || '成功')
            this.data.bSwitch = false
            this.$emit('refresh')
          }).finally(res => {
            this.loading=false
          })
        } else {
          return false;
        }
      });
    }
  }
}
</script>

<style lang="scss" scoped>
.demo-ruleForm {
 width: 95%;
}
</style>
