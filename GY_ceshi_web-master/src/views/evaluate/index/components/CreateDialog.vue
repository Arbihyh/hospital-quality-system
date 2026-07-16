<template>
  <div>
    <el-dialog v-el-drag-dialog :title="titleStr" append-to-body :visible.sync="data.bSwitch" width="700px">
      <el-form :model="ruleForm" :rules="rules" ref="ruleForm" label-width="150px" class="demo-ruleForm">
        <el-form-item label="评审指标一级目录" prop="first">
          <el-select v-model="ruleForm.first" clearable filterable placeholder="请选择" @change="onFirstChange" style="width: 400px;">
            <el-option v-for="(item, index) of firsts" :key="'f' + index" :label="item.name" :value="item.id"></el-option>
          </el-select>
          <i class="el-icon-plus plus-btn" @click="plusMenu(-1, 1)"></i>
        </el-form-item>
        <el-form-item label="评审指标二级目录" prop="second">
          <el-select v-model="ruleForm.second" clearable filterable placeholder="请选择" @change="onSecondChange" style="width: 400px;">
            <el-option v-for="(item, index) of seconds" :key="'s' + index" :label="item.name" :value="item.id"></el-option>
          </el-select>
          <i class="el-icon-plus plus-btn" @click="plusMenu(ruleForm.first, 2)"></i>
        </el-form-item>
        <el-form-item label="评审指标三级目录" prop="third">
          <el-select v-model="ruleForm.third" clearable filterable placeholder="请选择" style="width: 400px;">
            <el-option v-for="(item, index) of thirds" :key="'t' + index" :label="item.name" :value="item.id"></el-option>
          </el-select>
          <i class="el-icon-plus plus-btn" @click="plusMenu(ruleForm.second, 3)"></i>
        </el-form-item>
        <el-form-item label="分子" prop="fenzi_name">
          <el-input v-model="ruleForm.fenzi_name" type="textarea" autosize placeholder="请输入分子" style="width: 400px; vertical-align: top;"></el-input>
        </el-form-item>
        <el-form-item label="分子口径" >
          <div v-for="(item, index) of ruleForm.fenzi" :key="index" style="margin-bottom: 10px;">
            <el-input v-model="item.name" type="textarea" autosize placeholder="请输入标题" style="width: 140px; margin-right: 10px; vertical-align: top;"></el-input>
            <el-input v-model="item.info" type="textarea" autosize placeholder="请输入指标口径" style="width: 250px; vertical-align: top;"></el-input>
            <i class="el-icon-plus plus-btn" @click="plusFenzi"></i>
            <i class="el-icon-minus plus-btn" v-if="index" @click="minusFenzi(index)"></i>
          </div>
        </el-form-item>
        <el-form-item label="分母" prop="fenmu_name">
          <el-input v-model="ruleForm.fenmu_name" type="textarea" autosize placeholder="请输入分母" style="width: 400px; vertical-align: top;"></el-input>
        </el-form-item>
        <el-form-item label="分母口径" >
          <div v-for="(item, index) of ruleForm.fenmu" :key="index" style="margin-bottom: 10px;">
            <el-input v-model="item.name" type="textarea" autosize placeholder="请输入标题" style="width: 140px; margin-right: 10px; vertical-align: top;"></el-input>
            <el-input v-model="item.info" type="textarea" autosize placeholder="请输入指标口径" style="width: 250px; vertical-align: top;"></el-input>
            <i class="el-icon-plus plus-btn" @click="plusFenmu"></i>
            <i class="el-icon-minus plus-btn" v-if="index" @click="minusFenmu(index)"></i>
          </div>
        </el-form-item>
      </el-form>
      <span slot="footer" class="dialog-footer">
        <el-button @click="data.bSwitch = false">取 消</el-button>
        <el-button type="primary" @click="onSubmit">确 定</el-button>
      </span>
    </el-dialog>
    <!-- 新增 -->
    <CreateMenuVue v-if="menuData.bSwitch" :data="menuData" @refresh="handleRefresh" />
  </div>
</template>

<script>
import CreateMenuVue from './CreateMenu.vue';
export default {
  components: {
    CreateMenuVue
  },
  props: {
    data: {
      type: Object,
      default() {
        return {
          bSwitch: false,
          row: {
            fenzi: [],
            fenmu: [],
          },
        };
      },
    },
  },
  data() {
    return {
      ruleForm: {
        first: '',
        second: '',
        third: '',
        fenzi_name: '',
        fenmu_name: '',
        fenzi: [
          {
            name: '',
            info: ''
          }
        ],
        fenmu: [
          {
            name: '',
            info: ''
          }
        ]
      },
      rules: {
        first: [
          { required: true, message: '请选择', trigger: 'change' }
        ],
        second: [
          { required: true, message: '请选择', trigger: 'change' }
        ]
      },
      firsts: [],
      seconds: [],
      thirds: [],
      menuData: {
        bSwitch: false,
        pid: 0,
        type: ''
      }
    };
  },
  computed: {
    titleStr() {
      return this.data.row.id ? '编辑' : '新增';
    },
  },
  async created() {
    await this.getFirsts()
    if (this.data.row.id) {
      await this.getInfo()
    }
  },
  methods: {
    getInfo() {
      this.$axios2.get(`/catalog_info?id=${this.data.row.id}&is_children=1&is_parent=1`).then(async res => {
        const { parent, id, fenzi, fenmu, fenzi_name, fenmu_name } = res.data
        if (parent.length === 1) {
          this.ruleForm.first = parent[0].id
          await this.onFirstChange()
          this.ruleForm.second = id
        }
        if (parent.length === 2) {
          this.ruleForm.first = parent[1].id
          await this.onFirstChange()
          this.ruleForm.second = parent[0].id
          await this.onSecondChange()
          this.ruleForm.third = id
        }
        this.$set(this.ruleForm, 'fenzi_name', fenzi_name)
        this.$set(this.ruleForm, 'fenmu_name', fenmu_name)
        if (fenmu) {
          this.$set(this.ruleForm, 'fenmu', JSON.parse(fenmu))
        }
        if (fenzi) {
          this.$set(this.ruleForm, 'fenzi', JSON.parse(fenzi))
        }
      });
    },
    getFirsts() {
      this.$axios2.get('/catalog_lists').then(res => {
        this.firsts = res.data;
      });
    },
    onFirstChange() {
      if (this.ruleForm.first) {
        this.$axios2.get(`/catalog_info?id=${this.ruleForm.first}&is_children=1`).then(res => {
          this.seconds = res.data.children ? res.data.children : [];
        });
      }
    },
    onSecondChange() {
      if (this.ruleForm.second) {
        this.$axios2.get(`/catalog_info?id=${this.ruleForm.second}&is_children=1`).then(res => {
          this.thirds = res.data.children ? res.data.children : [];
        });
      }
    },
    plusFenzi() {
      this.ruleForm.fenzi.push({
        name: '',
        info: ''
      })
    },
    minusFenzi(index) {
      this.ruleForm.fenzi.splice(index, 1)
    },
    plusFenmu() {
      this.ruleForm.fenmu.push({
        name: '',
        info: ''
      })
    },
    minusFenmu(index) {
      this.ruleForm.fenmu.splice(index, 1)
    },
    plusMenu(pid, type) {
      this.menuData.pid = pid < 0 ? 0 : pid
      this.menuData.type = type
      this.menuData.bSwitch = true
    },
    formatData(obj) {
      const arr = []
      for(let key in obj) {
        arr.push({
          id: key,
          name: obj[key]
        })
      }
      return arr
    },
    handleRefresh(val) {
      if (val == 1) {
        this.getFirsts()
      }
      if (val == 2) {
        this.onFirstChange()
      }
      if (val == 3) {
        this.onSecondChange()
      }
    },
    onSubmit() {
      this.$refs['ruleForm'].validate((valid) => {
        if (valid) {
          const { fenzi, fenmu, first, second, third, fenmu_name, fenzi_name } = this.ruleForm
          const params = {
            fenmu_name,
            fenzi_name
          }
          if (fenzi) {
            params.fenzi = JSON.stringify(fenzi)
          }
          if (fenmu) {
            params.fenmu = JSON.stringify(fenmu)
          }
          if (third) {
            params.name = this.thirds.filter(item => item.id === third)[0].name
            params.pid = second

          } else {
            params.name = this.seconds.filter(item => item.id === second)[0].name
            params.pid = first
          }
          if (this.data.row.id) {
            params.id = this.data.row.id
            this.editSubmit(params)
          } else {
            this.plusSubmit(params)
          }
        }
      });
    },
    plusSubmit(params) {
      this.$axios2.post('/catalog_add', params).then(res => {
        this.$emit('refresh')
        this.data.bSwitch = false
      });
    },
    editSubmit(params) {
      this.$axios2.post('/catalog_edit', params).then(res => {
        this.$emit('refresh')
        this.data.bSwitch = false
      });
    }
  }
};
</script>

<style lang="scss" scoped>
.plus-btn {
  margin-left: 20px;
  &:hover {
    opacity: 0.6;
    cursor: pointer;
  }
}
</style>