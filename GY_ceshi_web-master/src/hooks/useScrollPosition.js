/**
 * 滚动定位
 * 支持自定义滚动容器，提供保存滚动/恢复滚动/滚动到顶部方法
 * 依赖：页面需开启keep-alive缓存（activated钩子才会触发）
 * @param {Object} vm - Vue组件实例（this）
 * @param {String} scrollContainer - 自定义滚动容器选择器，默认 '.page-container'
 * @returns {Object} 
 */
export default function useScrollPosition(vm, scrollContainer = '.page-container') {
    let _scrollPos = 0;
    let _isBackFromDrill = false;

    vm.$on('hook:activated', () => {
        console.log(`[滚动定位] activated：`, _isBackFromDrill);
        vm.$nextTick(() => {
            if (_isBackFromDrill) {
                console.log(`[滚动定位] 恢复位置：${_scrollPos}，容器：${scrollContainer}`);
                _restoreScroll();
                _isBackFromDrill = false;
            }
        });
    });

    function _restoreScroll() {
        const container = document.querySelector(scrollContainer);
        if (container) {
            container.scrollTop = _scrollPos;
        } else {
            window.scrollTo(0, _scrollPos);
        }
    }

    function saveScrollPos() {
        const container = document.querySelector(scrollContainer);
        if (container) {
            _scrollPos = container.scrollTop;
        } else {
            _scrollPos = window.pageYOffset || document.documentElement.scrollTop || document.body.scrollTop;
        }
        _isBackFromDrill = true;
        console.log(`[滚动定位] 保存位置：${_scrollPos}，容器：${scrollContainer}`);
    }

    function scrollToTop() {
        const container = document.querySelector(scrollContainer);
        if (container) {
            container.scrollTop = 0;
        } else {
            window.scrollTo(0, 0);
        }
        _isBackFromDrill = false;
    }

    return {
        saveScrollPos,
        scrollToTop
    };
}