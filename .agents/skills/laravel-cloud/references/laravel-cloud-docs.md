# Laravel Cloud Docs Index

Use this file as the first stop, then open only the linked official pages needed for the task.

Primary docs root:
- https://cloud.laravel.com/docs/intro

## Onboarding and Architecture

- Quick start and concepts:
  - https://cloud.laravel.com/docs/intro
- Projects, environments, and branching:
  - https://cloud.laravel.com/docs/environments
  - https://cloud.laravel.com/docs/branches
- Team access:
  - https://cloud.laravel.com/docs/teams

## Deployment and Runtime

- Deployments and command lifecycle:
  - https://cloud.laravel.com/docs/deployments
- Build command:
  - https://cloud.laravel.com/docs/build-commands
- Deploy command:
  - https://cloud.laravel.com/docs/deploy-commands
- Runtime commands / one-off commands:
  - https://cloud.laravel.com/docs/commands
- Deploy hooks:
  - https://cloud.laravel.com/docs/deploy-hooks
- Maintenance mode:
  - https://cloud.laravel.com/docs/maintenance-mode

Key constraints to remember:
- Build, deploy, and one-off commands must be non-interactive.
- Command execution windows are time-limited; keep command chains short and deterministic.
- Environment variable and resource updates typically require redeploy to apply to running workloads.

## Compute, Scaling, and Scheduling

- Compute:
  - https://cloud.laravel.com/docs/compute
- Auto-scaling:
  - https://cloud.laravel.com/docs/auto-scaling
- Queues:
  - https://cloud.laravel.com/docs/queues
- Scheduled tasks:
  - https://cloud.laravel.com/docs/scheduling

Operational notes:
- Laravel Cloud queue workers are managed by the platform; restart commands that are common on self-managed infra may be unnecessary.
- Scheduler behavior on replicated compute should be designed to avoid duplicate runs unless intentionally parallelized.
- Hibernating environments can pause background processing.

## Domains, Networking, and Security

- Domains:
  - https://cloud.laravel.com/docs/domains
- Subdomains:
  - https://cloud.laravel.com/docs/subdomains
- Networking:
  - https://cloud.laravel.com/docs/networking
- Environment variables:
  - https://cloud.laravel.com/docs/environment-variables
- Access control:
  - https://cloud.laravel.com/docs/access-control

Operational notes:
- DNS propagation and domain verification timing can be the real blocker even when app config is correct.
- Proxy settings (for example Cloudflare orange-cloud mode) can affect validation and traffic routing expectations.

## Data and Stateful Resources

- Databases:
  - https://cloud.laravel.com/docs/databases
- Caches:
  - https://cloud.laravel.com/docs/caches
- Key-value storage:
  - https://cloud.laravel.com/docs/key-value-storage
- Object storage:
  - https://cloud.laravel.com/docs/object-storage

Operational notes:
- Validate region/attachment requirements and generated environment variables before deep debugging.
- Confirm app-level disk/cache/queue configuration is aligned with attached Cloud resources.

## Observability and Troubleshooting

- Logging:
  - https://cloud.laravel.com/docs/logs
- Metrics:
  - https://cloud.laravel.com/docs/metrics
- Error tracking:
  - https://cloud.laravel.com/docs/error-tracking
- Troubleshooting:
  - https://cloud.laravel.com/docs/troubleshooting

Recommended incident triage order:
1. Confirm deployment and configuration changes around incident time.
2. Correlate app logs with infrastructure metrics.
3. Isolate to app code, command lifecycle, DNS/network, or attached resource.
4. Apply smallest safe fix, redeploy, then verify.

## CI/CD and Integrations

- Git providers and deploy triggers:
  - https://cloud.laravel.com/docs/github
  - https://cloud.laravel.com/docs/gitlab
  - https://cloud.laravel.com/docs/bitbucket
- API:
  - https://cloud.laravel.com/docs/api

Use deploy hooks for commit-pinned deployments from CI workflows.
