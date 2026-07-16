<!-- 知识库 -->
<template>
  <div class="page-container">
    <div class="search-box">
      <el-form :model="searchForm" label-width="80px" ref="searchFormRef">
        <el-row>
          <el-col :span="8">
            <el-form-item label="名称" prop="name">
              <el-input style="width: 100%" placeholder="搜索疾病、药品、检查检验等内容" v-model="searchForm.name" clearable></el-input>
            </el-form-item>
          </el-col>
          <el-col :span="8">
            <el-form-item label="类型" prop="type">
              <el-select v-model="searchForm.type" clearable placeholder="请选择知识库类型">
                <el-option v-for="item in typeOptions" :key="item.value" :label="item.label" :value="item.value"></el-option>
              </el-select>
            </el-form-item>
          </el-col>
          <el-col :span="5" offset="2">
            <el-form-item>
              <div style="width: 100%; display: flex; justify-content: flex-end; gap: 8px">
                <el-button type="primary" @click="funQuery" icon="el-icon-search">查询</el-button>
                <el-button @click="reset" icon="el-icon-refresh-left">重置</el-button>
              </div>
            </el-form-item>
          </el-col>
        </el-row>
      </el-form>
    </div>
    <div class="table-box">
      <div class="table-header">
        <CardTitle title="查询结果" />
        <!-- <el-button type="primary" plain icon="el-icon-plus" size="small" @click="handleAdd">新增知识库</el-button> -->
      </div>
      <!-- 表格区域 -->
      <el-table v-loading="listLoading" :data="tableData" stripe border fit highlight-current-row empty-text="暂无相关知识库数据" size="small" :row-class-name="tableRowClassName">
        <el-table-column type="index" label="序号" align="center" header-align="center" width="60" />
        <el-table-column label="类型" width="260" align="center" header-align="center" show-overflow-tooltip>
          <template slot-scope="scope">
            {{ getTypeNameByValue(scope.row.type) }}
          </template>
        </el-table-column>
        <el-table-column prop="name" label="名称" align="center" header-align="center" show-overflow-tooltip>
          <template slot-scope="scope">
            <span class="span-link" @click="handleNameClick(scope.row)">{{ scope.row.name }}</span>
          </template>
        </el-table-column>
        <el-table-column prop="updated_at" label="更新时间" align="center" header-align="center" width="200">
          <template slot-scope="scope">
            {{ scope.row.updated_at || '-' }}
          </template>
        </el-table-column>
        <el-table-column label="操作" width="180" align="center" header-align="center" fixed="right">
          <template slot-scope="scope">
            <el-button type="primary" icon="el-icon-edit" size="mini" circle @click="handleEdit(scope.row)" title="编辑" class="oper-btn edit-btn" />
            <el-button type="danger" icon="el-icon-delete" circle size="mini" @click="handleDelete(scope.row)" title="删除" class="oper-btn del-btn" />
          </template>
        </el-table-column>
      </el-table>
      <el-pagination
        @size-change="handleSizeChange"
        style="float: right; margin-top: 20px"
        @current-change="handleCurrentChange"
        :current-page="pageParams.page"
        :page-sizes="[10, 20, 30, 40]"
        :page-size="pageParams.page_size"
        layout="prev, pager, next, sizes, total, jumper"
        :total="pageParams.total"
        background
      ></el-pagination>
    </div>
    <el-dialog title="知识库" :visible.sync="dialogVisible" width="500px" destroy-on-close close-on-click-modal>
      <el-form :model="dialogForm" :rules="dialogRules" ref="dialogFormRef" label-width="80px">
        <el-form-item label="字典名称" prop="name">
          <el-input v-model="dialogForm.name" placeholder="请输入名称" clearable ref="dialogInput" style="width: 100%"></el-input>
        </el-form-item>
        <el-form-item label="映射名称">
          <el-select v-model="dialogForm.type" clearable placeholder="请选择知识库类型" style="width: 100%">
            <el-option v-for="item in typeOptions" :key="item.value" :label="item.label" :value="item.value"></el-option>
          </el-select>
        </el-form-item>
      </el-form>
      <template #footer>
        <span class="dialog-footer">
          <el-button size="small" @click="closeDialog">取消</el-button>
          <el-button size="small" type="primary" @click="saveDict">确定</el-button>
        </span>
      </template>
    </el-dialog>
    <KnowKuDetail ref="detailRef" />
  </div>
</template>

<script>
import DateRangePicker from '@/components/DateRangePicker';
import CardTitle from '@/components/CardTitle';
import KnowKuDetail from './detail';

export default {
  name: 'KnowKu',
  components: { DateRangePicker, CardTitle, KnowKuDetail },
  data() {
    return {
      searchForm: {
        name: '',
        type: '',
      },
      dialogVisible: false,
      listLoading: false,
      tableData: [],
      dataForm: {
        id: '',
        name: '',
        type: '',
        updated_at: '',
      },
      dialogForm: {
        id: '',
        name: '',
        type: '',
      },
      pageParams: {
        page: 1,
        page_size: 10,
        total: 0,
      },
      typeOptions: [
        { label: '医疗疾病', value: '医疗疾病' },
        { label: '手术操作', value: '手术操作' },
        { label: '药品', value: '药品' },
        { label: '检验检查', value: '检验检查' },
      ],
    };
  },
  created() {
    this.funQuery();
  },
  methods: {
    async funQuery() {
      try {
        this.listLoading = true;
        const params = { ...this.searchForm, page: this.pageParams.page, page_size: this.pageParams.page_size };
        const res = await this.$axios.post('/cdss_knowledge/get_knowledge_list', { params });
        if (res?.code === 200) {
          this.tableData = res.data?.list || [];
          this.pageParams.total = res.data?.count || 0;
          if (this.pageParams.total === 0) this.tableData = [];
        } else {
          // this.$message.warning(res?.msg || '查询知识库列表失败');
          this.tableData = [];
          this.pageParams.total = 0;
        }
      } catch (error) {
        // this.$message.error('网络异常，查询知识库列表失败！');
        console.error('【知识库查询报错】：', error);
        this.tableData = [];
        this.pageParams.total = 0;
      } finally {
        this.listLoading = false;
      }
    },

    reset() {
      if (this.$refs.searchFormRef) {
        this.$refs.searchFormRef.resetFields();
      }
      this.searchForm = {
        name: '',
        type: '',
      };
      this.pageParams.page = 1;
      this.pageParams.page_size = 10;
      this.funQuery();
    },

    handleSizeChange(val) {
      this.pageParams.page_size = val;
      this.pageParams.page = 1;
      this.funQuery();
    },

    handleCurrentChange(val) {
      this.pageParams.page = val;
      this.funQuery();
    },

    getTypeNameByValue(value) {
      const item = this.typeOptions.find(item => item.value === value);
      return item?.label || '未知类型';
    },

    handleEdit(row) {
      if (!row || !row.id || row.id === '暂无数据') {
        this.$message.warning('当前数据异常，不支持编辑操作');
        return;
      }
      this.handleAdd(row);
    },

    handleDelete(row) {
      if (!row || !row.id || row.id === '暂无数据') {
        this.$message.warning('当前数据异常，不支持删除操作');
        return;
      }
      this.$confirm('此操作将永久删除该条知识库数据, 是否继续?', '温馨提示', {
        confirmButtonText: '确定',
        cancelButtonText: '取消',
        type: 'warning',
        confirmButtonClass: 'el-button--danger',
      })
        .then(async () => {
          try {
            const res = await this.$axios.post(`/cdss_knowledge/delete_knowledge`, { id: row.id, type: row.type });
            if (res?.code === 200) {
              this.$message.success('删除成功！');
              this.funQuery();
            } else {
              // this.$message.error(res?.msg || '删除失败，请稍后重试');
            }
          } catch (error) {
            // this.$message.error('网络异常，删除操作失败！');
            console.error('【知识库删除报错】：', error);
          }
        })
        .catch(() => {
          this.$message.info('已取消删除操作');
        });
    },

    handleNameClick(row) {
      if (!row || !row.id) return;
      console.log('点击知识库名称：', row);
      this.$refs.detailRef.init(row);
    },

    tableRowClassName({ row }) {
      if (row.name === '暂无数据') return 'empty-data-row';
    },

    closeDialog() {
      this.dialogVisible = false;
      this.dialogForm = { id: '', name: '', type: '' };
    },

    async handleAdd(row = {}) {
      this.dialogVisible = true;
      this.dialogForm = { id: '', name: '', type: '' };
      if (row.id) {
        this.dialogForm.id = row.id;
        this.dialogForm.name = row.name;
        this.dialogForm.type = row.type;
      }
    },

    async saveDict() {
      if (!this.dialogForm.name) {
        this.$message.warning('请输入名称');
        return;
      }
      try {
        let res;
        if (this.dialogForm.id) {
          res = await this.$axios.post(`/cdss_knowledge/update_knowledge`, {
            name: this.dialogForm.name,
            id: row.id,
            type: row.type,
          });
        } else {
          res = await this.$axios.post('/cdss_knowledge/add_knowledge', {
            name: this.dialogForm.name,
            type: row.type,
          });
        }
        if (res.code === 200) {
          this.$message.success(this.dialogForm.id ? '编辑成功' : '新增成功');
          this.dialogVisible = false;
          this.funQuery();
        } else {
          // this.$message.error(res.msg || (this.dialogForm.id ? '编辑失败' : '新增失败'));
        }
      } catch (error) {
        // this.$message.error('网络异常，操作失败！');
        console.error('操作报错：', error);
      }
    },
  },
};
</script>

<style lang="scss" scoped>
@import '~@/styles/common.scss';

.page-container {
  padding: 0 18px;
  background: #f4f4f4;
  min-height: calc(100vh - 120px);

  .search-box {
    padding: 20px;
    background: #fff;
    border-radius: 6px;
    overflow-x: hidden;
    margin-bottom: 12px;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.02);
  }

  .table-box {
    padding: 20px;
    background: #fff;
    border-radius: 6px;
    overflow-x: auto;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.02);

    .table-header {
      margin-bottom: 15px;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
  }
}
</style>