<!-- 上报表数据 -->
<template>
  <div class="page-container">
    <div class="table-box">
      <div class="table-header">
        <div class="header-step2" v-if="currentStep === '2'">
          <div class="header-left">
            <SearchInput :value="searchForm.filterText" placeholderText="请输入关键词快速定位指标" @change="handleSearchChange" />
            <el-select style="width: 400px; margin-left: 20px" v-model="searchForm.status" multiple clearable placeholder="请选择状态" @change="funQuery">
              <el-option v-for="item in statusOptions" :key="item.value" :label="item.label" :value="item.value"></el-option>
            </el-select>
          </div>
          <div class="header-right">
            共
            <span class="span-num">{{ indexNum }}</span>
            个指标，
            <span class="span-num">{{ tableData.length }}</span>
            条 &nbsp;&nbsp;&nbsp;&nbsp;
            <el-button type="primary" plain size="small" class="update-btn" @click="handleUpdate">更新数据</el-button>
          </div>
        </div>
        <div class="header-step3" v-if="currentStep === '3'">
          <div class="header-left">
            <div style="width: 500px; display: flex; justify-content: space-between; align-items: center">
              <SearchInput :value="searchForm.filterText" placeholderText="请输入关键词快速定位指标" @change="handleSearchChange" />
              <span style="width: 200px; margin-left: 20px">
                共
                <span class="span-num">{{ indexNum }}</span>
                个指标，
                <span class="span-num">{{ tableData.length }}</span>
                条 &nbsp;&nbsp;&nbsp;&nbsp;
              </span>
            </div>
          </div>
          <div class="header-right">
            <el-button type="primary" plain size="small" @click="handleOk">确认上报</el-button>
          </div>
        </div>
      </div>
      <el-table v-loading="listLoading" :data="tableData" stripe border fit highlight-current-row empty-text="暂无数据" size="small">
        <el-table-column type="index" label="序号" align="center" header-align="center" width="60" />
        <el-table-column prop="name" label="指标名称" align="center" header-align="center" width="280" show-overflow-tooltip />
        <el-table-column prop="dbv" label="达标率" align="center" header-align="center" width="180" show-overflow-tooltip>
          <template slot-scope="scope">{{ scope.row.dbv }}%</template>
        </el-table-column>
        <el-table-column prop="reportName" label="上报名称" align="center" header-align="center" show-overflow-tooltip />
        <el-table-column prop="indexTime" label="指标日期" align="center" header-align="center" width="180" show-overflow-tooltip />
        <el-table-column prop="reportNum" label="上报数量" align="center" header-align="center" width="180" show-overflow-tooltip>
          <template slot-scope="scope">
            <span class="span-link" @click="toPage(scope.row)">{{ scope.row.reportNum }}</span>
            <span><el-image class="header-icon" :src="ReportEdit" @click="editClick(scope.row)" fit="contain" /></span>
          </template>
        </el-table-column>
        <el-table-column prop="reportField" label="上报字段" align="center" header-align="center" width="180"></el-table-column>
      </el-table>
    </div>

    <el-dialog :visible.sync="dialogVisible" width="380px" title="" :modal="true" :show-close="false" append-to-body border="0">
      <div class="dialog-content">
        <div class="dialog-header">
          <div class="dialog-title">核心制度指标</div>
        </div>
        <div class="dialog-text-box">
          <span class="dialog-text">请确认所填写的核心制度指标数据真实、完整、准确。一经上报，将同步至上级监管平台，不可修改。如发现数据有误，请及时取消并修正后再行上报。</span>
        </div>
        <div class="btn-container">
          <DialogFooterBtn @cancel="handleClose" confirmText="上报" @confirm="handleReport" />
        </div>
      </div>
    </el-dialog>
  </div>
</template>

<script>
import SearchInput from '@/components/SearchInput';
import DialogFooterBtn from '@/components/DialogFooterBtn';
import ReportEdit from '@/assets/images/report-edit.png';
import ReportDialogText from '@/assets/images/text.png';
export default {
  name: 'IndexReport',
  components: { SearchInput, DialogFooterBtn },
  data() {
    return {
      ReportEdit,
      ReportDialogText,
      listLoading: false,
      currentStep: '3',
      tableData: [],
      indexNum: 32,
      statusOptions: [
        { label: '全部', value: '0' },
        { label: '人工填报', value: '1' },
        { label: '作废', value: '2' },
      ],
      searchForm: {
        filterText: '',
        status: [],
      },
      dialogVisible: false,
    };
  },
  created() {
    this.funQuery();
  },
  methods: {
    async funQuery() {
      this.listLoading = true;
      try {
        const params = {
          keyword: this.searchForm.filterText.trim(),
          statusList: this.searchForm.status.length > 0 ? this.searchForm.status : ['0'],
        };
        this.tableData = [
          { name: '住院患者压疮发生率', dbv: 98.5, reportName: '院内压疮统计上报', indexTime: '2026-01-03', reportNum: 26, reportField: '压疮例数、住院总人数' },
          { name: '医院感染发生率', dbv: 100, reportName: '院感数据汇总上报', indexTime: '2026-01-03', reportNum: 18, reportField: '院感例数、科室分布' },
          { name: '手术部位感染率', dbv: 99.2, reportName: '手术安全指标上报', indexTime: '2026-01-03', reportNum: 8, reportField: '手术例数、感染例数' },
        ];
      } catch (err) {
        // this.$message.error('数据加载失败，请稍后重试！');
        this.tableData = [];
      } finally {
        this.listLoading = false;
      }
    },
    handleSearchChange(val) {
      this.searchForm.filterText = val;
      this.funQuery();
    },
    handleOk() {
      this.dialogVisible = true;
    },
    handleClose() {
      this.dialogVisible = false;
    },
    handleReport() {
      this.handleClose();
      this.$message.success('上报成功！');
    },
    async handleUpdate() {
      this.listLoading = true;
      try {
        await new Promise(resolve => setTimeout(resolve, 800));
        this.$message.success('数据更新成功！');
        this.funQuery();
      } catch (err) {
        // this.$message.error('数据更新失败，请稍后重试！');
      } finally {
        this.listLoading = false;
      }
    },
    toPage(row) {
      if (!row) return this.$message.warning('暂无跳转数据');
    },
    editClick(row) {
      this.$message.info(`【${row.name}】点击进入指标编辑页面`);
    },
  },
};
</script>

<style lang="scss" scoped>
@import '~@/styles/common.scss';

.page-container {
  margin: 0;
  padding: 20px;
  background-color: #ffffff;

  .table-box {
    margin-top: 10px;
    .table-header {
      margin-bottom: 13px;
      .header-step2,
      .header-step3 {
        display: flex;
        justify-content: space-between;
        align-items: center;
        .header-left {
          display: flex;
          width: 50%;
          justify-content: space-between;
          align-items: center;
        }
        .header-right {
          .update-btn {
            border-radius: 4px;
            background-color: rgba(86, 119, 34, 1);
            color: #ffffff;
            font-size: 12px;
            text-align: center;
            font-family: PingFangSC-regular;
          }
        }
      }
    }
  }

  .header-icon {
    width: 16px;
    height: 16px;
    margin-left: 8px;
    cursor: pointer;
  }
  .span-num {
    color: #005fa6;
    font-size: 14px;
    font-weight: bold;
  }
}

::v-deep {
  .el-dialog {
    width: 380px !important;
    margin: 0 auto;
    padding: 0;
    background: none;
  }
  .el-dialog__header,
  .el-dialog__body {
    padding: 0 !important;
  }
  .el-form-item__error {
    line-height: 17px !important;
    color: rgba(189, 49, 36, 1) !important;
    font-size: 12px !important;
    text-align: left !important;
    font-family: PingFangSC-bold !important;
    padding-top: 4px !important;
    margin-left: 0 !important;
  }
}

.dialog-content {
  width: 380px;
  background: #ffffff;
  border: 1px solid rgba(27, 100, 176, 1);
  border-radius: 8px;
  overflow: hidden;
  box-sizing: border-box;

  .dialog-header {
    height: 40px;
    line-height: 40px;
    border-radius: 8px 8px 0 0;
    background-color: rgba(27, 100, 176, 1);
    padding: 0 20px;
    display: flex;
    align-items: center;
    margin-bottom: 20px;

    .dialog-title {
      color: #ffffff;
      font-size: 16px;
      font-weight: 500;
    }
  }
  .dialog-text-box {
    padding: 0 30px;
    .dialog-text {
      display: block;
      line-height: 24px;
      color: #333333;
      font-size: 14px;
      text-align: justify;
      text-indent: 2em;
      font-family: PingFangSC-regular;
    }
  }
  .btn-container {
    width: 100%;
    padding: 20px 30px;
    display: flex;
    justify-content: center;
    align-items: center;
    box-sizing: border-box;
  }
}
</style>