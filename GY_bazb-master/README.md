# quality

质控

## 脚本命令
### 格式化出院记录入院记录
php artisan command:analysisCase > zb_log/analysisCase.log &
### 清洗病程记录中的病程特色，用于校验75%雷同
php artisan command:bc > zb_log/bc.log &
### 清洗手术记录中的手术时间，术者的信息到bl01表
php artisan command:OperationBl01Clean > zb_log/OperationBl01Clean.log &

### 超声质控
php artisan command:chaosheng > zb_log/chaosheng.log &
### 入院记录质控
php artisan command:quality-ry > zb_log/quality-ry.log &
### 病例质控
php artisan command:quality > zb_log/quality.log &

