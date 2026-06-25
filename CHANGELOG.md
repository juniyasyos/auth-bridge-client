# Changelog

All notable changes to `nexaid-client` will be documented in this file.

## [1.2.17] - 2026-06-25
### Fixed
- Fix unit_kerja relation sync during user push by preventing automatic creation of missing master unit_kerja records, avoiding database constraint errors.

## [1.2.16] - 2026-06-25
### Fixed
- Fix immediate SSO logout loop caused by Laravel `url.intended` caching `/logout` requests prior to login.

## [1.2.15] - 2026-06-25
### Fixed
- Rename `auth-bridge-client` to `nexaid-client` to reflect the new branding.
### Chore
- Update SBOM (Software Bill of Materials).

## [1.2.13] - 2026-06-25
### Optimized
- Enrich logging context across various controllers (Logout, SsoCallback, SyncUsers, PushUsers, etc.) by injecting `action`, `method`, `url`, `ip`, `user_agent`, and `timestamp` for detailed traceability.

## [1.2.12] - 2026-06-15
### Added
- `IAM_BACKCHANNEL` environment variable support for internal Docker networking server-to-server API calls.

## [1.2.11]
### Changed
- Update license to proprietary and add copyright notice with usage restrictions.

## [1.2.10]
### Optimized
- Optimize user and unit synchronization by reducing N+1 queries and improving caching mechanisms.

## [1.2.9]
### Added
- Implement handling for deleted `user_unit_kerja` relations during user synchronization.

## [1.2.8]
### Added
- Add delete behavior configuration and implement handling for deleted units and relations.

## [1.2.7]
### Added
- Implement application access check for user synchronization and log skipped users.

## [1.2.6]
### Added
- Set default password for new users during synchronization if not provided.

## [1.2.5]
### Changed
- Update Unit Kerja configuration and enhance CRUD permissions for backchannel sync/push requests.

## [1.2.4]
### Added
- Add fallback for 'name' field in user sync to prevent NOT NULL constraint violations.

## [1.2.3]
### Added
- Add IAM enabled checks in middleware to conditionally enforce authentication and token verification.

## [1.2.2]
### Added
- Implement security enhancements in VerifyIamToken middleware and add regression tests for token verification.

## [1.2.1]
### Added
- Add checks for existing tables and columns in migration files to prevent errors during migration.

## [1.2.0]
### Changed
- Refactor IAM Client to Auth Bridge Client.

## [1.1.1]
### Changed
- Refactor user status handling in PushUsersController and SyncUsersController.

## [1.1.0]
### Added
- Add Livewire app switcher component and related services for user applications management.

## [1.0.0] - 2024-01-01
### Added
- Initial release.
- SSO integration with IAM server.
- JIT user provisioning.
- JWT token verification.
- Role synchronization with Spatie Permission.
- Auto-register routes and migrations.
- Simplify OP-initiated logout by removing backchannel support and enforcing full session invalidation.
