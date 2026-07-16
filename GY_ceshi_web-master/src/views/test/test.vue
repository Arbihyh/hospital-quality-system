<template>
  <el-card style="height: 1000px;">
    <div class="flex-1 overflow-y-auto p-4 space-y-4" ref="chatContainer">
      <div v-for="(message, index) in chatMessages" :key="index"
           :class="message.role === 'user' ? 'flex justify-end' : 'flex justify-start'">
        <div :class="message.role === 'user' ? 'bg-blue-500 text-white rounded-md p-2 max-w-xs' : 'bg-gray-300 text-gray-800 rounded-md p-2 max-w-xs'">
          {{ message.content }}
        </div>
      </div>
    </div>
    <div style="display: flex;justify-content: center;height: 300px;position: fixed ;bottom: -180px;left: 0;right: 0">
      <el-input type="textarea" style="width: 800px;height: 300px;" placeholder="请输入" v-model="inputMessage">
      </el-input>
      <i class="el-icon-position" style="font-size: 28px;margin-top: 10px;margin-left: 10px;" @click="sendMessage"></i>
    </div>
  </el-card>
</template>

<script>
export default {
  data() {
    return {
      inputMessage:'',
      chatMessages:[],
    };
  },
  mounted() {
  },
  methods:{
    async sendMessage(){
      const messages = this.inputMessage.trim();
      if (messages){
        //添加用户信息到聊天记录
        this.chatMessages.push({
          role: 'user',
          content: messages
        });

        // 清空输入框
        this.inputMessage = '';

        //大模型请求
        const modelResponse = await this.simulateModelResponse(messages);
        console.log(modelResponse);

        // 添加模型响应消息到聊天记录
        this.chatMessages.push({
          role: 'assistant',
          content: modelResponse
        });
      }
    },
    //大模型请求
    simulateModelResponse(userMessage) {
      //请求内容
      const options = {
        method: 'POST',
        headers: {
          Authorization: 'Bearer sk-ttnryoxayznjgquagdfwjefbkubdnezabsotuczokunwkjin',
          'Content-Type': 'application/json'
        },
       // body: '{"model":"Qwen/QwQ-32B","messages":[{"role":"user","content":"当前时间"}],"stream":false,"max_tokens":512,"stop":null,"temperature":0.7,"top_p":0.7,"top_k":50,"frequency_penalty":0.5,"n":1,"response_format":{"type":"text"},"tools":[{"type":"function","function":{"description":"<string>","name":"<string>","parameters":{},"strict":false}}]}'
      };
      const body = {};
      body.model = "Qwen/QwQ-32B";
      body.messages = [{"role":"user","content":userMessage}];
      options.body = JSON.stringify(body);

      //请求
      return  fetch('https://api.siliconflow.cn/v1/chat/completions', options).then(response => {
        return response.json();
      }).then(data => {
        const content = data.choices[0].message.reasoning_content;
        return content;
      }).catch(err => console.error(err));
    },
  },
};
</script>

<style>

</style>
