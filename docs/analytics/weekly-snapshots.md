# Weekly Analytics Snapshots

This endpoint provides machine-readable weekly analytics snapshots for Codex automation and downstream analysis.

## Environment Setup

Set snapshot auth token in `.env`:

```bash
ANALYTICS_EXPORT_TOKEN=
```

- `ANALYTICS_EXPORT_TOKEN` is required and used for Bearer auth.

Generate a secure token:

```bash
php -r "echo bin2hex(random_bytes(32));"
```

## Endpoint

- Method: `GET`
- URL: `/admin/analytics/snapshot`
- Auth:
  - Admin session auth, or
  - `Authorization: Bearer <token>`
- Rate limit: `60` requests per minute

## Behavior

- Token-authenticated callers read the live endpoint snapshot response.
- Token-authenticated callers cannot request `fresh=1`.
- Admin-session callers keep live compute behavior, including `fresh=1`.
- Successful token responses include `X-Analytics-Snapshot-Id: <iso_week>@<snapshot_generated_at>`.

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

## Error Responses

`422` when token caller passes `fresh=1`:

```json
{
  "error": {
    "code": "fresh_not_allowed_for_token",
    "message": "Query parameter fresh=1 is not allowed for token-authenticated callers."
  }
}
```

## QA Commands

Successful token fetch:

```bash
curl -sS \
  -H "Authorization: Bearer $ANALYTICS_EXPORT_TOKEN" \
  "http://delight.test/admin/analytics/snapshot" -D - | sed -n '1,20p'
```

Forbidden without auth:

```bash
curl -i "http://delight.test/admin/analytics/snapshot"
curl -i -H "Authorization: Bearer wrong" "http://delight.test/admin/analytics/snapshot"
```

Token caller `fresh=1` rejected:

```bash
curl -sS \
  -H "Authorization: Bearer $ANALYTICS_EXPORT_TOKEN" \
  "http://delight.test/admin/analytics/snapshot?fresh=1" | jq .
```

## Local Codex Automation Storage Convention

Store local analysis artifacts at:

`/Users/orlando/Projects/Agents/sentinel/knowledge/delight-weekly-audits/{YYYY}/{YYYY}-W{ww}.json`
