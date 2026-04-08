# mediation module

**Status:** Future state. Not a priority to build.

Handles intake and tracking of interpersonal disputes, misconduct reports, safety concerns, and other situations requiring neutral review or formal mediation within OWBN. Highly sensitive — narrow access, strong audit trail, careful data retention.

## Why Future State

This touches real-world harm reporting, privacy, and organizational process in a way that deserves careful design with the people who would actually use it (safety team, exec leadership, legal/compliance considerations). Building it before that collaboration would be premature.

When it's time to build, the design conversation should cover:

- Who can submit (any logged-in member vs. anonymous)
- Who can read (mediation team, HST of affected chronicle, exec only)
- Retention policy (legal hold vs. time-bound deletion)
- Anonymization options
- Integration with OWBN's existing formal processes
- Appeals and escalation paths
- Notification dispatch with strong confidentiality guarantees

Until that design work happens, keep this module disabled in the registry and unlisted in the admin modules page.
