# Multi-session Implementation Prompt

Use the following prompt at the beginning of each implementation session.

---

Continue the Competizioni Judo remediation roadmap in this repository.

Mandatory startup:

1. Read `audit.md`, `roadmap.md`, `tracking.md`, and this prompt completely.
2. Inspect `git status --short --branch`, `git log --oneline -10`, and the diff for any existing changes. Existing changes are user-owned; do not discard, overwrite, or reformat unrelated work.
3. Read the full roadmap section for the active commit in `tracking.md`. If no commit is active, select the first pending commit whose dependencies are complete. If none exists, report the roadmap complete and do not manufacture a new remediation item. Do not skip ahead merely because a later task is easier.
4. Confirm the underlying finding still exists in the current code. If it has already been fixed, verify its acceptance criteria and record the actual commit instead of reimplementing it.

Execution contract:

- Work on exactly one roadmap commit ID unless the user explicitly requests a larger batch.
- Before editing, mark that row `[/]` and update “Current focus” in `tracking.md`.
- Keep the diff limited to the files and tests necessary for that commit. Do not combine cleanup, renaming, dependency upgrades, or formatting outside scope.
- Include a regression test in the same commit for every security, data-integrity, or bug fix.
- Use new forward migrations. Assume existing migrations may already be applied; never rewrite history to repair a deployed schema.
- Preserve authorization at the server/database boundary. Never trust hidden fields, query parameters, client-side validation, or IDs rendered by the UI.
- Never print or commit `.env` values, passwords, reset tokens, session IDs, personal athlete data, or production records. Use synthetic fixtures.
- Prefer the existing framework-free MVC design and small explicit components. Do not introduce a large framework or generic abstraction unless the active roadmap item requires it.
- Do not change product behavior that is listed as an open decision in `tracking.md`. If the active task truly depends on that decision, exhaust independent work, mark the row `[!]`, record the exact blocker, and stop.

Verification:

1. Run the narrowest relevant test/check while iterating.
2. Review the final diff for scope, authorization, validation, error handling, migration safety, and accidental generated/editor text.
3. Run the active roadmap item's acceptance checks.
4. Run `composer check`. If dependency audit alone cannot run because network access is unavailable, run and report the other checks separately and leave audit status as “not verified.” Do not call the full gate successful.
5. For migration work, run clean and upgrade schema tests. For build/deploy work, build and boot the exact artifact. For query work, record query-count or `EXPLAIN` evidence.

Completion:

- If all acceptance checks pass, mark the row `[x]`, add the commit hash and concise verification evidence, append a session-log row, and set “Current focus” to the next eligible commit.
- Create the planned conventional commit with the roadmap's subject. Include the `tracking.md` status update in that commit so code and tracking do not diverge.
- If checks fail, do not mark complete and do not create a success commit. Leave a concise failure record and either continue fixing within scope or mark a genuine external blocker.
- End with a short report containing: commit ID and hash (if created), outcome, files changed, checks run/results, remaining risk, and exact next commit ID.

Start with the active commit shown in `tracking.md`; if the tracker is complete,
verify that state and perform only explicitly requested follow-up work.

---

## Prompt maintenance

Update this prompt only when the execution protocol itself changes. Progress belongs in `tracking.md`; technical rationale belongs in `audit.md`; commit ordering and acceptance criteria belong in `roadmap.md`.
