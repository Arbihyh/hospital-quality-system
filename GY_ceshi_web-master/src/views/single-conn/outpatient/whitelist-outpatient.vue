<template>
  <div class="page-container">
    <CaseQualityBox :data="results" @refresh="handleRefresh" />
  </div>
</template>

<script>
import CaseQualityBox from '@/views/allcase/components/CaseQualityBox.vue';
export default {
  components: {
    CaseQualityBox,
  },
  data() {
    return {
      data: {
        xy: [],
      },
      results: {
        score: 0,
        data: {},
      },
    };
  },
  created() {
    if (this.$route.query.id) {
      this.getCaseQualityResults();
    }
  },
  methods: {
    handleRefresh(blbh) {
        console.log('handleRefresh', blbh);
      if (this.$route.query.id) {
        this.getCaseQualityResults(blbh);
      }
    },
    getCaseQualityResults(id = this.$route.query.id) {
      const params = {
        blbh: id,
      };
      this.$axios.post('/omr_zk/get_omr_quality', params).then(res => {
        this.results = res.data;
      });
    },
  },
};
</script>

<style lang="scss" scoped>
</style>