<template>
  <div>
    <el-row>
      <el-button type="primary" @click="addVisible = true;">添加规则</el-button>
    </el-row>
    <el-table :data="pageList">
      <el-table-column prop="id" label="ID" />
      <el-table-column prop="auth" label="验证字段" />
      <el-table-column prop="field" label="验证字段名称" />
      <el-table-column prop="rule" label="验证规则" />
      <el-table-column prop="relation" label="关联字段" />
      <el-table-column prop="relation_rule" label="关联规则" />
      <el-table-column prop="level" label="错误等级">
        <template slot-scope="scope">
          <span v-if="scope.row.level == 0">强制</span>
          <span v-else>建议</span>
        </template>
      </el-table-column>
      <el-table-column prop="desc" label="规则描述" />
      <el-table-column prop="type" label="缺陷分类">
        <template slot-scope="scope">
          <span v-if="scope.row.type === 0">患者基本信息</span>
          <span v-else-if="scope.row.type === 1">诊疗信息</span>
          <span v-else-if="scope.row.type === 2">费用信息</span>
        </template>
      </el-table-column>
      <el-table-column prop="type" label="缺陷类型">
        <template slot-scope="scope">
          <span v-if="scope.row.error_type === 0">逻辑性</span>
          <span v-else-if="scope.row.error_type === 1">规范性</span>
          <span v-else-if="scope.row.error_type === 2">编码</span>
        </template>
      </el-table-column>
      <el-table-column prop="category" label="错误类型">
        <template slot-scope="scope">
          <span v-if="scope.row.category === 0">A类</span>
          <span v-else-if="scope.row.category === 1">B类</span>
          <span v-else-if="scope.row.category === 2">C类</span>
          <span v-else-if="scope.row.category === 3">D类</span>
        </template>
      </el-table-column>
      <el-table-column label="操作">
        <template slot-scope="scope">
          <el-button type="primary" size="mini" plain @click="saveVisible=true;row = scope.row">修改</el-button>
          <el-button type="primary" size="mini" plain @click="del(scope.row.config_id)">删除</el-button>
        </template>
      </el-table-column>
    </el-table>
    <el-pagination
      background
      :current-page.sync="form.page"
      :page-size="form.limit"
      layout="prev, next"
      @current-change="list"
    />
    <el-dialog
      title="提示"
      :visible.sync="addVisible"
      width="30%"
    >
      <span>添加规则</span>
      <el-form>
        <el-row>
          <span>验证字段:</span>
          <el-input v-model="form.auth" placeholder="验证字段" />
        </el-row>
        <el-row>
          <span>验证字段名称:</span>
          <el-input v-model="form.field" placeholder="验证字段名称" />
        </el-row>
        <el-row>
          <span>验证规则:</span>
          <el-input v-model="form.rule" placeholder="验证规则" />
        </el-row>
        <el-row>
          <span>关联字段:</span>
          <el-input v-model="form.relation" placeholder="关联字段" />
        </el-row>
        <el-row>
          <span>关联规则:</span>
          <el-input v-model="form.relation_rule" placeholder="关联规则" />
        </el-row>
        <el-row>
          <span>规则描述:</span>
          <el-input v-model="form.desc" placeholder="规则描述" />
        </el-row>
        <el-row>
          <span>扣分:</span>
          <el-input v-model="form.down" placeholder="扣分" />
        </el-row>
        <el-row>
          <span>错误等级:</span>
          <el-select v-model="form.level">
            <el-option :value="0" label="强制" />
            <el-option :value="1" label="建议" />
          </el-select>
        </el-row>
        <el-row>
          <span>缺陷分类:</span>
          <el-select v-model="form.type">
            <el-option :value="0" label="患者基本信息" />
            <el-option :value="1" label="诊疗信息" />
            <el-option :value="2" label="费用信息" />
          </el-select>
        </el-row>
        <el-row>
          <span>缺陷类型:</span>
          <el-select v-model="form.error_type">
            <el-option :value="0" label="逻辑性" />
            <el-option :value="1" label="规范性" />
            <el-option :value="2" label="编码" />
          </el-select>
        </el-row>
        <el-row>
          <span>错误类型:</span>
          <el-select v-model="form.category">
            <el-option :value="0" label="A类" />
            <el-option :value="1" label="B类" />
            <el-option :value="2" label="C类" />
            <el-option :value="3" label="D类" />
          </el-select>
        </el-row>
      </el-form>
      <span slot="footer" class="dialog-footer">
        <el-button @click="addVisible=false">取 消</el-button>
        <el-button type="primary" @click="addReason">确 定</el-button>
      </span>
    </el-dialog>
    <el-dialog
      title="提示"
      :visible.sync="saveVisible"
      width="30%"
    >
      <span>修改规则</span>
      <el-form>
        <el-row>
          <span>验证字段:</span>
          <el-input v-model="row.auth" placeholder="验证字段" />
        </el-row>
        <el-row>
          <span>验证字段名称:</span>
          <el-input v-model="row.field" placeholder="验证字段名称" />
        </el-row>
        <el-row>
          <span>验证规则:</span>
          <el-input v-model="row.rule" placeholder="验证规则" />
        </el-row>
        <el-row>
          <span>关联字段:</span>
          <el-input v-model="row.relation" placeholder="关联字段" />
        </el-row>
        <el-row>
          <span>关联规则:</span>
          <el-input v-model="row.relation_rule" placeholder="关联规则" />
        </el-row>
        <el-row>
          <span>规则描述:</span>
          <el-input v-model="row.desc" placeholder="规则描述" />
        </el-row>
        <el-row>
          <span>扣分:</span>
          <el-input v-model="row.down" placeholder="扣分" />
        </el-row>
        <el-row>
          <span>错误等级:</span>
          <el-select v-model="row.level">
            <el-option :value="0" label="强制" />
            <el-option :value="1" label="建议" />
          </el-select>
        </el-row>
        <el-row>
          <span>缺陷分类:</span>
          <el-select v-model="row.type">
            <el-option :value="0" label="患者基本信息" />
            <el-option :value="1" label="诊疗信息" />
            <el-option :value="2" label="费用信息" />
          </el-select>
        </el-row>
        <el-row>
          <span>缺陷类型:</span>
          <el-select v-model="row.error_type">
            <el-option :value="0" label="逻辑性" />
            <el-option :value="1" label="规范性" />
            <el-option :value="2" label="编码" />
          </el-select>
        </el-row>
        <el-row>
          <span>错误类型:</span>
          <el-select v-model="row.category">
            <el-option :value="0" label="A类" />
            <el-option :value="1" label="B类" />
            <el-option :value="2" label="C类" />
            <el-option :value="3" label="D类" />
          </el-select>
        </el-row>
      </el-form>
      <span slot="footer" class="dialog-footer">
        <el-button @click="saveVisible=false">取 消</el-button>
        <el-button type="primary" @click="saveReason">确 定</el-button>
      </span>
    </el-dialog>
  </div>
</template>

<script>
import { configList, delConfig, saveConfig, addConfig } from '@/api/config'

export default {
  data() {
    return {
      pageList: [],
      form: {
        auth: '',
        field: '',
        rule: '',
        category: 0,
        error_type: 0,
        type: 0,
        level: 0,
        down: 0,
        desc: '',
        relation_rule: '',
        relation: '',
        limit: 20,
        page: 1
      },
      saveVisible: false,
      addVisible: false,
      row: {}
    }
  },
  created() {
    this.list()
  },
  methods: {
    list() {
      configList(this.form).then(res => {
        this.pageList = res.p.list
      })
    },
    del(id) {
      delConfig({ config_id: id }).then(res => {
        this.$message.success(res.m || 'ok')
        this.list()
      })
    },
    addReason() {
      addConfig(this.form).then(res => {
        this.$message.success(res.m || 'ok')
        this.addVisible = false
        this.list()
      })
    },
    saveReason() {
      saveConfig(this.row).then(res => {
        this.$message.success(res.m || 'ok')
        this.saveVisible = false
        this.list()
      })
    }
  }
}
</script>

<style scoped>
.el-row{
  margin-top: 10px;
  margin-bottom: 10px;
}
.avatar-uploader {
  border: 2px dashed #d9d9d9;
  width: 178px;
  border-radius: 6px;
  cursor: pointer;
  position: relative;
  overflow: hidden;
}
.avatar-uploader:hover {
  border-color: #409EFF;
}
.avatar-uploader-icon {
  font-size: 28px;
  color: #8c939d;
  width: 178px;
  height: 178px;
  line-height: 178px;
  text-align: center;
}
.avatar {
  width: 178px;
  height: 178px;
  display: block;
}
</style>
