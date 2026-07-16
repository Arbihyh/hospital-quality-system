<template>
    <el-collapse class="detailBox" v-model="activeNames" @change="handleChange">
        <el-collapse-item name="-1">
            <template slot="title">
                <el-row type="flex" align="middle">病案首页（<div class="blue-link" @click.stop="toPage({bllb: '-1'})">1</div>）</el-row>
            </template>
            <p class="blue-link" @click="toPage({bllb: '-1'})">
                病案首页
            </p>
        </el-collapse-item>
        <el-collapse-item v-for="(item, key) in dataSource" :title="item.name" :name="item.name" :key="key">
            <template slot="title">
                <el-row type="flex" align="middle">{{item.name}}（<div class="blue-link" @click.stop="toPage(item, {}, key)">{{Array.isArray(item.list) ? item.list.length : item.bllb == '49' ? 2 : 1}}</div>）</el-row>
            </template>
            <div v-if="item.list && Array.isArray(item.list) && !!(item.list.length)">
                <p v-for="(element) in item.list" class="blue-link" @click="toPage(element, item, key)">
                    {{element.name}}
                </p>
            </div>
            <div v-else-if="item.bllb == '49'">
                <p class="blue-link" @click="toPage({name: '长期医嘱'}, item, key)">长期医嘱</p>
                <p class="blue-link" @click="toPage({name: '临时医嘱'}, item, key)">临时医嘱</p>
            </div>
            <div v-else>
                <p class="blue-link" @click="toPage(item, item, key)">{{item.name}}</p>
            </div>
        </el-collapse-item>
    </el-collapse>
</template>
<script>
export default {
    props: ['dataSource', 'currentRow'],
    data() {
        return {
            activeNames: ['-1']
        }
    },
    methods: {
      handleChange(val) {
        console.log(val);
      },
      toPage(element, item, key) {
        console.log("==========toPage======>",element, item, key);
        let path = ''
        if(element.name == '长期医嘱' || element.name == '临时医嘱') {
            path = `bllb=${item.bllb}&currentKey=${key}&specialName=${element.name}`
        }else if(element.bllb) {
            path = `bllb=${element.bllb}&currentKey=${key}`
        }else {
            path =`bllb=${item.bllb}&currentKey=${key}&blbh=${element.blbh}`
        }
        this.$router.push(`/caseViews?from=expertQualityControl&ZYH=${this.$props.currentRow.ZYH}&${path}`)
      },
       // 处理dataSource并更新activeNames
      updateActiveNames() {
        if (this.dataSource && typeof this.dataSource === 'object') {
          const names = Object.values(this.dataSource).map(item => item.name);
          this.activeNames = ['-1', ...names];
        }
      }
    },
    watch: {

        dataSource: {
            handler (newVal) {
                this.updateActiveNames();
            },
            deep: true, // 启用深度监听
            immediate: true // 初始化时立即执行
        }
        // dataSource: {
        //     handler (newVal) {
        //         let dataSourceArray = Object.keys(newVal)
        //         if(!!dataSourceArray.length) {
        //             this.activeNames = newVal[dataSourceArray[0]].name
        //         }else{
        //             this.activeNames = ''
        //         }
        //     },
        //     deep: true // 启用深度监听
        // }
    },
      mounted() {
        // 组件挂载时初始化
        this.updateActiveNames();
    }
}
</script>
<style lang="scss" scoped>
</style>