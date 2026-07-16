<!-- 运行病历报表 -->
<template>
  <div class="medical-quality-content">
    <div class="table-card">
      <div
        class="table-title-warp"
        style="display: flex; justify-content: space-between; align-items: center"
      >
        <h3 class="table-title">运行病历报表</h3>
        <el-button class="filter-btn query-btn" type="primary" @click="exportCaseList">导出报表</el-button>
      </div>
      <el-table
        :data="tableData.list"
        border
        stripe
        size="medium"
        style="width: 100%"
        v-loading="tableLoading"
        @sort-change="handleSortChange"
        :row-class-name="tableRowClassName"
      >
        <el-table-column type="index" label="序号" width="80" align="center"></el-table-column>
        <el-table-column label="规则名称" prop="rule_name" align="left" min-width="220" sortable>
          <template slot-scope="scope">{{ scope.row.rule_name || '-' }}</template>
        </el-table-column>
        <el-table-column label="所属科室" prop="department" align="left" min-width="120" sortable>
          <template slot-scope="scope">{{ scope.row.department || '-' }}</template>
        </el-table-column>
        <el-table-column label="锁定次数" align="left" min-width="120" prop="lock_count" sortable>
          <template slot-scope="scope">{{ scope.row.lock_count || '-' }}</template>
        </el-table-column>
        <el-table-column label="整改状态" align="left" min-width="120">
          <template slot-scope="scope">{{ scope.row.correction_status || '-' }}</template>
        </el-table-column>
        <el-table-column label="住院医师" align="left" min-width="120">
          <template slot-scope="scope">{{ scope.row.resident_doctor || '-' }}</template>
        </el-table-column>
        <el-table-column label="病案号" align="left" min-width="120">
          <template slot-scope="scope">
            <span
              class="value-text-blue value-underline"
              @click="handleClick( scope.row)"
            >{{ scope.row.medical_record_no || '-' }}</span>
          </template>
        </el-table-column>
        <el-table-column label="患者姓名" align="left" min-width="120">
          <template slot-scope="scope">{{ scope.row.patient_name || '-' }}</template>
        </el-table-column>
        <el-table-column label="床号" align="left" min-width="120">
          <template slot-scope="scope">{{ scope.row.bed_no || '-' }}</template>
        </el-table-column>
        <el-table-column label="入院时间" align="left" min-width="120" prop="admission_time" sortable>
          <template slot-scope="scope">{{ scope.row.admission_time || '-' }}</template>
        </el-table-column>

        <el-table-column label="出院时间" align="left" min-width="120" prop="discharge_time" sortable>
          <template slot-scope="scope">{{ scope.row.discharge_time || '-' }}</template>
        </el-table-column>
        <el-table-column label="问题级别" align="left" min-width="120">
          <template slot-scope="scope">{{ scope.row.rule_type || '-' }}</template>
        </el-table-column>
        <el-table-column label="文书类型" align="left" min-width="120" prop="document_type" sortable>
          <template slot-scope="scope">{{ scope.row.document_type || '-' }}</template>
        </el-table-column>
        <el-table-column label="申诉状态" align="left" min-width="120" prop="appeal_status" sortable>
          <template slot-scope="scope">{{ scope.row.appeal_status || '-' }}</template>
        </el-table-column>
        <el-table-column label="末次质控时间" align="left" min-width="120">
          <template slot-scope="scope">{{ scope.row.last_quality_time || '-' }}</template>
        </el-table-column>
        <el-table-column label="是否出院" align="left" min-width="120">
          <template slot-scope="scope">{{ scope.row.is_discharge || '-' }}</template>
        </el-table-column>
      </el-table>
      <div class="pagination-container" style="margin-top: 20px; text-align: right">
        <el-pagination
          @size-change="val => handleSizeChange( val)"
          @current-change="val => handleCurrentChange( val)"
          :current-page="paginationData.currentPage"
          :page-sizes="[10, 20, 50, 100]"
          :page-size="paginationData.pageSize"
          layout="total, sizes, prev, pager, next, jumper"
          :total="tableData.count"
        ></el-pagination>
      </div>
    </div>
  </div>
</template>

<script>
import { shizhong_quality_records } from '@/api/excel';
export default {
  name: 'IndexCoreReport',
  data() {
    return {
      queryParams: {},
      tableData: {
        count: 0,
        list: [],
      },
      tableLoading: false,
      paginationData: {
        currentPage: 1,
        pageSize: 10,
      },
    };
  },
  mounted() {},
  methods: {
    formatPercent(value) {
      return value;
    },
    tableRowClassName({ row }) {
      let classes = [];
      if (row.selected) {
        classes.push('selected-row');
      }
      classes.push('table-row-hover');
      return classes.join(' ');
    },

    /**
     * 初始化数据
     * @param {Object} queryParams - 查询参数
     * @param {Object} data
     */
    initData(queryParams = {}, data) {
      this.queryParams = { ...queryParams };
      this.tableData = data;
    },

    exportCaseList() {
      const params = {
        ...this.queryParams,
        is_export: 1,
      };
      shizhong_quality_records(params).then(res => {
        const content = res.data;
        const blob = new Blob([content]);
        const fileName = `运行病历报表.csv`;
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
    handleSortChange(column) {
      const { prop, order } = column;
      if (order === 'descending') {
        this.queryParams.order_type = 'desc';
      } else if (order === 'ascending') {
        this.queryParams.order_type = 'asc';
      } else {
        this.queryParams.order_type = 'desc';
      }
      this.queryParams.order_key = prop;
      this.$emit('search', this.paginationData, this.queryParams);
    },
    handleClick(row) {
      this.storageSet('getData', row.jzhm);
      this.storageSet('getDataRule', row.rule_id);
      localStorage.setItem('isControl', true);
       this.$router.push({
        name: 'caseViews',
        query: {
          pageType: 'middleCaseControl',
        },
      });
    },

    handleSizeChange(val) {
      this.paginationData.pageSize = val;
      this.paginationData.currentPage = 1;
      this.$emit('search', this.paginationData, this.queryParams);
    },

    handleCurrentChange(val) {
      this.paginationData.currentPage = val;
      this.$emit('search', this.paginationData, this.queryParams);
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