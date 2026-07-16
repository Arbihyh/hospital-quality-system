<template>
  <div class="box">
    <el-row :gutter="16">
      <!-- 左侧菜单 -->
      <el-col :span="8">
        <div class="box_wrapper">
          <el-form :inline="true" :model="exportData" class="demo-form-inline">
            <el-form-item label="">
              <el-date-picker
                v-model="exportData.start_time"
                type="date"
                format="yyyy年MM月dd日"
                value-format="yyyyMMdd"
                placeholder="开始日期"
                style="margin-right: 10px; width: 168px;"
              />
              <el-date-picker
                v-model="exportData.end_time"
                type="date"
                format="yyyy年MM月dd日"
                value-format="yyyyMMdd"
                placeholder="结束日期"
                style=" width: 168px;"
              />
            </el-form-item>
            <el-form-item style="margin-right: 0;">
              <el-button type="primary" class="export-btn" @click="onExport">导出所有指标</el-button>
            </el-form-item>
          </el-form>
          <el-input placeholder="输入关键字进行过滤" v-model="filterText"></el-input>
          <el-tree
            class="filter-tree"
            node-key="id"
            highlight-current
            :data="menus"
            :props="defaultProps"
            :filter-node-method="filterNode"
            ref="tree"
            @node-click="handleNodeClick"
            :current-node-key="ruleId"
            :default-expanded-keys="[ruleId]"
          >
            <span class="custom-tree-node" slot-scope="{ node, data }">
              <span :class="{ green: greenColorMenus.includes(data.id) }">{{ node.label }}</span>
            </span>
          </el-tree>
        </div>
      </el-col>
      <!-- 右侧列表 -->
      <el-col :span="16">
        <div class="box_wrapper">
          <el-form :inline="true" :model="formInline" class="demo-form-inline">
            <el-form-item label="查询时间">
              <el-date-picker
                v-model="formInline.year"
                :clearable="false"
                type="year"
                :picker-options="pickerOptions"
                format="yyyy年"
                value-format="yyyy"
                placeholder="选择年份"
              ></el-date-picker>
            </el-form-item>
            <el-form-item>
              <el-button type="primary" @click="onSearch">查询</el-button>
            </el-form-item>
          </el-form>
          <el-table :data="tableData" style="width: 100%">
            <el-table-column prop="time" label="日期" width="160"></el-table-column>
            <el-table-column prop="percent" :label="cloumn1">
              <template slot-scope="scope">
                <span>{{ scope.row.percent }}</span>
              </template>
            </el-table-column>
            <el-table-column prop="count" :label="cloumn2">
              <template slot-scope="scope">
                <span class="link" @click="toListPage(scope.row)">{{ scope.row.count }}</span>
              </template>
            </el-table-column>
            <el-table-column prop="total" label="同期出院患者手术人次数" width="200"></el-table-column>
            <el-table-column prop="source" label="来源" width="100"></el-table-column>
          </el-table>
        </div>
      </el-col>
    </el-row>
  </div>
</template>

<script>
import { otherStatisticsMenuExport } from '@/api/excel'

export default {
  data() {
    return {
      exportData: {
        start_time: '',
        end_time: ''
      },
      menus: [
        {
          id: 'SSHFSS',
          name: '2.3.1 手术患者手术后肺栓塞发生例数和发生率',
        },
        {
          id: 'SSHSJMXS',
          name: '2.3.2 手术患者手术后深静脉血栓发生例数和发生率',
        },
        {
          id: 'SSHBXZ',
          name: '2.3.3 手术患者手术后败血症发生例数和发生率',
        },
        {
          id: 'SSHCXHXZ',
          name: '2.3.4 手术患者手术后出血或血肿发生例数和发生',
        },
        {
          id: 'SSHSKLK',
          name: '2.3.5 手术患者手术伤口裂开发生例数和发生率',
        },
        {
          id: 'SSHCS',
          name: '2.3.6 手术患者手术后猝死发生例数和发生率',
        },
        {
          id: 'SSHHXSJ',
          name: '2.3.7 手术患者手术后呼吸衰竭发生例数和发生率',
        },
        {
          id: 'SSHSLDXSLF',
          name: '2.3.8 手术患者手术后生理/代谢紊乱发生例数和发生',
        },
        {
          id: 'SSCZGR',
          name: '2.3.9 与手术/操作相关感染发生例数和发生率',
        },
        {
          id: 'MZBFZ',
          name: '2.3.10 手术过程中异物遗留发生例数和发生率',
        },
        {
          id: 'MZBFZ',
          name: '2.3.11 手术患者麻醉并发症发生例数和发生率',
        },
        {
          id: 'FBGRYFJNBQ',
          name: '2.3.12 手术患者肺部感染与肺机能不全发生例数和发生率',
        },
        {
          id: 'YWCCSHSLS',
          name: '2.3.13手术意外穿刺伤或撕裂伤发生例数和发生率',
        },
        {
          id: 'SSHJXSSJ',
          name: '2.3.14 手术后急性肾衰竭发生例数和发生率',
        },
        {
          id: 'GSTQG',
          name: '2.3.15 各系统/器官术后并发症发生例数和发生率',
          children: [
            {
              id: 'XTQGXH',
              name: '消化系统术后并发症发生例数',
            },
            {
              id: 'XTQGXUNHUAN',
              name: '循环系统术后并发症发生例数',
            },
            {
              id: 'XTQGSJ',
              name: '神经系统术后并发症发生例数',
            },
            {
              id: 'XTQGYHFQ',
              name: '眼和附器术后并发症发生例数',
            },
            {
              id: 'XTQGEHRC',
              name: '耳和乳突术后并发症发生例数',
            },
            {
              id: 'XTQGJRGG',
              name: '肌肉骨骼术后并发症发生例数',
            },
            {
              id: 'XTQGMNSZ',
              name: '泌尿生殖系统术后并发症发生例数',
            },
            {
              id: 'XTQGKQ',
              name: '口腔术后并发症发生例数',
            }
          ],
        },
        {
          id: 'ZRW',
          name: '2.3.16 植入物的并发症（不包括脓毒症）发生例数和发生率',
          children: [
            {
              id: 'ZRWXZHXG',
              name: '（心脏和血管）植入物的并发症（不包括脓毒症）发生例数',
            },
            {
              id: 'ZRWMNSZD',
              name: '（泌尿生殖道）植入物的并发症（不包括脓毒症）发生例数',
            },
            {
              id: 'ZRWGK',
              name: '（骨科）植入物的并发症（不包括脓毒症）发生例数',
            },
            {
              id: 'ZRWQT',
              name: '（其他）植入物的并发症（不包括脓毒症）发生例数',
            },
          ],
        },
        {
          id: 'YZBFZ',
          name: '2.3.17 移植的并发症发生例数和发生率',
        },
        {
          id: 'ZZHJZ',
          name: '2.3.18 再植和截肢的并发症发生例数和发生率',
        },
        {
          id: 'JRCZHQT',
          name: '2.3.19 介入操作与手术后患者其他并发症发生例数和发生率',
        },
      ],
      formInline: {
        year: '',
      },
      tableData: [],
      filterText: '',
      defaultProps: {
        children: 'children',
        label: 'name',
      },
      ruleId: '',
      ruleName: '',
      time: new Date(),
      pickerOptions: {
        disabledDate(time) {
          const date = new Date();
          const year = date.getFullYear();
          const timeYear = time.getFullYear();
          return year < timeYear;
        },
      },
      greenColorMenus: [],
    };
  },
  created() {
    this.formInline.year = new Date().getFullYear().toString();
    const { id, name } = this.menus[0];
    this.ruleId = id;
    this.ruleName = name;
    this.getList();
  },
  computed: {
    cloumn1() {
      const str = this.ruleName.split(' ').length === 2 ? this.ruleName.split(' ')[1] : this.ruleName
      return str.replace('发生例数和', '')
    },
    cloumn2() {
      const str = this.ruleName.split(' ').length === 2 ? this.ruleName.split(' ')[1] : this.ruleName
      return str.replace('和发生率', '')
    }
  },
  watch: {
    filterText(val) {
      this.$refs.tree.filter(val);
    },
  },
  methods: {
    // 导出
    onExport() {
      const { start_time, end_time } = this.exportData
      if (!start_time || !end_time) {
        this.$message.error('请选择导出日期！')
        return
      }
      otherStatisticsMenuExport(this.exportData).then(res => {
        const content = res.data; // 后台返回二进制数据
        const blob = new Blob([content]);
        const fileName = `其他统计数据.csv`;
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
    // 列表跳转
    toListPage(row) {
      console.log(row);
      const { time, ruleId, name } = row;
      this.$router.push({ path: '/otherStatisticsList', query: { year: this.formInline.year, time, ruleId, name } });
    },
    // 菜单筛选
    filterNode(value, data) {
      if (!value) return true;
      return data.name.indexOf(value) !== -1;
    },
    handleNodeClick(data) {
      console.log(data)
      const { id, name } = data;
      this.ruleId = id;
      this.ruleName = name;
      if (id && this.formInline.year) {
        this.getList();
      }
    },
    // 获取右侧列表数据
    getList() {
      const { year } = this.formInline;
      const params = {
        start_time: `${year}0101`,
        end_time: `${year}1231`,
        field: this.ruleId,
      };
      this.$axios.post('/ssbfz/getBfzData', params).then(res => {
        if (Array.isArray(res.data)) {
          res.data.map(item => {
            item.name = this.ruleName;
            item.ruleId = this.ruleId;
            item.percent = '0%';
            item.total = 0
          });
          this.tableData = res.data;
        } else {
          this.tableData = [];
        }
      });
    },
    // 查询
    onSearch() {
      const { year } = this.formInline;
      console.log(year);
      if (!year) {
        this.$message.error('请选择查询时间');
        return;
      }
      if (!this.ruleId) {
        this.$message.error('请选择查询指标');
        return;
      }
      this.getList();
    },
  },
};
</script>

<style lang="scss" scoped>
.link {
  font-weight: 600;
  color: #409eff;
}

.pointer {
  cursor: pointer;
}
.box {
  padding: 0 16px 16px 16px;
  .box_wrapper {
    padding: 16px;
    background: #fff;
    border-radius: 5px;
    overflow-x: hidden;
    overflow-y: auto;
    height: calc(100vh - 140px);
  }
}
.filter-tree {
  margin-top: 16px;
  ::v-deep .el-tree-node__content {
    height: 36px;
    line-height: 36px;
  }
}

::v-deep.el-table .el-table__header tr th {
  background: #f1f6ff;
  color: #13171e;
  border-bottom: 0px;
}
::v-deep.el-table .el-table__row td {
  color: #7e8bab;
  border-bottom: 1px solid #f4f4f4;
}
::v-deep.el-table .el-table__header tr th:first-child {
  border-radius: 5px 0px 0px 5px;
}
::v-deep.el-table .el-table__header tr th:nth-child(3) {
  border-radius: 0px 5px 5px 0px;
}
.green {
  color: #67c23a;
}
.custom-tree-node {
  width: 80%;
  overflow: hidden;
  text-overflow: ellipsis;
  display: inline-block;
}
</style>