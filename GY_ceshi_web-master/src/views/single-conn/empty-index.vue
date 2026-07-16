<template>
  <div>
    <div v-if="this.showPage == 1" class="empty-result">暂无质控结果</div>
    <div v-if="showPage === 2">
      <AiPage ref="AiPageRef" />
    </div>
    <ButtonPopUp ref="buttonPopUpRef" type="empty" @showMessage="showMessage"
      @generateMedicalRecords="generateMedicalRecords" />
  </div>
</template>

<script>
import AiPage from './ai-page.vue'
import ButtonPopUp from '@/components/button-pop-up'


export default {
  name: 'empty-index',
  components: {
    ButtonPopUp,
    AiPage
  },
  props: {
    data: {
      type: Object,
      default() {
        return {
          data: {}
        }
      }
    },
    type: {
      type: String,
      default() {
        return ''
      }
    },
    height: {
      type: Number,
    }
  },
  data() {
    return {
      showPage:1,
    }
  },
  methods: { 
     generateMedicalRecords() {
      this.showPage = 2
      // this.$refs.AiPageRef.init()
    },
    showMessage(){
      this.showPage = 1
    }
  }
}
</script>

<style lang="scss" scoped>
.empty-result {
  color: #909399;
  display: flex;
  align-items: center;
  justify-content: center;
  height: 80vh;
  margin: 0;
  font-size: 22px;
}
</style>