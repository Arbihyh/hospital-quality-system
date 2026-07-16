import uploadRequest from '@/utils/uploadRequest';

export default {
	//...其他的请求
  // 上传文件
  uploadFile(url,data){
    return uploadRequest({
      url: url,
      method: 'post',
      headers: {'Content-Type': 'multipart/form-data'},
      data: data
    })
  }
  
   // ...其他的请求
  //
}