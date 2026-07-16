<template>
  <el-dialog :visible.sync="dialogVisible" class="custom-dialog" :modal="false" :title="getDialogTitle()" width="600px"
    @close="onCancel">
    <el-form :model="formData" :rules="rules" ref="formDataRef" label-width="0px">
      <el-form-item label="" prop="title">
        <el-input v-model="formData.title" clearable placeholder="请输入需查询的条件名称">
          <el-button slot="suffix" class="el-icon-search el-input__icon" @click="getListData" type="text" />
        </el-input>
      </el-form-item>
      <el-form-item label="" prop="is_public">
        <el-tabs v-model="formData.is_public" @tab-click="getListData">
          <el-tab-pane label="个人" name="2" />
          <el-tab-pane label="公共" name="1" />
        </el-tabs>
      </el-form-item>
    </el-form>
    <div v-loading="loading" style="min-height: 335px;">
      <div v-if="Array.isArray(listData) && !!listData.length">
        <div v-for="(item, index) in listData">
          <el-row :gutter="24" type="flex" align="middle">
            <el-col :span="16" style="display: flex">
              <h5>条件{{ index + 1 }}：</h5>
              <span>{{ item.title }}</span>
            </el-col>
            <el-col :span="8" style="text-align: right">
              <!-- <el-radio v-model="item.is_default" :label="1" disabled>默认</el-radio> -->
              <el-tag size="small" type="success" v-if="item.is_default == 1" style="margin-right:10px">默认</el-tag>
              <el-button @click="onUse(item)" type="text">引用</el-button>
              <el-button class="el-icon-edit-outline" @click="openCollectModal(item)" type="text" />
              <el-button class="el-icon-delete" @click="onRemove(item)" type="text" style="color: #ef1f3a" />
            </el-col>
          </el-row>
          <el-divider />
        </div>
      </div>
      <div v-else>
        <el-empty :image-size="200"></el-empty>
      </div>
    </div>
    <template #footer>
      <!-- <el-button type="primary" @click="onSubmit()">保存 </el-button>
            <el-button @click="onCancel()">取消 </el-button> -->
    </template>
    <CollectModalBox ref="CollectModalBoxRef" @onUpdate="getListData" />
  </el-dialog>
</template>
<script>
import CollectModalBox from './CollectModal.vue'
import { getCollectSearchList, deleteCollectSearch } from '@/api/qc';
export default {
  components: {
    CollectModalBox,
  },
  data() {
    return {
      dialogVisible: false,
      formData: {
        title: '',
        is_public: '1',
      },
      rules: {},
      listData: [],
      loading: false
    };
  },
  computed: {},
  watch: {},
  created() { },
  mounted() { },
  beforeDestroy() { },

  methods: {
    getDialogTitle() {
      return '常用收藏条件'
    },

    async openModal(params) {
      this.dialogVisible = true;
      this.getListData()
    },

    getListData() {
      this.loading = true
      getCollectSearchList({
        ...this.formData,
      }).then(res => {
          this.listData = res.data || []
      }).catch(error => {
          console.log(error)
      }).finally(() => {
          this.loading = false
      })
    },

    openCollectModal(row) {
      this.$refs.CollectModalBoxRef.openModal('EDIT', row);
    },

    onUse(row) {
      this.$parent.formData = {...this.$parent.formData, ...JSON.parse(row.filter_content)};
      this.$parent.onSubmit();
      this.onCancel();
    },

    onRemove(row) {
      this.$confirm('确定要删除吗?', '提示', {
        confirmButtonText: '确定',
        cancelButtonText: '取消',
        type: 'warning'
      }).then(() => {
        deleteCollectSearch({
          id: row.id
        }).then(res => {
          if(res.code == 200) {
            this.$message({
              type: 'success',
              message: '删除成功!'
            });
            this.getListData()
          }
        })
      }).catch(() => {
      });
    },

    onSubmit() {
      this.$refs.formDataRef.validate((valid) => {
        if (valid) {
        } else {
          console.log('error submit!!');
          return false;
        }
      });

    },
    onCancel() {
      this.dialogVisible = false;
      this.$refs.formDataRef && this.$refs.formDataRef.resetFields()
    }
  },
};
</script>

<style lang="scss" scoped>
::v-deep .el-dialog__header {
  background-color: hsl(205.32deg 43.43% 49.22%);
}

::v-deep .el-dialog__close {
  color: #fff;
  border: 1px solid #fff;
  border-radius: 20px;
}

::v-deep .el-dialog__title {
  color: #fff;
}

::v-deep .el-divider--horizontal {
  margin: 5px 0
}
</style>
