<template>
  <div class="qc-card" :class="cardBorderClass">
    <div class="card-meta">
      <div class="deduct">-{{ dataForm.score }}分</div>
      <!-- <el-image class="zsIcon" :src="require('@/assets/images/zsicon.png')" fit="contain"></el-image> -->
      <el-image
        class="zsIcon"
        v-if="dataForm.entity.is_ai == 1 || dataForm.entity.is_ai == 3"
        :src="require('@/assets/images/zsicon.png')"
        fit="contain"
      ></el-image>
      <el-image
        v-if="dataForm.entity.is_ai == 2"
        class="zsIcon"
        :src="require('@/assets/images/ysicon.png')"
        fit="contain"
      ></el-image>
    </div>

    <div class="card-head">
      <span class="pill" :class="levelPillClass" :title="levelPillTitle">{{ levelPillText }}</span>
      <span class="pill pill-outline">{{ dataForm.recordType }}</span>
      <span v-if="dataForm.singleNo === 1 && routeType === 'RUN-RECORD'" class="pill-ring">单否</span>
    </div>

    <div class="field-label">错误描述</div>
    <div class="field-value">{{ dataForm.notice }}</div>

    <div v-if="routeType === 'RUN-RECORD' && (dataForm.evidenceList.length != 0)" class="evidence">
      <div class="evidence-head" @click="toggleCollapse">
        <div class="evidence-title">
          <span class="evidence-bar"></span>
          <span>质控依据</span>
        </div>
        <div class="evidence-arrow">{{ isCollapsed ? '▶' : '▼' }}</div>
      </div>
      <div class="evidence-body" v-show="!isCollapsed">
        <!-- <div class="evidence-empty" v-if="!dataForm.evidenceList || dataForm.evidenceList.length === 0">暂无数据</div> -->
        <template>
          <div class="evidence-item" v-for="(item, index) in dataForm.evidenceList" :key="index">
            <span class="serial">{{ index + 1 }}</span>
            <div class="evidence-content">
              <div v-if="typeof item === 'string'">{{ item }}</div>
              <div v-else-if="Array.isArray(item)">
                <div
                  v-for="(subItem, subIndex) in item"
                  :key="subIndex"
                  class="sub-item"
                >{{ subItem }}</div>
              </div>
            </div>
          </div>
        </template>
      </div>
    </div>

    <div class="card-footer">
      <div v-if="routeType === 'RUN-HOME'" class="footer-btn-group">
        <button
          class="btn btn-orange"
          v-if="dataForm.type == 2 && dataForm.status == 0"
          @click="$emit('openAppealDialog', 'appeal_ing', dataForm.entity, 1)"
        >申诉中</button>
        <button
          class="btn btn-red"
          v-if="dataForm.type == 2 && dataForm.status == 2"
          @click="$emit('openAppealDialog', 'appeal_no', dataForm.entity, 1)"
        >驳回</button>
        <button
          class="btn btn-rect"
          v-if="dataForm.is_artificial == 1"
          @click="clickAppealEdit(dataForm.entity, 1)"
        >已整改</button>
        <div
          class="btn-right"
          v-if="dataForm.type == 0 || dataForm.type == 1 || (dataForm.type == 2 && dataForm.status == 2)"
        >
          <button
            class="btn btn-light"
            @click="$emit('openAppealDialog', 'appeal_in_ignore', dataForm.entity, 1)"
          >忽略</button>
          <button
            class="btn btn-primary"
            @click="$emit('openAppealDialog', 'appeal', dataForm.entity, 1)"
          >申诉</button>
        </div>
      </div>

      <div v-if="routeType === 'RUN-RECORD'" class="footer-btn-group">
        <button
          v-if="dataForm.rule_type === '时效性'"
          :class="dataForm.isKnow === 1 ? 'appeal_in_ignore' : 'gotIt'"
          @click="dataForm.isKnow !== 1 && gotIt(dataForm.entity)"
        >知道了</button>
        <button
          class="btn btn-red"
          @click="$emit('openAppealDialog', 'appeal_no', dataForm.entity, 2)"
          v-if="dataForm.appeal_type == 2 && dataForm.appeal_status == 2"
        >驳回</button>
        <button
          class="btn btn-orange"
          style="cursor: pointer"
          v-if="dataForm.appeal_type == 2 && dataForm.appeal_status == 0"
          @click="$emit('openAppealDialog', 'appeal_ing', dataForm.entity, 2)"
        >申诉中</button>
        <button
          class="btn btn-rect"
          @click="clickAppealEdit(dataForm.entity, 2)"
          v-if="dataForm.is_artificial == 1"
        >已整改</button>
        <div
          class="btn-right"
          v-if="dataForm.appeal_type == 0 || dataForm.appeal_type == 1  || (dataForm.appeal_type == 2 && dataForm.appeal_status == 2)"
        >
          <button
            class="btn btn-light"
            @click="$emit('openAppealDialog', 'appeal_in_ignore', dataForm.entity, 2)"
          >忽略</button>
          <UnlockCountdownButton
            :rowData="dataForm.entity"
            v-if="dataForm.entity.can_unlock === 1 "
            @refresh="$emit('refresh')"
          />
          <button
            class="btn btn-primary"
            @click="$emit('openAppealDialog', 'appeal', dataForm.entity, 2)"
          >申诉</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { setCorrection } from '@/api/qc';
import UnlockCountdownButton from '../comp-v2/UnlockCountdownButton.vue';

export default {
  name: 'QcCard',
  components: {
    UnlockCountdownButton,
  },
  props: {
    routeType: {
      type: String,
      default: 'RUN-HOME',
    },
    dataForm: {
      type: Object,
      default: () => ({
        id: '',
        level: 0,
        is_artificial: 1,
        singleNo: 0,
        recordType: '病程记录',
        notice: '三级医师查房记录内容过于简单，未体现鉴别诊断',
        type: 0,
        status: 0,
        evidenceList: ['查房记录仅描述“病情平稳”，未对患者入院后的病情变化进行分析。', '建议补充诊断依据条目化记录，并与关键辅助检查结果进行逐条对应说明。'],
        score: '0.5',
        rule_type: '1',
        appeal_type: 0,
        entity: {},
      }),
    },
  },
  data() {
    return {
      isCollapsed: false, // 展开收起状态
    };
  },
  computed: {
    levelPillClass() {
      if (this.dataForm.level === -1 || this.dataForm.level === '-1') return 'pill-yellow';
      return this.dataForm.level === 2 || this.dataForm.level === '2' ? 'pill-blue' : 'pill-red';
    },
    levelPillText() {
      if (this.dataForm.level === -1 || this.dataForm.level === '-1') return '预警';
      return this.dataForm.level === 2 || this.dataForm.level === '2' ? '建议' : '必改';
    },
    levelPillTitle() {
      if (this.dataForm.level === -1 || this.dataForm.level === '-1') return '预警类问题，需关注核查';
      return this.dataForm.level === 2 || this.dataForm.level === '2' ? '建议类问题，可改可不改' : '强制类问题必须修改；如质控结果不准，可提交申诉';
    },
    cardBorderClass() {
      if (this.dataForm.level === -1 || this.dataForm.level === '-1') return 'card-warning';
      return this.dataForm.level === 2 || this.dataForm.level === '2' ? 'card-suggest' : 'card-must';
    },
  },

  methods: {
    toggleCollapse() {
      this.isCollapsed = !this.isCollapsed;
    },
    clickAppealEdit(item, quality_type) {
      console.log('>>>>', item);
      this.$confirm('是否确认已整改?', '提示', {
        confirmButtonText: '确定',
        cancelButtonText: '取消',
        type: 'warning',
        customClass: 'customClass-el-message-box-center',
      })
        .then(() => {
          setCorrection({
            id: item.id,
            quality_type,
          }).then(res => {
            if (res.code == 200) {
              this.$message.success('已整改成功！');
              this.$emit('refresh');
            }
          });
        })
        .catch(() => {});
    },

    gotIt(item) {
      this.$axios2
        .post('/tk/is_know', {
          zyh: this.$route.query.id,
          rule_id: item.rule_id,
        })
        .then(res => {
          if (res.code === 200) {
            this.$emit('refresh');
          }
        });
    },
  },
};
</script>

<style lang="scss" scoped>
.qc-card {
  background: #fff;
  border: 1px solid #e5eaf1;
  border-radius: 12px;
  padding: 8px;
  margin-bottom: 12px;
  position: relative;

  .card-meta {
    position: absolute;
    top: 16px;
    right: 16px;
    display: flex;
    align-items: center;
    gap: 8px;

    .deduct {
      font-size: 14px;
      font-weight: bold;
      color: #f04438;
    }

    .qc-icon {
      width: 18px;
      height: 18px;
      background-size: contain;
      background-repeat: no-repeat;
      background-position: center;

      &.qc-human {
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='18' height='18' viewBox='0 0 24 24' fill='none'%3E%3Cpath d='M12 12a4 4 0 1 0-4-4 4 4 0 0 0 4 4Z' stroke='%23f59e0b' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'/%3E%3Cpath d='M20 21a8 8 0 0 0-16 0' stroke='%23f59e0b' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E");
      }
    }
  }

  .card-head {
    display: flex;
    gap: 8px;
    margin-bottom: 12px;
    padding-right: 100px;

    .pill {
      height: 24px;
      padding: 0 10px;
      border-radius: 6px;
      font-size: 12px;
      font-weight: bold;
      display: inline-flex;
      align-items: center;
      justify-content: center;

      &.pill-red {
        background: #f04438;
        color: #fff;
      }
      &.pill-1 {
        background: #ffdfdf;
        color: #da1515;
      }

      &.pill-blue {
        background: #2f6bff;
        color: #fff;
      }
      &.pill-yellow {
        background: rgb(253, 246, 236) !important;
        color: rgb(230, 162, 60) !important;
      }
      &.card-warning {
        border-left: 3px solid rgb(230, 162, 60);
        background: rgb(253, 246, 236);
      }

      &.pill-0 {
        background: rgb(253, 246, 236);
        color: rgb(230, 162, 60);
      }

      &.pill-outline {
        background: #f7faff;
        color: #2f6bff;
        border: 1px solid #cfe0ff;
      }
    }

    .pill-ring {
      height: 22px;
      padding: 0 8px;
      border-radius: 999px;
      border: 1px solid #dfe5ec;
      font-size: 12px;
      font-weight: bold;
      color: #8b95a5;
      display: inline-flex;
      align-items: center;
    }
  }

  .field-label {
    font-size: 13px;
    color: #7b8794;
    margin-bottom: 4px;
  }

  .field-value {
    font-size: 15px;
    color: #1f2937;
    margin-bottom: 12px;
    line-height: 1.6;
  }

  .evidence {
    background: #fafbfd;
    border: 1px solid #e5eaf1;
    border-radius: 10px;
    overflow: hidden;
    margin-bottom: 12px;

    .evidence-head {
      height: 38px;
      padding: 0 12px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      border-bottom: 1px solid #edf2f7;
      cursor: pointer;

      .evidence-title {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 13px;
        font-weight: bold;
        color: #4b5563;

        .evidence-bar {
          width: 3px;
          height: 14px;
          background: #2f6bff;
          border-radius: 2px;
        }
      }

      .evidence-arrow {
        font-size: 12px;
        color: #8b95a5;
      }
    }

    .evidence-body {
      padding: 12px;

      .evidence-item {
        display: flex;
        gap: 8px;
        margin-bottom: 8px;
        font-size: 14px;
        color: #4b5563;
        line-height: 1.6;

        &:last-child {
          margin-bottom: 0;
        }

        .serial {
          width: 16px;
          height: 16px;
          border: 1px solid #d8e0ea;
          border-radius: 4px;
          font-size: 11px;
          color: #98a2b3;
          display: flex;
          align-items: center;
          justify-content: center;
          flex-shrink: 0;
          margin-top: 2px;
        }
      }
    }
  }

  .btn {
    height: 28px;
    padding: 0 12px;
    border-radius: 14px;
    font-size: 12px;
    font-weight: bold;
    border: none;
    cursor: pointer;

    &.btn-light {
      background: #eaeef3;
      color: #4b5563;
    }

    &.btn-primary {
      background: #2f6bff;
      color: #fff;
    }

    &.btn-rect {
      background: #10b981;
      color: #fff;
    }

    &.btn-orange {
      background: #fff7e6;
      color: #fa8c16;
      border: 1px solid #ffe8ba;
    }

    &.btn-red {
      background: #ffdfdf;
      color: #ef1f3a;
      border: 1px solid #ef1f3a;
    }
  }

  .footer-btn-group {
    display: flex;
    width: 100%;
    gap: 8px;
    align-items: center;
  }
  .btn-right {
    margin-left: auto;
    display: flex;
    gap: 8px;
  }
}

.zsIcon {
  width: 35px;
  height: 35px;
}
.evidence-empty {
  text-align: center;
  padding: 10px 0;
  color: #999;
  font-size: 14px;
}
.evidence-content {
  width: 100%;
}
.sub-item {
  margin-bottom: 4px;
  line-height: 1.5;
}

.card-must {
  border-left: 3px solid #f04438;
  background: #fff8f7;
}
.card-suggest {
  border-left: 3px solid #2f6bff;
  background: #f5f9ff;
}
</style>