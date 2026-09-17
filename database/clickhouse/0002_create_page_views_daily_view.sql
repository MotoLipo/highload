CREATE MATERIALIZED VIEW IF NOT EXISTS analytics.page_views_daily
ENGINE = AggregatingMergeTree
PARTITION BY toDate(date)
ORDER BY (date)
AS SELECT
    toDate(created_at) AS date,
    countState()                    AS views,
    uniqState(user_id)              AS unique_visitors
   FROM analytics.events
   WHERE event_type = 'page_view'
   GROUP BY date;