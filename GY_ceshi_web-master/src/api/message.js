import axios2 from '@/axios/index2';

/**
 * 获取消息列表
 */
export function getMessageList(pageParams) {
    console.log(pageParams)
    return axios2.get(`/tk/get_msg?page=${pageParams.page}&page_size=${pageParams.page_size}&is_read=${pageParams.is_read}&zyh=${pageParams.zyh}`)
}

/**
 * 获取消息数量
 */
export function get_msg_count() {
    return axios2.get('/tk/get_msg_count')
}

/**
 * 清空所有消息
 */
export function clearAllMessages(params) {
    return axios2.post('/tk/read_all_msg',params)
}

/**
 * 标记消息为已读
 */
export function markMessageAsRead(params) {
    return axios2.post('/tk/read_msg',params)
}
