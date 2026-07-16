<template>
  <div class="medical-qc-popup">
    <!-- 标签页 -->
    <div class="tabs">
      <div class="tabs-box">
        <el-button 
          type="text" 
          class="tab" 
          :class="{ active: activeTab === 'home' }"
          @click="switchTab('home')"
        >
          首页问题
          <span class="tab-badge">99</span>
        </el-button>
        <el-button 
          type="text" 
          class="tab" 
          :class="{ active: activeTab === 'record' }"
          @click="switchTab('record')"
        >
          病历问题
          <span class="tab-badge">9</span>
        </el-button>
        <el-button 
          type="text" 
          class="tab" 
          :class="{ active: activeTab === 'generate' }"
          @click="switchTab('generate')"
        >
          病历生成
        </el-button>
      </div>
    </div>

    <!-- 得分摘要 -->
    <div class="score-summary">
      <div class="score-main">
        <div class="score-left">
          <div class="score-label">当前得分</div>
          <div class="score-box">
            <span class="score-num">65</span>
            <span class="score-unit">分</span>
          </div>
        </div>
        <div class="grade-box">
          <div class="grade-label">病历等级</div>
          <div class="grade-value">乙级</div>
        </div>
      </div>
    </div>

    <!-- 页面内容 -->
    <div class="pages">
      <!-- 首页问题 -->
      <div class="page" v-show="activeTab === 'home'">
        <div class="card-list">
          <el-card 
            class="qc-card urgent" 
            v-for="(item, index) in homeIssues" 
            :key="index"
            shadow="hover"
          >
            <div class="card-meta">
              <div class="deduct">{{ item.deduct }}</div>
              <div :class="['qc-icon', item.iconType]" :title="item.iconTitle"></div>
            </div>
            <div class="card-head">
              <el-tag type="danger" size="small">必改</el-tag>
              <el-tag size="small" effect="plain">{{ item.type }}</el-tag>
            </div>
            <div class="field-label">错误描述</div>
            <div class="field-value">{{ item.description }}</div>
            <div class="evidence">
              <div class="evidence-head" @click="toggleEvidence(index)">
                <div class="evidence-title">
                  <span class="evidence-bar"></span>
                  <span>质控依据</span>
                </div>
                <div class="evidence-arrow" :class="{ collapsed: !item.evidenceExpanded }">{{ item.evidenceExpanded ? '▼' : '▶' }}</div>
              </div>
              <div class="evidence-body" :class="{ collapsed: !item.evidenceExpanded }">
                <div class="evidence-item" v-for="(evidence, idx) in item.evidence" :key="idx">
                  <span class="serial">{{ idx + 1 }}</span>
                  <div>{{ evidence }}</div>
                </div>
              </div>
            </div>
            <div class="card-footer">
              <el-button size="small" type="info" plain>忽略</el-button>
              <el-button size="small" type="primary">申诉</el-button>
            </div>
          </el-card>
        </div>
      </div>

      <!-- 病历问题 -->
      <div class="page" v-show="activeTab === 'record'">
        <div class="card-list">
          <el-card 
            class="qc-card" 
            :class="item.type === '必改' ? 'urgent' : 'suggestion'"
            v-for="(item, index) in recordIssues" 
            :key="index"
            shadow="hover"
          >
            <div class="card-meta">
              <div class="deduct">{{ item.deduct }}</div>
              <div :class="['qc-icon', item.iconType]" :title="item.iconTitle"></div>
            </div>
            <div class="card-head">
              <el-tag :type="item.type === '必改' ? 'danger' : 'primary'" size="small">{{ item.type }}</el-tag>
              <el-tag size="small" effect="plain">{{ item.category }}</el-tag>
              <el-tag v-if="item.reviewType" size="small" effect="plain" class="pill-ring">{{ item.reviewType }}</el-tag>
            </div>
            <div class="field-label">{{ item.type === '必改' ? '错误描述' : '建议描述' }}</div>
            <div class="field-value">{{ item.description }}</div>
            <div class="evidence" v-if="item.evidence && item.evidence.length">
              <div class="evidence-head" @click="toggleRecordEvidence(index)">
                <div class="evidence-title">
                  <span class="evidence-bar"></span>
                  <span>质控依据</span>
                </div>
                <div class="evidence-arrow" :class="{ collapsed: !item.evidenceExpanded }">{{ item.evidenceExpanded ? '▼' : '▶' }}</div>
              </div>
              <div class="evidence-body" :class="{ collapsed: !item.evidenceExpanded }">
                <div class="evidence-item" v-for="(evidence, idx) in item.evidence" :key="idx">
                  <span class="serial">{{ idx + 1 }}</span>
                  <div>{{ evidence }}</div>
                </div>
              </div>
            </div>
            <div class="card-footer">
              <el-button v-if="item.rectified" size="small" type="success" plain>已整改</el-button>
              <el-button size="small" type="info" plain>忽略</el-button>
              <el-button size="small" type="primary">申诉</el-button>
            </div>
          </el-card>
        </div>
      </div>

      <!-- 病历生成 -->
      <div class="page" v-show="activeTab === 'generate'">
        <div class="gen-stack">
          <el-card shadow="hover">
            <div class="card-head space">
              <div class="card-head-left">
                <el-tag type="warning" size="small">输入</el-tag>
                <el-tag size="small" effect="plain">点击输入，生成病历</el-tag>
              </div>
            </div>
            <div class="gen-field">
              <div class="gen-label">输入内容</div>
              <el-input
                type="textarea"
                class="gen-textarea"
                placeholder="请输入病历相关信息"
                rows="4"
              ></el-input>
            </div>
            <div class="gen-actions">
              <el-button size="small" type="info" plain>清空</el-button>
              <el-button size="small" type="primary">生成</el-button>
            </div>
          </el-card>
        </div>
      </div>
    </div>

    <!-- 底部栏 -->
    <div class="bottom-bar" :class="{ expanded: bottomExpanded }">
      <div class="bottom-top">
        <div class="bottom-text">
          <span class="bt-item">
            <span class="bt-label">病案号：</span>
            <span class="bt-value">00000001</span>
          </span>
          <span class="bt-item">
            <span class="bt-label">床号：</span>
            <span class="bt-value">CH14-55</span>
          </span>
          <span class="bt-item">
            <span class="bt-label">姓名：</span>
            <span class="bt-value">张三三</span>
          </span>
        </div>
        <div class="collapse-up" @click="toggleBottom"></div>
      </div>
      <div class="bottom-extra">
        <div class="bottom-stats">
          <div class="stat-row">
            <div class="stat-item">
              <span class="stat-label">问题数量</span>
              <span class="stat-value">5</span>
            </div>
            <div class="stat-item">
              <span class="stat-label">必改问题</span>
              <span class="stat-value urgent">1</span>
            </div>
            <div class="stat-item">
              <span class="stat-label">建议问题</span>
              <span class="stat-value suggestion">4</span>
            </div>
            <div class="stat-item">
              <span class="stat-label">质控状态</span>
              <span class="stat-status done">已质控</span>
            </div>
          </div>
          <div class="stat-time">
            <el-button 
              type="primary" 
              size="small" 
              plain 
              class="doc-menu-btn"
              @click="toggleDocMenu"
            >
              文书问题分布
            </el-button>
            <div class="time-container">
              <span class="time-label">质控时间：</span>
              <span class="time-value">2026-04-13 20:03:00</span>
            </div>
          </div>
        </div>
        <div class="bottom-nav">
          <el-button 
            size="small" 
            class="nav-btn nav-record"
            @click="handleNavClick('record')"
          >
            质控记录
          </el-button>
          <el-button 
            size="small" 
            class="nav-btn nav-generate"
            @click="handleNavClick('generate')"
          >
            病历生成
          </el-button>
          <el-button 
            size="small" 
            class="nav-btn nav-ai"
            @click="handleNavClick('ai')"
          >
            AI提醒
          </el-button>
          <el-button 
            size="small" 
            class="nav-btn nav-message"
            @click="handleNavClick('message')"
          >
            消息
          </el-button>
          <el-button 
            size="small" 
            class="nav-btn nav-login"
            @click="handleNavClick('login')"
          >
            登录
          </el-button>
        </div>
      </div>
    </div>

    <!-- 文书菜单 -->
    <el-popover
      v-model="docMenuVisible"
      placement="top"
      width="180"
      trigger="click"
    >
      <div class="doc-menu">
        <div class="doc-menu-header">文书类型</div>
        <div class="doc-menu-list">
          <div class="doc-item" v-for="(doc, index) in docTypes" :key="index">
            <span class="doc-name">{{ doc.name }}</span>
            <span class="doc-count">{{ doc.count }}个</span>
          </div>
        </div>
      </div>
      <el-button 
        slot="reference" 
        type="primary" 
        size="small" 
        plain 
        class="doc-menu-btn"
      >
        文书问题分布
      </el-button>
    </el-popover>
  </div>
</template>

<script>
export default {
  name: 'MedicalQcPopup',
  data() {
    return {
      activeTab: 'record',
      bottomExpanded: true,
      docMenuVisible: false,
      homeIssues: [
        {
          deduct: '-1分',
          iconType: 'qc-auto',
          iconTitle: '规则质控',
          type: '病案首页',
          description: '病案首页缺少入院科室/入院方式信息',
          evidence: [
            '病案首页需完整填写入院科室与入院方式，用于统计归档与病案管理。'
          ],
          evidenceExpanded: false
        },
        {
          deduct: '-0.5分',
          iconType: 'qc-model',
          iconTitle: '模型质控',
          type: '病案首页',
          description: '病案首页主要诊断编码格式疑似不规范',
          evidence: [
            '主要诊断编码应符合 ICD-10 书写规范；建议核对编码位数及分隔符。'
          ],
          evidenceExpanded: false
        }
      ],
      recordIssues: [
        {
          deduct: '-2分',
          iconType: 'qc-human',
          iconTitle: '人工质控',
          type: '必改',
          category: '病程记录',
          reviewType: '单否',
          description: '三级医师查房记录内容过于简单，未体现鉴别诊断',
          rectified: true,
          evidence: []
        },
        {
          deduct: '-1分',
          iconType: 'qc-auto',
          iconTitle: '规则质控',
          type: '必改',
          category: '入院记录',
          description: '主诉描述不完整：缺少症状持续时间',
          evidence: [
            '主诉记录为“反复咳嗽、咳痰”，未注明持续天数或年限。'
          ],
          evidenceExpanded: false
        },
        {
          deduct: '-0.5分',
          iconType: 'qc-auto',
          iconTitle: '规则质控',
          type: '必改',
          category: '首次病程',
          description: '诊断依据描述与辅助检查结果关联不足',
          evidence: [
            '依据条款：首次病程记录需体现诊断依据与辅助检查关联；建议明确列出关键检查结果与诊断对应关系。'
          ],
          evidenceExpanded: false
        },
        {
          deduct: '-0分',
          iconType: 'qc-auto',
          iconTitle: '规则质控',
          type: '建议',
          category: '出院记录',
          description: '出院记录未包含随访联系方式与复诊时间，建议补充“复诊时间/门诊科室/联系电话”。',
          evidence: [
            '依据条款：出院记录建议包含复诊安排与联系方式，便于患者随访管理。'
          ],
          evidenceExpanded: false
        },
        {
          deduct: '-0分',
          iconType: 'qc-model',
          iconTitle: '模型质控',
          type: '建议',
          category: '手术记录',
          description: '手术记录中手术步骤描述可进一步结构化，建议使用条目化方式记录。',
          evidence: [
            '结构化手术记录有助于提高可读性与后续检索效率。',
            '建议按“麻醉方式、手术体位、手术步骤、术中所见、术后处理”等模块组织内容。'
          ],
          evidenceExpanded: false
        }
      ],
      docTypes: [
        { name: '病案首页', count: 2 },
        { name: '入院记录', count: 1 },
        { name: '首次病程', count: 1 },
        { name: '病程记录', count: 1 },
        { name: '出院记录', count: 1 },
        { name: '手术记录', count: 1 }
      ]
    }
  },
  methods: {
    switchTab(tab) {
      this.activeTab = tab
    },
    toggleBottom() {
      this.bottomExpanded = !this.bottomExpanded
    },
    toggleDocMenu() {
      this.docMenuVisible = !this.docMenuVisible
    },
    toggleEvidence(index) {
      this.homeIssues[index].evidenceExpanded = !this.homeIssues[index].evidenceExpanded
    },
    toggleRecordEvidence(index) {
      this.recordIssues[index].evidenceExpanded = !this.recordIssues[index].evidenceExpanded
    },
    handleNavClick(action) {
      switch(action) {
        case 'record':
          this.switchTab('record')
          break
        case 'generate':
          this.switchTab('generate')
          break
        case 'ai':
          this.$message.info('AI提醒功能')
          break
        case 'message':
          this.$message.info('消息功能')
          break
        case 'login':
          this.$message.info('登录功能')
          break
      }
    }
  }
}
</script>

<style scoped>
/* 全局样式 */
:root {
  --bg: #f5f7fa;
  --panel: #ffffff;
  --panel-soft: #fafbfd;
  --line: #e5eaf1;
  --line-soft: #edf2f7;
  --text-1: #1f2937;
  --text-2: #4b5563;
  --text-3: #7b8794;
  --blue: #2f6bff;
  --blue-soft: #f5f9ff;
  --blue-line: #cfe0ff;
  --red: #f04438;
  --red-soft: #fff8f7;
  --red-line: #ffd4cf;
  --purple: #6f42ef;
  --purple-soft: #f3edff;
  --orange: #f59e0b;
  --orange-soft: #fff6e8;
  --orange-line: #ffe0b2;
  --green: #16a34a;
  --green-soft: #edf9f0;
  --green-line: #c7ebd1;
  --shadow: 0 2px 8px rgba(31, 41, 55, 0.04);
}

.medical-qc-popup {
  width: 420px;
  height: 700px;
  min-width: 420px;
  min-height: 700px;
  max-width: 420px;
  max-height: 700px;
  background: var(--bg);
  position: relative;
  overflow: hidden;
  border: 1px solid #dfe5ec;
  display: flex;
  flex-direction: column;
  font-family: "Microsoft YaHei", "PingFang SC", Arial, sans-serif;
  color: var(--text-1);
}

.main {
  flex: 1;
  min-height: 0;
  overflow-y: scroll;
  padding: 10px;
  scrollbar-gutter: stable;
  scrollbar-width: thin;
  scrollbar-color: #cfd7e3 transparent;
}

.main::-webkit-scrollbar {
  width: 6px;
}

.main::-webkit-scrollbar-thumb {
  background: #cfd7e3;
  border-radius: 999px;
}

.main::-webkit-scrollbar-track {
  background: transparent;
}

/* 标签页 */
.tabs {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 6px;
  padding: 10px;
}

.tabs-box {
  display: inline-flex;
  align-items: center;
  gap: 2px;
  padding: 2px;
  background: #e9edf2;
  border: 1px solid #d8dee7;
  border-radius: 12px;
}

.tab {
  position: relative;
  min-width: 86px;
  height: 30px;
  padding: 0 14px;
  border-radius: 10px;
  border: 1px solid transparent;
  background: transparent;
  color: var(--text-2);
  font-size: 13px;
  cursor: pointer;
  white-space: nowrap;
  display: inline-flex;
  align-items: center;
  justify-content: center;
}

.tab.active {
  background: #dbe8ff;
  color: var(--blue);
  border-color: #bfd3ff;
  font-weight: 700;
}

.tab-badge {
  position: absolute;
  top: -5px;
  right: -2px;
  min-width: 16px;
  height: 16px;
  padding: 0 4px;
  border-radius: 999px;
  background: var(--red);
  color: #ffffff;
  font-size: 9px;
  font-weight: 700;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  line-height: 1;
  border: 1px solid #ffffff;
  box-shadow: 0 0 0 1px var(--bg);
  transform: translateZ(0);
}

/* 得分摘要 */
.score-summary {
  margin: 0 10px 10px;
  background: var(--panel);
  border: 1px solid var(--line);
  border-radius: 12px;
  box-shadow: var(--shadow);
  padding: 12px;
}

.score-main {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}

.score-left {
  min-width: 0;
}

.score-label {
  font-size: 12px;
  color: var(--text-3);
  margin-bottom: 6px;
  line-height: 1;
}

.score-box {
  display: flex;
  align-items: baseline;
  gap: 4px;
}

.score-num {
  font-size: 32px;
  line-height: 1;
  font-weight: 700;
  color: var(--red);
  letter-spacing: -0.8px;
}

.score-unit {
  font-size: 14px;
  color: var(--text-3);
  font-weight: 600;
}

.grade-box {
  flex: 0 0 auto;
  min-width: 92px;
  padding: 10px 12px;
  background: #fffaf1;
  border: 1px solid var(--orange-line);
  border-radius: 10px;
  text-align: center;
}

.grade-label {
  font-size: 12px;
  color: #9a6b1f;
  margin-bottom: 6px;
  line-height: 1;
}

.grade-value {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 52px;
  height: 26px;
  padding: 0 12px;
  border-radius: 13px;
  font-size: 13px;
  font-weight: 700;
  color: #d97706;
  background: var(--orange-soft);
  border: 1px solid var(--orange-line);
}

/* 页面内容 */
.pages {
  flex: 1;
  overflow-y: auto;
  padding: 0 10px 10px;
}

.page {
  display: block;
}

/* 卡片列表 */
.card-list {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.qc-card {
  position: relative;
  background: var(--panel);
  border: 1px solid var(--line);
  border-radius: 12px;
  padding: 12px;
  box-shadow: var(--shadow);
}

.qc-card.urgent {
  border-left: 3px solid var(--red);
  background: var(--red-soft);
}

.qc-card.suggestion {
  border-left: 3px solid var(--blue);
  background: var(--blue-soft);
}

.card-head {
  display: flex;
  align-items: center;
  gap: 6px;
  padding-right: 120px;
  margin-bottom: 12px;
}

.card-head.space {
  justify-content: space-between;
  padding-right: 0;
}

.card-head-left {
  display: flex;
  align-items: center;
  gap: 6px;
  min-width: 0;
}

.pill {
  height: 24px;
  padding: 0 9px;
  border-radius: 6px;
  border: 1px solid transparent;
  font-size: 12px;
  font-weight: 700;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  white-space: nowrap;
}

.pill-red {
  color: #fff;
  background: var(--red);
}

.pill-blue {
  color: #fff;
  background: var(--blue);
}

.pill-purple {
  color: #fff;
  background: var(--purple);
}

.pill-outline {
  color: var(--blue);
  background: #f7faff;
  border-color: var(--blue-line);
}

.pill-ring {
  height: 22px;
  padding: 0 8px;
  border-radius: 999px;
  border: 1px solid #dfe5ec;
  background: transparent;
  color: #8b95a5;
  font-size: 12px;
  font-weight: 700;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  white-space: nowrap;
}

.card-meta {
  position: absolute;
  top: 12px;
  right: 12px;
  display: inline-flex;
  align-items: center;
  gap: 6px;
}

.deduct {
  position: absolute;
  top: 12px;
  right: 12px;
  width: 60px;
  height: 28px;
  padding: 0 8px;
  border-radius: 8px;
  background: transparent;
  border: 0;
  color: var(--red);
  font-size: 12px;
  font-weight: 700;
  display: flex;
  align-items: center;
  justify-content: center;
  text-align: center;
}

.card-meta .deduct {
  position: static;
}

.qc-icon {
  width: 18px;
  height: 18px;
  background-repeat: no-repeat;
  background-position: center;
  background-size: 18px 18px;
  flex: 0 0 auto;
}

.qc-human {
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='18' height='18' viewBox='0 0 24 24' fill='none'%3E%3Cpath d='M12 12a4 4 0 1 0-4-4 4 4 0 0 0 4 4Z' stroke='%23f59e0b' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'/%3E%3Cpath d='M20 21a8 8 0 0 0-16 0' stroke='%23f59e0b' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E");
}

.qc-auto {
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='18' height='18' viewBox='0 0 24 24' fill='none'%3E%3Cpath d='M4 12a8 8 0 0 1 14.9-4' stroke='%232f6bff' stroke-width='2' stroke-linecap='round'/%3E%3Cpath d='M20 12a8 8 0 0 1-14.9 4' stroke='%232f6bff' stroke-width='2' stroke-linecap='round'/%3E%3Cpath d='M18 4v5h-5' stroke='%232f6bff' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'/%3E%3Cpath d='M6 20v-5h5' stroke='%232f6bff' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E");
}

.qc-model {
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='18' height='18' viewBox='0 0 24 24' fill='none'%3E%3Cpath d='M7 7h10v10H7z' stroke='%236f42ef' stroke-width='2' stroke-linejoin='round'/%3E%3Cpath d='M9 3v2M15 3v2M9 19v2M15 19v2M3 9h2M3 15h2M19 9h2M19 15h2' stroke='%236f42ef' stroke-width='2' stroke-linecap='round'/%3E%3C/svg%3E");
}

.field-label {
  font-size: 13px;
  color: var(--text-3);
  margin-bottom: 4px;
}

.field-value {
  font-size: 15px;
  line-height: 1.7;
  color: var(--text-1);
  margin-bottom: 12px;
  word-break: break-word;
  overflow-wrap: anywhere;
}

/* 质控依据 */
.evidence {
  background: var(--panel-soft);
  border: 1px solid var(--line);
  border-radius: 10px;
  overflow: hidden;
}

.evidence-head {
  height: 38px;
  padding: 0 12px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  border-bottom: 1px solid var(--line-soft);
  cursor: pointer;
}

.evidence-title {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 13px;
  font-weight: 700;
  color: var(--text-2);
}

.evidence-bar {
  width: 3px;
  height: 14px;
  border-radius: 2px;
  background: var(--blue);
}

.evidence-arrow {
  font-size: 12px;
  color: #8b95a5;
  transition: transform .2s ease;
}

.evidence-body {
  padding: 10px 12px;
}

.evidence-body.collapsed {
  display: none;
}

.evidence-head.collapsed .evidence-arrow {
  transform: rotate(-90deg);
}

.evidence-item {
  display: flex;
  align-items: flex-start;
  gap: 8px;
  font-size: 14px;
  line-height: 1.7;
  color: var(--text-2);
  overflow-wrap: anywhere;
  word-break: break-word;
}

.serial {
  width: 16px;
  height: 16px;
  margin-top: 4px;
  border-radius: 4px;
  background: #fff;
  border: 1px solid #d8e0ea;
  color: #98a2b3;
  font-size: 11px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex: 0 0 auto;
}

/* 卡片底部 */
.card-footer {
  margin-top: 10px;
  padding-top: 10px;
  border-top: 1px solid var(--line);
  display: flex;
  justify-content: flex-end;
  gap: 6px;
}

/* 病历生成 */
.gen-stack {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.gen-field {
  margin-top: 10px;
}

.gen-label {
  font-size: 12px;
  color: var(--text-3);
  margin-bottom: 6px;
}

.gen-textarea {
  width: 100%;
  border: 1px solid #d8dee7;
  border-radius: 10px;
  background: #fff;
  padding: 10px 10px;
  font-size: 13px;
  color: var(--text-1);
  outline: none;
  box-shadow: 0 1px 0 rgba(31, 41, 55, 0.02);
  min-height: 110px;
  resize: vertical;
  line-height: 1.7;
  font-family: "Microsoft YaHei", "PingFang SC", Arial, sans-serif;
  overflow: auto;
  white-space: pre-wrap;
  word-break: break-word;
}

.gen-textarea:focus {
  border-color: #bfd3ff;
  box-shadow: 0 0 0 3px rgba(47, 107, 255, 0.12);
}

.gen-actions {
  margin-top: 10px;
  display: flex;
  gap: 8px;
  justify-content: flex-end;
}

/* 底部栏 */
.bottom-bar {
  position: relative;
  background: #f8fafc;
  border-top: 1px solid #dfe5ec;
  padding: 8px 10px;
  flex: 0 0 auto;
  display: flex;
  flex-direction: column;
  gap: 10px;
  max-height: 52px;
  overflow: hidden;
  transition: max-height .2s ease;
}

.bottom-bar.expanded {
  max-height: 210px;
}

.bottom-top {
  display: flex;
  align-items: center;
  gap: 8px;
  min-height: 32px;
}

.bottom-text {
  flex: 1;
  font-size: 13px;
  color: #5f6b7a;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 20px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.bt-item {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  flex: 1 1 0;
  min-width: 0;
  padding: 6px 12px;
  background: #f8fafc;
  border-radius: 8px;
}

.bt-label {
  color: #1f2937;
  font-weight: 600;
  font-size: 12px;
}

.bt-value {
  color: #475569;
  font-weight: 500;
  font-size: 12px;
}

.collapse-up {
  width: 0;
  height: 0;
  border-left: 7px solid transparent;
  border-right: 7px solid transparent;
  border-bottom: 12px solid #8b95a5;
  margin-left: 8px;
  cursor: pointer;
  transition: transform .2s ease;
}

.bottom-bar.expanded .collapse-up {
  transform: rotate(180deg);
}

/* 底部额外信息 */
.bottom-extra {
  display: flex;
  flex-direction: column;
  gap: 12px;
  font-size: 12px;
  color: #5f6b7a;
}

.bottom-stats {
  background: #f8fafc;
  border-radius: 8px;
  padding: 12px;
  border: 1px solid #e2e8f0;
}

.stat-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  margin-bottom: 8px;
}

.stat-item {
  display: flex;
  flex-direction: column;
  align-items: center;
  flex: 1;
}

.stat-label {
  font-size: 10px;
  color: #94a3b8;
  margin-bottom: 4px;
  font-weight: 700;
}

.stat-value {
  font-size: 16px;
  font-weight: 700;
  color: #1e293b;
}

.stat-value.urgent {
  color: var(--red);
}

.stat-value.suggestion {
  color: var(--blue);
}

.stat-status {
  padding: 4px 10px;
  border-radius: 12px;
  font-size: 10px;
  font-weight: 600;
}

.stat-status.done {
  background: var(--green-soft);
  color: var(--green);
  border: 1px solid var(--green-line);
}

.stat-time {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1px;
  font-size: 10px;
  color: #94a3b8;
  padding-top: 8px;
  border-top: 1px solid #e2e8f0;
}

.time-container {
  display: flex;
  align-items: center;
  gap: 0;
}

.time-label {
  margin: 0;
  padding: 0;
  font-weight: 700;
}

.time-value {
  margin: 0;
  padding: 0;
  color: #64748b;
}

/* 文书菜单按钮 */
.doc-menu-btn {
  background: var(--blue-soft);
  border: 1px solid var(--blue-line);
  color: var(--blue);
  padding: 8px 16px;
  border-radius: 20px;
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
  display: flex;
  align-items: center;
  gap: 4px;
  transition: all 0.2s ease;
  min-width: 120px;
}

.doc-menu-btn:hover {
  background: var(--blue);
  color: #ffffff;
  transform: translateY(-1px);
}

.menu-btn-text {
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  max-width: 150px;
}

/* 文书菜单 */
.doc-menu {
  background: rgba(33, 40, 48, 0.96);
  backdrop-filter: blur(2px);
  border: 1px solid rgba(255,255,255,0.06);
  border-radius: 12px;
  color: #e5e7eb;
  box-shadow: 0 10px 24px rgba(0,0,0,0.18);
  overflow: hidden;
}

.doc-menu-header {
  padding: 10px 12px;
  font-size: 12px;
  color: #cbd5e1;
  border-bottom: 1px solid rgba(255,255,255,0.06);
}

.doc-menu-list {
  display: flex;
  flex-direction: column;
  padding: 6px 0;
  max-height: 240px;
  overflow-y: auto;
}

.doc-item {
  height: 34px;
  padding: 0 12px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  font-size: 13px;
  cursor: default;
}

.doc-item:hover {
  background: rgba(59, 130, 246, 0.18);
}

.doc-name {
  color: #e5e7eb;
}

.doc-count {
  color: #cbd5e1;
  opacity: 0.9;
}

/* 底部导航 */
.bottom-nav {
  display: flex;
  align-items: center;
  gap: 1px;
  justify-content: space-between;
}

.nav-btn {
  flex: 1 1 0;
  height: 32px;
  padding: 0 5px;
  border-radius: 16px;
  border: 1px solid #e6edf5;
  background: #ffffff;
  color: var(--text-2);
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  transition: all 0.2s ease;
  box-shadow: 0 1px 2px rgba(0,0,0,0.05);
}

.nav-btn:hover {
  border-color: var(--blue-line);
  background: var(--blue-soft);
  color: var(--blue);
  transform: translateY(-1px);
  box-shadow: 0 2px 4px rgba(47,107,255,0.1);
}

.nav-btn.active {
  box-shadow: 0 4px 8px rgba(0,0,0,0.2);
  transform: translateY(-2px);
}

.nav-btn.active:hover {
  transform: translateY(-3px);
  box-shadow: 0 6px 12px rgba(0,0,0,0.25);
}

.nav-login {
  background: linear-gradient(135deg, #2f6bff, #1e56cc);
  border-color: #2f6bff;
  color: #ffffff;
}

.nav-login:hover {
  background: linear-gradient(135deg, #1e56cc, #1442a0);
  border-color: #1e56cc;
  transform: translateY(-1px);
}

.nav-message {
  background: linear-gradient(135deg, #6f42ef, #5b34c1);
  border-color: #6f42ef;
  color: #ffffff;
}

.nav-message:hover {
  background: linear-gradient(135deg, #5b34c1, #4a2a99);
  border-color: #5b34c1;
  transform: translateY(-1px);
}

.nav-record {
  background: linear-gradient(135deg, #f59e0b, #d97706);
  border-color: #f59e0b;
  color: #ffffff;
}

.nav-record:hover {
  background: linear-gradient(135deg, #d97706, #b45309);
  border-color: #d97706;
  transform: translateY(-1px);
}

.nav-generate {
  background: linear-gradient(135deg, #10b981, #059669);
  border-color: #10b981;
  color: #ffffff;
}

.nav-generate:hover {
  background: linear-gradient(135deg, #059669, #047857);
  border-color: #059669;
  transform: translateY(-1px);
}

.nav-ai {
  background: linear-gradient(135deg, #f04438, #dc2626);
  border-color: #f04438;
  color: #ffffff;
}

.nav-ai:hover {
  background: linear-gradient(135deg, #dc2626, #b91c1c);
  border-color: #dc2626;
  transform: translateY(-1px);
}
</style>