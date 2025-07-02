# phpList core change log

All notable changes to this project will be documented in this file.
This project adheres to [Semantic Versioning](https://semver.org/).

## x.y.z (next release)

### Added
- Graylog integration for centralized logging (#TBD)

### Changed

### Deprecated

### Removed

### Fixed
- Security update for symfony/symfony (#314)
- Security update for symfony/symfony and symfony/dependency-injection (#309)

## 4.0.0-alpha2

### Added
- SubscriptionRepository.findOneBySubscriberListAndSubscriber (#284)
- Repository.remove (#278)
- Interface for domain models (#274)
- Trait for database tests and abstract web test (#264)
- Repository methods for querying subscriptions (#253)
- Bidirectional m:n association Subscribers/SubscriberLists (#254)

### Changed
- Change the core namespace from PhpList\PhpList4 to PhpList\Core (#290)
- Depend on Symfony 3.4.0 as minimum version (#288)
- Move the PHPUnit configuration file (#283)
- Rename the Composer package to "phplist/core" (#275)
- Remove the obsolete core classes (#267)
- Adopt more of the default Symfony project structure (#265, #268, #269, #270, #285, #291)

### Deprecated

### Removed

### Fixed
- Make the exception codes 32-bit safe (#287)
- Remove associated subscriptions when a subscriber list or subscriber is removed (#271, #272)
- Always truncate the DB tables after an integration test (#259)
- Adapt the system tests to possible HTTP errors (#256)


## 4.0.0-alpha1

### Security
- Update PHPMailer to 5.2.23
  ([#56](https://github.com/phpList/phplist4-core/pull/55))
