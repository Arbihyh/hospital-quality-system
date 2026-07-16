<template>
  <div class="dept-stat-content">
    <!-- （一）病历质量最优前5名 -->
    <div class="table-card">
      <h3 class="table-title">（一）病历质量最优前5名</h3>
      <el-table :data="doctorData.best5" border stripe size="medium" style="width: 100%" :row-class-name="tableRowClassName" v-loading="tableLoading">
        <el-table-column label="排名" align="center" width="80">
          <template slot-scope="scope">
            {{ scope.row.rank || '-' }}
          </template>
        </el-table-column>
        <el-table-column label="医师姓名" align="left" min-width="120">
          <template slot-scope="scope">
            {{ scope.row.doctor_name || '-' }}
          </template>
        </el-table-column>
        <el-table-column label="所属科室" align="left" min-width="120">
          <template slot-scope="scope">
            {{ scope.row.department_name || '-' }}
          </template>
        </el-table-column>
        <el-table-column label="质控病历（例）" align="center">
          <template slot-scope="scope">
            <span class="value-text-blue value-underline" @click="handleClick('total_cases-1', scope.row)">{{ scope.row.total_cases || 0 }}</span>
          </template>
        </el-table-column>
        <el-table-column label="缺陷病历（例）" align="center">
          <template slot-scope="scope">
            <span class="trend-red value-underline" @click="handleClick('defect_cases-1', scope.row)">{{ scope.row.defect_cases || 0 }}</span>
          </template>
        </el-table-column>
        <el-table-column label="总扣分（分）" align="center">
          <template slot-scope="scope">
            <span>{{ formatNumber(scope.row.total_deduction) }}</span>
          </template>
        </el-table-column>
        <el-table-column label="平均分（分）" align="center">
          <template slot-scope="scope">
            <span>{{ formatNumber(scope.row.avg_score, 2) }}</span>
          </template>
        </el-table-column>
      </el-table>
    </div>

    <!-- （二）病历质量最差前5名 -->
    <div class="table-card">
      <h3 class="table-title">（二）病历质量最差前5名</h3>
      <el-table :data="doctorData.worst5" border stripe size="medium" style="width: 100%" :row-class-name="tableRowClassName" v-loading="tableLoading">
        <el-table-column label="排名" align="center" width="80">
          <template slot-scope="scope">
            {{ scope.row.rank || '-' }}
          </template>
        </el-table-column>
        <el-table-column label="医师姓名" align="left" min-width="120">
          <template slot-scope="scope">
            {{ scope.row.doctor_name || '-' }}
          </template>
        </el-table-column>
        <el-table-column label="所属科室" align="left" min-width="120">
          <template slot-scope="scope">
            {{ scope.row.department_name || '-' }}
          </template>
        </el-table-column>
        <el-table-column label="质控病历（例）" align="center">
          <template slot-scope="scope">
            <span class="value-text-blue value-underline" @click="handleClick('total_cases-2', scope.row)">{{ scope.row.total_cases || 0 }}</span>
          </template>
        </el-table-column>
        <el-table-column label="缺陷病历（例）" align="center">
          <template slot-scope="scope">
            <span class="trend-red value-underline" @click="handleClick('defect_cases-2', scope.row)">{{ scope.row.defect_cases || 0 }}</span>
          </template>
        </el-table-column>
        <el-table-column label="总扣分（分）" align="center">
          <template slot-scope="scope">
            <span>{{ formatNumber(scope.row.total_deduction) }}</span>
          </template>
        </el-table-column>
        <el-table-column label="平均分（分）" align="center">
          <template slot-scope="scope">
            <span>{{ formatNumber(scope.row.avg_score, 2) }}</span>
          </template>
        </el-table-column>
      </el-table>
    </div>

    <!-- （三）所有医师排名 -->
    <div class="table-card">
      <h3 class="table-title">（三）所有医师排名</h3>
      <el-table :data="paginationData.all.list" border stripe size="medium" style="width: 100%" :row-class-name="tableRowClassName" v-loading="tableLoading">
        <el-table-column label="排名" align="center" width="80">
          <template slot-scope="scope">
            {{ scope.row.rank || '-' }}
          </template>
        </el-table-column>
        <el-table-column label="医师姓名" align="left" min-width="120">
          <template slot-scope="scope">
            {{ scope.row.doctor_name || '-' }}
          </template>
        </el-table-column>
        <el-table-column label="所属科室" align="left" min-width="120">
          <template slot-scope="scope">
            {{ scope.row.department_name || '-' }}
          </template>
        </el-table-column>
        <el-table-column label="质控病历（例）" align="center">
          <template slot-scope="scope">
            <span class="value-text-blue value-underline" @click="handleClick('total_cases-3', scope.row)">{{ scope.row.total_cases || 0 }}</span>
          </template>
        </el-table-column>
        <el-table-column label="缺陷病历（例）" align="center">
          <template slot-scope="scope">
            <span class="trend-red value-underline" @click="handleClick('defect_cases-3', scope.row)">{{ scope.row.defect_cases || 0 }}</span>
          </template>
        </el-table-column>
        <el-table-column label="总扣分（分）" align="center">
          <template slot-scope="scope">
            <span>{{ formatNumber(scope.row.total_deduction) }}</span>
          </template>
        </el-table-column>
        <el-table-column label="平均分（分）" align="center">
          <template slot-scope="scope">
            <span>
              <!-- :class="{ 'trend-green': scope.row.avg_score >= 90, 'trend-yellow': scope.row.avg_score >= 75 && scope.row.avg_score < 90, 'trend-red': scope.row.avg_score < 75 }" -->
              {{ formatNumber(scope.row.avg_score, 2) }}
            </span>
          </template>
        </el-table-column>
      </el-table>

      <div class="pagination-container" style="margin-top: 20px; text-align: right">
        <el-pagination
          @size-change="handleSizeChange"
          @current-change="handleCurrentChange"
          :current-page="paginationData.all.currentPage"
          :page-sizes="[10, 20, 50, 100]"
          :page-size="paginationData.all.pageSize"
          layout="total, sizes, prev, pager, next, jumper"
          :total="paginationData.all.total"
        ></el-pagination>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'DoctorStatisticsNew',

  data() {
    return {
      queryParams: {},
      tableLoading: false,
      doctorData: {
        best5: [],
        worst5: [],
        all: [],
      },
      paginationData: {
        all: {
          currentPage: 1,
          pageSize: 10,
          total: 0,
          list: [],
        },
      },
    };
  },

  mounted() {},

  methods: {
    initData(queryParams = {}, data) {
      this.queryParams = { ...queryParams };
      this.doctorData = { ...data };
      this.initAllDoctorPagination();
    },

    initAllDoctorPagination() {
      this.paginationData.all.total = this.doctorData.all.length;
      this.paginationData.all.list = this.getPagedData(this.doctorData.all, this.paginationData.all.currentPage, this.paginationData.all.pageSize);
    },

    handleClick(tabKey, row) {
      console.log(tabKey, row);
      const { startTime, endTime } = this.calculateDateRange();

      const { dep_id } = this.queryParams;
      switch (tabKey) {
        case 'total_cases-1':
        case 'total_cases-2':
        case 'total_cases-3':
          this.$router.push({
            name: 'reportMedicalRecords',
            query: { startTime, endTime, dep_id, resident_doctor: [row.doctor_code], type: 'model-page' },
          });
          break;
        case 'defect_cases-1':
        case 'defect_cases-2':
        case 'defect_cases-3':
          this.$router.push({
            name: 'reportMedicalRecords',
            query: { startTime, endTime, dep_id, resident_doctor: [row.doctor_code], type: 'doctor-statistic' },
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
    getPagedData(data, currentPage, pageSize) {
      const startIndex = (currentPage - 1) * pageSize;
      const endIndex = startIndex + pageSize;
      return data.slice(startIndex, endIndex);
    },

    formatNumber(value, decimal = 0) {
      if (value === undefined || value === null) return decimal > 0 ? '0.00' : '0';
      return Number(value).toFixed(decimal);
    },

    handleSizeChange(val) {
      this.paginationData.all.pageSize = val;
      this.paginationData.all.currentPage = 1;
      this.initAllDoctorPagination();
    },
    handleCurrentChange(val) {
      this.paginationData.all.currentPage = val;
      this.initAllDoctorPagination();
    },

    tableRowClassName({ row, rowIndex }) {
      return 'table-row-hover';
    },
  },
};
</script>

<style lang="scss" scoped>
.dept-stat-content {
  width: 100%;
  padding: 0 10px;
  box-sizing: border-box;
}

.filter-bar {
  background: #fff;
  border-radius: 4px;
  padding: 16px 20px;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.08);
  border: 1px solid #e8e8e8;
  margin-bottom: 20px;

  .filter-form {
    ::v-deep .el-form-item {
      margin-bottom: 0;
      label {
        color: #333;
        font-weight: 500;
      }
      .el-select {
        min-width: 180px;
      }
    }
  }
}

.chart-group {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
  gap: 20px;
  margin-bottom: 20px;

  .chart-card {
    background: #fff;
    border-radius: 4px;
    padding: 20px;
    box-shadow: 0 1px 4px rgba(0, 0, 0, 0.08);
    border: 1px solid #e8e8e8;

    .chart-title {
      font-size: 16px;
      color: #333;
      margin-bottom: 16px;
      padding-left: 12px;
      border-left: 3px solid #185da6;
    }

    .chart-box {
      height: 300px;
      padding: 0 20px;
    }
  }
}

.table-card {
  background: #fff;
  padding: 20px;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.08);
  border: 1px solid #e8e8e8;
  margin-bottom: 20px;
  overflow-x: auto;

  .table-title {
    font-size: 16px;
    color: #333;
    margin-bottom: 16px;
    padding-left: 12px;
    border-left: 3px solid #185da6;
    font-weight: 600;
  }
}

.value-text-blue {
  color: #185da6;
  font-weight: 500;
  cursor: pointer;
}

.value-underline {
  text-decoration: underline;
  text-underline-offset: 4px;
  text-decoration-color: currentColor;
  text-decoration-thickness: 2px;
}

.trend-green {
  color: #22c55e !important;
  font-weight: 500;
}

.trend-yellow {
  color: #eab308 !important;
  font-weight: 500;
}

.trend-red {
  color: #ef4444 !important;
  font-weight: 500;
  cursor: pointer;
}

.trend-orange {
  color: #f97316 !important;
  font-weight: 500;
}

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
  background-color: #e8f4fc !important;
}

::v-deep .el-table__header-wrapper th {
  font-weight: 500 !important;
  background-color: #f5f7fa !important;
  color: #333 !important;
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
  .chart-group {
    grid-template-columns: 1fr;
  }

  .filter-bar {
    .filter-form {
      ::v-deep .el-form-item {
        display: block;
        margin-bottom: 12px;
      }
    }
  }

  .table-card {
    overflow-x: scroll;
    padding: 10px;
  }

  .table-title {
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