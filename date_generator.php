<?php
function generateNextThirtyOneDays() {
    $dates = [];
    $currentDate = new DateTime();

    for ($i = 0; $i < 31; $i++) {
        $date = clone $currentDate;  // Clone to avoid modifying the original object
        $date->add(new DateInterval('P' . $i . 'D')); // Add i days

        $dates[] = [
            'date' => $date->format('Y-m-d'), // Date in YYYY-MM-DD format (for database)
            'formattedDate' => $date->format('l, d F Y')  // e.g., "Monday, 17 July 2024" (UK format)
        ];
    }

    return $dates;
}
?>