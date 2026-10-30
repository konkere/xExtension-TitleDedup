# Title Dedup

FreshRSS extension that marks target feed entries as read when the same title already exists in source feeds.

中文说明见仓库根目录的 [README.zh-CN.md](../README.zh-CN.md)。

## Features

- Multiple rule groups
- Multiple source feeds and target feeds
- Title normalization (whitespace, case, punctuation)
- Detection of duplicates fetched in the same refresh
- One-off backfill after the rules are saved
- Per-user rules
- Visual configuration page (English, Russian, Chinese)

## Installation

Put the `xExtension-TitleDedup` directory into FreshRSS `extensions/` and enable it from the extension manager.

## Configuration

Configure the extension from its settings page. If the configuration page is opened from the sidebar, switch to the full-page editor first.
