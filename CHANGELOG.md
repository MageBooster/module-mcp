# Changelog

All notable changes to this package are documented here.
The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the package uses [Semantic Versioning](https://semver.org/).

## [Unreleased]

## [1.1.0] - 2026-11-21

### Changed
- The licence check follows MageBooster License 1.1: without a valid serial number (missing, invalid, expired, other domain or other product) the module keeps working for 30 days, then the MCP endpoint answers HTTP 403 `license_required` and connecting a new AI client redirects to the Licenses configuration. The storefront is not affected. Development hosts (localhost, *.localhost, *.test, *.local, 127.0.0.1) always pass the domain check. The admin shows the state on every page; nothing is shown on the storefront and nothing is sent anywhere.
- Requires magebooster/module-license ^1.1.

## [1.0.0] - 2026-11-20

First public release.

### Added
- MageBooster MCP: signed, role-limited Model Context Protocol tools for AI agents to read store data, with an audit log and PII masking, imported from the MageBooster demo stores.
- Commercial MageBooster licence (WebWorks 101 OÜ); copyright and licence notice in every source file.
- Composer metadata for the public repository (homepage, authors, support, keywords), README header, guides in `docs/`, `.gitignore`.

### Changed
- PHP 8.0 compatibility (readonly properties removed).
- Product text is decoded with explicit `ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5` flags.
