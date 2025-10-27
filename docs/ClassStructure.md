# Class structure

All production classes live under `src/`, and all unit/integration tests live under `tests/`.

## Core/
Core runtime and DI wiring.
- Bootstrap: entry point that bootstraps the phpList core system and configures the application.
- ApplicationKernel, ApplicationStructure: Symfony kernel and structure configuration.
- Compiler passes (e.g., BounceProcessorPass, DoctrineMappingPass), environment helpers, parameter providers.

## Bounce/
Bounce processing feature. (This module continuously updates the database throughout the bounce-processing workflow; therefore, it is separated into its own feature block.)
- Command/: Console commands related to processing bounces.
- Service/: Services that parse, classify and handle bounces.
- Exception/: Bounce‑related exceptions.

## Composer/
Integration with Composer.
- ScriptHandler, ModuleFinder, PackageRepository: helpers invoked by Composer scripts and for module discovery.

## Domain/
Domain logic organized by sub‑domains (e.g., Analytics, Common, Configuration, Identity, Messaging, Subscription).
Each sub‑domain follows similar conventions:
- Model/: Domain entities/value objects. Contains business logic; no direct DB access.
- Repository/: Reading/writing models and other DB queries.
- Service/: Domain services and orchestration.
- Exception/: Domain‑specific exceptions.

## EmptyStartPageBundle/
A minimal Symfony bundle providing an empty start page.
- Controller/: Controllers for the bundle.

## Migrations/
Holds database migration files (Doctrine Migrations). May be empty until migrations are generated.

## Routing/
Routing extensions and loaders.
- ExtraLoader: additional/dynamic route loading.

## Security/
Security‑related concerns.
- Authentication: authentication helpers/integration.
- HashGenerator: password hashing utilities.

## TestingSupport/
Utilities to support tests.
- Traits/: Reusable traits and helpers used in the test suite.


