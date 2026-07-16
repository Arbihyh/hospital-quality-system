<!-- 指标分析 -->
 <template>
  <div class="app-container">
    <div class="search-box">
      <el-form ref="searchFormRef" :model="searchForm" label-width="80px">
        <el-row>
          <el-col :span="5">
            <DateRangePicker
              labelText="出院日期"
              placeholder="请选择出院日期"
              v-model="searchForm.year_month"
              :showShortcuts="['currentMonth', 'lastMonth', 'thisYear', 'lastYear', 'beforeLastYear', 'quarter1', 'quarter2', 'quarter3', 'quarter4']"
              @shortcut-click="handleShortcutClick"
            />
          </el-col>
          <el-col :span="6">
            <el-form-item label="出院科室">
              <el-select v-model="searchForm.AAC11N" clearable filterable placeholder="请选择出院科室" style="width: 100%">
                <el-option v-for="(item, index) in departmentList" :label="item.name" :value="item.name" :key="index"></el-option>
              </el-select>
            </el-form-item>
          </el-col>
          <el-col :span="6">
            <el-form-item label="责任医师">
              <el-select v-model="searchForm.AEE03" clearable filterable placeholder="请选择">
                <el-option v-for="(item, index) in AEE03Options" :key="index" :label="item.name" :value="item.name"></el-option>
              </el-select>
            </el-form-item>
          </el-col>
          <el-col :span="5">
            <el-form-item>
              <div style="display: flex; justify-content: flex-end; margin-right: 20px">
                <el-button style="background-color: #1b64b0; color: #fff" @click="funQuery">查询</el-button>
                <el-button @click="reset">重置</el-button>
                <!-- <el-button style="float: right" type="primary" @click="exportCaseList">导出</el-button> -->
              </div>
            </el-form-item>
          </el-col>
        </el-row>
      </el-form>
    </div>
    <div class="content">
      <div class="aside-left">
        <CustomCardBox width="100%" :height="'calc(100vh - 80px)'" overflowY="scroll">
          <div class="aside-left-search">
            <SearchInput :value="filterText" placeholderText="请输入关键词进行查询" @change="handleSearchChange" />
            <el-tree
              default-expand-all
              class="filter-tree"
              node-key="id"
              highlight-current
              :data="treeData"
              :props="defaultProps"
              :filter-node-method="filterNode"
              ref="treeRef"
              @node-click="handleNodeClick"
              :render-content="renderContent"
            ></el-tree>
          </div>
        </CustomCardBox>
      </div>
      <div class="aside-right">
        <CustomCardBox width="100%" :height="'calc(100vh - 80px)'" overflowY="scroll" bgColor="#ffffff">
          <div class="aside-right-title">
            <CardTitle :title="currentTreeNode.name" :showLine="false"></CardTitle>
          </div>
          <div class="aside-right-chart" ref="chartBoxRef">
            <div style="margin-bottom: 13px; display: flex; justify-content: space-between; margin: 0 20px">
              <TabBtnGroup
                :active-key="timeActiveKey"
                :tab-list="[
                  { key: 'year', label: '年' },
                  { key: 'quarter', label: '季' },
                  { key: 'month', label: '月' },
                ]"
                @tab-change="key => changeActiveKey('timeActiveKey', key)"
              />
              <ChartDownloadBtn :chart-instance="indexCharts" chart-name="指标柱状图" />
            </div>
            <div style="margin: 0 60px">
              <EChartsColumnar :chart-data="indexChartData" barColor="#1B64B0" :height="400" @get-chart-instance="getIndexChartsInstance" />
            </div>
          </div>
          <div class="aside-right-table">
            <el-button style="float: right;margin-bottom: 10px;" type="primary" @click="exportCaseList">导出</el-button>
            <el-table :data="tableData" class="mb20" @sort-change="handleSortChange">
              <el-table-column prop="rank" label="排名" align="center"></el-table-column>
              <el-table-column prop="dep_name" label="就诊科室" align="center" sortable="custom" :sort-orders="['ascending', 'descending']"></el-table-column>
              <el-table-column prop="radio" label="达标率" align="center">
                <template slot-scope="scope">
                  <span :class="Number(scope.row.radio) < 60 ? 'danger-red' : ''">{{ scope.row.radio }}%</span>
                </template>
              </el-table-column>
              <el-table-column prop="fenzi" label="分子" align="center">
                <template slot-scope="scope">
                  <span class="span-link" @click="toPage(scope.row, 1)">{{ scope.row.fenzi }}</span>
                </template>
              </el-table-column>
              <el-table-column prop="fenmu" label="分母" align="center" show-overflow-tooltip>
                <template slot-scope="scope">
                  <span class="span-link" @click="toPage(scope.row, 0)">{{ scope.row.fenmu }}</span>
                </template>
              </el-table-column>
              <el-table-column prop="chain_ratio" label="环比" align="center" show-overflow-tooltip>
                <template slot-scope="scope">
                  <span style="display: inline-flex; align-items: center; gap: 4px">
                    <svg
                      v-if="Number(scope.row.chain_ratio) > 0"
                      t="1767166477592"
                      class="icon"
                      viewBox="0 0 1024 1024"
                      version="1.1"
                      xmlns="http://www.w3.org/2000/svg"
                      p-id="4995"
                      width="16"
                      height="16"
                    >
                      <path
                        d="M740.266667 262.4l-213.333333-213.333333C522.666667 44.8 518.4 42.666667 512 42.666667c-6.4 0-10.666667 2.133333-14.933333 6.4l-213.333333 213.333333C279.466667 266.666667 277.333333 270.933333 277.333333 277.333333c0 12.8 8.533333 21.333333 21.333333 21.333333 6.4 0 10.666667-2.133333 14.933333-6.4L490.666667 115.2 490.666667 960c0 12.8 8.533333 21.333333 21.333333 21.333333 12.8 0 21.333333-8.533333 21.333333-21.333333L533.333333 115.2l177.066667 177.066667c4.266667 4.266667 8.533333 6.4 14.933333 6.4 12.8 0 21.333333-8.533333 21.333333-21.333333C746.666667 270.933333 744.533333 266.666667 740.266667 262.4z"
                        fill="#567722"
                        p-id="4996"
                      ></path>
                    </svg>
                    <svg v-else t="1767167185867" class="icon" viewBox="0 0 1024 1024" version="1.1" xmlns="http://www.w3.org/2000/svg" p-id="18119" width="16" height="16">
                      <path d="M495.616 726.528V0h32.768v726.528H660.48L512 1024l-148.48-297.472z" fill="#f5222d" p-id="18120"></path>
                    </svg>
                    <span :class="Number(scope.row.chain_ratio) > 0 ? 'blue' : 'danger-red'">{{ scope.row.chain_ratio }}%</span>
                  </span>
                </template>
              </el-table-column>
            </el-table>
          </div>
        </CustomCardBox>
      </div>
    </div>
  </div>
</template>
 
<script>
//组件
import DateRangePicker from '@/components/DateRangePicker/SingleTime.vue';
import SearchInput from '@/components/SearchInput';
import CustomCardBox from '../comp/CustomCardBox';
import TabBtnGroup from '@/components/TabBtnGroup';
import ChartDownloadBtn from '@/components/chart/ChartDownloadBtn';
import EChartsColumnar from '@/components/chart/EChartColumnar';
import { exportQuality_index_department_ranking } from '@/api/excel';

import moment from 'moment';

export default {
  components: { DateRangePicker, CustomCardBox, SearchInput, TabBtnGroup, ChartDownloadBtn, EChartsColumnar },
  data() {
    return {
      searchForm: {
        year_month: moment().subtract(1, 'month').format('YYYY-MM'),
        AAC11N: '',
        AEE03: '',
      },
      defaultProps: {
        children: 'children',
        label: 'name',
      },
      timeActiveKey: 'month',
      filterText: '',
      departmentList: [],
      AEE03Options: [],
      treeData: [],
      tableData: [],
      currentTreeNode: {},
      indexCharts: {},
      indexChartData: {
        xAxisData: [],
        seriesData: [],
      },
    };
  },
  watch: {
    filterText(val) {
      this.$refs.treeRef.filter(val);
    },
  },

  async mounted() {
    this.getDepartmentList();
    this.getStaffList();
    await this.getTreeData();
  },
  methods: {
    getDepartmentList() {
      this.$axios.post('/selectInfo').then(res => {
        this.departmentList = res.data.department.slice(1, res.data.department.length);
      });
    },
    getStaffList() {
      this.$axios2.get('/get_staff?ygjb=主治医师').then(res => {
        this.AEE03Options = res.data;
      });
    },

    handleShortcutClick(type, year_month) {
      console.log('handleShortcutClick', year_month);
      this.searchForm.year_month = year_month;
      this.timeActiveKey = type;
      this.getChartDataByType(this.timeActiveKey);
    },
    getIndexChartsInstance(val) {
      this.indexCharts = val;
    },

    handleSortChange() {},

    toPage(row, status) {
      let startTime = '';
      let endTime = '';

      const timeStr = this.searchForm.year_month || '';
      const [year, period] = timeStr.split('-').map(item => item.padStart(2, '0'));

      if (this.timeActiveKey === 'month' && year && period) {
        const monthLastDay = new Date(Number(year), Number(period), 0).getDate();
        startTime = `${year}${period}01`;
        endTime = `${year}${period}${monthLastDay.toString().padStart(2, '0')}`;
      } else if (this.timeActiveKey === 'quarter' && year && period) {
        const quarterMap = {
          '01': { startMonth: '01', endMonth: '03' },
          '02': { startMonth: '04', endMonth: '06' },
          '03': { startMonth: '07', endMonth: '09' },
          '04': { startMonth: '10', endMonth: '12' },
        };
        const quarterInfo = quarterMap[period] || quarterMap['01']; // 默认第一季度
        startTime = `${year}${quarterInfo.startMonth}01`;
        const quarterEndLastDay = new Date(Number(year), Number(quarterInfo.endMonth), 0).getDate();
        endTime = `${year}${quarterInfo.endMonth}${quarterEndLastDay.toString().padStart(2, '0')}`;
      } else if (this.timeActiveKey === 'year' && year) {
        startTime = `${year}0101`;
        endTime = `${year}1231`;
      }

      localStorage.setItem('majorIndexData', JSON.stringify(this.currentTreeNode));
      // console.log('currentTreeNode', this.currentTreeNode);
      this.$router.push({
        path: '/majorIndexDetail',
        query: {
          startTime: startTime,
          endTime: endTime,
          AAC11N: row.dep_name,
          AEE03: this.searchForm.AEE03,
          status,
          catalog: this.currentTreeNode.url,
        },
      });
    },

    changeActiveKey(module, key) {
      this[module] = key;
      console.log(`${module} 切换为 ${key}`);
      this.$nextTick(() => {
        this.getChartDataByType(key);
      });
    },
    async getChartDataByType(type) {
      console.log('getChartDataByType', this.searchForm.year_month);
      let loadingInstance = null;
      try {
        loadingInstance = this.$loading({
          target: this.$refs.chartBoxRef,
          text: '数据加载中...',
          background: 'rgba(0,0,0,0.1)',
          lock: true,
        });
        const params = {
          year_month: this.searchForm.year_month.replace('-', ''), // 出院日期
          AAC11N: this.searchForm.AAC11N, // 出院科室
          AEE03: this.searchForm.AEE03, // 责任医师
          type: type,
          category: this.currentTreeNode.url + '' || '',
          is_export: '0',
        };
        const res = await this.$axios2.get('/quality_index_analysis', { params });
        if (res.code === 200 && res.data) {
          this.indexChartData = {
            xAxisData: Object.keys(res.data) || [],
            seriesData: Object.values(res.data) || [],
          };
        } else {
          this.indexChartData = { xAxisData: [], seriesData: [] };
        }
      } catch (error) {
        console.error('图表数据查询失败：', error);
        this.indexChartData = { xAxisData: [], seriesData: [] };
      } finally {
        loadingInstance && loadingInstance.close();
      }
    },

    handleSearchChange(val) {
      this.filterText = val;
    },
    async funQuery() {
      try {
        await this.getChartDataByType(this.timeActiveKey);
        await this.getList();
      } catch (error) {}
    },
    filterNode(value, data) {
      if (!value) return true;
      return data.name.indexOf(value) !== -1;
    },
    handleNodeClick(data) {
      const { children, fenzi, fenmu } = data;
      if (!children || children.length === 0) {
        if (fenzi.length && fenmu.length) {
          this.currentTreeNode = data;
          this.indexData = data;
          this.$refs.treeRef.setCurrentKey(data.id);
          this.getChartDataByType(this.timeActiveKey);
          this.getList();
        }
      }
    },
    renderContent(h, { node }) {
      return h(
        'span',
        {
          class: 'custom-node',
          style: {
            fontWeight: node.isLeaf ? 'normal' : 'bold',
          },
        },
        node.label,
      );
    },
    getTreeData() {
      this.$axios2.get('/catalog_lists').then(res => {
        if (Array.isArray(res.data)) {
          const array = res.data[0]?.children || [];
          this.treeData = array;
          this.$nextTick(() => {
            this.$refs.treeRef.setCurrentKey(this.defaultOpenMenu(array));
            const defaultNode = this.$refs.treeRef.getCurrentNode();
            if (defaultNode) {
              this.currentTreeNode = defaultNode;
              this.getChartDataByType(this.timeActiveKey);
              this.getList();
            }
          });
        }
      });
    },
    async getList() {
      try {
        const params = {
          year_month: this.searchForm.year_month.replace('-', ''), // 出院日期
          AAC11N: this.searchForm.AAC11N, // 出院科室
          AEE03: this.searchForm.AEE03, // 责任医师
          type: this.timeActiveKey,
          category: this.currentTreeNode.url + '' || '',
        };
        console.log('params', params);
        const res = await this.$axios2.get('/quality_index_department_ranking', { params });
        console.log('res', res);
        if (res?.code === 200) {
          this.tableData = res.data || [];
        } else {
          this.tableData = [];
        }
      } catch (error) {
        this.tableData = [];
      }
    },

    exportCaseList() {
      const params = {
        year_month: this.searchForm.year_month.replace('-', ''), // 出院日期
        AAC11N: this.searchForm.AAC11N, // 出院科室
        AEE03: this.searchForm.AEE03, // 责任医师
        type: this.timeActiveKey,
        category: this.currentTreeNode.url + '' || '',
        is_export: 1,
      };
      exportQuality_index_department_ranking(params).then(res => {
        const content = res.data;
        const blob = new Blob([content]);
        const fileName = `指标分析.csv`;
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
    defaultOpenMenu(list) {
      if (list.length) {
        // 直接取第一个节点（如果有子节点则取第一个子节点）
        if (list[0].children && list[0].children.length) {
          this.indexData = list[0].children[0];
          return list[0].children[0].id;
        } else {
          this.indexData = list[0];
          return list[0].id;
        }
      } else {
        return '';
      }
    },
    reset() {
      this.searchForm = {
        year_month: moment().subtract(1, 'month').format('YYYY-MM'),
        AAC11N: '',
        AEE03: '',
      };
      this.timeActiveKey = 'month';
      this.getChartDataByType(this.timeActiveKey);
    },
  },
};
</script>
 
<style lang="scss" scoped>
@import '~@/styles/common.scss';
@import './index.model.scss';
</style>