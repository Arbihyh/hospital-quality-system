<template>
  <el-table
    v-loading="loading"
    :data="data"
    style="width: 100%"
    @selection-change="handleSelectionChange"
  >
   <!-- <el-table-column type="selection" width="55"></el-table-column> -->
   <el-table-column type="index" label="序号" width="80" />
    <!-- 动态列 -->
    <el-table-column v-for="column in columns" :key="column.prop" :prop="column.prop" :label="column.label">
      <template slot-scope="scope">
        <template v-if="scope.row.isEditing">
            <!-- <el-input v-model="scope.row.editData[column.prop]"></el-input> -->
            <el-select 
              v-model="scope.row.editData[column.prop]" 
              filterable 
              clearable 
              placeholder="请选择"
            >
              <el-option 
                v-for="item of staffListData" 
                :key="item.code" 
                :label="item.name" 
                :value="item.code" 
              />
            </el-select>
        </template>
        <template v-else>
          {{ scope.row[column.prop] }}
        </template>
      </template>
    </el-table-column>
    <el-table-column label="操作" width="200" v-if="action != 'DETAIL'">
      <template slot-scope="scope">
        <template v-if="scope.row.isEditing">
          <el-button type="text" @click="saveRow(scope.row)">保存</el-button>
          <el-button type="text" @click="cancelEdit(scope.row)">取消</el-button>
        </template>
        <template v-else>
          <el-button type="text" icon="el-icon-edit" @click="editRow(scope.row, scope.$index)"></el-button>
          <el-button type="text" style="color:#ef1f3a" icon="el-icon-delete" @click="deleteRow(scope.row)"></el-button>
        </template>
      </template>
    </el-table-column>
  </el-table>
</template>

<script>
import moment from 'moment/moment';

export default {
  emits: ['onUpdate'],
  props: {
    action: {
      type: String,
      default() {
        return '';
      },
    },
    data: {
      type: Array,
      default() {
        return [];
      },
    },
    loading: {
      type: Boolean,
      default() {
        return false;
      },
    },
    staffListData: {
      type: Boolean,
      default() {
        return [];
      },
    },
  },
  data() {
    return {
      columns: [
        { prop: 'ssyq', label: '所属院区' },
        { prop: 'ssks', label: '所属科室' },
        { prop: 'syys', label: '审阅医师' },
        { prop: 'syks', label: '审阅科室' }
      ],
      selectedArray: []
    }
  },
  methods: {
    moment,
    toPage(row) {
      const { ZYH } = row;
      this.$router.push({ path: '/qc/caseViews', query: {
        ZYH,
        from: 'review'
      }, meta: {
        title: '申诉详情'
      }});
    },

    handleSelectionChange(val) {
      this.selectedArray = val;
    },
    editRow(row, index) {
      row.editData = { ...row }
      this.$parent.tableData.splice(index, 1, {
        ...this.$parent.tableData[index],
        isEditing: true
      })
    },
    saveRow(row) {
      Object.assign(row, row.editData)
      if (row.id) { // 编辑
        console.log('>>>>>>>>编辑', row.editData)
      } else {
        console.log('>>>>>>>>新增', row.editData)
      }
      row.isEditing = false
      this.$emit('onUpdate')
    },
    cancelEdit(row, index) {
      if (row.id) {
        row.isEditing = false
      } else {
        this.$parent.tableData.splice(index, 1) 
      }
    },
    deleteRow(row) {
      this.$confirm('确认删除此条数据?', '提示', {
        type: 'warning'
      }).then(() => {
        this.$emit('onUpdate')
      })
    }
  },
};
</script>

<style lang="scss" scoped>
// ::v-deep.el-table {
//   .selected-row {
//     background-color:#ecf5ff !important;
//   }
// }

@mixin status() {
    width: 60px;
    text-align: center;
    border-width: 1px;
    border-style: solid;
    border-radius: 4px;
    font-weight: 500
}

.status-0 {
  @include status();
  background-color: #fccbd4;
  border-color: #ef1f3a;
  color: #ef1f3a;
}
.status-1 {
  @include status();
  background-color: #d2e4d6;
  border-color: #318240;
  color: #318240;
}
.status-2 {
  @include status();
  background-color: #ef1f3a;
  border-color: #ef1f3a;
  color: #ffffff;
}

@mixin score-level() {
    font-weight: 500
}

.score-level-1 {
  @include score-level();
  color: #318240;
}
.score-level-2 {
  @include score-level();
  color: #89c30f;
}
.score-level-3 {
  @include score-level();
  color: #ec890e;
}
.score-level-4 {
  @include score-level();
  color: #ef1f3a;
}

.quality-type-1 {
  @include score-level();
  color: #07818a;
}
.quality-type-2 {
  @include score-level();
  color: #3c108f;
}
.quality-type-3 {
  @include score-level();
  color: #a26d0a
}
</style>