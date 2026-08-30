<?php

return [
    // Used to derive a "late" attendance status. Configurable because
    // working hours differ per agency and country.
    'work_start_time' => env('HRM_WORK_START_TIME', '09:30'),
    'work_end_time' => env('HRM_WORK_END_TIME', '18:00'),
];
