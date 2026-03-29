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
- It writes run summaries to `t_notification_run`.
- It writes per-case records to `t_notification_delivery`.
- Idempotency is handled by unique keys on delivery records; reruns become `skip`.
- Route lookup is resolved from common DB tables `tenant_notify_routes` and `tenant_notify_targets` by tenant code.
- Failed deliveries record attempt metadata in `error_message` and use `notified_at` as the last attempted timestamp for retry backoff checks.
