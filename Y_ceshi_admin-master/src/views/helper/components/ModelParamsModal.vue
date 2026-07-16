<template>
  <div>
    <el-dialog :modal="false" width="700px" :title="`模型入参`" :visible.sync="dialogFormVisible" @close="handleCancel">
      <el-row style="margin-bottom: 10px;" type="flex" justify="space-between">
        <el-input v-model="filterParams.keyword" clearable placeholder="请输入内容" @input="handleSearch">
          <i
            slot="prefix"
            class="el-icon-search el-input__icon"
          />
        </el-input>
        <el-button style="margin-left: 20px" type="primary" @click="handleSubmit">确认</el-button>
      </el-row>
      <el-tabs
        v-model="currentTab"
        type="border-card"
        class="tab"
        :tab-position="'left'"
        style="height:max-content"
        @tab-click="handleClickTab"
      >
        <el-tab-pane
          v-for="(item) in (listData)"
          :key="item.id"
          :label="item.field_name"
          :name="String(item.id)"
        >
          <el-row slot="label" type="flex" align="middle">{{ item.field_name }}<div
            v-if="getSelectedNumber(item)"
            class="tab-label-num"
          >{{ getSelectedNumber(item) }}</div></el-row>
          <div class="grid-container">
            <el-tag
              v-for="(tag) in item.child"
              :key="tag.id"
              size="large"
              :closable="getIsSelected(item, tag) ? true : false"
              :disable-transitions="false"
              :type="getIsSelected(item, tag) ? 'primary' : 'info'"
              @click="handleTabItemClick(item, tag)"
              @close="handleTabItemClose(item, tag)"
            >
              {{ tag.field_name }}
            </el-tag>
          </div>
        </el-tab-pane>
      </el-tabs>
      <!-- <div slot="footer" class="dialog-footer">
        <el-button type="primary" @click="handleSubmit">确认</el-button>
      </div> -->
    </el-dialog>
  </div>
</template>

<script>
// import { debounce } from 'lodash'
import { getModelParams } from '@/api/helper'
export default {
  emits: ['onSelect'],
  props: {
    selectDataList: {
      type: Array,
      default() {
        return []
      }
    }
  },
  data() {
    return {
      dialogFormVisible: false,
      currentTab: '1',
      filterParams: {
        keyword: ''
      },
      listData: [],
      selectList: [],
      filteredObjects: [],
      originalObjects: []
    }
  },
  created() {
    this.getListData()
  },
  methods: {
    openModal(params = []) {
      this.listData = [...this.originalObjects]
      this.dialogFormVisible = true
      this.$nextTick(() => {
        this.selectList = [...params]
      })
    },
    handleClickTab(tab) {
      this.currentTab = tab.name
    },
    handleTabItemClick(parent, current) {
      const findIndexValue = this.selectList.findIndex(element => element.id === parent.id)
      if (findIndexValue === -1) {
        this.selectList.push({
          ...parent,
          child: [{
            ...current
          }]
        })
      } else {
        const childArray = this.selectList[findIndexValue].child
        const findChildIndexValue = childArray.findIndex(item => item.id === current.id)
        if (findChildIndexValue === -1) {
          this.selectList[findIndexValue].child.push({
            ...current
          })
        } else {
          this.selectList[findIndexValue].child.splice(findChildIndexValue, 1)
        }
      }
    },
    handleTabItemClose(parent, current) {
      const findIndexValue = this.selectList.findIndex(element => element.id === parent.id)
      const childArray = this.selectList[findIndexValue].child
      const findChildIndexValue = childArray.findIndex(item => item.id === current.id)
      this.selectList[findIndexValue].child.splice(findChildIndexValue, 1)
    },
    getIsSelected(parent, current) {
      const findIndexValue = this.selectList.findIndex(element => element.id === parent.id)
      if (findIndexValue === -1) {
        return false
      } else {
        const childArray = this.selectList[findIndexValue].child
        const findChildIndexValue = childArray.findIndex(item => item.id === current.id)
        if (findChildIndexValue === -1) {
          return false
        } else {
          return true
        }
      }
    },
    getSelectedNumber(parent) {
      const findIndexValue = this.selectList.findIndex(element => element.id === parent.id)
      if (findIndexValue === -1) {
        return 0
      } else {
        return this.selectList[findIndexValue].child.length
      }
    },
    getSelectList(params) {
      this.selectList = []
      if (Array.isArray(params) && !!params.length) {
        params.forEach(item => {
          const findIndexValue = this.listData.findIndex(element => element.field === item.table)
          if (findIndexValue !== -1) {
            const childData = []
            if (Array.isArray(item.field) && !!item.field.length) {
              const childArray = this.listData[findIndexValue].child
              item.field.forEach(item => {
                const findChildIndexValue = childArray.findIndex(element => element.field === item)
                if (findChildIndexValue !== -1) {
                  childData.push(childArray[findChildIndexValue])
                }
              })
            }
            this.selectList.push({
              ...this.listData[findIndexValue],
              child: childData
            })
          }
        })
        this.$emit('onSelect', this.selectList)
      }
    },
    getListData() {
      // getModelParams(this.filterParams).then((res) => {
      //   const { p } = res
      //   this.listData = p || []
      //   if (Array.isArray(this.listData) && !!this.listData.length) {
      //     this.currentTab = String(this.listData[0].id)
      //   }
      // })
      getModelParams({ 'keyword': '' }).then((res) => {
        const { p } = res
        this.listData = p || []
        if (Array.isArray(this.listData) && !!this.listData.length) {
          this.currentTab = String(this.listData[0].id)
          this.originalObjects = [...this.listData]
        }
      })
    },
    // handleSearch: debounce(function() {
    //   this.getListData()
    // }, 2000),

    handleSearch() {
      const keyword = this.filterParams.keyword.trim().toLowerCase()

      if (!keyword) {
        this.listData = [...this.originalObjects]
        if (this.listData.length > 0) {
          this.currentTab = this.listData[0].field
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
            ? parent.child.filter(child =>
              child.field_name.toLowerCase().includes(keyword)
            )
            : matchedChildren

          if (filteredChild.length > 0) {
            result.push({
              ...parent,
              child: filteredChild
            })
          }
        }

        return result
      }, [])

      this.listData = [...this.filteredObjects]

      if (this.listData.length > 0) {
        this.$nextTick(() => {
          this.currentTab = String(this.listData[0].id)
        })
      } else {
        this.currentTab = ''
      }
    },
    handleSubmit() {
      this.dialogFormVisible = false
      this.$emit('onSelect', this.selectList)
    },
    handleCancel() {
      this.dialogFormVisible = false
      this.filterParams.keyword = ''
    }
  }
}
</script>

<style lang="scss" scoped>
.grid-container {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 10px;
    .el-tag {
        text-align: center;
    }
}

.tab-label-num {
    width: 16px;
    height: 16px;
    line-height: 16px;
    text-align: center;
    border-radius: 50%;
    background-color: red;
    color: #fff;
    font-size: 14px;
    margin-left: 5px;
}

::v-deep.el-tabs__nav-prev {
    display: none;
    height: 0;
}

::v-deep.el-tabs__nav-next {
    display: none;
    height: 0;
}

.tab {
    ::v-deep .el-tabs__nav-scroll {
        overflow: hidden !important;
        /* 隐藏滚动条 */
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
