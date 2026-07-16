/**
 * 滚动定位工具 
 * 支持自定义滚动容器，提供保存滚动/恢复滚动/滚动到顶部方法
 * 依赖：页面需开启keep-alive缓存（activated钩子才会触发）
 */
export default {
  data() {
    return {
      scrollContainer: '.page-container',
      _scrollPos: 0, 
      _isBackFromDrill: false 
    }
  },
  activated() {
    console.log(`[滚动定位] activated：`,this._isBackFromDrill)
    this.$nextTick(() => {
      if (this._isBackFromDrill) {
        console.log(`[滚动定位] 恢复位置：${this._scrollPos}，容器：${this.scrollContainer}`)
        this._restoreScroll()
        this._isBackFromDrill = false
      }
    })
  },
  methods: {
    saveScrollPos() {
      const container = document.querySelector(this.scrollContainer)
      if (container) {
        this._scrollPos = container.scrollTop
      } else {
        this._scrollPos = window.pageYOffset || document.documentElement.scrollTop || document.body.scrollTop
      }
      this._isBackFromDrill = true
      console.log(`[滚动定位] 保存位置：${this._scrollPos}，容器：${this.scrollContainer}`)
    },
    // 对外暴露：滚动到顶部（查询/重置/分页时调用）
    scrollToTop() {
      const container = document.querySelector(this.scrollContainer)
      if (container) {
        container.scrollTop = 0
      } else {
        window.scrollTo(0, 0)
      }
      this._isBackFromDrill = false
    },
    // 私有：恢复滚动位置（activated自动调用，无需外部操作）
    _restoreScroll() {
      const container = document.querySelector(this.scrollContainer)
      if (container) {
        container.scrollTop = this._scrollPos
      } else {
        window.scrollTo(0, this._scrollPos)
      }
    }
  }
}