<?php


namespace App\Services;

use Carbon\Carbon;
use zjkal\ChinaHoliday;
use App\Model\RuleWordMap;

/**
 * 公共服务类
 */
class PublicService
{
    /**
     * 获取两个字符串重复率
     * @param $str1
     * @param $str2
     * @return float|int
     */
    public function getSimilar($str1, $str2)
    {
        if ($str1 == '' || $str2 == '') {
            return 0;
        }
        $len1 = strlen($str1);
        $len2 = strlen($str2);
        $len = strlen($this->getLCS($str1, $str2, $len1, $len2));
        return $len * 2 / ($len1 + $len2);
    }
    public function getLCS($str1, $str2, $len1 = 0, $len2 = 0)
    {
        $this->str1 = $str1;
        $this->str2 = $str2;
        if ($len1 == 0) $len1 = strlen($str1);
        if ($len2 == 0) $len2 = strlen($str2);
        $this->initC($len1, $len2);
        return $this->printLCS($this->c, $len1 - 1, $len2 - 1);
    }
    public function initC($len1, $len2)
    {
        for ($i = 0; $i < $len1; $i++) $this->c[$i][0] = 0;
        for ($j = 0; $j < $len2; $j++) $this->c[0][$j] = 0;
        for ($i = 1; $i < $len1; $i++) {
            for ($j = 1; $j < $len2; $j++) {
                if ($this->str1[$i] == $this->str2[$j]) {
                    $this->c[$i][$j] = $this->c[$i - 1][$j - 1] + 1;
                } else if ($this->c[$i - 1][$j] >= $this->c[$i][$j - 1]) {
                    $this->c[$i][$j] = $this->c[$i - 1][$j];
                } else {
                    $this->c[$i][$j] = $this->c[$i][$j - 1];
                }
            }
        }
    }
    public function printLCS($c, $i, $j)
    {
        if ($i == 0 || $j == 0) {
            if ($this->str1[$i] == $this->str2[$j]) return $this->str2[$j];
            else return "";
        }
        if ($this->str1[$i] == $this->str2[$j]) {
            return $this->printLCS($this->c, $i - 1, $j - 1) . $this->str2[$j];
        } else if ($this->c[$i - 1][$j] >= $this->c[$i][$j - 1]) {
            return $this->printLCS($this->c, $i - 1, $j);
        } else {
            return $this->printLCS($this->c, $i, $j - 1);
        }
    }

    /**
     * 获取 N个工作日之后的日期
     * @param $dateTime 开始日期
     * @param $day 向后多少个工作日
     * @return string
     */
    protected $returnDateTime = '';
    public function getDay($dateTime, $day = 1)
    {
        for ($i = 1; $i <= $day; $i++) {
            $newDay = Carbon::parse($dateTime)->addDay()->toDateTimeString();
            $this->returnDateTime = $newDay;
            $res = ChinaHoliday::isHoliday($newDay);
            if ($res) {
                $this->getDay($newDay, $day);
            } else {
                if ($day > 1) {
                    $day -= 1;
                    $this->getDay($newDay, $day);
                }
            }
        }
        return $this->returnDateTime;
    }

    public function getWorkDays($startDate, $days)
    {
        try {
            $holidays = RuleWordMap::getArrayById(8092);
            $holidaySet = [];
            foreach ($holidays as $holiday) {
                $holidaySet[] = trim($holiday);
            }
        } catch (\Exception $e) {
            $holidaySet = [];
        }

        $currentDate = Carbon::parse($startDate);
        $workDaysCount = 0;
        $resultDate = $currentDate->copy();

        while ($workDaysCount < $days) {
            $resultDate->addDay();
            $dateString = $resultDate->format('Y-m-d');

            $isWeekend = $resultDate->isWeekend();
            $isHoliday = in_array($dateString, $holidaySet);
            if (!$isWeekend && !$isHoliday) {
                $workDaysCount++;
            }
        }

        return $resultDate->format('Y-m-d');
    }
}
