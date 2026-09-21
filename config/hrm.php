<?php

return [
    'leave_weekend_days' => array_map('intval', explode(',', env('HRM_LEAVE_WEEKEND_DAYS', '6,0'))),
];