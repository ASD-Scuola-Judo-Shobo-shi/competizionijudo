# Security Baseline

Last reviewed: 2026-08-03

Code baseline: `ba11669` (`fix(security): harden HTTP and reset token handling`)

This document is the current repository-level security summary. It supersedes
the status conclusions—not the historical evidence—in the June audit and July
post-remediation review. The review covered application code, routes, database
access, sessions, CSRF, password recovery, rendering, uploads, deployment
artifacts, and automated checks. It was a static and test-backed review, not a
live penetration test or hosting-control-panel audit.

## Implemented controls

| Boundary | Current control |
| --- | --- |
| Routing and authorization | `routes/web.php` declares top-level club and administrator policies. Controllers and repositories retain workflow and ownership checks. |
| State changes | Mutations use POST, validate CSRF, validate server-side input, and return controlled errors. Destructive GET routes are rejected. |
| Sessions | Production and development use environment/path-specific cookie names and contexts. Cookies are strict-mode, cookie-only, HttpOnly, SameSite=Lax, and Secure on HTTPS. Authentication rotates the session identifier and creates one exclusive typed principal. |
| Credentials | Passwords use PHP password hashing and a minimum 12-character policy. Login and recovery attempts use persistent hashed throttle keys. Production recovery responses do not reveal account existence or raw tokens. |
| Reset tokens | Tokens contain 256 bits of randomness, are stored only as SHA-256 hashes, expire after one hour, are issued transactionally under a club-row lock, and are consumed once in a transaction. Password replacement invalidates outstanding tokens. |
| Browser policy | Dynamic responses enforce CSP, deny framing, disable MIME sniffing, restrict referrers and browser permissions, and emit HSTS on HTTPS. Authentication and token pages are non-cacheable; token pages use `no-referrer`. |
| Hosting boundary | The shared and per-artifact Apache rules reject sensitive files and directories before existing files can bypass the front controller. Unknown hosts are rejected and HTTP redirects use fixed canonical destinations. |
| Database access | Request-derived values use prepared statements. Sort expressions are selected from explicit allowlists. Athlete registration writes scope the athlete and club together. |
| Output and exports | Templates escape untrusted HTML. CSV exports neutralize spreadsheet formula prefixes. Production exceptions are logged through the redacting failure path and rendered generically. |
| Uploads | Event documents are limited by size and detected MIME type to PDF, JPEG, or PNG, stored under generated names, served with `nosniff`, and purged on replacement or event deletion. |
| Privacy | Public club projections are minimized, registration records the versioned athlete-data-rights declaration, and closed-entry snapshots have a scheduled one-year purge workflow. |
| Release assurance | CI validates syntax, coding style, PHPStan, PHPUnit, changed-code coverage, locked dependency advisories, MySQL migrations, the exact deployment manifest, bilingual artifact boot, and root-router staging. |

The enforced CSP still permits inline scripts and styles because current
templates contain inline behavior and dynamic style values. It provides origin,
form, framing, object, and resource restrictions, but removing `unsafe-inline`
requires a separate nonce or external-asset migration.

## Remaining priorities

These are current limitations, not claims of an active exploit:

| Priority | Limitation | Required direction |
| --- | --- | --- |
| High | Administration uses one shared environment-defined identity without a second factor or per-operator audit identity. | Introduce individual administrators, revocation, a selected strong second factor, and break-glass recovery. |
| High | Authenticated sessions have no explicit 30-minute idle limit, 12-hour absolute limit, or credential-version revocation after password changes. | Add a persistent credential/session version and enforce both time boundaries centrally. |
| High | Email-confirmed public club registration has no administrator approval state or account-level athlete/registration quotas. | Add the approved lifecycle and configurable quotas with abuse and concurrency tests. |
| High defense-in-depth | The application scopes entry creation, but MySQL does not enforce that `entries.club_id` owns `entries.athlete_id` with one composite foreign key. | Add a mismatch preflight, supporting composite key, and forward-only MySQL migration. |
| Medium | Authentication throttling combines account and network in one key, and check/record are separate operations. Unknown identities also avoid equivalent password work. | Use atomic multi-dimensional limits and constant-work credential verification without creating an account-enumeration response. |
| Medium | CSP permits inline scripts and styles. | Move behavior to external assets or use per-response nonces, then remove `unsafe-inline`. |
| Medium | Individual administrator mutations are not recorded in a durable audit trail; host backup, restore, rotation, and scheduled purge evidence remain operational controls. | Add actor-aware audit events and record periodic restore/retention checks outside public logs. |

Schema, authentication-lifecycle, quota, and second-factor work changes persistent
or user-visible behavior and should be implemented as separate reviewed changes,
not folded into unrelated maintenance.

## Verification

Run the narrowest regression while editing, followed by:

```sh
composer check
composer test:coverage:changed
composer deploy:preflight
git diff --check
```

Database or migration changes also require `composer test:migrations`. Before a
push, run `composer ci`; the pre-push hook runs the same gate. The dependency
audit requires current Packagist access, and the artifact smoke test requires a
free loopback port.

After deployment, perform and record the live Apache/header checks in
[deployment.md](deployment.md#post-deployment-security-verification). Repository
tests cannot prove the hosting provider loaded `.htaccess`, enabled the required
Apache modules, configured least-privilege database grants, rotated logs,
expired backups, delivered mail, or scheduled privacy purges.

## Historical evidence

- [June 2026 audit](audit.md), [roadmap](roadmap.md), and
  [execution tracker](tracking.md)
- [July 2026 post-remediation review](review-tracking-2026-07-13.md)
- [Historical remediation continuation prompt](prompt.md)

Historical documents remain immutable evidence for their named revisions. When
current behavior changes, update this baseline and its tests rather than
rewriting old findings as though they had never existed.
