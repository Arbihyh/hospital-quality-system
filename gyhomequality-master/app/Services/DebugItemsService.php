<?php

namespace App\Services;


class DebugItemsService
{

    private $debugItems = [];

    public function putDebugItem($title, $data, $prefix='sql:')
    {
        $this->debugItems["{$prefix}{$title}"] = $data;
    }

    public function calcDebugData($debugis, & $data = null)
    {
        if ($debugis > 1) {
            $data['_debug_'] = $this->debugItems;
        }
    }

    /**
     * @return DebugItemsService|null
     */
    public static function getInstance()
    {
        static $ss = null;
        if (empty($ss)) {
            $ss = new self();
        }

        return $ss;
    }
}