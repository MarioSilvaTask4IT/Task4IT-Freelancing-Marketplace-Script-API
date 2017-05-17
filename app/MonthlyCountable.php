<?php

namespace Task4ItAPI;

trait MonthlyCountable
{
    //empty data
    private $counters = [
            'January' => 0,
            'February' => 0,
            'March' => 0,
            'April' => 0,
            'May' => 0,
            'June' => 0,
            'July' => 0,
            'August' => 0,
            'September' => 0,
            'October' => 0,
            'November' => 0,
            'December' => 0,
        ];

    /**
     * Counts data per month, filling in the zero counters months
     * @param  int   $year
     * @param  array $data - must be in the same format as $this->counters
     * @return array $counters
     */
    public function monthlyCount($year, $data)
    {
        $counters = $this->counters;

        //update  previous array with the data from the db
        foreach ($data as $item) {
            $counters[$item['month']] = (int) $item['count'];
        }

        return $counters;
    }
}
