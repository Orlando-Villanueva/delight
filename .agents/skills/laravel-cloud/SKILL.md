---
name: laravel-cloud
description: Laravel Cloud deployment and production operations guidance using official docs. Use when handling Laravel Cloud setup, environment configuration, build or deploy commands, domains or DNS, compute scaling, queues or scheduling, databases/caches/object storage, observability, or production troubleshooting.
---

# Laravel Cloud

Use official Laravel Cloud documentation first. Start with [references/laravel-cloud-docs.md](references/laravel-cloud-docs.md) and read only the sections relevant to the user request.

## Workflow

1. Identify the task category: onboarding, deploy/build issue, runtime incident, resource attachment, networking/domain, scaling, or cost/performance.
2. Gather minimum context:
   - Organization, app, and environment name
   - Branch/commit and deployment timestamp
   - Exact error output and stage (build, deploy, runtime, queue, scheduler, DNS)
   - Recent changes to environment variables, resources, or domain records
3. Open the matching docs section and produce:
   - Likely cause
   - Ordered remediation steps
   - Verification checks
   - Prevention/follow-up

## Guardrails

- Prioritize low-risk production guidance and explicit rollback paths.
- Warn before destructive actions and mention required production flags (for example `php artisan migrate --force`).
- Avoid recommending commands Laravel Cloud treats as unnecessary (`queue:restart`, `horizon:terminate`, `storage:link`, `optimize:clear`).
- Treat build/deploy/command jobs as non-interactive and time-bounded.
- Remind users that environment/resource changes generally require a redeploy before changes take effect.

## Playbooks

### Deployment failures

- Confirm whether failure occurred in Build Command, Deploy Command, or runtime command execution.
- Keep deploy commands minimal and deterministic.
- If command timeout symptoms appear, split/shorten command sequences and move heavy setup into build.
- Suggest deploy hooks when a specific commit deployment from CI is required.

### Queue and scheduler issues

- Choose the right execution model:
  - Queue cluster for production queue throughput and scaling controls
  - Worker cluster for dedicated background worker processes
  - Background process on app cluster for low-volume/dev workloads
- For scheduled tasks with replicated app compute, use `onOneServer` unless multiple parallel executions are intentional.
- Account for hibernation behavior when debugging jobs/tasks that stop running.

### Domain and DNS issues

- Separate Laravel Cloud domain behavior from custom domain behavior.
- Verify DNS record type/name/value, proxy mode, and propagation windows.
- Use pre-verification guidance for zero-downtime domain cutovers.

### Data and storage connectivity

- Confirm resource region and attachment constraints before troubleshooting credentials.
- Validate Cloud-injected environment variables and check accidental overrides.
- For object storage issues, verify disk config, visibility, and CORS/allowed-origins assumptions.

### Observability and incidents

- Correlate deployment events, logs, and resource metrics before proposing changes.
- Ask for precise timestamps and impacted endpoints/jobs to reduce guesswork.
- If retention windows are exceeded, request exported logs/metrics from the user.

## Response Template

1. Likely cause
2. Exact fix steps
3. Verification steps
4. Preventive hardening
