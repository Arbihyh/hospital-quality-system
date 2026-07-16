<template>
  <div>
    <el-dialog title="" :visible.sync="dialogVisible" width="60%" :show-close="false">
      <div slot="title" class="dialog-header">
        <div class="header-content">
          <span style="font-size: 16px; font-weight: bold">医疗疾病</span>
          <div style="display: flex; align-items: center">
            <span style="margin-right: 40px; cursor: pointer" @click="handleToggleMax">
              <svg t="1767243179456" class="icon" viewBox="0 0 1027 1024" version="1.1" xmlns="http://www.w3.org/2000/svg" p-id="24423" width="20" height="20">
                <path
                  d="M733.549304 0l116.434359 116.23452-226.402521 226.40252 57.053835 57.068109 226.459617-226.445342 120.616689 120.41685V0H733.549304zM689.513507 619.855586l-57.068108 57.068109 224.232847 224.232847-122.64362 122.843458h293.676657V729.838022l-114.007751 114.207588-224.190025-224.190024zM338.197775 404.144414l57.068109-57.068109L171.033037 122.843458 293.676657 0H0v294.161978l114.022025-114.207588 224.17575 224.190024zM347.076305 624.294851L120.616689 850.754468 0 730.323343v293.676657h294.161978l-116.420084-116.23452 226.40252-226.40252-57.068109-57.068109z"
                  p-id="24424"
                  fill="#fff"
                ></path>
              </svg>
            </span>
            <span style="cursor: pointer" @click="closeDialog">
              <svg t="1767243389682" class="icon" viewBox="0 0 1024 1024" version="1.1" xmlns="http://www.w3.org/2000/svg" p-id="26497" width="20" height="20">
                <path
                  d="M563.626667 512l155.306666 155.093333a36.693333 36.693333 0 0 1-51.84 51.84L512 563.626667l-155.093333 155.306666a36.693333 36.693333 0 0 1-51.84-51.84L460.373333 512l-155.306666-155.093333a36.693333 36.693333 0 0 1 51.84-51.84L512 460.373333l155.093333-155.306666a36.693333 36.693333 0 0 1 51.84 51.84z m-490.666667 0A438.826667 438.826667 0 1 0 512 73.173333 438.826667 438.826667 0 0 0 73.173333 512zM0 512a512 512 0 1 1 512 512A512 512 0 0 1 0 512z"
                  fill="#fff"
                  p-id="26498"
                ></path>
              </svg>
            </span>
          </div>
        </div>
      </div>
      <FullscreenContainer :showBtn="false" :showEscTip="true" ref="customMaxContainerRef">
        <div class="container-box">
          <div class="content-title">
            <H3Title :title="titleName" :fontSize="'18px'" :keyAlign="'center'"></H3Title>
            <div class="content-title-line">
              <!-- <i class="el-icon-share blue"></i>
              <span style="margin-left: 10px; color: #00000055; cursor: pointer">查看图谱</span> -->
            </div>
          </div>
          <div class="dialog-container">
            <div class="content" ref="contentRef">
              <div v-for="item in anchorList" :key="item.id" :id="item.id" class="content-item">
                <H3Title :title="item.title" :fontSize="'14px'" :keyAlign="'left'"></H3Title>
                <div class="content-desc">
                  <p>{{ item.label }}</p>
                </div>
              </div>
            </div>
            <div class="anchor-nav" ref="anchorNavRef">
              <ul>
                <li
                  v-for="(item, index) in anchorList"
                  :key="item.id"
                  :class="{ active: activeId === item.id, noLine: index === anchorList.length - 1 }"
                  @click="scrollTo(item.id)"
                >
                  <span class="nav-line"></span>
                  <span class="nav-marker"></span>
                  <span class="nav-text">{{ item.title }}</span>
                </li>
              </ul>
            </div>
          </div>
        </div>
      </FullscreenContainer>
    </el-dialog>
  </div>
</template>

<script>
import H3Title from '@/components/records-form/comp/h3-title.vue';
import FullscreenContainer from '@/components/fullscreen-container';
import { KnowledgeEnum, convertKnowledgeData } from '@/menu/knowledgeEnum.js';

export default {
  components: {
    H3Title,
    FullscreenContainer,
  },
  data() {
    return {
      dialogVisible: false,
      anchorList: [],
      activeId: '',
      observer: null,
      scrolling: false,
      scrollTimer: null,
      titleName: '',
      isMaximized: false,
    };
  },
  mounted() {
    this.$nextTick(() => {
      this.createObserver();

      this.activeId = this.anchorList[0].id;
      this.scrollAnchorToView(this.activeId);
    });
  },
  beforeDestroy() {
    this.observer && this.observer.disconnect();
  },
  methods: {
    init(row) {
      this.titleName = row.name;
      this.dialogVisible = true;
      this.$nextTick(() => {
        this.createObserver();
        this.queryDetail(row);
      });
    },
    async queryDetail(row) {
      console.log('queryDetail', row);
      if (!row.id) {
        this.$message.warning('ID不能为空');
        return;
      }
      try {
        let res = await this.$axios.post(`/cdss_knowledge/get_knowledge_detail`, {
          id: row.id,
          type: row.type,
        });

        if (res.code === 200) {
          this.anchorList = convertKnowledgeData(res.data, row.type);
          this.activeId = this.anchorList[0].id;
          this.scrollAnchorToView(this.activeId);
        } else {
          // this.$message.error(res.msg || '查询失败');
        }
      } catch (error) {
        console.error('操作报错：', error);
      }
    },
    closeDialog() {
      this.dialogVisible = false;
    },
    handleToggleMax() {
      if (!this.isMaximized) {
        this.isMaximized = true;
        this.$refs.customMaxContainerRef.openMax();
      } else {
        this.isMaximized = false;
        this.$refs.customMaxContainerRef.closeMax();
      }
    },
    scrollTo(id) {
      this.activeId = id;
      this.scrolling = true;
      clearTimeout(this.scrollTimer);

      const el = document.getElementById(id);
      const container = this.$refs.contentRef;
      if (el && container) {
        const elTop = el.offsetTop;
        const maxScroll = container.scrollHeight - container.clientHeight;
        const targetTop = Math.min(elTop - 20, maxScroll);
        container.scrollTo({ top: targetTop - 100, behavior: 'smooth' });

        this.scrollTimer = setTimeout(() => {
          this.scrolling = false;
        }, 350);
      }
      this.scrollAnchorToView(id);
    },
    createObserver() {
      const container = this.$refs.contentRef;
      const options = {
        root: container,
        threshold: 0.01,
        rootMargin: '0px 0px -50% 0px',
      };

      this.observer && this.observer.disconnect();
      this.observer = new IntersectionObserver(this.handleIntersect, options);

      this.anchorList.forEach(item => {
        const el = document.getElementById(item.id);
        el && this.observer.observe(el);
      });
    },
    handleIntersect(entries) {
      if (this.scrolling) return;
      const visibleNodes = entries.filter(entry => entry.isIntersecting).sort((a, b) => a.target.offsetTop - b.target.offsetTop);

      if (visibleNodes.length > 0) {
        this.activeId = visibleNodes[0].target.id;
        
        this.scrollAnchorToView(this.activeId);
      } else {
        const container = this.$refs.contentRef;
        if (container.scrollTop + container.clientHeight >= container.scrollHeight - 10) {
          this.activeId = this.anchorList[this.anchorList.length - 1].id;
          this.scrollAnchorToView(this.activeId);
        }
      }
    },
    scrollAnchorToView(activeId) {
      const anchorNav = this.$refs.anchorNavRef;
      if (!anchorNav) return;
      const activeLi = anchorNav.querySelector(`li[data-v-${this._uid}] .nav-text:contains(${this.anchorList.find(item => item.id === activeId)?.title})`)?.parentNode;
      if (activeLi) {
        activeLi.scrollIntoView({
          behavior: 'smooth',
          block: 'center',
        });
      }
    },
  },
};
</script>

<style lang="scss" scoped>
@import '~@/styles/common.scss';
::v-deep .max-container {
  background: #fff !important;
}
::v-deep .el-dialog {
  margin-top: 5vh !important;
}
::v-deep .el-dialog__header {
  // padding: 10px 20px;
  background: rgb(27, 100, 169);
  color: #fff !important;
  height: 60px !important;
}
::v-deep .el-dialog__header .el-dialog__title {
  color: #fff;
  font-size: 16px !important;
  align-items: center !important;
  font-weight: bold !important;
}

.dialog-container {
  display: flex;
  height: calc(90vh - 60px);
  overflow: hidden;
  padding: 0;
  margin: 0;
}
.header-content {
  display: flex;
  align-items: center;
  text-align: center;
  justify-content: space-between;
}

.content {
  flex: 1;
  overflow-y: auto;
  padding: 10px;
  scroll-behavior: smooth;
  box-sizing: border-box;
  border-radius: 6px;
  background: #fff;
}
.content::-webkit-scrollbar {
  width: 16px;
  height: 16px;
}

.content::-webkit-scrollbar-thumb {
  background-color: #74b9ff;
  border-radius: 8px;
  border: 4px solid transparent;
  background-clip: padding-box;
}

.content::-webkit-scrollbar-track {
  background-color: #f5f5f5;
  border-radius: 8px;
}

.content-item {
  margin-bottom: 10px;
  width: 100%;
}

.container-box{
  padding: 0 16px;
}

.content-title {
  margin-bottom: 40px;
  margin-top: 10px;
  align-items: center;
}
.content-title-line {
  float: right;
  align-items: center;
}

.content-desc {
  margin-top: 15px;
  color: #666;
  line-height: 1.5;
  font-size: 14px;
}
.content-desc p {
  text-indent: 2em;
  margin: 0;
  word-break: break-all;
}

.anchor-nav {
  width: 260px;
  padding: 10px 0 10px 20px;
  overflow-y: auto;
  background: #f7f7f7;
  flex-shrink: 0;
  box-sizing: border-box;
  border: 1px solid #e6ebf5;
  border-left: none;
  border-radius: 0 6px 6px 0;
  scroll-behavior: smooth;
  max-height: calc(90vh - 60px);
}

.anchor-nav ul {
  list-style: none;
  padding: 0;
  margin: 0;
}

.anchor-nav li {
  position: relative;
  display: flex;
  align-items: center;
  padding: 12px 15px;
  cursor: pointer;
  border-radius: 4px;
  margin-bottom: 4px;
  transition: all 0.3s ease;
  color: #333;
  box-sizing: border-box;
}

.anchor-nav li .nav-line {
  position: absolute;
  left: -10px;
  top: calc(50% + 10px);
  width: 2px;
  height: calc(100% - 22px);
  background-color: #e5e7eb;
  z-index: 1;
  transition: all 0.3s ease;
}

.anchor-nav li.noLine .nav-line {
  display: none;
}

.anchor-nav li .nav-marker {
  position: absolute;
  left: -14px;
  top: 50%;
  transform: translateY(-50%);
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background-color: #c9cacc;
  transition: all 0.3s ease;
  z-index: 2;
}

.anchor-nav li.active {
  color: #74b9ff;
  background-color: #f0f7ff;
}
.anchor-nav li.active .nav-marker {
  width: 20px;
  height: 12px;
  border-radius: 0 16px 16px 0;
  background-color: #74b9ff;
  left: -21px;
  transform: translateY(-50%);
}

.anchor-nav li .nav-text {
  transition: color 0.3s ease;
  font-size: 14px;
  padding-left: 4px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.anchor-nav li:hover:not(.active) {
  background-color: #f9fafb;
}
.anchor-nav li:hover:not(.active) .nav-marker {
  background-color: #a9b3bd;
}
</style>