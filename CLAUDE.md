# Security Notes

**In-house plugin for dedicated server - 2025-11-14**

## Accepted Risks

**SSL Verification Disabled** (`class-scraper.php:75-76`)
- Disabled SSL cert verification for Trustpilot scraping
- Acceptable: Dedicated server, public data only, no auth credentials
- Risk negligible for in-house use

**Cookie Storage in /tmp** (`class-scraper.php:79`)
- Stores session cookies in /tmp directory
- Acceptable: Dedicated server, non-sensitive cookies (not logged in)
- No confidential data at risk

## Assumptions

**Web Scraping Fragility**
- HTML parsing will break if Trustpilot changes their page structure
- Accepted because we don't have access to the official Trustpilot API
- Will need manual fixes when Trustpilot updates their site

**Review Fetching Scope**
- Single HTTP request per business fetches ~20 most recent reviews (Trustpilot page 1)
- All fetched reviews are saved to WordPress (deduplication prevents duplicates)
- No pagination support - only captures reviews from first page
- Historical reviews beyond page 1 are not accessible
- Reviews are fetched newest-first, so only recent reviews are captured

## Programming Notes

**No OOP Wrappers for WordPress APIs**
- Use WordPress functions directly: `wp_insert_post()`, `update_post_meta()`, `get_option()`, etc.
- Only create abstractions when coordinating multiple operations or adding business logic
- Current codebase already respects this principle
