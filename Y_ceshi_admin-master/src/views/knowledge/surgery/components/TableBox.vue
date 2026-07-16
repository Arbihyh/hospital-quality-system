<template>
  <div class="app-container">
    <div class="btn-box">
      <el-upload
        class="upload-btn"
        :multiple="false"
        :limit="1"
        :show-file-list="false"
        action="#"
        :before-upload="beforeUpload"
      >
        <el-button type="primary" size="small" icon="el-icon-upload">导入</el-button>
      </el-upload>
      <el-button type="primary" icon="el-icon-download" @click="onExport">导出</el-button>
      <el-button type="primary" plain icon="el-icon-download" @click="onTemplateExport">模板导出</el-button>
    </div>
    <el-table
      v-loading="loading"
      :data="data"
      style="width: 100%"
    >
      <el-table-column
        v-if="codes.includes('FLAG')"
        prop="FLAG"
        label="序号"
        width="200"
        show-overflow-tooltip
        fixed="left"
      />
      <el-table-column
        v-if="codes.includes('KSMC')"
        prop="KSMC"
        label="科室"
        width="200"
        show-overflow-tooltip
      />
      <el-table-column
        v-if="codes.includes('SSMC')"
        prop="SSMC"
        label="手术名称"
        width="200"
        show-overflow-tooltip
      />
      <el-table-column
        v-if="codes.includes('BM')"
        prop="BM"
        label="手术别名"
        width="200"
        show-overflow-tooltip
      />
      <el-table-column
        v-if="codes.includes('SSBM')"
        prop="SSBM"
        label="手术编码"
        width="200"
        show-overflow-tooltip
      />
      <el-table-column
        v-if="codes.includes('BFZ')"
        prop="BFZ"
        label="并发症"
        width="200"
        show-overflow-tooltip
      />
      <el-table-column
        v-if="codes.includes('JC')"
        prop="JC"
        label="检查"
        width="200"
        show-overflow-tooltip
      />
      <el-table-column
        v-if="codes.includes('JJ')"
        prop="JJ"
        label="检验"
        width="200"
        show-overflow-tooltip
      />
      <el-table-column
        v-if="codes.includes('CKWX')"
        prop="CKWX"
        label="参考文献"
        width="200"
        show-overflow-tooltip
      />
      <el-table-column
        v-if="codes.includes('created_at')"
        prop="created_at"
        label="创建时间"
        width="200"
        show-overflow-tooltip
      />
      <el-table-column
        v-if="codes.includes('updated_at')"
        prop="updated_at"
        label="更新时间"
        width="200"
      />
      <el-table-column
        prop=""
        label="操作"
        width="200"
        fixed="right"
      >
        <template slot-scope="scope">
          <el-button type="text" @click="onEdit(scope.row)">编辑</el-button>
          <el-button type="text" @click="onDelConfirm(scope.row)">删除</el-button>
        </template>
      </el-table-column>
    </el-table>
  </div>
</template>

<script>
import { surgeryDelete } from '@/api/knowledge'
import { surgeryTemplateExport, surgeryImport } from '@/api/excel'

export default {
  props: {
    data: {
      type: Array,
      default() {
        return []
      }
    },
    loading: {
      type: Boolean,
      default() {
        return false
      }
    },
    codes: {
      type: Array,
      default() {
        return []
      }
    }
  },
  computed: {
    actionUrl() {
      return `${process.env.VUE_APP_BASE_API}/disease/diseaseImport`
    }
  },
  methods: {
    beforeUpload(file) {
      console.log(file, '导入')
      surgeryImport({ file }).then(res => {
        if (res.data.c === 0) {
          this.$emit('refresh')
          this.$message.success('成功')
        }
      })
    },
    // 删除
    onDelConfirm(row) {
      this.$confirm(`是否删除【${row.SSMC}】信息?`, '提示', {
        confirmButtonText: '确定',
        cancelButtonText: '取消',
        type: 'warning'
      }).then(() => {
        surgeryDelete({ id: row.id }).then(res => {
          const { m } = res
          this.$message.success(m || '成功')
          this.$emit('refresh')
        })
      })
    },
    // 编辑
    onEdit(row) {
      this.$emit('edit', row)
    },
    // 模板导出
    onTemplateExport() {
      surgeryTemplateExport().then(res => {
        const content = res.data // 后台返回二进制数据
        const blob = new Blob([content])
        const fileName = `手术库模板.xlsx`
        if ('download' in document.createElement('a')) { // 非IE下载
          const elink = document.createElement('a')
          elink.download = fileName
          elink.style.display = 'none'
          elink.href = URL.createObjectURL(blob)
          document.body.appendChild(elink)
          elink.click()
          URL.revokeObjectURL(elink.href) // 释放URL 对象
          document.body.removeChild(elink)
        } else { // IE10+下载
          navigator.msSaveBlob(blob, fileName)
        }
      })
    },
    // 模板导出
    onExport() {
      this.$emit('export')
    }
  }
}
</script>

<style lang="scss" scoped>
.btn-box {
  text-align: right;
  margin-bottom: 15px;
}
.upload-btn {
  display: inline-block;
  margin-right: 10px;
}
</style>
