# Notification Batch

Phase 6 notification batch runner for renewal and accident types.

## Command

Run renewal for one tenant:

```bash
php tools/batch/run_renewal_notification.php --date=2026-03-29 --tenant=TE001 --executed-by=1
```

Run accident for one tenant:

```bash
php tools/batch/run_renewal_notification.php --date=2026-03-29 --tenant=TE001 --executed-by=1 --type=accident
```

Run renewal and accident for one tenant:

```bash
php tools/batch/run_renewal_notification.php --date=2026-03-29 --tenant=TE001 --executed-by=1 --type=all
```

Run for all active tenants:

```bash
php tools/batch/run_renewal_notification.php --date=2026-03-29 --executed-by=1
```

Retry failed deliveries from a previous run:

```bash
php tools/batch/run_renewal_notification.php --date=2026-03-29 --tenant=TE001 --executed-by=1 --type=renewal --retry-failed-run-id=123
```

Retry with explicit policy control:

```bash
php tools/batch/run_renewal_notification.php --date=2026-03-29 --tenant=TE001 --executed-by=1 --type=accident --retry-failed-run-id=456 --retry-max-attempts=4 --retry-minutes=30
```

## Notes

- `--type` supports `renewal` (default), `accident`, `all`.
- `--retry-failed-run-id` reprocesses only failed deliveries from the specified run id.
- `--retry-max-attempts` limits how many failed attempts are retried before the item is left as-is and counted as `skip`.
- `--retry-minutes` enforces a minimum wait window between the last failed attempt and the next retry run.
- For enabled routes with provider `lineworks`, the batch actually performs HTTP POST to `webhook_url`.
- LINE WORKS payload uses `title`, `body.text`, `button.label`, and `button.url`.
- `body.text` is required and contains Japanese business text with target count and target list.
- Renewal notifications are split into two fixed messages: `【満期案件通知（早期）】` at 28 days before maturity and `【満期案件通知（直前）】` at 14 days before maturity.
- Accident notifications are sent as `【事故対応リマインド】` when existing reminder rules match.
- The message body starts with a one-line action prompt, then shows `対象件数` and a titled bullet list.
- When the list exceeds 10 items, the message omits the overflow and appends `ほかN件`.
- Internal IDs such as case id, phase id, and rule id are not included in the LINE WORKS message body.
- Button URLs are built from `APP_PUBLIC_URL`; `localhost`, `127.0.0.1`, and `::1` are rejected and result in `failed`.
- Renewal buttons open `?route=renewal/list`; accident buttons open `?route=accident/list`.
- Delivery is recorded as `success` only when webhook returns HTTP 2xx.
- Non-2xx responses and transport exceptions are recorded as `failed` with error details.
- It writes run summaries to `t_notification_run`.
- It writes per-case records to `t_notification_delivery`.
- Idempotency is handled by unique keys on delivery records; reruns become `skip`.
- Route lookup is resolved from common DB tables `tenant_notify_routes` and `tenant_notify_targets` by tenant code.
- Failed deliveries record attempt metadata in `error_message` and use `notified_at` as the last attempted timestamp for retry backoff checks.
