<?php

if (!function_exists('format_grade_range')) {
    function format_grade_range(array $grades): string 
    {
        if (empty($grades)) {
            return '';
        }
        
        sort($grades);
        $ranges = [];
        $start = $grades[0];
        $prev = $grades[0];
        
        for ($i = 1; $i <= count($grades); $i++) {
            if ($i == count($grades) || $grades[$i] != $prev + 1) {
                if ($start == $prev) {
                    $ranges[] = $start;
                } else {
                    if ($prev - $start === 1) {
                        $ranges[] = "$start, $prev";
                    } else {
                        $ranges[] = "$start-$prev";
                    }
                }
                if ($i < count($grades)) {
                    $start = $grades[$i];
                }
            }
            if ($i < count($grades)) {
                $prev = $grades[$i];
            }
        }
        
        $result = implode(', ', $ranges);
        return Str::ucfirst($result . (count($grades) === 1 ? ' класс' : ' классы'));
    }
} 