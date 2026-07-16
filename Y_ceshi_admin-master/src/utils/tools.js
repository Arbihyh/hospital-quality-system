import { upload } from '@/api/admin'
import SVGA from 'svgaplayerweb'
import router from '@/router'

export function uploadFile(file) {
  upload({ file: file.file, type: file.data.type }).then(res => {
    file.onSuccess(res, file)
  })
}

// 秒转天
export function secondsToDays(time) {
  return time / 60 / 60 / 24
}

// 格式化时间
function formatDate(date) {
  date = new Date(date * 1000) // 如果date为13位不需要乘1000
  const Y = date.getFullYear() + '-'
  const M = (date.getMonth() + 1 < 10 ? '0' + (date.getMonth() + 1) : date.getMonth() + 1) + '-'
  const D = (date.getDate() < 10 ? '0' + (date.getDate()) : date.getDate()) + ' '
  const h = (date.getHours() < 10 ? '0' + date.getHours() : date.getHours()) + ':'
  const m = (date.getMinutes() < 10 ? '0' + date.getMinutes() : date.getMinutes()) + ':'
  const s = (date.getSeconds() < 10 ? '0' + date.getSeconds() : date.getSeconds())
  return Y + M + D + h + m + s
}

export function svgaPlay(id, svgaUrl) {
  const div = document.querySelector(`#${id}`)
  div.style.width = '320px'
  div.style.height = '458px'
  div.style.margin = 'auto'
  const player = new SVGA.Player(`#${id}`)
  const parser = new SVGA.Parser(`#${id}`) // Must Provide same selector eg:#demoCanvas IF support IE6+
  player.loops = 1
  parser.load(svgaUrl, function(videoItem) {
    player.setContentMode('AspectFit')
    player.setVideoItem(videoItem)
    player.startAnimation()
  })
}

export function routePush(url, params) {
  return router.push({
    path: url,
    query: params
  })
}

export function handleClose(done) {
  this.$confirm('确认关闭？')
    .then(_ => {
      done()
    })
    .catch(_ => {})
}

export default {
  uploadFile: uploadFile,
  secondsToDays: secondsToDays,
  formatDate: formatDate,
  svgaPlay: svgaPlay,
  routePush: routePush
}
