<template>
  <div class="record-generate">
    <div v-if="pageFlage === '0'">
      <div class="gen-card">
        <div class="card-head">
          <div class="head-left">
            <span class="pill pill-purple">首页</span>
            <span class="pill pill-outline">文书类型选择</span>
          </div>
          <button class="history-btn" @click="goHistory">历史记录</button>
        </div>

        <div class="gen-type">
          <button v-for="t in taskNameList" :key="t.id" class="type-btn" @click="goPage(t.id, t.title)">
            {{ t.title }}
          </button>
        </div>
      </div>
    </div>

    <record-input-result
      v-if="pageFlage === '1'"
      :taskId="currentTask.id"
      :title="currentTask.title"
      :input-value="currentTask.data.input"
      :result-value="currentTask.data.result"
      @back="toSelect"
      @clear="currentTask.data.input = ''"
      @generate="generateRecord"
      @copy="copy(currentTask.data.result)"
      @save="save(currentTask.title, currentTask.data.result)"
      @backfill="currentTask.data.input = currentTask.data.result"
    />

    <RecordHistory
      v-if="pageFlage === '2'"
      @close="pageFlage = '0'"
    />
  </div>
</template>

<script>
import RecordInputResult from './RecordInputResult.vue';
import RecordHistory from './RecordHistory.vue';

export default {
  name: 'MedicalRecordGenerate',
  components: {
    RecordInputResult,
    RecordHistory,
  },
  data() {
    return {
      pageFlage: '0',
      historyList: [],
      taskNameList: [],
      currentTask: {
        id: '',
        title: '',
        data: { input: '', result: '' },
      },
    };
  },
  mounted() {
    this.getTaskNameList();
  },
  methods: {

    getTaskNameList() {
      this.$axios2.get('/big_model/get_task_name', {}).then(res => {
        if (res.code == 200) {
          this.taskNameList = res.data || [];
        }
      });
    },

    goPage(id, title) {
      this.pageFlage = '1';
      this.currentTask = {
        id,
        title,
        data: { input: '', result: '' },
      };
    },

    goHistory() {
      this.pageFlage = '2'; 
    },

    toSelect() {
      this.pageFlage = '0';
      this.currentTask = { id: '', title: '', data: { input: '', result: '' } };
    },

    parse(text) {
      const d = {};
      text.split('\n').forEach(line => {
        const i = line.includes('：') ? line.indexOf('：') : line.indexOf(':');
        if (i > 0) {
          const k = line.slice(0, i).trim();
          const v = line.slice(i + 1).trim();
          d[k] = v;
        }
      });
      return d;
    },

    generateRecord() {
      const d = this.parse(this.currentTask.data.input);
      this.currentTask.data.result = `${this.currentTask.title}\n` + `姓名：${d.姓名 || ''}\n` + `性别：${d.性别 || ''}\n` + `年龄：${d.年龄 || ''}\n` + `主诉：${d.主诉 || ''}`;
    },

    // 复制
    copy(val) {
      navigator.clipboard.writeText(val);
      this.$message.success('复制成功');
    },

    // 保存到历史
    save(type, content) {
      this.historyList.unshift({
        dt: new Date().toLocaleString(),
        type,
        content,
      });
      this.$message.success('保存成功');
    },
  },
};
</script>

<style scoped>
.record-generate {
  background: #f5f7fa;
}
.gen-card {
  background: #fff;
  border: 1px solid #e5eaf1;
  border-radius: 12px;
  padding: 14px;
  margin-bottom: 12px;
}

.card-head {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 12px;
}
.head-left {
  display: flex;
  gap: 6px;
}

.pill {
  height: 24px;
  padding: 0 10px;
  border-radius: 6px;
  font-size: 12px;
  font-weight: bold;
  display: inline-flex;
  align-items: center;
}
.pill-purple {
  background: #6f42ef;
  color: #fff;
}
.pill-outline {
  background: #f7faff;
  color: #2f6bff;
  border: 1px solid #cfe0ff;
}
.history-btn {
  padding: 4px 10px;
  border: 1px solid #e5eaf1;
  border-radius: 6px;
  background: #fff;
  font-size: 12px;
  cursor: pointer;
}
.gen-type {
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.type-btn {
  height: 38px;
  padding: 0 12px;
  border-radius: 10px;
  border: 1px solid #e5eaf1;
  background: #fff;
  text-align: left;
  cursor: pointer;
}
</style>