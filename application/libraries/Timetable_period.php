<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Timetable_period
{
    public function today()
    {
        return new DateTimeImmutable('today', new DateTimeZone('Africa/Cairo'));
    }

    public function latest_month($today = null)
    {
        $today = $today ?: $this->today();
        $next = $today->modify('first day of next month');
        return ($today >= $next->modify('-10 days') ? $next : $today)->format('Y-m');
    }

    public function dates($month, $week = 'all')
    {
        if (!is_string($month) || !preg_match('/^[1-9][0-9]{3}-(0[1-9]|1[0-2])$/D', $month) ||
            $month > $this->latest_month() || $month < '2024-01') {
            throw new InvalidArgumentException('الشهر غير متاح.');
        }
        $first = new DateTimeImmutable($month . '-01');
        $start = $first->modify('-' . $first->format('w') . ' days');
        if ($week !== 'all' && (!is_string($week) || !preg_match('/^[0-5]$/D', $week))) {
            throw new InvalidArgumentException('الأسبوع غير صحيح.');
        }
        $dates = array();
        for ($day = $first; $day->format('Y-m') === $month; $day = $day->modify('+1 day')) {
            $index = (int) floor($start->diff($day)->days / 7);
            if ($week === 'all' || $index === (int) $week) $dates[] = $day->format('Y-m-d');
        }
        if (!$dates) throw new InvalidArgumentException('الأسبوع غير موجود.');
        return $dates;
    }

    public function allowed_type($role, $type)
    {
        return in_array($type, $role === 'consultant'
            ? array('am', 'pm', 'store_am', 'store_pm', 'off', 'note', '')
            : array('am', 'pm', 'off', 'note', ''), true);
    }
}
