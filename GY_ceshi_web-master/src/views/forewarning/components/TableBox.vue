<template>
  <div class="bg-card">
    <div class="btn-box">
      <span class="page-msg">
        共找到
        <span class="num">{{ page.total }}</span>
        条结果
        <span class="page" v-if="page.total">{{ page.page }}/{{ Math.ceil(page.total / page.limit) }}</span>
        <span class="page_limit_box">
          显示
          <el-select v-model="page.limit" size="mini" @change="handleLimitChange" style="width: 100px">
            <el-option label="10条/页" :value="10"></el-option>
            <el-option label="20条/页" :value="20"></el-option>
            <el-option label="30条/页" :value="30"></el-option>
            <el-option label="50条/页" :value="50"></el-option>
          </el-select>
        </span>
      </span>
      <el-button type="primary" icon="el-icon-download" @click="onExport" class="export-btn">导出数据</el-button>
    </div>
    <!-- 列表 -->
    <el-table :data="data" style="width: 100%">
      <el-table-column label="序号" width="80" align="center" fixed="left">
        <template slot-scope="scope">{{ (page.page - 1) * page.limit + scope.$index + 1 }}</template>
      </el-table-column>
      <el-table-column prop="push_time" label="质控时间" align="center" width="160" fixed="left"></el-table-column>
      <el-table-column prop="quality_content" width="340" align="left" fixed="left" label="预警问题" show-overflow-tooltip=""></el-table-column>
      <el-table-column prop="" label="是否整改" width="120" align="center">
        <template slot-scope="scope">
          <el-tag type="success" v-if="scope.row.status == 1">已整改</el-tag>
          <el-tag type="danger" v-else-if="scope.row.status == 2">未整改</el-tag>
          <el-tag type="primary" v-else-if="scope.row.status == 3">按时整改</el-tag>
        </template>
      </el-table-column>
      <el-table-column prop="" label="质控依据" width="160" align="center" show-overflow-tooltip>
        <template slot-scope="scope">
          <span v-for="(item, index) of scope.row.msg_yj" :key="index">
            <span v-if="index">;</span>
            <span>{{ item }}</span>
          </span>
        </template>
      </el-table-column>
      <el-table-column prop="" label="住院号" width="140" align="center">
        <template slot-scope="scope">
          <span class="link" @click="toDetailPage(scope.row)">{{ scope.row.AAA28 }}</span>
        </template>
      </el-table-column>
      <el-table-column prop="AAA01" label="患者姓名" align="center" width="120"></el-table-column>
      <el-table-column prop="AAC11N" label="科室名称" align="center" width="160"></el-table-column>
      <el-table-column prop="AEE03" label="主治医生" align="center" width="120"></el-table-column>
      <el-table-column prop="AEE01" label="主任医师" align="center" width="120"></el-table-column>
      <el-table-column prop="AAB01" label="入院时间" align="center" width="160"></el-table-column>
      <el-table-column prop="AAC01" label="出院时间" align="center" width="160"></el-table-column>
      <el-table-column label="是否删除" align="center" width="140">
        <template slot-scope="scope">
          <el-popover
            v-if="scope.row.data_type"
            placement="bottom-start"
            title=""
            width="200"
            trigger="hover"
            :content="scope.row.data_type">
            <el-tag slot="reference" type="success" v-if="scope.row.is_delete == 1">正常</el-tag>
            <el-tag slot="reference" type="danger" v-if="scope.row.is_delete == 0">删除</el-tag>
          </el-popover>
          <span v-else>
            <el-tag type="success" v-if="scope.row.is_delete == 1">正常</el-tag>
            <el-tag type="danger" v-if="scope.row.is_delete == 0">删除</el-tag>
          </span>
        </template>
      </el-table-column>
      <!-- <el-table-column label="操作" align="center" width="140" fixed="right">
        <el-button type="primary" size="mini" @click="onFeedback">问题反馈</el-button>
      </el-table-column> -->
    </el-table>
    <!-- 问题反馈 -->
    <FeedbackDialogVue v-if="feedbackData.bSwitch" :data="feedbackData" />
  </div>
</template>

<script>
import FeedbackDialogVue from './FeedbackDialog.vue';
export default {
  components: {
    FeedbackDialogVue
  },
  props: {
    page: {
      type: Object,
      default() {
        return {
          total: 0,
          page: 1,
          limit: 10,
        };
      },
    },
    data: {
      type: Array,
      default() {
        return [];
      },
    },
  },
  data() {
    return {
      feedbackData: {
        bSwitch: false
      }
    }
  },
  methods: {
    handleLimitChange(val) {
      this.$emit('limit_change', val);
    },
    // 模板导出
    onExport() {
      this.$emit('export');
    },
    toDetailPage(row) {
      this.storageSet('getData', row.zyh);
      localStorage.setItem('isControl', true)
      let path = '/caseViews?topBtn=top';
      this.$router.push({ path, query: { from: 'forewarning' }})
    },
    onFeedback() {
      this.feedbackData.bSwitch = true
    }
  },
};
</script>
<style lang="scss" scoped>
.btn-box {
  text-align: right;
  margin-bottom: 15px;
  .page-msg {
    float: left;
    height: 40px;
    line-height: 40px;
    font-size: 14px;
    .num {
      color: #f56c6c;
    }
    .page {
      margin-left: 40px;
    }
    .page_limit_box {
      margin-left: 40px;
    }
  }
}
.link {
  cursor: pointer;
  color: #409eff;
}
</style>