<?php

return [
    // SLO: 50K+ строк/сек в ClickHouse. events:flush-buffer теперь работает как
    // постоянный демон (см. FlushEventsBufferCommand), а не разовая cron-задача
    // раз в 30 секунд, поэтому батч может быть большим без риска раздувания
    // задержки между записями.
    'batch_size' => env('EVENTS_BATCH_SIZE', 50000),
    'buffer_key' => env('EVENTS_BUFFER_KEY', 'analytics:events:buffer'),
];
