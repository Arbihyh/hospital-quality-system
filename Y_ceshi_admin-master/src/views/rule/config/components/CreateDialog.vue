<template>
  <div>
    <el-dialog
      v-el-drag-dialog
      :title="titleStr"
      :visible.sync="data.bSwitch"
      width="1200px"
      top="5vh"
    >
      <el-form
        ref="ruleForm"
        :disabled="titleStr === '详情'"
        :model="ruleForm"
        :rules="rules"
        label-width="80px"
        class="demo-ruleForm"
      >
        <el-row :gutter="16">
          <el-col :span="8">
            <el-form-item label="病历类型" prop="case_type">
              <el-select v-model="ruleForm.case_type" placeholder="请选择" style="width: 100%;">
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
            <el-form-item label="质控项目" prop="object">
              <el-cascader
                v-model="ruleForm.object"
                :options="objects"
                :props="{
                  expandTrigger: 'hover',
                  value: 'field_name',
                  label: 'field_name',
                  children: 'child',
                  checkStrictly: true // 添加此属性允许选择任意一级
                }"
                clearable
                filterable
                :show-all-levels="false"
                placeholder="请选择"
                style="width: 100%;"
              />
            </el-form-item>
          </el-col>
          <el-col :span="8">
            <el-form-item label="质控科室">
              <el-select
                v-model="ruleForm.department"
                multiple
                collapse-tags
                placeholder="请选择"
                style="width: 100%;"
                clearable
                filterable
              >
                <el-option
                  v-for="item in departments"
                  :key="item.id"
                  :label="item.dep_name"
                  :value="item.dep_id"
                />
              </el-select>
            </el-form-item>
          </el-col>
          <el-col :span="8">
            <el-form-item label="单项否决">
              <el-select
                v-model="ruleForm.is_not"
                filterable
                clearable
                placeholder="请选择"
                style="width: 100%;"
              >
                <el-option label="是" :value="1" />
                <el-option label="否" :value="0" />
              </el-select>
            </el-form-item>
          </el-col>
          <el-col :span="8">
            <el-form-item label="质控状态" prop="status">
              <el-select
                v-model="ruleForm.status"
                filterable
                clearable
                placeholder="请选择"
                style="width: 100%;"
              >
                <el-option label="开启" :value="1" />
                <el-option label="禁用" :value="2" />
              </el-select>
            </el-form-item>
          </el-col>
          <el-col :span="8">
            <el-form-item label="质控类型" prop="type">
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
                <el-option
                  v-for="item in sceneOptions"
                  :key="item.value"
                  :label="item.label"
                  :value="item.value"
                />
              </el-select>
            </el-form-item>
          </el-col>
          <el-col :span="8">
            <el-form-item label="风险等级" prop="error_level">
              <el-select
                v-model="ruleForm.error_level"
                filterable
                clearable
                placeholder="请选择"
                style="width: 100%;"
              >
                <el-option label="必改" :value="1" />
                <el-option label="建议" :value="2" />
              </el-select>
            </el-form-item>
          </el-col>
          <el-col :span="8">
            <el-form-item label="扣分分值" prop="score">
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
              <el-input
                v-model="ruleForm.description"
                type="textarea"
                :autosize="{ minRows: 3 }"
                placeholder="请输入"
              />
            </el-form-item>
          </el-col>
        </el-row>
        <el-row :gutter="16">
          <el-col :span="24">
            <el-form-item label="质控规则">
              <el-col :span="5">
                <el-form-item label="规则模板" prop="rule_type">
                  <el-select
                    v-model="ruleForm.rule_type"
                    filterable
                    placeholder="请选择"
                    style="width: 100%;"
                  >
                    <el-option label="普通规则" value="普通规则" />
                    <el-option label="门诊规则" value="门诊规则" />
                    <!-- <el-option label="时效性规则" value="时效性规则" />
                    <el-option label="完整性规则" value="完整性规则" />
                    <el-option label="知识库规则" value="知识库规则" />
                    <el-option label="大模型规则" value="大模型规则" />-->
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
                <el-card
                  v-for="(item, index) of ruleForm.qztj"
                  :key="'qztj' + index"
                  class="box-card"
                  shadow="never"
                >
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
                        <div
                          class="rule-condition"
                          @click="openCategoryDialog(index, sIndex, 'qztj', sItem.param1)"
                        >
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
                                style="width: 70px; position: absolute; right: 0; top: 0; opacity: 0;"
                                @click="openCategoryDialog(index, sIndex, 'sx_1', sItem.sx_1)"
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
                                style="width: 70px; position: absolute; right: 0; top: 0; opacity: 0;"
                                @click="openCategoryDialog(index, sIndex, 'sx_2', sItem.sx_2)"
                              />
                            </div>
                          </el-col>
                          <el-col v-if="sItem.sx_2" :span="0.5">
                            <div class="text-right">+</div>
                          </el-col>
                          <el-col v-if="sItem.sx_2" :span="5">
                            <el-input
                              v-model="sItem.sx_3"
                              clearable
                              placeholder="请输入"
                              style="width: 100%;"
                            />
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
                      <el-col
                        v-else-if="['等于', '不等于', '包含', '不包含', '大于', '小于', '大于等于', '小于等于'].includes(sItem.condition)"
                        :span="6"
                      >
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
                              placeholder="请输入1111"
                              type="textarea"
                              autosize
                              size="small"
                              style="width: calc(100% - 90px)"
                              @input="(value) => handleInput(value, index, sIndex, 'qztj')"
                              @blur="validateParam2(sItem.param2)"
                            />-->
                            <el-form-item
                              :prop="`qztj[${index}].condition_content[${sIndex}].param2`"
                              :rules="[
                                { required: true, message: '请输入', trigger: 'blur' }
                              ]"
                              style="width: calc(100% - 90px)"
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
                      <el-col v-if="['包含', '不包含'].includes(sItem.condition)" :span="1">
                        <div class="text-center" style="line-height: 36px;">删除</div>
                      </el-col>
                      <el-col v-if="['包含', '不包含'].includes(sItem.condition)" :span="4">
                        <!-- 第二参数选择 -->
                        <div class="rule-condition">
                          <el-input
                            v-model="sItem.delete_field"
                            placeholder="请输入"
                            type="textarea"
                            autosize
                            size="small"
                            style="width: 100%;"
                          />
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
                            @click="openCategoryDialog(index, sIndex, 'subtract', sItem.subtract_param)"
                          />
                          <el-button
                            size="small"
                            style="width: 70px"
                            class="category-select"
                            @click="openCategoryDialog(index, sIndex, 'subtract', sItem.subtract_param)"
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
                      <el-col v-else-if="sItem.condition === '重复率'" :span="9">
                        <el-row :gutter="8">
                          <el-col :span="10">
                            <!-- 第二参数选择 -->
                            <div class="rule-condition">
                              <el-input
                                :value="getDisplayName(sItem.param2_object)"
                                placeholder="请选择比较对象"
                                size="small"
                                readonly
                                style="width: 100%;"
                              />
                              <el-button
                                size="small"
                                style="width: 100%; height: 100%; position: absolute; right: 0; top: 0; opacity: 0;"
                                @click="openCategoryDialog(index, sIndex, 'param2_object', sItem.param2_object)"
                              />
                            </div>
                          </el-col>
                          <el-col :span="2">
                            <div class="text-center" style="line-height: 36px;">≥</div>
                          </el-col>
                          <el-col :span="8">
                            <!-- 重复率值输入 -->
                            <el-input-number
                              v-model="sItem.subtract_value"
                              :precision="1"
                              :step="0.1"
                              :max="100"
                              :min="0"
                              controls-position="right"
                              placeholder="重复率"
                              style="width: 100%;"
                            />
                          </el-col>
                          <el-col :span="2">
                            <div class="text-left" style="line-height: 36px; padding-left: 5px;">%</div>
                          </el-col>
                        </el-row>
                      </el-col>
                      <el-col :span="2">
                        <div class="button-group">
                          <el-button
                            type="primary"
                            plain
                            icon="el-icon-plus"
                            size="mini"
                            @click="onAddTJ(index, sIndex)"
                          />
                          <el-button
                            v-if="item.condition_content.length !== 1"
                            size="mini"
                            type="primary"
                            plain
                            icon="el-icon-minus"
                            @click="onDeleteTJ(index, sIndex)"
                          />
                        </div>
                      </el-col>
                    </el-row>
                  </div>
                  <div class="span">
                    <span>规则之间的逻辑</span>
                    <el-radio v-model="item.condition_relation" :label="1" class="ml40">且（满足所有条件）</el-radio>
                    <el-radio v-model="item.condition_relation" :label="2">或（满足任意一条件）</el-radio>
                  </div>
                </el-card>
              </el-col>

              <!-- 质控逻辑 -->
              <el-col :span="24">
                <el-divider />
                <el-card
                  v-for="(item, index) of ruleForm.rule"
                  :key="'rule' + index"
                  class="box-card"
                  shadow="never"
                >
                  <div slot="header" class="clearfix box-card_header span">
                    <span class="text">质控逻辑{{ index + 1 }}</span>
                    <span class="ml40">
                      <span>流向</span>
                      <el-radio v-model="item.detail_status" :label="1" class="ml40">正确</el-radio>
                      <el-radio v-model="item.detail_status" :label="2">错误</el-radio>
                    </span>
                    <i
                      class="el-icon-delete"
                      style="float: right; margin-top: 13px; cursor: pointer;"
                      @click="onDeleteZKLJ(index)"
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
                        <div class="text-right">逻辑{{ sIndex + 1 }}</div>
                      </el-col>
                      <el-col
                        v-if="!(sItem.condition === '病程记录关联' || sItem.condition === '时效')"
                        :span="5"
                      >
                        <div
                          class="rule-condition"
                          @click="openCategoryDialog(index, sIndex, 'rule', sItem.param1)"
                        >
                          <el-input
                            :value="getDisplayName(sItem.param1)"
                            placeholder="请选择"
                            size="small"
                            readonly
                            style="width: 100%"
                          />
                        </div>
                      </el-col>
                      <el-col
                        v-if="!(sItem.condition === '病程记录关联' || sItem.condition === '时效')"
                        :span="4"
                      >
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
                      <el-col v-if="sItem.condition === '时效'" :span="22" class="progress-note">
                        <el-row :gutter="24">
                          <el-col v-if="sItem.condition === '时效'" :span="5">
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
                          <el-col :span="5">
                            <div
                              class="rule-condition"
                              @click="openCategoryDialog(index, sIndex, 'sx_param1', sItem.sx_param1)"
                            >
                              <el-input
                                :value="getDisplayName(sItem.sx_param1)"
                                placeholder="请选择"
                                size="small"
                                readonly
                                style="width: 100%"
                              />
                            </div>
                          </el-col>
                          <el-col :span="2">
                            <div class="text-center">等于</div>
                          </el-col>
                          <el-col :span="5">
                            <div class="rule-condition">
                              <el-input
                                v-model="sItem.sx_param2"
                                placeholder="请输入"
                                size="small"
                                style="width: 100%;"
                                @input="(value) => handleInput(value, index, sIndex, 'rule_other', 'sx_param2')"
                              />
                            </div>
                          </el-col>
                          <el-col :span="5">
                            <div
                              class="rule-condition"
                              @click="openCategoryDialog(index, sIndex, 'sx_param3', sItem.sx_param3)"
                            >
                              <el-input
                                :value="getDisplayName(sItem.sx_param3)"
                                placeholder="请选择"
                                size="small"
                                readonly
                                style="width: 100%"
                              />
                            </div>
                          </el-col>
                          <el-col :span="2">
                            <div class="text-center">在</div>
                          </el-col>
                          <el-col :span="5">
                            <div
                              class="rule-condition"
                              @click="openCategoryDialog(index, sIndex, 'sx_param4_index', sItem.sx_param4_index)"
                            >
                              <el-input
                                :value="getDisplayName(sItem.sx_param4_index)"
                                placeholder="请选择"
                                size="small"
                                readonly
                                style="width: 100%"
                              />
                            </div>
                          </el-col>
                          <el-col :span="2">
                            <div class="text-center">包含</div>
                          </el-col>
                          <el-col :span="4">
                            <div class="rule-condition">
                              <el-input
                                v-model="sItem.sx_param4_condition1"
                                placeholder="请输入"
                                size="small"
                                style="width: 100%;"
                                @input="(value) => handleInput(value, index, sIndex, 'rule_other', 'sx_param4_condition1')"
                              />
                            </div>
                          </el-col>
                          <el-col :span="2">
                            <div class="text-center">不包含</div>
                          </el-col>
                          <el-col :span="4">
                            <div class="rule-condition">
                              <el-input
                                v-model="sItem.sx_param4_condition2"
                                placeholder="请输入"
                                size="small"
                                style="width: 100%;"
                                @input="(value) => handleInput(value, index, sIndex, 'rule_other', 'sx_param4_condition2')"
                              />
                            </div>
                          </el-col>
                          <el-col :span="5">
                            <div
                              class="rule-condition"
                              @click="openCategoryDialog(index, sIndex, 'sx_param4_kssj', sItem.sx_param4_kssj)"
                            >
                              <el-input
                                :value="getDisplayName(sItem.sx_param4_kssj)"
                                placeholder="请选择"
                                size="small"
                                readonly
                                style="width: 100%"
                              />
                            </div>
                          </el-col>
                          <el-col :span="2">
                            <div class="text-center">至</div>
                          </el-col>
                          <el-col :span="5">
                            <div
                              class="rule-condition"
                              @click="openCategoryDialog(index, sIndex, 'sx_param4_jssj', sItem.sx_param4_jssj)"
                            >
                              <el-input
                                :value="getDisplayName(sItem.sx_param4_jssj)"
                                placeholder="请选择"
                                size="small"
                                readonly
                                style="width: 100%"
                              />
                            </div>
                          </el-col>
                          <el-col :span="1">
                            <div class="text-center">+</div>
                          </el-col>
                          <el-col :span="4">
                            <div class="rule-condition">
                              <el-input
                                v-model="sItem.sx_param4_sjjg"
                                placeholder="请输入"
                                size="small"
                                style="width: 100%;"
                              />
                            </div>
                          </el-col>
                          <el-col :span="2">
                            <div class="text-center">小时内</div>
                          </el-col>
                          <el-col v-if="sItem.condition === '时效'" :span="2">
                            <div class="button-group">
                              <el-button
                                type="primary"
                                plain
                                icon="el-icon-plus"
                                size="mini"
                                @click="onAddGZ(index, sIndex)"
                              />
                              <el-button
                                v-if="item.condition_content.length !== 1"
                                size="mini"
                                type="primary"
                                plain
                                icon="el-icon-minus"
                                @click="onDeleteGZ(index, sIndex)"
                              />
                            </div>
                          </el-col>
                          <!-- <el-col :span="6">
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
                          <el-col :span="3">
                            <div class="text-right">小时</div>
                          </el-col>-->
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
                      <el-col
                        v-else-if="['等于', '不等于', '包含', '不包含', '大于', '小于', '大于等于', '小于等于'].includes(sItem.condition)"
                        :span="6"
                      >
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
                              placeholder="请输入2222"
                              type="textarea"
                              autosize
                              size="small"
                              style="width: calc(100% - 90px)"
                              @input="(value) => sItem.categoryType === '字典' && handleInput(value, index, sIndex, 'rule')"
                              @blur="validateParam2(sItem.param2)"
                            />-->
                            <el-form-item
                              :prop="`rule[${index}].condition_content[${sIndex}].param2`"
                              :rules="[
                                { required: true, message: '请输入', trigger: 'blur' }
                              ]"
                              style="width: calc(100% - 90px)"
                            >
                              <el-input
                                v-model="sItem.param2"
                                placeholder="请输入"
                                type="textarea"
                                autosize
                                size="small"
                                @input="(value) => sItem.categoryType === '字典' && handleInput(value, index, sIndex, 'rule')"
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
                      <el-col v-if="['包含', '不包含'].includes(sItem.condition)" :span="1">
                        <div class="text-center" style="line-height: 36px;">删除</div>
                      </el-col>
                      <el-col v-if="['包含', '不包含'].includes(sItem.condition)" :span="4">
                        <!-- 第二参数选择 -->
                        <div class="rule-condition">
                          <el-input
                            v-model="sItem.delete_field"
                            placeholder="请输入"
                            type="textarea"
                            autosize
                            size="small"
                            style="width: 100%;"
                          />
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
                            @click="openCategoryDialog(index, sIndex, 'subtract', sItem.subtract_param)"
                          />
                          <el-button
                            size="small"
                            style="width: 90px"
                            class="category-select"
                            @click="openCategoryDialog(index, sIndex, 'subtract', sItem.subtract_param)"
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
                      <el-col v-else-if="sItem.condition === '重复率'" :span="9">
                        <el-row :gutter="8">
                          <el-col :span="10">
                            <!-- 第二参数选择 -->
                            <div class="rule-condition">
                              <el-input
                                :value="getDisplayName(sItem.param2_object)"
                                placeholder="请选择比较对象"
                                size="small"
                                readonly
                                style="width: 100%;"
                              />
                              <el-button
                                size="small"
                                style="width: 100%; height: 100%;  position: absolute; right: 0; top: 0; opacity: 0;"
                                @click="openCategoryDialog(index, sIndex, 'param2_object', sItem.param2_object)"
                              />
                            </div>
                          </el-col>
                          <el-col :span="2">
                            <div class="text-center" style="line-height: 36px;">≥</div>
                          </el-col>
                          <el-col :span="8">
                            <!-- 重复率值输入 -->
                            <el-input-number
                              v-model="sItem.subtract_value"
                              :precision="1"
                              :step="0.1"
                              :max="100"
                              :min="0"
                              controls-position="right"
                              placeholder="重复率"
                              style="width: 100%;"
                            />
                          </el-col>
                          <el-col :span="2">
                            <div class="text-left" style="line-height: 36px; padding-left: 5px;">%</div>
                          </el-col>
                        </el-row>
                      </el-col>
                      <el-col
                        v-else-if="sItem.condition === '病程记录关联'"
                        :span="22"
                        class="progress-note"
                      >
                        <el-row :gutter="24">
                          <!-- 参数1 -->
                          <el-col v-if="sItem.condition === '病程记录关联'" :span="5">
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
                          <!-- 参数2 -->
                          <el-col :span="4">
                            <div class="rule-condition">
                              <el-input
                                v-model="sItem.pn_param1_BLBH"
                                placeholder="请输入"
                                size="small"
                                style="width: 100%;"
                                @input="(value) => handleInput(value, index, sIndex, 'rule_other', 'pn_param1_BLBH')"
                              />
                            </div>
                          </el-col>
                          <!-- 参数3 -->
                          <el-col :span="5">
                            <div
                              class="rule-condition"
                              @click="openCategoryDialog(index, sIndex, 'pn_param1', sItem.pn_param1)"
                            >
                              <el-input
                                :value="getDisplayName(sItem.pn_param1)"
                                placeholder="请选择"
                                size="small"
                                readonly
                                style="width: 100%"
                              />
                            </div>
                          </el-col>
                          <!-- 参数4 -->
                          <el-col :span="4">
                            <div class="rule-condition">
                              <el-input
                                v-model="sItem.pn_param2"
                                placeholder="请输入"
                                size="small"
                                style="width: 100%;"
                                @input="(value) => handleInput(value, index, sIndex, 'rule_other', 'pn_param2')"
                              />
                            </div>
                          </el-col>
                          <!-- 参数5 -->
                          <el-col :span="5">
                            <div
                              class="rule-condition"
                              @click="openCategoryDialog(index, sIndex, 'pn_param3', sItem.pn_param3)"
                            >
                              <el-input
                                :value="getDisplayName(sItem.pn_param3)"
                                placeholder="请选择"
                                size="small"
                                readonly
                                style="width: 100%"
                              />
                            </div>
                          </el-col>
                          <el-col :span="1">
                            <div class="text-center" style="line-height: 36px;">在</div>
                          </el-col>
                          <!-- 参数6 -->
                          <el-col :span="5">
                            <div
                              class="rule-condition"
                              @click="openCategoryDialog(index, sIndex, 'pn_param4_index', sItem.pn_param4_index)"
                            >
                              <el-input
                                :value="getDisplayName(sItem.pn_param4_index)"
                                placeholder="请选择"
                                size="small"
                                readonly
                                style="width: 100%"
                              />
                            </div>
                          </el-col>
                          <el-col :span="2">
                            <div class="text-center" style="line-height: 36px;">包含</div>
                          </el-col>
                          <!-- 参数7 -->
                          <el-col :span="4">
                            <div class="rule-condition">
                              <el-input
                                v-model="sItem.pn_param4_condition1"
                                placeholder="请输入"
                                size="small"
                                style="width: 100%;"
                                @input="(value) => handleInput(value, index, sIndex, 'rule_other', 'pn_param4_condition1')"
                              />
                            </div>
                          </el-col>
                          <el-col :span="2">
                            <div class="text-center" style="line-height: 36px;">不包含</div>
                          </el-col>
                          <!-- 参数8 -->
                          <el-col :span="4">
                            <div class="rule-condition">
                              <el-input
                                v-model="sItem.pn_param4_condition2"
                                placeholder="请输入"
                                size="small"
                                style="width: 100%;"
                                @input="(value) => handleInput(value, index, sIndex, 'rule_other', 'pn_param4_condition2')"
                              />
                            </div>
                          </el-col>
                          <!-- 参数9 -->
                          <el-col :span="5">
                            <div
                              class="rule-condition"
                              @click="openCategoryDialog(index, sIndex, 'pn_param4_kssj', sItem.pn_param4_kssj)"
                            >
                              <el-input
                                :value="getDisplayName(sItem.pn_param4_kssj)"
                                placeholder="请选择"
                                size="small"
                                readonly
                                style="width: 100%"
                              />
                            </div>
                          </el-col>
                          <el-col :span="1">
                            <div class="text-center" style="line-height: 36px;">至</div>
                          </el-col>
                          <!-- 参数10 -->
                          <el-col :span="5">
                            <div
                              class="rule-condition"
                              @click="openCategoryDialog(index, sIndex, 'pn_param4_jssj', sItem.pn_param4_jssj)"
                            >
                              <el-input
                                :value="getDisplayName(sItem.pn_param4_jssj)"
                                placeholder="请选择"
                                size="small"
                                readonly
                                style="width: 100%"
                              />
                            </div>
                          </el-col>
                          <el-col :span="1">
                            <div class="text-center" style="line-height: 36px;">+</div>
                          </el-col>
                          <!-- 参数 -->
                          <el-col :span="4">
                            <div class="rule-condition">
                              <el-input
                                v-model="sItem.pn_param4_sjjg"
                                placeholder="请输入"
                                size="small"
                                style="width: 100%;"
                              />
                            </div>
                          </el-col>
                          <el-col :span="2">
                            <div class="text-center" style="line-height: 36px;">小时内</div>
                          </el-col>
                        </el-row>
                        <el-row :gutter="24">
                          <!-- 参数12 -->
                          <el-col :span="5">
                            <el-select
                              v-model="sItem.pn_param5"
                              filterable
                              clearable
                              placeholder="请选择"
                              style="width: 100%;"
                            >
                              <el-option label="关联" value="gl" />
                              <el-option label="不关联" value="bgl" />
                            </el-select>
                          </el-col>
                          <!-- 参数13 -->
                          <el-col :span="4">
                            <div
                              class="rule-condition"
                              @click="openCategoryDialog(index, sIndex, 'pn_param6', sItem.pn_param6)"
                            >
                              <el-input
                                :value="getDisplayName(sItem.pn_param6)"
                                placeholder="请选择"
                                size="small"
                                readonly
                                style="width: 100%"
                              />
                            </div>
                          </el-col>
                          <el-col :span="2">
                            <div class="text-center" style="line-height: 36px;">包含</div>
                          </el-col>
                          <!-- 参数14 -->
                          <el-col :span="4">
                            <div class="rule-condition">
                              <el-input
                                v-model="sItem.pn_param6_ct"
                                placeholder="请输入"
                                size="small"
                                style="width: 100%;"
                                @input="(value) => handleInput(value, index, sIndex, 'rule_other', 'pn_param6_ct')"
                              />
                            </div>
                          </el-col>
                          <el-col :span="2">
                            <div class="text-center" style="line-height: 36px;">不含</div>
                          </el-col>
                          <!-- 参数15 -->
                          <el-col :span="4">
                            <div class="rule-condition">
                              <el-input
                                v-model="sItem.pn_param6_oct"
                                placeholder="请输入"
                                size="small"
                                style="width: 100%;"
                                @input="(value) => handleInput(value, index, sIndex, 'rule_other', 'pn_param6_oct')"
                              />
                            </div>
                          </el-col>
                          <el-col v-if="sItem.condition === '病程记录关联'" :span="2">
                            <div class="button-group">
                              <el-button
                                type="primary"
                                plain
                                icon="el-icon-plus"
                                size="mini"
                                @click="onAddGZ(index, sIndex)"
                              />
                              <el-button
                                v-if="item.condition_content.length !== 1"
                                size="mini"
                                type="primary"
                                plain
                                icon="el-icon-minus"
                                @click="onDeleteGZ(index, sIndex)"
                              />
                            </div>
                          </el-col>
                        </el-row>
                      </el-col>
                      <el-col
                        v-if="!(sItem.condition === '病程记录关联' || sItem.condition === '时效')"
                        :span="2"
                      >
                        <div class="button-group">
                          <el-button
                            type="primary"
                            plain
                            icon="el-icon-plus"
                            size="mini"
                            @click="onAddGZ(index, sIndex)"
                          />
                          <el-button
                            v-if="item.condition_content.length !== 1"
                            size="mini"
                            type="primary"
                            plain
                            icon="el-icon-minus"
                            @click="onDeleteGZ(index, sIndex)"
                          />
                        </div>
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
                <el-card
                  v-for="(item, index) of ruleForm.rule"
                  :key="'rule' + index"
                  class="box-card"
                  shadow="never"
                >
                  <div slot="header" class="clearfix box-card_header span">
                    <span class="text">质控依据</span>
                    <i
                      class="el-icon-delete"
                      style="float: right; margin-top: 13px; cursor: pointer;"
                      @click="onDeleteZKYJ(index)"
                    />
                  </div>
                  <div>
                    <el-row
                      v-for="(sItem, sIndex) of item.custom_basis"
                      :key="'tj' + sIndex"
                      :gutter="12"
                      class="mb12"
                    >
                      <el-col :span="2">
                        <div class="text-right">条件{{ sIndex + 1 }}</div>
                      </el-col>
                      <el-col :span="6">
                        <div class="rule-condition">
                          <el-input
                            v-model="sItem.input1"
                            placeholder="请输入"
                            size="small"
                            style="width: 100%"
                          />
                        </div>
                      </el-col>
                      <el-col :span="6">
                        <div class="rule-condition">
                          <el-input
                            :value="getDisplayName(sItem.param1)"
                            placeholder="请选择"
                            size="small"
                            style="width: calc(100% - 90px)"
                            readonly
                            @click="openCategoryDialog(index, sIndex, 'custom_basis', sItem.param1)"
                          />
                          <el-button
                            size="small"
                            style="width: 90px"
                            class="category-select"
                            @click="openCategoryDialog(index, sIndex, 'custom_basis', sItem.param1)"
                          >
                            <i class="el-icon-s-operation" style="margin-right: 5px" />
                            选择
                          </el-button>
                        </div>
                      </el-col>
                      <el-col :span="6">
                        <el-input
                          v-model="sItem.input2"
                          clearable
                          placeholder="请输入"
                          style="width: 100%;"
                        />
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
                <el-card
                  v-for="(item, index) of ruleForm.rule"
                  :key="'rule' + index"
                  class="box-card"
                  shadow="never"
                >
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
                        <div
                          class="rule-condition"
                          @click="openCategoryDialog(index, 0, 'custom_msg', item.custom_msg.param1)"
                        >
                          <el-input
                            :value="getDisplayName(item.custom_msg.param1)"
                            placeholder="请选择"
                            size="small"
                            readonly
                            style="width: 100%"
                          />
                        </div>
                      </el-col>
                      <el-col :span="1">+</el-col>
                      <el-col :span="2">
                        <el-input
                          v-model="item.custom_msg.aftertime"
                          clearable
                          placeholder="请输入"
                          style="width: 100%;"
                        />
                      </el-col>
                      <el-col :span="1">分</el-col>
                      <el-col :span="1">前</el-col>
                      <el-col :span="2">
                        <el-input
                          v-model="item.pre_warning_time"
                          clearable
                          placeholder="请输入"
                          style="width: 100%;"
                        />
                      </el-col>
                      <el-col :span="2">分提醒</el-col>
                    </el-row>
                    <el-row :gutter="12" class="mb12">
                      <el-col :span="2">
                        <div class="text-right">预警提示</div>
                      </el-col>
                      <el-col :span="6">
                        <el-input
                          v-model="item.custom_msg.input1"
                          clearable
                          placeholder="请输入"
                          style="width: 100%;"
                        />
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
                        <el-input
                          v-model="item.custom_msg.input2"
                          clearable
                          placeholder="请输入"
                          style="width: 100%;"
                        />
                      </el-col>
                      <el-col :span="4" />
                    </el-row>
                  </div>
                </el-card>
              </el-col>
            </el-form-item>
          </el-col>
        </el-row>

        <div v-if="data.isParamsEx">
          <el-row :gutter="16">
            <el-col :span="12">
              <el-form-item prop="disease" label="病种">
                <el-select
                  v-model="ruleForm.disease"
                  :disabled="data.isDisable"
                  clearable
                  filterable
                  placeholder="请选择"
                  style="width: 100%;"
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
            <el-col :span="12">
              <el-form-item label="质控依据" prop="quality_basis">
                <el-input v-model="ruleForm.quality_basis" placeholder="请输入质控依据" />
              </el-form-item>
            </el-col>
          </el-row>
          <el-row :gutter="16">
            <el-col :span="12">
              <el-form-item label="依据来源" prop="basis_source">
                <el-select
                  v-model="ruleForm.basis_source"
                  clearable
                  filterable
                  placeholder="请选择"
                  style="width: 100%;"
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
            <el-col :span="12">
              <el-form-item label="数据来源" prop="data_source">
                <el-select
                  v-model="ruleForm.data_source"
                  clearable
                  filterable
                  placeholder="请选择"
                  style="width: 100%;"
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
          </el-row>
          <el-form-item label="触发条件" prop="trigger_condition">
            <el-input
              v-model="ruleForm.trigger_condition"
              type="textarea"
              :rows="2"
              placeholder="请输入触发条件"
            />
          </el-form-item>

          <el-form-item label="判断口径" prop="judgment_caliber">
            <el-input
              v-model="ruleForm.judgment_caliber"
              type="textarea"
              :rows="2"
              placeholder="请输入判断口径"
            />
          </el-form-item>
        </div>
      </el-form>
      <span v-if="titleStr !== '详情'" slot="footer" class="dialog-footer">
        <el-button type="primary" @click="submitForm('ruleForm')">提 交</el-button>
        <el-button @click="onTest">测试规则</el-button>
      </span>
    </el-dialog>

    <!-- 新增选择弹窗 -->
    <el-dialog title="选择类目" :visible.sync="categoryDialogVisible" width="600px" append-to-body>
      <el-row style="margin-bottom: 10px;" type="flex" justify="space-between">
        <el-input
          v-model="filterParams.keyword"
          clearable
          placeholder="请输入内容"
          @input="handleSearch"
        >
          <i slot="prefix" class="el-icon-search el-input__icon" />
        </el-input>
        <!-- <el-button style="margin-left: 20px" type="primary" @click="handleSubmit">确认</el-button> -->
      </el-row>
      <el-tabs
        v-model="activeCategory"
        type="border-card"
        class="tab"
        :tab-position="'left'"
        style="height:max-content"
        @tab-click="handleCategoryClick"
      >
        <el-tab-pane
          v-for="(item) in (searchObject)"
          :key="item.field"
          :label="item.field_name"
          :name="item.field"
        >
          <el-row slot="label" type="flex" align="middle">{{ item.field_name }}</el-row>
          <div class="grid-container">
            <el-tag
              v-for="(tag) in item.child"
              :key="tag.field"
              size="large"
              :type="`${tag.field === activeSubCategory ? 'primary': 'info'}`"
              @click="handleSubCategorySelect(tag)"
            >{{ tag.field_name }}</el-tag>
          </div>
        </el-tab-pane>
      </el-tabs>
    </el-dialog>

    <!-- 修改知识库弹窗 -->
    <el-dialog title="知识库" :visible.sync="knowledgeDialogVisible" width="800px" append-to-body>
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
              <el-button type="text" @click="handleKnowledgeView(scope.row)">查看</el-button>
              <el-button type="text" @click="handleKnowledgeSelect(scope.row)">引用</el-button>
            </template>
          </el-table-column>
        </el-table>
      </div>
    </el-dialog>

    <!-- 添加知识库详情弹窗 -->
    <el-dialog title="知识库详情" :visible.sync="knowledgeDetailVisible" width="600px" append-to-body>
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
    <el-dialog title="选择子选项" :visible.sync="childOptionsDialogVisible" width="30%">
      <div class="child-options-list">
        <el-button
          v-for="option in currentChildOptions"
          :key="option.field"
          size="small"
          @click="handleChildOptionSelect(option)"
        >{{ option.field_name }}</el-button>
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
          row: {},
          isParamsEx: false
        }
      }
    },
    objects: {
      type: Array,
      default() {
        return []
      }
    },
    diseaseOptions: {
      type: Array,
      default() {
        return []
      }
    },
    sourceOptions: {
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
      filterParams: {
        keyword: ''
      },
      qcData: [],
      filteredObjects: [],
      originalObjects: [],
      searchObject: [],
      selectFormula: [],
      ruleForm: {
        case_type: '',
        changjing: [],
        department: [],
        object: [],
        type: '',
        is_not: '',
        description: '',
        score: undefined,
        error_level: '',
        disease: '', // 病种
        trigger_condition: '', // 触发条件
        judgment_caliber: '', // 判断口径
        quality_basis: '', // 质控依据
        basis_source: '', // 依据来源
        data_source: '', // 数据来源
        status: '',
        rule_type: this.ruleType === 1 ? '普通规则' : '门诊规则',
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
                delete_field: '',
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
                childOptions: [],
                // 病程记录关联
                pn_param1_BLBH: '',
                pn_param1: [],
                pn_param2: '',
                pn_param3: [],
                pn_param4_index: [],
                pn_param4_condition1: '',
                pn_param4_condition2: '',
                pn_param4_kssj: [],
                pn_param4_jssj: [],
                pn_param4_sjjg: '',
                pn_param5: '',
                pn_param6: [],
                pn_param6_ct: '',
                pn_param6_oct: '',
                // 时效
                sx_param1: [],
                sx_param2: '',
                sx_param3: [],
                sx_param4_index: [],
                sx_param4_kssj: [],
                sx_param4_jssj: [],
                sx_param4_condition1: '',
                sx_param4_condition2: '',
                sx_param4_sjjg: ''
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
        description: [{ required: true, message: '请输入', trigger: 'blur' }],
        score: [{ required: true, message: '请输入', trigger: 'blur' }],
        case_type: [{ required: true, message: '请选择', trigger: 'blur' }],
        changjing: [{ required: true, message: '请选择', trigger: 'blur' }],
        department: [{ required: true, message: '请选择', trigger: 'blur' }],
        object: [{ required: true, message: '请选择', trigger: 'blur' }],
        type: [{ required: true, message: '请选择', trigger: 'blur' }],
        is_not: [{ required: true, message: '请选择', trigger: 'blur' }],
        error_level: [{ required: true, message: '请选择', trigger: 'blur' }],
        status: [{ required: true, message: '请选择', trigger: 'blur' }],
        rule_type: [{ required: true, message: '请选择', trigger: 'blur' }],
        param2: [{ required: true, message: '请输入', trigger: 'blur' }]
      },
      categoryDialogVisible: false,
      searchKeyword: '',
      selectedCategoryValue: '',
      currentRuleIndex: null,
      currentConditionIndex: null,
      categories: [],
      categoryType: 'text', // 默认选中文本类型
      activeCategory: '',
      activeSubCategory: '',
      currentSubCategories: [],
      selectedCategory: null,
      currentSource: '',
      currentInputSource: '', // 添加输入来源标识
      currentFieldsName: '',
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
      currentChildOptions: []
    }
  },
  computed: {
    titleStr() {
      return this.data.row.id
        ? this.data.actionType === 'DETAIL'
          ? '详情'
          : '编辑'
        : '新增'
    },
    filteredCategories() {
      if (!this.searchKeyword) return this.categories
      return this.categories.filter((item) =>
        item.label.toLowerCase().includes(this.searchKeyword.toLowerCase())
      )
    },
    bzmcArray() {
      return this.currentKnowledge.BZMC
        ? this.currentKnowledge.BZMC.split(',')
        : []
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
        ? typeof this.currentKnowledge.keywords === 'string'
          ? this.currentKnowledge.keywords.split(',')
          : this.currentKnowledge.keywords
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
    this.originalObjects = [...this.objects]
    this.searchObject = [...this.objects]
    this.getDropdownData()
  },
  methods: {
    onTest() {
      this.$message.info('功能待开放...')
    },
    // 获取详情
    getDetail(id) {
      get_rule_detail({ id }).then((res) => {
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
          status,
          disease,
          trigger_condition,
          judgment_caliber,
          quality_basis,
          basis_source,
          data_source
        } = p.rule

        // 直接使用字符串数组，不需要转换为数字
        this.ruleForm.department = department || []
        this.ruleForm.disease = disease
        this.ruleForm.trigger_condition = trigger_condition
        this.ruleForm.judgment_caliber = judgment_caliber
        this.ruleForm.quality_basis = quality_basis
        this.ruleForm.basis_source = basis_source
        this.ruleForm.data_source = data_source
        this.ruleForm.case_type = case_type
        this.ruleForm.changjing = changjing.split(',')
        this.ruleForm.is_not = is_not
        this.ruleForm.status = status
        this.ruleForm.type = type
        this.ruleForm.error_level = error_level
        this.ruleForm.score = score
        this.ruleForm.description = description
        this.ruleForm.object = object.split(',')

        // 获取规则列表并处理 param2
        const ruleList = this.getQcRule(p.rule_list)
        ruleList.forEach((rule) => {
          rule.condition_content.forEach((item) => {
            if (item.condition === '病程记录关联') {
              // 病程记录关联
              item.pn_param1_BLBH = item.param1[2]
              item.pn_param1 = [item.param1[0], item.param1[1]]
              item.pn_param2 = item.param2
              item.pn_param3 = item.param3
              item.pn_param4_index =
                (item.param4[0].index && item.param4[0].index.split('.')) || []
              item.pn_param4_condition1 = item.param4[0].condition1
              item.pn_param4_condition2 = item.param4[0].condition2
              item.pn_param4_kssj =
                (item.param4[0].kssj && item.param4[0].kssj.split('.')) || []
              item.pn_param4_jssj =
                (item.param4[0].jssj && item.param4[0].jssj.split('.')) || []
              item.pn_param4_sjjg = item.param4[0].sjjg
              item.pn_param5 = item.param5
              item.pn_param6 =
                (item.param6[0] && item.param6[0].split('.')) || []
              item.pn_param6_ct = item.param6[1]
              item.pn_param6_oct = item.param6[2]
            }
            if (item.condition === '时效') {
              // 时效
              item.sx_param1 = [item.param1[0], item.param1[1]]
              item.sx_param2 = item.param2
              item.sx_param3 = [item.param3[0], item.param3[1]]
              item.sx_param4_index =
                (item.param4[0].index && item.param4[0].index.split('.')) || []
              item.sx_param4_condition1 = item.param4[0].condition1
              item.sx_param4_condition2 = item.param4[0].condition2
              item.sx_param4_kssj =
                (item.param4[0].kssj && item.param4[0].kssj.split('.')) || []
              item.sx_param4_jssj =
                (item.param4[0].jssj && item.param4[0].jssj.split('.')) || []
              item.sx_param4_sjjg = item.param4[0].sjjg
            }
            // 如果是字典类型且 param2 是字符串，转换为数组
            if (
              item.hasChildOptions &&
              typeof item.param2 === 'string' &&
              item.param2
            ) {
              item.param2 = item.param2.split(',')
            }
            // 处理重复率条件的param2
            if (item.condition === '重复率' && item.param2) {
              // 将形如 "bllb294_295_2023.BLTD" 的字符串解析为对象数组
              const parts = item.param2.split('.')
              if (parts.length === 2) {
                item.param2_object = [parts[0], parts[1]]
              }
            }
          })
        })
        this.ruleForm.rule = ruleList

        // 获取前置条件并处理 param2
        const preRuleList = this.getPreRule(p.rule_list)
        preRuleList.forEach((rule) => {
          rule.condition_content.forEach((item) => {
            // 如果是字典类型且 param2 是字符串，转换为数组
            if (
              item.hasChildOptions &&
              typeof item.param2 === 'string' &&
              item.param2
            ) {
              item.param2 = item.param2.split(',')
            }
            // 处理重复率条件的param2
            if (item.condition === '重复率' && item.param2) {
              // 将形如 "bllb294_295_2023.BLTD" 的字符串解析为对象数组
              const parts = item.param2.split('.')
              if (parts.length === 2) {
                item.param2_object = [parts[0], parts[1]]
              }
            }
          })
        })
        this.ruleForm.qztj = preRuleList
      })
    },
    // 找到前置条件
    getPreRule(list) {
      return list.filter((item) => item.is_pre_condition)
    },
    // 找到质控逻辑
    getQcRule(list) {
      const rule = list.filter((item) => !item.is_pre_condition)
      return rule
    },
    getSelectFormula() {
      getSelectFormula({ lx: 0 }).then((res) => {
        const { p } = res
        console.log('select formula:', p)
        this.selectFormula = p
      })
    },
    // 搜索质控字典
    getQcData() {
      get_all_word_map({ status: 1 }).then((res) => {
        const { p } = res
        this.qcData = Array.isArray(p) ? p : []
      })
    },
    querySearch(queryString, cb) {
      var qcData = this.qcDataruleForm.rule
      var results = queryString
        ? qcData.filter(this.createFilter(queryString))
        : qcData
      // 调用 callback 返回建议列表的数据
      cb(results)
    },
    createFilter(queryString) {
      return (restaurant) => {
        return (
          restaurant.name.toLowerCase().indexOf(queryString.toLowerCase()) === 0
        )
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
            param2_object: null, // 添加这个字段存储对象选择
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
        param2_object: null, // 添加这个字段存储对象选择
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
        delete_field: '',
        param2_object: null, // 添加这个字段存储对象选择
        condition: '包含',
        subtract_param: '',
        subtract_condition: '',
        subtract_value: '',
        subtract_value_type: 'number',
        hasChildOptions: false,
        childOptions: [],
        // 病程记录关联
        pn_param1_BLBH: '',
        pn_param1: [],
        pn_param2: '',
        pn_param3: [],
        pn_param4_index: [],
        pn_param4_condition1: '',
        pn_param4_condition2: '',
        pn_param4_kssj: [],
        pn_param4_jssj: [],
        pn_param4_sjjg: '',
        pn_param5: '',
        pn_param6: [],
        pn_param6_ct: '',
        pn_param6_oct: '',
        // 时效
        sx_param1: [],
        sx_param2: '',
        sx_param3: [],
        sx_param4_index: [],
        sx_param4_kssj: [],
        sx_param4_jssj: [],
        sx_param4_condition1: '',
        sx_param4_condition2: '',
        sx_param4_sjjg: ''
      })
    },
    // 删除质控逻辑大框中_逻辑
    onDeleteGZ(index, sIndex) {
      this.ruleForm.rule[index].condition_content.splice(sIndex, 1)
    },
    // 添加质控依据
    onAddZKYJ() {
      this.ruleForm.rule[0].custom_basis = this.ruleForm.rule[0].custom_basis
        ? this.ruleForm.rule[0].custom_basis
        : []
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
      console.log('>>>>>>>>>>', qztj)

      if (qztj.length) {
        for (let i = 0; i < qztj.length; i++) {
          qztj[i].condition_content.forEach((item) => {
            // 检查必填项
            // const isEmpty = !item.param1 ||
            //   (Array.isArray(item.param2) ? item.param2.length === 0 : !item.param2) ||
            //   !item.condition
            // 检查必填项
            const isEmpty = !item.param1 || !item.condition
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
      rule[0].condition_content.map((item) => {
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
              const contentArr = []
              content.forEach((item) => {
                const element = { ...item }
                if (element.condition === '病程记录关联') {
                  const elementParams = {
                    param1: [
                      Array.isArray(element.pn_param1) &&
                      !!element.pn_param1.length
                        ? element.pn_param1[0]
                        : '',
                      Array.isArray(element.pn_param1) &&
                      !!element.pn_param1.length
                        ? element.pn_param1[1]
                        : '',
                      element.pn_param1_BLBH
                    ],
                    param2: element.pn_param2,
                    condition: element.condition,
                    param3: [
                      Array.isArray(element.pn_param3) &&
                      !!element.pn_param3.length
                        ? element.pn_param3[0]
                        : '',
                      Array.isArray(element.pn_param3) &&
                      !!element.pn_param3.length
                        ? element.pn_param3[1]
                        : ''
                    ],
                    param4: [
                      {
                        kssj: element.pn_param4_kssj.join('.'),
                        jssj: element.pn_param4_jssj.join('.'),
                        sjjg: element.pn_param4_sjjg,
                        index: element.pn_param4_index.join('.'),
                        condition1: element.pn_param4_condition1,
                        condition2: element.pn_param4_condition2
                      }
                    ],
                    param5: element.pn_param5,
                    param6: [
                      element.pn_param6.join('.'),
                      element.pn_param6_ct,
                      element.pn_param6_oct
                    ]
                  }
                  contentArr.push(elementParams)
                } else if (element.condition === '时效') {
                  const elementParams = {
                    param1: [
                      Array.isArray(element.sx_param1) &&
                      !!element.sx_param1.length
                        ? element.sx_param1[0]
                        : '',
                      Array.isArray(element.sx_param1) &&
                      !!element.sx_param1.length
                        ? element.sx_param1[1]
                        : ''
                    ],
                    param2: element.sx_param2,
                    condition: element.condition,
                    param3: [
                      Array.isArray(element.sx_param3) &&
                      !!element.sx_param3.length
                        ? element.sx_param3[0]
                        : '',
                      Array.isArray(element.sx_param3) &&
                      !!element.sx_param3.length
                        ? element.sx_param3[1]
                        : ''
                    ],
                    param4: [
                      {
                        kssj: element.sx_param4_kssj.join('.'),
                        jssj: element.sx_param4_jssj.join('.'),
                        sjjg: element.sx_param4_sjjg,
                        index: element.sx_param4_index.join('.'),
                        condition1: element.sx_param4_condition1,
                        condition2: element.sx_param4_condition2
                      }
                    ]
                  }
                  contentArr.push(elementParams)
                } else {
                  // 处理多选值
                  if (Array.isArray(element.param2)) {
                    element.param2 = element.param2.join(',')
                  }
                  if (element.condition === '减' && element.subtract_param) {
                    // 组合减法条件的完整表达式
                    const subtractValue = this.getDisplayName(
                      element.subtract_param
                    )
                    if (subtractValue) {
                      // 获取父级字段名
                      const [parentField] = element.subtract_param
                      // 构造 param2 格式: parentField.field
                      element.param2 = `${parentField}.${element.subtract_param[1]}`
                    }
                  }
                  // 对重复率条件的特殊处理
                  if (element.condition === '重复率') {
                    // 确保param2格式正确
                    if (
                      element.param2_object &&
                      Array.isArray(element.param2_object)
                    ) {
                      const [field1, field2] = element.param2_object
                      element.param2 = `${field1}.${field2}`
                    }
                    // 确保subtract_value是数字格式
                    if (element.subtract_value !== undefined) {
                      element.subtract_value = Number(element.subtract_value)
                    }
                  }
                  contentArr.push(element)
                }
              })
              return contentArr
            }

            // 处理前置条件和质控逻辑中的减法条件
            this.ruleForm.qztj.forEach((item) => {
              processConditionContent(item.condition_content)
            })
            console.log('=============this.ruleForm', this.ruleForm.rule)

            const rules = []
            this.ruleForm.rule.forEach((item) => {
              rules.push({
                ...item,
                condition_content: processConditionContent(
                  item.condition_content
                )
              })
            })

            // 继续原有的提交逻辑
            const params = {
              case_type: this.ruleForm.case_type,
              changjing: this.ruleForm.changjing,
              department: this.ruleForm.department,
              object: this.ruleForm.object,
              type: this.ruleForm.type,
              is_not: this.ruleForm.is_not,
              description: this.ruleForm.description,
              score: this.ruleForm.score,
              error_level: this.ruleForm.error_level,
              status: this.ruleForm.status,
              rule_type: this.ruleForm.rule_type,

              rule: [...rules, ...this.ruleForm.qztj]
            }
            if (this.data.isParamsEx) {
              params.disease = this.ruleForm.disease // 病种
              params.trigger_condition = this.ruleForm.trigger_condition // 触发条件
              params.judgment_caliber = this.ruleForm.judgment_caliber // 判断口径
              params.quality_basis = this.ruleForm.quality_basis // 质控依据
              params.basis_source = this.ruleForm.basis_source // 依据来源
              params.data_source = this.ruleForm.data_source // 数据来源
            }
            console.log('>>>>>>>>>>>>>>>>>>>>', params)
            if (this.data.row.id) {
              params.id = this.data.row.id
            }
            add_rule(params).then((res) => {
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
    openCategoryDialog(index, sIndex, source, value) {
      this.searchObject = [...this.objects]
      this.filterParams.keyword = ''
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
        if (
          parentElement &&
          parentElement
            .querySelector('.box-card_header span')
            ?.textContent.includes('前置条件')
        ) {
          this.currentInputSource = 'qztj'
        } else {
          this.currentInputSource = 'rule'
        }
        this.currentSource = source // 保持原有的 source 值
      }

      // 设置默认显示
      if (this.objects.length > 0) {
        // this.activeCategory = this.objects[0].field_name
        if (value) {
          if (Array.isArray(value)) {
            if (value.length === 0) {
              this.activeCategory = this.objects[0].field
              this.activeSubCategory = ''
            } else {
              this.activeCategory = value[0]
              this.activeSubCategory = value[1]
            }
          } else {
            if (value instanceof String) {
              this.activeCategory = value.split(',')[0]
              this.activeSubCategory = value.split(',')[1]
            }
          }
        } else {
          this.activeCategory = value ? value[0] : this.objects[0].field
          this.activeSubCategory = ''
        }
        // this.currentSubCategories = this.objects[0].child || []
      }

      this.categoryDialogVisible = true
      this.selectedCategory = null
    },
    handleSearch() {
      const keyword = this.filterParams.keyword.trim().toLowerCase()

      // 如果没有关键词，显示所有原始数据
      if (!keyword) {
        this.searchObject = [...this.originalObjects]
        // 重置到第一个分类
        if (this.searchObject.length > 0) {
          this.activeCategory = this.searchObject[0].field
        }
        return
      }

      // 同时过滤父类目和子类目
      this.filteredObjects = this.originalObjects.reduce((result, parent) => {
        // 过滤匹配的子类目
        const matchedChildren = parent.child
          ? parent.child.filter((child) =>
            child.field_name.toLowerCase().includes(keyword)
          )
          : []

        // 检查父类目是否匹配
        const parentMatches = parent.field_name.toLowerCase().includes(keyword)

        // 只有当父类目匹配或者有匹配的子类目时才保留
        if (parentMatches || matchedChildren.length > 0) {
          // 始终只显示匹配的子类目，即使父类目本身匹配
          const filteredChild = parentMatches
            ? parent.child.filter((child) =>
              child.field_name.toLowerCase().includes(keyword)
            )
            : matchedChildren

          // 确保有子项才添加到结果中
          if (filteredChild.length > 0) {
            result.push({
              ...parent,
              child: filteredChild
            })
          }
        }

        return result
      }, [])

      // 更新显示数据
      this.searchObject = [...this.filteredObjects]

      // 自动切换到第一个匹配的分类
      if (this.searchObject.length > 0) {
        this.activeCategory = this.searchObject[0].field
        this.activeSubCategory = ''
      } else {
        this.activeCategory = ''
        this.activeSubCategory = ''
      }
    },
    confirmCategory() {
      if (this.selectedCategory) {
        // 更新对应条件的类目
        this.ruleForm.rule[this.currentRuleIndex].condition_content[
          this.currentConditionIndex
        ].category = this.selectedCategory
        // 可能还需要更新显示的文本
        const selectedItem = this.categories.find(
          (item) => item.value === this.selectedCategory
        )
        this.ruleForm.rule[this.currentRuleIndex].condition_content[
          this.currentConditionIndex
        ].param2 = selectedItem ? selectedItem.label : ''
      }
      this.categoryDialogVisible = false
    },
    // 点击左侧类目
    handleCategoryClick(item) {
      this.activeCategory = item.name
      // this.currentSubCategories = item.child || []
    },
    // 选择右侧子类目
    handleSubCategorySelect(subItem) {
      if (
        this.currentRuleIndex !== null &&
        this.currentConditionIndex !== null
      ) {
        // const parentObj = this.objects.find(obj => obj.field === this.activeCategory)
        const parentField = this.activeCategory || ''

        if (this.currentSource === 'qztj') {
          const condition =
            this.ruleForm.qztj[this.currentRuleIndex].condition_content[
              this.currentConditionIndex
            ]
          if (condition) {
            condition.param1 = [parentField, subItem.field]
            condition.field_name = subItem.field_name
            condition.categoryTypeDisabled = false
            // 检查子数据类型
            if (
              subItem.type === 2 &&
              subItem.child &&
              subItem.child.length > 0
            ) {
              const dictData = subItem.child.find((item) => item.type === 4)
              if (dictData) {
                condition.hasChildOptions = true
                condition.categoryType = '选择'
                condition.categoryTypeDisabled = true // 重置禁用标志
                // type=4 的情况，获取字典数据
                get_dict_by_type({
                  table: dictData.field,
                  field: dictData.field_name
                }).then((res) => {
                  if (res?.p) {
                    // 转换数据格式
                    condition.childOptions = res.p.map((item) => ({
                      field: item.dep_name,
                      field_name: item.dep_name
                    }))
                  }
                })
              } else if (subItem.child.some((item) => item.type === 3)) {
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
          const condition =
            this.ruleForm.rule[this.currentRuleIndex].condition_content[
              this.currentConditionIndex
            ]
          if (condition) {
            condition.param1 = [parentField, subItem.field]
            condition.field_name = subItem.field_name

            condition.categoryTypeDisabled = false
            if (
              subItem.type === 2 &&
              subItem.child &&
              subItem.child.length > 0
            ) {
              const dictData = subItem.child.find((item) => item.type === 4)
              if (dictData) {
                condition.hasChildOptions = true
                condition.categoryType = '选择'
                condition.categoryTypeDisabled = true // 重置禁用标志
                get_dict_by_type({
                  table: dictData.field,
                  field: dictData.field_name
                }).then((res) => {
                  if (res?.p) {
                    condition.childOptions = res.p.map((item) => ({
                      field: item.dep_name,
                      field_name: item.dep_name
                    }))
                  }
                })
              } else if (subItem.child.some((item) => item.type === 3)) {
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
        } else if (this.currentSource === 'sx_1') {
          if (this.currentInputSource === 'qztj') {
            // 前置条件的时效
            const condition =
              this.ruleForm.qztj[this.currentRuleIndex].condition_content[
                this.currentConditionIndex
              ]
            if (condition) {
              condition.sx_1 = [parentField, subItem.field]
              condition.field_name = subItem.field_name
            }
          } else {
            // 质控逻辑的时效
            const condition =
              this.ruleForm.rule[this.currentRuleIndex].condition_content[
                this.currentConditionIndex
              ]
            if (condition) {
              condition.sx_1 = [parentField, subItem.field]
              condition.field_name = subItem.field_name
            }
          }
        } else if (this.currentSource === 'sx_2') {
          if (this.currentInputSource === 'qztj') {
            // 前置条件的时效
            const condition =
              this.ruleForm.qztj[this.currentRuleIndex].condition_content[
                this.currentConditionIndex
              ]
            if (condition) {
              condition.sx_2 = [parentField, subItem.field]
              condition.field_name = subItem.field_name
              this.$set(condition, 'sx_3', '') // 确保 sx_3 是响应式的
            }
          } else {
            // 质控逻辑的时效
            const condition =
              this.ruleForm.rule[this.currentRuleIndex].condition_content[
                this.currentConditionIndex
              ]
            if (condition) {
              condition.sx_2 = [parentField, subItem.field]
              condition.field_name = subItem.field_name
              this.$set(condition, 'sx_3', '') // 确保 sx_3 是响应式的
            }
          }
        } else if (this.currentSource === 'rule') {
          // 质控逻辑
          const condition =
            this.ruleForm.rule[this.currentRuleIndex].condition_content[
              this.currentConditionIndex
            ]
          if (condition) {
            condition.param1 = [parentField, subItem.field]
            condition.field_name = subItem.field_name
            // 检查是否有 type=4 的子数据
            if (
              subItem.type === 2 &&
              subItem.child &&
              subItem.child.length > 0
            ) {
              const dictData = subItem.child.find((item) => item.type === 4)
              if (dictData) {
                condition.hasChildOptions = true
                // type=4 的情况，获取字典数据
                get_dict_by_type({
                  table: dictData.field,
                  field: dictData.field_name
                }).then((res) => {
                  if (res?.p) {
                    // 转换数据格式
                    condition.childOptions = res.p.map((item) => ({
                      field: item.dep_name,
                      field_name: item.dep_name
                    }))
                  }
                })
              } else if (subItem.child.some((item) => item.type === 3)) {
                // type=3 的情况，直接使用 child 数据
                condition.hasChildOptions = true
                condition.childOptions = subItem.child
              }
            }
          }
        } else if (this.currentSource === 'custom_basis') {
          // 质控依据
          const basis =
            this.ruleForm.rule[this.currentRuleIndex].custom_basis[
              this.currentConditionIndex
            ]
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
            const condition =
              this.ruleForm.qztj[this.currentRuleIndex].condition_content[
                this.currentConditionIndex
              ]
            if (condition) {
              condition.subtract_param = [parentField, subItem.field]
            }
          } else if (this.currentInputSource === 'rule') {
            const condition =
              this.ruleForm.rule[this.currentRuleIndex].condition_content[
                this.currentConditionIndex
              ]
            if (condition) {
              condition.subtract_param = [parentField, subItem.field]
            }
          }
        } else if (this.currentSource === 'param2_object') {
          // 判断是在前置条件还是质控逻辑中
          if (this.currentInputSource === 'qztj') {
            const condition =
              this.ruleForm.qztj[this.currentRuleIndex].condition_content[
                this.currentConditionIndex
              ]
            if (condition) {
              // 存储完整的对象选择，而不只是字段名
              condition.param2_object = [parentField, subItem.field]
              // 同时更新param2为字符串表示形式，用于提交
              condition.param2 = `${parentField}.${subItem.field}`
            }
          } else {
            const condition =
              this.ruleForm.rule[this.currentRuleIndex].condition_content[
                this.currentConditionIndex
              ]
            if (condition) {
              condition.param2_object = [parentField, subItem.field]
              condition.param2 = `${parentField}.${subItem.field}`
            }
          }
        } else if (
          this.currentSource === 'pn_param1' ||
          this.currentSource === 'pn_param3' ||
          this.currentSource === 'pn_param6' ||
          this.currentSource === 'pn_param4_index' ||
          this.currentSource === 'pn_param4_kssj' ||
          this.currentSource === 'pn_param4_jssj'
        ) {
          // 判断是在前置条件还是质控逻辑中
          if (this.currentInputSource === 'qztj') {
            const condition =
              this.ruleForm.qztj[this.currentRuleIndex].condition_content[
                this.currentConditionIndex
              ]
            if (condition) {
              // 存储完整的对象选择，而不只是字段名
              condition.param2_object = [parentField, subItem.field]
              // 同时更新param2为字符串表示形式，用于提交
              condition.param2 = `${parentField}.${subItem.field}`
            }
          } else {
            const condition =
              this.ruleForm.rule[this.currentRuleIndex].condition_content[
                this.currentConditionIndex
              ]
            if (condition) {
              condition[this.currentSource] = [parentField, subItem.field]
            }
          }
        } else if (
          this.currentSource === 'sx_param1' ||
          this.currentSource === 'sx_param3' ||
          this.currentSource === 'sx_param4_index' ||
          this.currentSource === 'sx_param4_kssj' ||
          this.currentSource === 'sx_param4_jssj'
        ) {
          // 判断是在前置条件还是质控逻辑中
          if (this.currentInputSource === 'qztj') {
            const condition =
              this.ruleForm.qztj[this.currentRuleIndex].condition_content[
                this.currentConditionIndex
              ]
            if (condition) {
              // 存储完整的对象选择，而不只是字段名
              condition.param2_object = [parentField, subItem.field]
              // 同时更新param2为字符串表示形式，用于提交
              condition.param2 = `${parentField}.${subItem.field}`
            }
          } else {
            const condition =
              this.ruleForm.rule[this.currentRuleIndex].condition_content[
                this.currentConditionIndex
              ]
            if (condition) {
              condition[this.currentSource] = [parentField, subItem.field]
            }
          }
        }
      }
      this.categoryDialogVisible = false
    },
    getDisplayName(param1) {
      if (!Array.isArray(param1)) return ''
      const [parentField, childField] = param1
      const parentObj = this.objects.find((obj) => obj.field === parentField)
      if (!parentObj) return ''
      const childObj = parentObj.child.find(
        (child) => child.field === childField
      )
      return childObj ? childObj.field_name : ''
    },
    handleInput(value, ruleIndex, index, source = 'custom_basis', fieldsName) {
      if (value && value.endsWith('@')) {
        this.currentInputIndex = index
        this.currentRuleIndexForKnowledge = ruleIndex
        this.currentInputSource = source
        this.knowledgeDialogVisible = true
        this.currentFieldsName = fieldsName
        this.getKnowledgeList()
      }
    },
    handleKnowledgeSelect(item) {
      if (
        this.currentInputIndex !== null &&
        this.currentRuleIndexForKnowledge !== null
      ) {
        if (this.currentInputSource === 'rule') {
          // 质控逻辑
          const condition =
            this.ruleForm.rule[this.currentRuleIndexForKnowledge]
              .condition_content[this.currentInputIndex]
          if (condition) {
            // 直接替换整个值，不保留@
            condition.param2 = condition.param2.replace(/@.*$/, item.keyword)
          }
        } else if (this.currentInputSource === 'qztj') {
          const condition =
            this.ruleForm.qztj[this.currentRuleIndexForKnowledge]
              .condition_content[this.currentInputIndex]
          if (condition) {
            condition.param2 = condition.param2.replace(/@.*$/, item.keyword)
          }
        } else if (this.currentInputSource === 'rule_other') {
          const condition =
            this.ruleForm.rule[this.currentRuleIndexForKnowledge]
              .condition_content[this.currentInputIndex]
          if (condition) {
            // condition[this.currentFieldsName] = condition[this.currentFieldsName].replace(/@.*$/, item.name)
            condition[this.currentFieldsName] = `@${item.keyword}`
          }
        } else {
          const basis =
            this.ruleForm.rule[this.currentRuleIndexForKnowledge].custom_basis[
              this.currentInputIndex
            ]
          if (basis) {
            // 直接替换整个值，不保留@
            basis.input2 = basis.input2.replace(/@.*$/, item.keyword)
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
      // if (this.ruleForm.rule && this.ruleForm.rule.length > 0) {
      //   this.$message.warning('质控逻辑已存在')
      //   return
      // }

      this.ruleForm.rule.push({
        is_pre_condition: 0,
        condition_type: 1,
        condition_relation: 2,
        detail_status: 1,
        condition_content: [
          {
            param1: '',
            param2: '',
            param2_object: null, // 添加这个字段存储对象选择
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
      })
    },
    // 删除质控逻辑条件大框
    onDeleteZKLJ(index) {
      if (index === 0) {
        this.$message.warning('质控逻辑第一项不可删除')
        return
      }
      this.ruleForm.rule.splice(index, 1)
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
      getSelectFormula({ lx: 1 }).then((res) => {
        const { p } = res
        console.log('compare formula:', p)
        this.compareFormula = p
      })
    },
    // 获取类型数据
    getCategories() {
      getSelectFormula({ lx: 1 }).then((res) => {
        const { p } = res
        console.log('categories:', p)
        // 转换数据格式
        this.categories = p.map((item) => ({
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
      edit_word_map(params).then((res) => {
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
      edit_word_map(params).then((res) => {
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
      edit_word_map(params).then((res) => {
        this.$message.success('删除成功')
        this.getKnowledgeDetail(this.currentKnowledge.id)
      })
    },
    // 处理关键词的删除
    handleKeywordClose(index) {
      const keywords = this.formattedKeywords.split(',')
      keywords.splice(index, 1)

      const params = this.getFullParams({ keywords: keywords.join(',') })
      edit_word_map(params).then((res) => {
        this.$message.success('删除成功')
        this.getKnowledgeDetail(this.currentKnowledge.id)
      })
    },
    // 保存名称
    saveKnowledge() {
      const params = this.getFullParams({ name: this.editKnowledge.name })
      edit_word_map(params).then((res) => {
        this.$message.success('保存成功')
        this.knowledgeDetailVisible = false
        // 刷新知识库列表
        this.getKnowledgeList()
      })
    },
    // 获取知识库详情
    getKnowledgeDetail(id) {
      get_all_word_map({ id }).then((res) => {
        const { p } = res
        if (Array.isArray(p) && p.length > 0) {
          // 找到对应 id 的数据
          const detail = p.find((item) => item.id === id)
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
        const caseTypeRes = await get_rule_setting_other({
          type: 1,
          issz: this.ruleType === 1 ? 0 : this.ruleType === 3 ? 3 : 1
        })
        if (caseTypeRes?.p) {
          this.caseTypeOptions = caseTypeRes.p.map((item) => ({
            value: item.name, // 使用 name 作为 value
            label: item.name // 使用 name 作为 label
          }))
        }

        // 获取质控类型
        const qualityTypeRes = await get_rule_setting_other({
          type: 2,
          issz: this.ruleType === 1 ? 0 : this.ruleType === 3 ? 3 : 1
        })
        if (qualityTypeRes?.p) {
          this.qualityTypeOptions = qualityTypeRes.p.map((item) => ({
            value: item.name,
            label: item.name
          }))
        }

        // 获取质控场景
        const sceneRes = await get_rule_setting_other({
          type: 3,
          issz: this.ruleType === 1 ? 0 : this.ruleType === 3 ? 3 : 1
        })
        if (sceneRes?.p) {
          this.sceneOptions = sceneRes.p.map((item) => ({
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
      const currentItem =
        this.ruleForm[this.currentInputSource][index].condition_content[sIndex]
      if (currentItem.param1 && Array.isArray(currentItem.param1)) {
        const [parentField, field] = currentItem.param1
        const category = this.objects.find(
          (item) => item.field === parentField
        )
        const subCategory = category?.child?.find(
          (item) => item.field === field
        )

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
      const condition =
        this.ruleForm[this.currentInputSource][this.currentRuleIndex]
          .condition_content[this.currentConditionIndex]
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
    background: #f7f7f8;
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

  .category-menu {
    height: 100%;
    border-right: 1px solid #e6e6e6;
  }

  .sub-category-list {
    padding: 10px;
    display: flex;
    flex-wrap: wrap;
    gap: 10px;

    .el-button {
      margin: 5px;
    }
  }
}

.rule-condition {
  position: relative;
  display: flex;
  align-items: center;
  cursor: pointer; // 添加手型光标

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

  .el-button {
    padding: 7px 8px;
  }
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

.progress-note {
  .el-row {
    display: flex;
    flex-wrap: wrap;
    justify-content: flex-start;
  }
  .el-col {
    margin-bottom: 10px;
  }
}
.grid-container {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 10px;
  .el-tag {
    text-align: center;
  }
}
::v-deep .el-tabs--left .el-tabs__nav-wrap.is-left.is-scrollable {
  padding: 0;

  .el-tabs__nav-prev {
    display: none;
    height: 0px;
  }

  .el-tabs__nav-next {
    display: none;
    height: 0;
  }
}
</style>
