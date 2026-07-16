<template>
  <div class="part-box">
    <div class="btn-box">
      <span class="page-msg">
        共找到 <span class="num">{{ page.total }}</span> 条结果
      </span>
      <el-button type="primary" @click="onCreate">新增</el-button>
    </div>
    <el-table
      :data="data"
      row-key="id"
      border
      default-expand-all
      :tree-props="{children: 'children', hasChildren: 'hasChildren'}">
      style="width: 100%">
      <el-table-column
        prop="name"
        label="指标名称">
      </el-table-column>
      <el-table-column
        prop="fenzi_name"
        label="分子"/>
      <el-table-column
        prop="fenmu_name"
        label="分母"/>
      <el-table-column
        prop=""
        label="操作"
        width="100">
        <template slot-scope="scope">
          <el-button type="text" @click="onEdit(scope.row)">编辑</el-button>
          <el-button type="text" style="color: #F56C6C;" @click="onDeleteConfirm(scope.row)">删除</el-button>
        </template>
      </el-table-column>
    </el-table>
    <!-- 新增、编辑二三级 -->
    <CreateDialogVue v-if="createData.bSwitch" :data="createData" @refresh="handleRefresh" />
    <!-- 编辑一级 -->
    <CreateMenuVue v-if="menuData.bSwitch" :data="menuData" @refresh="handleRefresh" />
  </div>
</template>

<script>
import CreateDialogVue from './CreateDialog.vue'
import CreateMenuVue from './CreateMenu.vue'
export default {
  components: {
    CreateDialogVue,
    CreateMenuVue
  },
  props: {
    data: {
      type: Array,
      default() {
        return []
      }
    },
    page: {
      type: Object,
      default() {
        return {
          total: 0
        }
      }
    }
  },
  data() {
    return {
      createData: {
        bSwitch: false,
        row: {}
      },
      menuData: {
        bSwitch: false,
        row: {}
      }
    }
  },
  methods: {
    onCreate() {
      this.createData.row = {}
      this.createData.bSwitch = true
    },
    onEdit(row) {
      if (row.pid) {
        this.createData.row = row
        this.createData.bSwitch = true
      } else {
        this.menuData.row = row
        this.menuData.bSwitch = true
      }
    },
    onDeleteConfirm(row) {
      this.$confirm('是否删除该数据?', '提示', {
          confirmButtonText: '确定',
          cancelButtonText: '取消',
          type: 'warning'
        }).then(() => {
          this.$axios2.post('/catalog_del', {id: row.id}).then(res => {
            this.$message({
              type: 'success',
              message: '删除成功!'
            });
            this.$emit('refresh')
          });
        })
    },
    getNames(str) {
      const names = []
      if (str) {
        const list = JSON.parse(str)
        list.map(item => {
          names.push(item.name)
        })
      }
      return names
    },
    handleRefresh() {
      this.$emit('refresh')
    }
  }
}
</script>

<style lang="scss" scoped>
.btn-box {
  text-align: right;
  margin-bottom: 15px;
  .page-msg {
    float: left;
    height: 40px;
    line-height: 40px;
    font-size: 14px;
    .num {
      color: #F56C6C;
    }
    .page {
      margin-left: 40px;
    }
    .page_limit_box {
      margin-left: 40px;
    }
  }
}
.link {
  cursor: pointer;
  color: #409EFF;
}
</style>
