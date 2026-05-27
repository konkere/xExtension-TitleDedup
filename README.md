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
- Title normalization: whitespace, case, punctuation
- Maintenance backfill for cases where target entries arrive before source entries
- Visual configuration page with existing feed selection

## Installation

Copy [xExtension-TitleDedup](./xExtension-TitleDedup) into your FreshRSS `extensions/` directory, then enable `Title Dedup` from the FreshRSS extension manager.

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
