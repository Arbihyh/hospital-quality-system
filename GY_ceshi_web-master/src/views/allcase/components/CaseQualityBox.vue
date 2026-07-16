<template>
  <div
    class="caseQualityBox"
    :style="{
      width: $route.path == '/whitelist-outpatient' ? '100%' : '340px',
      // height: $route.path == '/whitelist-outpatient' ? '100%' : '820px',
    }"
  >
    <div class="score-box">
      {{ data.score }}分
      <span class="level">{{ scoreLevel }}</span>
    </div>
    <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 5px">
      <span>质控时间: {{ data.quality_time }}</span>
      <el-button size="mini" style="float: right" type="primary" :disabled="isClickFeedback" @click="oneClickFeedback">一键复核</el-button>
    </div>
    <el-table :data="tableData" default-expand-all style="width: 100%">
      <el-table-column type="expand">
        <template slot-scope="props">
          <el-card v-for="(item, index) of props.row.children" :key="index" class="box-card" shadow="hover">
            <el-row>
              <el-col :span="24">
                <el-descriptions title="" :column="1" direction="vertical">
                  <template slot="extra">
                    <el-avatar v-if="item.is_fk === 1 && item.fk_status === '0'" class="typeImg" :src="require('@/assets/images/kefu.png')" fit="contain"></el-avatar>
                  </template>
                  <el-descriptions-item label="质控项目" style="margin-top: -8px; padding-top: 0">
                    <el-tag>{{ item.error_field }}</el-tag>
                    <span class="koufen">-{{ item.score }}分</span>
                  </el-descriptions-item>
                  <el-descriptions-item label="错误描述">{{ item.notice }}</el-descriptions-item>
                  <el-descriptions-item label="质控依据">
                    <div v-for="(yItem, yIndex) of item.basis" :key="yIndex" style="margin-bottom: 10px">
                      <div v-if="item.rule_id !== 6">
                        <div v-for="(cItem, cIndex) of yItem" :key="cIndex">{{ cItem }}</div>
                      </div>
                      <a v-else href="javascript:;" class="link" @click="toPage(yItem[1])">{{ yItem[0] }}</a>
                    </div>
                  </el-descriptions-item>
                </el-descriptions>
                <el-button v-if="$route.path != '/whitelist-outpatient' && item.is_fk === 0" style="float: right" type="primary" @click="submitFeedback(item)">提交反馈</el-button>
              </el-col>
            </el-row>
          </el-card>
        </template>
      </el-table-column>
      <el-table-column label="" prop="category"></el-table-column>
    </el-table>
    <RetrialHandleDialog :basicData="basicData" ref="retrialHandleDialogRef" @confirmFeedback="confirmFeedback" />
  </div>
</template>

<script>
import RetrialHandleDialog from '@/views/outpatient/retrial/handle-dialog';
export default {
  components: { RetrialHandleDialog },
  props: {
    data: {
      type: Object,
      default() {
        return {
          score: 0,
          quality_time: '',
          data: {},
        };
      },
    },
    basicData: {
      type: Object,
      default() {
        return {
          ks: '-',
          sxys: '-',
        };
      },
    },
  },
  computed: {
    tableData() {
      let arr = [];
      const keys = Object.keys(this.data.data);
      for (let i = 0; i < keys.length; i++) {
        let obj = {
          category: keys[i],
          children: this.data.data[keys[i]],
        };
        arr.push(obj);
      }
      return arr;
    },
    isClickFeedback() {
      return this.getRuleIdsStr()  === '';
    },
    scoreLevel() {
      /**
       * 甲＞90分
       * 乙75-90分
       * 丙＜75分
       * */
      let str;
      const { score } = this.data;
      if (score > 90) {
        str = '甲';
      } else if (score < 75) {
        str = '丙';
      } else {
        str = '乙';
      }
      return str;
    },
  },
  methods: {
    toPage(blbh) {
      console.log('toPage', blbh);
      if (this.$route.path == '/whitelist-outpatient') {
        this.$emit('refresh', blbh);
      } else {
        const { path } = this.$route;
        let routeData = this.$router.resolve({ path, query: { blbh } });
        window.open(routeData.href, '_blank');
      }
    },

    submitFeedback(item) {
      this.$refs.retrialHandleDialogRef.init(item);
    },
    getRuleIdsStr() {
      const ruleIds = [];
      Object.keys(this.data.data).forEach(key => {
        const list = this.data.data[key];
        if (Array.isArray(list)) {
          const validRuleIds = list
            .filter(item => item?.is_fk === 0)
            .map(item => item.rule_id);
          ruleIds.push(...validRuleIds);
        }
      });
      const uniqueRuleIds = [...new Set(ruleIds)];
      const ruleIdStr = uniqueRuleIds.join(',');
      return ruleIdStr;
    },

    oneClickFeedback() {
      const params = {
        blbh: this.$route.query.blbh,
        rule_id: this.getRuleIdsStr(),
      };
      this.$axios2
        .post('/mz_feedback', params)
        .then(res => {
          if (res.code == 200) {
            this.$emit('confirmFeedback');
            this.$message({
              message: '复核成功',
              type: 'success',
            });
          }
        })
        .catch(err => {
          console.error('复核失败：', err);
        });
    },
    confirmFeedback() {
      this.$emit('confirmFeedback');
    },
  },
};
</script>

<style lang="scss" scoped>
.caseQualityBox {
  // width: 340px;
  height: 100vh;
  overflow-x: hidden;
  overflow-y: scroll;
  background: #ffffff;
  border: 1px solid #e2e2e2;
  box-sizing: border-box;
  padding: 20px;

  ::v-deep .el-table__header-wrapper {
    display: none !important;
    height: 0 !important;
  }

  ::v-deep .el-descriptions {
    padding-top: 0 !important;
    margin-top: 0 !important;
    position: relative;
  }

  ::v-deep .el-descriptions__header {
    margin-bottom: 0 !important;
    padding-bottom: 0 !important;
  }

  ::v-deep .el-descriptions-item__content {
    padding-top: 8px !important;
  }

  ::v-deep .el-table__footer-wrapper {
    display: none !important;
    height: 0 !important;
  }

  ::v-deep .el-descriptions-item__container .el-descriptions-item__content {
    display: block;
  }
  ::v-deep .el-descriptions-item__label:not(.is-bordered-label) {
    font-weight: 600;
    display: inline-table;
  }
  ::v-deep .el-descriptions-item__content {
    padding-top: 12px;
  }
  .box-card {
    margin-bottom: 10px;
  }
  .score-box {
    width: 100%;
    height: 120px;
    line-height: 120px;
    font-size: 32px;
    font-weight: bold;
    text-align: center;
    border-radius: 5px;
    border: 1px solid #dddddd;
    position: relative;
    overflow: hidden;
    &::before {
      content: '';
      position: absolute;
      width: 200px;
      height: 200px;
      top: -120px;
      right: -160px;
      z-index: 1;
      background-color: red;
      transform: rotate(45deg);
    }
    .level {
      position: absolute;
      top: 5px;
      right: 10px;
      z-index: 2;
      line-height: 30px;
      color: #ffffff;
      font-size: 16px;
    }
  }
}
.koufen {
  color: red;
  float: right;
  line-height: 32px;
  font-size: 16px;
  font-weight: bold;
}
.link {
  text-decoration: underline;
  &:hover {
    color: #409eff;
  }
}
.typeImg {
  position: absolute;
  right: 0;
  top: -10px;
  cursor: pointer;
  z-index: 10;
  width: 34px;
  height: 34px;
}
</style>