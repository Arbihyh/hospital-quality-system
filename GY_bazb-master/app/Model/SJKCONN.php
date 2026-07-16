<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

/**
 * 数据库连接配置模型
 */
class SJKCONN extends Model
{
    protected $table = 'SJK_CONN';
    
    protected $primaryKey = 'SJK_ID';
    
    public $timestamps = false;
    
    protected $fillable = [
        'SJKLX',
        'host',
        'port',
        'username',
        'password',
        'database'
    ];

    /**
     * 根据数据库ID获取连接信息
     * @param int $sjkId
     * @return array|null
     */
    public static function getBySjkId($sjkId)
    {
        $conn = self::where('SJK_ID', $sjkId)->first();
        
        if (!$conn) {
            return null;
        }
        
        return [
            'SJKLX' => $conn->SJKLX,
            'host' => $conn->host,
            'port' => $conn->port,
            'username' => $conn->username,
            'password' => $conn->password,
            'database' => $conn->database,
        ];
    }
}
