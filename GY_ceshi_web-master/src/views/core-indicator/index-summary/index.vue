<!-- 指标概括 -->
 <template>
  <div class="app-container">
    <div class="search-box">
      <el-form ref="searchFormRef" :model="searchForm" label-width="80px">
        <el-row>
          <el-col :span="7">
            <DateRangePicker
              labelText="出院日期"
              :show-shortcuts="['currentMonth', 'lastMonth', 'thisYear', 'lastYear', 'beforeLastYear', 'quarter1', 'quarter2', 'quarter3', 'quarter4']"
              v-model="searchForm"
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
              <el-cascader v-model="searchForm.AEE03" :options="AEE03Options" :props="{ expandTrigger: 'hover' }"></el-cascader>
            </el-form-item>
          </el-col>
          <el-col :span="5">
            <el-form-item>
              <div style="display: flex; justify-content: flex-end; margin-right: 20px">
                <el-button style="background-color: #1b64b0; color: #fff" @click="funQuery">查询</el-button>
                <el-button @click="reset">重置</el-button>
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
      <div class="aside-right" ref="asideRightRef" @scroll="handleAsideScroll">
        <div class="aside-right-item1">
          <CustomCardBox width="100%" height="200px">
            <div class="aside-right-title">
              <div class="title-btn">
                <CardTitle :title="currentTreeNode.name" :showLine="false" />
                <span class="span-btn" @click="clickJsTitleBtn">指标计算</span>
              </div>

              <div class="mb16 pl16">
                <span>分子: {{ indexData.fenzi_name }}</span>
                <el-popover placement="right-start" trigger="hover">
                  <div v-for="(fItem, fIndex) in fenziList" :key="fIndex" class="mb16">
                    <div
                      class="input_label"
                      style="width: 140px; padding: 8px 15px; line-height: 24px; display: inline-block; border: 1px solid #dcdfe6; border-radius: 4px; vertical-align: top"
                    >
                      {{ fItem.name }}
                    </div>
                    <div
                      class="input_label"
                      style="
                        width: 280px;
                        margin-left: 16px;
                        padding: 8px 15px;
                        line-height: 24px;
                        display: inline-block;
                        border: 1px solid #dcdfe6;
                        border-radius: 4px;
                        vertical-align: top;
                      "
                    >
                      {{ fItem.info }}
                    </div>
                  </div>
                  <span slot="reference" class="span-sol-btn ml16">分子计算口径</span>
                </el-popover>
              </div>
              <div class="mb16 pl16">
                <span>分母: {{ indexData.fenmu_name }}</span>
                <el-popover placement="right-start" trigger="hover">
                  <div v-for="(mItem, mIndex) in fenmuList" :key="mIndex" class="mb16">
                    <div
                      class="input_label"
                      style="width: 140px; padding: 8px 15px; line-height: 24px; display: inline-block; border: 1px solid #dcdfe6; border-radius: 4px; vertical-align: top"
                    >
                      {{ mItem.name }}
                    </div>
                    <div
                      class="input_label"
                      style="
                        width: 280px;
                        margin-left: 16px;
                        padding: 8px 15px;
                        line-height: 24px;
                        display: inline-block;
                        border: 1px solid #dcdfe6;
                        border-radius: 4px;
                        vertical-align: top;
                      "
                    >
                      {{ mItem.info }}
                    </div>
                  </div>
                  <span slot="reference" class="span-sol-btn ml16">分母计算口径</span>
                </el-popover>
              </div>
            </div>
          </CustomCardBox>
        </div>
        <div class="aside-right-item2">
          <CustomCardBox width="100%">
            <div style="margin-bottom: 13px; display: flex; justify-content: space-between; align-items: center">
              <CardTitle title="统计结果" />
              <el-button size="small" class="export-btn" @click="onExport">导出</el-button>
            </div>
            <div class="aside-right-table">
              <el-table :data="tableData" class="mb20" @sort-change="handleSortChange">
                <el-table-column prop="time" label="日期" align="center">
                  <template slot-scope="scope">
                    <span v-if="scope.row.month">{{ scope.row.year }}-{{ scope.row.month < 10 ? `0${scope.row.month}` : scope.row.month }}</span>
                    <span v-else>{{ scope.row.year }}</span>
                  </template>
                </el-table-column>
                <el-table-column prop="radio" label="达标率" align="center">
                  <template slot-scope="scope">
                    <span>{{ scope.row.radio }}%</span>
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
                <el-table-column prop="ly" label="来源" align="center">
                  <template>
                    <span>系统提取</span>
                  </template>
                </el-table-column>
                <el-table-column prop="status" label="指标状态" align="center">
                  <template slot-scope="scope">
                    <div class="status-box" :class="`${scope.row.status == '质控中' ? 'status-icon-3' : scope.row.status == '成功' ? 'status-icon-2' : 'status-icon-1'}`">
                      <span class="status-icon"></span>
                      <span>{{ scope.row.status }}</span>
                    </div>
                  </template>
                </el-table-column>
                <el-table-column prop="update_time" label="更新时间" align="center"></el-table-column>
              </el-table>
            </div>
          </CustomCardBox>
        </div>
      </div>
    </div>

    <!-- 指标计算 弹窗 -->
    <div class="js-pop-box" v-if="jsPopShow">
      <div class="js-pop-title-box">
        <span class="span-l">指标计算</span>
        <span class="span-c" @click="jsPopShowOff">X</span>
      </div>
      <div class="js-pop-centent-box">
        <el-form :inline="true" :model="searchForm" class="demo-form-inline">
          <el-form-item label="出院日期:" style="margin-bottom: 16px; margin-right: 0">
            <el-date-picker v-model="searchForm.startTime" type="date" format="yyyy年MM月dd日" value-format="yyyyMMdd" placeholder="开始日期" style="width: 170px"></el-date-picker>
            <el-date-picker
              v-model="searchForm.endTime"
              type="date"
              style="margin-left: 10px; width: 170px; margin-right: 0"
              format="yyyy年MM月dd日"
              value-format="yyyyMMdd"
              placeholder="结束日期"
            ></el-date-picker>
          </el-form-item>
        </el-form>
      </div>
      <div class="js-pop-foo-box">
        <el-button type="primary" @click="reComputed">重新计算指标</el-button>
      </div>
    </div>
  </div>
</template>
 
<script>
//组件
import DateRangePicker from '@/components/DateRangePicker';
import SearchInput from '@/components/SearchInput';
import CustomCardBox from '../comp/CustomCardBox';
import CardTitle from '@/components/CardTitle';

import moment from 'moment';
import { majorIndexExport } from '@/api/excel';

export default {
  components: { DateRangePicker, CustomCardBox, SearchInput, CardTitle },
  data() {
    return {
      asideScrollTop: 0, // 存储右侧容器滚动位置
      lastClickTableRow: null, // 存储最后点击的表格行数据
      currentNodeId: '', // 用于存储当前选中的节点ID
      searchForm: {
        startTime: moment().startOf('year').format('YYYYMMDD'),
        endTime: moment().format('YYYYMMDD'),
        AAC11N: '',
        AEE03: '',
      },
      defaultProps: {
        children: 'children',
        label: 'name',
      },
      jsPopShow: false,
      indexData: {
        name: '',
        fenzi: '',
        fenmu: '',
      },
      apiType: '0',
      filterText: '',
      departmentList: [],
      AEE03Options: [],
      treeData: [],
      tableData: [],
      currentTreeNode: {},
    };
  },
  watch: {
    filterText(val) {
      this.$refs.treeRef.filter(val);
    },
  },

  computed: {
    fenziList() {
      return this.indexData.fenzi ? JSON.parse(this.indexData.fenzi) : [];
    },
    fenmuList() {
      return this.indexData.fenmu ? JSON.parse(this.indexData.fenmu) : [];
    },
  },
  activated() {
    const savedNodeId = localStorage.getItem('lastSelectedTreeNodeId');
    console.log('从 localStorage 加载的节点ID:', savedNodeId);
    if (savedNodeId) {
      this.currentNodeId = savedNodeId;
    }
    this.getTreeData();

    // 恢复右侧容器滚动位置
    this.$nextTick(() => {
      this.restoreAsideScroll();
    });
  },

  beforeRouteLeave(to, from, next) {
    if (this.currentNodeId) {
      localStorage.setItem('lastSelectedTreeNodeId', this.currentNodeId);
      console.log('已保存选中节点ID:', this.currentNodeId);
    }
    next();
  },
  async mounted() {
    localStorage.setItem('lastSelectedTreeNodeId', '');
    this.getDepartmentList();
    this.getResponsiblePhysician();
    await this.getTreeData();
  },
  methods: {
    toPage(row, status) {
      // 记录最后点击的行数据
      this.lastClickTableRow = row;
      // 记录右侧容器滚动位置
      this.asideScrollTop = this.$refs.asideRightRef.scrollTop;

      // 存储到localStorage
      localStorage.setItem('asideScrollTop', this.asideScrollTop);
      localStorage.setItem('lastClickTableRow', JSON.stringify(row));

      let startTime = '';
      let endTime = '';
      if (row.month) {
        var month = Number(row.month) + 1 <= 10 ? '0' + Number(row.month) : Number(row.month);
        let num = this.getDaysInMonth(row.year, month);
        startTime = row.year + '' + month + '01';
        endTime = row.year + '' + month + '' + num;
      } else {
        if (this.searchForm.startTime && this.searchForm.endTime) {
          startTime = this.searchForm.startTime;
          endTime = this.searchForm.endTime;
        } else {
          let date = new Date();
          let yy = date.getFullYear();
          let mm = date.getMonth() + 1;
          let dd = date.getDate();
          mm = mm > 9 ? mm : '0' + mm;
          dd = dd > 9 ? dd : '0' + dd;
          startTime = yy + '0101';
          endTime = `${yy}${mm}${dd}`;
        }
      }
      localStorage.setItem('majorIndexData', JSON.stringify(this.indexData));
      this.$router.push({
        path: '/majorIndexDetail',
        query: { startTime, endTime, year: row.year, month, AAC11N: this.searchForm.AAC11N, AEE03: this.searchForm.AEE03, status, catalog: this.indexData.url },
      });
    },

    handleAsideScroll() {
      // 实时更新右侧容器滚动位置
      this.asideScrollTop = this.$refs.asideRightRef.scrollTop;
      localStorage.setItem('asideScrollTop', this.asideScrollTop);
    },

    getDaysInMonth(year, month) {
      month = parseInt(month, 10);
      var temp = new Date(year, month, 0);
      return temp.getDate();
    },
    getList() {
      if (this.apiType === '0') {
        this.$axios2
          .get(
            `/quality_index_list?type=1&cysj_start=${this.searchForm.startTime}&cysj_end=${this.searchForm.endTime}&AAC11N=${this.searchForm.AAC11N}&AEE03=${this.searchForm.AEE03}&is_export=0&category=${this.indexData.url}`,
          )
          .then(res => {
            this.tableData = Array.isArray(res.data) ? res.data : [];
            // 数据加载完成后恢复滚动位置
            this.$nextTick(() => {
              this.restoreAsideScroll();
            });
          });
      } else {
        this.$axios2
          .get(
            `/catalog_get_custom_data?index_name=${this.nodeName}&type=1&cysj_start=${this.searchForm.startTime}&cysj_end=${this.searchForm.endTime}&AAC11N=${this.searchForm.AAC11N}&AEE03=${this.searchForm.AEE03}&is_export=0&category=${this.indexData.url}`,
          )
          .then(res => {
            this.tableData = Array.isArray(res.data) ? res.data : [];
            // 数据加载完成后恢复滚动位置
            this.$nextTick(() => {
              this.restoreAsideScroll();
            });
          });
      }
    },

    onExport() {
      const params = {
        cysj_start: this.searchForm.startTime,
        cysj_end: this.searchForm.endTime,
        category: this.indexData.url,
        AAC11N: this.searchForm.AAC11N,
        AEE03: this.searchForm.AEE03,
        is_export: 1,
        apiType: this.apiType,
        nodeName: this.nodeName,
      };
      majorIndexExport(params).then(res => {
        const content = res.data; // 后台返回二进制数据
        const blob = new Blob([content]);
        const fileName = `${this.indexData.name}.csv`;
        if ('download' in document.createElement('a')) {
          // 非IE下载
          const elink = document.createElement('a');
          elink.download = fileName;
          elink.style.display = 'none';
          elink.href = URL.createObjectURL(blob);
          document.body.appendChild(elink);
          elink.click();
          URL.revokeObjectURL(elink.href); // 释放URL 对象
          document.body.removeChild(elink);
        } else {
          // IE10+下载
          navigator.msSaveBlob(blob, fileName);
        }
      });
    },
    getDepartmentList() {
      this.$axios.post('/selectInfo').then(res => {
        this.departmentList = res.data.department.slice(1, res.data.department.length);
      });
    },
    clickJsTitleBtn() {
      this.jsPopShow = !this.jsPopShow;
    },
    jsPopShowOff() {
      this.jsPopShow = false;
    },

    restoreAsideScroll() {
      if (!this.$refs.asideRightRef) return;

      // 优先从localStorage获取滚动位置
      const savedScrollTop = localStorage.getItem('asideScrollTop');
      const savedTableRow = localStorage.getItem('lastClickTableRow');

      if (savedScrollTop) {
        // 直接恢复滚动位置
        this.$refs.asideRightRef.scrollTop = Number(savedScrollTop);
      }

      // 如果有保存的行数据，滚动到对应行（更精准）
      if (savedTableRow && this.tableData.length) {
        const row = JSON.parse(savedTableRow);
        // 找到对应行的DOM元素
        const rowEl = this.$el.querySelector(`.el-table__row[data-row-key="${row.year + (row.month ? '-' + row.month : '')}"]`);
        if (rowEl) {
          // 计算行元素相对于右侧容器的位置并滚动
          const container = this.$refs.asideRightRef;
          const rect = rowEl.getBoundingClientRect();
          const containerRect = container.getBoundingClientRect();
          container.scrollTop += rect.top - containerRect.top - container.clientHeight / 2;

          // 平滑滚动
          container.style.scrollBehavior = 'smooth';
          setTimeout(() => {
            container.style.scrollBehavior = 'auto';
          }, 500);
        }
      }
    },
    reComputed() {
      const params = {
        category: this.indexData.url,
        cysj_start: this.searchForm.startTime,
        cysj_end: this.searchForm.endTime,
      };
      this.$axios2
        .post('/quality_index_recalculate', params, {
          headers: { isNoLoading: true },
        })
        .then(res => {
          if (res.code == 200) {
            //更改为发起请求后2秒刷新数据
          }
        });
      let timer = setTimeout(() => {
        clearTimeout(timer);
        this.jsPopShow = false;
        this.getList();
      }, 2000);
    },

    handleSortChange() {},

    // 获取责任医师
    getResponsiblePhysician() {},
    handleSearchChange(val) {
      this.filterText = val;
    },
    async funQuery() {
      try {
        await this.getList();
      } catch (error) {
      } finally {
        this.$loading().close();
      }
    },

    filterNode(value, data) {
      if (!value) return true;
      return data.name.indexOf(value) !== -1;
    },
    handleNodeClick(data) {
      this.nodeName = data.index_name;
      const { children, fenzi, fenmu } = data;
      if (!children || children.length === 0) {
        if (fenzi.length && fenmu.length) {
          this.currentTreeNode = data;
          this.apiType = data.is_rg;
          this.currentNodeId = data.id; // 更新当前选中的节点ID
          this.indexData = data;
          this.$refs.treeRef.setCurrentKey(data.id);
          this.apiType = data.is_rg;
          this.$nextTick(() => {
            this.getList();
          });
        }
      }
      this.$refs.treeRef.setCurrentKey(this.indexData.id);
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
      if (this.treeData && this.treeData.length > 0) {
        this.$nextTick(() => {
          this.restoreTreeState();
        });
        return Promise.resolve();
      }
      this.$axios2.get('/catalog_lists').then(res => {
        if (Array.isArray(res.data)) {
          const array = res.data[0]?.children || [];
          this.treeData = array;
          this.$nextTick(() => {
            this.$refs.treeRef.setCurrentKey(this.defaultOpenMenu(array));
            const defaultNode = this.$refs.treeRef.getCurrentNode();
            if (defaultNode) {
              this.currentTreeNode = defaultNode;
            }
            this.getList();
            this.restoreTreeState();
          });
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

    restoreTreeState() {
      if (this.currentNodeId) {
        this.$refs.treeRef.setCurrentKey(this.currentNodeId);

        this.$nextTick(() => {
          this.scrollToSelectedNode();
        });

        const node = this.findNode(this.treeData, this.currentNodeId);
        if (node && (!node.children || node.children.length === 0)) {
          this.indexData = node;
          this.getList();
        }
      } else {
        const defaultId = this.defaultOpenMenu(this.treeData);
        this.$refs.treeRef.setCurrentKey(defaultId);
        this.$nextTick(() => {
          this.scrollToSelectedNode();
        });
        this.getList();
      }
    },

    findNode(nodes, id) {
      for (const node of nodes) {
        if (node.id === id) {
          return node;
        }
        if (node.children && node.children.length > 0) {
          const found = this.findNode(node.children, id);
          if (found) {
            return found;
          }
        }
      }
      return null;
    },
    scrollToSelectedNode() {
      const scrollContainer = this.$el.querySelector('.custom-card-box');
      if (!scrollContainer) {
        console.warn('滚动容器 .custom-card-box 未找到');
        return;
      }
      const nodeEl = scrollContainer.querySelector('.el-tree-node.is-current');

      if (!nodeEl) {
        console.warn('未找到当前选中的树节点 DOM (.el-tree-node.is-current)');
        return;
      }

      console.log('找到选中节点DOM:', nodeEl);

      // 计算并执行滚动
      const containerHeight = scrollContainer.clientHeight;
      const nodeHeight = nodeEl.offsetHeight;
      const nodeOffsetTop = nodeEl.offsetTop;

      // 让节点垂直居中
      scrollContainer.scrollTop = nodeOffsetTop - containerHeight / 2 + nodeHeight / 2;

      // 平滑滚动
      scrollContainer.style.scrollBehavior = 'smooth';
      setTimeout(() => {
        scrollContainer.style.scrollBehavior = 'auto';
      }, 500);
    },
    reset() {
      this.searchForm = {
        startTime: moment().startOf('year').format('YYYYMMDD'),
        endTime: moment().format('YYYYMMDD'),
        AAC11N: '',
        AEE03: '',
      };
    },
  },
};
</script>
 
<style lang="scss" scoped>
@import '~@/styles/common.scss';

.app-container {
  padding: 0 18px;
  overflow: hidden;
  height: 100vh;
  background-color: rgba(255, 255, 255, 1);
  .search-box {
    margin-top: 20px;
    margin-bottom: 10px;
    height: 80px;
  }
  .content {
    padding: 0 20px;
    width: 100%;
    height: calc(100vh - 90px);
    display: flex;
    gap: 20px;

    .aside-left {
      flex: 2;
      height: 100%;

      .aside-left-search {
        width: 90%;
        align-items: center;
        margin: 0 auto;
        margin-top: 20px;
        .filter-tree {
          margin-top: 16px;

          ::v-deep .el-tree-node__content {
            height: 36px;
            line-height: 36px;
            .el-tree-node__expand-icon {
              padding: 12px 6px;
            }
          }
        }
      }
    }

    .aside-right {
      flex: 4;
      height: 100%;
      overflow-y: scroll;
      padding: 1px 1px;
      height: calc(100vh - 80px);

      .aside-right-item1 {
        margin-bottom: 10px;

        .aside-right-title {
          margin: 20px 0;
        }
      }

      .aside-right-item2 {
        .aside-right-table {
          margin: 0 20px;

          .danger-red {
            color: #f5222d;
            font-weight: bold;
          }
          .blue {
            color: #567722;
            font-weight: bold;
          }
        }
      }
    }
  }
}

.status-box.status-icon-1 {
  & span {
    color: #b1b1b1;
  }

  .status-icon {
    background: #b1b1b1;
  }
}

.status-box.status-icon-2 {
  & span {
    color: #2b8f53;
  }

  .status-icon {
    background: #2b8f53;
  }
}

.status-box.status-icon-3 {
  & span {
    color: #ff0000;
  }

  .status-icon {
    background: #ff0000;
  }
}

.title-btn {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 10px;

  .span-btn {
    background: #2b8f53;
    font-size: 12px;
    color: #fff;
    padding: 6px 12px;
    border-radius: 4px;
    text-align: center;
    cursor: pointer;
  }
}

.js-pop-box {
  position: fixed;
  top: 40%;
  left: 50%;
  transform: translate(-50%, -50%);
  z-index: 999;
  width: 400px;
  height: auto;
  border-radius: 4px;
  background: #fff;
  border: 1px solid #f2f2f2;
  box-shadow: 0 1px 4px rgba(0, 21, 41, 0.08);
  overflow: hidden;

  .js-pop-title-box {
    width: 100%;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 6px 16px;
    padding-right: 6px;
    background: #17509a;
    font-size: 12px;
    color: #fff;

    .span-l {
      font-weight: 100;
      font-size: 12px;
      line-height: normal;
    }

    .span-c {
      display: inline-block;
      height: 100%;
      padding: 0 10px;
      cursor: pointer;
    }
  }

  .js-pop-centent-box {
    width: 100%;
    padding: 10px 16px;

    &::v-deep .el-input__inner {
      height: 35px;
      line-height: 35px;
    }
  }

  .js-pop-foo-box {
    width: 100%;
    padding: 0 16px 10px 16px;
    display: flex;
    align-items: center;
    justify-content: flex-end;

    &::v-deep .el-button {
      height: 35px;
      line-height: normal;
      padding: 0 20px;
    }
  }
}

.mb16 {
  margin-bottom: 16px;
}
.pl16 {
  padding-left: 16px;
}
.mt10 {
  margin-top: 10px;
}
.ml16 {
  margin-left: 16px;
}
.span-sol-btn {
  background: #ecf5ff;
  font-size: 12px;
  color: #409eff;
  padding: 6px 12px;
  border-radius: 4px;
  text-align: center;
  font-weight: bold;
  display: inline-block;
  font-family: PingFangSC-bold;
  cursor: pointer;
  transition: all 0.2s ease-in-out;
  border: 1px solid #c6e2ff;
}

.span-sol-btn:hover {
  background: #409eff;
  color: #fff;
  box-shadow: 0 2px 4px rgba(147, 210, 243, 0.5);
  border: 1px solid #409eff;
}
</style>