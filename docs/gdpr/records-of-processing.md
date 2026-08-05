# Records of Processing Activities (Article 30 GDPR)

| Field | Value |
| --- | --- |
| Controller | Value of `APP_OWNER`, `APP_OWNER_ADDRESS`, `APP_OWNER_EMAIL`, `APP_OWNER_FISCAL_CODE` (deployment environment) |
| Document version | 2026-08-05 |
| Review cycle | Every 12 months, or on any change of purpose, recipients, retention, or transfers |
| Status | Current; the controller must keep the named host and its subprocessors verified |

## 1. Processing activities

| # | Activity | Data subjects | Categories of personal data | Recipients | Retention | Transfers outside EEA | Security measures |
| --- | --- | --- | --- | --- | --- | --- | --- |
| A | Club account and authentication (registration, approval, login, recovery) | Club contacts | Name, email, phone, address, affiliation, fiscal/federal identifiers, password hash, approval timestamp, throttling evidence | Controller's hosting provider and authorized personnel; the club itself | Until an administrator deletes the club; workflow tokens 1h (recovery) / 24h (confirmation), deleted on success or by daily purge | None by design | Prepared statements, CSRF, throttling, strict sessions, password policy |
| B | Athlete archive management (add, edit, import, export, reconciliation) | Athletes incl. minors | Last/first name, birth date, gender, weight, belt, membership number, free-text notes, import/export files | Owning club; administrators; hosting provider; CSV recipients chosen by the club | Until the club or an administrator deletes the athlete; closed-event snapshots see row D | None by design | Per-club scoping, ownership constraints, quotas, upload policies |
| C | Event publication and registration (entries, options, fees, recaps) | Athletes, clubs | Registration data, selected options and fees, event exceptions, recap emails | Organizer/administrators, owning club, hosting/mail provider | Until the event is deleted; snapshots see row D | None by design | Authorization gates, CSRF, prepared statements, entry ownership FK |
| D | Closed-event consolidation (snapshots) and aggregate statistics | Athletes, clubs | Frozen copy of registered athlete data and categories; aggregate club totals | Administrators; public (aggregate totals only) | 1 year from snapshot/event date, then daily purge deletes them | None by design | No public nominal results, indexed purge, per-club scoping |
| E | Payment instruction handling | Organizer | SEPA account holder, IBAN, BIC of the organizer | Participants' clubs; hosting provider | Until the event is deleted | None by design | Displayed only within event context |
| F | Event document uploads | Athletes, clubs | PDF/JPEG/PNG event documents as provided by organizers | Public site visitors (published events), hosting provider | Deleted on replacement or event deletion | None by design | MIME/size limits, generated names, sandboxed directory, forced download for PDFs |
| G | Security and technical data | Visitors, clubs, administrators | Session identifiers, throttling evidence, application error logs, correlation IDs | Hosting provider; authorized operators | Sessions 30 min idle / 12 h absolute; logs per `APP_LOG_RETENTION_DAYS` (host-managed rotation) | None by design | Redacting failure path, per-request correlation IDs, nonce-based CSP |
| H | Hosting and backups | All data subjects above | Full copy of the application database and uploads | Hosting provider (`APP_WEBHOST`, currently Aruba Linux Basic) and its subprocessors | Backups per `APP_BACKUP_RETENTION_DAYS` (host-managed expiry) | None by design; subprocessors and safeguards to be verified by the controller | Host access controls, encrypted transport, restore tests per deployment runbook |

## 2. Lawful bases used

- Article 6(1)(b): account creation, service access, pre-contractual steps.
- Article 6(1)(f): athlete and event processing — see
  [Legitimate Interest Assessment](lia.md).
- Consent is not relied upon; the Art 14 delivery warranty recorded for each
  club is not the athlete's consent.

## 3. Notes and obligations

- No special categories (Article 9) are collected by design; free-text fields
  are discouraged for sensitive content.
- No automated decisions with legal effects; age/weight categories are
  administrative calculations only.
- The privacy notice at `/privacy` reflects this record; the controller must
  keep notice and record aligned and verify the host's subprocessors and any
  future transfer safeguards (see `docs/deployment.md`).
