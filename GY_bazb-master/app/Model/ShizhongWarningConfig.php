<?php

namespace App\Model;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;

class ShizhongWarningConfig extends Model
{
    protected $table = 'shizhong_warning_configs';

    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }

    /**
     * 根据配置键获取配置值。
     *
     * @param string $key
     * @param string $default
     * @return string
     */
    public static function getValue($key, $default = '')
    {
        $value = self::query()->where('config_key', '=', $key)->value('config_value');

        return $value === null || $value === '' ? $default : $value;
    }

    /**
     * 根据配置键获取逗号分隔数组。
     *
     * @param string $key
     * @param string $default
     * @return array
     */
    public static function getList($key, $default = '')
    {
        $value = self::getValue($key, $default);
        $value = str_replace('，', ',', $value);

        return array_values(array_filter(array_map('trim', explode(',', $value)), function ($item) {
            return $item !== '';
        }));
    }
}
