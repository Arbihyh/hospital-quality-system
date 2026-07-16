<template>
  <div>
    <el-dialog
      v-el-drag-dialog
      :title="titleStr"
      :visible.sync="data.bSwitch"
      width="1200px"
      top="5vh"
      class="rule-dialog"
    >
      <!-- 使用flex布局包装内容区域 -->
      <div class="dialog-content-wrapper">
        <!-- 表单内容区域 -->
        <el-form ref="ruleForm" :model="ruleForm" :rules="rules" label-width="80px" class="demo-ruleForm">
          <el-row :gutter="16">
            <el-col :span="8">
              <el-form-item label="首页字段" prop="case_type">
                <el-select v-model="ruleForm.case_type" filterable placeholder="请选择" style="width: 100%;">
                  <el-option
                    v-for="item in caseTypeOptions"
                    :key="item.value"
                    :label="item.label"
                    :value="item.value"
                  />
                </el-select>
              </el-form-item>
            </el-col>
            <el-col :span="8">
              <el-form-item label="缺陷类别" prop="object">
                <el-select v-model="ruleForm.object" placeholder="请选择" style="width: 100%;">
                  <el-option label="A类" value="A类" />
                  <el-option label="B类" value="B类" />
                  <el-option label="C类" value="C类" />
                  <el-option label="D类" value="D类" />
                </el-select>
              </el-form-item>
            </el-col>
            <el-col :span="8">
              <el-form-item label="缺陷类型">
                <el-select
                  v-model="ruleForm.department"
                  collapse-tags
                  placeholder="请选择"
                  style="width: 100%;"
                >
                  <el-option label="基本信息" value="基本信息" />
                  <el-option label="诊疗信息" value="诊疗信息" />
                  <el-option label="费用信息" value="费用信息" />
                </el-select>
              </el-form-item>
            </el-col>
            <el-col :span="8">
              <el-form-item label="规则状态" prop="status">
                <el-select v-model="ruleForm.status" filterable clearable placeholder="请选择" style="width: 100%;">
                  <el-option label="开启" :value="1" />
                  <el-option label="禁用" :value="2" />
                </el-select>
              </el-form-item>
            </el-col>
            <el-col :span="8">
              <el-form-item label="规则类型" prop="type">
                <el-select v-model="ruleForm.type" placeholder="请选择" style="width: 100%;">
                  <el-option
                    v-for="item in qualityTypeOptions"
                    :key="item.value"
                    :label="item.label"
                    :value="item.value"
                  />
                </el-select>
              </el-form-item>
            </el-col>
            <el-col :span="8">
              <el-form-item label="质控场景" prop="changjing">
                <el-select
                  v-model="ruleForm.changjing"
                  multiple
                  collapse-tags
                  placeholder="请选择"
                  style="width: 100%;"
                >
                  <el-option label="编码员" value="编码员" />
                  <el-option label="医生站" value="医生站" />
                  <el-option label="病案室" value="病案室" />
                </el-select>
              </el-form-item>
            </el-col>
            <el-col :span="8">
              <el-form-item label="风险等级" prop="error_level">
                <el-select v-model="ruleForm.error_level" filterable clearable placeholder="请选择" style="width: 100%;">
                  <el-option label="必改" :value="1" />
                  <el-option label="建议" :value="2" />
                </el-select>
              </el-form-item>
            </el-col>
            <el-col :span="8">
              <el-form-item label="扣分分值" prop="score">
                <!-- <el-input-number
                  v-model="ruleForm.score"
                  :precision="1"
                  :step="0.5"
                  step-strictly
                  :max="100"
                  :min="0"
                  controls-position="right"
                  placeholder="请输入"
                  style="width: 100%;"
                /> -->
                <el-input
                  v-model="ruleForm.score"
                  controls-position="right"
                  placeholder="请输入"
                  style="width: 100%;"
                  oninput="this.value = this.value.replace(/[^\d.]/g, '').replace(/^\./g, '').replace(/\.{2,}/g, '.').replace('.', '$#$').replace(/\./g, '').replace('$#$', '.').replace(/^(\d+)\.(\d*)\.$/, '$1.$2')"
                />
              </el-form-item>
            </el-col>
            <el-col :span="24">
              <el-form-item label="规则描述" prop="description">
                <el-input v-model="ruleForm.description" type="textarea" :autosize="{ minRows: 3 }" placeholder="请输入" />
              </el-form-item>
            </el-col>
            <el-col :span="24">
              <el-form-item label="质控规则">
                <el-col :span="5">
                  <el-form-item label="规则模板" prop="rule_type">
                    <el-select v-model="ruleForm.rule_type" filterable placeholder="请选择" style="width: 100%;">
                      <el-option label="首页规则" value="首页规则" />
                    </el-select>
                  </el-form-item>
                </el-col>

                <el-col :span="24" style="margin-bottom: 20px;">
                  <el-button type="primary" plain icon="el-icon-plus" @click="onAddQZTJ">前置条件</el-button>
                  <el-button type="primary" plain icon="el-icon-plus" @click="onAddZKLJ">质控逻辑</el-button>
                  <el-button type="primary" plain icon="el-icon-plus" @click="onAddZKYJ">质控依据</el-button>
                  <el-button type="primary" plain icon="el-icon-plus" @click="onAddZKyujing">质控预警</el-button>
                </el-col>

                <!-- 前置条件 -->
                <el-col v-if="ruleForm.qztj.length" :span="24">
                  <el-card v-for="(item, index) of ruleForm.qztj" :key="'qztj' + index" class="box-card" shadow="never">
                    <div slot="header" class="clearfix box-card_header span">
                      <span class="text">前置条件{{ index + 1 }}</span>
                      <span class="ml40">
                        <el-radio v-model="item.condition_type" :label="1">过滤</el-radio>
                        <el-radio v-model="item.condition_type" :label="2">免审</el-radio>
                      </span>
                      <i
                        class="el-icon-delete"
                        style="float: right; margin-top: 13px; cursor: pointer;"
                        @click="onDeleteQZTJ(index)"
                      />
                    </div>
                    <div>
                      <el-row
                        v-for="(sItem, sIndex) of item.condition_content"
                        :key="'tj' + sIndex"
                        :gutter="12"
                        class="mb12"
                      >
                        <el-col :span="2">
                          <div class="text-right">条件{{ sIndex + 1 }}</div>
                        </el-col>
                        <el-col :span="6">
                          <div class="rule-condition" @click="openCategoryDialog(index, sIndex, 'qztj')">
                            <el-input
                              :value="getDisplayName(sItem.param1)"
                              placeholder="请选择"
                              size="small"
                              readonly
                              style="width: 100%"
                            />
                          </div>
                        </el-col>
                        <el-col :span="3">
                          <el-select
                            v-model="sItem.condition"
                            filterable
                            clearable
                            placeholder="请选择"
                            style="width: 100%;"
                          >
                            <el-option
                              v-for="(items, bindex) in selectFormula"
                              :key="bindex"
                              :label="items.formula"
                              :value="items.formula"
                            />
                          </el-select>
                        </el-col>
                        <el-col v-if="sItem.condition === '时效'" :span="9">
                          <el-row :gutter="8">
                            <el-col :span="6">
                              <div class="rule-condition">
                                <el-input
                                  :value="getDisplayName(sItem.sx_1)"
                                  placeholder="请选择"
                                  size="small"
                                  readonly
                                  style="width: 100%;"
                                />
                                <el-button
                                  size="small"
                                  style="width: 90px; position: absolute; right: 0; top: 0; opacity: 0;"
                                  @click="openCategoryDialog(index, sIndex, 'sx_1')"
                                />
                              </div>
                            </el-col>
                            <el-col :span="2">
                              <div class="text-right">至</div>
                            </el-col>
                            <el-col :span="6">
                              <div class="rule-condition">
                                <el-input
                                  :value="getDisplayName(sItem.sx_2)"
                                  placeholder="请选择"
                                  size="small"
                                  readonly
                                  style="width: 100%;"
                                />
                                <el-button
                                  size="small"
                                  style="width: 90px; position: absolute; right: 0; top: 0; opacity: 0;"
                                  @click="openCategoryDialog(index, sIndex, 'sx_2')"
                                />
                              </div>
                            </el-col>
                            <el-col v-if="sItem.sx_2" :span="0.5">
                              <div class="text-right">+</div>
                            </el-col>
                            <el-col v-if="sItem.sx_2" :span="5">
                              <el-input v-model="sItem.sx_3" clearable placeholder="请输入" style="width: 100%;" />
                            </el-col>
                            <el-col :span="3" style="margin-left: 5px;">
                              <div class="text-right">小时</div>
                            </el-col>
                          </el-row>
                        </el-col>
                        <el-col v-else-if="sItem.condition === '范围'" :span="6">
                          <el-row :gutter="8">
                            <el-col :span="10">
                              <el-input v-model="sItem.fanwei_1" clearable placeholder="请输入" />
                            </el-col>
                            <el-col :span="2">
                              <div class="text-right">至</div>
                            </el-col>
                            <el-col :span="10">
                              <el-input v-model="sItem.fanwei_2" clearable placeholder="请输入" />
                            </el-col>
                          </el-row>
                        </el-col>
                        <el-col v-else-if="['等于', '不等于', '包含', '不包含', '大于', '小于', '大于等于', '小于等于'].includes(sItem.condition)" :span="6">
                          <div class="input-with-category">
                            <template v-if="sItem.hasChildOptions">
                              <!-- 当有三级数据时显示只读输入框和选择按钮 -->
                              <el-input
                                v-if="sItem.hasThirdLevel"
                                :value="sItem.param2"
                                placeholder="请选择"
                                size="small"
                                readonly
                                style="width: calc(100% - 90px)"
                              />
                              <!-- 当没有三级数据时显示多选下拉框 -->
                              <el-select
                                v-else
                                v-model="sItem.param2"
                                multiple
                                collapse-tags
                                filterable
                                clearable
                                placeholder="请选择"
                                size="small"
                                style="width: calc(100% - 90px)"
                                @change="(value) => handleInput(value, index, sIndex, 'qztj')"
                              >
                                <el-option
                                  v-for="option in sItem.childOptions"
                                  :key="option.field"
                                  :label="option.field_name"
                                  :value="option.field_name"
                                />
                              </el-select>
                            </template>
                            <template v-else>
                              <!-- <el-input
                                v-model="sItem.param2"
                                placeholder="请输入"
                                type="textarea"
                                autosize
                                size="small"
                                style="width: calc(100% - 90px)"
                                @input="(value) => handleInput(value, index, sIndex, 'qztj')"
                              /> -->
                              <el-form-item
                                :prop="`qztj[${index}].condition_content[${sIndex}].param2`"
                                :rules="[
                                  { required: true, message: '请输入', trigger: 'blur' }
                                ]"
                                style="width: calc(100% - 90px);margin: 0; display: flex; align-items: center;"
                              >
                                <el-input
                                  v-model="sItem.param2"
                                  placeholder="请输入"
                                  type="textarea"
                                  autosize
                                  size="small"
                                  @input="(value) => handleInput(value, index, sIndex, 'qztj')"
                                />
                              </el-form-item>
                            </template>
                            <!-- 当有三级数据时显示选择按钮 -->
                            <el-button
                              v-if="sItem.hasThirdLevel"
                              size="small"
                              style="width: 90px"
                              class="category-select"
                              @click="openChildOptionsDialog(index, sIndex)"
                            >
                              <i class="el-icon-s-operation" style="margin-right: 5px" />
                              选择
                            </el-button>
                            <!-- 当没有三级数据时显示类型选择 -->
                            <el-select
                              v-else
                              v-model="sItem.categoryType"
                              size="small"
                              placeholder="文本"
                              style="width: 90px"
                              class="category-select"
                              :disabled="sItem.categoryTypeDisabled"
                            >
                              <i slot="prefix" class="el-icon-s-operation" />
                              <el-option
                                v-for="sitem in categories"
                                :key="sitem.value"
                                :label="sitem.label"
                                :value="sitem.value"
                              />
                            </el-select>
                          </div>
                        </el-col>
                        <el-col v-if="sItem.condition === '减'" :span="4">
                          <div class="rule-condition">
                            <el-input
                              :value="getDisplayName(sItem.subtract_param)"
                              placeholder="请选择"
                              size="small"
                              style="width: calc(100% - 70px)"
                              readonly
                              @click="openCategoryDialog(index, sIndex, 'subtract')"
                            />
                            <el-button
                              size="small"
                              style="width: 70px"
                              class="category-select"
                              @click="openCategoryDialog(index, sIndex, 'subtract')"
                            >
                              <i class="el-icon-s-operation" style="margin-right: 5px" />
                              选择
                            </el-button>
                          </div>
                        </el-col>
                        <el-col v-if="sItem.condition === '减'" :span="3">
                          <el-select
                            v-model="sItem.subtract_condition"
                            filterable
                            clearable
                            placeholder="请选择"
                            style="width: 100%;"
                          >
                            <el-option label="大于" value="gt" />
                            <el-option label="小于" value="lt" />
                            <el-option label="等于" value="eq" />
                          </el-select>
                        </el-col>
                        <el-col v-if="sItem.condition === '减'" :span="3">
                          <div class="input-with-category">
                            <el-input
                              v-model="sItem.subtract_value"
                              placeholder="请输入"
                              size="small"
                              style="width: calc(100% - 70px)"
                            />
                            <el-select
                              v-model="sItem.subtract_value_type"
                              size="small"
                              placeholder="类型"
                              style="width: 70px"
                              class="category-select"
                            >
                              <el-option label="小时" value="hour" />
                              <el-option label="分钟" value="minute" />
                              <el-option label="数值" value="number" />
                            </el-select>
                          </div>
                        </el-col>
                        <el-col :span="3" class="button-actions-col">
                          <div class="button-actions">
                            <!-- 加号按钮 -->
                            <el-button
                              type="primary"
                              plain
                              icon="el-icon-plus"
                              size="mini"
                              class="action-button"
                              @click="onAddTJ(index, sIndex)"
                            />
                            <!-- 减号按钮 -->
                            <el-button
                              v-if="item.condition_content.length !== 1"
                              size="mini"
                              type="primary"
                              plain
                              icon="el-icon-minus"
                              class="action-button"
                              @click="onDeleteTJ(index, sIndex)"
                            />
                          </div>
                        </el-col>
                        <!-- 推送开关单独放在一列 -->
                        <el-col :span="2" class="push-switch-col">
                          <el-tooltip content="是否推送数据" placement="top">
                            <div class="switch-container">
                              <el-switch
                                v-model="sItem.is_post"
                                :active-value="1"
                                :inactive-value="0"
                                class="compact-switch"
                              />
                              <span class="push-label">推送</span>
                            </div>
                          </el-tooltip>
                        </el-col>
                      </el-row>

                      <div class="span">
                        <span>规则之间的逻辑</span>
                        <el-radio v-model="item.condition_relation" :label="1" class="ml40">且（满足所有条件）</el-radio>
                        <el-radio v-model="item.condition_relation" :label="2">或（满足任意一条件）</el-radio>
                      </div>
                    </div>
                  </el-card>
                </el-col>

                <!-- 质控逻辑 -->
                <el-col :span="24">
                  <el-divider />
                  <el-card v-for="(item, index) of ruleForm.rule" :key="'rule' + index" class="box-card" shadow="never">
                    <div slot="header" class="clearfix box-card_header span">
                      <span class="text">质控逻辑</span>
                      <span class="ml40">
                        <span>流向</span>
                        <el-radio v-model="item.detail_status" :label="1" class="ml40">正确</el-radio>
                        <el-radio v-model="item.detail_status" :label="2">错误</el-radio>
                      </span>
                    </div>
                    <div>
                      <el-row
                        v-for="(sItem, sIndex) of item.condition_content"
                        :key="'tj' + sIndex"
                        :gutter="12"
                        class="mb12"
                      >
                        <el-col :span="2">
                          <div class="text-right">逻辑{{ sIndex + 1 }}</div>
                        </el-col>
                        <el-col :span="6">
                          <div class="rule-condition" @click="openCategoryDialog(index, sIndex, 'rule')">
                            <el-input
                              :value="getDisplayName(sItem.param1)"
                              placeholder="请选择"
                              size="small"
                              readonly
                              style="width: 100%"
                            />
                          </div>
                        </el-col>
                        <el-col :span="2">
                          <el-select
                            v-model="sItem.condition"
                            filterable
                            clearable
                            placeholder="请选择"
                            style="width: 100%;"
                          >
                            <el-option
                              v-for="(items, bindex) in selectFormula"
                              :key="bindex"
                              :label="items.formula"
                              :value="items.formula"
                            />
                          </el-select>
                        </el-col>
                        <el-col v-if="sItem.condition === '时效'" :span="9">
                          <el-row :gutter="8">
                            <el-col :span="6">
                              <div class="rule-condition">
                                <el-input
                                  :value="getDisplayName(sItem.sx_1)"
                                  placeholder="请选择"
                                  size="small"
                                  readonly
                                  style="width: 100%;"
                                />
                                <el-button
                                  size="small"
                                  style="width: 90px; position: absolute; right: 0; top: 0; opacity: 0;"
                                  @click="openCategoryDialog(index, sIndex, 'sx_1')"
                                />
                              </div>
                            </el-col>
                            <el-col :span="2">
                              <div class="text-right">至</div>
                            </el-col>
                            <el-col :span="6">
                              <div class="rule-condition">
                                <el-input
                                  :value="getDisplayName(sItem.sx_2)"
                                  placeholder="请选择"
                                  size="small"
                                  readonly
                                  style="width: 100%;"
                                />
                                <el-button
                                  size="small"
                                  style="width: 90px; position: absolute; right: 0; top: 0; opacity: 0;"
                                  @click="openCategoryDialog(index, sIndex, 'sx_2')"
                                />
                              </div>
                            </el-col>
                            <el-col v-if="sItem.sx_2" :span="0.5">
                              <div class="text-right">+</div>
                            </el-col>
                            <el-col v-if="sItem.sx_2" :span="5">
                              <el-input v-model="sItem.sx_3" clearable placeholder="请输入" style="width: 100%;" />
                            </el-col>
                            <el-col :span="3" style="margin-left: 5px;">
                              <div class="text-right">小时</div>
                            </el-col>
                          </el-row>
                        </el-col>
                        <el-col v-else-if="sItem.condition === '范围'" :span="6">
                          <el-row :gutter="8">
                            <el-col :span="10">
                              <el-input v-model="sItem.fanwei_1" clearable placeholder="请输入" />
                            </el-col>
                            <el-col :span="2">
                              <div class="text-right">至</div>
                            </el-col>
                            <el-col :span="10">
                              <el-input v-model="sItem.fanwei_2" clearable placeholder="请输入" />
                            </el-col>
                          </el-row>
                        </el-col>
                        <el-col v-else-if="['等于', '不等于', '包含', '不包含', '大于', '小于', '大于等于', '小于等于'].includes(sItem.condition)" :span="6">
                          <div class="input-with-category">
                            <template v-if="sItem.hasChildOptions">
                              <!-- 当有三级数据时显示只读输入框和选择按钮 -->
                              <el-input
                                v-if="sItem.hasThirdLevel"
                                :value="sItem.param2"
                                placeholder="请选择"
                                size="small"
                                readonly
                                style="width: calc(100% - 90px)"
                              />
                              <!-- 当没有三级数据时显示多选下拉框 -->
                              <el-select
                                v-else
                                v-model="sItem.param2"
                                multiple
                                collapse-tags
                                filterable
                                clearable
                                placeholder="请选择"
                                size="small"
                                style="width: calc(100% - 90px)"
                                @change="(value) => handleInput(value, index, sIndex, 'rule')"
                              >
                                <el-option
                                  v-for="option in sItem.childOptions"
                                  :key="option.field"
                                  :label="option.field_name"
                                  :value="option.field_name"
                                />
                              </el-select>
                            </template>
                            <template v-else>
                              <!-- <el-input
                                v-model="sItem.param2"
                                placeholder="请输入"
                                type="textarea"
                                autosize
                                size="small"
                                style="width: calc(100% - 90px)"
                                @input="(value) => handleInput(value, index, sIndex, 'rule')"
                              /> -->
                              <el-form-item
                                :prop="`rule[${index}].condition_content[${sIndex}].param2`"
                                :rules="[
                                  { required: true, message: '请输入', trigger: 'blur' }
                                ]"
                                style="width: calc(100% - 90px); margin: 0; display: flex; align-items: center;"
                              >
                                <el-input
                                  v-model="sItem.param2"
                                  placeholder="请输入"
                                  type="textarea"
                                  autosize
                                  size="small"
                                  @input="(value) => handleInput(value, index, sIndex, 'rule')"
                                />
                              </el-form-item>
                            </template>
                            <!-- 当有三级数据时显示选择按钮 -->
                            <el-button
                              v-if="sItem.hasThirdLevel"
                              size="small"
                              style="width: 90px"
                              class="category-select"
                              @click="openChildOptionsDialog(index, sIndex)"
                            >
                              <i class="el-icon-s-operation" style="margin-right: 5px" />
                              选择
                            </el-button>
                            <!-- 当没有三级数据时显示类型选择 -->
                            <el-select
                              v-else
                              v-model="sItem.categoryType"
                              size="small"
                              placeholder="文本"
                              style="width: 90px"
                              class="category-select"
                              :disabled="sItem.categoryTypeDisabled"
                            >
                              <i slot="prefix" class="el-icon-s-operation" />
                              <el-option
                                v-for="sitem in categories"
                                :key="sitem.value"
                                :label="sitem.label"
                                :value="sitem.value"
                              />
                            </el-select>
                          </div>
                        </el-col>
                        <el-col v-if="sItem.condition === '减'" :span="4">
                          <div class="rule-condition">
                            <el-input
                              :value="getDisplayName(sItem.subtract_param)"
                              placeholder="请选择"
                              size="small"
                              style="width: calc(100% - 90px)"
                              readonly
                              @click="openCategoryDialog(index, sIndex, 'subtract')"
                            />
                            <el-button
                              size="small"
                              style="width: 90px"
                              class="category-select"
                              @click="openCategoryDialog(index, sIndex, 'subtract')"
                            >
                              <i class="el-icon-s-operation" style="margin-right: 5px" />
                              选择
                            </el-button>
                          </div>
                        </el-col>
                        <el-col v-if="sItem.condition === '减'" :span="3">
                          <el-select
                            v-model="sItem.subtract_condition"
                            filterable
                            clearable
                            placeholder="请选择"
                            style="width: 100%;"
                          >
                            <el-option label="大于" value="gt" />
                            <el-option label="小于" value="lt" />
                            <el-option label="等于" value="eq" />
                          </el-select>
                        </el-col>
                        <el-col v-if="sItem.condition === '减'" :span="3">
                          <div class="input-with-category">
                            <el-input
                              v-model="sItem.subtract_value"
                              placeholder="请输入"
                              size="small"
                              style="width: calc(100% - 70px)"
                            />
                            <el-select
                              v-model="sItem.subtract_value_type"
                              size="small"
                              placeholder="类型"
                              style="width: 70px"
                              class="category-select"
                            >
                              <el-option label="小时" value="hour" />
                              <el-option label="分钟" value="minute" />
                              <el-option label="数值" value="number" />
                            </el-select>
                          </div>
                        </el-col>
                        <el-col :span="3" class="button-actions-col">
                          <div class="button-actions">
                            <!-- 加号按钮 -->
                            <el-button
                              type="primary"
                              plain
                              icon="el-icon-plus"
                              size="mini"
                              class="action-button"
                              @click="onAddGZ(index, sIndex)"
                            />
                            <!-- 减号按钮 -->
                            <el-button
                              v-if="item.condition_content.length !== 1"
                              size="mini"
                              type="primary"
                              plain
                              icon="el-icon-minus"
                              class="action-button"
                              @click="onDeleteGZ(index, sIndex)"
                            />
                          </div>
                        </el-col>
                        <!-- 推送开关单独放在一列 -->
                        <el-col :span="2" class="push-switch-col">
                          <el-tooltip content="是否推送数据" placement="top">
                            <div class="switch-container">
                              <el-switch
                                v-model="sItem.is_post"
                                :active-value="1"
                                :inactive-value="0"
                                class="compact-switch"
                              />
                              <span class="push-label">推送</span>
                            </div>
                          </el-tooltip>
                        </el-col>
                      </el-row>
                      <div class="span">
                        <span>规则之间的逻辑</span>
                        <el-radio v-model="item.condition_relation" :label="1" class="ml40">且（满足所有条件）</el-radio>
                        <el-radio v-model="item.condition_relation" :label="2">或（满足任意一条件）</el-radio>
                      </div>
                    </div>
                  </el-card>
                </el-col>
                <!-- 质控依据 -->
                <el-col v-if="ruleForm.rule[0].custom_basis.length" :span="24">

                  <el-card v-for="(item, index) of ruleForm.rule" :key="'rule' + index" class="box-card" shadow="never">
                    <div slot="header" class="clearfix box-card_header span">
                      <span class="text">质控依据</span>
                      <i
                        class="el-icon-delete"
                        style="float: right; margin-top: 13px; cursor: pointer;"
                        @click="onDeleteZKYJ(index)"
                      />
                    </div>
                    <div>
                      <el-row v-for="(sItem, sIndex) of item.custom_basis" :key="'tj' + sIndex" :gutter="12" class="mb12">
                        <el-col :span="2">
                          <div class="text-right">条件{{ sIndex + 1 }}</div>
                        </el-col>
                        <el-col :span="6">
                          <el-input v-model="sItem.input1" clearable placeholder="请输入" style="width: 100%;" />
                        </el-col>
                        <el-col :span="6">
                          <div class="rule-condition">
                            <el-input
                              :value="getDisplayName(sItem.param1)"
                              placeholder="请选择"
                              size="small"
                              style="width: calc(100% - 90px)"
                              readonly
                              type="textarea"
                              autosize
                              @click="openCategoryDialog(index, sIndex, 'custom_basis')"
                            />
                            <el-button
                              size="small"
                              style="width: 90px"
                              class="category-select"
                              @click="openCategoryDialog(index, sIndex, 'custom_basis')"
                            >
                              <i class="el-icon-s-operation" style="margin-right: 5px" />
                              选择
                            </el-button>
                          </div>
                        </el-col>
                        <el-col :span="6">
                          <el-input v-model="sItem.input2" clearable placeholder="请输入" type="textarea" autosize style="width: 100%;" />
                        </el-col>
                        <el-col :span="4">
                          <el-button
                            type="primary"
                            plain
                            icon="el-icon-plus"
                            size="mini"
                            @click="onAddTJZKYJ(index, sIndex)"
                          />
                          <el-button
                            v-if="item.custom_basis.length !== 1"
                            size="mini"
                            type="primary"
                            plain
                            icon="el-icon-minus"
                            @click="onDeleteTJZKYJ(index, sIndex)"
                          />
                        </el-col>
                      </el-row>
                    </div>
                  </el-card>
                </el-col>
                <!-- 质控预警 -->
                <el-col v-if="Object.keys(ruleForm.rule[0].custom_msg).length != 0" :span="24">

                  <el-card v-for="(item, index) of ruleForm.rule" :key="'rule' + index" class="box-card" shadow="never">
                    <div slot="header" class="clearfix box-card_header span">
                      <span class="text">质控预警</span>
                      <i
                        class="el-icon-delete"
                        style="float: right; margin-top: 13px; cursor: pointer;"
                        @click="onDeleteZKyujing(index)"
                      />
                    </div>
                    <div>
                      <el-row :gutter="12" class="mb12">
                        <el-col :span="2">
                          <div class="text-right">事件</div>
                        </el-col>
                        <el-col :span="7">
                          <div class="rule-condition" @click="openCategoryDialog(index, 0, 'custom_msg')">
                            <el-input
                              :value="getDisplayName(item.custom_msg.param1)"
                              placeholder="请选择"
                              size="small"
                              readonly
                              style="width: 100%"
                            />
                          </div>
                        </el-col>
                        <el-col :span="1"> + </el-col>
                        <el-col :span="2"><el-input
                          v-model="item.custom_msg.aftertime"
                          clearable
                          placeholder="请输入"
                          style="width: 100%;"
                        /></el-col>
                        <el-col :span="1">分</el-col>
                        <el-col :span="1">前</el-col>
                        <el-col :span="2"><el-input
                          v-model="item.pre_warning_time"
                          clearable
                          placeholder="请输入"
                          style="width: 100%;"
                        /></el-col>
                        <el-col :span="2">分提醒</el-col>
                      </el-row>
                      <el-row :gutter="12" class="mb12">
                        <el-col :span="2">
                          <div class="text-right">预警提示</div>
                        </el-col>
                        <el-col :span="6">
                          <el-input v-model="item.custom_msg.input1" clearable placeholder="请输入" style="width: 100%;" />
                        </el-col>
                        <el-col :span="4">
                          <el-input
                            v-model="item.custom_msg.input3"
                            clearable
                            disabled
                            placeholder="请输入"
                            style="width: 100%;"
                          />
                        </el-col>
                        <el-col :span="6">
                          <el-input v-model="item.custom_msg.input2" clearable placeholder="请输入" style="width: 100%;" />
                        </el-col>
                        <el-col :span="4" />
                      </el-row>
                    </div>
                  </el-card>
                </el-col>
              </el-form-item>
            </el-col>

          </el-row>
        </el-form>
      </div>

      <!-- 页脚按钮固定在底部 -->
      <span slot="footer" class="dialog-footer">
        <el-button type="primary" @click="submitForm('ruleForm')">提 交</el-button>
        <el-button @click="onTest">测试规则</el-button>
      </span>
    </el-dialog>

    <!-- 新增选择弹窗 - 修改配置 -->
    <el-dialog
      title="选择类目"
      :visible.sync="categoryDialogVisible"
      width="600px"
      append-to-body
      destroy-on-close
      :modal-append-to-body="true"
      :close-on-click-modal="true"
      :close-on-press-escape="true"
      custom-class="global-category-dialog"
    >
      <div style="margin-bottom: 10px;">
        <el-input v-model="filterSearch" clearable placeholder="请输入内容" @input="handleSearch">
          <i
            slot="prefix"
            class="el-icon-search el-input__icon"
          />
        </el-input>
      </div>
      <div class="category-content">
        <el-row>
          <el-col :span="8" class="menu-column">
            <!-- 左侧菜单 -->
            <el-menu
              :default-active="activeCategory"
              class="category-menu"
            >
              <el-menu-item
                v-for="item in searchObject"
                :key="item.field_name"
                :index="item.field_name"
                @click="handleCategoryClick(item)"
              >
                {{ item.field_name }}
              </el-menu-item>
            </el-menu>
          </el-col>
          <el-col :span="16">
            <!-- 右侧子项，添加v-if确保始终有内容显示 -->
            <div class="sub-category-list">
              <template v-if="currentSubCategories && currentSubCategories.length > 0">
                <el-button
                  v-for="subItem in currentSubCategories"
                  :key="subItem.field"
                  @click="handleSubCategorySelect(subItem)"
                >
                  {{ subItem.field_name }}
                </el-button>
              </template>
              <template v-else>
                <!-- 当没有子类目时显示的提示或默认选项 -->
                <div class="empty-sub-category">
                  <p>此类目下暂无子选项</p>
                  <!-- 或者显示一些通用选项 -->
                  <el-button @click="handleEmptySubCategorySelect">通用选项</el-button>
                </div>
              </template>
            </div>
          </el-col>
        </el-row>
      </div>
      <span slot="footer" class="dialog-footer">
        <el-button @click="categoryDialogVisible = false">取 消</el-button>
        <el-button type="primary" @click="confirmSelection">确 定</el-button>
      </span>
    </el-dialog>

    <!-- 以类似方式修改其他所有弹窗 -->
    <el-dialog
      title="知识库"
      :visible.sync="knowledgeDialogVisible"
      width="800px"
      append-to-body
      :close-on-click-modal="true"
      :close-on-press-escape="true"
      :show-close="true"
      custom-class="global-knowledge-dialog"
    >
      <div class="knowledge-content">
        <!-- 搜索框 -->
        <el-input
          v-model="searchKeyword"
          placeholder="请输入内容"
          prefix-icon="el-icon-search"
          style="margin-bottom: 15px;"
        />

        <!-- 知识库列表 -->
        <el-table
          :data="knowledgeList.filter(data => !searchKeyword || data.name.toLowerCase().includes(searchKeyword.toLowerCase()))"
          style="width: 100%"
        >
          <el-table-column prop="id" label="序号" width="80" />
          <el-table-column prop="name" label="知识库名称" />
          <el-table-column label="操作" width="180">
            <template slot-scope="scope">
              <el-button type="text" @click="handleKnowledgeView(scope.row)">
                查看
              </el-button>
              <el-button type="text" @click="handleKnowledgeSelect(scope.row)">
                引用
              </el-button>
            </template>
          </el-table-column>
        </el-table>
      </div>
    </el-dialog>

    <!-- 添加知识库详情弹窗 - 确保将内容附加到body -->
    <el-dialog
      title="知识库详情"
      :visible.sync="knowledgeDetailVisible"
      width="600px"
      append-to-body
      destroy-on-close
      :modal-append-to-body="true"
      custom-class="global-knowledge-detail"
    >
      <div class="knowledge-detail">
        <div class="detail-item">
          <span class="label">名称：</span>
          <el-input v-model="editKnowledge.name" placeholder="请输入名称" />
        </div>
        <div class="detail-item">
          <span class="label">ID：</span>
          <span class="id-value">{{ currentKnowledge.id }}</span>
        </div>
        <div class="detail-item">
          <span class="label">用途：</span>
          <div class="tags-input">
            <el-button
              v-for="(item, index) in bzmcArray"
              :key="'bzmc_' + index"
              type="primary"
              plain
              class="tag-button"
            >
              {{ item }}
              <i class="el-icon-close" @click.stop="handleBzmcClose(index)" />
            </el-button>
            <el-input
              v-if="inputVisible.bzmc"
              ref="bzmcInput"
              v-model="editKnowledge.bzmc"
              class="tag-input"
              @keyup.enter.native="handleInputConfirm('bzmc')"
              @blur="handleInputConfirm('bzmc')"
            />
            <el-button v-else type="primary" plain @click="showInput('bzmc')">+ 添加</el-button>
          </div>
        </div>
        <div class="detail-item">
          <span class="label">关键词：</span>
          <div class="tags-input">
            <el-button
              v-for="(keyword, index) in keywordsArray"
              :key="'keyword_' + index"
              type="primary"
              plain
              class="tag-button"
            >
              {{ keyword }}
              <i class="el-icon-close" @click.stop="handleKeywordClose(index)" />
            </el-button>
            <el-input
              v-if="inputVisible.keyword"
              ref="keywordInput"
              v-model="editKnowledge.keyword"
              class="tag-input"
              @keyup.enter.native="handleInputConfirm('keyword')"
              @blur="handleInputConfirm('keyword')"
            />
            <el-button v-else type="primary" plain @click="showInput('keyword')">+ 添加</el-button>
          </div>
        </div>
      </div>
      <span slot="footer" class="dialog-footer">
        <el-button @click="knowledgeDetailVisible = false">取 消</el-button>
        <el-button type="primary" @click="saveKnowledge">保 存</el-button>
      </span>
    </el-dialog>

    <!-- 添加新的对话框 -->
    <el-dialog
      title="选择子选项"
      :visible.sync="childOptionsDialogVisible"
      width="30%"
      append-to-body
      destroy-on-close
      :modal-append-to-body="true"
      custom-class="global-child-options-dialog"
    >
      <div class="child-options-list">
        <el-button
          v-for="option in currentChildOptions"
          :key="option.field"
          size="small"
          @click="handleChildOptionSelect(option)"
        >
          {{ option.field_name }}
        </el-button>
      </div>
    </el-dialog>

  </div>
</template>

<script>
import { add_rule } from '@/api/rule/config'
import { get_all_word_map } from '@/api/dict'
import { get_rule_detail, getSelectFormula } from '@/api/rule/config'
import { edit_word_map } from '@/api/dict'
import { get_rule_setting_other } from '@/api/rule/config'
import { get_dict_by_type } from '@/api/rule/config'

export default {
  props: {
    data: {
      type: Object,
      default() {
        return {
          bSwitch: false,
          row: {}
        }
      }
    },
    objects: {
      type: Array,
      default() {
        return []
      }
    },
    departments: {
      type: Array,
      default() {
        return []
      }
    },
    ruleType: {
      type: Number,
      default: 1
    }
  },
  data() {
    return {
      filterSearch: '',
      qcData: [],
      originalData: [],
      filteredData: [],
      // 初始化为空数组
      searchObject: [],
      // 新增：用于存储原始的、未过滤的类目数据
      originalObjects: [],
      ruleForm: {
        case_type: '',
        changjing: [],
        department: [],
        object: '',
        type: '',
        is_not: '',
        description: '',
        score: undefined,
        error_level: '',
        status: '',
        rule_type: '首页规则',
        selectFormula: [],
        compareFormula: [],
        rule: [
          // 质控逻辑只有一条
          {
            is_pre_condition: 0,
            condition_type: 1,
            condition_relation: 2,
            detail_status: 1,
            condition_content: [
              {
                param1: '',
                param2: '',
                condition: '包含',
                categoryType: '文本',
                sx_1: '',
                sx_2: '',
                sx_3: '',
                subtract_param: '',
                subtract_condition: '',
                subtract_value: '',
                subtract_value_type: 'number',
                hasChildOptions: false,
                childOptions: []
              }
            ],
            custom_basis: [], // 质控依据

            pre_warning_time: '',
            custom_msg: {}
          }
        ],
        qztj: []
      },
      rules: {
        description: [
          { required: true, message: '请输入', trigger: 'blur' }
        ],
        score: [
          { required: true, message: '请输入', trigger: 'blur' }
        ],
        case_type: [
          { required: true, message: '请选择', trigger: 'blur' }
        ],
        changjing: [
          { required: true, message: '请选择', trigger: 'blur' }
        ],
        department: [
          { required: true, message: '请选择', trigger: 'blur' }
        ],
        object: [
          { required: true, message: '请选择', trigger: 'blur' }
        ],
        type: [
          { required: true, message: '请选择', trigger: 'blur' }
        ],
        is_not: [
          { required: true, message: '请选择', trigger: 'blur' }
        ],
        error_level: [
          { required: true, message: '请选择', trigger: 'blur' }
        ],
        status: [
          { required: true, message: '请选择', trigger: 'blur' }
        ],
        rule_type: [
          { required: true, message: '请选择', trigger: 'blur' }
        ]
      },
      categoryDialogVisible: false,
      searchKeyword: '',
      selectedCategoryValue: '',
      currentRuleIndex: null,
      currentConditionIndex: null,
      categories: [],
      categoryType: 'text', // 默认选中文本类型
      activeCategory: '', // 当前选中的类目
      currentSubCategories: [], // 当前显示的子类目列表
      selectedCategory: null,
      currentSource: '',
      currentInputSource: '', // 添加输入来源标识
      knowledgeDialogVisible: false,
      knowledgeList: [],
      currentInputIndex: null,
      currentRuleIndexForKnowledge: null,
      knowledgeDetailVisible: false,
      currentKnowledge: {
        id: '',
        name: '',
        BZMC: '', // 改为字符串类型
        keywords: []
      },
      editKnowledge: {
        name: '',
        bzmc: '',
        keyword: ''
      },
      inputVisible: {
        bzmc: false,
        keyword: false
      },
      caseTypeOptions: [], // 病历类型选项
      qualityTypeOptions: [], // 质控类型选项
      sceneOptions: [], // 质控场景选项
      childOptionsDialogVisible: false,
      currentChildOptions: [],
      categoryOptions: [
        { label: '入院记录', value: 'admission' },
        { label: '出院记录', value: 'discharge' },
        { label: '手术记录', value: 'surgery' },
        { label: '首次病程记录', value: 'firstCourse' },
        { label: '输血病程记录', value: 'bloodTransfusion' },
        { label: '剖宫产记录', value: 'cesarean' },
        { label: '病案首页', value: 'medicalRecordIndex' },
        { label: '通用', value: 'common' }
      ],
      selectedValue: null // 最终选中的值
    }
  },
  computed: {
    titleStr() {
      return this.data.row.id ? '编辑' : '新增'
    },
    filteredCategories() {
      if (!this.searchKeyword) return this.categories
      return this.categories.filter(item =>
        item.label.toLowerCase().includes(this.searchKeyword.toLowerCase())
      )
    },
    bzmcArray() {
      return this.currentKnowledge.BZMC ? this.currentKnowledge.BZMC.split(',') : []
    },
    // 获取格式化后的关键词字符串
    formattedKeywords() {
      return Array.isArray(this.currentKnowledge.keywords)
        ? this.currentKnowledge.keywords.join(',')
        : this.currentKnowledge.keywords || ''
    },
    // 处理关键词数组
    keywordsArray() {
      return this.currentKnowledge.keywords
        ? (typeof this.currentKnowledge.keywords === 'string'
          ? this.currentKnowledge.keywords.split(',')
          : this.currentKnowledge.keywords)
        : []
    }
  },
  created() {
    this.getQcData()
    this.getSelectFormula()
    this.getCategories()
    if (this.data.row.id) {
      this.getDetail(this.data.row.id)
    }
    this.getDropdownData()
  },
  mounted() {
    // 确保弹窗打开时重置滚动位置
    this.$nextTick(() => {
      const dialogBody = document.querySelector('.el-dialog__body')
      if (dialogBody) {
        dialogBody.scrollTop = 0
      }
    })
    this.originalObjects = [...this.objects]
    this.searchObject = [...this.objects]
  },
  methods: {
    onTest() {
      this.$message.info('功能待开放...')
    },
    // 获取详情
    getDetail(id) {
      get_rule_detail({ id }).then(res => {
        const { p } = res
        const {
          case_type,
          changjing,
          department,
          object,
          type,
          is_not,
          description,
          score,
          error_level,
          status
        } = p.rule

        // 直接使用字符串数组，不需要转换为数字
        this.ruleForm.department = department || []

        this.ruleForm.case_type = case_type
        this.ruleForm.changjing = changjing.split(',')
        this.ruleForm.is_not = is_not
        this.ruleForm.status = status
        this.ruleForm.type = type
        this.ruleForm.error_level = error_level
        this.ruleForm.score = score
        this.ruleForm.description = description
        this.ruleForm.object = object

        // 获取规则列表并处理 param2
        // 获取规则列表并处理 param2
        const ruleList = this.getQcRule(p.rule_list)
        ruleList.forEach(rule => {
          // 确保custom_basis存在且为数组
          if (!rule.custom_basis) {
            rule.custom_basis = []
          }

          rule.condition_content.forEach(item => {
            // 如果是字典类型且 param2 是字符串，转换为数组
            if (item.hasChildOptions && typeof item.param2 === 'string' && item.param2) {
              item.param2 = item.param2.split(',')
            }
          })
        })
        this.ruleForm.rule = ruleList

        // 获取前置条件并处理 param2
        const preRuleList = this.getPreRule(p.rule_list)
        preRuleList.forEach(rule => {
          rule.condition_content.forEach(item => {
            // 如果是字典类型且 param2 是字符串，转换为数组
            if (item.hasChildOptions && typeof item.param2 === 'string' && item.param2) {
              item.param2 = item.param2.split(',')
            }
          })
        })
        this.ruleForm.qztj = preRuleList
      })
    },
    // 找到前置条件
    getPreRule(list) {
      return list.filter(item => item.is_pre_condition)
    },
    // 找到质控逻辑
    getQcRule(list) {
      const rule = list.filter(item => !item.is_pre_condition)
      return rule
    },
    getSelectFormula() {
      getSelectFormula({ lx: 0 }).then(res => {
        const { p } = res
        console.log('select formula:', p)
        this.selectFormula = p
      })
    },
    // 搜索质控字典
    getQcData() {
      get_all_word_map({ status: 1 }).then(res => {
        const { p } = res
        this.qcData = Array.isArray(p) ? p : []
      })
    },
    querySearch(queryString, cb) {
      var qcData = this.qcData
      var results = queryString ? qcData.filter(this.createFilter(queryString)) : qcData
      // 调用 callback 返回建议列表的数据
      cb(results)
    },
    createFilter(queryString) {
      return (restaurant) => {
        return (restaurant.name.toLowerCase().indexOf(queryString.toLowerCase()) === 0)
      }
    },
    handleSelect(item) {
      console.log(item)
    },

    // 添加前置条件大框
    onAddQZTJ() {
      this.ruleForm.qztj.push({
        is_pre_condition: 1,
        condition_type: 1,
        condition_relation: 2,
        detail_status: 1,
        condition_content: [
          {
            param1: '',
            param2: '',
            condition: '包含',
            categoryType: '文本',
            sx_1: '',
            sx_2: '',
            sx_3: '',
            subtract_param: '',
            subtract_condition: '',
            subtract_value: '',
            subtract_value_type: 'number',
            hasChildOptions: false,
            childOptions: []
          }
        ]
      })
    },
    // 删除前置条件大框
    onDeleteQZTJ(index) {
      this.ruleForm.qztj.splice(index, 1)
    },
    // 新增前置条件大框中_条件
    onAddTJ(index, sIndex) {
      this.ruleForm.qztj[index].condition_content.push({
        param1: '',
        param2: '',
        condition: '包含',
        categoryType: 'text',
        sx_1: '',
        sx_2: '',
        sx_3: '',
        subtract_param: '',
        subtract_condition: '',
        subtract_value: '',
        subtract_value_type: 'number',
        hasChildOptions: false,
        childOptions: [],
        is_post: 0 // 添加is_post字段，默认关闭
      })
    },
    // 删除前置条件大框中_条件
    onDeleteTJ(index, sIndex) {
      this.ruleForm.qztj[index].condition_content.splice(sIndex, 1)
    },
    // 新增质控逻辑大框中_逻辑
    onAddGZ(index, sIndex) {
      this.ruleForm.rule[index].condition_content.push({
        param1: '',
        param2: '',
        condition: '包含',
        subtract_param: '',
        subtract_condition: '',
        subtract_value: '',
        subtract_value_type: 'number',
        hasChildOptions: false,
        childOptions: [],
        is_post: 0 // 添加is_post字段，默认关闭
      })
    },
    // 删除质控逻辑大框中_逻辑
    onDeleteGZ(index, sIndex) {
      this.ruleForm.rule[index].condition_content.splice(sIndex, 1)
    },
    // 添加质控依据
    onAddZKYJ() {
      // 确保rule[0].custom_basis是一个数组
      if (!this.ruleForm.rule[0].custom_basis) {
        // 使用Vue.set确保响应式
        this.$set(this.ruleForm.rule[0], 'custom_basis', [])
      }

      // 添加新的质控依据
      this.ruleForm.rule[0].custom_basis.push({
        param1: '',
        input1: '',
        input2: ''
      })
    },
    // 删除质控依据大框
    onDeleteZKYJ(index) {
      this.ruleForm.rule[0].custom_basis = []
    },
    // 新增质控依据大框中_条件
    onAddTJZKYJ(index, sIndex) {
      this.ruleForm.rule[index].custom_basis.push({
        param1: '',
        input1: '',
        input2: ''
      })
    },
    // 删除质控依据大框中_条件
    onDeleteTJZKYJ(index, sIndex) {
      this.ruleForm.rule[index].custom_basis.splice(sIndex, 1)
    },
    // 添加质控预警
    onAddZKyujing() {
      this.ruleForm.rule[0].custom_msg = {
        aftertime: '',
        param1: '',
        input1: '',
        input2: ''
      }
    },
    // 删除质控预警大框
    onDeleteZKyujing(index) {
      this.ruleForm.rule[0].custom_msg = {}
    },

    hasEmptyValues(obj) {
      return Object.entries(obj).some(([key, value]) => {
        // 如果是数组（多选的情况），检查数组长度
        if (Array.isArray(value)) {
          return value.length === 0
        }
        // 其他情况保持原有的空值判断
        return !value
      })
    },

    // 验证前置条件
    judgeQZTJ() {
      const { qztj } = this.ruleForm
      let result = true
      const e_index = []

      if (qztj.length) {
        for (let i = 0; i < qztj.length; i++) {
          qztj[i].condition_content.forEach(item => {
            // 检查必填项
            const isEmpty = !item.param1 ||
              (Array.isArray(item.param2) ? item.param2.length === 0 : !item.param2) ||
              !item.condition

            if (isEmpty) {
              e_index.push(i + 1)
            }
          })
        }
        if (e_index.length) {
          this.$message.error(`请完善前置条件${e_index.join()}`)
          result = false
        }
      }
      return result
    },
    // 验证质控逻辑
    judgeRule() {
      const { rule } = this.ruleForm
      let result = true
      // 质控逻辑只有一条
      rule[0].condition_content.map(item => {
        console.log(item)
        if (this.hasEmptyValues(item)) {
          this.$message.error(`请完善质控逻辑`)
          result = false
        }
      })
      return result
    },
    submitForm(formName) {
      this.$refs[formName].validate(async(valid) => {
        if (valid) {
          if (this.judgeQZTJ()) {
            const processConditionContent = (content) => {
              content.forEach(item => {
                // 处理多选值
                if (Array.isArray(item.param2)) {
                  item.param2 = item.param2.join(',')
                }
                if (item.condition === '减' && item.subtract_param) {
                  // 组合减法条件的完整表达式
                  const subtractValue = this.getDisplayName(item.subtract_param)
                  if (subtractValue) {
                    // 获取父级字段名
                    const [parentField] = item.subtract_param
                    // 构造 param2 格式: parentField.field
                    item.param2 = `${parentField}.${item.subtract_param[1]}`
                  }
                }
                // 确保is_post字段被包含
                item.is_post = item.is_post || 0
              })
            }

            // 处理前置条件和质控逻辑中的减法条件
            this.ruleForm.qztj.forEach(item => {
              processConditionContent(item.condition_content)
            })
            this.ruleForm.rule.forEach(item => {
              processConditionContent(item.condition_content)
            })

            // department 处理,如果已经被[]包裹了就不用再加一层[],比如原本的参数已经是['基本信息']，就不用再加一层变成[['基本信息']]
            if (Array.isArray(this.ruleForm.department) && this.ruleForm.department.length > 0) {
              // this.ruleForm.department = this.ruleForm.department
            } else {
              this.ruleForm.department = [this.ruleForm.department]
            }

            // 继续原有的提交逻辑
            const params = {
              case_type: this.ruleForm.case_type,
              changjing: this.ruleForm.changjing,
              department: this.ruleForm.department,
              object: this.ruleForm.object ? [this.ruleForm.object] : [],
              type: this.ruleForm.type,
              is_not: this.ruleForm.is_not,
              description: this.ruleForm.description,
              score: this.ruleForm.score,
              error_level: this.ruleForm.error_level,
              status: this.ruleForm.status,
              rule_type: this.ruleForm.rule_type,
              rule: [...this.ruleForm.rule, ...this.ruleForm.qztj]
            }
            if (this.data.row.id) {
              params.id = this.data.row.id
            }
            add_rule(params).then(res => {
              this.data.bSwitch = false
              this.$emit('refresh')
              this.$message.success(res.m || '操作成功')
            })
          }
        } else {
          return false
        }
      })
    },
    openCategoryDialog(index, sIndex, source) {
      this.currentRuleIndex = index
      this.currentConditionIndex = sIndex
      this.currentSource = source

      // 判断来源类型
      if (source === 'qztj') {
        this.currentInputSource = 'qztj'
      } else if (source === 'rule') {
        this.currentInputSource = 'rule'
      } else if (source === 'sx_1' || source === 'sx_2') {
        // 需要判断是在前置条件还是质控逻辑中调用的
        const parentElement = document.activeElement.closest('.box-card')
        if (parentElement && parentElement.querySelector('.box-card_header span')?.textContent.includes('前置条件')) {
          this.currentInputSource = 'qztj'
        } else {
          this.currentInputSource = 'rule'
        }
        this.currentSource = source // 保持原有的 source 值
      }
      // 关键改动：清空搜索框并重置搜索结果
      this.filterSearch = ''
      this.searchObject = [...this.originalObjects]

      // 设置默认显示
      if (this.objects.length > 0) {
        this.activeCategory = this.objects[0].field_name
        this.currentSubCategories = this.objects[0].child || []
      }

      this.categoryDialogVisible = true
      this.selectedCategory = null
    },

    handleSearch() {
      const keyword = this.filterSearch.trim().toLowerCase()
      if (!keyword) {
        this.searchObject = [...this.originalObjects]
        if (this.searchObject.length > 0) {
          this.activeCategory = this.searchObject[0].field_name
        }
        return
      }
      this.filteredObjects = this.originalObjects.reduce((result, parent) => {
        const matchedChildren = parent.child ? parent.child.filter(child =>
          child.field_name.toLowerCase().includes(keyword)
        ) : []
        const parentMatches = parent.field_name.toLowerCase().includes(keyword)
        if (parentMatches || matchedChildren.length > 0) {
          const filteredChild = parentMatches
            ? parent.child
            : matchedChildren

          result.push({
            ...parent,
            child: filteredChild || []
          })
        }

        return result
      }, [])

      this.searchObject = [...this.filteredObjects]

      if (this.searchObject.length > 0) {
        this.activeCategory = this.searchObject[0].field_name
        this.activeSubCategory = ''
        this.handleCategoryClick(this.searchObject[0])
      } else {
        this.activeCategory = ''
        this.activeSubCategory = ''
      }
    },
    confirmCategory() {
      if (this.selectedCategory) {
        // 更新对应条件的类目
        this.ruleForm.rule[this.currentRuleIndex].condition_content[this.currentConditionIndex].category = this.selectedCategory
        // 可能还需要更新显示的文本
        const selectedItem = this.categories.find(item => item.value === this.selectedCategory)
        this.ruleForm.rule[this.currentRuleIndex].condition_content[this.currentConditionIndex].param2 =
          selectedItem ? selectedItem.label : ''
      }
      this.categoryDialogVisible = false
    },
    // 点击左侧类目
    handleCategoryClick(item) {
      // 更新当前选中的类目
      this.activeCategory = item.field_name

      // 健壮性处理：确保即使没有child属性也能正常工作
      if (item.child && Array.isArray(item.child)) {
        // 正常情况：使用子类目数据
        this.currentSubCategories = item.child
      } else if (item.field_name === '病案首页' || item.field_name === '通用') {
        // 特殊情况处理：为这两个特殊菜单项提供默认选项
        this.currentSubCategories = this.getDefaultSubCategories(item.field_name)
      } else {
        // 其他情况：提供空数组，避免错误
        this.currentSubCategories = []
        console.warn(`未找到 ${item.field_name} 的子类目数据`)
      }

      // 记录当前选中的类目对象，以便后续使用
      this.selectedCategory = item
    },
    // 获取默认子类目选项的方法
    getDefaultSubCategories(categoryName) {
      // 根据不同的类目名称返回不同的默认选项
      if (categoryName === '病案首页') {
        return [
          { field: 'med_record_page', field_name: '病案首页信息' },
          { field: 'med_record_basic', field_name: '病案基本信息' },
          { field: 'med_record_diagnose', field_name: '诊断信息' }
        ]
      } else if (categoryName === '通用') {
        return [
          { field: 'common_info', field_name: '通用信息' },
          { field: 'common_setting', field_name: '通用设置' }
        ]
      }
      return []
    },
    // 处理空子类目情况下的选择
    handleEmptySubCategorySelect() {
      // 创建一个虚拟的子类目项
      const defaultSubItem = {
        field: 'default_option',
        field_name: '默认选项'
      }

      // 使用相同的处理逻辑
      this.handleSubCategorySelect(defaultSubItem)
    },
    // 选择子类目的处理可能也需要增强健壮性
    handleSubCategorySelect(subItem) {
      if (this.currentRuleIndex !== null && this.currentConditionIndex !== null) {
        const parentObj = this.objects.find(obj => obj.field_name === this.activeCategory)
        const parentField = parentObj ? parentObj.field : ''

        if (this.currentSource === 'qztj') {
          const condition = this.ruleForm.qztj[this.currentRuleIndex].condition_content[this.currentConditionIndex]
          if (condition) {
            condition.param1 = [parentField, subItem.field]
            condition.field_name = subItem.field_name
            condition.categoryTypeDisabled = false
            // 检查子数据类型
            if (subItem.type === 2 && subItem.child && subItem.child.length > 0) {
              const dictData = subItem.child.find(item => item.type === 4)
              if (dictData) {
                condition.hasChildOptions = true
                condition.categoryType = '选择'
                condition.categoryTypeDisabled = true // 重置禁用标志
                // type=4 的情况，获取字典数据
                get_dict_by_type({
                  table: dictData.field,
                  field: dictData.field_name
                }).then(res => {
                  if (res?.p) {
                    // 转换数据格式
                    condition.childOptions = res.p.map(item => ({
                      field: item.dep_name,
                      field_name: item.dep_name
                    }))
                  }
                })
              } else if (subItem.child.some(item => item.type === 3)) {
                // type=3 的情况，直接使用 child 数据
                condition.hasChildOptions = true
                condition.childOptions = subItem.child
                condition.categoryType = '选择' // 设置为选择类型
                condition.categoryTypeDisabled = true // 添加禁用标志
              } else {
                // 没有子数据时重置标志
                condition.categoryTypeDisabled = false
                condition.hasChildOptions = false
              }
            }
          }
        } else if (this.currentSource === 'rule') {
          const condition = this.ruleForm.rule[this.currentRuleIndex].condition_content[this.currentConditionIndex]
          if (condition) {
            condition.param1 = [parentField, subItem.field]
            condition.field_name = subItem.field_name

            condition.categoryTypeDisabled = false
            if (subItem.type === 2 && subItem.child && subItem.child.length > 0) {
              const dictData = subItem.child.find(item => item.type === 4)
              if (dictData) {
                condition.hasChildOptions = true
                condition.categoryType = '选择'
                condition.categoryTypeDisabled = true // 重置禁用标志
                get_dict_by_type({
                  table: dictData.field,
                  field: dictData.field_name
                }).then(res => {
                  if (res?.p) {
                    condition.childOptions = res.p.map(item => ({
                      field: item.dep_name,
                      field_name: item.dep_name
                    }))
                  }
                })
              } else if (subItem.child.some(item => item.type === 3)) {
                // type=3 的情况，直接使用 child 数据
                condition.hasChildOptions = true
                condition.childOptions = subItem.child
              }
            }
          }
        } else if (this.currentSource === 'sx_1') {
          if (this.currentInputSource === 'qztj') {
            // 前置条件的时效
            const condition = this.ruleForm.qztj[this.currentRuleIndex].condition_content[this.currentConditionIndex]
            if (condition) {
              condition.sx_1 = [parentField, subItem.field]
              condition.field_name = subItem.field_name
            }
          } else {
            // 质控逻辑的时效
            const condition = this.ruleForm.rule[this.currentRuleIndex].condition_content[this.currentConditionIndex]
            if (condition) {
              condition.sx_1 = [parentField, subItem.field]
              condition.field_name = subItem.field_name
            }
          }
        } else if (this.currentSource === 'sx_2') {
          if (this.currentInputSource === 'qztj') {
            // 前置条件的时效
            const condition = this.ruleForm.qztj[this.currentRuleIndex].condition_content[this.currentConditionIndex]
            if (condition) {
              condition.sx_2 = [parentField, subItem.field]
              condition.field_name = subItem.field_name
              this.$set(condition, 'sx_3', '') // 确保 sx_3 是响应式的
            }
          } else {
            // 质控逻辑的时效
            const condition = this.ruleForm.rule[this.currentRuleIndex].condition_content[this.currentConditionIndex]
            if (condition) {
              condition.sx_2 = [parentField, subItem.field]
              condition.field_name = subItem.field_name
              this.$set(condition, 'sx_3', '') // 确保 sx_3 是响应式的
            }
          }
        } else if (this.currentSource === 'rule') {
          // 质控逻辑
          const condition = this.ruleForm.rule[this.currentRuleIndex].condition_content[this.currentConditionIndex]
          if (condition) {
            condition.param1 = [parentField, subItem.field]
            condition.field_name = subItem.field_name
            // 检查是否有 type=4 的子数据
            if (subItem.type === 2 && subItem.child && subItem.child.length > 0) {
              const dictData = subItem.child.find(item => item.type === 4)
              if (dictData) {
                condition.hasChildOptions = true
                // type=4 的情况，获取字典数据
                get_dict_by_type({
                  table: dictData.field,
                  field: dictData.field_name
                }).then(res => {
                  if (res?.p) {
                    // 转换数据格式
                    condition.childOptions = res.p.map(item => ({
                      field: item.dep_name,
                      field_name: item.dep_name
                    }))
                  }
                })
              } else if (subItem.child.some(item => item.type === 3)) {
                // type=3 的情况，直接使用 child 数据
                condition.hasChildOptions = true
                condition.childOptions = subItem.child
              }
            }
          }
        } else if (this.currentSource === 'custom_basis') {
          // 质控依据
          const basis = this.ruleForm.rule[this.currentRuleIndex].custom_basis[this.currentConditionIndex]
          if (basis) {
            basis.param1 = [parentField, subItem.field]
            basis.field_name = subItem.field_name
          }
        } else if (this.currentSource === 'custom_msg') {
          // 预警
          const msg = this.ruleForm.rule[this.currentRuleIndex].custom_msg
          if (msg) {
            msg.param1 = [parentField, subItem.field]
            msg.field_name = subItem.field_name
          }
        } else if (this.currentSource === 'subtract') {
          // 处理减法时的第二个类目选择
          if (this.currentInputSource === 'qztj') {
            const condition = this.ruleForm.qztj[this.currentRuleIndex].condition_content[this.currentConditionIndex]
            if (condition) {
              condition.subtract_param = [parentField, subItem.field]
            }
          } else if (this.currentInputSource === 'rule') {
            const condition = this.ruleForm.rule[this.currentRuleIndex].condition_content[this.currentConditionIndex]
            if (condition) {
              condition.subtract_param = [parentField, subItem.field]
            }
          }
        }
      }
      this.categoryDialogVisible = false
    },
    getDisplayName(param1) {
      if (!Array.isArray(param1)) return ''
      const [parentField, childField] = param1
      const parentObj = this.objects.find(obj => obj.field === parentField)
      if (!parentObj) return ''
      const childObj = parentObj.child.find(child => child.field === childField)
      return childObj ? childObj.field_name : ''
    },
    handleInput(value, ruleIndex, index, source = 'custom_basis') {
      if (value && value.endsWith('@')) {
        this.currentInputIndex = index
        this.currentRuleIndexForKnowledge = ruleIndex
        this.currentInputSource = source
        this.knowledgeDialogVisible = true
        this.getKnowledgeList()
      }
    },
    handleKnowledgeSelect(item) {
      if (this.currentInputIndex !== null && this.currentRuleIndexForKnowledge !== null) {
        if (this.currentInputSource === 'rule') {
          // 质控逻辑
          const condition = this.ruleForm.rule[this.currentRuleIndexForKnowledge].condition_content[this.currentInputIndex]
          if (condition) {
            // 直接替换整个值，不保留@
            condition.param2 = condition.param2.replace(/@.*$/, item.name)
          }
        } else if (this.currentInputSource === 'qztj') {
          const condition = this.ruleForm.qztj[this.currentRuleIndexForKnowledge].condition_content[this.currentInputIndex]
          if (condition) {
            condition.param2 = condition.param2.replace(/@.*$/, item.name)
          }
        } else {
          const basis = this.ruleForm.rule[this.currentRuleIndexForKnowledge].custom_basis[this.currentInputIndex]
          if (basis) {
            // 直接替换整个值，不保留@
            basis.input2 = basis.input2.replace(/@.*$/, item.name)
          }
        }
      }
      this.knowledgeDialogVisible = false
    },
    // 获取知识库列表
    async getKnowledgeList() {
      try {
        const res = await get_all_word_map({ status: 1 })
        if (res && res.p) {
          this.knowledgeList = Array.isArray(res.p) ? res.p : []
        }
      } catch (error) {
        console.error('获取知识库列表失败:', error)
        this.$message.error('获取知识库列表失败')
      }
    },
    // 添加质控逻辑
    onAddZKLJ() {
      // 检查是否已存在质控逻辑
      if (this.ruleForm.rule && this.ruleForm.rule.length > 0) {
        this.$message.warning('质控逻辑已存在')
        return
      }

      this.ruleForm.rule = [{
        is_pre_condition: 0,
        condition_type: 1,
        condition_relation: 2,
        detail_status: 1,
        condition_content: [
          {
            param1: '',
            param2: '',
            condition: '包含',
            categoryType: 'text',
            sx_1: '',
            sx_2: '',
            sx_3: '',
            subtract_param: '',
            subtract_condition: '',
            subtract_value: '',
            subtract_value_type: 'number',
            hasChildOptions: false,
            childOptions: []
          }
        ],
        custom_basis: [],
        pre_warning_time: '',
        custom_msg: {}
      }]
    },
    // 查看知识库详情
    handleKnowledgeView(row) {
      this.currentKnowledge = {
        ...row,
        keywords: row.keywords || '',
        BZMC: row.BZMC || ''
      }
      this.editKnowledge.name = row.name
      this.knowledgeDetailVisible = true
    },
    // 获取比较公式数据
    getCompareFormula() {
      getSelectFormula({ lx: 1 }).then(res => {
        const { p } = res
        console.log('compare formula:', p)
        this.compareFormula = p
      })
    },
    // 获取类型数据
    getCategories() {
      getSelectFormula({ lx: 1 }).then(res => {
        const { p } = res
        console.log('categories:', p)
        // 转换数据格式
        this.categories = p.map(item => ({
          label: item.formula,
          value: item.formula // 或者使用 id，取决于后端需要的格式
        }))
      })
    },
    // 获取完整的请求参数
    getFullParams(extraParams = {}) {
      return {
        id: this.currentKnowledge.id,
        name: this.currentKnowledge.name,
        BZMC: this.currentKnowledge.BZMC || '',
        keywords: this.formattedKeywords,
        ...extraParams
      }
    },
    // 显示输入框
    showInput(type) {
      this.inputVisible[type] = true
      this.$nextTick(() => {
        this.$refs[`${type}Input`].focus()
      })
    },
    // 处理输入确认
    handleInputConfirm(type) {
      if (type === 'bzmc') {
        if (this.editKnowledge.bzmc) {
          this.addBzmc()
        }
      } else if (type === 'keyword') {
        if (this.editKnowledge.keyword) {
          this.addKeyword()
        }
      }
      this.inputVisible[type] = false
    },
    // 添加用途
    addBzmc() {
      if (!this.editKnowledge.bzmc) return
      const newBzmc = this.currentKnowledge.BZMC
        ? `${this.currentKnowledge.BZMC},${this.editKnowledge.bzmc}`
        : this.editKnowledge.bzmc

      const params = this.getFullParams({ BZMC: newBzmc })
      edit_word_map(params).then(res => {
        this.$message.success('添加成功')
        this.editKnowledge.bzmc = ''
        this.getKnowledgeDetail(this.currentKnowledge.id)
      })
    },
    // 添加关键词
    addKeyword() {
      if (!this.editKnowledge.keyword) return
      const newKeywords = this.formattedKeywords
        ? `${this.formattedKeywords},${this.editKnowledge.keyword}`
        : this.editKnowledge.keyword

      const params = this.getFullParams({ keywords: newKeywords })
      edit_word_map(params).then(res => {
        this.$message.success('添加成功')
        this.editKnowledge.keyword = ''
        this.getKnowledgeDetail(this.currentKnowledge.id)
      })
    },
    // 处理用途的删除
    handleBzmcClose(index) {
      const bzmc = this.bzmcArray
      bzmc.splice(index, 1)

      const params = this.getFullParams({ BZMC: bzmc.join(',') })
      edit_word_map(params).then(res => {
        this.$message.success('删除成功')
        this.getKnowledgeDetail(this.currentKnowledge.id)
      })
    },
    // 处理关键词的删除
    handleKeywordClose(index) {
      const keywords = this.formattedKeywords.split(',')
      keywords.splice(index, 1)

      const params = this.getFullParams({ keywords: keywords.join(',') })
      edit_word_map(params).then(res => {
        this.$message.success('删除成功')
        this.getKnowledgeDetail(this.currentKnowledge.id)
      })
    },
    // 保存名称
    saveKnowledge() {
      const params = this.getFullParams({ name: this.editKnowledge.name })
      edit_word_map(params).then(res => {
        this.$message.success('保存成功')
        this.knowledgeDetailVisible = false
        // 刷新知识库列表
        this.getKnowledgeList()
      })
    },
    // 获取知识库详情
    getKnowledgeDetail(id) {
      get_all_word_map({ id }).then(res => {
        const { p } = res
        if (Array.isArray(p) && p.length > 0) {
          // 找到对应 id 的数据
          const detail = p.find(item => item.id === id)
          if (detail) {
            this.currentKnowledge = {
              ...detail,
              // 确保 keywords 和 BZMC 有值
              keywords: detail.keywords || '',
              BZMC: detail.BZMC || ''
            }
            // 同步更新编辑框的名称
            this.editKnowledge.name = detail.name
          }
        }
      })
    },
    // 获取下拉数据
    async getDropdownData() {
      try {
        // 获取病历类型
        const caseTypeRes = await get_rule_setting_other({ type: 1, issz: this.ruleType === 1 ? 0 : 1 })
        if (caseTypeRes?.p) {
          this.caseTypeOptions = caseTypeRes.p.map(item => ({
            value: item.name, // 使用 name 作为 value
            label: item.name // 使用 name 作为 label
          }))
        }

        // 获取质控类型
        const qualityTypeRes = await get_rule_setting_other({ type: 2, issz: this.ruleType === 1 ? 0 : 1 })
        if (qualityTypeRes?.p) {
          this.qualityTypeOptions = qualityTypeRes.p.map(item => ({
            value: item.name,
            label: item.name
          }))
        }

        // 获取质控场景
        const sceneRes = await get_rule_setting_other({ type: 3, issz: this.ruleType === 1 ? 0 : 1 })
        if (sceneRes?.p) {
          this.sceneOptions = sceneRes.p.map(item => ({
            value: item.name,
            label: item.name
          }))
        }
      } catch (error) {
        console.error('获取下拉数据失败:', error)
        this.$message.error('获取下拉数据失败')
      }
    },
    openChildOptionsDialog(index, sIndex) {
      const currentItem = this.ruleForm[this.currentInputSource][index].condition_content[sIndex]
      if (currentItem.param1 && Array.isArray(currentItem.param1)) {
        const [parentField, field] = currentItem.param1
        const category = this.objects.find(item => item.field === parentField)
        const subCategory = category?.child?.find(item => item.field === field)

        if (subCategory?.child && subCategory.child.length > 0) {
          this.currentChildOptions = subCategory.child
          this.currentRuleIndex = index
          this.currentConditionIndex = sIndex
          // this.currentInputSource = source
          this.childOptionsDialogVisible = true
        }
      }
    },
    handleChildOptionSelect(option) {
      const condition = this.ruleForm[this.currentInputSource][this.currentRuleIndex].condition_content[this.currentConditionIndex]
      condition.param2 = option.field_name
      this.childOptionsDialogVisible = false
    }
  }
}
</script>

<style lang="scss" scoped>
::v-deep .el-col-15 {
  .el-form-item__label {
    width: 160px !important;
  }
}

.box-card {
  margin-bottom: 16px;
}

::v-deep .el-card__header {
  padding: 0;

  .box-card_header {
    background: #F7F7F8;
    padding: 0 20px;
    line-height: 40px;
  }
}

::v-deep .el-card__body {
  padding: 20px 12px;
}

.text-right {
  text-align: right;
}

.ml40 {
  margin-left: 40px;
}

.mb12 {
  margin-bottom: 12px;
}

.span {
  padding-top: 40px;

  .text {
    color: #909399;
  }
}

.input-with-category {
  display: flex;
  align-items: stretch;
  gap: 0;

  ::v-deep .el-input__inner {
    border-top-right-radius: 0;
    border-bottom-right-radius: 0;
  }

  .category-select {
    ::v-deep .el-input__inner {
      border-top-left-radius: 0;
      border-bottom-left-radius: 0;
      margin-left: -1px;
      padding-left: 25px;
      /* 为图标留出空间 */
    }

    ::v-deep .el-input__prefix {
      left: 5px;
      color: #909399;
    }
  }
}

.category-content {
  height: 400px;
  display: flex;

  .menu-column {
    min-width: 200px !important;
    width: 33.33% !important;
  }

  // 右侧内容区域样式
  .sub-category-list {
    padding: 20px;
    height: 100%;
    overflow-y: auto;
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    align-content: flex-start;

    // 按钮样式
    .el-button {
      margin: 5px;
      min-width: 120px;
      height: 36px;
      display: flex;
      align-items: center;
      justify-content: center;

      &:hover {
        background-color: #ecf5ff;
        color: #409EFF;
      }
    }

    // 空状态提示样式
    .empty-sub-category {
      width: 100%;
      text-align: center;
      padding: 20px;
      color: #909399;

      p {
        margin-bottom: 15px;
      }

      .el-button {
        margin: 0 auto;
      }
    }
  }
}

.rule-condition {
  position: relative;
  display: flex;
  align-items: center;
  cursor: pointer; /* 添加手型光标 */

  .el-input {
    cursor: pointer;

    ::v-deep .el-input__inner {
      cursor: pointer;
    }
  }
}

.knowledge-content {
  max-height: 500px;
  overflow-y: auto;
}

.knowledge-detail {
  padding: 20px;

  .detail-item {
    margin-bottom: 20px;
    display: flex;
    align-items: center;

    .label {
      width: 70px;
      line-height: 32px;
    }

    .id-value {
      line-height: 32px;
      padding: 0 15px;
    }

    .el-input {
      flex: 1;
    }
  }

  .tags-input {
    flex: 1;
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 8px;

    .tag-button {
      margin: 0 8px 8px 0;
      padding: 5px 10px;
      height: 28px;
      line-height: 1;
      font-weight: normal;
      position: relative;

      .el-icon-close {
        margin-left: 5px;
        &:hover {
          color: #409eff;
        }
      }
    }

    .tag-input {
      width: 120px;
      margin-right: 8px;
    }

    .el-button {
      margin: 0 8px 8px 0;
      padding: 5px 10px;
      height: 28px;
      line-height: 1;
      font-weight: normal;

      &.is-plain {
        background: #ecf5ff;
        border-color: #d9ecff;
        color: #409eff;

        &:hover {
          background: #ecf5ff;
          border-color: #409eff;
          color: #409eff;
        }
      }
    }
  }
}

.button-group {
  display: flex;
  gap: 5px;
  margin-right: 10px;
}

.knowledge-detail .detail-item {
  margin-bottom: 20px;
}

.knowledge-detail .label {
  display: inline-block;
  width: 80px;
  vertical-align: top;
}

.input-with-tags {
  display: flex;
  flex-direction: column;
  width: calc(100% - 80px);
  margin-left: 80px;
  margin-top: -20px;
}

.tags-container {
  margin-bottom: 10px;
}

.tags-container .el-tag {
  margin-right: 10px;
  margin-bottom: 10px;
}

.input-container {
  width: 100%;
}

.rule-condition {
  .category-select {
    position: absolute;
    right: 0;
    top: 0;
  }
}

.child-options-list {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  padding: 10px;
}

/* 弹出菜单样式调整 */
.dialog-menu {
  position: fixed; /* 使用fixed定位 */
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%); /* 居中定位 */
  max-width: 90%; /* 响应式最大宽度 */
  width: 600px; /* 适当的固定宽度 */
  max-height: 90vh; /* 最大高度 */
  overflow-y: auto; /* 内容过多时可滚动 */
  z-index: 1000; /* 确保在最上层 */
  background-color: white;
  border-radius: 4px;
  box-shadow: 0 2px 12px 0 rgba(0, 0, 0, 0.1);
  padding: 20px;
}

/* 遮罩层样式 */
.dialog-overlay {
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background-color: rgba(0, 0, 0, 0.5);
  z-index: 999;
}

/* 1. 确保弹窗容器样式正确 */
.dialog-container {
  position: fixed; /* 使用fixed定位相对于viewport */
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  display: flex;
  justify-content: center;
  align-items: center;
  z-index: 9999; /* 确保弹窗在最上层 */
  background-color: rgba(0, 0, 0, 0.5); /* 半透明背景 */
}

/* 2. 弹窗主体样式 */
.dialog-content {
  position: relative;
  background: white;
  border-radius: 4px;
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
  max-width: 90%;
  max-height: 90vh;
  width: auto;
  overflow: auto;
  display: flex;
  flex-direction: row; /* 水平排列左侧菜单和右侧内容 */
}

/* 3. 左侧菜单容器 */
.dialog-sidebar {
  width: 200px; /* 固定宽度 */
  border-right: 1px solid #eee;
  overflow-y: auto; /* 允许垂直滚动 */
  background: #f8f8f8;
  padding: 15px 0;
}

/* 4. 右侧内容区域 */
.dialog-main {
  flex: 1;
  padding: 20px;
  overflow-y: auto;
}

/* 5. 确保左侧菜单项样式正确 */
.sidebar-menu-item {
  padding: 10px 15px;
  cursor: pointer;
  transition: background-color 0.2s;
}

.sidebar-menu-item:hover,
.sidebar-menu-item.active {
  background-color: #e8f4ff;
  color: #1890ff;
}

/* 6. 关闭按钮样式 */
.dialog-close {
  position: absolute;
  top: 15px;
  right: 15px;
  font-size: 18px;
  cursor: pointer;
  color: #999;
  z-index: 1;
}

.dialog-close:hover {
  color: #333;
}

/* 新增选择弹窗样式 */
.category-dialog-style {
  position: fixed; /* 使用fixed定位 */
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  display: flex;
  justify-content: center;
  align-items: center;
  z-index: 1000; /* 确保弹窗在最上层 */
  background-color: rgba(0, 0, 0, 0.5); /* 半透明背景 */
}

/* 修改知识库弹窗样式 */
.knowledge-dialog {
  position: fixed; /* 使用fixed定位 */
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  display: flex;
  justify-content: center;
  align-items: center;
  z-index: 1000; /* 确保弹窗在最上层 */
  background-color: rgba(0, 0, 0, 0.5); /* 半透明背景 */
}

/* 添加知识库详情弹窗样式 */
.knowledge-detail-dialog {
  position: fixed; /* 使用fixed定位 */
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  display: flex;
  justify-content: center;
  align-items: center;
  z-index: 1000; /* 确保弹窗在最上层 */
  background-color: rgba(0, 0, 0, 0.5); /* 半透明背景 */
}

/* 添加新的对话框样式 */
.child-options-dialog {
  position: fixed; /* 使用fixed定位 */
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  display: flex;
  justify-content: center;
  align-items: center;
  z-index: 1000; /* 确保弹窗在最上层 */
  background-color: rgba(0, 0, 0, 0.5); /* 半透明背景 */
}

/* 确保弹窗层级和定位正确 */
::v-deep .category-dialog {
  /* 使用更高的z-index确保弹窗在最上层 */
  z-index: 3000 !important;
}

::v-deep .knowledge-dialog {
  z-index: 3000 !important;
}

::v-deep .el-dialog__wrapper {
  overflow: hidden;
  display: flex;
  justify-content: center;
  align-items: center;
}

/* 确保弹窗在小屏幕上也能正确显示 */
@media (max-width: 768px) {
  ::v-deep .el-dialog {
    width: 95% !important;
    margin-top: 10vh !important;
  }

  ::v-deep .category-dialog .sub-category-list {
    max-height: 300px;
    overflow-y: auto;
  }
}

/* 确保菜单容器有足够的z-index和正确的定位 */
.menu-container {
  height: 100%;
  position: relative;
  z-index: 2001; /* 确保菜单在弹窗内容之上 */
}

.category-menu {
  border-right: 1px solid #e6e6e6;
  height: 400px;
  overflow-y: auto;
}

.category-content-area {
  padding: 10px;
  height: 400px;
  overflow-y: auto;
}

/* 确保弹窗内部的交互元素可点击 */
::v-deep .el-dialog__body {
  padding: 10px;
  overflow: visible; /* 允许内容溢出 */
}

/* 确保菜单项可以正常点击 */
::v-deep .el-menu-item {
  cursor: pointer;
  user-select: none;
  height: 40px;
  line-height: 40px;

  &:hover, &:focus {
    background-color: #ecf5ff;
    color: #409EFF;
  }

  &.is-active {
    color: #409EFF;
    background-color: #ecf5ff;
    border-right: 2px solid #409EFF;
  }
}

// 确保弹窗内容区域正确显示
.el-dialog {
  .category-content {
    .el-row {
      margin: 0;
      display: flex;
      width: 100%;

      // 右侧列样式
      .el-col:last-child {
        flex: 1;
        overflow: hidden;
        padding: 0 20px;
      }
    }
  }
}

// 响应式布局调整
@media (max-width: 768px) {
  .category-content {
    .sub-category-list {
      padding: 10px;

      .el-button {
        min-width: calc(50% - 20px);
        margin: 5px;
      }
    }
  }
}

/* 添加开关样式 */
.post-switch {
  margin-top: 5px;

  ::v-deep .el-switch__label {
    font-size: 12px;
    color: #606266;
  }

  ::v-deep .el-switch.is-checked .el-switch__core {
    border-color: #409EFF;
    background-color: #409EFF;
  }
}

/* 优化按钮组和开关的间距 */
.button-group {
  display: flex;
  gap: 5px;
  margin-right: 10px;
}

/* 优化开关样式 */
.push-switch-col {
  display: flex;
  align-items: center;
  justify-content: flex-start;
  padding-left: 5px;

  .compact-switch {
    transform: scale(0.8); /* 缩小开关尺寸 */
    margin-right: 4px;
  }

  .push-label {
    font-size: 12px;
    color: #606266;
    white-space: nowrap;
  }
}

/* 确保按钮组样式一致 */
.button-group {
  display: flex;
  gap: 5px;
  justify-content: flex-end;
}

/* 对质控逻辑部分应用相同样式 */
.box-card {
  .push-switch-col {
    margin-top: 0; /* 确保与其他行元素对齐 */
  }
}

/* 优化按钮操作列 */
.button-actions-col {
  padding-right: 10px; /* 增加右侧间距 */
}

.button-actions {
  display: flex;
  justify-content: flex-start; /* 左对齐 */
  gap: 8px; /* 按钮之间的间距 */

  .action-button {
    padding: 6px 8px; /* 调整按钮内边距 */
    min-width: 28px; /* 确保最小宽度 */
    height: 28px; /* 固定高度 */
    flex-shrink: 0; /* 防止按钮被压缩 */
  }
}

/* 优化推送开关样式 */
.push-switch-col {
  padding-left: 5px; /* 增加左侧间距 */
}

.switch-container {
  display: flex;
  align-items: center;
  white-space: nowrap; /* 防止文字换行 */

  .compact-switch {
    transform: scale(0.8); /* 缩小开关尺寸 */
    margin-right: 5px;
  }

  .push-label {
    font-size: 12px;
    color: #606266;
  }
}

/* 确保表单行对齐 */
.mb12 {
  margin-bottom: 12px;
  display: flex;
  align-items: center;
}

/* 防止大屏幕上按钮被挤压 */
@media (min-width: 1200px) {
  .button-actions {
    min-width: 80px; /* 在大屏上确保最小宽度 */
  }
}

/* 针对小屏幕优化 */
@media (max-width: 768px) {
  .button-actions-col {
    padding-right: 5px;
  }

  .button-actions {
    gap: 4px;
  }
}

/* 弹窗整体样式 */
::v-deep .rule-dialog {
  display: flex;
  flex-direction: column;

  .el-dialog {
    margin-top: 5vh !important;
    margin-bottom: 5vh !important;
    max-height: 90vh; /* 设置最大高度 */
    display: flex;
    flex-direction: column;

    /* 弹窗头部样式 */
    .el-dialog__header {
      padding: 15px 20px;
      border-bottom: 1px solid #e4e4e4;
    }

    /* 弹窗主体内容区域 */
    .el-dialog__body {
      flex: 1; /* 占用剩余空间 */
      overflow-y: auto; /* 启用垂直滚动 */
      padding: 20px;

      /* 设置滚动条样式 */
      &::-webkit-scrollbar {
        width: 6px;
      }

      &::-webkit-scrollbar-thumb {
        background: #c0c4cc;
        border-radius: 3px;
      }

      &::-webkit-scrollbar-track {
        background: #f5f7fa;
      }
    }

    /* 弹窗页脚样式 */
    .el-dialog__footer {
      padding: 15px 20px;
      border-top: 1px solid #e4e4e4;
      background: #fff;
    }
  }
}

/* 内容包装器样式 */
.dialog-content-wrapper {
  min-height: 200px; /* 设置最小高度 */
}

/* 表单样式优化 */
.demo-ruleForm {
  .el-form-item {
    margin-bottom: 18px;
  }
}

/* 确保在小屏幕上也能正常显示 */
@media screen and (max-height: 768px) {
  ::v-deep .rule-dialog .el-dialog {
    margin-top: 2vh !important;
    margin-bottom: 2vh !important;
    max-height: 96vh;
  }
}
</style>

<!-- 替换现有的全局样式为简化版本 -->
<style lang="scss">
/* 全局样式，只保留必要的z-index覆盖 */

/* 弹窗父容器样式 */
.el-dialog__wrapper {
  z-index: 2000 !important; /* 使用适当的z-index，不要太高 */
}

/* 遮罩层样式 */
.v-modal {
  z-index: 1999 !important; /* 比弹窗低1级 */
}

/* 各弹窗自定义样式 */
.global-category-dialog,
.global-knowledge-dialog,
.global-knowledge-detail,
.global-child-options-dialog {
  /* 只设置自定义样式，不设置定位和z-index */
  .el-dialog {
    margin: 15vh auto !important; /* 从顶部留出适当空间 */
  }
}

/* 小屏幕适配 */
@media (max-width: 768px) {
  .el-dialog {
    width: 95% !important;
    margin-top: 10vh !important;
  }
}
</style>
