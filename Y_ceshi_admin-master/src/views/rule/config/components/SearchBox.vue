<template>
  <div>
    <div>
      <div class="title">病历评价总分：100分</div>
      <div class="sub_msg">
        <span class="sub_level">病历等级</span>
        <span class="sub_text">甲级：91~100分</span>
        <span class="sub_text">乙级：75~90分</span>
        <span class="sub_text">丙级：0~74分</span>
      </div>
    </div>
    <el-form :inline="true" :model="data" class="demo-form-inline">
      <el-form-item label="">
        <el-select v-model="data.case_type" filterable clearable placeholder="病历类型" style="width: 198px;">
          <el-option label="出院记录" value="出院记录" />
          <el-option label="入院记录" value="入院记录" />
          <el-option label="病程类" value="病程类" />
          <el-option label="手术类" value="手术类" />
          <el-option label="医嘱" value="医嘱" />
          <el-option label="费用" value="费用" />
        </el-select>
      </el-form-item>
      <el-form-item label="">
        <el-cascader
          v-model="data.object"
          :options="objects"
          :props="{
            expandTrigger: 'hover',
            value: 'field_name',
            label: 'field_name',
            children: 'child'
          }"
          clearable
          filterable
          :show-all-levels="false"
          placeholder="质控项目"
          style="width: 198px;"
        />
      </el-form-item>
      <el-form-item label="">
        <el-select
          v-model="data.department"
          multiple
          collapse-tags
          placeholder="质控科室"
          filterable
          style="width: 198px;"
        >
          <el-option
            v-for="item in departments"
            :key="item.id"
            :label="item.dep_name"
            :value="item.dep_id"
          />
        </el-select>
      </el-form-item>
      <el-form-item label="">
        <el-select v-model="data.is_not" filterable clearable placeholder="单项否决" style="width: 198px;">
          <el-option label="是" :value="1" />
          <el-option label="否" :value="0" />
        </el-select>
      </el-form-item>
      <el-form-item label="">
        <el-select v-model="data.status" filterable clearable placeholder="质控状态" style="width: 198px;">
          <el-option label="开启" :value="1" />
          <el-option label="禁用" :value="2" />
        </el-select>
      </el-form-item>
      <el-form-item label="">
        <el-select v-model="data.type" filterable clearable placeholder="质控类型" style="width: 198px;">
          <el-option label="时效性" value="时效性" />
          <el-option label="完整性" value="完整性" />
          <el-option label="逻辑性" value="逻辑性" />
          <el-option label="内涵性" value="内涵性" />
          <el-option label="专病规则" value="专病规则" />
          <el-option label="专科规则" value="专科规则" />
          <el-option label="检查规则" value="检查规则" />
          <el-option label="检验规则" value="检验规则" />
          <el-option label="其他规则" value="其他规则" />
        </el-select>
      </el-form-item>
      <el-form-item label="">
        <el-select v-model="data.changjing" multiple collapse-tags filterable clearable placeholder="质控场景" style="width: 198px;">
          <el-option label="医生端" value="医生端" />
          <el-option label="编码员" value="编码员" />
          <el-option label="质控员" value="质控员" />
          <el-option label="国考" value="国考" />
          <el-option label="卫统" value="卫统" />
          <el-option label="医保" value="医保" />
        </el-select>
      </el-form-item>
      <el-form-item label="">
        <el-select v-model="data.error_level" filterable clearable placeholder="风险等级" style="width: 198px;">
          <el-option label="必改" :value="1" />
          <el-option label="建议" :value="2" />
        </el-select>
      </el-form-item>
      <el-form-item label="">
        <!-- <el-input-number v-model="data.score" :precision="1" :step="1" step-strictly :max="100" :min="0" controls-position="right" placeholder="扣分分值" style="width: 198px;" /> -->
        <el-input
          v-model="data.score"
          controls-position="right"
          placeholder="扣分分值"
          style="width: 100%;"
          oninput="this.value = this.value.replace(/[^\d.]/g, '').replace(/^\./g, '').replace(/\.{2,}/g, '.').replace('.', '$#$').replace(/\./g, '').replace('$#$', '.').replace(/^(\d+)\.(\d*)\.$/, '$1.$2')"
        />
      </el-form-item>
      <el-form-item label="">
        <el-input v-model="data.description" placeholder="错误描述" clearable style="width: 198px;" />
      </el-form-item>
      <el-form-item>
        <el-button type="primary" @click="onSubmit">查询</el-button>
        <el-button @click="onReset">重置</el-button>
      </el-form-item>
    </el-form>
  </div>
</template>

<script>

export default {
  props: {
    objects: {
      type: Array,
      default() {
        return []
      }
    },
    departments: {
      type: Array,
      default() {
        return []
      }
    }
  },
  data() {
    return {
      data: {
        changjing: [],
        department: [],
        object: '',
        type: '',
        is_not: '',
        description: '',
        score: '',
        error_level: '',
        status: ''
      }
    }
  },
  methods: {
    onSubmit() {
      this.$emit('search', this.data)
    },
    onReset() {
      this.data = {
        changjing: [],
        department: [],
        object: '',
        type: '',
        is_not: '',
        description: '',
        score: '',
        error_level: '',
        status: ''
      }
      this.$emit('rest', this.data)
    }
  }
}
</script>

<style lang="scss" scoped>
::v-deep .el-input-number{
  .el-input__inner{
    text-align: left;
  }
}
.title {
  font-size: 22px;
  line-height: 40px;
}
.sub_msg {
  margin-bottom: 20px;
  .sub_level {
    font-size: 18px;
    margin-right: 10px;
    line-height: 30px;
  }
  .sub_text {
    font-size: 14px;
    line-height: 24px;
  }
}
</style>
