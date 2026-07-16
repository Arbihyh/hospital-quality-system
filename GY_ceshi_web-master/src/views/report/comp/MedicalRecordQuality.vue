<template>
  <div class="medical-quality-content">
    <!-- （一）各科室甲乙丙级病历占比 -->
    <div class="table-card">
      <h3 class="table-title">（一）各科室甲乙丙级病历占比</h3>
      <el-table
        :data="paginationData.case_level_by_dept.list"
        border
        stripe
        size="medium"
        style="width: 100%"
        :row-class-name="tableRowClassName"
        v-loading="tableLoading"
      >
        <el-table-column label="排名" align="center" width="80">
          <template slot-scope="scope">{{ scope.row.rank || '-' }}</template>
        </el-table-column>
        <el-table-column label="科室名称" align="left" min-width="120">
          <template slot-scope="scope">{{ scope.row.dept_name || '-' }}</template>
        </el-table-column>
        <el-table-column label="病历总数（例）" align="center">
          <template slot-scope="scope">
            <span
              class="value-text-blue value-underline"
              @click="handleClick('total-1', scope.row)"
            >{{ scope.row.total_cases || 0 }}</span>
          </template>
        </el-table-column>
        <el-table-column label="甲级病案（例）" align="center">
          <template slot-scope="scope">
            <span
              class="value-text-blue value-underline"
              @click="handleClick('grade_a_count', scope.row)"
            >{{ scope.row.grade_a_count || 0 }}</span>
          </template>
        </el-table-column>
        <el-table-column label="甲级占比" align="center">
          <template slot-scope="scope">
            <span
              :class="{ highlight: scope.row.grade_a_ratio < 90 }"
            >{{ formatPercent(scope.row.grade_a_ratio) }}%</span>
          </template>
        </el-table-column>
        <el-table-column label="乙级病案（例）" align="center">
          <template slot-scope="scope">
            <span
              class="value-text-blue value-underline"
              @click="handleClick('grade_b_count', scope.row)"
            >{{ scope.row.grade_b_count || 0 }}</span>
          </template>
        </el-table-column>
        <el-table-column label="乙级占比" align="center">
          <template slot-scope="scope">{{ formatPercent(scope.row.grade_b_ratio) }}%</template>
        </el-table-column>
        <el-table-column label="丙级病案（例）" align="center">
          <template slot-scope="scope">
            <span
              class="value-text-blue value-underline"
              @click="handleClick('grade_c_count', scope.row)"
            >{{ scope.row.grade_c_count || 0 }}</span>
          </template>
        </el-table-column>
        <el-table-column label="丙级占比" align="center">
          <template slot-scope="scope">
            <span class="highlight">{{ formatPercent(scope.row.grade_c_ratio) }}%</span>
          </template>
        </el-table-column>
      </el-table>
      <div class="pagination-container" style="margin-top: 20px; text-align: right">
        <el-pagination
          @size-change="val => handleSizeChange('case_level_by_dept', val)"
          @current-change="val => handleCurrentChange('case_level_by_dept', val)"
          :current-page="paginationData.case_level_by_dept.currentPage"
          :page-sizes="[10, 20, 50, 100]"
          :page-size="paginationData.case_level_by_dept.pageSize"
          layout="total, sizes, prev, pager, next, jumper"
          :total="paginationData.case_level_by_dept.total"
        ></el-pagination>
      </div>
    </div>

    <!-- （二）单否项缺陷情况 -->
    <div class="table-card">
      <h3 class="table-title">（二）单否项缺陷情况</h3>
      <el-table
        :data="paginationData.single_no_defects.list"
        border
        stripe
        size="medium"
        style="width: 100%"
        :row-class-name="tableRowClassName"
        v-loading="tableLoading"
      >
        <el-table-column label="排名" align="center" width="80">
          <template slot-scope="scope">{{ scope.row.rank || '-' }}</template>
        </el-table-column>
        <el-table-column label="科室名称" align="left" min-width="120">
          <template slot-scope="scope">{{ scope.row.dept_name || '-' }}</template>
        </el-table-column>
        <el-table-column label="病历总数（例）" align="center">
          <template slot-scope="scope">
            <span
              class="value-text-blue value-underline"
              @click="handleClick('total-2', scope.row)"
            >{{ scope.row.total_cases || 0 }}</span>
          </template>
        </el-table-column>
        <el-table-column label="单否项缺陷病历（例）" align="center">
          <template slot-scope="scope">
            <span
              class="value-text-blue value-underline"
              @click="handleClick('defect_cases', scope.row)"
            >{{ scope.row.defect_cases || 0 }}</span>
          </template>
        </el-table-column>
        <el-table-column label="单否项问题数量（个）" align="center">
          <template slot-scope="scope">
            <span
              class="value-text-blue value-underline"
              @click="handleClick('defect_count', scope.row)"
            >{{ scope.row.defect_count || 0 }}</span>
          </template>
        </el-table-column>
        <el-table-column label="占比" align="center">
          <template slot-scope="scope">
            <span class="highlight">{{ formatPercent(scope.row.ratio) }}%</span>
          </template>
        </el-table-column>
      </el-table>
      <div class="pagination-container" style="margin-top: 20px; text-align: right">
        <el-pagination
          @size-change="val => handleSizeChange('single_no_defects', val)"
          @current-change="val => handleCurrentChange('single_no_defects', val)"
          :current-page="paginationData.single_no_defects.currentPage"
          :page-sizes="[10, 20, 50, 100]"
          :page-size="paginationData.single_no_defects.pageSize"
          layout="total, sizes, prev, pager, next, jumper"
          :total="paginationData.single_no_defects.total"
        ></el-pagination>
      </div>
    </div>

    <!-- （三）医师申诉情况 -->
    <div class="table-card">
      <h3 class="table-title">（三）医师申诉情况</h3>
      <h3 class="table-title-no">驳回数量最多的前五个问题</h3>
      <el-table
        :data="medicalQualityData.appeal_stats.top5_rejected"
        border
        stripe
        size="medium"
        style="width: 100%"
        :row-class-name="tableRowClassName"
        v-loading="tableLoading"
      >
        <el-table-column label="排名" align="center" width="80">
          <template slot-scope="scope">{{ scope.row.rank || '-' }}</template>
        </el-table-column>
        <el-table-column label="问题名称" align="left" min-width="200">
          <template slot-scope="scope">{{ scope.row.issue || '-' }}</template>
        </el-table-column>
        <el-table-column label="申诉数量（个）" align="center">
          <template slot-scope="scope">
            <span>{{ scope.row.appeal_count || 0 }}</span>
          </template>
        </el-table-column>
        <el-table-column label="驳回数量（个）" align="center">
          <template slot-scope="scope">
            <span>{{ scope.row.rejected_count || 0 }}</span>
          </template>
        </el-table-column>
        <el-table-column label="申诉成功率" align="center">
          <template slot-scope="scope">
            <span
              :class="{ highlight: scope.row.success_rate < 10 }"
            >{{ formatPercent(scope.row.success_rate) }}%</span>
          </template>
        </el-table-column>
      </el-table>

      <h3 class="table-title-no" style="margin-top: 20px">申诉情况</h3>
      <el-table
        :data="paginationData.by_department.list"
        border
        stripe
        size="medium"
        style="width: 100%"
        :row-class-name="tableRowClassName"
        v-loading="tableLoading"
      >
        <el-table-column label="排名" align="center" width="80">
          <template slot-scope="scope">{{ scope.row.rank || '-' }}</template>
        </el-table-column>
        <el-table-column label="科室名称" align="left" min-width="120">
          <template slot-scope="scope">{{ scope.row.dept_name || '-' }}</template>
        </el-table-column>
        <el-table-column label="申诉医师（人）" align="center">
          <template slot-scope="scope">
            <span
              class="value-text-blue value-underline"
              @click="handleClick('appeal_doctors', scope.row)"
            >{{ scope.row.appeal_doctors || 0 }}</span>
          </template>
        </el-table-column>
        <el-table-column label="申诉问题（个）" align="center">
          <template slot-scope="scope">{{ scope.row.appeal_issues || 0 }}</template>
        </el-table-column>
        <el-table-column label="通过数量（个）" align="center">
          <template slot-scope="scope">{{ scope.row.passed_count || 0 }}</template>
        </el-table-column>
        <el-table-column label="驳回数量（个）" align="center">
          <template slot-scope="scope">
            <span
              class="value-text-blue value-underline"
              @click="handleClick('rejected_count', scope.row)"
            >{{ scope.row.rejected_count || 0 }}</span>
          </template>
        </el-table-column>
        <el-table-column label="申诉成功率" align="center">
          <template slot-scope="scope">
            <span
              :class="{ highlight: scope.row.success_rate < 50 }"
            >{{ formatPercent(scope.row.success_rate) }}%</span>
          </template>
        </el-table-column>
      </el-table>
      <div class="pagination-container" style="margin-top: 20px; text-align: right">
        <el-pagination
          @size-change="val => handleSizeChange('by_department', val)"
          @current-change="val => handleCurrentChange('by_department', val)"
          :current-page="paginationData.by_department.currentPage"
          :page-sizes="[10, 20, 50, 100]"
          :page-size="paginationData.by_department.pageSize"
          layout="total, sizes, prev, pager, next, jumper"
          :total="paginationData.by_department.total"
        ></el-pagination>
      </div>
    </div>

    <!-- （四）病案管理质量控制指标达成情况 -->
    <div class="table-card">
      <h3 class="table-title">（四）病案管理质量控制指标达成情况</h3>
      <h3 class="table-title-no">指标达成率最优前5名</h3>
      <el-table
        :data="medicalQualityData.indicator_achievement.best5"
        border
        stripe
        size="medium"
        style="width: 100%"
        :row-class-name="tableRowClassName"
        v-loading="tableLoading"
      >
        <el-table-column label="序号" align="center" width="80">
          <template slot-scope="scope">{{ scope.row.rank || '-' }}</template>
        </el-table-column>
        <el-table-column label="指标名称" align="left" min-width="200">
          <template slot-scope="scope">{{ scope.row.indicator_name || '-' }}</template>
        </el-table-column>
        <el-table-column label="第一名" align="center">
          <template slot-scope="scope">{{ scope.row.dept1_name || '-' }}{{ scope.row.dept1 || '-' }}</template>
        </el-table-column>
        <el-table-column label="第二名" align="center">
          <template slot-scope="scope">{{ scope.row.dept2_name || '-' }}{{ scope.row.dept2 || '-' }}</template>
        </el-table-column>
        <el-table-column label="第三名" align="center">
          <template slot-scope="scope">{{ scope.row.dept3_name || '-' }}{{ scope.row.dept3 || '-' }}</template>
        </el-table-column>
        <el-table-column label="第四名" align="center">
          <template slot-scope="scope">{{ scope.row.dept4_name || '-' }}{{ scope.row.dept4 || '-' }}</template>
        </el-table-column>
        <el-table-column label="第五名" align="center">
          <template slot-scope="scope">{{ scope.row.dept5_name || '-' }}{{ scope.row.dept5 || '-' }}</template>
        </el-table-column>
      </el-table>

      <h3 class="table-title-no" style="margin-top: 20px">指标达成率最差前5名</h3>
      <el-table
        :data="medicalQualityData.indicator_achievement.worst5"
        border
        stripe
        size="medium"
        style="width: 100%"
        :row-class-name="tableRowClassName"
        v-loading="tableLoading"
      >
        <el-table-column label="序号" align="center" width="80">
          <template slot-scope="scope">{{ scope.row.rank || '-' }}</template>
        </el-table-column>
        <el-table-column label="指标名称" align="left" min-width="200">
          <template slot-scope="scope">{{ scope.row.indicator_name || '-' }}</template>
        </el-table-column>
        <el-table-column label="第一名（末位）" align="center">
          <template slot-scope="scope">{{ scope.row.dept1_name || '-' }}{{ scope.row.dept1 || '-' }}</template>
        </el-table-column>
        <el-table-column label="第二名（末位）" align="center">
          <template slot-scope="scope">{{ scope.row.dept2_name || '-' }}{{ scope.row.dept2 || '-' }}</template>
        </el-table-column>
        <el-table-column label="第三名（末位）" align="center">
          <template slot-scope="scope">{{ scope.row.dept3_name || '-' }}{{ scope.row.dept3 || '-' }}</template>
        </el-table-column>
        <el-table-column label="第四名（末位）" align="center">
          <template slot-scope="scope">{{ scope.row.dept4_name || '-' }}{{ scope.row.dept4 || '-' }}</template>
        </el-table-column>
        <el-table-column label="第五名（末位）" align="center">
          <template slot-scope="scope">{{ scope.row.dept5_name || '-' }}{{ scope.row.dept5 || '-' }}</template>
        </el-table-column>
      </el-table>
    </div>
  </div>
</template>

<script>
export default {
  name: 'MedicalRecordQuality',
  data() {
    return {
      queryParams: {},
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
      if (value === undefined || value === null) return '0.00';
      return Number(value).toFixed(2);
    },

    tableRowClassName({ row, rowIndex }) {
      return 'table-row-hover';
    },

    /**
     * 初始化数据
     * @param {Object} queryParams - 查询参数
     * @param {Object} data
     */
    initData(queryParams = {}, data) {
      this.queryParams = { ...queryParams };
      this.medicalQualityData = { ...data };
      this.initPaginationData();
    },

    handleClick(tabKey, row) {
      console.log(tabKey, row);
      let record_levels = [];

      const { startTime, endTime } = this.calculateDateRange();
      let deptArray = [];
      let dep_id = this.queryParams.dep_id;
      if (row && Reflect.has(row, 'dep_id') && row.dep_id != null && row.dep_id !== '') {
        deptArray = [row.dep_id];
      }else{
        deptArray = dep_id;
      }
      switch (tabKey) {
        case 'total-1':
        case 'total-2':
          this.$router.push({
            name: 'reportMedicalRecords',
            query: { startTime, endTime, dep_id: deptArray, record_levels, type: 'model-page' },
          });
          break;
        case 'grade_a_count':
          record_levels = ['甲'];
          this.$router.push({
            name: 'reportMedicalRecords',
            query: { startTime, endTime, dep_id: deptArray, record_levels, type: 'model-page' },
          });
          break;
          break;
        case 'grade_b_count':
          record_levels = ['乙'];
          this.$router.push({
            name: 'reportMedicalRecords',
            query: { startTime, endTime, dep_id: deptArray, record_levels, type: 'model-page' },
          });
          break;
          break;
        case 'grade_c_count':
          record_levels = ['丙'];
          this.$router.push({
            name: 'reportMedicalRecords',
            query: { startTime, endTime, dep_id: deptArray, record_levels, type: 'model-page' },
          });
          break;
        case 'defect_cases':
        case 'defect_count':
          this.$router.push({
            name: 'reportMedicalRecords',
            query: { startTime, endTime, dep_id: [dep_id], is_danfou: '有', type: 'model-page' },
          });
          break;
        case 'appeal_count':
        case 'appeal_doctors':
          // this.$router.push({
          //   name: 'reportDoctorAppeal',
          //   query: { startTime, endTime, dep_id, type: 'appeal-doctors' },
          // });
          break;
        case 'rejected_count':
          // this.$router.push({
          //   name: 'reportDoctorAppeal',
          //   query: { startTime, endTime, dep_id, appeal_status: '2', type: 'rejected-count' },
          // });
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