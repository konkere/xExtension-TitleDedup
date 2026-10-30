# Changelog

## 0.2.0

- Duplicates fetched in the same refresh are now detected: lookups also cover `_entrytmp`, where FreshRSS keeps new entries until the end of the refresh.
- A source entry that arrives after its target copy marks that copy as read immediately, instead of waiting for maintenance.
- Titles are loaded once per rule and per request instead of once per entry; the maintenance backfill is linear instead of quadratic.
- The maintenance backfill runs once after the rules are saved, so entries manually marked as unread are no longer marked as read again on every refresh.
- The punctuation option now works; whitespace, case and punctuation have separate checkboxes.
- Rules are stored per user (user extension). Rules from 0.1 are still read from the system configuration until saved again.
- Titles are compared after decoding HTML entities.
- Interface translated via FreshRSS i18n: English, Russian, Chinese.
- Updated entries are no longer re-checked.
