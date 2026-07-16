<template>
  <div class="box">
    <div class="box_wrapper">
      <SearchBoxVue :data="searchData" @search="handleSearch" @reset="handleReset" />
      <TableBoxVue :data="tableData" :page="paginationData" @refresh="handleRefresh" />
    </div>
  </div>
</template>

<script>
import SearchBoxVue from './components/SearchBox.vue';
import TableBoxVue from './components/TableBox.vue';

export default {
  components: {
    SearchBoxVue,
    TableBoxVue,
  },
  data() {
    return {
      searchData: {
        name: '',
        fenzi: '',
        fenmu: '',
      },
      tableData: [],
      // tableData: [
      //   {
      //     id: 1,
      //     pid: null,
      //     level: 0,
      //     name: 'testsh123',
      //     fenzi: '[{"name":"名字1","info":"说明1"},{"name":"名字2","info":"说明2"}]',
      //     fenmu: '[{"name":"名字1","info":"说明1"},{"name":"名字2","info":"说明2"}]',
      //     img: null,
      //     url: null,
      //     updated_at: '2024-01-02 12:05:47',
      //     created_at: null,
      //     children: [
      //       {
      //         id: 3,
      //         pid: 1,
      //         level: 0,
      //         name: '测试3',
      //         fenzi: '[{"name":"名字1","info":"说明1"},{"name":"名字2","info":"说明2"}]',
      //         fenmu: '[{"name":"名字1","info":"说明1"},{"name":"名字2","info":"说明2"}]',
      //         img: null,
      //         url: '1',
      //         updated_at: null,
      //         created_at: null,
      //       },
      //       {
      //         id: 4,
      //         pid: 1,
      //         level: 0,
      //         name: '测试3',
      //         fenzi: '[{"name":"名字1","info":"说明1"},{"name":"名字2","info":"说明2"}]',
      //         fenmu: '[{"name":"名字1","info":"说明1"},{"name":"名字2","info":"说明2"}]',
      //         img: null,
      //         url: '1',
      //         updated_at: null,
      //         created_at: null,
      //       },
      //     ],
      //   },
      //   {
      //     id: 2,
      //     pid: 0,
      //     level: 0,
      //     name: '测试',
      //     fenzi: '[{"name":"名字1","info":"说明1"},{"name":"名字2","info":"说明2"}]',
      //     fenmu: '[{"name":"名字1","info":"说明1"},{"name":"名字2","info":"说明2"}]',
      //     img: null,
      //     url: '1',
      //     updated_at: null,
      //     created_at: null,
      //   },
      // ],
      paginationData: {
        total: 0,
      },
    };
  },
  created() {
    this.getList();
  },
  methods: {
    getList() {
      const { name, fenzi, fenmu } = this.searchData;
      const params = {
        name,
        fenzi,
        fenmu,
      };

      this.$axios2.get('/catalog_lists', params).then(res => {
        this.tableData = res.data || [];
        this.paginationData.total = res.data.length;
      });
    },
    handleSearch() {
      this.paginationData.page = 1;
      this.getList();
    },
    handleReset() {
      this.searchData = {
        name: '',
        fenzi: '',
        fenmu: '',
      };
    },
    handleRefresh() {
      this.getList();
    }
  },
};
</script>

<style lang="scss" scoped>
.part-box {
  padding: 16px;
  background: #fff;
  border-radius: 4px;
}
.box {
  padding: 0 16px 16px 16px;
  .box_wrapper {
    border-radius: 5px;
    position: relative;
  }
}
</style>