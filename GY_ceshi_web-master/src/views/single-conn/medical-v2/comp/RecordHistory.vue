<template>
  <div class="history-record" v-show="visible">
    <div class="qc-card">
      <div class="card-head space">
        <div class="card-head-left">
          <span class="pill pill-purple">历史</span>
          <span class="pill pill-outline">生成记录</span>
        </div>
        <div class="close-x" @click="close">关闭</div>
      </div>

      <div class="gen-field">
        <input v-model="filterText" @input="renderHistory" class="gen-input" placeholder="输入关键词过滤" />
      </div>

      <div class="gen-field history-list" ref="historyWrap" @scroll="handleScroll">
        <div v-if="loading" class="loading-message">
          <span>加载中...</span>
        </div>

        <div v-else-if="filteredList.length === 0" class="no-message">暂无数据</div>

        <template v-else>
          <div v-for="(group, date) in groupedList" :key="date" class="hist-section">
            <div
              class="hist-date-title"
              :class="{ collapsed: collapsedDates.includes(date) }"
              @click="toggleDate(date)"
            >
              <span>{{ date }}</span>
              <span class="hist-date-arrow">▼</span>
            </div>

            <div class="hist-group" :class="{ collapsed: collapsedDates.includes(date) }">
              <div
                v-for="(item, idx) in group"
                :key="idx"
                class="hist-item"
                :class="{ expanded: expandedItem === item }"
              >
                <div class="hist-meta">
                  <span class="hist-doc">{{ item.doc }}</span>
                  <span class="hist-time">{{ item.time }}</span>
                  <span
                    class="hist-excerpt"
                    @mouseenter="showPreview(item.content, $event)"
                    @mouseleave="hidePreview"
                  >{{ getExcerpt(item.content) }}</span>
                </div>

                <div class="hist-actions">
                  <button class="btn btn-light" @click="expandItem(item)">查看</button>
                  <button class="btn btn-primary" @click="fillItem(item)">填入</button>
                </div>

                <div v-if="expandedItem === item" class="hist-detail">
                  <textarea class="gen-textarea" readonly v-model="item.content"></textarea>
                </div>
              </div>
            </div>
          </div>
        </template>

        <div v-if="loadingMore && filteredList.length > 0" class="loading-more">
          <el-loading-spinner size="small"></el-loading-spinner>
          <span>加载更多...</span>
        </div>

        <div v-if="!hasMore && filteredList.length > 0" class="no-more">没有更多记录了</div>
      </div>
    </div>

    <div class="hover-preview" ref="preview">{{ previewText }}</div>
  </div>
</template>

<script>
export default {
  name: 'HistoryRecord',
  props: {
    visible: { type: Boolean, default: true },
  },
  data() {
    return {
      filterText: '',
      collapsedDates: [],
      expandedItem: null,
      previewText: '',

      page: 1,
      page_size: 20,
      historyList: [],
      total: 0,
      loading: false,
      loadingMore: false,
      hasMore: true,
      totalPages: 0,
    };
  },
  computed: {
    filteredList() {
      const q = this.filterText.trim().toLowerCase();
      if (!q) return this.historyList;
      return this.historyList.filter(it => {
        const name = this.extractName(it.content);
        const mrn = this.extractMrn(it.content);
        const bed = this.extractBed(it.content);
        const str = `${it.doc} ${it.content} ${it.dt} ${name} ${mrn} ${bed}`.toLowerCase();
        return str.includes(q);
      });
    },
    groupedList() {
      const groups = {};
      this.filteredList.forEach(it => {
        groups[it.date] = groups[it.date] || [];
        groups[it.date].push(it);
      });
      return groups;
    },
  },
  watch: {
    filterText() {
      this.resetPageParams();
      this.medical_record_list();
    },
  },
  mounted() {
    this.medical_record_list();
  },
  methods: {
    close() {
      this.$emit('close');
    },
    initData(searchData) {
      this.medical_record_list();
    },
    renderHistory() {
      console.log('renderHistory');
    },
    toggleDate(date) {
      this.collapsedDates.includes(date) ? (this.collapsedDates = this.collapsedDates.filter(d => d !== date)) : this.collapsedDates.push(date);
    },
    expandItem(item) {
      this.expandedItem = this.expandedItem === item ? null : item;
    },
    fillItem(item) {
      this.$emit('fill', item.content);
    },
    getExcerpt(s) {
      const mrn = this.extractMrn(s);
      const bed = this.extractBed(s);
      if (mrn || bed) {
        return [mrn && `病案号：${mrn}`, bed && `床号：${bed}`].filter(Boolean).join('  ');
      }
      return s.replace(/\n+/g, ' ').slice(0, 40);
    },
    extractName(s) {
      const m = String(s).match(/姓名[:：]\s*(\S+)/);
      return m ? m[1] : '';
    },
    extractMrn(s) {
      const m = String(s).match(/病案号[:：]\s*(\S+)/);
      return m ? m[1] : '';
    },
    extractBed(s) {
      const m = String(s).match(/床号[:：]\s*(\S+)/);
      return m ? m[1] : '';
    },
    showPreview(text, e) {
      this.previewText = text;
      this.$nextTick(() => {
        const el = this.$refs.preview;
        if (!el) return;
        const r = e.target.getBoundingClientRect();
        el.style.display = 'block';
        el.style.left = r.left + 'px';
        el.style.top = r.bottom + 6 + 'px';
      });
    },
    hidePreview() {
      this.$nextTick(() => {
        const el = this.$refs.preview;
        if (el) el.style.display = 'none';
      });
    },

    resetPageParams() {
      this.page = 1;
      this.historyList = [];
      this.hasMore = true;
      this.totalPages = 0;
    },

    handleScroll(e) {
      const element = e.target;
      if (element.scrollHeight - element.scrollTop <= element.clientHeight + 20) {
        if (!this.hasMore || this.loading || this.loadingMore) return;
        if (this.page >= this.totalPages) {
          this.hasMore = false;
          return;
        }
        this.page++;
        this.medical_record_list();
      }
    },

    medical_record_list() {
      if (this.page === 1) {
        this.loading = true;
      } else {
        this.loadingMore = true;
      }

      this.$axios2
        .post('/big_model/medical_record_list', {
          zyh: this.$route.query.id,
          title: this.filterText,
          page: this.page,
          page_size: this.page_size,
        })
        .then(res => {
          if (res.code === 200) {
            const rawList = res.data?.list || [];
            this.total = res.data?.count || 0;
            this.totalPages = Math.ceil(this.total / this.page_size);

            const newList = rawList.map(item => {
              const dt = item.created_at || '';
              const [date, time] = dt.split(' ') || ['', ''];
              return {
                dt: dt,
                date: date || '',
                time: time || '',
                doc: item.title || '未知文档',
                content: item.content || '',
              };
            });

            if (this.page === 1) {
              this.historyList = newList;
            } else {
              this.historyList = [...this.historyList, ...newList];
            }

            this.hasMore = this.page < this.totalPages;
          }
        })
        .catch(err => {
          console.error('获取病历记录失败：', err);
        })
        .finally(() => {
          this.loading = false;
          this.loadingMore = false;
        });
    },
  },
};
</script>

<style scoped lang="scss">
.history-record {
  display: flex;
  position: fixed;
  inset: 0px;
  background: rgba(0, 0, 0, 0.4);
  z-index: 300;
  align-items: center;
  justify-content: center;
}
.qc-card {
  position: relative;
  background: #ffffff;
  border: 1px solid #e5eaf1;
  border-radius: 12px;
  padding: 12px;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
  width: 90%;
  max-width: 500px;
  max-height: 80vh;
  display: flex;
  flex-direction: column;
}
.card-head {
  display: flex;
  align-items: center;
  gap: 6px;
  margin-bottom: 12px;
}
.card-head.space {
  justify-content: space-between;
}
.card-head-left {
  display: flex;
  align-items: center;
  gap: 6px;
  min-width: 0;
}

.pill {
  height: 24px;
  padding: 0 9px;
  border-radius: 6px;
  border: 1px solid transparent;
  font-size: 12px;
  font-weight: 700;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  white-space: nowrap;
}
.pill-purple {
  color: #fff;
  background: #6f42ef;
}
.pill-outline {
  color: #2f6bff;
  background: #f7faff;
  border-color: #cfe0ff;
}

.close-x {
  height: 24px;
  padding: 0 8px;
  border-radius: 6px;
  border: 1px solid #e1e7ef;
  background: #f8fafc;
  color: #4b5563;
  font-size: 12px;
  font-weight: 700;
  display: inline-flex;
  align-items: center;
  cursor: pointer;
}

.gen-field {
  margin-top: 8px;
}
.gen-input {
  width: 100%;
  border: 1px solid #d8dee7;
  border-radius: 10px;
  padding: 10px;
  font-size: 13px;
  outline: none;
}
.gen-input:focus {
  border-color: #2f6bff;
  box-shadow: 0 0 0 3px rgba(47, 107, 255, 0.1);
}

.btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  height: 28px;
  padding: 0 12px;
  border-radius: 6px;
  font-size: 12px;
  cursor: pointer;
  border: none;
}
.btn-light {
  background: #f3f4f6;
  color: #4b5563;
}
.btn-primary {
  background: #2f6bff;
  color: #fff;
}

.history-list {
  margin-top: 8px;
  // max-height: 380px;
  overflow-y: auto;
  padding-right: 4px;
}
.history-list::-webkit-scrollbar {
  width: 6px;
}
.history-list::-webkit-scrollbar-thumb {
  background: #d9d9d9;
  border-radius: 3px;
}

.hist-section {
  margin: 10px 0;
  padding: 8px 10px;
  border: 1px solid #e5eaf1;
  border-radius: 10px;
  background: #fff;
}
.hist-date-title {
  font-size: 12px;
  color: #7b8794;
  font-weight: 700;
  cursor: pointer;
  display: flex;
  justify-content: space-between;
}
.hist-date-arrow {
  font-size: 12px;
  transition: transform 0.2s;
}
.hist-date-title.collapsed .hist-date-arrow {
  transform: rotate(-90deg);
}
.hist-group.collapsed {
  display: none;
}

.hist-item {
  position: relative;
  padding: 8px 0;
  border-bottom: 1px solid #edf2f7;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  font-size: 12px;
  color: #4b5563;
}
.hist-item:last-child {
  border-bottom: none;
}
.hist-meta {
  flex: 1;
  min-width: 0;
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}
.hist-doc {
  color: #2f6bff;
  font-weight: 500;
}
.hist-time {
  color: #7b8794;
}
.hist-excerpt {
  flex: 1;
  min-width: 120px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  color: #7b8794;
  cursor: pointer;
}

.hist-item.expanded {
  flex-direction: column;
  align-items: stretch;
  gap: 0;
  padding-bottom: 44px;
}
.hist-item.expanded .hist-actions {
  position: absolute;
  right: 0;
  bottom: 10px;
  display: flex;
  gap: 8px;
}

.hist-detail {
  margin-top: 8px;
  width: 100%;
}
.gen-textarea {
  width: 100%;
  min-height: 160px;
  resize: vertical;
  border: 1px solid #d8dee7;
  border-radius: 10px;
  padding: 10px;
  font-size: 13px;
  line-height: 1.6;
  outline: none;
}
.gen-textarea:focus {
  border-color: #2f6bff;
  box-shadow: 0 0 0 3px rgba(47, 107, 255, 0.1);
}

.hover-preview {
  position: fixed;
  z-index: 100;
  max-width: 400px;
  max-height: 420px;
  overflow: auto;
  padding: 12px 14px;
  border: 1px solid #e5eaf1;
  border-radius: 10px;
  background: #fff;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
  font-size: 13px;
  line-height: 1.7;
  display: none;
}

.loading-message {
  text-align: center;
  padding: 30px 0;
  color: #666;
  font-size: 14px;
}
.loading-more {
  text-align: center;
  padding: 12px 0;
  color: #666;
  font-size: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
}
.no-more {
  text-align: center;
  padding: 12px 0;
  color: #999;
  font-size: 12px;
}
.no-message {
  text-align: center;
  padding: 40px 0;
  color: #999;
  font-size: 14px;
}
</style>