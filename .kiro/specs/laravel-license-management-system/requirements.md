# Requirements Document

## Introduction

This document outlines the requirements for a comprehensive Laravel 11 license management system with multi-role authentication, backup/restore capabilities, and developer operational tools. The system will serve as a production-ready platform for managing software licenses, user roles, payments, and system administration with a focus on security and operational excellence.

The application will be built using Laravel 11, Blade templates, Alpine.js, and TailwindCSS, designed to run locally and be deployable on cPanel hosting with HTTPS support.

## Requirements

### Requirement 1: Multi-Role Authentication System

**User Story:** As a system administrator, I want a secure multi-role authentication system so that different user types can access appropriate functionality based on their permissions.

#### Acceptance Criteria

1. WHEN a user registers THEN the system SHALL support four distinct roles: admin, developer, reseller, and user
2. WHEN an admin or developer performs sensitive actions THEN the system SHALL require 2FA verification
3. WHEN a user logs in THEN the system SHALL regenerate session IDs and use secure cookies
4. WHEN a user attempts Gmail OAuth THEN the system SHALL allow optional extra password setup after OAuth completion
5. WHEN a user logs in via Gmail THEN the system SHALL support both Gmail-only and Gmail+password authentication methods
6. WHEN any form is submitted THEN the system SHALL validate CSRF tokens for all forms and AJAX requests

### Requirement 2: User Profile and Privacy Management

**User Story:** As a user, I want to manage my profile and privacy settings so that I can control my personal information and comply with privacy regulations.

#### Acceptance Criteria

1. WHEN a user accesses their profile THEN the system SHALL display fields for name, email, password (nullable), avatar, 2fa_enabled, privacy_policy_accepted_at, and developer_notes (for developer role)
2. WHEN a user logs in for the first time THEN the system SHALL display a privacy policy modal requiring consent
3. WHEN a user requests data export THEN the system SHALL provide a mechanism to export personal data (GDPR compliance)
4. WHEN a user requests data erasure THEN the system SHALL provide a mechanism to erase personal data
5. WHEN privacy policy consent is given THEN the system SHALL track the acceptance timestamp

### Requirement 3: License Management System

**User Story:** As an admin or reseller, I want to manage software licenses so that I can control product access and device bindings for customers.

#### Acceptance Criteria

1. WHEN an admin or reseller creates a license THEN the system SHALL generate a license with fields: id, product_id, owner_id, user_id, license_key (UUID), status, device_type, max_devices, expires_at, deleted_at
2. WHEN a license is created THEN the system SHALL support statuses: active, expired, suspended, reset
3. WHEN a license validation request is made THEN the system SHALL provide /api/license/validate endpoint with JWT or API key + HMAC authentication
4. WHEN device bindings are tracked THEN the system SHALL store them in license_activations table
5. WHEN license operations are performed THEN the system SHALL support generate, suspend/unsuspend, reset device bindings, expire, and delete actions
6. WHEN API requests are made THEN the system SHALL implement rate limiting and logging for all license validation requests

### Requirement 4: Reseller and User Management

**User Story:** As an admin, I want to manage resellers and their quotas so that I can control the distribution hierarchy and resource allocation.

#### Acceptance Criteria

1. WHEN an admin creates a reseller THEN the system SHALL allow setting quotas for maximum users and licenses
2. WHEN a reseller manages users THEN the system SHALL restrict them to managing only their assigned users
3. WHEN dashboard data is requested THEN the system SHALL provide AJAX-powered dashboard widgets
4. WHEN quotas are exceeded THEN the system SHALL prevent further resource allocation

### Requirement 5: Real-time Communication System

**User Story:** As a user, I want to communicate with my reseller and admins so that I can get support and resolve issues.

#### Acceptance Criteria

1. WHEN users need to communicate THEN the system SHALL provide private chat between User ↔ Reseller ↔ Admin
2. WHEN chat messages are sent THEN the system SHALL store them with sender_id, receiver_id, body, metadata, created_at
3. WHEN chat is active THEN the system SHALL use AJAX polling with 5-second intervals by default
4. WHEN chat abuse occurs THEN the system SHALL support blocking, slow-mode, and per-user rate limiting
5. WHEN messages are displayed THEN the system SHALL show real-time updates via AJAX polling

### Requirement 6: Payment and Webhook Integration

**User Story:** As a business owner, I want to integrate payment systems so that I can automatically handle license purchases and renewals.

#### Acceptance Criteria

1. WHEN payment webhooks are received THEN the system SHALL support Stripe, PayPal, and Razorpay webhook endpoints
2. WHEN webhooks process payments THEN the system SHALL credit wallets and activate/extend licenses automatically
3. WHEN webhook requests are received THEN the system SHALL verify authenticity and ensure idempotent processing
4. WHEN payment processing fails THEN the system SHALL log errors and provide retry mechanisms

### Requirement 7: Account Recovery and Security

**User Story:** As a user, I want secure account recovery options so that I can regain access to my account if I forget my password.

#### Acceptance Criteria

1. WHEN a user requests password reset THEN the system SHALL use signed time-limited tokens
2. WHEN password reset attempts are made THEN the system SHALL implement rate limiting
3. WHEN suspicious reset activity occurs THEN the system SHALL send email alerts
4. WHEN password reset is confirmed THEN the system SHALL optionally require TOTP verification
5. WHEN repeated failures occur THEN the system SHALL implement account lockout mechanisms

### Requirement 8: Developer Role and Operational Safety

**User Story:** As a developer, I want elevated operational permissions so that I can perform system maintenance and monitoring tasks safely.

#### Acceptance Criteria

1. WHEN a developer performs operational tasks THEN the system SHALL allow backup/restore trigger, DB export, log viewing, and API key rotation
2. WHEN developer or admin actions are performed THEN the system SHALL audit all actions in activity_logs and audit_logs
3. WHEN sensitive operations are attempted THEN the system SHALL require 2FA verification
4. WHEN IP restrictions are configured THEN the system SHALL support IP allowlist for developer and admin roles

### Requirement 9: Site Settings Management

**User Story:** As an admin, I want a comprehensive settings panel so that I can configure all system parameters from a centralized interface.

#### Acceptance Criteria

1. WHEN admin accesses settings THEN the system SHALL provide panels for General, Email, Storage, Integrations, and DevOps
2. WHEN SMTP settings are configured THEN the system SHALL include host, port, encryption, user, from email with test-send functionality
3. WHEN Telegram integration is configured THEN the system SHALL support bot token and chat ID with test-send button
4. WHEN S3 storage is configured THEN the system SHALL support Access Key, Secret, Bucket, Region with test-upload functionality
5. WHEN API keys are managed THEN the system SHALL provide generation, revocation, scope setting, and usage tracking
6. WHEN backup settings are configured THEN the system SHALL support schedule, retention, encryption passphrase, and offsite targets
7. WHEN settings are changed THEN the system SHALL require confirmation and audit all changes
8. WHEN sensitive fields are displayed THEN the system SHALL mask values in UI and store them encrypted

### Requirement 10: Backup and Restore System

**User Story:** As a developer or admin, I want comprehensive backup and restore capabilities so that I can protect and recover system data.

#### Acceptance Criteria

1. WHEN manual backup is triggered THEN the system SHALL create full DB dump with optional file inclusion
2. WHEN backups are created THEN the system SHALL generate AES-256 encrypted archives
3. WHEN backups are stored THEN the system SHALL support offsite storage (S3/FTP) and secure download
4. WHEN scheduled backups run THEN the system SHALL follow configured schedule and retention policies
5. WHEN restore is initiated THEN the system SHALL restrict access to developer and admin with 2FA
6. WHEN restore runs THEN the system SHALL perform pre-checks and create pre-restore snapshots
7. WHEN backup operations occur THEN the system SHALL log who triggered, size, checksum, status, and retention expiry
8. WHEN CLI commands are used THEN the system SHALL provide artisan commands for backup operations

### Requirement 11: Developer Dashboard and Monitoring

**User Story:** As a developer, I want a comprehensive dashboard so that I can monitor system health and access audit information.

#### Acceptance Criteria

1. WHEN developer accesses dashboard THEN the system SHALL display system health metrics (disk, DB size, last backup)
2. WHEN system status is checked THEN the system SHALL show queue status, pending migrations, and error rates
3. WHEN audit information is needed THEN the system SHALL provide audit trail viewer for settings and critical actions
4. WHEN compliance reporting is required THEN the system SHALL allow exporting audit logs as CSV

### Requirement 12: Security and Monitoring Framework

**User Story:** As a security-conscious administrator, I want comprehensive security measures so that the system is protected against common threats.

#### Acceptance Criteria

1. WHEN browser sessions are managed THEN the system SHALL use Sanctum for authentication
2. WHEN external API access is required THEN the system SHALL support JWT or API key + HMAC authentication
3. WHEN HTTP responses are sent THEN the system SHALL include CSRF, CSP, X-Frame, and X-Content-Type headers
4. WHEN API keys are stored THEN the system SHALL hash them and provide rotation/revocation UI
5. WHEN rate limiting is applied THEN the system SHALL limit login, password reset, and license validation attempts
6. WHEN sessions are managed THEN the system SHALL regenerate on login, implement absolute expiry, idle timeout, and concurrent session detection
7. WHEN backups are created THEN the system SHALL encrypt them and store secrets in .env files
8. WHEN errors occur THEN the system SHALL support error reporting (Sentry) and metrics collection

### Requirement 13: Database Schema and Performance

**User Story:** As a system architect, I want a well-designed database schema so that the system performs efficiently and maintains data integrity.

#### Acceptance Criteria

1. WHEN database is initialized THEN the system SHALL provide migrations for all required tables: users, roles, licenses, license_activations, chat_messages, transactions, payments, api_clients, activity_logs, audit_logs, settings, backups
2. WHEN database queries are executed THEN the system SHALL have proper indexes on license_key, owner_id, expires_at, device_hash
3. WHEN data is deleted THEN the system SHALL support soft deletes for all relevant tables
4. WHEN system is seeded THEN the system SHALL create 1 admin (with 2FA seed), 1 developer, 1 reseller, 1 Gmail user, 1 product, 1 license

### Requirement 14: API Endpoints and External Integration

**User Story:** As an API consumer, I want well-defined API endpoints so that I can integrate with the license management system programmatically.

#### Acceptance Criteria

1. WHEN license validation is requested THEN the system SHALL provide POST /api/license/validate with X-API-KEY + X-SIGNATURE or JWT authorization
2. WHEN backup operations are needed THEN the system SHALL provide POST /api/backups/trigger for developer-only access
3. WHEN settings management is required THEN the system SHALL provide GET/POST /api/admin/settings with encrypted sensitive values
4. WHEN payment webhooks are received THEN the system SHALL provide POST /api/webhook/payment with verification
5. WHEN API requests are made THEN the system SHALL validate nonce and timestamp, check rate limits, and log results

### Requirement 15: Testing and Quality Assurance

**User Story:** As a developer, I want comprehensive test coverage so that the system is reliable and maintainable.

#### Acceptance Criteria

1. WHEN code is developed THEN the system SHALL include unit and integration tests for all major features
2. WHEN authentication is tested THEN the system SHALL cover Gmail + extra-password flows
3. WHEN license operations are tested THEN the system SHALL cover generation, validation, reset, and suspension
4. WHEN backup functionality is tested THEN the system SHALL cover trigger and listing operations
5. WHEN settings are tested THEN the system SHALL cover change auditing
6. WHEN security is tested THEN the system SHALL cover token expiry and rate limit enforcement
7. WHEN CI/CD is implemented THEN the system SHALL include GitHub Actions pipeline with security scanning

### Requirement 16: Documentation and Deployment

**User Story:** As a system administrator, I want comprehensive documentation so that I can deploy, configure, and maintain the system effectively.

#### Acceptance Criteria

1. WHEN local development is set up THEN the system SHALL provide README with setup instructions and sample .env
2. WHEN cPanel deployment is needed THEN the system SHALL provide deployment guide with Let's Encrypt and file permissions
3. WHEN API integration is required THEN the system SHALL provide OpenAPI/Swagger documentation
4. WHEN system administration is needed THEN the system SHALL provide admin manual for key rotation, backup operations, and service testing
5. WHEN security assessment is required THEN the system SHALL provide pen-test checklist and security remediation steps