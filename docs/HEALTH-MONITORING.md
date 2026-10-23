# POS Endpoint Health Monitoring

The Router checks active customer POS endpoints every five minutes through `router:check-pos-health`. The command processes stores in ID order, in batches of 100, one endpoint at a time. This keeps outbound concurrency bounded to one process and reuses the manual checker's HTTPS, DNS/IP pinning, redirect blocking, 3-second connect timeout, 6-second request timeout, and response-size limit.

Manual and scheduled checks share a cache lock for each store. A duplicate check skips that store without writing a false offline result. The scheduled command also uses Laravel's scheduler overlap lock.

Health history is pruned daily at 02:30 by `router:prune-health-checks`. The default retention is 30 days. The latest recorded result for each store is retained even if it is older than the configured window. Configure `ROUTER_HEALTH_RETENTION_DAYS` to change the window; values below one day are treated as one day.

In local development, run `php artisan schedule:work` in a separate terminal. Production must run Laravel's scheduler once per minute, for example:

```cron
* * * * * cd /path/to/router && php artisan schedule:run >> /dev/null 2>&1
```

The default file cache provides process locks on a single Router host. If the Router runs on multiple hosts, configure a shared lock-capable cache such as Redis or the database cache store so that all scheduler instances and web workers coordinate through the same locks.

No external alerting is configured. The System Health page reports the latest status and response time, last successful check and its POS/API versions, and a slow indicator for successful responses taking at least two seconds.
