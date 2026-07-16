<!-- 版本记录 -->
 <template>
  <div class="page-container">
    <div class="search-box">
      <el-form :model="searchForm" label-width="80px" ref="searchFormRef">
        <el-row>
          <el-col :span="6">
            <el-form-item label="字典名称" prop="name">
              <el-input style="width: 100%" placeholder="请输入字典名称" v-model="searchForm.name" clearable></el-input>
            </el-form-item>
          </el-col>
          <el-col :span="6">
            <el-form-item label="映射名称" prop="keyword">
              <el-input style="width: 100%" placeholder="请输入映射名称" v-model="searchForm.keyword" clearable></el-input>
            </el-form-item>
          </el-col>
          <el-col :span="5">
            <!-- <el-form-item label="更新人" prop="updaterId">
              <el-input style="width: 100%" placeholder="请输入映射名称" v-model="searchForm.updaterId" clearable></el-input>
            </el-form-item> -->
            <el-form-item>
              <div style="width: 100%; display: flex; justify-content: flex-end">
                <el-button type="primary" @click="funQuery">查询</el-button>
                <el-button @click="reset">重置</el-button>
              </div>
            </el-form-item>
          </el-col>
        </el-row>
        <!-- <el-row>
          <el-col :span="8">
            <DateRangePicker labelText="更新时间" v-model="searchForm" />
          </el-col>
          <el-col :span="8">
            <DateRangePicker labelText="创建时间" v-model="searchForm" />
          </el-col>
          <el-col :span="5" offset="1">
            <el-form-item>
              <div style="width: 100%; display: flex; justify-content: flex-end">
                <el-button type="primary" @click="funQuery">查询</el-button>
                <el-button @click="reset">重置</el-button>
              </div>
            </el-form-item>
          </el-col>
        </el-row> -->
      </el-form>
    </div>
    <div class="table-box">
      <div class="table-header">
        <CardTitle title="查询结果" />
        <el-button type="primary" plain icon="el-icon-plus" size="small" @click="handleAdd">新增字典</el-button>
      </div>
      <el-table v-loading="listLoading" :data="tableData" stripe border fit highlight-current-row empty-text="暂无数据" size="small">
        <el-table-column type="index" label="序号" align="center" header-align="center" width="60" />
        <el-table-column prop="name" label="字典名称" align="center" header-align="center" show-overflow-tooltip />
        <el-table-column prop="keyword" label="映射名称" align="center" header-align="center" show-overflow-tooltip />
        <el-table-column prop="BZMC" label="用途说明" align="center" header-align="center" show-overflow-tooltip />
        <el-table-column prop="created_at" label="创建时间" align="center" header-align="center" width="180"></el-table-column>
        <el-table-column prop="GXR" label="更新人" align="center" header-align="center" show-overflow-tooltip />
        <el-table-column prop="updated_at" label="更新时间" align="center" header-align="center" width="180"></el-table-column>
        <el-table-column label="操作" width="180" align="center" header-align="center" fixed="right">
          <template slot-scope="scope">
            <el-button type="primary" icon="el-icon-edit" size="mini" circle @click="handleEdit(scope.row)" title="编辑" />
            <el-button type="danger" icon="el-icon-delete" circle size="mini" @click="handleDelete(scope.row)" title="删除" />
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
      ></el-pagination>
    </div>
    <!-- 新增/编辑字典弹窗 -->
    <el-dialog title="字典配置" :visible.sync="dialogVisible" width="500px" destroy-on-close close-on-click-modal>
      <el-form :model="dialogForm" :rules="dialogRules" ref="dialogFormRef" label-width="80px">
        <el-form-item label="字典名称" prop="name">
          <el-input v-model="dialogForm.name" placeholder="请输入字典名称" clearable ref="dialogInput" style="width: 100%"></el-input>
        </el-form-item>
        <el-form-item label="映射名称">
          <div>
            <el-tag :key="tag" v-for="tag in dialogForm.keywordList" closable :disable-transitions="false" @close="handleClose(tag)">
              {{ tag }}
            </el-tag>
            <el-input
              class="input-new-tag"
              v-if="inputVisible"
              v-model="inputValue"
              ref="saveTagInput"
              size="small"
              @keyup.enter.native="handleInputConfirm"
              @blur="handleInputConfirm"
            ></el-input>
            <el-button v-else class="button-new-tag" size="small" @click="showInput">+ 添加</el-button>
          </div>
        </el-form-item>
        <el-form-item label="用途说明" prop="BZMC">
          <el-input v-model="dialogForm.BZMC" type="textarea" :rows="2" placeholder="请输入用途" clearable style="width: 100%"></el-input>
        </el-form-item>
      </el-form>
      <template #footer>
        <span class="dialog-footer">
          <el-button size="small" @click="closeDialog">取消</el-button>
          <el-button size="small" type="primary" @click="saveDict">确定</el-button>
        </span>
      </template>
    </el-dialog>
  </div>
</template>

<script>
import DateRangePicker from '@/components/DateRangePicker';
import CardTitle from '@/components/CardTitle';

export default {
  name: 'DictManager',
  components: { DateRangePicker, CardTitle },
  data() {
    return {
      // 搜索表单数据
      searchForm: {
        name: '',
        keyword: '',
      },
      listLoading: false,
      tableData: [],
      dialogVisible: false,
      dialogForm: {
        id: '',
        name: '',
        keywordList: [],
        BZMC: '',
      },
      dialogRules: {
        name: [{ required: true, message: '请输入字典名称', trigger: 'blur' }],
      },
      pageParams: {
        page: 1,
        page_size: 10,
        total: 10,
      },
      inputVisible: false,
      inputValue: '',
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
        const res = await this.$axios.post('/rule_word_map/get_version_record', { params });
        if (res.code === 200) {
          this.tableData = res.data.list || [];
          this.pageParams.total = res.data.count || 0;
        } else {
          // this.$message.warning(res.msg || '查询字典列表失败');
          this.tableData = [];
          this.pageParams.total = 0;
        }
      } catch (error) {
        // this.$message.error('网络异常，查询字典列表失败！');
        console.error('字典查询报错：', error);
        this.tableData = [];
        this.pageParams.total = 0;
      } finally {
        this.listLoading = false;
      }
    },

    reset() {
      this.$refs.searchFormRef.resetFields();
      this.searchForm = {
        name: '',
        keyword: '',
      };
      this.funQuery();
    },

    handlePagination(val) {
      this.pageParams.page = val.page;
      this.pageParams.page_size = val.limit;
      this.funQuery();
    },

    async handleAdd(row = {}) {
      this.dialogVisible = true;
      this.dialogForm = { id: '', name: '', keywordList: [] , BZMC: '' };
      if (row.id) {
        this.dialogForm.id = row.id;
        this.dialogForm.name = row.name;
        this.dialogForm.keywordList = row.keywords || [];
        this.dialogForm.BZMC = row.BZMC;
      }
    },

    async saveDict() {
      if (!this.dialogForm.name) {
        this.$message.warning('请输入字典名称');
        return;
      }
      //   if (this.dialogForm.keywordList.length === 0) {
      //     this.$message.warning('请至少添加一个映射名称');
      //     return;
      //   }
      try {
        let res;
        if (this.dialogForm.id) {
          res = await this.$axios.post(`/rule_word_map/edit_word_map`, {
            id: this.dialogForm.id,
            name: this.dialogForm.name,
            keywords: this.dialogForm.keywordList,
            BZMC: this.dialogForm.BZMC,
          });
        } else {
          res = await this.$axios.post('/rule_word_map/add_word_map', {
            name: this.dialogForm.name,
            keywords: this.dialogForm.keywordList,
            BZMC: this.dialogForm.BZMC,
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
        console.error('字典操作报错：', error);
      }
    },
    closeDialog() {
      this.dialogVisible = false;
      this.dialogForm = { id: '', name: '', keywordList: [], BZMC: '' };
    },

    handleSizeChange(val) {
      this.pageParams.page_size = val;
      this.funQuery();
    },
    handleCurrentChange(val) {
      this.pageParams.page = val;
      this.funQuery();
    },

    handleEdit(row) {
      if (!row || !row.id) {
        this.$message.warning('数据异常，无法编辑');
        return;
      }
      this.handleAdd(row);
    },

    handleDelete(row) {
      if (!row || !row.id) {
        this.$message.warning('数据异常，无法删除');
        return;
      }
      this.$confirm('是否确定删除', {
        confirmButtonText: '确定',
        cancelButtonText: '取消',
        type: 'warning',
      })
        .then(async () => {
          try {
            const res = await this.$axios.post(`/rule_word_map/del_word_map`, { id: row.id });
            if (res.code === 200) {
              this.$message.success('删除成功！');
              this.funQuery();
            } else {
              // this.$message.error(res.msg || '删除失败');
            }
          } catch (error) {
            // this.$message.error('网络异常，删除失败！');
            console.error('字典删除报错：', error);
          }
        })
        .catch(() => {
          this.$message.info('已取消删除');
        });
    },

    handleClose(tag) {
      this.dialogForm.keywordList.splice(this.dialogForm.keywordList.indexOf(tag), 1);
    },

    showInput() {
      this.inputVisible = true;
      this.$nextTick(_ => {
        this.$refs.saveTagInput.$refs.input.focus();
      });
    },

    handleInputConfirm() {
      let inputValue = this.inputValue;
      if (inputValue) {
        this.dialogForm.keywordList.push(inputValue);
      }
      this.inputVisible = false;
      this.inputValue = '';
    },
  },
};
</script>
<style lang="scss" scoped>
@import '~@/styles/common.scss';

.page-container {
  padding: 0 18px;
  background: #f4f4f4;

  .table-box,
  .search-box {
    padding: 20px;
    background: #fff;
    border-radius: 5px;
    overflow-x: hidden;
  }

  .table-box {
    margin-top: 10px;

    .table-header {
      margin-bottom: 13px;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
  }
}

.el-tag {
  margin-left: 10px;
}
.button-new-tag {
  margin-left: 10px;
  height: 32px;
  line-height: 30px;
  padding-top: 0;
  padding-bottom: 0;
}
.input-new-tag {
  width: 90px;
  margin-left: 10px;
  vertical-align: bottom;
}
</style>