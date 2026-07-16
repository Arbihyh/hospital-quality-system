<template>
  <div class="box-container">
    <el-form ref="ruleForm" :model="ruleForm" :rules="rules" label-width="180px" class="demo-ruleForm">
      <!-- <el-form-item label="登录页背景图" prop="background_img">
        <ImgUploadLoginBg :image-url="ruleForm.background_img" code="login_bg" size="1920*920" @upload="handleUpload" />
      </el-form-item>
      <el-form-item label="登录页logo" prop="logo">
        <ImgUploadLoginLogo :image-url="ruleForm.logo" code="logo" size="800*127" @upload="handleUpload" />
      </el-form-item> -->
      <el-form-item label="系统logo" prop="menu_logo">
        <ImgUploadMenuLogo :image-url="ruleForm.menu_logo" code="menu_logo" size="400*400" @upload="handleUpload" />
      </el-form-item>
      <el-form-item label="医院名称" prop="web_name">
        <el-input v-model="ruleForm.web_name" placeholder="请输入" style="width: 300px;" />
      </el-form-item>
      <el-form-item>
        <el-button type="primary" @click="submitForm('ruleForm')">保存</el-button>
        <el-button @click="onCancel">取消</el-button>
      </el-form-item>
    </el-form>
  </div>
</template>

<script>
// import ImgUploadLoginBg from './components/ImgUploadLoginBg'
// import ImgUploadLoginLogo from './components/ImgUploadLoginLogo'
import ImgUploadMenuLogo from './components/ImgUploadMenuLogo'
import { setSetting, getSetting } from '@/api/setting'

export default {
  components: {
    // ImgUploadLoginBg,
    // ImgUploadLoginLogo,
    ImgUploadMenuLogo
  },
  data() {
    return {
      ruleForm: {
        background_img: '',
        logo: '',
        menu_logo: '',
        web_name: ''
      },
      rules: {
        background_img: [
          { required: true, message: '请上传', trigger: 'blur' }
        ],
        logo: [
          { required: true, message: '请上传', trigger: 'blur' }
        ],
        menu_logo: [
          { required: true, message: '请上传', trigger: 'blur' }
        ],
        web_name: [
          { required: true, message: '请输入', trigger: 'blur' }
        ]
      }
    }
  },
  created() {
    this.getSetting()
  },
  methods: {
    handleUpload(val) {
      const { type, url } = val
      if (type === 'login_bg') {
        this.ruleForm.background_img = url
      }
      if (type === 'logo') {
        this.ruleForm.logo = url
      }
      if (type === 'menu_logo') {
        this.ruleForm.menu_logo = url
      }
    },
    submitForm(formName) {
      this.$refs[formName].validate((valid) => {
        if (valid) {
          setSetting(this.ruleForm).then(res => {
            this.$message.success(res.message || '保存成功')
          })
        } else {
          console.log('error submit!!')
          return false
        }
      })
    },
    onCancel() {
      this.$refs.ruleForm.clearValidate()
    },
    getSetting() {
      getSetting().then(res => {
        const { logo, menu_logo, background_img, web_name } = res.p
        this.$set(this.ruleForm, 'logo', logo.content)
        this.$set(this.ruleForm, 'menu_logo', menu_logo.content)
        this.$set(this.ruleForm, 'background_img', background_img.content)
        this.$set(this.ruleForm, 'web_name', web_name.content)
      })
    }
  }
}
</script>

<style lang="scss" scoped>
.box-container {
  padding: 20px;
}
</style>
