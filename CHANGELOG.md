# Changelog


## 1.5.0

### Bug Fixes

- Fix foreign URLs being recorded when site is accessed through a web proxy (host validation against `home_url()`)
- Fix DB upgrade mechanism: replace strict `===` comparison with `version_compare()` for robust version detection across all update scenarios
- Fix IP migration not running on plugin file-overwrite updates (now also triggered from `rsp_run_db_upgrades()`)
- Fix missing `$wpdb->esc_like()` in `SHOW TABLES LIKE` queries to prevent wildcard matching on underscores
- Fix missing backtick escaping on `$table_name` in `rsp_get_most_visited()` and `rsp_process_cleanup_batch()` SQL queries
- Fix unsanitized URL input in `rsp_record_visit()` — now passes through `esc_url_raw()` before storage
- Fix unbounded table growth by enforcing a configurable max row limit (`RSP_MAX_TABLE_ROWS`) (#8)
- Fix cleanup task using correct `DateTime('now')` with WordPress timezone and re-enable batch deletion (#9)
- Fix fatal error when `$wpdb->get_results()` returns `null` by adding `is_array()` guards (#11)
- Fix MySQL 8.4+ deprecation of `VALUES()` in `ON DUPLICATE KEY UPDATE` clause
- Add `idx_last_visit` index for faster cleanup queries

### Improvements


- Add `Requires PHP: 7.3` and `Requires at least: 6.3` to plugin header
- Add URL max length validation (2048 chars) in `rsp_sanitize_url()` with `is_string()` type guard
- Add HTTP 403 status code to nonce-failure response in `rsp_record_visit()`
- Fix toggle switch accessibility: replace `display: none` with visually-hidden pattern and add keyboard focus indicator
- Update dependencies
- Update `tsconfig.json`: change `module` from `ES2015` to `preserve` to avoid deprecated `moduleResolution: node10` default (TypeScript 6.0)
- Expand bot detection: add 16 new bot identifiers
- Remove duplicated entries
- Add explicit `: bool` return type to `is_bot()`
- Add inline source documentation and category grouping to bot list
- Add database upgrade mechanism (`RSP_DB_VERSION` + `rsp_run_db_upgrades()` on `plugins_loaded`)
- Replace `in_array()` with `array_flip()` + `isset()` in preload exclusion filter for O(1) lookups
- Batch IP migration (`rsp_migrate_raw_ips_to_hashed()`) with cursor-based pagination to prevent OOM
- Replace `stripos()` loop with single `preg_match()` in bot detection
- Normalize line endings to LF via `.gitattributes`
