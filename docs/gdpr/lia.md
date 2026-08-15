# Legitimate Interest Assessment (LIA)

Reference: Article 6(1)(f) GDPR — balancing test supporting the legitimate
interest basis for athlete and event processing.

| Field | Value |
| --- | --- |
| Controller | Value of `APP_OWNER` (deployment environment), address `APP_OWNER_ADDRESS`, email `APP_OWNER_EMAIL` |
| Document version | 2026-08-15 |
| Review cycle | Every 12 months, or on any change to purposes, data categories, safeguards, or retention |
| Status | Current |

## 1. Purpose and scope

This assessment documents the balancing test that supports processing of
athlete and event data on the legitimate interest basis (Article 6(1)(f)
GDPR), as announced in the public privacy notice:

- managing the club athlete archive (add, edit, import, export, reconcile);
- registering athletes to events, sending registration recaps to clubs, and
  consolidating closed-event records (snapshots);
- giving authenticated representatives of clubs participating in an event a
  minimized roster after registrations are closed to that club, so they can
  verify entries and categories and coordinate event logistics;
- publishing aggregate entry and medal totals after an event.

It does not cover processing based on contract (Article 6(1)(b)), which the
notice applies to account creation, service access and pre-contractual steps.

## 2. The legitimate interests pursued

1. **Controller:** operating the competition management portal, protecting it
   against abuse, and running the published event services.
2. **Organizers:** managing event participation, eligibility, entries and
   fees, and preserving a reliable closed-event record.
3. **Participating clubs:** administering their own athlete archives, sending
   registrations without re-typing or paper exchanges, verifying the closed
   roster and competition categories, and coordinating coaches and event
   logistics against the same information.

The processing serves all three interests; the controller's and organizers'
interests coincide with the clubs' operational need to manage competitions.

## 3. Necessity

Athlete and event registration cannot function without the minimal identity
and competition attributes used (last/first name, birth date, gender, weight,
belt, membership number where provided, and notes the club chooses to add).
No alternative with substantially less impact achieves the purpose:

- the public surface is minimized to aggregate totals — no nominal results
  are published by design;
- the cross-club roster is not public, is available only after registrations
  are closed to that club, and is limited to clubs with an entry in that event;
- access requires authentication, current Terms acceptance and a current
  Article 14 notice-delivery warranty;
- the shared projection contains only name, club, age class, weight category,
  gender and belt; exact birth date, exact weight, membership number, notes,
  contacts and payment data remain restricted to narrower roles;
- there is no cross-club CSV export, while an owning club may still export only
  its own entries;
- unpublished events reject entry-metadata requests;
- the optional free-text note field is discouraged for sensitive content.

## 4. Impact on data subjects

- **Data subjects:** athletes (including minors) and club contacts.
- **Data categories:** identity, birth date, gender, weight, belt,
  membership identifier, club contact details, registration and payment
  instruction data, security/technical data. No special categories are
  collected by design (Article 9 is expressly excluded in the notice and
  terms).
- **Risk of harm:** moderate before safeguards because the roster contains
  identities of athletes, including minors, disclosed to other participating
  clubs. Residual risk is reduced by the narrow recipient group and field set,
  absence of public access and cross-club export, short authenticated sessions,
  contractual confidentiality, retention, and data-subject rights.
- **Children:** particular weight is given to minors' rights: no public
  nominal results, no exact birth dates or weights in the shared roster, no
  scoring of individuals, live archive access restricted to the owning club,
  and the club must warrant delivery of the updated Article 14 notice to the
  parent or guardian before cross-club roster access is enabled.

## 5. Safeguards already in place

| Area | Safeguard |
| --- | --- |
| Access | Live archives and exports remain per-club scoped; composite ownership foreign key; shared roster limited to participating authenticated clubs after closure, current agreements and removal of that club's registration exception; administrator access only for the full table |
| Security | Prepared statements, CSRF, throttling, strict sessions, nonce-based CSP, upload sandboxing |
| Growth | Admin approval before activation; athlete and entry quotas (`CLUB_ATHLETE_LIMIT`, `CLUB_ENTRY_LIMIT`) |
| Retention | Closed-event snapshots purged after 1 year; tokens 1h/24h; uploads deleted on replacement or event deletion; log/backup expiry per `APP_LOG_RETENTION_DAYS` / `APP_BACKUP_RETENTION_DAYS` |
| Notice | Public privacy notice (Art 13/14), versioned terms acceptance, versioned Art 14 delivery warranty |
| Rights | Contact channel published; club-side erasure and export; administrator export before club deletion |

## 6. Balancing conclusion

The controller's, organizers' and participating clubs' interests in operating,
verifying and administering competitions are legitimate. A common roster is
necessary for the documented verification and coordination purpose, while a
public roster or disclosure of exact source facts is not. The impact on data
subjects is limited by recipient and field minimization, lifecycle and current-
agreement gates, absence of public nominal results or cross-club export,
restrictive retention and technical safeguards. For minors, exclusion of exact
birth dates and weights, absence of public nominal results, and the mandatory
updated Article 14 delivery warranty weigh decisively in the balance.

**Conclusion: the legitimate interests outweigh the impact; processing on
the Article 6(1)(f) basis is justified for the scope in section 1.**

## 7. Review

Reassess when any of the following changes: purpose or data categories;
publication behavior; retention periods; safeguards; recipient/subprocessor
composition; or after any data-protection incident. The review must be
recorded in this document.

Historical evidence: June 2026 audit, July 2026 post-remediation review, and
the security baseline in `docs/security.md`. The 2026-08-15 review added the
minimized closed-roster purpose, recipients, necessity test, and safeguards.
