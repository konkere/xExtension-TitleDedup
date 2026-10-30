# FreshRSS Title Dedup

FreshRSS extension for one-way cross-feed deduplication by title.

Chinese documentation: [README.zh-CN.md](./README.zh-CN.md)

When a new entry arrives in target feed B, the extension marks it as read if the same title has already appeared in source feed A.

## Use Cases

- Multiple feeds publish the same story repeatedly
- You want to keep a primary source unread while skipping reposting feeds
- You want rules scoped to selected feeds instead of one global dedup rule

## Features

- One-way rules: A affects B, but B does not affect A
- Multiple rule groups
- Multiple source feeds and target feeds per rule
- Title normalization: whitespace, case, punctuation (each can be toggled separately)
- Works in both directions in time: a target entry is marked read whether its source copy arrived before it, in the same refresh, or later
- One-off backfill of existing entries after the rules are saved
- Per-user rules
- Visual configuration page with existing feed selection, translated (English, Russian, Chinese)

## How it works

- When a new entry is fetched, its title is compared with the titles of the source feeds within the lookback window, including entries fetched earlier in the same refresh and not yet committed by FreshRSS. A match marks the new target entry as read.
- When a new source entry is fetched, unread target entries with the same title are marked as read.
- Titles are loaded once per rule and per request, so a refresh costs one query per rule.
- After the rules are saved, the next refresh checks the existing entries within the lookback window once. Later refreshes only look at new entries, so an entry you mark as unread again stays unread.

## Installation

Copy [xExtension-TitleDedup](./xExtension-TitleDedup) into your FreshRSS `extensions/` directory, then enable `Title Dedup` from the FreshRSS extension manager.

Tested with FreshRSS 1.30.4 (PHP 8.3, PostgreSQL).

### Upgrading from 0.1

Version 0.1 was a system extension; 0.2 is a user extension. After upgrading, enable it again in each user's extension settings. Existing rules are read from the old system configuration until the user saves the settings once.

## Usage

1. Open the extension configuration page.
2. If the page is opened from the sidebar, click the full-page editor first.
3. Create a rule group.
4. Select source feeds and target feeds.
5. Set lookback days and title normalization options.
6. Save the configuration.

## Rule Fields

- `source_feed_ids`: source feeds A
- `source_feed_names`: source feed names, can be mixed with `source_feed_ids`
- `target_feed_ids`: target feeds B
- `target_feed_names`: target feed names, can be mixed with `target_feed_ids`
- `lookback_days`: compare only within the last N days
- `normalize_case`: ignore letter case
- `normalize_whitespace`: collapse repeated whitespace
- `strip_punctuation`: compare after punctuation removal

## Example

See [examples/sample-config.json](./examples/sample-config.json) for an example configuration.

## Repository Layout

- `xExtension-TitleDedup/`: installable FreshRSS extension directory
- `examples/`: sample configuration
- `docs/`: design notes

## License

MIT
