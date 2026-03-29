# Notification Batch

Phase 6 renewal notification batch runner.

## Command

Run for one tenant:

```bash
php tools/batch/run_renewal_notification.php --date=2026-03-29 --tenant=TE001 --executed-by=1
```

Run for all active tenants:

```bash
php tools/batch/run_renewal_notification.php --date=2026-03-29 --executed-by=1
```

## Notes

- This runner targets `renewal` notification type.
- It writes run summaries to `t_notification_run`.
- It writes per-case records to `t_notification_delivery`.
- Idempotency is handled by unique keys on delivery records; reruns become `skip`.
- Route lookup is resolved from common DB tables `tenant_notify_routes` and `tenant_notify_targets` by tenant code.
