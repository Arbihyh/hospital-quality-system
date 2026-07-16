<!-- 拖拽对话框 -->
<template>
  <div ref="dialogContainer">
    <slot></slot>
  </div>
</template>

<script>
export default {
  name: 'DraggableDialog',
  props: {
    dialogKey: {
      type: [String, Number],
      default: ''
    }
  },
  data() {
    return {
      isDragging: false,
      dragStartX: 0,
      dragStartY: 0,
      currentLeft: null,
      currentTop: null,
      dialogEl: null,
      dialogWidth: 'auto',
    };
  },
  watch: {
    dialogKey() {
      this.currentLeft = null;
      this.currentTop = null;
      this.$nextTick(() => {
        this.applyStyleToDialog();
      });
    }
  },
  computed: {
    dialogStyle() {
      if (this.currentLeft === null && this.currentTop === null) {
        return {
          width: this.dialogWidth,
          position: 'fixed',
          top: '50%',
          left: '50%',
          transform: 'translate(-50%, -50%)',
          margin: '0'
        };
      }
      return {
        width: this.dialogWidth,
        left: `${this.currentLeft}px`,
        top: `${this.currentTop}px`,
        margin: '0',
        zIndex: '2001',
        position: 'fixed',
        transform: 'none',
      };
    },
  },
  mounted() {
    this.$nextTick(() => {
      const dialogs = this.$refs.dialogContainer.querySelectorAll('.el-dialog');
      if (dialogs.length > 0) {
        this.dialogEl = dialogs[0];
        const computedStyle = window.getComputedStyle(this.dialogEl);
        this.dialogWidth = computedStyle.width;
        const headerEl = this.dialogEl.querySelector('.el-dialog__header');
        const customHeaderEl = this.dialogEl.querySelector('.dialog-header');
        if (headerEl) {
          headerEl.style.cursor = 'move';
          headerEl.addEventListener('mousedown', this.startDragging);
        }
        if (customHeaderEl) {
          customHeaderEl.style.cursor = 'move';
          customHeaderEl.addEventListener('mousedown', this.startDragging);
        }
        this.applyStyleToDialog();
      }
    });
  },
  beforeDestroy() {
    if (this.dialogEl) {
      const headerEl = this.dialogEl.querySelector('.el-dialog__header');
      const customHeaderEl = this.dialogEl.querySelector('.dialog-header');
      if (headerEl) headerEl.removeEventListener('mousedown', this.startDragging);
      if (customHeaderEl) customHeaderEl.removeEventListener('mousedown', this.startDragging);
    }
    document.removeEventListener('mousemove', this.onMouseMove);
    document.removeEventListener('mouseup', this.onMouseUp);
  },
  methods: {
    applyStyleToDialog() {
      if (this.dialogEl) Object.assign(this.dialogEl.style, this.dialogStyle);
    },
    startDragging(e) {
      e.preventDefault();
      const isCloseBtn = e.target.classList.contains('close-btn') || e.target.parentElement?.classList.contains('close-btn');
      if (isCloseBtn) return;
      this.isDragging = true;
      this.dragStartX = e.clientX;
      this.dragStartY = e.clientY;
      const rect = this.dialogEl.getBoundingClientRect();
      this.currentLeft = rect.left;
      this.currentTop = rect.top;
      this.applyStyleToDialog();
      document.addEventListener('mousemove', this.onMouseMove);
      document.addEventListener('mouseup', this.onMouseUp);
    },
    onMouseMove(e) {
      if (!this.isDragging) return;
      const dx = e.clientX - this.dragStartX;
      const dy = e.clientY - this.dragStartY;
      let newLeft = this.currentLeft + dx;
      let newTop = this.currentTop + dy;

      const windowHeight = window.innerHeight;
      const dialogHeight = this.dialogEl.offsetHeight;
      newTop = Math.max(0, Math.min(newTop, windowHeight - dialogHeight));

      this.currentLeft = newLeft;
      this.currentTop = newTop;
      this.dragStartX = e.clientX;
      this.dragStartY = e.clientY;
      this.applyStyleToDialog();
    },
    onMouseUp() {
      this.isDragging = false;
      document.removeEventListener('mousemove', this.onMouseMove);
      document.removeEventListener('mouseup', this.onMouseUp);
    },
  },
};
</script>

<style scoped>
::v-deep .el-dialog__header {
  cursor: move;
  user-select: none;
}
</style>