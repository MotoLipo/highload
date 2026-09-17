CREATE TABLE IF NOT EXISTS analytics.events (
    id UUID,
    event_type String,
    user_id UInt64,
    payload String,
    created_at DateTime
) ENGINE = MergeTree()
    PARTITION BY toDate(created_at)
    ORDER BY (toDate(created_at), user_id)
    TTL created_at + INTERVAL 90 DAY DELETE
    SETTINGS index_granularity = 8192;