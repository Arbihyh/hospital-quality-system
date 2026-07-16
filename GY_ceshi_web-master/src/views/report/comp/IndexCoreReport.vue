    <!-- 核心制度报表 -->

<template>
  <div class="medical-quality-content">
    <!-- （一）各科室甲乙丙级病历占比 -->
    <div class="table-card">
      <div class="table-title-warp" style="display: flex; justify-content: space-between; align-items: center">
        <h3 class="table-title">核心制度指标</h3>
        <el-button class="filter-btn query-btn" type="primary" @click="exportCaseList">导出报表</el-button>
      </div>
      <el-table :data="tableData" border stripe size="medium" style="width: 100%" :row-class-name="tableRowClassName" v-loading="tableLoading">
        <el-table-column label="序号" align="center" width="80">
          <template slot-scope="scope">
            {{ scope.row.sort_index || '-' }}
          </template>
        </el-table-column>
        <el-table-column label="名称" align="left" min-width="120">
          <template slot-scope="scope">
            {{ scope.row.name || '-' }}
          </template>
        </el-table-column>
        <el-table-column label="当前选中的指标率" align="center">
          <template slot-scope="scope">{{ formatPercent(scope.row.radio) }}</template>
        </el-table-column>
        <el-table-column label="分子数量" align="center">
          <template slot-scope="scope">
            <span class="value-text-blue value-underline" @click="handleClick('total-1', scope.row)">{{ scope.row.fenzi || 0 }}</span>
          </template>
        </el-table-column>
        <el-table-column label="分母数量" align="center">
          <template slot-scope="scope">
            <span class="value-text-blue value-underline" @click="handleClick('total-1', scope.row)">{{ scope.row.fenmu || 0 }}</span>
          </template>
        </el-table-column>
        <el-table-column label="上期指标率" align="center">
          <template slot-scope="scope">{{ formatPercent(scope.row.radio) }}</template>
        </el-table-column>
        <el-table-column label="同比" align="center">
          <template slot-scope="scope">{{ formatPercent(scope.row.chain_ratio) }}</template>
        </el-table-column>
        <el-table-column label="环比" align="center">
          <template slot-scope="scope">{{ formatPercent(scope.row.yoy_ratio) }}</template>
        </el-table-column>
      </el-table>
      <!-- <div class="pagination-container" style="margin-top: 20px; text-align: right">
        <el-pagination
          @size-change="val => handleSizeChange('case_level_by_dept', val)"
          @current-change="val => handleCurrentChange('case_level_by_dept', val)"
          :current-page="paginationData.case_level_by_dept.currentPage"
          :page-sizes="[10, 20, 50, 100]"
          :page-size="paginationData.case_level_by_dept.pageSize"
          layout="total, sizes, prev, pager, next, jumper"
          :total="paginationData.case_level_by_dept.total"
        ></el-pagination>
      </div> -->
    </div>

    <div class="table-card">
      <h3 class="table-title">核心制度指标达成情况</h3>
      <!-- <h3 class="table-title-no">指标达成率最优前5名</h3> -->
      <div class="table-title-warp" style="display: flex; justify-content: space-between; align-items: center">
        <h3 class="table-title-no">指标达成率最优前5名</h3>
        <el-button class="filter-btn query-btn" type="primary" @click="exportCaseListTop">导出报表</el-button>
      </div>
      <el-table :data="top5DataList" border stripe size="medium" style="width: 100%" :row-class-name="tableRowClassName" v-loading="tableLoading">
        <el-table-column label="序号" align="center" width="80">
          <template slot-scope="scope">
            {{ scope.row.sort_index || '-' }}
          </template>
        </el-table-column>
        <el-table-column label="指标名称" align="left" min-width="200">
          <template slot-scope="scope">
            {{ scope.row.name || '-' }}
          </template>
        </el-table-column>
        <el-table-column label="第一名" align="center">
          <template slot-scope="scope">{{ scope.row.first || '-' }}</template>
        </el-table-column>
        <el-table-column label="第二名" align="center">
          <template slot-scope="scope">{{ scope.row.second || '-' }}</template>
        </el-table-column>
        <el-table-column label="第三名" align="center">
          <template slot-scope="scope">{{ scope.row.third || '-' }}</template>
        </el-table-column>
        <el-table-column label="第四名" align="center">
          <template slot-scope="scope">{{ scope.row.fourth || '-' }}</template>
        </el-table-column>
        <el-table-column label="第五名" align="center">
          <template slot-scope="scope">{{ scope.row.fifth || '-' }}</template>
        </el-table-column>
      </el-table>

      <!-- <h3 class="table-title-no" style="margin-top: 20px">指标达成率最差前5名</h3> -->
      <div class="table-title-warp" style="display: flex; justify-content: space-between; align-items: center">
        <h3 class="table-title-no" style="margin-top: 20px">指标达成率最差前5名</h3>
        <el-button class="filter-btn query-btn" type="primary" @click="exportCaseListEnd">导出报表</el-button>
      </div>
      <el-table :data="endDataList" border stripe size="medium" style="width: 100%" :row-class-name="tableRowClassName" v-loading="tableLoading">
        <el-table-column label="序号" align="center" width="80">
          <template slot-scope="scope">
            {{ scope.row.sort_index || '-' }}
          </template>
        </el-table-column>
        <el-table-column label="指标名称" align="left" min-width="200">
          <template slot-scope="scope">
            {{ scope.row.name || '-' }}
          </template>
        </el-table-column>
        <el-table-column label="第一名（末位）" align="center">
          <template slot-scope="scope">{{ scope.row.first || '-' }}</template>
        </el-table-column>
        <el-table-column label="第二名（末位）" align="center">
          <template slot-scope="scope">{{ scope.row.second || '-' }}</template>
        </el-table-column>
        <el-table-column label="第三名（末位）" align="center">
          <template slot-scope="scope">{{ scope.row.third || '-' }}</template>
        </el-table-column>
        <el-table-column label="第四名（末位）" align="center">
          <template slot-scope="scope">{{ scope.row.fourth || '-' }}</template>
        </el-table-column>
        <el-table-column label="第五名（末位）" align="center">
          <template slot-scope="scope">{{ scope.row.fifth || '-' }}</template>
        </el-table-column>
      </el-table>
    </div>
  </div>
</template>

<script>
import { quality_index_core_reportExport,quality_index_core_top_departmentsExport,quality_index_core_bottom_departmentsExport } from '@/api/excel';
export default {
  name: 'IndexCoreReport',
  data() {
    return {
      queryParams: {},
      tableData: [],
      top5DataList: [],
      endDataList: [],
      tableLoading: false,
      medicalQualityData: {
        case_level_by_dept: [],
        single_no_defects: [],
        appeal_stats: {
          top5_rejected: [],
          by_department: [],
        },
        indicator_achievement: {
          best5: [],
          worst5: [],
        },
      },
      paginationData: {
        case_level_by_dept: {
          // （一）各科室甲乙丙级病历占比
          currentPage: 1,
          pageSize: 10,
          total: 0,
          list: [],
        },
        single_no_defects: {
          // （二）单否项缺陷情况
          currentPage: 1,
          pageSize: 10,
          total: 0,
          list: [],
        },
        by_department: {
          // 申诉情况
          currentPage: 1,
          pageSize: 10,
          total: 0,
          list: [],
        },
      },
    };
  },
  mounted() {},
  destroyed() {},
  methods: {
    formatPercent(value) {
      // if (value === undefined || value === null) return '0.00';
      // return Number(value).toFixed(2);
      return value;
    },

    tableRowClassName({ row, rowIndex }) {
      return 'table-row-hover';
    },

    /**
     * 初始化数据
     * @param {Object} queryParams - 查询参数
     * @param {Object} data
     */
    initData(queryParams = {}) {
      this.queryParams = { ...queryParams };
      // this.initPaginationData();
      this.loadingData();
      this.top5Data();
      this.end5Data();
    },

    async loadingData() {
      try {
        const params = {
          period: this.queryParams.time,
          type: this.queryParams.type,
          AAC11N: this.queryParams.dep_id.join(','),
          is_export: 0,
        };
        const res = await this.$axios2.post('/quality_index_core_report', params);
        if (res.data) {
          this.tableData = res.data.list;
        } else {
          this.tableData = [];
        }
      } catch (error) {
        console.error('加载报告数据失败：', error);
        this.tableData = [];
      }
    },

    //     前五新增接口：bazb/quality_index_core_top_departments
    // 后五新增接口：bazb/quality_index_core_bottom_departments

    async top5Data() {
      try {
        const params = {
          period: this.queryParams.time,
          type: this.queryParams.type,
          // AAC11N: this.queryParams.dep_id,
          is_export: 0,
        };
        const res = await this.$axios2.post('/quality_index_core_top_departments', params);
        if (res.data) {
          this.top5DataList = res.data.list;
        } else {
          this.top5DataList = [];
        }
      } catch (error) {
        console.error('加载报告数据失败：', error);
        this.top5DataList = [];
      }
    },

    async end5Data() {
      try {
        const params = {
          period: this.queryParams.time,
          type: this.queryParams.type,
          // AAC11N: this.queryParams.dep_id,
          is_export: 0,
        };
        const res = await this.$axios2.post('/quality_index_core_bottom_departments', params);
        if (res.data) {
          this.endDataList = res.data.list;
        } else {
          this.endDataList = [];
        }
      } catch (error) {
        console.error('加载报告数据失败：', error);
        this.endDataList = [];
      }
    },

    exportCaseList() {
      const params = {
        period: this.queryParams.time,
        type: this.queryParams.type,
        AAC11N: this.queryParams.dep_id,
        is_export: 1,
      };
      quality_index_core_reportExport(params).then(res => {
        const content = res.data;
        const blob = new Blob([content]);
        const fileName = `核心制度指标.csv`;
        if ('download' in document.createElement('a')) {
          const elink = document.createElement('a');
          elink.download = fileName;
          elink.style.display = 'none';
          elink.href = URL.createObjectURL(blob);
          document.body.appendChild(elink);
          elink.click();
          URL.revokeObjectURL(elink.href);
          document.body.removeChild(elink);
        } else {
          navigator.msSaveBlob(blob, fileName);
        }
      });
    },
    exportCaseListTop(){
      const params = {
        period: this.queryParams.time,
        type: this.queryParams.type,
        // AAC11N: this.queryParams.dep_id,
        is_export: 1,
      };
      quality_index_core_top_departmentsExport(params).then(res => {
        const content = res.data;
        const blob = new Blob([content]);
        const fileName = `指标达成率最优前5名.csv`;
        if ('download' in document.createElement('a')) {
          const elink = document.createElement('a');
          elink.download = fileName;
          elink.style.display = 'none';
          elink.href = URL.createObjectURL(blob);
          document.body.appendChild(elink);
          elink.click();
          URL.revokeObjectURL(elink.href);
          document.body.removeChild(elink);
        } else {
          navigator.msSaveBlob(blob, fileName);
        }
      });
    },
    exportCaseListEnd(){
      const params = {
        period: this.queryParams.time,
        type: this.queryParams.type,
        // AAC11N: this.queryParams.dep_id,
        is_export: 1,
      };
      quality_index_core_bottom_departmentsExport(params).then(res => {
        const content = res.data;
        const blob = new Blob([content]);
        const fileName = `指标达成率最差前5名.csv`;
        if ('download' in document.createElement('a')) {
          const elink = document.createElement('a');
          elink.download = fileName;
          elink.style.display = 'none';
          elink.href = URL.createObjectURL(blob);
          document.body.appendChild(elink);
          elink.click();
          URL.revokeObjectURL(elink.href);
          document.body.removeChild(elink);
        } else {
          navigator.msSaveBlob(blob, fileName);
        }
      });
    },

    handleClick(tabKey, row) {
      console.log(tabKey, row);
      let record_levels = [];

      const { startTime, endTime } = this.calculateDateRange();
      const { dep_id } = this.queryParams;
      switch (tabKey) {
        case 'fenzi':
        case 'fenmu':
          this.$router.push({
            name: 'reportMedicalRecords',
            query: { startTime, endTime, dep_id, record_levels, type: 'model-page' },
          });
          break;

        default:
          break;
      }
    },

    calculateDateRange() {
      let startTime = '';
      let endTime = '';
      const { type = 'year', year = new Date().getFullYear().toString(), quarter = 1, month = '01' } = this.queryParams;
      switch (type) {
        case 'year': {
          startTime = `${year}0101`;
          endTime = `${year}1231`;
          break;
        }
        case 'quarter': {
          const quarterStartMonthMap = {
            1: '01', // 一季度：1-3月
            2: '04', // 二季度：4-6月
            3: '07', // 三季度：7-9月
            4: '10', // 四季度：10-12月
          };
          const quarterEndMonthMap = {
            1: '03',
            2: '06',
            3: '09',
            4: '12',
          };
          const quarterEndDayMap = {
            1: '31', // 3月31日
            2: '30', // 6月30日
            3: '30', // 9月30日
            4: '31', // 12月31日
          };
          const startMonth = quarterStartMonthMap[quarter] || '01';
          const endMonth = quarterEndMonthMap[quarter] || '12';
          const endDay = quarterEndDayMap[quarter] || '31';

          startTime = `${year}${startMonth}01`;
          endTime = `${year}${endMonth}${endDay}`;
          break;
        }
        case 'month': {
          startTime = `${year}${month}01`;
          const lastDay = new Date(Number(year), Number(month), 0).getDate();
          const lastDayStr = lastDay.toString().padStart(2, '0');
          endTime = `${year}${month}${lastDayStr}`;
          break;
        }
      }
      return { startTime, endTime };
    },

    /**
     * 初始化分页数据
     */
    initPaginationData() {
      // （一）各科室甲乙丙级病历占比分页
      this.paginationData.case_level_by_dept.total = this.medicalQualityData.case_level_by_dept.length;
      this.paginationData.case_level_by_dept.list = this.getPagedData(
        this.medicalQualityData.case_level_by_dept,
        this.paginationData.case_level_by_dept.currentPage,
        this.paginationData.case_level_by_dept.pageSize,
      );

      // （二）单否项缺陷情况分页
      this.paginationData.single_no_defects.total = this.medicalQualityData.single_no_defects.length;
      this.paginationData.single_no_defects.list = this.getPagedData(
        this.medicalQualityData.single_no_defects,
        this.paginationData.single_no_defects.currentPage,
        this.paginationData.single_no_defects.pageSize,
      );

      // 申诉情况分页
      this.paginationData.by_department.total = this.medicalQualityData.appeal_stats.by_department.length;
      this.paginationData.by_department.list = this.getPagedData(
        this.medicalQualityData.appeal_stats.by_department,
        this.paginationData.by_department.currentPage,
        this.paginationData.by_department.pageSize,
      );
    },

    getPagedData(data, currentPage, pageSize) {
      const startIndex = (currentPage - 1) * pageSize;
      const endIndex = startIndex + pageSize;
      return data.slice(startIndex, endIndex);
    },

    handleSizeChange(tableKey, val) {
      this.paginationData[tableKey].pageSize = val;
      this.paginationData[tableKey].currentPage = 1;
      this.initPaginationData();
    },

    handleCurrentChange(tableKey, val) {
      this.paginationData[tableKey].currentPage = val;
      this.initPaginationData();
    },

    /**
     * @param {String} tableKey -
     */
    fetchTableData(tableKey) {
      const pagination = this.paginationData[tableKey];
      this.tableLoading = true;
      // 示例接口请求逻辑
      /*
      api.getQualityData({
        ...this.queryParams,
        pageNum: pagination.currentPage,
        pageSize: pagination.pageSize,
        type: tableKey
      }).then(res => {
        // 更新对应表格数据
        if (tableKey === 'case_level_by_dept') {
          this.medicalQualityData.case_level_by_dept = res.data || [];
        } else if (tableKey === 'single_no_defects') {
          this.medicalQualityData.single_no_defects = res.data || [];
        } else if (tableKey === 'by_department') {
          this.medicalQualityData.appeal_stats.by_department = res.data || [];
        }
        this.initPaginationData();
        this.tableLoading = false;
      }).catch(err => {
        this.tableLoading = false;
      });
      */
    },
  },
};
</script>

<style lang="scss" scoped>
.medical-quality-content {
  width: 100%;
  padding: 0 10px;
  box-sizing: border-box;
}

.table-card {
  background: #fff;
  padding: 20px;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.08);
  border: 1px solid #e8e8e8;
  margin-bottom: 20px;
  overflow-x: auto;

  .filter-btn {
    padding: 8px 20px !important;
    font-size: 14px !important;
    font-weight: 500 !important;
    border-radius: 4px !important;
    transition: all 0.3s ease !important;
    border: none !important;
  }

  ::v-deep .query-btn {
    background-color: #185da6 !important;
    color: #fff !important;
  }

  ::v-deep .query-btn:hover {
    background-color: #134a82 !important;
    box-shadow: 0 2px 4px rgba(24, 93, 166, 0.2) !important;
  }

  .table-title {
    font-size: 16px;
    color: #333;
    margin-bottom: 16px;
    padding-left: 12px;
    border-left: 3px solid #185da6;
    font-weight: 600;
  }
  .table-title-no {
    font-size: 16px;
    color: #333;
    margin-bottom: 16px;
    padding-left: 12px;
    font-weight: 600;
  }
}

.value-text-blue {
  color: #185da6;
  cursor: pointer;
}

.value-text-red {
  color: #ef4444;
  cursor: pointer;
}

.value-underline {
  text-decoration: underline;
  text-underline-offset: 4px;
  text-decoration-color: currentColor;
  text-decoration-thickness: 2px;
}

// .highlight {
//  // color: #ef4444 !important;
//   font-weight: 600;
// }

.pagination-container {
  ::v-deep .el-pagination {
    user-select: none;

    .el-pagination__total {
      margin-right: 10px;
      color: #666;
    }

    .el-pagination__sizes {
      margin-right: 10px;
    }

    button:disabled {
      background-color: #f5f7fa !important;
      color: #ccc !important;
      border-color: #e8e8e8 !important;
    }
  }
}

::v-deep .table-row-hover:hover {
  background-color: #f8f9fa !important;
}

::v-deep .el-table__header-wrapper th {
  font-weight: 600 !important;
  color: #333 !important;
  background-color: #f5f7fa !important;
  border: none !important;
  height: 48px;
}

::v-deep .el-table__body-wrapper td {
  border-bottom: 1px solid #e8e8e8 !important;
  color: #333 !important;
  height: 48px;
}

::v-deep .el-loading-mask {
  background-color: rgba(255, 255, 255, 0.8) !important;
}

@media (max-width: 768px) {
  .table-card {
    padding: 10px;
  }

  .table-title,
  .table-title-no {
    font-size: 14px;
  }

  .pagination-container {
    ::v-deep .el-pagination {
      font-size: 12px;

      .el-pagination__total {
        display: block;
        margin-bottom: 5px;
        text-align: left;
      }
    }
  }
}

@media (max-width: 1200px) {
  ::v-deep .el-table {
    font-size: 13px;
  }
}
</style>