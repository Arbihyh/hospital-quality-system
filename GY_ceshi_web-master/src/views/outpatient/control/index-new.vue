<!-- 门诊病历质控统计分析 -->
<template>
  <div class="outpatient-container">
    <!-- 搜索条件 -->
    <div class="search-box">
      <!-- <H1Title>门诊病历质控统计分析</H1Title> -->
      <el-form :model="searchForm" label-width="80px" ref="searchFormRef">
        <el-row style="align-items: center">
          <el-col :span="8">
            <el-form-item label="就诊时间">
              <el-date-picker v-model="searchForm.start_time" type="date" :picker-options="pickerOptions" placeholder="开始日期" value-format="yyyyMMdd" format="yyyy年MM月dd日" />
              <el-date-picker v-model="searchForm.end_time" type="date" :picker-options="[]" placeholder="结束日期" value-format="yyyyMMdd" format="yyyy年MM月dd日" />
            </el-form-item>
          </el-col>
          <el-col :span="5">
            <el-form-item label="就诊科室" prop="dep_id">
              <el-select style="width: 100%" v-model="searchForm.dep_id" filterable clearable placeholder="请选择">
                <el-option v-for="item of departmentList" :key="`${Date.now()}${Math.random()}${item.id}`" :label="item.name" :value="item.id" />
              </el-select>
            </el-form-item>
          </el-col>
          <el-col :span="5">
            <el-form-item label="接诊医师" prop="doctor_id">
              <el-select v-model="searchForm.doctor_id" clearable filterable placeholder="请选择">
                <el-option v-for="(item, index) in staffList" :key="index" :label="item.name" :value="item.id"></el-option>
              </el-select>
            </el-form-item>
          </el-col>
          <el-col :span="5">
            <el-form-item>
              <div style="display: flex; justify-content: flex-end">
                <el-button style="background-color: #1b64b0; color: #fff" @click="funQuery">查询</el-button>
                <el-button @click="reset">重置</el-button>
              </div>
            </el-form-item>
          </el-col>
        </el-row>
      </el-form>
    </div>
    <div class="card-box">
      <CardTitleCollapse title="统计分析">
        <div class="statistic">
          <el-row type="flex" align="stretch" style="height: 100%">
            <el-col :span="18" style="display: flex; flex-direction: column; gap: 30px; margin: 0 30px">
              <el-row :gutter="30">
                <el-col :span="12">
                  <!--  6C6C6C-->
                  <CardItem leftText="就诊记录（人）" :rightText="statisticsData.should_be_total" rightTextColor="#5087ec" @right-click="handleCardClick('1')" />
                </el-col>
                <el-col :span="12">
                  <!-- 1B64B0 -->
                  <CardItem leftText="门诊病历（份）" :rightText="statisticsData.omr_total" rightTextColor="#5087ec" @right-click="handleCardClick('2')" />
                </el-col>
              </el-row>
              <el-row :gutter="30">
                <el-col :span="12">
                  <!-- BD3124 -->
                  <CardItem leftText="存在问题（条）" :rightText="statisticsData.omr_defect_issue_total" rightTextColor="#ff786f" @right-click="handleCardClick('3')" />
                </el-col>
                <el-col :span="12">
                  <CardItem leftText="问题病历（份）" :rightText="statisticsData.omr_defect_total" rightTextColor="#ff786f" @right-click="handleCardClick('4')" />
                </el-col>
              </el-row>
            </el-col>

            <el-col :span="6" style="margin-right: 30px; display: flex; flex-direction: column; justify-content: space-around; gap: 15px">
              <CardItem size="small" leftText="甲级（份）" :rightText="statisticsData.cur_grade_a_count" rightTextColor="#2D8042" @right-click="handleCardClick('5')" />
              <CardItem size="small" leftText="乙级（份）" :rightText="statisticsData.cur_grade_b_count" rightTextColor="#E6851A" @right-click="handleCardClick('6')" />
              <CardItem size="small" leftText="丙级（份）" :rightText="statisticsData.cur_grade_c_count" rightTextColor="#C5350C" @right-click="handleCardClick('7')" />
            </el-col>
          </el-row>

          <!-- 图表 -->
          <el-row :gutter="20" style="margin-top: 50px">
            <el-col :span="11">
              <el-card shadow="always" style="margin: 0 30px; height: 400px; border-radius: 8px">
                <div style="margin: 10px 10px; height: 40px; display: flex; justify-content: space-between">
                  <CardTitle title="月趋势">
                    <span style="color: #00000088; font-size: 16px; font-weight: bold; margin-left: 5px">(近6个月)</span>
                  </CardTitle>
                  <span class="span-btn" @click="handleDownloadChart('monthlyTrend', '月趋势图表')">
                    <svg t="1765250968962" class="icon" viewBox="0 0 1024 1024" version="1.1" xmlns="http://www.w3.org/2000/svg" p-id="5503" width="25" height="25">
                      <path
                        d="M896 672c-17.066667 0-32 14.933333-32 32v128c0 6.4-4.266667 10.666667-10.666667 10.666667H170.666667c-6.4 0-10.666667-4.266667-10.666667-10.666667v-128c0-17.066667-14.933333-32-32-32s-32 14.933333-32 32v128c0 40.533333 34.133333 74.666667 74.666667 74.666667h682.666666c40.533333 0 74.666667-34.133333 74.666667-74.666667v-128c0-17.066667-14.933333-32-32-32z"
                        fill="#666666"
                        p-id="5504"
                      ></path>
                      <path
                        d="M488.533333 727.466667c6.4 6.4 14.933333 8.533333 23.466667 8.533333s17.066667-2.133333 23.466667-8.533333l213.333333-213.333334c12.8-12.8 12.8-32 0-44.8-12.8-12.8-32-12.8-44.8 0l-157.866667 157.866667V170.666667c0-17.066667-14.933333-32-32-32s-34.133333 14.933333-34.133333 32v456.533333L322.133333 469.333333c-12.8-12.8-32-12.8-44.8 0-12.8 12.8-12.8 32 0 44.8l211.2 213.333334z"
                        fill="#666666"
                        p-id="5505"
                      ></path>
                    </svg>
                  </span>
                </div>
                <div id="monthlyTrend"></div>
              </el-card>
            </el-col>
            <el-col :span="13">
              <el-card shadow="always" style="margin-right: 30px; height: 400px; border-radius: 8px">
                <div style="margin: 10px 10px; display: flex; justify-content: space-between">
                  <CardTitle title="病历质量"></CardTitle>
                  <span class="span-btn" @click="handleDownloadChart('medicalRecordQualityId', '病历质量图表')">
                    <svg t="1765250968962" class="icon" viewBox="0 0 1024 1024" version="1.1" xmlns="http://www.w3.org/2000/svg" p-id="5503" width="25" height="25">
                      <path
                        d="M896 672c-17.066667 0-32 14.933333-32 32v128c0 6.4-4.266667 10.666667-10.666667 10.666667H170.666667c-6.4 0-10.666667-4.266667-10.666667-10.666667v-128c0-17.066667-14.933333-32-32-32s-32 14.933333-32 32v128c0 40.533333 34.133333 74.666667 74.666667 74.666667h682.666666c40.533333 0 74.666667-34.133333 74.666667-74.666667v-128c0-17.066667-14.933333-32-32-32z"
                        fill="#666666"
                        p-id="5504"
                      ></path>
                      <path
                        d="M488.533333 727.466667c6.4 6.4 14.933333 8.533333 23.466667 8.533333s17.066667-2.133333 23.466667-8.533333l213.333333-213.333334c12.8-12.8 12.8-32 0-44.8-12.8-12.8-32-12.8-44.8 0l-157.866667 157.866667V170.666667c0-17.066667-14.933333-32-32-32s-34.133333 14.933333-34.133333 32v456.533333L322.133333 469.333333c-12.8-12.8-32-12.8-44.8 0-12.8 12.8-12.8 32 0 44.8l211.2 213.333334z"
                        fill="#666666"
                        p-id="5505"
                      ></path>
                    </svg>
                  </span>
                </div>
                <div id="medicalRecordQualityId"></div>
              </el-card>
            </el-col>
          </el-row>
        </div>
      </CardTitleCollapse>
    </div>

    <!-- 缺陷问题 -->
    <div class="defect-problem">
      <CardTitleCollapse title="缺陷问题">
        <div class="defect-select">
          <div style="margin-bottom: 13px; display: flex; justify-content: space-between">
            <TabBtnGroup
              :active-key="defectActiveKey"
              width="100px"
              :tab-list="[
                { key: 'top10', label: 'TOP10' },
                { key: 'all', label: '全部' },
                { key: 'analysis', label: '问题分析' },
              ]"
              @tab-change="key => switchGranularity('defectActiveKey', key)"
            />

            <span class="span-btn" v-if="defectActiveKey === 'top10'" @click="handleDownloadChart(`defectDataTop10Id`, '缺陷问题图表')">
              <svg t="1765250968962" class="icon" viewBox="0 0 1024 1024" version="1.1" xmlns="http://www.w3.org/2000/svg" p-id="5503" width="25" height="25">
                <path
                  d="M896 672c-17.066667 0-32 14.933333-32 32v128c0 6.4-4.266667 10.666667-10.666667 10.666667H170.666667c-6.4 0-10.666667-4.266667-10.666667-10.666667v-128c0-17.066667-14.933333-32-32-32s-32 14.933333-32 32v128c0 40.533333 34.133333 74.666667 74.666667 74.666667h682.666666c40.533333 0 74.666667-34.133333 74.666667-74.666667v-128c0-17.066667-14.933333-32-32-32z"
                  fill="#666666"
                  p-id="5504"
                ></path>
                <path
                  d="M488.533333 727.466667c6.4 6.4 14.933333 8.533333 23.466667 8.533333s17.066667-2.133333 23.466667-8.533333l213.333333-213.333334c12.8-12.8 12.8-32 0-44.8-12.8-12.8-32-12.8-44.8 0l-157.866667 157.866667V170.666667c0-17.066667-14.933333-32-32-32s-34.133333 14.933333-34.133333 32v456.533333L322.133333 469.333333c-12.8-12.8-32-12.8-44.8 0-12.8 12.8-12.8 32 0 44.8l211.2 213.333334z"
                  fill="#666666"
                  p-id="5505"
                ></path>
              </svg>
            </span>
            <!-- <span class="span-btn" v-else-if="defectActiveKey === 'analysis'" @click="handleDownloadChart(`defectAnalysisId`, '问题分析图表')">
              <svg t="1765250968962" class="icon" viewBox="0 0 1024 1024" version="1.1" xmlns="http://www.w3.org/2000/svg" p-id="5503" width="25" height="25">
                <path
                  d="M896 672c-17.066667 0-32 14.933333-32 32v128c0 6.4-4.266667 10.666667-10.666667 10.666667H170.666667c-6.4 0-10.666667-4.266667-10.666667-10.666667v-128c0-17.066667-14.933333-32-32-32s-32 14.933333-32 32v128c0 40.533333 34.133333 74.666667 74.666667 74.666667h682.666666c40.533333 0 74.666667-34.133333 74.666667-74.666667v-128c0-17.066667-14.933333-32-32-32z"
                  fill="#666666"
                  p-id="5504"
                ></path>
                <path
                  d="M488.533333 727.466667c6.4 6.4 14.933333 8.533333 23.466667 8.533333s17.066667-2.133333 23.466667-8.533333l213.333333-213.333334c12.8-12.8 12.8-32 0-44.8-12.8-12.8-32-12.8-44.8 0l-157.866667 157.866667V170.666667c0-17.066667-14.933333-32-32-32s-34.133333 14.933333-34.133333 32v456.533333L322.133333 469.333333c-12.8-12.8-32-12.8-44.8 0-12.8 12.8-12.8 32 0 44.8l211.2 213.333334z"
                  fill="#666666"
                  p-id="5505"
                ></path>
              </svg>
            </span> -->
            <el-button v-if="defectActiveKey === 'all'" size="small" class="export-btn" @click="tableDataExport('defect')">导出</el-button>
          </div>
        </div>
        <div class="defect-data">
          <div v-if="defectActiveKey === 'top10'" id="defectDataTop10Id"></div>
          <div v-if="defectActiveKey === 'analysis'">
            <div>
              <H1Title>门诊电子病历问题分布</H1Title>
              <div style="margin: 20px 50px; height: 20px; display: flex; justify-content: space-between">
                <CardTitle title="缺陷问题分布" />
                <span class="span-btn" @click="handleDownloadChart('defectAnalysisId', '缺陷问题分布')">
                  <svg t="1765250968962" class="icon" viewBox="0 0 1024 1024" version="1.1" xmlns="http://www.w3.org/2000/svg" p-id="5503" width="25" height="25">
                    <path
                      d="M896 672c-17.066667 0-32 14.933333-32 32v128c0 6.4-4.266667 10.666667-10.666667 10.666667H170.666667c-6.4 0-10.666667-4.266667-10.666667-10.666667v-128c0-17.066667-14.933333-32-32-32s-32 14.933333-32 32v128c0 40.533333 34.133333 74.666667 74.666667 74.666667h682.666666c40.533333 0 74.666667-34.133333 74.666667-74.666667v-128c0-17.066667-14.933333-32-32-32z"
                      fill="#666666"
                      p-id="5504"
                    ></path>
                    <path
                      d="M488.533333 727.466667c6.4 6.4 14.933333 8.533333 23.466667 8.533333s17.066667-2.133333 23.466667-8.533333l213.333333-213.333334c12.8-12.8 12.8-32 0-44.8-12.8-12.8-32-12.8-44.8 0l-157.866667 157.866667V170.666667c0-17.066667-14.933333-32-32-32s-34.133333 14.933333-34.133333 32v456.533333L322.133333 469.333333c-12.8-12.8-32-12.8-44.8 0-12.8 12.8-12.8 32 0 44.8l211.2 213.333334z"
                      fill="#666666"
                      p-id="5505"
                    ></path>
                  </svg>
                </span>
              </div>
              <div id="defectAnalysisId"></div>
            </div>
            <div>
              <div>
                <div style="margin: 0px 50px; height: 20px; display: flex; justify-content: space-between">
                  <CardTitle title="科室问题分布" />
                  <span class="span-btn" @click="handleDownloadChart('deptAnalysisId', '科室问题分布')">
                    <svg t="1765250968962" class="icon" viewBox="0 0 1024 1024" version="1.1" xmlns="http://www.w3.org/2000/svg" p-id="5503" width="25" height="25">
                      <path
                        d="M896 672c-17.066667 0-32 14.933333-32 32v128c0 6.4-4.266667 10.666667-10.666667 10.666667H170.666667c-6.4 0-10.666667-4.266667-10.666667-10.666667v-128c0-17.066667-14.933333-32-32-32s-32 14.933333-32 32v128c0 40.533333 34.133333 74.666667 74.666667 74.666667h682.666666c40.533333 0 74.666667-34.133333 74.666667-74.666667v-128c0-17.066667-14.933333-32-32-32z"
                        fill="#666666"
                        p-id="5504"
                      ></path>
                      <path
                        d="M488.533333 727.466667c6.4 6.4 14.933333 8.533333 23.466667 8.533333s17.066667-2.133333 23.466667-8.533333l213.333333-213.333334c12.8-12.8 12.8-32 0-44.8-12.8-12.8-32-12.8-44.8 0l-157.866667 157.866667V170.666667c0-17.066667-14.933333-32-32-32s-34.133333 14.933333-34.133333 32v456.533333L322.133333 469.333333c-12.8-12.8-32-12.8-44.8 0-12.8 12.8-12.8 32 0 44.8l211.2 213.333334z"
                        fill="#666666"
                        p-id="5505"
                      ></path>
                    </svg>
                  </span>
                </div>
                <div id="deptAnalysisId"></div>
              </div>
            </div>
          </div>
          <div v-if="defectActiveKey === 'all'">
            <el-table :data="defectTableData" max-height="530" style="width: 100%">
              <el-table-column prop="" label="序号" width="80">
                <template slot-scope="scope">
                  <span>{{ scope.$index + 1 }}</span>
                </template>
              </el-table-column>
              <el-table-column prop="field" label="缺陷类目"></el-table-column>
              <el-table-column prop="desc" label="缺陷描述"></el-table-column>

              <el-table-column prop="" label="缺陷数量">
                <template slot-scope="scope">
                  <span class="link" @click="toPage('1', scope.row)">{{ scope.row.total_num }}</span>
                </template>
              </el-table-column>
              <el-table-column prop="defect_rate" label="缺陷占比">
                <template slot-scope="scope">
                  <span>{{ scope.row.defect_rate }}%</span>
                </template>
              </el-table-column>
            </el-table>
            <el-row type="flex" justify="end" align="middle">
              <mPagination v-if="defectTableData && defectTableData.length !== 0" :data="paginationDataDefect" @pageChangeEvent="pageHasChanged('defect')"></mPagination>
            </el-row>
          </div>
        </div>
      </CardTitleCollapse>
    </div>

    <!-- 科室排名 -->
    <div class="department-rank">
      <CardTitleCollapse title="科室排名">
        <div class="department-select">
          <div style="margin-bottom: 13px; display: flex; justify-content: space-between">
            <TabBtnGroup
              :active-key="deptActiveKey"
              width="100px"
              :tab-list="[
                { key: 'top10', label: 'TOP10' },
                { key: 'all', label: '全部' },
              ]"
              @tab-change="key => switchGranularity('deptActiveKey', key)"
            />
            <span class="span-btn" v-if="deptActiveKey === 'top10'" @click="handleDownloadChart('departmentDataTopId', '科室排名图表')">
              <svg t="1765250968962" class="icon" viewBox="0 0 1024 1024" version="1.1" xmlns="http://www.w3.org/2000/svg" p-id="5503" width="25" height="25">
                <path
                  d="M896 672c-17.066667 0-32 14.933333-32 32v128c0 6.4-4.266667 10.666667-10.666667 10.666667H170.666667c-6.4 0-10.666667-4.266667-10.666667-10.666667v-128c0-17.066667-14.933333-32-32-32s-32 14.933333-32 32v128c0 40.533333 34.133333 74.666667 74.666667 74.666667h682.666666c40.533333 0 74.666667-34.133333 74.666667-74.666667v-128c0-17.066667-14.933333-32-32-32z"
                  fill="#666666"
                  p-id="5504"
                ></path>
                <path
                  d="M488.533333 727.466667c6.4 6.4 14.933333 8.533333 23.466667 8.533333s17.066667-2.133333 23.466667-8.533333l213.333333-213.333334c12.8-12.8 12.8-32 0-44.8-12.8-12.8-32-12.8-44.8 0l-157.866667 157.866667V170.666667c0-17.066667-14.933333-32-32-32s-34.133333 14.933333-34.133333 32v456.533333L322.133333 469.333333c-12.8-12.8-32-12.8-44.8 0-12.8 12.8-12.8 32 0 44.8l211.2 213.333334z"
                  fill="#666666"
                  p-id="5505"
                ></path>
              </svg>
            </span>
            <span class="span-btn" v-else-if="deptActiveKey === 'analysis'" @click="handleDownloadChart(`deptAnalysisId`, '问题分析图表')">
              <svg t="1765250968962" class="icon" viewBox="0 0 1024 1024" version="1.1" xmlns="http://www.w3.org/2000/svg" p-id="5503" width="25" height="25">
                <path
                  d="M896 672c-17.066667 0-32 14.933333-32 32v128c0 6.4-4.266667 10.666667-10.666667 10.666667H170.666667c-6.4 0-10.666667-4.266667-10.666667-10.666667v-128c0-17.066667-14.933333-32-32-32s-32 14.933333-32 32v128c0 40.533333 34.133333 74.666667 74.666667 74.666667h682.666666c40.533333 0 74.666667-34.133333 74.666667-74.666667v-128c0-17.066667-14.933333-32-32-32z"
                  fill="#666666"
                  p-id="5504"
                ></path>
                <path
                  d="M488.533333 727.466667c6.4 6.4 14.933333 8.533333 23.466667 8.533333s17.066667-2.133333 23.466667-8.533333l213.333333-213.333334c12.8-12.8 12.8-32 0-44.8-12.8-12.8-32-12.8-44.8 0l-157.866667 157.866667V170.666667c0-17.066667-14.933333-32-32-32s-34.133333 14.933333-34.133333 32v456.533333L322.133333 469.333333c-12.8-12.8-32-12.8-44.8 0-12.8 12.8-12.8 32 0 44.8l211.2 213.333334z"
                  fill="#666666"
                  p-id="5505"
                ></path>
              </svg>
            </span>
            <el-button v-else size="small" class="export-btn" @click="tableDataExport('dept')">导出</el-button>
          </div>
        </div>

        <div class="department-data">
          <div v-if="deptActiveKey === 'top10'" id="departmentDataTopId"></div>
          <div v-if="deptActiveKey === 'all'">
            <el-table :data="deptTableData" class="mb20" :default-sort="ysSortParams" @sort-change="sortObj => handleSortChange('dept', sortObj)">
              <el-table-column type="index" label="序号" width="50" align="center">
                <template slot-scope="scope">
                  <span>{{ scope.$index + 1 }}</span>
                </template>
              </el-table-column>
              <el-table-column label="门诊科室" align="center" show-overflow-tooltip>
                <template slot-scope="scope">
                  <span v-if="scope.row.name">
                    {{ scope.row.name }}
                    <i class="el-icon-document-copy" style="color: green" @click="toPage('2', scope.row)" />
                  </span>
                </template>
              </el-table-column>
              <el-table-column prop="total_medical" label="病历总数" align="center" sortable="custom" :sort-orders="['ascending', 'descending']">
                <template slot-scope="scope">
                  <span v-if="scope.row.total_medical" class="link2" @click="toPage('2', scope.row)">{{ scope.row.total_medical }}例</span>
                </template>
              </el-table-column>
              <el-table-column prop="total_error_medical" label="缺陷总数" align="center">
                <template slot-scope="scope">
                  <span v-if="typeof scope.row.total_error_medical === 'number'">{{ scope.row.total_error_medical }}例</span>
                </template>
              </el-table-column>
              <el-table-column prop="issue_count" label="问题数量" align="center">
                <template slot-scope="scope">
                  <span v-if="typeof scope.row.issue_count === 'number'">{{ scope.row.issue_count }}例</span>
                </template>
              </el-table-column>
              <el-table-column prop="grade_a_count" label="甲级数量(满分数)" align="center" show-overflow-tooltip></el-table-column>
              <el-table-column prop="grade_a_rate" label="甲级占比(满分比)" align="center" show-overflow-tooltip></el-table-column>
              <el-table-column prop="grade_b_count" label="乙级数量" align="center" show-overflow-tooltip></el-table-column>
              <el-table-column prop="grade_b_rate" label="乙级占比" align="center" show-overflow-tooltip></el-table-column>
              <el-table-column prop="grade_b_count" label="丙级数量" align="center" show-overflow-tooltip></el-table-column>
              <el-table-column prop="grade_c_rate" label="丙级占比" align="center" show-overflow-tooltip></el-table-column>
            </el-table>
            <el-row type="flex" justify="end" align="middle">
              <mPagination v-if="deptTableData && deptTableData.length !== 0" :data="paginationDataDept" @pageChangeEvent="pageHasChanged('dept')"></mPagination>
            </el-row>
          </div>
        </div>
      </CardTitleCollapse>
    </div>

    <!-- 医师排名 -->
    <div class="doctor-rank">
      <CardTitleCollapse title="医师排名">
        <div class="doctor-select">
          <div style="margin-bottom: 13px; display: flex; justify-content: space-between">
            <TabBtnGroup
              :active-key="doctorActiveKey"
              width="100px"
              :tab-list="[
                { key: 'top10', label: 'TOP10' },
                { key: 'all', label: '全部' },
              ]"
              @tab-change="key => switchGranularity('doctorActiveKey', key)"
            />
            <span class="span-btn" v-if="doctorActiveKey === 'top10'" @click="handleDownloadChart('doctorDataTopId', '医师排名图表')">
              <svg t="1765250968962" class="icon" viewBox="0 0 1024 1024" version="1.1" xmlns="http://www.w3.org/2000/svg" p-id="5503" width="25" height="25">
                <path
                  d="M896 672c-17.066667 0-32 14.933333-32 32v128c0 6.4-4.266667 10.666667-10.666667 10.666667H170.666667c-6.4 0-10.666667-4.266667-10.666667-10.666667v-128c0-17.066667-14.933333-32-32-32s-32 14.933333-32 32v128c0 40.533333 34.133333 74.666667 74.666667 74.666667h682.666666c40.533333 0 74.666667-34.133333 74.666667-74.666667v-128c0-17.066667-14.933333-32-32-32z"
                  fill="#666666"
                  p-id="5504"
                ></path>
                <path
                  d="M488.533333 727.466667c6.4 6.4 14.933333 8.533333 23.466667 8.533333s17.066667-2.133333 23.466667-8.533333l213.333333-213.333334c12.8-12.8 12.8-32 0-44.8-12.8-12.8-32-12.8-44.8 0l-157.866667 157.866667V170.666667c0-17.066667-14.933333-32-32-32s-34.133333 14.933333-34.133333 32v456.533333L322.133333 469.333333c-12.8-12.8-32-12.8-44.8 0-12.8 12.8-12.8 32 0 44.8l211.2 213.333334z"
                  fill="#666666"
                  p-id="5505"
                ></path>
              </svg>
            </span>
            <el-button v-else size="small" class="export-btn" @click="tableDataExport('doctor')">导出</el-button>
          </div>
        </div>
        <div class="doctor-data">
          <div v-if="doctorActiveKey === 'top10'" id="doctorDataTopId"></div>
          <div v-else>
            <el-table :data="doctorTableData" class="mb20" :default-sort="ysSortParams" @sort-change="sortObj => handleSortChange('doctor', sortObj)">
              <el-table-column type="index" label="序号" width="50" align="center">
                <template slot-scope="scope">
                  <span>{{ scope.$index + 1 }}</span>
                </template>
              </el-table-column>
              <el-table-column label="医师" align="center" show-overflow-tooltip>
                <template slot-scope="scope">
                  <span v-if="scope.row.doctor_name">
                    {{ scope.row.doctor_name }}
                    <i class="el-icon-document-copy" style="color: green" @click="toPage('3', scope.row)" />
                  </span>
                </template>
              </el-table-column>
              <el-table-column prop="total_medical" label="病历总数" align="center" sortable="custom" :sort-orders="['ascending', 'descending']">
                <template slot-scope="scope">
                  <span v-if="scope.row.total_medical" class="link2" @click="toPage('3', scope.row)">{{ scope.row.total_medical }}例</span>
                </template>
              </el-table-column>
              <el-table-column prop="total_error_medical" label="缺陷总数" align="center">
                <template slot-scope="scope">
                  <span v-if="typeof scope.row.total_error_medical === 'number'">{{ scope.row.total_error_medical }}例</span>
                </template>
              </el-table-column>
              <el-table-column prop="grade_a_count" label="甲级数量(满分数)" align="center" show-overflow-tooltip></el-table-column>
              <el-table-column prop="grade_a_rate" label="甲级占比(满分比)" align="center" show-overflow-tooltip></el-table-column>
              <el-table-column prop="grade_b_count" label="乙级数量" align="center" show-overflow-tooltip></el-table-column>
              <el-table-column prop="grade_b_rate" label="乙级占比" align="center" show-overflow-tooltip></el-table-column>
              <el-table-column prop="grade_c_count" label="丙级数量" align="center" show-overflow-tooltip></el-table-column>
              <el-table-column prop="grade_c_rate" label="丙级占比" align="center" show-overflow-tooltip></el-table-column>
            </el-table>
            <el-row type="flex" justify="end" align="middle">
              <mPagination v-if="doctorTableData && doctorTableData.length !== 0" :data="paginationDataDoctor" @pageChangeEvent="pageHasChanged('doctor')"></mPagination>
            </el-row>
          </div>
        </div>
      </CardTitleCollapse>
    </div>
  </div>
</template>
<script>
import H1Title from '@/components/Title/h1-title.vue';
import CardItem from '@/components/card/card-item.vue';
import { download } from '@/utils/echarts-utils';
import moment from 'moment/moment';
import mPagination from '@/components/m-pagination';
import TabBtnGroup from '@/components/TabBtnGroup';
import Title from '@/components/Title';

import { defectDataExport, deptDataExport, doctorDataExport } from '@/api/excel.js';

export default {
  name: 'OutpatientControl',
  components: {
    H1Title,
    Title,
    CardItem,
    mPagination,
    TabBtnGroup,
  },

  data() {
    const that = this;
    return {
      searchForm: {
        start_time: moment().startOf('month').format('YYYYMMDD'),
        end_time: moment().format('YYYYMMDD'),
        dep_id: '', //科室
        doctor_id: '', //接诊医师
        order: 'desc',
      },
      defectTableData: [], //缺陷列表数据
      defectChartData: [], //缺陷列表数据

      deptTableData: [], //缺陷列表数据
      deptChartData: [], //缺陷列表数据

      doctorTableData: [], //缺陷列表数据
      doctorChartData: [], //缺陷列表数据

      departmentData: [],
      statisticsData: {
        omr_total: 0, //门诊病历数量
        should_be_total: 0, //就诊记录
        omr_defect_total: 0, //问题病历数量
        omr_defect_issue_total: 0, //存在问题数量
        cur_grade_a_count: 0, //甲级数量
        cur_grade_b_count: 0, //乙级数量
        cur_grade_c_count: 0, //丙级数量

        prev_grade_a_count: 0, //甲级数量
        prev_grade_b_count: 0, //乙级数量
        prev_grade_c_count: 0, //丙级数量
        prev_total_count: 0,
      },
      paginationDefectData: {
        total: 0,
        currentPage: 1,
        pageSize: 10,
      },
      medicalRecordData: [],
      departmentList: [],
      staffList: [], // 主治医师
      defectActiveKey: 'all',
      deptActiveKey: 'all',
      doctorActiveKey: 'all',

      pickerOptions1: {
        disabledDate(time) {
          return time.getTime() > Date.now();
        },
      },
      pickerOptions2: {
        disabledDate(time) {
          return time.getTime() > Date.now();
        },
      },
      pickerOptions: {
        disabledDate: time => {
          if (this.searchForm.end_time != '') {
            return time.getTime() > Date.now();
          }
        },
        shortcuts: [
          {
            text: '今天',
            onClick(picker) {
              picker.$emit('pick', moment().format('YYYYMMDD'));
              that.searchForm.end_time = moment().format('YYYYMMDD');
            },
          },
          {
            text: '近7天',
            onClick(picker) {
              picker.$emit('pick', moment().subtract(7, 'days').format('YYYYMMDD'));
              that.searchForm.end_time = moment().format('YYYYMMDD');
            },
          },
          {
            text: '近30天',
            onClick(picker) {
              picker.$emit('pick', moment().subtract(30, 'days').format('YYYYMMDD'));
              that.searchForm.end_time = moment().format('YYYYMMDD');
            },
          },
          {
            text: '近3年',
            onClick(picker) {
              picker.$emit('pick', moment().subtract(3, 'years').format('YYYYMMDD'));
              that.searchForm.end_time = moment().format('YYYYMMDD');
            },
          },
          {
            text: '一季度',
            onClick(picker) {
              picker.$emit('pick', moment().startOf('year').format('YYYYMMDD'));
              that.searchForm.end_time = moment().startOf('year').add(3, 'M').subtract(1, 'days').format('YYYYMMDD');
            },
          },
          {
            text: '二季度',
            onClick(picker) {
              picker.$emit('pick', moment().startOf('year').add(3, 'M').format('YYYYMMDD'));
              that.searchForm.end_time = moment().startOf('year').add(6, 'M').subtract(1, 'days').format('YYYYMMDD');
            },
          },
          {
            text: '三季度',
            onClick(picker) {
              picker.$emit('pick', moment().startOf('year').add(6, 'M').format('YYYYMMDD'));
              that.searchForm.end_time = moment().startOf('year').add(9, 'M').subtract(1, 'days').format('YYYYMMDD');
            },
          },
          {
            text: '四季度',
            onClick(picker) {
              picker.$emit('pick', moment().startOf('year').add(9, 'M').format('YYYYMMDD'));
              that.searchForm.end_time = moment().startOf('year').add(12, 'M').subtract(1, 'days').format('YYYYMMDD');
            },
          },
          {
            text: moment().add(-2, 'Y').format('YYYY'),
            onClick(picker) {
              picker.$emit('pick', moment().add(-2, 'Y').startOf('year').format('YYYYMMDD'));
              that.searchForm.end_time = moment().add(-2, 'Y').endOf('year').format('YYYYMMDD');
            },
          },
          {
            text: moment().add(-1, 'Y').format('YYYY'),
            onClick(picker) {
              picker.$emit('pick', moment().add(-1, 'Y').startOf('year').format('YYYYMMDD'));
              that.searchForm.end_time = moment().add(-1, 'Y').endOf('year').format('YYYYMMDD');
            },
          },
          {
            text: moment().format('YYYY'),
            onClick(picker) {
              console.log(that.formData);
              picker.$emit('pick', moment().startOf('year').format('YYYYMMDD'));
              console.log(moment().endOf('year').format('YYYYMMDD'));
              that.searchForm.end_time = moment().endOf('year').format('YYYYMMDD');
            },
          },
        ],
      },

      ysSortParams: {
        prop: 'avg_score',
        order: 'descending',
      },
      paginationDataDoctor: {
        currentPage: 1,
        pageSize: 10,
        total: 0,
      },
      paginationDataDefect: {
        currentPage: 1,
        pageSize: 10,
        total: 0,
      },
      paginationDataDept: {
        currentPage: 1,
        pageSize: 10,
        total: 0,
      },
      monthlyTrendCharts: null,
      medicalRecordQualityIdCharts: null,

      defectDataTop10IdCharts: null,
      departmentDataTopIdCharts: null,
      doctorDataTopIdCharts: null,
      defectAnalysisIdCharts: null, // 问题分析图表实例
      defectAnalysisData: {
        // 问题分析数据存储
        categories: [], // 缺陷类型
        counts: [], // 缺陷数量
        rates: [], // 缺陷占比
      },

      deptAnalysisIdCharts: null, // 问题分析图表实例
      deptAnalysisData: {
        // 问题分析数据存储
        categories: [], // 缺陷类型
        counts: [], // 缺陷数量
        rates: [], // 缺陷占比
      },
      scrollPos: 0,
      monthlyTrendData: {
        dataX: [],
        dataY: [],
      },
      departmentChartData: {
        dataA: [],
        dataB: [],
        dataC: [],
      },
      doctorChartDataObj: {
        dataA: [],
        dataB: [],
        dataC: [],
      },
      defectResizeHandler: null,
      resizeObserver: null,
    };
  },
  computed: {
    getButtonStyle() {
      return isActive => ({
        borderRadius: '5px',
        width: '80px',
        backgroundColor: isActive ? '#1B64B0' : '',
        color: isActive ? '#fff' : '',
        borderColor: isActive ? '#1B64B0' : '',
      });
    },
  },
  mounted() {
    this.getDeportmentList();
    this.getStaffList();
    this.funQuery();
    this.resizeObserver = new ResizeObserver(() => {
      this.defectDataTop10IdCharts && this.defectDataTop10IdCharts.resize();
      this.departmentDataTopIdCharts && this.departmentDataTopIdCharts.resize();
      this.monthlyTrendCharts && this.monthlyTrendCharts.resize();
      this.medicalRecordQualityIdCharts && this.medicalRecordQualityIdCharts.resize();
      this.doctorDataTopIdCharts && this.doctorDataTopIdCharts.resize();
      this.defectAnalysisIdCharts && this.defectAnalysisIdCharts.resize();
      this.deptAnalysisIdCharts && this.deptAnalysisIdCharts.resize();
    });
    this.resizeObserver.observe(document.getElementById('monthlyTrend'));
  },
  activated() {
    this.$nextTick(() => {
      const container = document.querySelector('.outpatient-container');
      if (container) {
        container.scrollTop = this.scrollPos;
      }
    });
  },
  beforeDestroy() {
    this.resizeObserver && this.resizeObserver.disconnect();
  },
  methods: {
    async handleDownloadChart(chartKey, chartName) {
      const chartInstance = this[`${chartKey}Charts`];
      await download(chartInstance, chartName, {
        type: 'png',
        pixelRatio: 2,
        backgroundColor: '#fff',
      });
      this.$message.success(`${chartName}下载成功`);
    },
    pageHasChanged(type) {
      if (type === 'defect') {
        this.defectDataM(0);
      }

      if (type === 'dept') {
        this.departmentDataTopM(0);
      }
      if (type === 'doctor') {
        this.doctorDataTopM(0);
      }
    },

    tableDataExport(type) {
      let requestParams = {
        start_time: this.searchForm.start_time,
        end_time: this.searchForm.end_time,
        dep_id: this.searchForm.dep_id, // 科室
        doctor_id: this.searchForm.doctor_id, // 接诊医师
        is_top10: 0,
        is_export: 1,
      };

      const downloadCsvFile = (data, fileName = '门诊病历.csv') => {
        if (!data) {
          console.warn('导出失败：无有效数据');
          return;
        }
        const blob = new Blob([data]);
        const elink = document.createElement('a');
        elink.download = fileName;
        elink.style.display = 'none';
        elink.href = URL.createObjectURL(blob);
        document.body.appendChild(elink);
        elink.click();
        URL.revokeObjectURL(elink.href);
        document.body.removeChild(elink);
      };

      let exportApiPromise;
      switch (type) {
        case 'defect':
          (requestParams.page = this.paginationDataDefect.currentPage),
            (requestParams.page_size = this.paginationDataDefect.pageSize),
            (exportApiPromise = defectDataExport(requestParams));
          break;
        case 'dept':
          (requestParams.page = this.paginationDataDept.currentPage),
            (requestParams.page_size = this.paginationDataDept.pageSize),
            (exportApiPromise = deptDataExport(requestParams));
          break;
        case 'doctor':
          (requestParams.page = this.paginationDataDoctor.currentPage),
            (requestParams.page_size = this.paginationDataDoctor.pageSize),
            (exportApiPromise = doctorDataExport(requestParams));
          break;
      }

      exportApiPromise
        .then(res => {
          const fileNameMap = {
            defect: '门诊病历-缺陷统计.csv',
            dept: '门诊病历-科室统计.csv',
            doctor: '门诊病历-医师统计.csv',
          };
          downloadCsvFile(res.data, fileNameMap[type]);
        })
        .catch(err => {
          console.error('导出接口请求失败', err);
        });
    },

    handleCardClick(type) {
      const container = document.querySelector('.outpatient-container');
      if (container) {
        this.scrollPos = container.scrollTop; // 保存当前滚动距离
        console.log('保存的滚动距离：', this.scrollPos);
      }
      if (type === '1') {
        this.$router.push({
          path: `/outpatientMedicalShouldDefectNumber`,
          query: {
            dep_id: this.searchForm.dep_id,
            doctor_id: this.searchForm.doctor_id,
            start_time: this.searchForm.start_time,
            end_time: this.searchForm.end_time,
          },
        });
      }
      if (type === '2') {
        // type = 1 查询错误门诊数据
        // type = 0 查询所有门诊数据
        this.$router.push({
          path: '/outpatientMedicalRecordDefectNumber',
          query: {
            is_error: 0,
            dep_id: this.searchForm.dep_id,
            doctor_id: this.searchForm.doctor_id,
            start_time: this.searchForm.start_time,
            end_time: this.searchForm.end_time,
          },
        });
      }
      if (type === '3') {
        this.$router.push({
          path: '/outpatientMedicalRecordIssNumber',
          query: {
            is_error: 1,
            dep_id: this.searchForm.dep_id,
            doctor_id: this.searchForm.doctor_id,
            start_time: this.searchForm.start_time,
            end_time: this.searchForm.end_time,
          },
        });
      }
      if (type === '4') {
        this.$router.push({
          path: '/outpatientMedicalRecordDefectNumber',
          query: {
            is_error: 1,
            dep_id: this.searchForm.dep_id,
            doctor_id: this.searchForm.doctor_id,
            start_time: this.searchForm.start_time,
            end_time: this.searchForm.end_time,
          },
        });
      }
    },
    funQuery() {
      this.paginationDataDefect.currentPage = 1;
      this.paginationDataDept.currentPage = 1;
      this.paginationDataDoctor.currentPage = 1;

      this.getStatisticCount();
      this.getMonthlyTrendData();
      this.departmentDataTopM(0);
      this.departmentDataTopM(1);
      this.doctorDataTopM(0);
      this.doctorDataTopM(1);
      this.defectDataM(0);
      this.defectDataM(1);
      this.getDefectAnalysisData();
      this.getDeptAnalysisData();

      this.$nextTick(() => {
        this.medicalRecordQuality();
        this.defectDataTop10();
        this.departmentDataTop();
        this.doctorDataTop();
      });
      const container = document.querySelector('.outpatient-container');
      container && (container.scrollTop = 0);
    },

    async getStatisticCount() {
      const params = {
        start_time: this.searchForm.start_time,
        end_time: this.searchForm.end_time,
        dep_id: this.searchForm.dep_id, // 科室
        doctor_id: this.searchForm.doctor_id, // 接诊医师
      };

      try {
        const res = await this.$axios.post('/omr_zk/analysis', { params });
        const responseData = res?.data || {};
        this.statisticsData = responseData;

        const totalCount = responseData.prev_total_count || 0;

        let gradeARatio = 0;
        let gradeBRatio = 0;
        let gradeCRatio = 0;
        if (totalCount !== 0) {
          gradeARatio = responseData.prev_grade_a_count / totalCount;
          gradeBRatio = responseData.prev_grade_b_count / totalCount;
          gradeCRatio = responseData.prev_grade_c_count / totalCount;
        }

        // const gradeCRatio = Math.max(0, 1 - gradeARatio - gradeBRatio);

        this.medicalRecordData = [
          {
            value: responseData.prev_grade_a_count,
            name: '甲级',
            // 补充乘100后保留两位小数，拼接%号
            labelValue: (gradeARatio * 100).toFixed(2) + '%',
          },
          {
            value: responseData.prev_grade_b_count,
            name: '乙级',
            labelValue: (gradeBRatio * 100).toFixed(2) + '%',
          },
          {
            value: responseData.prev_grade_c_count,
            name: '丙级',
            // 使用间接计算的丙级占比，保证总和100%
            labelValue: (gradeCRatio * 100).toFixed(2) + '%',
          },
        ];
        this.medicalRecordQuality();
      } catch (error) {
        console.error('获取统计数据失败：', error);
      }
    },

    //月度趋势数据接口
    async getMonthlyTrendData() {
      const params = {
        start_time: this.searchForm.start_time,
        end_time: this.searchForm.end_time,
        dep_id: this.searchForm.dep_id, // 科室
        doctor_id: this.searchForm.doctor_id, // 接诊医师
      };

      this.monthlyTrendData = {
        dataX: [],
        dataY: [],
      };

      try {
        const res = await this.$axios.post('/omr_zk/quality_month_trend', { params });
        if (res?.code === 200) {
          const resultList = res.data || [];
          resultList.forEach(item => {
            if (item.month) {
              this.monthlyTrendData.dataX.push(item.month);
            }
            if (item.defect_total !== undefined && item.defect_total !== null) {
              this.monthlyTrendData.dataY.push(item.defect_total);
            }
          });
          if (this.monthlyTrendData.dataX.length > 0 || this.monthlyTrendData.dataY.length > 0) {
            this.monthlyTrend(this.monthlyTrendData);
          }
        }
      } catch (error) {
        console.error('请求月度趋势数据失败：', error);
      }
    },

    // 获取问题分析数据
    async getDefectAnalysisData() {
      const params = {
        start_time: this.searchForm.start_time,
        end_time: this.searchForm.end_time,
        dep_id: this.searchForm.dep_id,
        is_top10: 1,
        doctor_id: this.searchForm.doctor_id,
      };

      try {
        // 调用问题分析接口（需根据实际接口调整）
        const res = await this.$axios.post('/omr_zk/defect_issues', { params });
        const fakeData = res?.data.list || [];
        console.log('问题分析数据：', fakeData);

        // // 格式化数据
        // this.defectAnalysisData = {
        //   categories: data.map(item => item.field || item.name),
        //   counts: data.map(item => item.total_num || 0),
        //   rates: data.map(item => (item.defect_rate || 0).toFixed(2)),
        // };

        // 格式化假数据
        this.defectAnalysisData = {
          categories: fakeData.map(item => item.desc),
          counts: fakeData.map(item => item.total_num || 0),
          rates: fakeData.map(item => item.defect_rate || 0),
        };

        // 渲染图表
        this.renderDefectAnalysisChart();
      } catch (error) {
        console.error('获取问题分析数据失败：', error);
        this.defectAnalysisData = { categories: [], counts: [], rates: [] };
        this.renderDefectAnalysisChart();
      }
    },

    async getDeptAnalysisData() {
      const params = {
        start_time: this.searchForm.start_time,
        end_time: this.searchForm.end_time,
        dep_id: this.searchForm.dep_id,
        is_top10: 1,
        doctor_id: this.searchForm.doctor_id,
      };

      try {
        // 调用问题分析接口（需根据实际接口调整）
        const res = await this.$axios.post('/omr_zk/ranking_department', { params });
        const fakeData = res?.data.list || [];
        console.log('问题分析数据：', fakeData);

        // 格式化假数据
        this.deptAnalysisData = {
          categories: fakeData.map(item => item.name),
          counts: fakeData.map(item => item.total_error_medical || 0),
          rates: fakeData.map(item => item.deduct_score || 0),
        };
        console.log('部门分析数据：', this.deptAnalysisData);

        // 渲染图表
        this.renderDeptAnalysisChart();
      } catch (error) {
        console.error('获取问题分析数据失败：', error);
        this.deptAnalysisData = { categories: [], counts: [], rates: [] };
        this.renderDeptAnalysisChart();
      }
    },

    //缺陷数据接口
    async defectDataM(num) {
      const params = {
        start_time: this.searchForm.start_time,
        end_time: this.searchForm.end_time,
        dep_id: this.searchForm.dep_id,
        doctor_id: this.searchForm.doctor_id,
        is_top10: num,
        page: this.paginationDataDefect.currentPage,
        page_size: this.paginationDataDefect.pageSize,
      };

      try {
        const res = await this.$axios.post('/omr_zk/defect_issues', { params });
        const responseData = res?.data || [];
        if (num == 1) {
          this.defectChartData = responseData.list;
          this.defectDataTop10();
        } else {
          this.defectTableData = responseData.list;
          this.paginationDataDefect.total = responseData.count;
        }
      } catch (error) {
        console.error('获取缺陷数据失败：', error);
        this.defectData = [];
      }
    },
    //科室数据接口
    async departmentDataTopM(num) {
      const params = {
        start_time: this.searchForm.start_time,
        end_time: this.searchForm.end_time,
        dep_id: this.searchForm.dep_id, // 科室
        doctor_id: this.searchForm.doctor_id, // 接诊医师
        order: this.searchForm.order,
        is_top10: num,
        page: this.paginationDataDept.currentPage,
        page_size: this.paginationDataDept.pageSize,
      };

      this.departmentChartData = {
        dataA: [],
        dataB: [],
        dataC: [],
      };

      try {
        const res = await this.$axios.post('/omr_zk/ranking_department', { params });

        if (Array.isArray(res.data.list)) {
          const resultList = res.data.list;

          if (num == 1) {
            this.deptChartData = resultList;
            this.departmentDataTop();
          } else {
            this.deptTableData = resultList;
            this.paginationDataDept.total = res.data.count;
          }

          // resultList.forEach(item => {
          //   if (!item) return;

          //   if (item.name) {
          //     this.departmentChartData.dataA.push(item.name);
          //   }
          //   const totalMedical = typeof item.total_medical === 'number' ? item.total_medical : 0;
          //   this.departmentChartData.dataB.push(totalMedical);

          //   const totalErrorMedical = typeof item.total_error_medical === 'number' ? item.total_error_medical : 0;
          //   this.departmentChartData.dataC.push(totalErrorMedical);
          // });
        }
      } catch (error) {
        console.error('获取科室数据失败：', error);
        this.departmentChartData = {
          dataA: [],
          dataB: [],
          dataC: [],
        };
      }
    },

    async doctorDataTopM(num) {
      const params = {
        start_time: this.searchForm.start_time,
        end_time: this.searchForm.end_time,
        is_top10: num || 0,
        dep_id: this.searchForm.dep_id,
        doctor_id: this.searchForm.doctor_id,
        page: this.paginationDataDoctor?.currentPage || 1,
        page_size: this.paginationDataDoctor?.pageSize || 10,
        order: this.searchForm.order,
      };

      this.doctorChartDataObj = {
        dataA: [],
        dataB: [],
        dataC: [],
      };

      try {
        const res = await this.$axios.post('/omr_zk/ranking_doctor', { params });

        if (res?.code === 200 && res?.data && Array.isArray(res.data.list)) {
          const resultList = res.data.list;

          if (num === 1) {
            this.doctorChartData = resultList;
            this.doctorDataTop();
          } else {
            this.doctorTableData = resultList;
            this.paginationDataDoctor.total = res.data.count || 0;
          }
        }
      } catch (error) {
        console.error('获取医生排名数据失败：', error);
        this.doctorChartDataObj = {
          dataA: [],
          dataB: [],
          dataC: [],
        };
        // 分页数据兜底，避免表格渲染异常
        this.paginationDataDoctor.total = 0;
        this.doctorTableData = [];
      }
    },

    toPage(type, row) {
      //缺陷问题
      if (type === '1') {
        const container = document.querySelector('.outpatient-container');
        if (container) {
          this.scrollPos = container.scrollTop; // 保存当前滚动距离
          console.log('保存的滚动距离：', this.scrollPos);
        }
        this.$router.push({
          path: '/outpatientMedicalSummaryDefectNumber',
          query: {
            is_error: 1,
            rule_id: row.rule_id,
            dep_id: this.searchForm.dep_id,
            doctor_id: this.searchForm.doctor_id,
            start_time: this.searchForm.start_time,
            end_time: this.searchForm.end_time,
          },
        });
      }
    },
    switchGranularity(module, key) {
      this[module] = key;

      console.log(`${module} 切换为 ${key}`);
      if (module === 'defectActiveKey') {
        // this.$nextTick(() => {
        //   this.defectDataTop10();
        // });

        if (key === 'top10') {
          this.$nextTick(() => this.defectDataTop10());
        } else if (key === 'analysis') {
          this.$nextTick(() => {
            this.renderDefectAnalysisChart();
            this.renderDeptAnalysisChart();
          });
        }
      } else if (module === 'deptActiveKey') {
        // this.$nextTick(() => {
        //   this.departmentDataTop();
        // });

        if (key === 'top10') {
          this.$nextTick(() => this.departmentDataTop());
        } else if (key === 'analysis') {
          this.$nextTick(() => this.renderDeptAnalysisChart());
        }
      } else if (module === 'doctorActiveKey' && key === 'top10') {
        this.$nextTick(() => {
          this.doctorDataTop();
        });
      }
    },

    changeActiveKey(module, key) {
      this[module] = key;
      console.log(`${module} 切换为 ${key}`);
      if (module === 'defectActiveKey' && key === 'top10') {
        this.$nextTick(() => {
          this.defectDataTop10();
        });
      } else if (module === 'deptActiveKey' && key === 'top10') {
        this.$nextTick(() => {
          this.departmentDataTop();
        });
      } else if (module === 'doctorActiveKey' && key === 'top10') {
        this.$nextTick(() => {
          this.doctorDataTop();
        });
      }

      if (module === 'deptActiveKey' && key === 'top10') {
        this.$nextTick(() => {
          this.departmentDataTop();
        });
      }
      if (module === 'doctorActiveKey' && key === 'top10') {
        this.$nextTick(() => {
          this.doctorDataTop();
        });
      }
    },

    getDeportmentList() {
      this.$axios
        .post('/get_omr_department_list')
        .then(res => {
          const { data } = res;
          this.departmentList = data;
        })
        .catch(error => {
          console.log(error);
        });
    },
    getStaffList() {
      this.$axios.post('/omr_zk/docker_list').then(res => {
        this.staffList = res.data;
      });
    },

    handleSortChange(type, sortObj) {
      const { order } = sortObj;
      if (order) {
        this.searchForm.order = order === 'descending' ? 'desc' : 'asc';
      } else {
        this.searchForm.order = 'desc';
        this.ysSortParams.order = 'descending';
      }

      if (type === 'dept') {
        this.departmentDataTopM(0);
      }
      if (type === 'doctor') {
        this.doctorDataTopM(0);
      }
    },

    defectDataTop10() {
      const chartDom = document.getElementById('defectDataTop10Id');
      if (!chartDom) {
        console.warn('图表容器不存在');
        return;
      }

      if (this.defectDataTop10IdCharts) {
        this.defectDataTop10IdCharts.dispose();
        this.defectDataTop10IdCharts = null;
      }

      chartDom.style.width = '100%';
      chartDom.style.height = '600px';
      this.defectDataTop10IdCharts = this.$echarts.init(chartDom);

      const isDataEmpty = !Array.isArray(this.defectChartData) || this.defectChartData.length === 0;

      if (isDataEmpty) {
        const emptyOption = {
          title: {
            text: '暂无数据',
            x: 'center',
            y: 'center',
            textStyle: {
              fontSize: 20,
              fontWeight: 'normal',
              color: '#999',
            },
          },
          xAxis: { show: false },
          yAxis: { show: false },
          series: [],
        };
        this.defectDataTop10IdCharts.setOption(emptyOption);
        return;
      }

      const xAxisData = [];
      const yAxisData = [];

      this.defectChartData.forEach(item => {
        if (item.field || item.name) {
          xAxisData.push(item.field || item.name);
        }
        if (item.total_num !== undefined && item.total_num !== null) {
          yAxisData.push(item.total_num);
        }
      });

      xAxisData.reverse();
      yAxisData.reverse();
      // 正常图表配置
      const option = {
        tooltip: {
          trigger: 'axis',
          axisPointer: {
            type: 'shadow',
          },
        },
        xAxis: {
          type: 'value',
          boundaryGap: [0, 0.01],
          axisLabel: {
            formatter: '{value} 条',
            fontSize: 16,
            color: '#666',
          },
        },
        yAxis: {
          type: 'category',
          data: xAxisData,
        },
        series: [
          {
            name: '2012',
            type: 'bar',
            data: yAxisData,
            label: {
              show: true,
              position: 'right',
              offset: [10, 0],
              color: '#5087ec',
              fontSize: 16,
              fontWeight: '500',
              formatter: '{c} 条',
              align: 'left',
            },
            itemStyle: {
              borderRadius: [0, 4, 4, 0],
              color: '#5087ec',
            },
            barWidth: '60%',
          },
        ],
      };

      this.defectDataTop10IdCharts.setOption(option);

      window.addEventListener('resize', () => {
        this.defectDataTop10IdCharts?.resize();
      });
    },

    renderDefectAnalysisChart() {
      const chartDom = document.getElementById('defectAnalysisId');
      if (!chartDom) {
        console.warn('问题分析图表容器不存在');
        return;
      }

      if (this.defectAnalysisIdCharts) {
        this.defectAnalysisIdCharts.dispose();
        this.defectAnalysisIdCharts = null;
      }

      chartDom.style.width = '100%';
      chartDom.style.height = '500px';
      this.defectAnalysisIdCharts = this.$echarts.init(chartDom);

      if (this.defectAnalysisData.categories.length === 0) {
        const emptyOption = {
          title: {
            text: '暂无数据',
            x: 'center',
            y: 'center',
            textStyle: { fontSize: 20, color: '#999' },
          },
          xAxis: { show: false },
          yAxis: { show: false },
          series: [],
        };
        this.defectAnalysisIdCharts.setOption(emptyOption);
        return;
      }

      const colorList = ['#FF6B6B', '#4ECDC4', '#45B7D1', '#96CEB4', '#DDA0DD', '#98D8C8', '#85C1E9', '#FFEAA7', '#F7DC6F', '#BB8FCE'];

      const seriesData = this.defectAnalysisData.counts.map((value, index) => ({
        value: value,
        itemStyle: {
          color: colorList[index % colorList.length],
        },
      }));

      const option = {
        title: {
          text: '门诊电子病历缺陷问题分布',
          left: 'center',
          textStyle: {
            fontSize: 18,
            fontWeight: 'bold',
            color: '#333',
          },
        },
        tooltip: {
          trigger: 'axis',
          axisPointer: {
            type: 'shadow',
          },
          formatter: function (params) {
            const item = params[0];
            return `${item.name}<br/>缺陷数量: ${item.value}个`;
          },
        },
        grid: {
          left: '3%',
          right: '4%',
          bottom: '15%',
          containLabel: true,
        },
        xAxis: {
          type: 'category',
          data: this.defectAnalysisData.categories,
          axisLabel: {
            rotate: 45,
            interval: 0,
            fontSize: 14,
            color: '#333',
          },
          axisTick: {
            alignWithLabel: true,
          },
        },
        yAxis: {
          type: 'value',
          axisLabel: {
            fontSize: 14,
            color: '#666',
          },
          splitLine: {
            lineStyle: {
              type: 'dashed',
              color: '#e0e0e0',
            },
          },
        },
        series: [
          {
            name: '缺陷数量',
            type: 'bar',
            barWidth: '60%',
            data: seriesData,
            label: {
              show: true,
              position: 'top',
              fontSize: 12,
              color: '#333',
              fontWeight: 'bold',
            },
            itemStyle: {
              borderRadius: [4, 4, 0, 0],
              shadowBlur: 10,
              shadowColor: 'rgba(0, 0, 0, 0.3)',
            },
          },
        ],
      };

      this.defectAnalysisIdCharts.setOption(option);

      window.addEventListener('resize', () => {
        this.defectAnalysisIdCharts?.resize();
      });
    },

    renderDeptAnalysisChart() {
      const chartDom = document.getElementById('deptAnalysisId');
      if (!chartDom) {
        console.warn('renderDeptAnalysisChart问题分析图表容器不存在');
        return;
      }

      if (this.deptAnalysisIdCharts) {
        this.deptAnalysisIdCharts.dispose();
        this.deptAnalysisIdCharts = null;
      }

      chartDom.style.width = '100%';
      chartDom.style.height = '500px';
      this.deptAnalysisIdCharts = this.$echarts.init(chartDom);

      if (this.deptAnalysisData.categories.length === 0) {
        const emptyOption = {
          title: {
            text: '暂无数据',
            x: 'center',
            y: 'center',
            textStyle: { fontSize: 20, color: '#999' },
          },
          xAxis: { show: false },
          yAxis: { show: false },
          series: [],
        };
        this.deptAnalysisIdCharts.setOption(emptyOption);
        return;
      }

      const colorList = ['#FF6B6B', '#4ECDC4', '#45B7D1', '#96CEB4', '#DDA0DD', '#98D8C8', '#85C1E9', '#FFEAA7', '#F7DC6F', '#BB8FCE'];

      const seriesData = this.deptAnalysisData.counts.map((value, index) => ({
        value: value,
        itemStyle: {
          color: colorList[index % colorList.length],
        },
      }));

      const option = {
        title: {
          text: '各科室电子病历缺陷问题分布',
          left: 'center',
          textStyle: {
            fontSize: 18,
            fontWeight: 'bold',
            color: '#333',
          },
        },
        tooltip: {
          trigger: 'axis',
          axisPointer: {
            type: 'shadow',
          },
          formatter: function (params) {
            const item = params[0];
            return `${item.name}<br/>缺陷数量: ${item.value}个`;
          },
        },
        grid: {
          left: '3%',
          right: '4%',
          bottom: '15%',
          containLabel: true,
        },
        xAxis: {
          type: 'category',
          data: this.deptAnalysisData.categories,
          axisLabel: {
            rotate: 45,
            interval: 0,
            fontSize: 14,
            color: '#333',
          },
          axisTick: {
            alignWithLabel: true,
          },
        },
        yAxis: {
          type: 'value',
          axisLabel: {
            fontSize: 14,
            color: '#666',
          },
          splitLine: {
            lineStyle: {
              type: 'dashed',
              color: '#e0e0e0',
            },
          },
        },
        series: [
          {
            name: '缺陷数量',
            type: 'bar',
            barWidth: '60%',
            data: seriesData,
            label: {
              show: true,
              position: 'top',
              fontSize: 12,
              color: '#333',
              fontWeight: 'bold',
            },
            itemStyle: {
              borderRadius: [4, 4, 0, 0],
              shadowBlur: 10,
              shadowColor: 'rgba(0, 0, 0, 0.3)',
            },
          },
        ],
      };

      this.deptAnalysisIdCharts.setOption(option);

      window.addEventListener('resize', () => {
        this.deptAnalysisIdCharts?.resize();
      });
    },

    departmentDataTop() {
      this.departmentChartData = {
        dataA: [],
        dataB: [],
        dataC: [],
      };
      const deptDataList = Array.isArray(this.deptChartData) ? this.deptChartData : [];

      deptDataList.forEach(item => {
        if (!item) return;

        const deptName = item.name && typeof item.name === 'string' ? item.name : '未知科室';
        this.departmentChartData.dataA.push(deptName);

        const totalMedical = typeof item.total_medical === 'number' ? item.total_medical : 0;
        this.departmentChartData.dataB.push(totalMedical);

        const totalErrorMedical = typeof item.total_error_medical === 'number' ? item.total_error_medical : 0;
        this.departmentChartData.dataC.push(totalErrorMedical);
      });

      const chartDom = document.getElementById('departmentDataTopId');
      if (!chartDom) {
        console.warn('科室图表容器不存在');
        return;
      }

      if (this.departmentDataTopIdCharts) {
        this.departmentDataTopIdCharts.dispose();
        this.departmentDataTopIdCharts = null;
      }

      chartDom.style.width = '100%';
      chartDom.style.height = '600px';

      this.departmentDataTopIdCharts = this.$echarts.init(chartDom);

      const isDataEmpty = this.departmentChartData.dataA.length === 0;
      if (isDataEmpty) {
        const emptyOption = {
          title: {
            text: '暂无数据',
            x: 'center',
            y: 'center',
            textStyle: {
              fontSize: 20,
              fontWeight: 'normal',
              color: '#999',
            },
          },
          xAxis: { show: false },
          yAxis: { show: false },
          series: [],
        };
        this.departmentDataTopIdCharts.setOption(emptyOption);
        return;
      }

      const option = {
        tooltip: {
          trigger: 'axis',
          axisPointer: {
            type: 'shadow',
          },
        },
        legend: {
          orient: 'vertical',
          right: 0,
          top: '50%',
          transform: 'translateY(-50%)',
          textStyle: {
            color: '#333',
            fontSize: 14,
          },
          itemWidth: 16,
          itemHeight: 10,
          itemGap: 15,
        },
        xAxis: {
          type: 'value',
          boundaryGap: [0, 0.01],
          axisLabel: {
            fontSize: 16,
            color: '#666',
          },
        },
        yAxis: {
          type: 'category',
          data: this.departmentChartData.dataA.reverse(),
          axisLabel: {
            fontSize: 16,
            color: '#666',
          },
        },
        series: [
          {
            name: '门诊病历',
            type: 'bar',
            data: this.departmentChartData.dataB.reverse(),
            label: {
              show: true,
              position: 'right',
              offset: [10, 0],
              color: '#5087ec',
              fontSize: 16,
              fontWeight: '500',
              formatter: '{c} 条',
              align: 'left',
            },
            itemStyle: {
              borderRadius: [0, 4, 4, 0],
              color: '#5087ec',
            },
            barWidth: '30%',
          },
          {
            name: '问题病历',
            type: 'bar',
            data: this.departmentChartData.dataC.reverse(),
            label: {
              show: true,
              position: 'right',
              offset: [10, 0],
              color: '#990000',
              fontSize: 16,
              fontWeight: '500',
              formatter: '{c} 条',
              align: 'left',
            },
            itemStyle: {
              borderRadius: [0, 4, 4, 0],
              color: '#990000',
            },
            barWidth: '30%',
          },
        ],
      };

      this.departmentDataTopIdCharts.setOption(option);

      window.addEventListener('resize', () => {
        this.departmentDataTopIdCharts?.resize();
      });
    },
    doctorDataTop() {
      this.doctorChartDataObj = {
        dataA: [],
        dataB: [],
        dataC: [],
      };
      const docDataList = Array.isArray(this.doctorChartData) ? this.doctorChartData : [];

      docDataList.forEach(item => {
        if (!item) return;

        const doctorName = item.doctor_name && typeof item.doctor_name === 'string' ? item.doctor_name : '未知医师';
        this.doctorChartDataObj.dataA.push(doctorName);

        const totalMedical = typeof item.total_medical === 'number' ? item.total_medical : 0;
        this.doctorChartDataObj.dataB.push(totalMedical);

        const totalErrorMedical = typeof item.total_error_medical === 'number' ? item.total_error_medical : 0;
        this.doctorChartDataObj.dataC.push(totalErrorMedical);
      });

      const chartDom = document.getElementById('doctorDataTopId');
      if (!chartDom) {
        console.warn('医生排名图表容器不存在，请检查容器ID是否为 doctorDataTopId');
        return;
      }

      if (this.doctorDataTopIdCharts) {
        this.doctorDataTopIdCharts.dispose();
        this.doctorDataTopIdCharts = null;
      }

      chartDom.style.width = '100%';
      chartDom.style.height = '600px';

      this.doctorDataTopIdCharts = this.$echarts.init(chartDom);

      const isDataEmpty = this.doctorChartDataObj.dataA.length === 0;
      if (isDataEmpty) {
        const emptyOption = {
          title: {
            text: '暂无数据',
            x: 'center',
            y: 'center',
            textStyle: {
              fontSize: 20,
              fontWeight: 'normal',
              color: '#999',
            },
          },
          xAxis: { show: false },
          yAxis: { show: false },
          series: [],
        };
        this.doctorDataTopIdCharts.setOption(emptyOption);
        return;
      }

      const option = {
        tooltip: {
          trigger: 'axis',
          axisPointer: {
            type: 'shadow',
          },
        },
        legend: {
          orient: 'vertical',
          right: 0,
          top: '50%',
          transform: 'translateY(-50%)',
          textStyle: {
            color: '#333',
            fontSize: 14,
          },
          itemWidth: 16,
          itemHeight: 10,
          itemGap: 15,
          data: ['病历数量', '缺陷病历数量'],
        },
        xAxis: {
          type: 'value',
          boundaryGap: [0, 0.01],
          axisLabel: {
            fontSize: 16,
            color: '#666',
          },
        },
        yAxis: {
          type: 'category',
          data: this.doctorChartDataObj.dataA.reverse(),
          axisLabel: {
            fontSize: 16,
            color: '#666',
          },
        },
        series: [
          {
            name: '病历数量',
            type: 'bar',
            data: this.doctorChartDataObj.dataB.reverse(),
            label: {
              show: true,
              position: 'right',
              offset: [10, 0],
              color: '#5087ec',
              fontSize: 16,
              fontWeight: '500',
              formatter: '{c} 条',
              align: 'left',
            },
            itemStyle: {
              borderRadius: [0, 4, 4, 0],
              color: '#5087ec',
            },
            barWidth: '30%',
          },
          {
            name: '缺陷病历数量',
            type: 'bar',
            data: this.doctorChartDataObj.dataC.reverse(),
            label: {
              show: true,
              position: 'right',
              offset: [10, 0],
              color: '#990000',
              fontSize: 16,
              fontWeight: '500',
              formatter: '{c} 条',
              align: 'left',
            },
            itemStyle: {
              borderRadius: [0, 4, 4, 0],
              color: '#990000',
            },
            barWidth: '30%',
          },
        ],
      };

      this.doctorDataTopIdCharts.setOption(option);

      window.addEventListener('resize', () => {
        this.doctorDataTopIdCharts?.resize();
      });
    },

    monthlyTrend(data) {
      console.log('this.monthlyTrendData.dataX', data.dataX);
      const chartDom = document.getElementById('monthlyTrend');
      if (!chartDom) {
        console.warn('图表容器不存在');
        return;
      }

      if (this.monthlyTrendCharts) {
        this.monthlyTrendCharts.dispose();
        this.monthlyTrendCharts = null;
      }

      chartDom.style.width = '100%';
      chartDom.style.height = '360px';

      this.monthlyTrendCharts = this.$echarts.init(chartDom);
      const option = {
        grid: {
          left: '3%',
          right: '4%',
          bottom: '10%',
          top: '10%',
          containLabel: true,
        },
        xAxis: {
          type: 'category',
          data: data.dataX,
          axisLabel: {
            fontSize: 14,
            color: '#666',
          },

          axisLine: {
            lineStyle: {
              fontSize: 14,
            },
          },
          axisTick: {
            lineStyle: {
              fontSize: 16,
            },
          },
        },
        yAxis: {
          type: 'value',
          axisLabel: {
            formatter: '{value} 条',
            fontSize: 16,
            color: '#666',
          },
          axisLine: {
            lineStyle: {
              fontSize: 16,
            },
          },
          axisTick: {
            lineStyle: {
              fontSize: 16,
            },
          },
        },
        series: [
          {
            data: this.monthlyTrendData.dataY,
            type: 'bar',
            label: {
              show: true,
              position: 'top',
              offset: [0, -5],
              color: '#5087ec',
              fontSize: 18,
              formatter: '{c} 条',
            },
            itemStyle: {
              color: '#5087ec',
            },
          },
        ],
      };

      this.monthlyTrendCharts.setOption(option);
      window.addEventListener('resize', () => {
        this.monthlyTrendCharts.resize();
      });
    },
    medicalRecordQuality() {
      const chartDom = document.getElementById('medicalRecordQualityId');
      if (!chartDom) {
        console.warn('图表容器不存在');
        return;
      }

      if (this.medicalRecordQualityIdCharts) {
        this.medicalRecordQualityIdCharts.dispose();
        this.medicalRecordQualityIdCharts = null;
      }

      chartDom.style.width = '100%';
      chartDom.style.height = '100%';

      this.medicalRecordQualityIdCharts = this.$echarts.init(chartDom);
      const option = {
        color: ['#2D8042', '#E6851A', '#C5350C'],
        tooltip: {
          trigger: 'item',
          formatter: '{b}: {c} ({d}%)',
          backgroundColor: 'rgba(255, 255, 255, 0.9)',
          borderColor: '#e6e6e6',
          borderWidth: 1,
          textStyle: {
            color: '#333',
            fontSize: 12,
          },
          padding: [8, 12],
          borderRadius: 6,
        },
        legend: {
          orient: 'vertical',
          right: '10%',
          top: '40px',
          margin: [0, 0, 0, 60],
          width: '40%',
          textStyle: {
            color: '#666',
            fontSize: 18,
            fontWeight: 500,
          },
          itemWidth: 30,
          itemHeight: 30,
          itemGap: 40,
          align: 'left',
          formatter: function (name) {
            const targetItem = option.series[0].data.find(item => item.name === name);
            return `${name}上期 ${targetItem ? targetItem.labelValue : ''}`;
          },
        },
        series: [
          {
            name: 'Access From',
            type: 'pie',
            radius: ['30%', '45%'],
            center: ['35%', '35%'],
            avoidLabelOverlap: false,
            data: this.medicalRecordData,
            itemStyle: {
              borderRadius: 10,
              borderColor: '#fff',
              borderWidth: 2,
              emphasis: {
                shadowBlur: 15,
                shadowOffsetX: 0,
                shadowColor: 'rgba(0, 0, 0, 0.3)',
              },
            },
            label: {
              show: true,
              position: 'outside',
              fontSize: 18,
              color: '#333',
              formatter: function (params) {
                return `${params.name}: ${params.data.labelValue}`;
              },
              alignTo: 'labelLine',
              distanceToLabelLine: 5,
            },
            labelLine: {
              show: true,
              length: 20,
              length2: 15,
              lineStyle: {
                color: '#999',
                width: 1,
                type: 'solid',
              },
              smooth: 0.2,
              minTurnAngle: 45,
            },
          },
        ],
        graphic: [
          {
            type: 'group',
            left: '30%',
            top: '30%',
            width: 120,
            height: 60,
            origin: [60, 30],
            anchor: [0.5, 0.5],
            children: [
              {
                type: 'text',
                left: 'center',
                top: '20%',
                style: { text: '质控份数', fontSize: 23, color: '#33333399', textAlign: 'center', fontWeight: '500' },
              },
              {
                type: 'text',
                left: 'center',
                top: '60%',
                style: { text: this.statisticsData.prev_total_count, fontSize: 24, color: '#666', textAlign: 'center' },
              },
            ],
          },
        ],

        grid: {
          left: '5%',
          right: '5%',
          top: '5%',
          bottom: '5%',
        },
        emphasis: {
          scale: true,
          scaleSize: 5,
        },
        animation: true,
        animationDuration: 1000,
        animationEasing: 'cubicOut',
        animationDelay: function (idx) {
          return idx * 50;
        },
      };
      this.medicalRecordQualityIdCharts.setOption(option);
      window.addEventListener('resize', () => {
        this.medicalRecordQualityIdCharts.resize();
      });
    },

    reset() {
      this.searchForm = {
        start_time: moment().startOf('month').format('YYYYMMDD'),
        end_time: moment().format('YYYYMMDD'),
        dep_id: '',
        doctor_id: '',
      };
      if (this.$refs.searchFormRef) {
        this.$refs.searchFormRef.resetFields();
      }
      const container = document.querySelector('.outpatient-container');
      container && (container.scrollTop = 0);
    },
  },
};
</script>
<style lang="scss" scoped>
::v-deep .el-card {
  padding: 0 !important;
}

::v-deep .el-card__body {
  padding: 0 !important;
  height: 100%;
}

::v-deep #monthlyTrend,
#medicalRecordQualityId {
  width: 100%;
  height: 360px;
}

::v-deep #defectDataTop10Id,
#defectAnalysisId,
#deptAnalysisId,
#departmentDataTopId,
#doctorDataTopId {
  width: 100%;
  height: 600px;
  position: relative;
}

.outpatient-container {
  padding: 0 18px;
  min-height: 100vh;
  overflow-y: auto;

  .search-box,
  .defect-problem,
  .department-rank,
  .doctor-rank {
    margin: 18px 0 16px 0;
    background: #fff;
    padding: 15px;
    border-radius: 5px;
    overflow-x: hidden;
  }

  .card-box {
    margin: 18px 0 16px 0;
    background: #fff;
    padding: 15px;
    border-radius: 5px;

    .statistic {
      padding: 10px 0;
      height: 100%;

      .el-row {
        margin-bottom: 0 !important;

        &:not(:last-child) {
          margin-bottom: 20px;
        }
      }
    }
  }
}

.mag-top15 {
  margin-top: 15px;
}

.el-button-group {
  .el-button {
    margin: 0;

    &:first-child {
      border-top-right-radius: 0 !important;
      border-bottom-right-radius: 0 !important;
    }

    &:last-child {
      border-top-left-radius: 0 !important;
      border-bottom-left-radius: 0 !important;
    }
  }
}

.export-btn {
  background-color: #1b64b0 !important;
  color: #fff !important;
  border-radius: 5px !important;
  width: 80px !important;
}
</style>

<!-- 设置滚动条样式 -->
<style lang="scss">
.outpatient-container::-webkit-scrollbar {
  width: 16px;
  height: 16px;
}

.outpatient-container::-webkit-scrollbar-thumb {
  background-color: #dcdfe6;
  border-radius: 8px;
  border: 4px solid transparent;
  background-clip: padding-box;
}

.outpatient-container::-webkit-scrollbar-track {
  background-color: #f5f5f5;
  border-radius: 8px;
}

.span-btn {
  cursor: pointer;
}
</style>