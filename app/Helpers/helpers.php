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

if (!function_exists('format_datetime')) {
    /**
     * Format datetime with time
     * Current year: "14 янв, 12:00"
     * Past years: "14 янв 2025, 12:00"
     */
    function format_datetime($date): ?string
    {
        if (!$date) {
            return null;
        }

        if (is_string($date)) {
            $date = \Carbon\Carbon::parse($date);
        }

        $isCurrentYear = $date->year === now()->year;

        if ($isCurrentYear) {
            return $date->translatedFormat('j M, H:i');
        }

        return $date->translatedFormat('j M Y, H:i');
    }
}

if (!function_exists('format_date')) {
    /**
     * Format date without time
     * Current year: "14 янв"
     * Past years: "14 янв 2025"
     */
    function format_date($date): ?string
    {
        if (!$date) {
            return null;
        }

        if (is_string($date)) {
            $date = \Carbon\Carbon::parse($date);
        }

        $isCurrentYear = $date->year === now()->year;

        if ($isCurrentYear) {
            return $date->translatedFormat('j M');
        }

        return $date->translatedFormat('j M Y');
    }
}