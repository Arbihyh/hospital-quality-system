<?php

namespace App\Model;

class SjkConn extends BaseModel
{
    protected $table = 'SJK_CONN';
    
    protected $fillable = [
        'sjk_id',
        'sjklx', 
        'host',
        'port',
        'username',
        'password',
        'database'
    ];

    /**
     * 根据 SJKID 获取连接信息
     * @param string $sjkId
     * @return array
     */
    public static function getBySjkId(string $sjkId): array
    {
        $conn = self::query()->where('sjk_id', $sjkId)->first();
        return $conn ? $conn->toArray() : [];
    }
}
