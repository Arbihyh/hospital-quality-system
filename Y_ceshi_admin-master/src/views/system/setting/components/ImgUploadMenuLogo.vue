<template>
  <div>
    <el-upload
      ref="upload"
      class="avatar-uploader"
      action="#"
      :show-file-list="false"
      :before-upload="beforeUpload"
      :auto-upload="true"
    >
      <div slot="tip" class="el-upload__tip">只能上传jpg/png文件，建议分辨率{{ size }}</div>
      <img v-if="imageUrl" :src="imageUrl" class="avatar" alt>
      <i v-else class="el-icon-plus avatar-uploader-icon" />
    </el-upload>

  </div>
</template>

<script>
export default {
  props: {
    size: {
      type: String,
      default() {
        return ''
      }
    },
    code: {
      type: String,
      default() {
        return ''
      }
    },
    imageUrl: {
      type: String,
      default() {
        return ''
      }
    }
  },
  methods: {
    beforeUpload(file) {
      const imgType = ['image/jpeg', 'image/png'].includes(file.type)
      if (!imgType) {
        this.$message.error('上传头像图片只能是 JPG/PNG 格式!')
        return false
      }
      const content = this.$refs.upload.uploadFiles[0].raw
      const reader = new FileReader()
      const _this = this
      reader.onloadend = (e) => {
        const base64 = reader.result
        const params = {
          type: this.code,
          url: base64
        }
        _this.$emit('upload', params)
        _this.imageUrl = e.target.result
        // 处理 base64 格式的文件
      }
      reader.readAsDataURL(content)
      return false
    }
  }
}
</script>

<style lang="scss" scoped>
  ::v-deep .avatar-uploader .el-upload {
    border: 1px dashed #d9d9d9;
    border-radius: 6px;
    cursor: pointer;
    position: relative;
    overflow: hidden;
  }
  ::v-deep .avatar-uploader .el-upload:hover {
    border-color: #409EFF;
  }
  ::v-deep .avatar-uploader-icon {
    font-size: 20px;
    color: #8c939d;
    width: 120px;
    height: 120px;
    line-height: 120px;
    text-align: center;
  }
  ::v-deep .avatar {
    width: 120px;
    height: 120px;
    display: block;
  }
  ::v-deep .el-upload__tip {
    margin-top: 0;
  }
</style>
