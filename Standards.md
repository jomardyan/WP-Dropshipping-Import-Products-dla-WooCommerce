
WordPress and WooCommerce Plugin Specification
!!!IMPORTANT: Multilangual by architecture (Polish and English) 

This specification defines the minimum engineering, security, compatibility, and operational standards for all WordPress plugins and WooCommerce plugins developed, maintained, or reviewed by the team.

These requirements are mandatory.

1. Scope

This specification applies to all of the following

·        WordPress plugins

·        WooCommerce extensions

·        Custom integrations that run inside WordPress

·        Block-based plugins

·        Plugins distributed privately, commercially, or through the WordPress Plugin Directory or WooCommerce Marketplace

WooCommerce extensions are regular WordPress plugins and must follow WordPress plugin guidance in addition to WooCommerce-specific rules. (The WooCommerce Developer Blog)

2. Core Principles

Every plugin must be

·        Secure by default

·        Compatible with current supported WordPress and WooCommerce versions

·        Maintainable and testable

·        Performant under normal and high-load conditions

·        Interoperable with WordPress core and other plugins

·        Accessible and translatable

·        Clear in naming, configuration, and user experience
·        !!!IMPORTANT: Multilangual by architecture (Polish and English) 


WordPress and WooCommerce both publish best-practice guidance that emphasizes consistency, readability, interoperability, maintainability, UX quality, and compatibility. (WordPress Developer Resources)

3. Architecture and Code Organization

Each plugin must follow these rules

·        Use a unique plugin prefix for PHP functions, option names, hooks, database tables, CSS classes, JavaScript globals, and REST routes

·        Avoid generic names that may collide with WordPress core, themes, or third-party plugins

·        Separate responsibilities by module or class, such as admin, frontend, API, data, and WooCommerce integration

·        Load files conditionally where possible instead of loading all code on every request

·        Use WordPress hooks, APIs, and extension points instead of modifying core behavior directly

·        Provide activation, deactivation, and uninstall behavior where relevant

·        Remove plugin data on uninstall only when this is part of the plugin contract or user expectation

WordPress plugin best practices explicitly recommend unique prefixes, avoiding collisions, and organizing code so it works well with core and other plugins. (WordPress Developer Resources)

4. Coding Standards

All code must comply with current WordPress Coding Standards for PHP, JavaScript, CSS, and HTML.

Mandatory requirements

·        PHP must follow WordPress PHP Coding Standards

·        JavaScript must follow WordPress JavaScript Coding Standards

·        CSS must follow WordPress CSS Coding Standards

·        Code must be linted in CI before merge

·        Formatting and linting rules must be automated, not enforced manually only

WordPress publishes official coding standards for PHP, JavaScript, CSS, and HTML, and recommends their use for plugins and themes. (WordPress Developer Resources)

For block-based plugins, use official WordPress tooling where applicable, including @wordpress/scripts and metadata-driven block registration with block.json. (WordPress Developer Resources)

5. Security Requirements

Every plugin must implement the following controls

·        Validate input before processing it

·        Sanitize input when validation alone is not sufficient

·        Escape output at the point of rendering

·        Check user capabilities before allowing privileged actions

·        Use nonces for state-changing admin actions, forms, and AJAX requests

·        Never trust request data, database data, or third-party API data without verification

·        Use prepared database queries when custom SQL is required

·        Restrict file access and direct execution where relevant

·        Do not store secrets in code repositories

·        Do not expose sensitive data in logs, HTML, JavaScript, or REST responses

WordPress security guidance states that developers should validate and sanitize input, escape output, use capability checks, and use nonces for protected actions. It also states that validation is preferred over sanitization when possible. (WordPress Developer Resources)

6. Data and Database Requirements

Plugins must handle data responsibly.

Mandatory rules

·        Use the WordPress Options API, Settings API, Metadata API, Transients API, REST API, and filesystem APIs where appropriate

·        Do not write directly to core tables in unsupported ways

·        Do not duplicate data without a clear functional reason

·        Keep database writes minimal and scoped

·        Add custom tables only when justified by scale, query needs, or data model requirements

·        Version schema changes and migrations

·        Make activation and upgrade routines idempotent

·        Ensure uninstall cleanup is predictable and documented

For WooCommerce order data, plugins must support High-Performance Order Storage when they interact with orders. HPOS has been stable since WooCommerce 8.2 and is enabled by default for new installations. Incompatible plugins can block HPOS enablement. (The WooCommerce Developer Blog)

7. WooCommerce-Specific Requirements

Any plugin that extends WooCommerce must also comply with the following

·        Detect WooCommerce availability before initializing WooCommerce-dependent code

·        Fail gracefully when WooCommerce is inactive or missing

·        Use WooCommerce APIs, hooks, CRUD objects, and extension patterns instead of bypassing them

·        Declare and maintain compatibility with current WooCommerce versions

·        Support HPOS where order data is touched

·        Test critical commerce flows such as product display, cart, checkout, payment, order creation, refunds where relevant, emails where relevant, and admin order management

·        Avoid breaking checkout, cart fragments, payment flows, or order synchronization

·        Follow WooCommerce UX guidance for naming, admin behavior, and merchant-facing interactions

WooCommerce states that extensions are regular WordPress plugins, but they must follow WooCommerce extension development best practices, UX guidance, and compatibility requirements. WooCommerce Marketplace review also expects critical flows to be thoroughly tested. (The WooCommerce Developer Blog)

8. Performance Requirements

Plugins must have a measurable and controlled performance footprint.

Mandatory rules

·        Do not run expensive queries on every page request

·        Do not autoload large options unnecessarily

·        Load scripts, styles, and integrations only where needed

·        Avoid synchronous external requests during normal page rendering

·        Cache expensive computations where appropriate

·        Benchmark slow paths and WooCommerce-critical flows

·        Review impact on checkout, cart, account, admin order screens, and background jobs

·        Ensure scheduled tasks are bounded, retry-safe, and observable

WooCommerce publishes dedicated performance guidance for extensions and stores, with emphasis on measuring impact, testing, and optimizing extension behavior. (The WooCommerce Developer Blog)

9. Frontend and Asset Loading

All frontend and admin assets must follow WordPress loading standards.

Mandatory rules

·        Enqueue scripts and styles with WordPress APIs

·        Do not hardcode script tags or style tags when WordPress enqueue APIs are appropriate

·        Load assets only on screens that need them

·        Use dependencies and version strings correctly

·        Avoid global JavaScript pollution

·        Keep CSS scoped to the plugin or block namespace

·        Reuse core libraries where appropriate instead of bundling duplicates

WordPress guidance recommends using proper enqueue mechanisms and existing platform tooling and assets where possible. (WordPress Developer Resources)

10. Accessibility Requirements

Plugins must meet accessibility expectations for both admin and frontend experiences.

Mandatory rules

·        Meet WCAG AA intent for new and updated UI

·        Use semantic HTML and proper labels

·        Ensure keyboard operability

·        Provide visible focus states

·        Use landmarks and accessible navigation patterns where relevant

·        Ensure error messages, validation states, and status notices are accessible

·        Do not rely on color alone to communicate meaning

WordPress coding standards reference accessibility standards and state a commitment to WCAG AA for new and updated code. WordPress accessibility guidance also recommends using landmark regions and accessible navigation structures. (WordPress Developer Resources)

11. Internationalization and Localization

All user-facing plugins must be translation-ready.

Mandatory rules

·        Wrap all user-facing strings in WordPress internationalization functions

·        Use a consistent text domain

·        Do not concatenate translatable strings in ways that break translation

·        Escape translated output appropriately

·        Prepare localization assets as part of the release process

·        Do not hardcode locale-specific formats when WordPress APIs provide localization support

WordPress states that plugins should be developed so they can be translated and provides official internationalization functions and guidance, including security considerations for translated strings. (WordPress Developer Resources)

12. Admin UX and Merchant Experience

Plugins must be understandable and non-disruptive.

Mandatory rules

·        Use clear, functional, and original plugin names

·        Keep settings minimal and task-oriented

·        Avoid intrusive admin notices

·        Show actionable error messages

·        Do not hijack dashboards, menus, or onboarding flows

·        Do not create unnecessary top-level menus

·        Keep defaults safe and sensible

·        Document side effects of enabling or disabling major features

WooCommerce UX guidance states that plugin naming should be functional and original and that extensions should align with WooCommerce UX conventions. (The WooCommerce Developer Blog)

13. Compatibility and Dependency Management

Each plugin must define and maintain compatibility boundaries.

Mandatory rules

·        Declare minimum supported PHP, WordPress, and WooCommerce versions

·        Test against the current supported versions and the lowest supported versions where feasible

·        Gracefully handle missing PHP extensions, unavailable services, and inactive dependencies

·        Do not fatal on activation because of avoidable dependency issues

·        Isolate third-party libraries to reduce conflicts

·        Keep dependencies current and review them for security and license compliance

WooCommerce and WordPress both stress compatibility and maintainability as part of extension quality and release readiness. (The WooCommerce Developer Blog)

14. Testing and Quality Assurance

Every plugin must have a repeatable quality process.

Mandatory rules

·        Automated linting for PHP, JavaScript, and CSS

·        Automated unit tests for business logic

·        Integration tests for WordPress and WooCommerce hooks and APIs where relevant

·        End-to-end tests for critical user journeys where relevant

·        Manual regression testing before each release

·        Test coverage for activation, upgrade, uninstall, settings save, permissions, and failure states

·        WooCommerce plugins must test cart, checkout, and order lifecycle flows where applicable

WordPress and WooCommerce both provide testing guidance and tooling. WordPress block tooling includes unit and end-to-end test scripts, and WooCommerce publishes guidance for unit testing and end-to-end testing for extensions. (WordPress Developer Resources)

15. Logging, Monitoring, and Supportability

Plugins must be diagnosable without exposing sensitive data.

Mandatory rules

·        Use structured logging where helpful

·        Never log secrets, payment data, or personal data beyond what is necessary

·        Provide clear debug paths for support teams

·        Make background jobs traceable

·        Include version information in support diagnostics

·        Document known compatibility constraints and operational limitations

WooCommerce maintainability guidance emphasizes ongoing compatibility, update discipline, and operational checks. (The WooCommerce Developer Blog)

16. Release Management

Every release must follow a controlled process.

Mandatory rules

·        Maintain semantic or otherwise documented versioning

·        Keep a changelog

·        Run automated checks before release

·        Run a final manual smoke test

·        Document database migrations and rollback risk

·        Publish compatibility updates promptly after major WordPress or WooCommerce releases

·        Review support issues and regressions after release

WooCommerce maintainability guidance explicitly focuses on update frequency, process, and compatibility maintenance. (The WooCommerce Developer Blog)

17. Documentation Requirements

Each plugin must include

·        Purpose and feature summary

·        Installation steps

·        Configuration guide

·        Compatibility statement

·        Upgrade notes

·        Changelog

·        Troubleshooting notes

·        Data storage behavior

·        Uninstall behavior

·        Support contact or process

For WooCommerce Marketplace submissions, WooCommerce requires strong product quality, support standards, and compliance with documentation and review requirements. (The WooCommerce Developer Blog)

18. Prohibited Practices

The following are not allowed

·        Direct modification of WordPress core or WooCommerce core files

·        Hidden tracking or undisclosed remote calls

·        Unsafe SQL construction

·        Processing privileged actions without capability checks and nonce verification

·        Rendering unescaped output from untrusted sources

·        Loading all assets on all pages without need

·        Storing WooCommerce order logic in legacy-only patterns when HPOS compatibility is required

·        Admin spam, misleading notices, or deceptive upsell behavior

·        Naming that impersonates or confuses core WordPress or WooCommerce features

These restrictions are aligned with WordPress security and plugin guideline expectations and WooCommerce UX and extension quality expectations. (WordPress Developer Resources)

19. Acceptance Checklist

A plugin is approved only when all items below are true

·        Coding standards pass

·        Security review passes

·        No namespace or prefix collisions identified

·        Compatibility requirements documented

·        Translation-ready strings verified

·        Accessibility review completed

·        Performance impact reviewed

·        Automated tests pass

·        Manual critical-flow testing completed

·        WooCommerce HPOS compatibility confirmed where applicable

·        Documentation complete

·        Release notes complete

20. Recommended Enforcement Model

Use this specification as a release gate with these checkpoints

·        Pull request review against this standard

·        Automated CI checks for linting and tests

·        Security review for privileged and public entry points

·        Performance review for high-traffic or checkout-impacting changes

·        Final release approval by engineering owner
