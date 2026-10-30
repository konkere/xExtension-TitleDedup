# Title Dedup: design notes

## Goal

When a new entry arrives in a target feed and an entry with the same normalized title already exists in a source feed of the same rule, mark the target entry as read.

## Scope

- Several rules, each with several source feeds and several target feeds
- One-way by default: sources affect targets, not the other way round
- A feed may be both source and target. Listing all sections of a site on both sides keeps the first copy of each story unread and marks every later copy as read
- Exact match after title normalization

## Out of scope

- Full-text similarity
- Cross-language deduplication
- Changes to the FreshRSS core
- Client-side deduplication

## How it works

### New entries (`entry_before_insert` hook)

FreshRSS keeps new entries in `_entrytmp` until the end of a refresh, then moves them to `_entry`. Lookups therefore cover both tables, so copies fetched in the same refresh are compared too.

On the first new entry of a request that concerns a rule, the titles of the rule's feeds within the lookback window are loaded once, then kept up to date in memory. For each new entry, in arrival order:

1. If the entry belongs to a target feed, is unread, and its title is already known from another source feed, it is a duplicate and is marked as read.
2. If the entry belongs to a source feed, is unread and is not a duplicate, unread target entries with the same title in other feeds are marked as read (the source copy arrived after them).
3. The title is remembered as known for its feed; an unread target entry that is not a duplicate is remembered in case its source arrives later.

A read entry only serves as a reference: it never marks other entries. Otherwise a copy marked by an earlier pass could mark the surviving original.

Updated versions of known entries are ignored.

### One-off backfill (`freshrss_user_maintenance` hook)

After the rules are saved, the next maintenance run replays the existing entries within the lookback window in arrival order (by entry ID), with the same logic as above. Later maintenance runs do nothing, so an entry the user marks as unread again stays unread. Saving the rules again repeats the backfill, which leaves already deduplicated entries unchanged.

## Configuration

Stored per user, as JSON, in the extension's user configuration. Each rule has:

- `name`
- `enabled`
- `source_feed_ids`, `source_feed_names`
- `target_feed_ids`, `target_feed_names`
- `lookback_days`
- `normalize_whitespace`
- `normalize_case`
- `strip_punctuation`

Version 0.1 stored the rules in the system configuration; they are still read from there until the user saves the settings.

## Files

- `metadata.json`
- `extension.php`: hooks, settings form handling, configuration storage
- `configure.php`, `configure.phtml`: settings page
- `lib/DedupService.php`: deduplication logic
- `lib/DedupDAO.php`: database queries
- `lib/TitleNormalizer.php`
- `lib/RuleRepository.php`: parsing and validation of the rules
- `i18n/`: translations
- `examples/sample-config.json`

## Risks

- Identical titles do not always mean the same story, so unrelated entries may be marked as read
- Reposts with a rewritten title are not detected
