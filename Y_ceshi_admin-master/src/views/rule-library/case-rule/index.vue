<template>
  <div class="rule-config-page">
    <div class="rule-app">
      <main class="main-content">
        <header class="topbar">
          <div class="title">
            <h1>住院病历规则库</h1>
            <p>
              <strong>病历评分标准：</strong>甲级：91~100分 乙级：75~90分
              丙级：0~74分
            </p>
          </div>
          <div>
            <el-button
              type="primary"
              @click="onCreate"
            >+ 新增可维护规则</el-button>
          </div>
        </header>

        <section class="content">
          <div class="summary-bar">
            <div class="summary-items">
              <div class="summary-item">
                <span class="summary-dot total" />
                <span class="summary-label">规则总量：</span>
                <span class="summary-value">{{
                  ruleStatistics.count_statistics.total_rules
                }}</span>
              </div>
              <div class="summary-item">
                <span class="summary-dot enable" />
                <span class="summary-label">启用规则：</span>
                <span class="summary-value">{{
                  ruleStatistics.count_statistics.enabled_rules
                }}</span>
              </div>
              <div class="summary-item">
                <span class="summary-dot disable" />
                <span class="summary-label">停用规则：</span>
                <span class="summary-value">{{
                  ruleStatistics.count_statistics.disabled_rules
                }}</span>
              </div>
              <div class="summary-item">
                <span class="summary-dot unmap" />
                <span class="summary-label">模型规则：</span>
                <span class="summary-value">{{
                  ruleStatistics.count_statistics.model_rules
                }}</span>
              </div>
              <div class="summary-item">
                <span class="summary-dot maintain" />
                <span class="summary-label">可维护规则：</span>
                <span class="summary-value">{{
                  ruleStatistics.count_statistics.maintainable_rules
                }}</span>
              </div>
            </div>
            <el-button
              class="detail-btn"
              :class="{ active: showCards }"
              @click="toggleDetail"
            >{{ showCards ? "收起详情" : "查看详情" }}</el-button>
          </div>

          <div v-show="showCards" class="cards-container">
            <div class="time-range-tabs">
              <button
                class="tab-btn"
                :class="{ active: activeTab === 'type' }"
                @click="switchTab('type')"
              >
                规则类型
              </button>
              <button
                class="tab-btn"
                :class="{ active: activeTab === 'doc' }"
                @click="switchTab('doc')"
              >
                文书范围
              </button>
              <div
                style="
                  margin-left: auto;
                  display: flex;
                  align-items: center;
                  gap: 16px;
                "
              >
                <el-select
                  v-model="timeRange"
                  size="mini"
                  class="tag-select"
                  @change="updateTimeRange"
                >
                  <el-option label="近30天" value="30" />
                  <el-option label="近90天" value="90" />
                  <el-option label="近180天" value="180" />
                  <el-option label="近365天" value="all" />
                </el-select>
                <span class="range-stats">
                  <span class="stat-label">
                    <strong>质控正确率：</strong>
                    <span class="stat-tag blue">{{ overallAccuracy }}</span>
                  </span>
                  <span class="stat-label">
                    <strong>触发次数：</strong>
                    <span class="stat-tag orange">{{ overallTrigger }}</span>
                  </span>
                </span>
                <div class="trend-chart">
                  <svg
                    width="160"
                    height="48"
                    viewBox="0 0 160 48"
                    class="trend-svg"
                  >
                    <defs>
                      <linearGradient
                        id="trendGradient"
                        x1="0%"
                        y1="0%"
                        x2="0%"
                        y2="100%"
                      >
                        <stop
                          offset="0%"
                          style="stop-color: #1b64b0; stop-opacity: 0.2"
                        />
                        <stop
                          offset="100%"
                          style="stop-color: #1b64b0; stop-opacity: 0"
                        />
                      </linearGradient>
                    </defs>
                    <polygon fill="url(#trendGradient)" :points="trendPoints" />
                    <polyline
                      fill="none"
                      stroke="#1B64B0"
                      stroke-width="2"
                      :points="trendPoints"
                    />
                    <circle
                      v-for="(point, index) in trendData"
                      :key="index"
                      :cx="point.cx"
                      :cy="point.cy"
                      r="2"
                      fill="#1B64B0"
                      class="trend-point"
                      :data-month="point.month"
                      :data-accuracy="point.accuracy"
                      :data-trigger="point.trigger"
                      :class="{ active: activePointIndex === index }"
                      @click="showTrendInfo(point, index)"
                    />
                    <text
                      v-for="(label, index) in monthLabels"
                      :key="'label' + index"
                      :x="label.x"
                      y="44"
                      font-size="8"
                      fill="#8A97A8"
                      text-anchor="middle"
                    >
                      {{ label.text }}
                    </text>
                  </svg>
                  <div class="trend-info" :class="{ show: trendInfoVisible }">
                    <div class="trend-info-title">{{ trendInfoTitle }}</div>
                    <div class="trend-info-content">
                      <span class="trend-info-item trend-accuracy">
                        <i class="trend-dot" />正确率:
                        <strong>{{ trendInfoAccuracy }}</strong>
                      </span>
                      <span class="trend-info-item trend-trigger">
                        <i class="trend-dot" />触发次数:
                        <strong>{{ trendInfoTrigger }}</strong>
                      </span>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- 文书范围卡片 -->
            <div v-show="activeTab === 'doc'" class="cards">
              <StatCard
                v-for="item in docCardList"
                :id="item.id"
                :key="item.id"
                :title="item.title"
                :total="item.count"
                :enable="item.enable"
                :disable="item.disable"
                :model="item.model"
                :maintain="item.maintain"
                @double-click="handleCardDoubleClick"
              />
            </div>

            <!-- 规则类型卡片 -->
            <div v-show="activeTab === 'type'" class="cards">
              <StatCard
                v-for="item in typeCardList"
                :id="item.id"
                :key="item.id"
                :title="item.title"
                :total="item.count"
                :enable="item.enable"
                :disable="item.disable"
                :model="item.model"
                :maintain="item.maintain"
                @double-click="handleCardDoubleClick"
              />
            </div>
          </div>

          <!-- 查询条件栏 -->
          <div class="query-toolbar">
            <el-form
              ref="filterFormRef"
              :model="filterForm"
              :inline="true"
              size="small"
              label-width="80px"
            >
              <el-row :gutter="24">
                <el-col :span="4">
                  <el-form-item label="规则名称">
                    <el-input
                      v-model="filterForm.rule_name"
                      placeholder="请输入规则名称"
                      style="width: 198px"
                    />
                  </el-form-item>
                </el-col>
                <el-col :span="4">
                  <el-form-item label="专科类型">
                    <el-select
                      v-model="filterForm.department"
                      multiple
                      filterable
                      collapse-tags
                      clearable
                      placeholder="请选择"
                      style="width: 198px"
                    >
                      <el-option
                        v-for="item in deptOptions"
                        :key="item.id"
                        :label="item.name"
                        :value="item.name"
                      />
                    </el-select>
                  </el-form-item>
                </el-col>
                <el-col :span="4">
                  <el-form-item label="专病病种">
                    <el-select
                      v-model="filterForm.disease"
                      multiple
                      filterable
                      clearable
                      collapse-tags
                      placeholder="请选择"
                      style="width: 198px"
                    >
                      <el-option
                        v-for="item in diseaseOptions"
                        :key="item.id"
                        :label="item.name"
                        :value="item.name"
                      />
                    </el-select>
                  </el-form-item>
                </el-col>
                <el-col :span="4">
                  <el-form-item label="文书范围">
                    <el-select
                      v-model="filterForm.case_type"
                      multiple
                      clearable
                      filterable
                      collapse-tags
                      placeholder="请选择"
                      style="width: 198px"
                    >
                      <el-option
                        v-for="item in docOptions"
                        :key="item.id"
                        :label="item.name"
                        :value="item.name"
                      />
                    </el-select>
                  </el-form-item>
                </el-col>
                <el-col :span="4">
                  <el-form-item label="规则类型">
                    <el-select
                      v-model="filterForm.type"
                      multiple
                      filterable
                      clearable
                      collapse-tags
                      placeholder="请选择"
                      style="width: 198px"
                    >
                      <el-option
                        v-for="item in ruleTypeOptions"
                        :key="item.id"
                        :label="item.name"
                        :value="item.name"
                      />
                    </el-select>
                  </el-form-item>
                </el-col>
              </el-row>

              <!-- 第二行 4个 + 按钮 -->
              <el-row :gutter="24">
                <el-col :span="4">
                  <el-form-item label="问题级别">
                    <el-select
                      v-model="filterForm.level"
                      clearable
                      filterable
                      placeholder="全部"
                      style="width: 198px"
                    >
                      <el-option
                        v-for="item in levelOptions"
                        :key="item.id"
                        :label="item.name"
                        :value="item.name"
                      />
                    </el-select>
                  </el-form-item>
                </el-col>
                <el-col :span="4">
                  <el-form-item label="运行节点">
                    <el-select
                      v-model="filterForm.node"
                      clearable
                      filterable
                      placeholder="全部"
                      style="width: 198px"
                    >
                      <el-option
                        v-for="item in nodeOptions"
                        :key="item.id"
                        :label="item.name"
                        :value="item.name"
                      />
                    </el-select>
                  </el-form-item>
                </el-col>
                <el-col :span="4">
                  <el-form-item label="质控来源">
                    <el-select
                      v-model="filterForm.source"
                      multiple
                      filterable
                      clearable
                      collapse-tags
                      placeholder="请选择"
                      style="width: 198px"
                    >
                      <el-option
                        v-for="item in sourceOptions"
                        :key="item.id"
                        :label="item.name"
                        :value="item.name"
                      />
                    </el-select>
                  </el-form-item>
                </el-col>
                <el-col :span="4">
                  <el-form-item label="状态">
                    <el-select
                      v-model="filterForm.status"
                      clearable
                      placeholder="全部"
                      style="width: 198px"
                    >
                      <el-option
                        v-for="item in statusOptions"
                        :key="item.id"
                        :label="item.name"
                        :value="item.name"
                      />
                    </el-select>
                  </el-form-item>
                </el-col>
                <el-col :span="8" style="text-align: right">
                  <el-form-item>
                    <el-button @click="resetFilters">重置</el-button>
                    <el-button
                      type="primary"
                      @click="getRuleList"
                    >查询</el-button>
                  </el-form-item>
                </el-col>
              </el-row>
            </el-form>
          </div>

          <div class="table-wrap">
            <div class="table-head">
              <div>
                <strong>规则列表</strong>
                <span>共 {{ tableData.length }} 条规则</span>
              </div>
              <div class="table-actions">
                <el-button
                  size="small"
                  class="tag-btn default restore"
                  icon="el-icon-refresh-right"
                  @click="showRestoreModal"
                >恢复</el-button>
                <el-button
                  size="small"
                  icon="el-icon-download"
                  @click="exportRules"
                >导出</el-button>
                <el-button
                  type="primary"
                  size="small"
                  icon="el-icon-plus"
                  @click="newRule"
                >新增</el-button>
              </div>
            </div>
            <el-table
              :data="tableData"
              border
              stripe
              style="width: 100%"
              size="small"
            >
              <el-table-column
                type="index"
                label="序号"
                width="50"
                align="center"
              />
              <el-table-column
                prop="category"
                label="文书范围"
                width="100"
                align="center"
              />
              <el-table-column prop="notice" label="规则名称" width="600">
                <template slot-scope="scope">
                  <div class="rule-name" @click="showRuleDetail(scope.row)">
                    {{ scope.row.notice }}
                  </div>
                </template>
              </el-table-column>
              <el-table-column
                prop="type"
                label="规则类型"
                width="80"
                align="center"
              />
              <el-table-column
                prop="level"
                label="问题级别"
                width="80"
                align="center"
              >
                <template slot-scope="scope">
                  <el-tag
                    :type="scope.row.level === 1 ? 'danger' : ''"
                    size="mini"
                  >{{ scope.row.level === 1 ? "必改" : "建议" }}</el-tag>
                </template>
              </el-table-column>
              <el-table-column
                prop="department"
                label="专科类型"
                width="80"
                align="center"
              />
              <el-table-column
                prop="disease"
                label="专病病种"
                width="100"
                align="center"
              >
                <template slot-scope="scope">{{
                  scope.row.disease || "-"
                }}</template>
              </el-table-column>
              <el-table-column
                prop="warningTime"
                label="预警时间"
                width="80"
                align="center"
              >
                <template slot-scope="scope">{{
                  scope.row.warningTime || "-"
                }}</template>
              </el-table-column>
              <el-table-column
                prop="one_no"
                label="单否项"
                width="60"
                align="center"
              >
                <template slot-scope="scope">{{
                  scope.row.one_no == 1 ? "是" : "否"
                }}</template>
              </el-table-column>
              <el-table-column
                prop="node"
                label="运行节点"
                width="90"
                align="center"
              />
              <el-table-column
                prop="score"
                label="分值"
                width="70"
                align="center"
              >
                <template
                  slot-scope="scope"
                >{{ scope.row.score || "-0.5" }}分</template>
              </el-table-column>
              <el-table-column
                prop="status"
                label="状态"
                width="80"
                align="center"
              >
                <template slot-scope="scope">
                  <el-switch
                    v-model="scope.row.status"
                    active-color="#13ce66"
                    :active-value="1"
                    :inactive-value="2"
                    size="mini"
                    @change="handleStatusChange(scope.row)"
                  />
                </template>
              </el-table-column>
              <el-table-column
                prop="id"
                label="规则ID"
                width="70"
                align="center"
              />
              <el-table-column
                prop="basis_source"
                label="质控来源"
                width="80"
                align="center"
              >
                <template slot-scope="scope">
                  <span>{{
                    scope.row.is_ai === 1
                      ? "系统"
                      : scope.row.is_ai === 2
                        ? "人工"
                        : scope.row.is_ai === 3
                          ? "大模型"
                          : "-"
                  }}</span>
                </template>
              </el-table-column>
              <el-table-column
                prop="quality_accuracy_rate"
                label="准确率"
                width="70"
                align="center"
              >
                <template slot-scope="scope">
                  <el-tag
                    :type="getAccuracyType(scope.row.accuracy)"
                    size="mini"
                  >{{ scope.row.quality_accuracy_rate }}%</el-tag>
                </template>
              </el-table-column>
              <el-table-column
                prop="quality_count"
                label="触发次数"
                width="100"
                align="center"
                sortable
              />
              <el-table-column
                prop="updated_by"
                label="更新人"
                width="100"
                align="center"
                sortable
              />
              <el-table-column
                prop="updated_at"
                label="更新时间"
                width="140"
                align="center"
                sortable
              />
              <el-table-column
                label="操作"
                width="100"
                align="center"
                fixed="right"
              >
                <template slot-scope="scope">
                  <el-button
                    type="text"
                    size="mini"
                    @click="editRule(scope.row)"
                  >修改</el-button>
                  <el-button
                    type="text"
                    size="mini"
                    class="danger-text"
                    @click="handleDel(scope.row)"
                  >删除</el-button>
                </template>
              </el-table-column>
            </el-table>

            <div class="pagination">
              <el-pagination
                :current-page="pageParams.page"
                :page-sizes="[10, 20, 50, 100]"
                :page-size="pageParams.page_size"
                layout="total, sizes, prev, pager, next, jumper"
                :total="pageParams.count"
                background
                small
                @size-change="handleSizeChange"
                @current-change="handleCurrentChange"
              />
            </div>
          </div>
        </section>
      </main>
    </div>

    <!-- 规则详情弹窗 -->
    <el-dialog
      :title="isEditMode ? '编辑规则' : '规则详情'"
      :visible.sync="ruleDetailVisible"
      width="800px"
      height="75vh"
      top="10vh"
      class="rule-detail-dialog"
    >
      <el-form
        :model="currentRule"
        label-width="80px"
        size="small"
        :disabled="!isEditMode"
      >
        <el-form-item label="规则名称">
          <el-input
            v-model="currentRule.notice"
            :disabled="!isEditMode"
            placeholder="请输入规则名称"
          />
        </el-form-item>
        <el-form-item label="触发条件">
          <el-input
            v-model="currentRule.trigger_condition"
            :disabled="!isEditMode"
            type="textarea"
            :rows="3"
            placeholder="请输入触发条件"
          />
        </el-form-item>
        <el-form-item label="判断口径">
          <el-input
            v-model="currentRule.judgment_caliber"
            :disabled="!isEditMode"
            type="textarea"
            :rows="5"
            placeholder="请输入判断口径"
          />
        </el-form-item>
        <el-form-item label="质控依据">
          <el-input
            v-model="currentRule.quality_basis"
            :disabled="!isEditMode"
            type="textarea"
            :rows="3"
            placeholder="请输入质控依据"
          />
        </el-form-item>
        <el-row :gutter="16">
          <el-col :span="6">
            <el-form-item label="质控项目" prop="title">
              <el-input
                v-model="currentRule.title"
                :disabled="!isEditMode"
                placeholder="请输入质控项目"
              />
            </el-form-item>
          </el-col>
          <el-col :span="6">
            <el-form-item label="状态" prop="status">
              <el-select
                v-model="currentRule.status"
                :disabled="!isEditMode"
                placeholder="请选择状态"
                style="width: 100%"
              >
                <el-option label="启用" :value="1" />
                <el-option label="停用" :value="2" />
              </el-select>
            </el-form-item>
          </el-col>
          <el-col :span="6">
            <el-form-item label="运行节点">
              <el-select
                v-model="currentRule.node"
                :disabled="!isEditMode"
                clearable
                placeholder="全部"
                style="width: 100%"
              >
                <el-option
                  v-for="item in nodeOptions"
                  :key="item.id"
                  :label="item.name"
                  :value="item.name"
                />
              </el-select>
            </el-form-item>
          </el-col>
          <el-col :span="6">
            <el-form-item label="科室" prop="department">
              <el-select
                v-model="currentRule.department"
                :disabled="!isEditMode"
                clearable
                filterable
                placeholder="请选择"
                style="width: 100%"
              >
                <el-option
                  v-for="item in deptList"
                  :key="item.id"
                  :label="item.dep_name"
                  :value="item.dep_name"
                />
              </el-select>
            </el-form-item>
          </el-col>

          <el-col :span="6">
            <el-form-item label="质控分类" prop="category">
              <el-select
                v-model="currentRule.category"
                :disabled="!isEditMode"
                clearable
                placeholder="请选择"
                style="width: 100%"
              >
                <el-option
                  v-for="item of categorys"
                  :key="item"
                  :label="item"
                  :value="item"
                />
              </el-select>
            </el-form-item>
          </el-col>
          <el-col :span="6">
            <el-form-item label="扣分" prop="score">
              <el-input
                v-model="currentRule.score"
                controls-position="right"
                placeholder="请输入"
                style="width: 100%"
                oninput="this.value = this.value.replace(/[^\d.]/g, '').replace(/^\./g, '').replace(/\.{2,}/g, '.').replace('.', '$#$').replace(/\./g, '').replace('$#$', '.').replace(/^(\d+)\.(\d*)\.$/, '$1.$2')"
              />
            </el-form-item>
          </el-col>

          <el-col :span="6">
            <el-form-item label="质控类型" prop="type">
              <el-select
                v-model="currentRule.type"
                :disabled="!isEditMode"
                clearable
                filterable
                placeholder="请选择"
                style="width: 100%"
              >
                <el-option
                  v-for="item of types"
                  :key="item"
                  :label="item"
                  :value="item"
                />
              </el-select>
            </el-form-item>
          </el-col>
          <el-col :span="6">
            <el-form-item label="单项否决" prop="one_no">
              <el-select
                v-model="currentRule.one_no"
                :disabled="!isEditMode"
                clearable
                placeholder="请选择"
                style="width: 100%"
              >
                <el-option
                  v-for="item of oneNos"
                  :key="item.value"
                  :label="item.name"
                  :value="item.value"
                />
              </el-select>
            </el-form-item>
          </el-col>

          <el-col :span="6">
            <el-form-item label="问题等级" prop="level">
              <el-select
                v-model="currentRule.level"
                clearable
                :disabled="!isEditMode"
                placeholder="请选择等级"
                style="width: 100%"
              >
                <el-option label="强制" :value="1" />
                <el-option label="建议" :value="2" />
              </el-select>
            </el-form-item>
          </el-col>
          <el-col :span="6">
            <el-form-item prop="disease" label="病种">
              <el-select
                v-model="currentRule.disease"
                :disabled="!isEditMode"
                clearable
                filterable
                placeholder="请选择"
                style="width: 100%"
              >
                <el-option
                  v-for="item in diseaseOptions"
                  :key="item.id"
                  :label="item.name"
                  :value="item.name"
                />
              </el-select>
            </el-form-item>
          </el-col>

          <el-col :span="6">
            <el-form-item label="规则来源">
              <el-input v-model="currentRuleComp" disabled />
            </el-form-item>
          </el-col>
          <el-col :span="6">
            <el-form-item label="规则ID">
              <el-input v-model="currentRule.id" disabled />
            </el-form-item>
          </el-col>
        </el-row>

        <el-form-item label="依据来源">
          <el-input
            v-model="currentRule.basis_source"
            :disabled="!isEditMode"
            type="textarea"
            :rows="2"
            placeholder="请输入依据来源"
          />
        </el-form-item>
        <el-form-item label="数据来源">
          <el-input
            v-model="currentRule.data_source"
            :disabled="!isEditMode"
            type="textarea"
            :rows="3"
            placeholder="请输入数据来源"
          />
        </el-form-item>

        <div class="quality-control-section">
          <div class="section-header">
            <span class="section-title">质控效果</span>
          </div>
          <div class="stats-grid">
            <div class="stat-card">
              <span class="stat-label">准确率</span>
              <span
                class="stat-value"
              >{{ currentRule.quality_accuracy_rate }}%</span>
            </div>
            <div class="stat-card">
              <span class="stat-label">触发次数</span>
              <span class="stat-value">{{ currentRule.quality_count }}</span>
            </div>
            <div class="stat-card">
              <span class="stat-label">人工纠正</span>
              <span class="stat-value">{{
                currentRule.artificial_correction_count == ""
                  ? "0"
                  : currentRule.artificial_correction_count
              }}</span>
            </div>
            <div class="stat-card">
              <span class="stat-label">申诉纠正</span>
              <span class="stat-value">{{
                currentRule.appeal_correction_count == ""
                  ? "0"
                  : currentRule.appeal_correction_count
              }}</span>
            </div>
          </div>
        </div>
      </el-form>
      <span slot="footer">
        <el-button
          v-if="!isEditMode"
          @click="ruleDetailVisible = false"
        >关闭</el-button>
        <el-button
          v-if="!isEditMode"
          type="primary"
          @click="toggleEditMode"
        >编辑</el-button>
        <el-button v-if="isEditMode" @click="cancelEdit">取消</el-button>
        <el-button
          v-if="isEditMode"
          type="primary"
          @click="saveRule"
        >保存</el-button>
      </span>
    </el-dialog>

    <!-- 删除确认 -->
    <el-dialog
      title="确认删除"
      :visible.sync="deleteModalVisible"
      width="400px"
    >
      <div style="text-align: center">
        <div style="margin-bottom: 16px">
          <i class="el-icon-warning" style="font-size: 48px; color: #d93026" />
        </div>
        <p style="color: #1f2d3d; font-size: 15px; margin-bottom: 8px">
          确定删除规则「{{ deleteRuleName }}」吗？
        </p>
        <p style="color: #8a97a8; font-size: 13px">
          删除后可在"恢复已删除"中找回。
        </p>
      </div>
      <span slot="footer">
        <el-button @click="deleteModalVisible = false">取消</el-button>
        <el-button type="danger" @click="confirmDelete">确认删除</el-button>
      </span>
    </el-dialog>

    <!-- 全局确认对话框 -->
    <el-dialog
      :visible.sync="restoreConfirmVisible"
      title="提示"
      width="400px"
      custom-class="confirm-dialog"
      :close-on-click-modal="false"
      append-to-body
    >
      <div style="padding: 20px 10px; text-align: center">
        <i
          class="el-icon-warning"
          style="font-size: 32px; color: #e6a23c; margin-bottom: 10px"
        />
        <p>{{ confirmRestoreText }}</p>
      </div>
      <span slot="footer">
        <el-button @click="restoreConfirmVisible = false">取消</el-button>
        <el-button type="primary" @click="confirmOk">确定</el-button>
      </span>
    </el-dialog>

    <!-- 恢复删除 -->
    <el-dialog title="恢复已删除规则" :visible.sync="restoreModalVisible">
      <div>
        <el-table
          v-if="deletedRules.length > 0"
          :data="deletedRules"
          border
          size="small"
        >
          <el-table-column prop="notice" width="240" label="规则名称" />
          <el-table-column prop="rule_type" label="规则类型" />
          <el-table-column prop="node" label="规则类型" />
          <el-table-column prop="updated_at" label="删除时间" />
          <el-table-column label="操作" align="center">
            <template slot-scope="scope">
              <el-popconfirm
                confirm-button-text="确认"
                cancel-button-text="取消"
                icon="el-icon-info"
                icon-color="red"
                title="确定要恢复该规则吗？"
                @confirm="restoreRule(scope.row)"
              >
                <el-button
                  slot="reference"
                  type="text"
                  size="mini"
                >恢复</el-button>
              </el-popconfirm>
            </template>
          </el-table-column>
        </el-table>
        <p v-else style="color: #8a97a8; text-align: center; padding: 20px">
          暂无已删除的规则
        </p>
      </div>
      <div class="pagination">
        <el-pagination
          :current-page="deletePageParams.page"
          :page-sizes="[10, 20, 50, 100]"
          :page-size="deletePageParams.page_size"
          layout="total, sizes, prev, pager, next, jumper"
          :total="deletePageParams.count"
          background
          small
          @size-change="delHandleSizeChange"
          @current-change="delHandleCurrentChange"
        />
      </div>
      <span slot="footer">
        <el-button @click="restoreModalVisible = false">关闭</el-button>
      </span>
    </el-dialog>

    <!-- 新增、编辑 病历规则 -->
    <CreateDialogSingle
      v-if="createData.bSwitch"
      :rule-type="ruleType"
      :data="createData"
      :source-options="sourceOptions"
      :disease-options="diseaseOptions"
      :departments="departmentList"
      :objects="objects"
      @refresh="handleRefresh"
    />
    <!-- 新增、编辑 首页规则 -->
    <HomePageRuleDialog
      v-if="homePageRuleData.bSwitch"
      :rule-type="ruleType"
      :data="homePageRuleData"
      :departments="departmentList"
      :objects="objects"
      @refresh="handleRefresh"
    />
    <!-- 规则详情 -->
    <RuleDialog
      v-if="ruleData.bSwitch"
      :data="ruleData"
      :source-options="sourceOptions"
      :disease-options="diseaseOptions"
      :departments="departmentList"
      :objects="objects"
    />
    <CreateDialogQuality
      v-if="createQualityData.bSwitch"
      :data="createQualityData"
      :types="types"
      :source-options="sourceOptions"
      :node-options="nodeOptions"
      :disease-options="diseaseOptions"
      :categorys="categorys"
      @refresh="handleRefresh"
    />
  </div>
</template>

<script>
import StatCard from './comp/StatCard.vue'
import CreateDialogSingle from './comp/CreateDialogSingle.vue'
import RuleDialog from '@/views/rule/config/components/RuleDialog.vue'
import HomePageRuleDialog from '@/views/rule/config/components/HomePageRuleDialog.vue'
import {
  getCategory,
  createCaseRuleList,
  getType,
  getDepartmentList
} from '@/api/admin'
import CreateDialogQuality from './comp/CreateDialog.vue'
import {
  get_rule_statistics,
  get_all_rule_list,
  get_custom_template_departments,
  get_custom_template_diseases,
  get_rule_setting_other,
  get_deleted_rule_list,
  get_select_department2,
  restore_rule
} from '@/api/rule/data'
import { export_all_rule_list } from '@/api/excel'
import {
  del_rule,
  edit_rule_status,
  get_select_object,
  get_select_department
} from '@/api/rule/config'
export default {
  name: 'RuleConfig',
  components: {
    StatCard,
    CreateDialogSingle,
    RuleDialog,
    HomePageRuleDialog,
    CreateDialogQuality
  },
  data() {
    return {
      restoreConfirmVisible: false,
      confirmRestoreText: '确定要恢复该数据吗？',
      confirmCallback: null,
      ruleStatistics: {
        count_statistics: {
          total_rules: 0,
          enabled_rules: 0,
          disabled_rules: 0,
          model_rules: 0,
          maintainable_rules: 0
        },
        document_scope_statistics: [], // 文书范围
        type_statistics: [], // 规则分类
        quality_record_statistics: {
          end_time: '',
          start_time: '',
          quality_accuracy_rate: 0,
          trigger_count: 0,
          monthly_quality_counts: []
        } // 质量记录
      },
      createData: {
        bSwitch: false,
        row: {},
        isParamsEx: false
      },
      createQualityData: {
        bSwitch: false,
        isDisable: false,
        row: {}
      },
      homePageRuleData: {
        bSwitch: false,
        row: {}
      },
      ruleData: {
        bSwitch: false,
        row: {},
        isParamsEx: false
      },
      ruleType: 1,
      tableData: [],
      pageParams: {
        count: 0,
        page: 1,
        page_size: 10
      },
      deletePageParams: {
        count: 0,
        page: 1,
        page_size: 10
      },
      categorys: [],
      objects: [],
      sidebarExpanded: false,
      showCards: false,
      activeTab: 'type',
      timeRange: '30',
      overallAccuracy: '0%',
      overallTrigger: '0',
      totalCount: 0,
      enableCount: 0,
      disableCount: 0,
      modelCount: 0,
      maintainCount: 0,
      activePointIndex: -1,
      trendClickOutsideHandler: null,

      filterForm: {
        rule_name: '', // 规则名称
        department: [], // 专科类型
        disease: [], // 专病病种
        case_type: [], // 文书范围
        type: [], // 规则类型
        level: '', // 问题级别
        node: '', // 运行节点
        source: [], // 质控来源
        status: '' // 状态
      },

      // 下拉选项（接口获取）
      deptOptions: [], // 专科类型
      deptOptions2: [], // 专科类型2
      diseaseOptions: [], // 专病病种
      docOptions: [], // 文书范围
      ruleTypeOptions: [], // 规则类型
      levelOptions: [], // 问题级别
      nodeOptions: [], // 运行节点
      sourceOptions: [], // 质控来源
      statusOptions: [], // 状态

      departmentList: [],
      docCardList: [],
      typeCardList: [],
      rules: [],
      types: [],
      deletedRules: [],
      ruleDetailVisible: false,
      deleteModalVisible: false,
      restoreModalVisible: false,
      currentRule: {
        id: '', // 规则id
        category: '', // 文书范围
        options: '',
        title: '', // 标题
        notice: '', // 规则名称
        rule: '',
        number1: '',
        number2: '',
        score: '', // 分数
        status: '', // 状态 1开启 0关闭
        created_at: '', // 创建时间
        type: '', // 规则类型
        level: '', // 级别 1必改 2建议
        is_ai: '', // 1系统 2人工 3大模型
        updated_at: '', // 更新时间
        is_shizhong: '',
        department: '', // 专科类型
        disease: '', // 病种
        trigger_condition: '', // 触发条件
        judgment_caliber: '', // 判断口径
        quality_basis: '', // 质控依据
        basis_source: '', // 依据来源
        data_source: '', // 数据来源
        node: '', // 运行节点
        one_no: '', // 是否单否 0否 1是
        warningTime: '', // 预警时间
        case_type: '',
        object: '',
        description: '',
        updated_by: '', // 更新人
        is_custom: '', // 是否自定义 0否 1是
        quality_count: '', // 质控次数
        quality_accuracy_rate: '', // 质控准确率
        artificial_correction_count: '', // 人工纠正
        appeal_correction_count: '' // 申诉纠正
      },
      isEditMode: false,
      deleteRuleName: '',
      deleteIndex: -1,
      trendInfoVisible: false,
      trendInfoTitle: '近一年趋势',
      trendInfoAccuracy: '',
      trendInfoTrigger: '',
      trendData: [],
      monthLabels: [],
      statusArr: [
        { id: 1, name: '启用' },
        { id: 2, name: '停用' }
      ],
      startTime: '',
      endTime: '',
      deptList: []
    }
  },
  computed: {
    trendPoints() {
      return this.trendData.map((p) => `${p.cx},${p.cy}`).join(' ')
    },
    currentRuleComp() {
      if (this.currentRule.is_ai === 0) {
        return '自定义'
      } else if (this.currentRule.is_ai === 1) {
        return '系统'
      } else if (this.currentRule.is_ai === 2) {
        return '人工'
      } else {
        return '大模型'
      }
    }
  },
  created() {
    this.updateTimeRange('30')
    this.getDictData()
    this.get_rule_statisticsM()
    this.getRuleList()
    this.getObjectData(1)
    this.getDepartmentData()
    this.getCategoryM()
    this.getTypeM()
    this.getDeptList()
  },
  methods: {
    // 获取所有下拉字典
    async getDictData() {
      try {
        // 专科类型
        const dept = await get_custom_template_departments()
        this.deptOptions = dept.p || []

        const depts = await get_select_department2()
        this.deptOptions2 = depts.p || []

        // 专病病种
        const disease = await get_custom_template_diseases()
        this.diseaseOptions = disease.p || []

        // 文书范围
        const doc = await get_rule_setting_other(1, 0)
        this.docOptions = doc.p || []

        // 规则类型
        const ruleType = await get_rule_setting_other(2, 0)
        this.ruleTypeOptions = ruleType.p || []

        // 运行节点
        const node = await get_rule_setting_other(3, 0)
        this.nodeOptions = node.p || []

        // 问题级别
        const level = await get_rule_setting_other(4, 0)
        this.levelOptions = level.p || []

        // 质控来源
        const source = await get_rule_setting_other(5, 0)
        this.sourceOptions = source.p || []

        // 状态
        const status = await get_rule_setting_other(6, 0)
        this.statusOptions = status.p || []
      } catch (err) {
        console.error('获取字典失败', err)
      }
    },

    getDeptList() {
      getDepartmentList().then((res) => {
        this.deptList = res.p || []
      })
    },
    getTypeM() {
      getType()
        .then((res) => {
          const { p } = res
          this.types = p
        })
        .catch((error) => {
          console.log(error)
        })
    },

    getCategoryM() {
      getCategory()
        .then((res) => {
          const { p } = res
          this.categorys = p
        })
        .catch((error) => {
          console.log(error)
        })
    },
    getObjectData(type) {
      // 构建请求参数对象
      const params = {}

      // 如果传入了type参数，添加到请求中
      if (type !== undefined) {
        params.rule_type = type
      }

      // 发起API请求获取对象数据
      get_select_object(params)
        .then((res) => {
          const { p } = res
          this.objects = Array.isArray(p) ? p : []
          console.log('获取到质控项目数据:', this.objects)
        })
        .catch((error) => {
          console.error('获取质控项目数据失败:', error)
        })
    },

    getDepartmentData() {
      get_select_department().then((res) => {
        const { p } = res
        this.departmentList = Array.isArray(p) ? p : []
      })
    },

    get_rule_statisticsM() {
      get_rule_statistics({
        start_time: this.startTime,
        end_time: this.endTime
      }).then((res) => {
        this.ruleStatistics = res.p

        this.overallAccuracy =
          this.ruleStatistics.quality_record_statistics.quality_accuracy_rate
        this.overallTrigger =
          this.ruleStatistics.quality_record_statistics.trigger_count
        // { cx: 30, cy: 28, month: "1月", accuracy: "82%", trigger: "234" },
        const monthData =
          this.ruleStatistics.quality_record_statistics
            .monthly_quality_counts || []
        this.trendData = monthData.map((item, index) => {
          return {
            cx: 30 + index * 10,
            cy: 30,
            month: item.month,
            accuracy: '10%',
            trigger: '20'
          }
        })

        this.monthLabels = this.trendData
          .filter((_, idx) => idx % 2 === 0)
          .map((item) => ({
            x: item.cx,
            text: item.month.slice(5)
          }))

        if (this.ruleStatistics.type_statistics.length > 0) {
          const newArr = this.ruleStatistics.type_statistics.map((item) => {
            return {
              id: item.name,
              title: item.name,
              count: item.count,
              enable: item.enabled_rules,
              disable: item.disabled_rules,
              model: item.model_rules,
              maintain: item.maintainable_rules
            }
          })
          this.typeCardList = newArr
        }
        if (this.ruleStatistics.document_scope_statistics.length > 0) {
          const newArr = this.ruleStatistics.document_scope_statistics.map(
            (item) => {
              return {
                id: item.name,
                title: item.name,
                count: item.count,
                enable: item.enabled_rules,
                disable: item.disabled_rules,
                model: item.model_rules,
                maintain: item.maintainable_rules
              }
            }
          )
          this.docCardList = newArr
        }
      })
    },

    handleStatusChange(rule) {
      if (rule.is_custom === 0) {
        this.handleStatusChangeQuality(rule)
      }
      // 自定义规则
      if (rule.is_custom === 1) {
        this.handleStatusChangeSingle(rule)
      }
    },

    handleStatusChangeQuality(row) {
      const index = row.status ? 1 : 0
      this.$confirm(
        '确认要更改为 <strong>' +
        ['停用', '正常'][index] +
        '</strong> 状态吗？',
        '提示',
        {
          dangerouslyUseHTMLString: true,
          confirmButtonText: '确定',
          cancelButtonText: '取消',
          type: 'warning'
        }
      )
        .then(() => {
          createCaseRuleList(row)
            .then((res) => {
              this.$message.success(res.m || '操作成功')
              this.getRuleList()
            })
            .catch(function() {
              row.status = row.status === 0 ? 1 : 0
            })
        })
        .catch(function() {
          row.status = row.status === 0 ? 1 : 0
        })
    },

    handleStatusChangeSingle(row) {
      const statusIndex = this.statusArr.findIndex(
        (value) => parseInt(value.id) === parseInt(row.status)
      )
      console.log('statusIndex', statusIndex)
      this.$confirm(
        '确认要更改为 <strong>' +
        this.statusArr[statusIndex].name +
        '</strong> 状态吗？',
        '提示',
        {
          dangerouslyUseHTMLString: true,
          confirmButtonText: '确定',
          cancelButtonText: '取消',
          type: 'warning'
        }
      )
        .then(() => {
          edit_rule_status({ id: row.id, status: row.status })
            .then((res) => {
              this.$message.success(res.m || '操作成功')
              this.getRuleList()
            })
            .catch(function() {
              row.status = row.status === 1 ? 2 : 1
            })
        })
        .catch(function() {
          row.status = row.status === 1 ? 2 : 1
        })
    },

    // 重置
    resetFilters() {
      this.filterForm = {
        rule_name: '',
        department: [],
        disease: [],
        case_type: [],
        type: [],
        level: '',
        node: '',
        source: [],
        status: ''
      }
      this.pageParams.page = 1
      this.pageParams.page_size = 10
      this.getRuleList()
    },

    onCreate() {
      if (this.ruleType === 1 || this.ruleType === 3) {
        // 病历规则
        this.createData.row = { rule_type: this.ruleType }
        this.createData.bSwitch = true
        this.createData.isParamsEx = true
      } else if (this.ruleType === 2) {
        // 首页规则
        this.homePageRuleData.row = { rule_type: this.ruleType }
        this.homePageRuleData.bSwitch = true
      }
    },
    handleRefresh() {
      this.getRuleList()
    },

    onCreateQuality() {
      this.createQualityData.row = {}
      this.createQualityData.bSwitch = true
      this.createQualityData.isDisable = false
    },

    onEditQuality(row) {
      this.createQualityData.row = row
      this.createQualityData.bSwitch = true
      this.createQualityData.isDisable = false
    },

    handleDel(row) {
      if (row.is_custom === 0) {
        this.onDelQuality(row)
      }
      // 自定义规则
      if (row.is_custom === 1) {
        this.onDel(row)
      }
    },
    onDelQuality(row) {
      this.$confirm('是否确认删除该数据?', '提示', {
        confirmButtonText: '确定',
        cancelButtonText: '取消',
        type: 'warning'
      })
        .then(() => {
          row.status = 3
          createCaseRuleList(row)
            .then((res) => {
              this.$message.success(res.m || '操作成功')
              this.getRuleList()
            })
            .catch(function() {
              // row.status = row.status === 0 ? 1 : 0
            })
        })
        .catch(function() {
          // row.status = row.status === 0 ? 1 : 0
        })
    },

    onEdit(row) {
      if (this.ruleType === 1 || this.ruleType === 3) {
        // 病历规则
        this.createData.row = row
        this.createData.bSwitch = true
        this.createData.isParamsEx = true
        this.createData.actionType = 'EDIT'
      } else if (this.ruleType === 2) {
        // 首页规则
        this.homePageRuleData.row = row
        this.homePageRuleData.bSwitch = true
      }
    },
    // 删除规则
    onDel(row) {
      this.$confirm('是否确认删除该数据?', '提示', {
        confirmButtonText: '确定',
        cancelButtonText: '取消',
        type: 'warning'
      }).then(() => {
        del_rule({ id: row.id }).then((res) => {
          this.$message.success(res.m || '操作成功')
          this.getRuleList()
        })
      })
    },
    // 查看规则
    onRuleDetail(row) {
      if (this.ruleType === 1 || this.ruleType === 3) {
        // 病历规则
        this.createData.row = row
        this.createData.bSwitch = true
        this.createData.isParamsEx = true
        this.createData.actionType = 'DETAIL'
      } else {
        this.ruleData.row = row
        this.ruleData.bSwitch = true
        this.ruleData.isParamsEx = true
      }
    },
    getRuleList() {
      const params = {
        ...this.filterForm,
        start_time: this.startTime,
        end_time: this.endTime,
        page: this.pageParams.page,
        page_size: this.pageParams.page_size
      }
      get_all_rule_list(params).then((res) => {
        this.tableData = res.p.list
        this.pageParams.count = res.p.count
      })
    },

    getDeletedRuleListM() {
      const params = {
        page: this.deletePageParams.page,
        page_size: this.deletePageParams.page_size
      }
      get_deleted_rule_list(params).then((res) => {
        this.deletedRules = res.p.list
        this.deletePageParams.count = res.p.count
      })
    },

    handleCardDoubleClick(title) {
      this.resetFilters()
      console.log(title)
      this.filterForm.type.push(title)
      this.getRuleList()
      this.$message.success(`已筛选：${title}`)
      setTimeout(() => {
        document.querySelector('.table-wrap').scrollIntoView({
          behavior: 'smooth',
          block: 'start'
        })
      }, 100)
    },

    toggleDetail() {
      this.showCards = !this.showCards
    },
    switchTab(tab) {
      this.activeTab = tab
    },
    updateTimeRange(type) {
      const now = new Date()
      const endTime = this.formatDate(now)

      let startTime = ''
      if (type === '30') {
        startTime = this.formatDate(new Date(now.setDate(now.getDate() - 30)))
      } else if (type === '90') {
        startTime = this.formatDate(new Date(now.setDate(now.getDate() - 90)))
      } else if (type === '180') {
        startTime = this.formatDate(new Date(now.setDate(now.getDate() - 180)))
      } else if (type === 'all') {
        startTime = this.formatDate(new Date(now.setDate(now.getDate() - 365)))
      }

      this.startTime = startTime
      this.endTime = endTime
      this.get_rule_statisticsM()
    },
    formatDate(date) {
      const year = date.getFullYear()
      const month = String(date.getMonth() + 1).padStart(2, '0')
      const day = String(date.getDate()).padStart(2, '0')
      return `${year}-${month}-${day}`
    },
    showTrendInfo(point, index) {
      this.trendInfoTitle = point.month + '数据'
      this.trendInfoAccuracy = point.accuracy
      this.trendInfoTrigger = point.trigger
      this.trendInfoVisible = true
      this.activePointIndex = index

      if (this.trendClickOutsideHandler) {
        document.removeEventListener('click', this.trendClickOutsideHandler)
      }
      this.trendClickOutsideHandler = (e) => {
        const chart = document.querySelector('.trend-chart')
        if (!chart?.contains(e.target)) {
          this.trendInfoVisible = false
          this.activePointIndex = -1
          document.removeEventListener('click', this.trendClickOutsideHandler)
          this.trendClickOutsideHandler = null
        }
      }
      setTimeout(() => {
        document.addEventListener('click', this.trendClickOutsideHandler)
      }, 0)
    },

    filterRules() {
      this.pageParams.page = 1
    },

    handleSizeChange(val) {
      this.pageParams.page_size = val
      this.getRuleList()
    },

    handleCurrentChange(val) {
      this.pageParams.page = val
      this.getRuleList()
    },

    delHandleSizeChange(val) {
      this.deletePageParams.page_size = val
      this.getDeletedRuleListM()
    },

    delHandleCurrentChange(val) {
      this.deletePageParams.page = val
      this.getDeletedRuleListM()
    },

    getAccuracyType(accuracy) {
      if (accuracy > 95) return 'success'
      if (accuracy < 60) return 'danger'
      return 'warning'
    },
    showRuleDetail(rule) {
      this.currentRule = { ...rule }

      if (rule.is_custom === 0) {
        // this.createQualityData.row = rule;
        // this.createQualityData.bSwitch = true;
        // this.createQualityData.isDisable = true;
        this.ruleDetailVisible = true
      }
      // 自定义规则
      if (rule.is_custom === 1) {
        this.onRuleDetail(rule)
      }
    },
    toggleEditMode() {
      this.isEditMode = true
    },
    cancelEdit() {
      this.isEditMode = false
      const original = this.rules.find((r) => r.id === this.currentRule.id)
      if (original) this.currentRule = { ...original }
    },
    saveRule() {
      createCaseRuleList(this.currentRule).then((res) => {
        this.$emit('refresh')
        this.$message.success('保存成功！')
        this.isEditMode = false
        this.ruleDetailVisible = false
        this.getRuleList()
        // this.$refs[formName].resetFields();
      })
      // const index = this.rules.findIndex((r) => r.id === this.currentRule.id);
      // if (index >= 0) {
      //   // this.currentRule.updateUser = "当前用户";
      //   // this.currentRule.updateTime = new Date()
      //   //   .toLocaleString("zh-CN", {
      //   //     year: "numeric",
      //   //     month: "2-digit",
      //   //     day: "2-digit",
      //   //     hour: "2-digit",
      //   //     minute: "2-digit",
      //   //   })
      //   //   .replace(/\//g, "/");
      //   // this.rules[index] = { ...this.currentRule };
      //   // this.filterRules();
      //   // this.isEditMode = false;

      // }
    },
    editRule(rule) {
      this.currentRule = { ...rule }
      // this.isEditMode = true;
      // this.ruleDetailVisible = true;
      // 质控规则
      if (rule.is_custom === 0) {
        this.onEditQuality(rule)
      }
      // 自定义规则
      if (rule.is_custom === 1) {
        this.onEdit(rule)
      }
    },
    newRule() {
      this.onCreateQuality()
    },
    customRuleConfig() {
      this.$message.info('示例页面：打开自定义规则配置页面')
    },

    exportRules() {
      const params = {
        ...this.filterForm,
        page: this.pageParams.page,
        page_size: this.pageParams.page_size
      }
      export_all_rule_list(params).then((res) => {
        const content = res.data // 后台返回二进制数据
        const blob = new Blob([content])
        const fileName = `规则清单.xlsx`
        if ('download' in document.createElement('a')) {
          // 非IE下载
          const elink = document.createElement('a')
          elink.download = fileName
          elink.style.display = 'none'
          elink.href = URL.createObjectURL(blob)
          document.body.appendChild(elink)
          elink.click()
          URL.revokeObjectURL(elink.href) // 释放URL 对象
          document.body.removeChild(elink)
        } else {
          // IE10+下载
          navigator.msSaveBlob(blob, fileName)
        }
      })
    },
    // delRule(rule) {
    //   this.deleteRuleName = rule.name;
    //   this.deleteIndex = this.rules.indexOf(rule);
    //   this.deleteModalVisible = true;
    // },
    confirmDelete() {
      if (this.deleteIndex >= 0) {
        const deleted = this.rules.splice(this.deleteIndex, 1)[0]
        deleted.deletedTime = new Date().toLocaleString()
        this.deletedRules.push(deleted)
        this.deleteModalVisible = false
        this.filterRules()
        this.$message.success('删除成功')
      }
    },
    showRestoreModal() {
      this.restoreModalVisible = true
      this.deletePageParams.page = 1
      this.deletePageParams.page_size = 10
      this.getDeletedRuleListM()
    },
    restoreRule(row) {
      restore_rule({ id: row.id, is_custom: row.is_custom }).then(() => {
        this.$message.success('恢复成功')
        this.getDeletedRuleListM()
        this.getRuleList()
      })
    },

    showConfirm(text, okCallback) {
      this.confirmRestoreText = text
      this.confirmCallback = okCallback
      this.restoreConfirmVisible = true
    },

    confirmOk() {
      this.restoreConfirmVisible = false
      if (this.confirmCallback) {
        this.confirmCallback()
      }
    }
  }
}
</script>
<style scoped>
::v-deep .rule-detail-dialog .el-dialog__body {
  max-height: 75vh;
  overflow-y: auto;
  padding: 10px 20px;
}

.quality-control-section {
  margin-top: 15px;
}
.section-header {
  font-weight: bold;
  margin-bottom: 10px;
}
.stats-grid {
  display: flex;
  gap: 16px;
}
.stat-card {
  flex: 1;
  padding: 10px;
  border: 1px solid #eee;
  border-radius: 4px;
  text-align: center;
}
</style>
<style lang="scss" scoped>
$sidebar-bg: #0f2f57;
$sidebar-width: 64px;
$sidebar-hover-width: 220px;
$primary: #1b64b0;
$card-bg: #fff;
$border-color: #e5eaf0;
$text-main: #1f2d3d;
$text-secondary: #667085;
$text-desc: #8a97a8;

.rule-detail-dialog {
  .el-dialog__body {
    padding: 10px 20px !important;
  }
  .dialog-body-wrapper {
    max-height: calc(75vh - 130px);
    overflow-y: auto;
    padding-right: 6px;
  }
}
.quality-control-section {
  margin-bottom: 16px;
  padding: 12px;
  background: #f7f8fa;
  border-radius: 8px;
}
.section-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 8px;
}
.section-title {
  font-size: 13px;
  font-weight: 600;
  color: #1f2d3d;
}
.section-subtitle {
  font-size: 11px;
  color: #8f959e;
}
.stats-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 8px;
}
.stat-card {
  background: #fff;
  padding: 8px;
  border-radius: 6px;
}
.stat-label {
  display: block;
  font-size: 11px;
  color: #8f959e;
  margin-bottom: 3px;
}
.stat-value {
  display: block;
  font-size: 16px;
  font-weight: 600;
  color: #1f2d3d;
}

.rule-config-page {
  width: 100%;
  height: 100%;
}

.rule-app {
  display: flex;
  overflow: hidden;
}

.main-content {
  flex: 1;
  display: flex;
  flex-direction: column;
  min-width: 0;
  overflow: hidden;
}

.topbar {
  height: 64px;
  background: #fff;
  border-bottom: 1px solid $border-color;
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0 24px;
  flex-shrink: 0;

  .title {
    h1 {
      margin: 0;
      font-size: 20px;
      color: $text-main;
    }
    p {
      margin: 4px 0 0;
      font-size: 12px;
      color: $text-desc;
    }
  }
}

.content {
  flex: 1;
  padding: 16px;
  overflow: auto;
}

// 统计栏
.summary-bar {
  background: $card-bg;
  border-radius: 12px;
  border: 1px solid $border-color;
  padding: 12px 16px;
  margin-bottom: 16px;
  display: flex;
  align-items: center;
  justify-content: space-between;

  .summary-items {
    display: flex;
    align-items: center;
    gap: 24px;
    flex-wrap: wrap;
  }

  .summary-item {
    display: flex;
    align-items: center;
    gap: 8px;
  }

  .summary-label {
    font-size: 13px;
    color: $text-secondary;
  }

  .summary-value {
    font-size: 18px;
    font-weight: 700;
    color: $text-main;
  }

  .summary-dot {
    width: 10px;
    height: 10px;
    border-radius: 50%;

    &.total {
      background: $primary;
    }
    &.enable {
      background: #22c55e;
    }
    &.disable {
      background: #d93026;
    }
    &.unmap {
      background: #f59e0b;
    }
    &.maintain {
      background: #8b5cf6;
    }
  }

  .detail-btn {
    background: #f2f4f7;
    color: $text-secondary;
    border: 1px solid $border-color;
    border-radius: 6px;
    padding: 8px 16px;
    font-size: 13px;
    cursor: pointer;

    &.active {
      background: #6b7280;
      color: #fff;
    }
  }
}

// 卡片容器
.cards-container {
  margin-bottom: 16px;
}

.time-range-tabs {
  display: flex;
  align-items: center;
  margin-bottom: 16px;
  padding: 8px 12px;
  background: #f7f8fa;
  border-radius: 8px;

  .tab-btn {
    background: transparent;
    border: 0;
    color: $text-secondary;
    font-size: 14px;
    cursor: pointer;
    padding: 10px 20px;
    position: relative;

    &.active {
      color: $primary;
      font-weight: 500;
    }
  }
}

.range-stats {
  .stat-label {
    display: inline-flex;
    align-items: center;
    gap: 4px;

    strong {
      color: $text-main;
      font-weight: 600;
    }
  }

  .stat-tag {
    display: inline-flex;
    align-items: center;
    padding: 2px 8px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: 600;

    &.blue {
      background: #e8f5e9;
      color: #155724 !important;
    }
    &.orange {
      background: #fff3e6;
      color: #d46b08 !important;
    }
  }
}

.trend-chart {
  display: flex;
  align-items: center;
  padding: 4px 8px;
  background: #f7f8fa;
  border-radius: 4px;
  position: relative;

  .trend-point {
    cursor: pointer;
    transition: all 0.25s ease;
  }
  .trend-point.active {
    r: 5;
    fill: #1b64b0;
    filter: drop-shadow(0 0 2px #1b64b0);
  }

  .trend-point:hover {
    r: 5;
  }
  .trend-point.active {
    r: 5;
    filter: drop-shadow(0 0 3px rgba(27, 100, 176, 0.4));
  }
  .trend-info {
    display: none;
    position: absolute;
    top: -60px;
    right: 0;
    background: #fff;
    border: 1px solid $border-color;
    border-radius: 8px;
    // 扩大弹窗尺寸
    padding: 12px 16px;
    min-width: 180px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    z-index: 100;
    white-space: nowrap;

    &.show {
      display: block;
    }

    .trend-info-title {
      font-size: 14px;
      color: #8599af;
      font-weight: 500;
      padding-bottom: 8px;
      border-bottom: 1px solid #e5eaf0;
      margin-bottom: 8px;
    }

    .trend-info-content {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 6px;

      .trend-info-item {
        font-size: 13px;
        color: #1f2d3d;
        display: flex;
        align-items: center;
        gap: 2px;
      }
    }

    .trend-dot {
      display: inline-block;
      width: 10px;
      height: 10px;
      border-radius: 50%;
      margin-right: 6px;
    }

    .trend-accuracy .trend-dot {
      background-color: #5fc36b;
    }

    .trend-trigger .trend-dot {
      background-color: #8c97a6;
    }
  }
}

.cards {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 16px;
}

// 工具栏
.query-toolbar {
  padding: 12px 16px;
  background: #fff;
  border: 1px solid #e4e7ed;
  border-radius: 8px;
  margin-bottom: 16px;
}

// 表格
.table-wrap {
  background: $card-bg;
  border-radius: 12px;
  border: 1px solid $border-color;
  display: flex;
  flex-direction: column;

  .table-head {
    padding: 14px 16px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    border-bottom: 1px solid #eef2f6;

    strong {
      font-size: 15px;
    }
    span {
      font-size: 12px;
      color: $text-desc;
      margin-left: 8px;
    }
  }

  .table-actions {
    display: flex;
    gap: 8px;
  }

  .tag-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 4px 10px;
    border-radius: 4px;
    font-size: 12px;
    cursor: pointer;
    border: 1px solid #9ca3af;
    background: #fff;
    color: #6b7280;

    &.primary {
      border-color: $primary;
      color: $primary;
      background: #e8f3ff;
    }
    &:hover {
      background: #f3f4f6;
    }
  }

  .rule-name {
    cursor: pointer;
    color: $text-main;

    &:hover {
      color: $primary;
    }
  }

  .danger-text {
    color: #d93026 !important;
  }
}

// 分页
.pagination {
  padding: 10px 16px;
  display: flex;
  justify-content: flex-end;
  align-items: center;
  border-top: 1px solid #eef2f6;
}
</style>
