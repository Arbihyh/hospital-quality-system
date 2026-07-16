<template>
  <div ref="box" class="box">
    <CaseQualityBox :width="400"/>
  </div>
</template>

<script>
import CaseQualityBox from '@/views/single-conn/CaseQualityBox2.vue';
export default {
    components: {
      CaseQualityBox
    },
    data() {
      return {
        data: {}
      }
    },
    created() {
      // this.getCaseQualityResults()
      // if(this.$route.query.id) {
      //   this.getCaseQualityResults()
      // }
    },
    methods: {
      // 获取新病案指控结果
      // getCaseQualityResults() {
      //   const params = {
      //     ZYH: Number(this.$route.query.ZYH),
      //   };
      //   this.$axios.post('/home_bmy_quality/bmyQualityResult', params).then(res => {
      //     this.data = res.data;
      //   });
      // }
    }
  }
</script>

<style lang="scss" scoped>
</style>