# Legitimate Interest Assessment (LIA)

Reference: Article 6(1)(f) GDPR — balancing test supporting the legitimate
interest basis for athlete and event processing.

| Field | Value |
| --- | --- |
| Controller | Value of `APP_OWNER` (deployment environment), address `APP_OWNER_ADDRESS`, email `APP_OWNER_EMAIL` |
| Document version | 2026-08-05 |
| Review cycle | Every 12 months, or on any change to purposes, data categories, safeguards, or retention |
| Status | Current |

## 1. Purpose and scope

This assessment documents the balancing test that supports processing of
athlete and event data on the legitimate interest basis (Article 6(1)(f)
GDPR), as announced in the public privacy notice:

- managing the club athlete archive (add, edit, import, export, reconcile);
- registering athletes to events, sending registration recaps to clubs, and
  consolidating closed-event records (snapshots);
- publishing aggregate entry and medal totals after an event.

It does not cover processing based on contract (Article 6(1)(b)), which the
notice applies to account creation, service access and pre-contractual steps.

## 2. The legitimate interests pursued

1. **Controller:** operating the competition management portal, protecting it
   against abuse, and running the published event services.
2. **Organizers:** managing event participation, eligibility, entries and
   fees, and preserving a reliable closed-event record.
3. **Participating clubs:** administering their own athlete archives and
   sending registrations without re-typing or paper exchanges.

The processing serves all three interests; the controller's and organizers'
interests coincide with the clubs' operational need to manage competitions.

## 3. Necessity

Athlete and event registration cannot function without the minimal identity
and competition attributes used (last/first name, birth date, gender, weight,
belt, membership number where provided, and notes the club chooses to add).
No alternative with substantially less impact achieves the purpose:

- the public surface is minimized to aggregate totals — no nominal results
  are published by design;
- detailed athlete views are limited to the owning club and to administrators;
- unpublished events reject entry-metadata requests;
- the optional free-text note field is discouraged for sensitive content.

## 4. Impact on data subjects

- **Data subjects:** athletes (including minors) and club contacts.
- **Data categories:** identity, birth date, gender, weight, belt,
  membership identifier, club contact details, registration and payment
  instruction data, security/technical data. No special categories are
  collected by design (Article 9 is expressly excluded in the notice and
  terms).
- **Risk of harm:** low. Data stays within the participating clubs, the
  organizer and the controller; no public nominal results, no profiling,
  no marketing, no automated decisions with legal effects.
- **Children:** particular weight is given to minors' rights: no public
  nominal results, no scoring of individuals, archive access restricted to
  the owning club, and the club must warrant Art 14 notice delivery to the
  parent or guardian before providing data.

## 5. Safeguards already in place

| Area | Safeguard |
| --- | --- |
| Access | Per-club scoping enforced in queries; composite ownership foreign key; administrator access only for the full table |
| Security | Prepared statements, CSRF, throttling, strict sessions, nonce-based CSP, upload sandboxing |
| Growth | Admin approval before activation; athlete and entry quotas (`CLUB_ATHLETE_LIMIT`, `CLUB_ENTRY_LIMIT`) |
| Retention | Closed-event snapshots purged after 1 year; tokens 1h/24h; uploads deleted on replacement or event deletion; log/backup expiry per `APP_LOG_RETENTION_DAYS` / `APP_BACKUP_RETENTION_DAYS` |
| Notice | Public privacy notice (Art 13/14), versioned terms acceptance, versioned Art 14 delivery warranty |
| Rights | Contact channel published; club-side erasure and export; administrator export before club deletion |

## 6. Balancing conclusion

The controller's, organizers' and clubs' interests in operating and
administering competitions are legitimate and the processing is necessary for
them. The impact on data subjects is limited by minimization, per-club
scoping, absence of any public nominal results, restrictive retention and
strong technical safeguards; for minors, the absence of public nominal
results and the mandatory Art 14 delivery warranty weigh decisively in the
balance.

**Conclusion: the legitimate interests outweigh the impact; processing on
the Article 6(1)(f) basis is justified for the scope in section 1.**

## 7. Review

Reassess when any of the following changes: purpose or data categories;
publication behavior; retention periods; safeguards; recipient/subprocessor
composition; or after any data-protection incident. The review must be
recorded in this document.

Historical evidence: June 2026 audit, July 2026 post-remediation review, and
the security baseline in `docs/security.md`.
