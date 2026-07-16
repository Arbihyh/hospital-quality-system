<template>
  <div class="app-container">
    <el-form
      ref="queryForm"
      :model="queryParams"
      size="small"
      :inline="true"
      class="head-query-form"
      label-width="68px"
    >
      <el-form-item label="缺陷字段" prop="auth">
        <el-input
          v-model="queryParams.auth"
          size="small"
          placeholder="请输入字段"
          clearable
          style="width: 220px"
          @keyup.enter.native="handleQuery"
          @clear="handleQuery"
        />
      </el-form-item>
      <el-form-item label="字段名称" prop="field">
        <el-input
          v-model="queryParams.field"
          size="small"
          placeholder="请输入字段名称"
          clearable
          style="width: 220px"
          @keyup.enter.native="handleQuery"
          @clear="handleQuery"
        />
      </el-form-item>
      <el-form-item label="规则描述" prop="desc">
        <el-input
          v-model="queryParams.desc"
          size="small"
          placeholder="请输入规则描述"
          clearable
          style="width: 220px"
          @keyup.enter.native="handleQuery"
          @clear="handleQuery"
        />
      </el-form-item>
      <el-form-item label="错误等级" prop="level">
        <el-select
          v-model="queryParams.level"
          placeholder="错误等级"
          clearable
          style="width: 220px"
          @clear="handleQuery"
        >
          <el-option
            v-for="item in level"
            :key="item.id"
            :label="item.name"
            :value="item.id"
          />
        </el-select>
      </el-form-item>
      <el-form-item label="缺陷分类" prop="type">
        <el-select
          v-model="queryParams.type"
          placeholder="缺陷分类"
          clearable
          style="width: 220px"
          @clear="handleQuery"
        >
          <el-option
            v-for="item in type"
            :key="item.id"
            :label="item.name"
            :value="item.id"
          />
        </el-select>
      </el-form-item>
      <el-form-item label="缺陷类型" prop="errorType">
        <el-select
          v-model="queryParams.errorType"
          placeholder="缺陷类型"
          clearable
          style="width: 220px"
          @clear="handleQuery"
        >
          <el-option
            v-for="item in errorType"
            :key="item.id"
            :label="item.name"
            :value="item.id"
          />
        </el-select>
      </el-form-item>
      <el-form-item label="缺陷类别" prop="category">
        <el-select
          v-model="queryParams.category"
          placeholder="缺陷类别"
          clearable
          style="width: 220px"
          @clear="handleQuery"
        >
          <el-option
            v-for="item in category"
            :key="item.id"
            :label="item.name"
            :value="item.id"
          />
        </el-select>
      </el-form-item>
      <el-form-item label="规则状态" prop="status">
        <el-select
          v-model="queryParams.status"
          placeholder="规则状态"
          clearable
          style="width: 220px"
          @clear="handleQuery"
        >
          <el-option
            v-for="item in statusArr"
            :key="item.id"
            :label="item.name"
            :value="item.id"
          />
        </el-select>
      </el-form-item>
      <el-form-item label="编码员" prop="">
        <el-select
          v-model="queryParams.bmy_level"
          placeholder="编码员"
          clearable
          style="width: 220px"
          @clear="handleQuery"
        >
          <el-option
            v-for="item in level"
            :key="item.id"
            :label="item.name"
            :value="item.id"
          />
        </el-select>
      </el-form-item>
      <el-form-item>
        <el-button
          type="primary"
          icon="el-icon-search"
          @click="handleQuery"
        >搜索</el-button>
        <el-button
          icon="el-icon-refresh"
          @click="handleResetQuery"
        >重置</el-button>
      </el-form-item>
    </el-form>
    <el-row :gutter="10" class="mb8">
      <el-col :span="9">
        <el-col :span="1.5">
          <el-tooltip class="item" effect="dark" content="刷新" placement="top">
            <el-button
              size="small"
              icon="el-icon-refresh"
              @click="handleRefresh"
            />
          </el-tooltip>
        </el-col>
        <el-col :span="1.5">
          <el-button
            v-if="checkPermission(['admin/rule/addErrorRule'])"
            type="primary"
            plain
            icon="el-icon-plus"
            size="small"
            @click="handleAdd"
          >新增</el-button>
        </el-col>
      </el-col>
    </el-row>
    <el-table v-loading="listLoading" :data="pageList" max-height="1000">
      <el-table-column type="index" label="序号" width="50" />
      <el-table-column
        label="字段"
        width="170"
        header-align="center"
        align="left"
      >
        <template slot-scope="scope">
          <el-popover trigger="click" placement="top" width="300">
            <div style="max-height: 300px; overflow-y: auto">
              {{ scope.row.auth }}
            </div>
            <div slot="reference" class="text-more-box">
              <el-tag style="width: 150px">{{ scope.row.auth }}</el-tag>
            </div>
          </el-popover>
        </template>
      </el-table-column>
      <el-table-column label="字段名称" width="160">
        <template slot-scope="scope">
          <el-popover trigger="click" placement="top" width="400">
            <div style="max-height: 400px; overflow-y: auto">
              {{ scope.row.field }}
            </div>
            <div slot="reference" class="text-more-box">
              {{ scope.row.field }}
            </div>
          </el-popover>
        </template>
      </el-table-column>
      <!-- <el-table-column label="规则" width="130">
        <template slot-scope="scope">
          <el-tag style="width:110px;" type="info">{{ scope.row.rule }}</el-tag>
        </template>
      </el-table-column>
      <el-table-column prop="relation" label="关联字段" width="130" />
      <el-table-column label="关联规则" width="240">
        <template slot-scope="scope">
          <el-popover trigger="click" placement="top" width="400">
            <div style="max-height: 400px;overflow-y: auto;">{{ scope.row.relation_rule }}</div>
            <div slot="reference" class="text-more-box">
              {{ scope.row.relation_rule }}
            </div>
          </el-popover>
        </template>
      </el-table-column> -->
      <el-table-column prop="level" label="错误等级" width="110">
        <template slot-scope="scope">
          <span>
            <el-tag
              style="max-width: 90px"
              :type="scope.row.level === 0 ? 'danger' : 'info'"
            >
              {{ scope.row.level | formatSingleInArray(level) }}
            </el-tag>
          </span>
        </template>
      </el-table-column>
      <el-table-column prop="category" label="缺陷类别" width="80">
        <template slot-scope="scope">
          <span>{{ scope.row.category | formatSingleInArray(category) }}</span>
        </template>
      </el-table-column>
      <el-table-column label="规则描述" width="280">
        <template slot-scope="scope">
          <el-popover trigger="click" placement="top" width="400">
            <div style="max-height: 400px; overflow-y: auto">
              {{ scope.row.desc }}
            </div>
            <div slot="reference" class="text-more-box">
              {{ scope.row.desc }}
            </div>
          </el-popover>
        </template>
      </el-table-column>
      <el-table-column prop="type" label="缺陷分类" width="130">
        <template slot-scope="scope">
          <span>{{ scope.row.type | formatSingleInArray(type) }}</span>
        </template>
      </el-table-column>
      <el-table-column prop="error_type" label="缺陷类型" width="80">
        <template slot-scope="scope">
          <span>{{
            scope.row.error_type | formatSingleInArray(errorType)
          }}</span>
        </template>
      </el-table-column>
      <el-table-column key="status" label="状态" width="130">
        <template slot-scope="scope">
          <el-switch
            v-model="scope.row.status"
            active-color="#13ce66"
            :active-value="0"
            :inactive-value="1"
            @change="handleStatusChange(scope.row)"
          />
        </template>
      </el-table-column>
      <el-table-column prop="type" label="质控对象" width="130">
        <template slot-scope="scope">
          <span v-if="scope.row.ZKDX == 0">通用</span>
          <span v-if="scope.row.ZKDX == 1">临床</span>
          <span v-if="scope.row.ZKDX == 2">编码员</span>
        </template>
      </el-table-column>
      <el-table-column prop="type" label="质控分类" width="130">
        <template slot-scope="scope">
          <span v-if="scope.row.ZKFL == 0">通用</span>
          <span v-if="scope.row.ZKFL == 1">国考</span>
          <span v-if="scope.row.ZKFL == 2">卫统</span>
          <span v-if="scope.row.ZKFL == 3">医保</span>
        </template>
      </el-table-column>
      <el-table-column prop="type" label="运行节点" width="130">
        <template slot-scope="scope">
          <!-- <span>
            <el-tag
              style="max-width: 90px"
              :type="
                scope.row.node === '终末' || scope.row.node === '0'
                  ? 'danger'
                  : ''
              "
            >
              {{ changeNodeStr(scope.row.node) }}
            </el-tag>
          </span> -->
          <span>
            <el-tag
              style="max-width: 90px"
            >
              {{ changeNodeStr(scope.row.node) }}
            </el-tag>
          </span>
        </template>
      </el-table-column>
      <el-table-column key="bmy" label="编码员" width="160">
        <template slot-scope="scope">
          <el-radio-group
            v-model="scope.row.bmy_level"
            fill="#13CE66"
            @change="handleBmyChange(scope.row)"
          >
            <el-radio-button
              v-for="item in levelArr"
              :key="`${scope.$index}_${item.id}`"
              :label="item.id"
              size="mini"
            >{{ item.name }}</el-radio-button>
          </el-radio-group>
        </template>
      </el-table-column>
      <el-table-column
        header-align="center"
        align="center"
        label="操作"
        width="80"
      >
        <template slot-scope="scope">
          <div>
            <el-popover
              placement="right"
              trigger="hover"
              popper-class="opera-popper"
            >
              <div>
                <el-button
                  v-if="checkPermission(['admin/admin/editErrorRule'])"
                  type="primary"
                  icon="el-icon-edit"
                  size="mini"
                  circle
                  @click="handleUpdate(scope.row)"
                />
                <el-button
                  v-if="checkPermission(['admin/admin/delErrorRule'])"
                  type="danger"
                  icon="el-icon-delete"
                  size="mini"
                  circle
                  @click="handleDelete(scope.row, scope.$index)"
                />
              </div>
              <i
                slot="reference"
                class="el-icon-more my-vertical-more"
                style="display: inline-block"
              />
            </el-popover>
          </div>
        </template>
      </el-table-column>
      <el-table-column label="备注" width="160">
        <template slot-scope="scope">
          <el-popover trigger="click" placement="top" width="400">
            <div style="max-height: 400px; overflow-y: auto">
              {{ scope.row.BZ }}
            </div>
            <div slot="reference" class="text-more-box">
              {{ scope.row.BZ }}
            </div>
          </el-popover>
        </template>
      </el-table-column>
    </el-table>
    <pagination
      :auto-scroll="false"
      :total="listCount"
      :page="queryParams.page"
      :limit="queryParams.limit"
      @pagination="handlePagination"
    />
    <!-- 添加或修改对话框 -->
    <el-dialog
      :title="title"
      :visible.sync="open"
      :close-on-click-modal="false"
      width="60%"
      top="8vh"
      append-to-body
    >
      <el-form ref="form" :model="form" :rules="rules" label-width="80px">
        <el-row>
          <el-col :span="12">
            <el-form-item label="字段" prop="auth">
              <el-input
                v-model="form.auth"
                type="textarea"
                :autosize="{ minRows: 1, maxRows: 3 }"
                resize="none"
                placeholder="验证字段"
              />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="字段名称" prop="field">
              <el-input
                v-model="form.field"
                type="textarea"
                :autosize="{ minRows: 1, maxRows: 3 }"
                resize="none"
                placeholder="验证字段名称"
              />
            </el-form-item>
          </el-col>
        </el-row>
        <!-- <el-row>
          <el-col :span="12">
            <el-form-item label="关联字段" prop="relation">
              <el-input
                v-model="form.relation"
                type="textarea"
                :autosize="{ minRows: 1, maxRows: 3 }"
                resize="none"
                placeholder="关联字段"
              />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="关联规则" prop="relation_rule">
              <el-input
                v-model="form.relation_rule"
                type="textarea"
                :autosize="{ minRows: 1, maxRows: 3 }"
                resize="none"
                placeholder="关联规则"
              />
            </el-form-item>
          </el-col>
        </el-row> -->
        <el-row>
          <!-- <el-col :span="12">
            <el-form-item label="验证规则" prop="rule">
              <el-input
                v-model="form.rule"
                type="textarea"
                :autosize="{ minRows: 1, maxRows: 3 }"
                resize="none"
                placeholder="验证规则"
              />
            </el-form-item>
          </el-col> -->
          <el-col :span="12">
            <el-form-item label="规则描述" prop="desc">
              <el-input
                v-model="form.desc"
                type="textarea"
                :autosize="{ minRows: 1, maxRows: 3 }"
                resize="none"
                placeholder="规则描述"
              />
            </el-form-item>
          </el-col>
        </el-row>
        <el-row>
          <el-col :span="12">
            <el-form-item label="错误类型" prop="type">
              <el-select
                v-model="form.category"
                placeholder="错误类型"
                style="width: 100%"
              >
                <el-option
                  v-for="item in category"
                  :key="item.id"
                  :label="item.name"
                  :value="item.id"
                />
              </el-select>
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="错误等级" prop="level">
              <el-select
                v-model="form.level"
                placeholder="错误等级"
                style="width: 100%"
              >
                <el-option
                  v-for="item in level"
                  :key="item.id"
                  :label="item.name"
                  :value="item.id"
                />
              </el-select>
            </el-form-item>
          </el-col>
        </el-row>
        <el-row>
          <el-col :span="12">
            <el-form-item label="缺陷类型" prop="type">
              <el-select
                v-model="form.error_type"
                placeholder="缺陷类型"
                style="width: 100%"
              >
                <el-option
                  v-for="item in errorType"
                  :key="item.id"
                  :label="item.name"
                  :value="item.id"
                />
              </el-select>
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="缺陷分类" prop="type">
              <el-select
                v-model="form.type"
                placeholder="缺陷分类"
                style="width: 100%"
              >
                <el-option
                  v-for="item in type"
                  :key="item.id"
                  :label="item.name"
                  :value="item.id"
                />
              </el-select>
            </el-form-item>
          </el-col>
        </el-row>
        <el-row>
          <el-col :span="12">
            <el-form-item label="扣分" prop="down">
              <el-input v-model="form.down" placeholder="扣分" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="状态" prop="status">
              <el-radio-group v-model="form.status">
                <el-radio
                  v-for="item in statusArr"
                  :key="item.id"
                  :label="item.id"
                >{{ item.name }}</el-radio>
              </el-radio-group>
            </el-form-item>
          </el-col>
        </el-row>

        <el-row>
          <el-col :span="12">
            <el-form-item label="质控对象" prop="type">
              <el-select
                v-model="form.ZKDX"
                placeholder="质控对象"
                style="width: 100%"
              >
                <el-option
                  v-for="item in zkdxType"
                  :key="item.id"
                  :label="item.name"
                  :value="item.id"
                />
              </el-select>
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="质控分类" prop="type">
              <el-select
                v-model="form.ZKFL"
                placeholder="质控分类"
                style="width: 100%"
              >
                <el-option
                  v-for="item in zkflType"
                  :key="item.id"
                  :label="item.name"
                  :value="item.id"
                />
              </el-select>
            </el-form-item>
          </el-col>
        </el-row>
        <el-row>
          <el-col :span="12">
            <el-form-item label="运行节点" prop="node">
              <el-select
                v-model="form.node"
                placeholder="运行节点"
                style="width: 100%"
                multiple
                filterable
                clearable
              >
                <el-option
                  v-for="item in node_options"
                  :key="item.id"
                  :label="item.name"
                  :value="item.id"
                />
              </el-select>
            </el-form-item>
          </el-col>

          <el-col :span="12">
            <el-form-item label="编码员" prop="status">
              <el-radio-group v-model="form.bmy_level">
                <el-radio-button
                  v-for="item in levelArr"
                  :key="item.id"
                  :label="item.id"
                  size="mini"
                >{{ item.name }}</el-radio-button>
              </el-radio-group>
            </el-form-item>
          </el-col>
        </el-row>
        <el-row>
          <el-col :span="12">
            <el-form-item label="备注" prop="BZ">
              <el-input
                v-model="form.BZ"
                type="textarea"
                :rows="4"
                placeholder="备注"
              />
            </el-form-item>
          </el-col>
        </el-row>
      </el-form>
      <div slot="footer" class="dialog-footer">
        <el-button type="primary" @click="submitForm">确 定</el-button>
        <el-button @click="cancel">取 消</el-button>
      </div>
    </el-dialog>
    <!--标记-->
    <el-dialog title="提示" :visible.sync="addVisible" width="30%">
      <span>添加规则</span>
      <el-form>
        <el-row>
          <span>验证字段:</span>
          <el-input v-model="form.auth" placeholder="验证字段" />
        </el-row>
        <el-row>
          <span>验证字段名称:</span>
          <el-input v-model="form.field" placeholder="验证字段名称" />
        </el-row>
        <!-- <el-row>
          <span>验证规则:</span>
          <el-input v-model="form.rule" placeholder="验证规则" />
        </el-row> -->
        <!-- <el-row>
          <span>关联字段:</span>
          <el-input v-model="form.relation" placeholder="关联字段" />
        </el-row>
        <el-row>
          <span>关联规则:</span>
          <el-input v-model="form.relation_rule" placeholder="关联规则" />
        </el-row> -->
        <el-row>
          <span>规则描述:</span>
          <el-input v-model="form.desc" placeholder="规则描述" />
        </el-row>
        <el-row>
          <span>扣分:</span>
          <el-input v-model="form.down" placeholder="扣分" />
        </el-row>
        <el-row>
          <span>错误等级:</span>
          <el-select v-model="form.level">
            <el-option :value="0" label="强制" />
            <el-option :value="1" label="建议" />
          </el-select>
        </el-row>
        <el-row>
          <span>缺陷分类:</span>
          <el-select v-model="form.type">
            <el-option :value="0" label="患者基本信息" />
            <el-option :value="1" label="诊疗信息" />
            <el-option :value="2" label="费用信息" />
          </el-select>
        </el-row>
        <el-row>
          <span>缺陷类型:</span>
          <el-select v-model="form.error_type">
            <el-option :value="0" label="逻辑性" />
            <el-option :value="1" label="规范性" />
            <el-option :value="2" label="编码" />
          </el-select>
        </el-row>
        <el-row>
          <span>错误类型:</span>
          <el-select v-model="form.category">
            <el-option :value="0" label="A类" />
            <el-option :value="1" label="B类" />
            <el-option :value="2" label="C类" />
            <el-option :value="3" label="D类" />
          </el-select>
        </el-row>
      </el-form>
      <span slot="footer" class="dialog-footer">
        <el-button @click="addVisible = false">取 消</el-button>
        <el-button type="primary" @click="addReason">确 定</el-button>
      </span>
    </el-dialog>
    <el-dialog title="提示" :visible.sync="saveVisible" width="30%">
      <span>修改规则</span>
      <el-form>
        <el-row>
          <span>验证字段:</span>
          <el-input v-model="row.auth" placeholder="验证字段" />
        </el-row>
        <el-row>
          <span>验证字段名称:</span>
          <el-input v-model="row.field" placeholder="验证字段名称" />
        </el-row>
        <!-- <el-row>
          <span>验证规则:</span>
          <el-input v-model="row.rule" placeholder="验证规则" />
        </el-row> -->
        <!-- <el-row>
          <span>关联字段:</span>
          <el-input v-model="row.relation" placeholder="关联字段" />
        </el-row>
        <el-row>
          <span>关联规则:</span>
          <el-input v-model="row.relation_rule" placeholder="关联规则" />
        </el-row> -->
        <el-row>
          <span>规则描述:</span>
          <el-input v-model="row.desc" placeholder="规则描述" />
        </el-row>
        <el-row>
          <span>扣分:</span>
          <el-input v-model="row.down" placeholder="扣分" />
        </el-row>
        <el-row>
          <span>错误等级:</span>
          <el-select v-model="row.level">
            <el-option :value="0" label="强制" />
            <el-option :value="1" label="建议" />
          </el-select>
        </el-row>
        <el-row>
          <span>缺陷分类:</span>
          <el-select v-model="row.type">
            <el-option :value="0" label="患者基本信息" />
            <el-option :value="1" label="诊疗信息" />
            <el-option :value="2" label="费用信息" />
          </el-select>
        </el-row>
        <el-row>
          <span>缺陷类型:</span>
          <el-select v-model="row.error_type">
            <el-option :value="0" label="逻辑性" />
            <el-option :value="1" label="规范性" />
            <el-option :value="2" label="编码" />
          </el-select>
        </el-row>
        <el-row>
          <span>错误类型:</span>
          <el-select v-model="row.category">
            <el-option
              v-for="item in category"
              :key="item.id"
              :label="item.label"
              :value="item.id"
            />
          </el-select>
        </el-row>
      </el-form>
      <span slot="footer" class="dialog-footer">
        <el-button @click="saveVisible = false">取 消</el-button>
        <el-button type="primary" @click="saveReason">确 定</el-button>
      </span>
    </el-dialog>
  </div>
</template>

<script>
import {
  addConfig,
  configList,
  delConfig,
  saveConfig,
  updateStatus,
  updateBmyLevel
} from '@/api/config'
import { scrollTo } from '@/utils/scroll-to'

export default {
  data() {
    return {
      listCount: 0,
      pageList: [],
      listLoading: false,
      showSearch: false,
      search: true,
      queryParams: {
        page: 1,
        limit: 10,
        auth: undefined,
        field: undefined,
        desc: undefined,
        level: undefined,
        category: undefined,
        type: undefined,
        errorType: undefined,
        node_options: undefined,
        status: undefined,
        zkdxType: undefined,
        zkflType: undefined,
        node: undefined,
        bmy_level: ''
      },
      dialogStatus: '',
      textMap: {
        update: '编辑规则',
        create: '创建规则'
      },
      category: [
        { id: 0, name: 'A类' },
        { id: 1, name: 'B类' },
        { id: 2, name: 'C类' },
        { id: 3, name: 'D类' }
      ],
      level: [
        { id: 0, name: '强制' },
        { id: 1, name: '建议' }
      ],
      type: [
        { id: 0, name: '患者基本信息' },
        { id: 1, name: '诊疗信息' },
        { id: 2, name: '费用信息' }
      ],
      errorType: [
        { id: 0, name: '逻辑性' },
        { id: 1, name: '规范性' },
        { id: 2, name: '编码' }
      ],
      statusArr: [
        { id: 0, name: '启用' },
        { id: 1, name: '停用' }
      ],
      levelArr: [
        { id: 0, name: '强制' },
        { id: 1, name: '建议' }
      ],
      zkdxType: [
        { id: 0, name: '通用' },
        { id: 1, name: '临床' },
        { id: 2, name: '编码员' }
      ],
      zkflType: [
        { id: 0, name: '通用' },
        { id: 1, name: '国考' },
        { id: 2, name: '卫统' },
        { id: 3, name: '医保' }
      ],
      node_options: [
        { id: '0', name: '终末' },
        { id: '1', name: '运行' }
      ],
      title: '',
      open: false,
      form: {},
      rules: {
        // fee_name: [
        //   { required: true, message: '项目名称不能为空', trigger: 'blur' }
        // ],
        // operation_name: [
        //   { required: true, message: '手术名称不能为空', trigger: 'blur' }
        // ],
        // code: [
        //   { required: true, message: '手术代码不能为空', trigger: 'blur' }
        // ]
      },
      // 标记
      saveVisible: false,
      addVisible: false,
      row: {}
    }
  },
  created() {
    this.getList()
  },
  methods: {
    changeNodeStr(nodeVal) {
      if (!nodeVal) return ''
      return nodeVal
        .split('、')
        .map((item) => {
          return item === '0' ? '终末' : '运行'
        })
        .join('、')
    },
    // 搜索
    toggleSearch() {
      this.showSearch = !this.showSearch
    },
    handleRefresh() {
      this.getList()
    },
    handleResetQuery() {
      this.queryParams = {
        page: 1,
        limit: 10,
        auth: undefined,
        field: undefined,
        desc: undefined,
        level: undefined,
        category: undefined,
        type: undefined,
        errorType: undefined,
        node_options: undefined,
        status: undefined,
        bmy_level: ''
      }
      this.getList()
    },
    handleQuery() {
      this.queryParams.page = 1
      this.getList()
    },
    handlePagination(param) {
      this.queryParams.page = param.page
      this.queryParams.limit = param.limit
      this.getList()
    },
    getList() {
      this.listLoading = true
      configList(this.queryParams)
        .then((res) => {
          this.pageList = res.p.list
          this.listCount = res.p.count
          this.listLoading = false
          scrollTo(300)
        })
        .catch((error) => {
          console.log(error)
        })
    },
    cancel() {
      this.open = false
      this.reset()
    },
    reset() {
      this.form = {
        id: undefined,
        auth: undefined,
        field: undefined,
        // rule: undefined,
        category: 0,
        error_type: 0,
        type: 0,
        node_options: 0,
        level: 0,
        down: 0,
        desc: undefined,
        // relation_rule: undefined,
        // relation: undefined,
        status: 0,
        zkdxType: 0,
        zkflType: 0,
        node: undefined,
        BZ: undefined,
        bmy_level: ''
      }
      this.resetForm('form')
    },
    handleAdd() {
      this.reset()
      this.open = true
      this.title = '添加基础规则'
    },
    handleUpdate(row) {
      console.log('handleUpdate', row)
      this.reset()

      const temp = Object.assign({}, row)
      this.form = temp
      this.form.node = temp.node.split('、')
      this.form.status = parseInt(temp.status)
      this.form.bmy_level = parseInt(temp.bmy_level)
      this.open = true
      this.title = '修改基础规则'
    },
    submitForm: function() {
      this.form.node = this.form.node.join('、')
      this.$refs['form'].validate((valid) => {
        if (valid) {
          if (this.form.id !== undefined) {
            saveConfig(this.form).then((res) => {
              this.$message.success(res.m || '操作成功')
              this.open = false
              this.getList()
            })
          } else {
            addConfig(this.form).then((res) => {
              this.$message.success(res.m || '操作成功')
              this.open = false
              this.getList()
            })
          }
        }
      })
    },
    handleStatusChange(row) {
      const statusIndex = this.statusArr.findIndex(
        (value) => parseInt(value.id) === parseInt(row.status)
      )
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
          updateStatus({ id: row.id, status: row.status })
            .then((res) => {
              this.$message.success(res.m || '操作成功')
            })
            .catch(function() {
              row.status = row.status === 0 ? 1 : 0
            })
        })
        .catch(function() {
          row.status = row.status === 0 ? 1 : 0
        })
    },
    handleBmyChange(row) {
      const statusIndex = this.levelArr.findIndex(
        (value) => parseInt(value.id) === parseInt(row.bmy_level)
      )
      this.$confirm(
        '确认要更改为 <strong>' +
          this.levelArr[statusIndex].name +
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
          updateBmyLevel({ id: row.id, status: row.bmy_level })
            .then((res) => {
              this.$message.success(res.m || '操作成功')
            })
            .catch(function() {
              row.bmy_level = row.bmy_level === 0 ? 1 : 0
            })
        })
        .catch(function() {
          row.bmy_level = row.bmy_level === 0 ? 1 : 0
        })
    },
    handleDelete(row, index) {
      const indexNum = index + 1
      this.$confirm(
        '是否确认删除第 <strong>' + indexNum + '</strong> 行的数据项？',
        '提示',
        {
          dangerouslyUseHTMLString: true,
          confirmButtonText: '确定',
          cancelButtonText: '取消',
          type: 'warning'
        }
      )
        .then(() => {
          delConfig({ id: row.id }).then((res) => {
            this.$message.success(res.m || '操作成功')
            this.getList()
          })
        })
        .catch(function() {})
    },
    // 标记
    list() {
      configList(this.form).then((res) => {
        this.pageList = res.p.list
      })
    },
    del(id) {
      delConfig({ config_id: id }).then((res) => {
        this.$message.success(res.m || 'ok')
        this.list()
      })
    },
    addReason() {
      addConfig(this.form).then((res) => {
        this.$message.success(res.m || 'ok')
        this.addVisible = false
        this.list()
      })
    },
    saveReason() {
      saveConfig(this.row).then((res) => {
        this.$message.success(res.m || 'ok')
        this.saveVisible = false
        this.list()
      })
    }
  }
}
</script>

<style lang="scss" scoped>
::v-deep .head-query-form {
  .el-form-item {
    .el-form-item__label {
      font-weight: 400 !important;
    }
  }
}
</style>
