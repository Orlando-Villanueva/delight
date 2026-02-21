# Weekly Analytics Snapshots

This endpoint provides machine-readable weekly analytics snapshots for local Codex automation and downstream analysis.

## Environment Setup

Add a token to your `.env`:

```bash
ANALYTICS_EXPORT_TOKEN=
```

Generate a secure token:

```bash
php -r "echo bin2hex(random_bytes(32));"
```

## Endpoint

- Method: `GET`
- URL: `/admin/analytics/snapshot`
- Auth:
  - Admin session auth, or
  - `X-Analytics-Token` header
- Rate limit: `60` requests per minute

## Query Parameters

- `fresh=1`
  - Clears `admin_analytics_stats_v1` before reading metrics.
  - Use when you need uncached values in automation.

## Response Shape

```json
{
  "schema_version": "admin_analytics_weekly_v1",
  "snapshot_generated_at": "2026-02-21T09:00:00-05:00",
  "audit_week": {
    "timezone": "America/New_York",
    "iso_week": "2026-W08",
    "week_start": "2026-02-16",
    "week_end": "2026-02-22"
  },
  "metrics": {
    "generated_at": "2026-02-21T09:00:00-05:00",
    "onboarding": {},
    "activation": {},
    "churn_recovery": {},
    "current_stats": {},
    "weekly_activity_rate": 0,
    "insights": []
  }
}
```

## QA Commands

Successful fetch:

```bash
curl -sS \
  -H "X-Analytics-Token: $ANALYTICS_EXPORT_TOKEN" \
  "http://delight.test/admin/analytics/snapshot" | jq .
```

Forbidden without auth:

```bash
curl -i "http://delight.test/admin/analytics/snapshot"
curl -i -H "X-Analytics-Token: wrong" "http://delight.test/admin/analytics/snapshot"
```

Cache reuse and bypass:

```bash
A=$(curl -sS -H "X-Analytics-Token: $ANALYTICS_EXPORT_TOKEN" "http://delight.test/admin/analytics/snapshot" | jq -r '.metrics.generated_at')
sleep 2
B=$(curl -sS -H "X-Analytics-Token: $ANALYTICS_EXPORT_TOKEN" "http://delight.test/admin/analytics/snapshot" | jq -r '.metrics.generated_at')
C=$(curl -sS -H "X-Analytics-Token: $ANALYTICS_EXPORT_TOKEN" "http://delight.test/admin/analytics/snapshot?fresh=1" | jq -r '.metrics.generated_at')
echo "A=$A"
echo "B=$B"
echo "C=$C"
```

Expected:

- `A == B`
- `C != B`

## Local Codex Automation Storage Convention

Store weekly snapshots locally at:

`/Users/orlando/Projects/Agents/sentinel/knowledge/delight-weekly-audits/{YYYY}/{YYYY}-W{ww}.json`
