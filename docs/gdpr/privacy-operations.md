# Privacy Operations: Data Subject Requests and Breach Management

Reference: Articles 12–17, 33, 34 GDPR. Owner: the controller (value of
`APP_OWNER`); the public contact channel is the privacy email
(`APP_OWNER_EMAIL`), published in the notice at `/privacy`.

| Document version | 2026-08-05 |
| --- | --- |
| Review cycle | Every 12 months, or after any material change of processing or incident |

## 1. Data subject request procedure

### 1.1 Intake

- Requests arrive through the published privacy email (or are received by
  the controller through other channels).
- Acknowledge receipt immediately and log the request in the internal
  request register: date, channel, identity evidence, requested right, and
  the data subjects concerned (including whether any is a minor).

### 1.2 Identity verification

- Verify the requester's identity before acting. For athletes, route the
  request through the owning club when the identity cannot be established
  directly, without revealing the athlete's data to third parties.
- For requests concerning minors, require authority of the parent or
  guardian.

### 1.3 Handling by right

| Right | Handling in this portal |
| --- | --- |
| Access (Art 15) | Provide a complete copy: club self-service CSV export of athlete data; administrator export before club deletion; otherwise extract and provide the records from the database. |
| Rectification (Art 16) | Club-side athlete edit, inline edit, CSV update/reconciliation; administrator event and club editing. |
| Erasure (Art 17) | Club or administrator deletes the athlete (cascades entries and snapshots); administrator deletes the club after offering an export. Closed-event consolidated records follow the documented one-year retention unless erasure applies. |
| Restriction (Art 18) | Not supported in-app: restrict by suspending the club account and documenting the restriction, pending resolution. |
| Portability (Art 20) | Club self-service CSV export (AthleteCsvTransfer) covers the data the club provides. |
| Objection (Art 21) | Legitimate-interest processing: evaluate per the LIA; record the outcome and any restriction applied. |

### 1.4 Deadlines and records

- Answer within one month of identity verification (Art 12(3)); extend by two
  further months only where justified and notified.
- Keep the request register current; every refusal must state the reason and
  inform about the complaint option (Garante per la protezione dei dati
  personali).

## 2. Breach management procedure

### 2.1 Detection and containment

- Detection channels: application error logs with correlation IDs, purge job
  exit status, monitoring/health checks, hosting provider reports, user and
  club reports.
- Containment: suspend affected accounts (admin approval/revocation),
  rotate credentials, take the environment offline if necessary, preserve
  evidence (logs, backups, database state) without altering it.

### 2.2 Assessment

- Establish the categories of data and data subjects involved (including
  minors), the likely consequences (confidentiality, integrity,
  availability) and the actual likelihood/severity of risk, considering the
  existing safeguards (hashed passwords, per-club scoping, retention).
- Document the assessment in the incident log.

### 2.3 Notification

- Notify the Garante per la protezione dei dati personali within 72 hours
  where the breach is likely to result in a risk to rights and freedoms,
  with the elements of Art 33(3) (nature, categories and approximate
  numbers, measures taken, contact details).
- Communicate to data subjects without undue delay where the breach is
  likely to result in a high risk (Art 34), using the club channels when
  athletes' contact details are held by clubs.
- Record each notification, its date, the authority and the outcome.

### 2.4 After-action

- Identify root cause, implement improvements (including, where relevant,
  the repository's remediation process), and record a post-incident review.
- Confirm log and backup evidence for the period is retained per
  `APP_LOG_RETENTION_DAYS` / `APP_BACKUP_RETENTION_DAYS`.

## 3. Logs and evidence

- The internal request and incident registers are operational records of the
  controller; keep them outside public logs and apply the retention the
  controller chooses for operational records.
- Repository tests and documentation cannot prove live hosting behavior:
  the controller must record the periodic restore, expiry, and scheduler
  checks described in `docs/deployment.md` and `docs/security.md`.
