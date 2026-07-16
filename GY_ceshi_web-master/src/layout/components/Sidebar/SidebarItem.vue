<template>
  <div>
    <el-menu-item v-if="!item.children || (Array.isArray(item.children) && item.children.length === 1 && item.children[0].path == '')" :index="resolvePath(item.path)" :class="{ 'submenu-title-noDropdown': !isNest }"
      >
      <item :icon="item.meta.icon || (item.meta && item.meta.icon)" :title="item.meta.title" />
    </el-menu-item>
    <!-- 一级菜单（有子菜单）-->
    <el-submenu :index="item.path" v-else>
      <template slot="title">
        <item v-if="item.meta" :icon="item.meta && item.meta.icon" :title="item.meta.title" />
      </template>
      <!-- 遍历二级菜单 -->
      <div v-for="(i, index) in item.children" :key="index">
        <!-- 二级菜单（没有三级菜单）-->
        <el-menu-item :index="resolvePath(i.path)" v-if="!i.children">
          <item :icon="i.meta.icon || (i.meta && i.meta.icon)" :title="i.meta.title" />
        </el-menu-item>
        <!-- 二级菜单（有三级菜单）-->
        <el-submenu :index="resolvePath(i.path)" v-if="i.children">
          <template slot="title">
            <item v-if="i.meta" :icon="i.meta && i.meta.icon" :title="i.meta.title" />
          </template>
          <el-menu-item :index="resolvePath(j.path)" v-for="(j, index) in i.children" :key="index">
            <item :icon="j.meta.icon || (j.meta && j.meta.icon)" :title="j.meta.title" />
          </el-menu-item>
        </el-submenu>
      </div>
    </el-submenu>
  </div>
</template>

<script>
import path from 'path';
import { isExternal } from '@/utils/validate';
import Item from './Item';
import AppLink from './Link';
import FixiOSBug from './FixiOSBug';

export default {
  name: 'SidebarItem',
  components: { Item, AppLink },
  mixins: [FixiOSBug],
  props: {
    // route object
    item: {
      // type: Object,  // 添加动态路由 需要注释掉，不然会报错
      required: true,
    },
    isNest: {
      type: Boolean,
      default: false,
    },
    basePath: {
      type: String,
      default: '',
    },
  },
  data() {
    // To fix https://github.com/PanJiaChen/vue-admin-template/issues/237
    // TODO: refactor with render function
    this.onlyOneChild = null;
    return {};
  },
  methods: {
    onMenuClick(menu) {
      // 判断目录是否为质控
      const { title } = menu.meta
      if (title.includes('质控') || title.includes('首页数据分析') || title.includes('预警')) {
        localStorage.setItem('isControl', true)
      } else {
        localStorage.setItem('isControl', false)
      }
    },
    hasOneShowingChild(children = [], parent) {
      const showingChildren = children.filter(item => {
        // console.log(item.path)
        if (item.path == '/courseOfDisease' ||
            item.path == '/dischargerecord' ||
            item.path == '/hospitalized' ||
            item.path == '/operation' ||
            item.path == '/groupConsultation' ||
            item.path == '/ultrasonic' ||
            item.path == '/image' ||
            item.path == '/electrocardiogram' ||
            item.path == '/inspectionReport' ||
            item.path == '/medicalAdvice' ||
            item.path == '/medicalTemporary' ||
            item.path == '/imgsText'
            ) {
          console.log('_______');
          return false;
        }
        if (item.hidden) {
          return false;
        } else {
          // Temp set(will be used if only has one showing child)
          this.onlyOneChild = item;
          return true;
        }
      });

      // When there is only one child router, the child router is displayed by default
      if (showingChildren.length === 1) {
        return true;
      }

      // Show parent if there are no child router to display
      if (showingChildren.length === 0) {
        this.onlyOneChild = { ...parent, path: '', noShowingChildren: true };
        return true;
      }

      return false;
    },
    resolvePath(routePath) {
      if (isExternal(routePath)) {
        return routePath;
      }
      if (isExternal(this.basePath)) {
        return this.basePath;
      }
      return path.resolve(this.basePath, routePath);
    },
  },
};
</script>
<style scoped></style>
