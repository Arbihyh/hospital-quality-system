<template>
  <div class="box">
    <div class="box_left">
      <!-- <div class="box_card mb16">
        <el-form :inline="true" :model="mForm" class="demo-form-inline">
          <el-form-item label="出院时间" style="margin-bottom: 16px; margin-right: 0;">
            <el-date-picker
              v-model="mForm.startTime"
              type="date"
              format="yyyy年MM月dd日"
              value-format="yyyyMMdd"
              placeholder="开始日期"
              style="width: 170px;">
            </el-date-picker>
            <el-date-picker
              v-model="mForm.endTime"
              type="date"
              style="margin-left: 10px; width: 170px; margin-right: 0;"
              format="yyyy年MM月dd日"
              value-format="yyyyMMdd"
              placeholder="结束日期">
              </el-date-picker>
          </el-form-item>
        </el-form>
        <div class="text-right">
          <el-button type="primary" plain @click="alertText">导出全部指标</el-button>
          <el-button type="primary" plain @click="alertText">导出全部详情</el-button>
        </div>
      </div> -->
      <div class="box_card tree-box">
        <el-input placeholder="输入关键字进行过滤" v-model="filterText"></el-input>
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
    </div>
    <div class="box_right">
      <div class="box_card mb16">
        <el-form :inline="true" :model="formInline" class="demo-form-inline">
          <el-form-item label="">
            <el-dropdown>
              <el-button :class="formInline.year.name ? 'color-btn' : ''">
                {{ formInline.year.name || '按年' }}
                <i class="el-icon-arrow-down el-icon--right"></i>
              </el-button>
              <el-dropdown-menu slot="dropdown">
                <el-dropdown-item v-for="(item, index) in yearList" :key="index" @click.native="funSeleterYear(item)">{{ item.name }}</el-dropdown-item>
              </el-dropdown-menu>
            </el-dropdown>

            <el-dropdown>
              <el-button :disabled="formInline.year.name == ''" :class="formInline.quarter.name ? 'color-btn' : ''">
                {{ formInline.quarter.name || '按季' }}
                <i class="el-icon-arrow-down el-icon--right"></i>
              </el-button>
              <el-dropdown-menu slot="dropdown">
                <el-dropdown-item v-for="(item, index) in quarterList" :key="index" @click.native="funSeleterQuarter(item)">{{ item.name }}</el-dropdown-item>
              </el-dropdown-menu>
            </el-dropdown>
          </el-form-item>
          <el-form-item label="">
            <el-date-picker v-model="formInline.startTime" style="width: 150px" type="month" format="yyyy 年 MM 月" value-format="yyyyMMdd" placeholder="开始日期"></el-date-picker>

            <el-date-picker
              v-model="formInline.endTime"
              type="month"
              style="margin-left: 10px; width: 150px"
              format="yyyy 年 MM 月"
              value-format="yyyyMMdd"
              placeholder="结束日期"
            ></el-date-picker>
          </el-form-item>
          <el-form-item label="科室:" style="margin-bottom: 0">
            <el-select v-model="formInline.AAC11N" clearable filterable placeholder="请选择">
              <el-option v-for="(item, index) in departmentList" :key="index" :label="item.name" :value="item.name"></el-option>
            </el-select>
          </el-form-item>
          <el-form-item label="医师:" style="margin-bottom: 0">
            <el-select v-model="formInline.AEE03" clearable filterable placeholder="请选择">
              <el-option v-for="(item, index) in staffList" :key="index" :label="item.name" :value="item.name"></el-option>
            </el-select>
          </el-form-item>

          <el-form-item>
            <el-button type="primary" @click="getList">查询</el-button>
            <el-button @click="getResult">重置条件</el-button>
          </el-form-item>
        </el-form>
      </div>
      <div class="box_card mb16">
        <CardTitle :title="indexData.name">
          <div class="title-js-btn-box">
            <span class="span-btn" @click="clickJsTitleBtn">{{ jsText }}</span>
            <div class="js-pop-box" v-if="jsPopShow">
              <div class="js-pop-title-box">
                <span class="span-l">指标计算</span>
                <span class="span-c" @click="jsPopShowOff">X</span>
              </div>
              <div class="js-pop-centent-box">
                <el-form :inline="true" :model="formInline" class="demo-form-inline">
                  <el-form-item label="出院时间:" style="margin-bottom: 16px; margin-right: 0">
                    <el-date-picker
                      v-model="formInline.startTime"
                      type="date"
                      format="yyyy年MM月dd日"
                      value-format="yyyyMMdd"
                      placeholder="开始日期"
                      style="width: 170px"
                    ></el-date-picker>
                    <el-date-picker
                      v-model="formInline.endTime"
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
        </CardTitle>
        <div class="mb16">
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
            <el-button slot="reference" plain size="mini" type="primary" class="ml16">计算口径</el-button>
          </el-popover>
        </div>
        <div class="mb16">
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
            <el-button slot="reference" plain size="mini" type="primary" class="ml16">计算口径</el-button>
          </el-popover>
        </div>
      </div>
      <div class="box_card table-box">
        <div class="table-title-box mb16">
          <div class="table-title-l">
            <span>统计结果</span>
            <el-select v-model="formInline.dimensionality" clearable filterable placeholder="请选择">
              <el-option label="指标维度" value="指标维度"></el-option>
              <el-option label="科室维度" value="科室维度"></el-option>
              <el-option label="医师维度" value="医师维度"></el-option>
            </el-select>
          </div>
          <el-button type="primary" plain @click="onExport">导出数据</el-button>
        </div>
        <el-table :data="tableData" style="width: 100%" :key="apiType">
          <el-table-column prop="date" label="日期">
            <template slot-scope="scope">
              <span v-if="scope.row.month">{{ scope.row.year }}-{{ scope.row.month < 10 ? `0${scope.row.month}` : scope.row.month }}</span>
              <span v-else>{{ scope.row.year }}</span>
            </template>
          </el-table-column>
          <el-table-column prop="" label="指标率">
            <template slot-scope="scope">
              <span>{{ scope.row.radio }}%</span>
            </template>
          </el-table-column>
          <el-table-column prop="fenzi" label="分子">
            <template slot="header">
              <span>分子</span>
              <i class="el-icon-edit table_edit pointer" v-show="apiType === '1'" @click="onChangeValue(null, 'fenzi')"></i>
            </template>
            <template slot-scope="scope">
              <div>
                <span v-if="apiType === '1'">{{ scope.row.fenzi }}</span>
                <span v-else class="link" @click="toPage(scope.row, 1)">{{ scope.row.fenzi }}</span>
                <i
                  class="el-icon-edit table_edit pointer"
                  style="margin-left: 10px"
                  @click="onChangeValue(scope.row, 'fenzi')"
                  v-show="apiType === '1' && scope.row.year != '平均值'"
                ></i>
              </div>
            </template>
          </el-table-column>
          <el-table-column prop="fenmu" label="分母">
            <template slot="header">
              <span>分母</span>
              <i class="el-icon-edit table_edit pointer" v-show="apiType === '1'" @click="onChangeValue(null, 'fenmu')"></i>
            </template>
            <template slot-scope="scope">
              <div>
                <span v-if="apiType === '1'">{{ scope.row.fenmu }}</span>
                <span v-else class="link" @click="toPage(scope.row, 0)">{{ scope.row.fenmu }}</span>
                <i
                  class="el-icon-edit table_edit pointer"
                  style="margin-left: 10px"
                  @click="onChangeValue(scope.row, 'fenmu')"
                  v-show="apiType === '1' && scope.row.year != '平均值'"
                ></i>
              </div>
            </template>
          </el-table-column>
          <el-table-column label="来源">
            <template>
              <!-- 接口没有来源字段 -->
              <span>系统提取</span>
            </template>
          </el-table-column>
          <el-table-column prop="status" label="指标状态">
            <template slot-scope="scope">
              <div class="status-box" :class="`${scope.row.status == '进行中' ? 'status-icon-1' : scope.row.status == '成功' ? 'status-icon-2' : 'status-icon-3'}`">
                <span class="status-icon"></span>
                <span>{{ scope.row.status }}</span>
              </div>
            </template>
          </el-table-column>
          <el-table-column prop="update_time" label="更新时间">
            <template slot-scope="scope">
              <span>{{ scope.row.update_time }}</span>
            </template>
          </el-table-column>
        </el-table>
      </div>
    </div>
    <ChangeCaseIndexValueDialogVue v-if="dialogData.bSwitch" :index-name="nodeName" :data="dialogData" @refresh="getList" />
  </div>
</template>

<script>
import { majorIndexExport } from '@/api/excel';
import { Number } from 'core-js';
import ChangeCaseIndexValueDialogVue from './components/ChangeCaseIndexValueSelfDialog.vue';
export default {
  components: { ChangeCaseIndexValueDialogVue },
  data() {
    return {
      currentNodeId: '', // 用于存储当前选中的节点ID
      dialogData: {
        bSwitch: false,
        type: 'fenzi',
        rows: [],
      },
      mForm: {
        startTime: '',
        endTime: '',
      },
      filterText: '',
      defaultProps: {
        children: 'children',
        label: 'name',
      },
      indexData: {
        name: '',
        fenzi: '',
        fenmu: '',
      },
      treeData: [],
      // 右侧
      formInline: {
        year: {
          name: '',
        },
        month: {
          name: '',
        },
        quarter: {
          name: '',
        },
        startTime: '',
        endTime: '',
        AAC11N: '',
        AEE03: '',
        dimensionality: '指标维度',
        type: '1',
      },
      departmentList: [], // 科室
      staffList: [], // 主治医师
      jsText: '指标计算',
      jsPopShow: false,
      // pickerOptions: {
      //   disabledDate(time) {
      //     const date = new Date();
      //     const year = date.getFullYear();
      //     const timeYear = time.getFullYear();
      //     return year < timeYear;
      //   },
      // },
      tableData: [],
      quarterList: [],
      monthList: [],
      yearList: [],
      apiType: '0',
      nodeName: '',
    };
  },
  computed: {
    fenziList() {
      return this.indexData.fenzi ? JSON.parse(this.indexData.fenzi) : [];
    },
    fenmuList() {
      return this.indexData.fenmu ? JSON.parse(this.indexData.fenmu) : [];
    },
  },
  watch: {
    filterText(val) {
      this.$refs.treeRef.filter(val);
    },
  },
  activated() {
    const savedNodeId = localStorage.getItem('lastSelectedTreeNodeId');
    console.log('从 localStorage 加载的节点ID:', savedNodeId);
    if (savedNodeId) {
      this.currentNodeId = savedNodeId;
    }
    this.getTreeData();
  },

  beforeRouteLeave(to, from, next) {
    if (this.currentNodeId) {
      localStorage.setItem('lastSelectedTreeNodeId', this.currentNodeId);
      console.log('已保存选中节点ID:', this.currentNodeId);
    }
    next();
  },
  async created() {
    // this.formInline.year = new Date().getFullYear().toString();
    // 默认获取当前本年日期
    localStorage.setItem('lastSelectedTreeNodeId', '');
    this.getdaTe();
    this.getDepartmentList();
    this.getstaffList();
    this.selectInfo();
    await this.getTreeData();
  },
  methods: {
    onChangeValue(row, type) {
      console.log('onChangeValue', row, type);
      if (row) {
        // 单个修改
        const obj = {
          month: row.month,
          year: row.year,
          fenzi: row.fenzi,
          fenmu: row.fenmu,
          num: type == 'fenzi' ? row.fenzi : row.fenmu,
        };
        // 先清空在赋值，防止重复
        this.$set(this.dialogData, 'rows', []);
        this.dialogData.rows.push(obj);
      } else {
        // 批量修改
        // 先清空在赋值，防止重复
        this.$set(this.dialogData, 'rows', []);
        this.tableData.map(item => {
          if (item.year != '平均值') {
            const obj = {
              month: item.month,
              year: item.year,
              fenzi: item.fenzi,
              fenmu: item.fenmu,
              num: type == 'fenzi' ? item.fenzi : item.fenmu,
            };
            this.dialogData.rows.push(obj);
          }
        });
      }
      this.dialogData.bSwitch = true;
      this.dialogData.type = type;
    },
    // 获取默认当前日期
    getdaTe() {
      let date = new Date();
      let yy = date.getFullYear();
      let mm = date.getMonth() + 1;
      let dd = date.getDate();
      mm = mm > 9 ? mm : '0' + mm;
      dd = dd > 9 ? dd : '0' + dd;
      this.formInline.startTime = yy + '0101';
      this.formInline.endTime = `${yy}${mm}${dd}`;
      // this.storageSet('endTime', this.formInline.endTime || '');
      // this.storageSet('startTime', this.formInline.startTime || '');
    },
    selectInfo() {
      // let pramse = {};
      this.$axios.post('/selectInfo').then(res => {
        this.quarterList = res.data.quarter;
        // 季度
        this.monthList = res.data.month;
        //月
        this.yearList = res.data.year;
      });
    },
    funSeleterYear(val) {
      console.log(val);
      this.formInline.year = val;
      this.formInline.type = '1';
      this.formInline.endTime = this.goTimeTwe(val.end);
      this.formInline.startTime = this.goTimeTwe(val.start);
    },
    // 点击月份下下拉框
    funSeleterMonth(val) {
      this.formInline.month = val;
      this.formInline.type = '3';
      this.formInline.endTime = this.formInline.year.name + val.end;
      this.formInline.startTime = this.formInline.year.name + val.start;
    },
    // 点击按季度下拉框
    funSeleterQuarter(val) {
      this.formInline.type = '2';
      this.formInline.quarter = val;
      this.formInline.endTime = this.formInline.year.name + this.zh(val.end);
      this.formInline.startTime = this.formInline.year.name + this.zh(val.start);
    },
    zh(str) {
      let arr = str.split('-');
      return arr.join('');
    },
    // 获取部门集合
    getDepartmentList() {
      this.$axios.post('/get_department_list').then(res => {
        this.departmentList = res.data;
      });
    },
    // 点击重置条件
    getResult() {
      this.formInline.year.name = '';
      this.formInline.month.name = '';
      this.formInline.type = '1';
      this.formInline.quarter.name = '';
      this.formInline.startTime = '';
      this.formInline.endTime = '';
      this.formInline.AAC11N = '';
      this.formInline.AEE03 = '';
      this.selectInfo();
      this.funQuery();
    },
    getstaffList() {
      this.$axios2.get('/get_staff?ygjb=主治医师').then(res => {
        this.staffList = res.data;
      });
    },
    clickJsTitleBtn() {
      this.jsPopShow = !this.jsPopShow;
    },
    jsPopShowOff() {
      this.jsPopShow = false;
    },
    renderContent(h, { node }) {
      return h(
        'span',
        {
          // 自定义class类名
          class: 'custom-node',
          // 设置样式(样式权限仅次于 !important，可能会覆盖掉原有定义的其他选择器样式)
          style: {
            fontWeight: node.isLeaf ? 'normal' : 'bold',
            color: node.data?.is_rg === '1' ? '#67C23A' : 'inherit',
          },
        },
        node.label,
      );
    },
    getDaysInMonth(year, month) {
      month = parseInt(month, 10);
      var temp = new Date(year, month, 0);
      return temp.getDate();
    },
    toPage(row, status) {
      let startTime = '';
      let endTime = '';
      if (row.month) {
        var month = Number(row.month) + 1 <= 10 ? '0' + Number(row.month) : Number(row.month);
        let num = this.getDaysInMonth(row.year, month);
        startTime = row.year +''+ month + '01';
        endTime = row.year +''+ month +''+ num;
      } else {
        if (this.formInline.startTime && this.formInline.endTime) {
          startTime = this.formInline.startTime;
          endTime = this.formInline.endTime;
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
        query: { startTime, endTime, year: row.year, month, AAC11N: this.formInline.AAC11N, AEE03: this.formInline.AEE03, status, catalog: this.indexData.url },
      });
    },
    alertText() {
      this.$message.info('接口没有，待开放');
    },
    // defaultOpenMenu
    defaultOpenMenu(list) {
      if (list.length && list[0].children && list[0].children.length) {
        if (list[0].children[0].children && list[0].children[0].children.length) {
          this.indexData = list[0].children[0].children[0];
          return list[0].children[0].children[0].id;
        } else {
          this.indexData = list[0].children[0];
          return list[0].children[0].id;
        }
      } else {
        this.indexData = list[0];
        return list[0].id;
      }
    },
    // getTreeData() {
    //   this.$axios2.get('/catalog_lists').then(res => {
    //     if(Array.isArray(res.data)) {
    //       this.treeData = res.data
    //       this.$nextTick(() => {
    //         this.$refs.treeRef.setCurrentKey(this.defaultOpenMenu(res.data))
    //         this.getList()
    //       })
    //     }
    //   });
    // },

    getTreeData() {
      if (this.treeData && this.treeData.length > 0) {
        this.$nextTick(() => {
          this.restoreTreeState();
        });
        return Promise.resolve();
      }

      return this.$axios2.get('/catalog_lists').then(res => {
        if (Array.isArray(res.data)) {
          this.treeData = res.data;
          this.$nextTick(() => {
            this.restoreTreeState();
          });
        }
      });
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

    scrollToSelectedNode() {
      const scrollContainer = this.$el.querySelector('.tree-box');
      if (!scrollContainer) {
        console.warn('滚动容器 .tree-box 未找到');
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
    // 菜单筛选
    filterNode(value, data) {
      if (!value) return true;
      return data.name.indexOf(value) !== -1;
    },
    handleNodeClick(data) {
      console.log('handleNodeClick', data);
      this.nodeName = data.index_name;
      const { children, fenzi, fenmu } = data;
      if (!children || children.length === 0) {
        if (fenzi.length && fenmu.length) {
          this.indexData = data;
          this.currentNodeId = data.id; // 更新当前选中的节点ID
          this.apiType = data.is_rg;
          this.$nextTick(() => {
            this.getList();
          });
        }
      }
      this.$refs.treeRef.setCurrentKey(this.indexData.id);
    },
    reComputed() {
      const params = {
        category: this.indexData.url,
        cysj_start: this.formInline.startTime,
        cysj_end: this.formInline.endTime,
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
    getList() {
      if (this.apiType === '0') {
        this.$axios2
          .get(
            `/quality_index_list?type=1&cysj_start=${this.formInline.startTime}&cysj_end=${this.formInline.endTime}&AAC11N=${this.formInline.AAC11N}&AEE03=${this.formInline.AEE03}&is_export=0&category=${this.indexData.url}`,
          )
          .then(res => {
            this.tableData = Array.isArray(res.data) ? res.data : [];
          });
      } else {
        this.$axios2
          .get(
            `/catalog_get_custom_data?index_name=${this.nodeName}&type=1&cysj_start=${this.formInline.startTime}&cysj_end=${this.formInline.endTime}&AAC11N=${this.formInline.AAC11N}&AEE03=${this.formInline.AEE03}&is_export=0&category=${this.indexData.url}`,
          )
          .then(res => {
            this.tableData = Array.isArray(res.data) ? res.data : [];
          });
      }
    },
    onExport() {
      // const params = {
      //   cysj_start: this.formInline.startTime,
      //   cysj_end: this.formInline.endTime,
      //   category: this.indexData.url,
      //   AAC11N: this.formInline.AAC11N,
      //   AEE03: this.formInline.AEE03,
      //   is_export: 1,
      // };
      const params = {
        cysj_start: this.formInline.startTime,
        cysj_end: this.formInline.endTime,
        category: this.indexData.url,
        AAC11N: this.formInline.AAC11N,
        AEE03: this.formInline.AEE03,
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
  },
};
</script>

<style lang="scss" scoped>
.box {
  padding: 0 16px 16px;
  overflow: hidden;
  height: 100vh;

  .link {
    color: #409eff;
    cursor: pointer;
  }

  .mb16 {
    margin-bottom: 16px;
  }

  .ml16 {
    margin-left: 16px;
  }

  .box_card {
    background: #fff;
    padding: 16px;
    border-radius: 4px;
  }

  .text-right {
    text-align: right;
  }

  .box_left {
    width: 460px;
    float: left;
    height: 100%;
    box-sizing: border-box;

    .tree-box {
      height: 100%;
      overflow-y: auto;
      box-sizing: border-box;
    }
  }

  .box_right {
    width: calc(100% - 476px);
    float: right;
    height: calc(100%);
    box-sizing: border-box;
    overflow-y: auto;

    .table-box {
      // height: calc(100%);
    }

    &::v-deep .el-form-item {
      margin-right: 10px !important;
    }

    &::v-deep .el-input__inner {
      height: 35px;
      line-height: 35px;
    }

    &::v-deep .el-button {
      padding: 9px 20px;
    }
  }
}

.table-title-box {
  width: 100%;
  display: flex;
  align-items: center;
  justify-content: space-between;

  & .table-title-l {
    display: flex;
    align-items: center;

    & > span {
      font-size: 14px;
      font-weight: bold;
      padding-right: 10px;
    }

    &::v-deep .el-input__inner {
      height: 35px;
      line-height: 35px;
    }

    &::v-deep .el-input__icon {
      line-height: 35px;
    }
  }
}

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

::v-deep .el-tree-node {
  white-space: normal;
  outline: 0;

  .el-tree-node__content {
    text-align: left;
    align-items: start;
    margin: 4px;
    height: 100%;
  }
}

.title-js-btn-box {
  display: inline-block;
  position: relative;

  .span-btn {
    display: inline-block;
    background: #2b8f53;
    font-size: 12px;
    color: #fff;
    padding: 6px 12px;
    border-radius: 4px;
    text-align: center;
    cursor: pointer;
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
}

.status-box {
  display: flex;
  align-items: center;

  .status-icon {
    display: inline-block;
    width: 6px;
    height: 6px;
    border-radius: 50%;
    margin-right: 6px;
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

.green {
  color: #67c23a;
}

.pointer {
  cursor: pointer;
}
</style>