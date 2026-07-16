<template>
  <div>
    <el-form inline>
      <el-form-item>
        <el-select v-model="data" @change="selectChange">
          <el-option v-for="(v, k) in selectList" :key="k" :ref="v.route" :value="v.route" :data-obj="v" :label="v.route_name" />
        </el-select>
      </el-form-item>
      <el-form-item>
        <el-button v-if="data" type="primary" @click="selectChange(data)">查看配置</el-button>
        <el-button v-else type="primary" disabled>未选择路由</el-button>
      </el-form-item>
      <el-form-item>
        <p>{{ routeData }}</p>
      </el-form-item>
    </el-form>
    <el-dialog
      title="提示"
      :visible.sync="selectVisible"
      width="50%"
      :before-close="TOOLS.handleClose"
      :modal="false"
      @open="init(routeData)"
    >
      <el-form>
        <el-form-item v-for="(v, k) in paramList" :key="k" :label="`${v.desc}:`">
          <el-input v-model="paramData[v.key]" :placeholder="v.desc" @input="$forceUpdate()" />
        </el-form-item>
      </el-form>
      <span slot="footer" class="dialog-footer">
        <el-button @click="selectVisible = false;paramData={}">取 消</el-button>
        <el-button type="primary" @click="save">确 定</el-button>
      </span>
    </el-dialog>
  </div>
</template>
<script>
import { getRouteList } from '@/api/common'

export default {
  name: 'CheckRoute',
  props: {
    routeData: {
      type: String,
      default: ''
    }
  },
  data() {
    return {
      selectVisible: false,
      data: null,
      selectList: null,
      paramList: [],
      paramData: {},
      paramKeyList: {}
    }
  },
  watch: {
    routeData(res) {
      this.init(res)
    }
  },
  created() {
    this.list()
    this.init(this.routeData)
  },
  methods: {
    list() {
      getRouteList().then(res => {
        this.selectList = res.p.list
        this.selectList.forEach((v) => {
          this.paramKeyList[v.route] = v.param
        })
      })
    },
    init(res) {
      if (!res || res.length < 1) {
        return 0
      }
      let url = ''
      const data = res.replace('://', ':&').split(':&')
      this.paramData = {}
      data.forEach((v, k) => {
        if (parseInt(k) === 0) {
          url = `${v}://`
          return 0
        } else {
          const paramUrl = v.replace('?#', '-').replace('?', '&').split('&')
          paramUrl.forEach(kv => {
            const param = kv.split('=')
            this.paramData[param[0]] = param[1].replace('-', '?#')
          })
        }
      })
      this.data = url
    },
    save() {
      let url = this.data
      Object.keys(this.paramData).forEach(res => {
        if (this.paramData[res] && this.paramData[res].length > 0) {
          if (url === this.data) {
            url += `${res}=${this.paramData[res]}`
          } else {
            url += `&${res}=${this.paramData[res]}`
          }
        }
      })
      this.$emit('update:routeData', url)
      this.paramData = {}
      this.selectVisible = false
    },
    selectChange(res) {
      this.paramList = this.paramKeyList[res]
      const keyDesc = {}
      this.paramList.forEach((v, k) => {
        keyDesc[v['key']] = v['desc']
      })
      const data = this.paramData
      Object.keys(data).forEach((k, key) => {
        this.paramData[k] = `${k}` in data ? data[k] : ''
        this.paramList[key] = {
          desc: `${k}` in keyDesc ? keyDesc[k] : `其他(${k})`,
          value: data[k],
          key: k
        }
      })
      this.selectVisible = true
    }
  }
}
</script>

<style scoped>

</style>
