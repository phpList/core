# phpList 4 core change log

All notable changes to this project will be documented in this file.
This project adheres to [Semantic Versioning](https://semver.org/).


## x.y.z

### Added
- Trait for database tests and abstract web test (#264)
- Repository methods for querying subscriptions (#253)
- Bidirectional m:n association Subscribers/SubscriberLists (#254)

### Changed
- Remove the obsolete core classes (#267)
- Adopt more of the default Symfony project structure (#265, #268, #269, #270)
- Change composer configuration to reflect new repository name 'core'

### Deprecated

### Removed

### Fixed
- Remove associated subscriptions when a subscriber list or subscriber is removed (#271, #272)
- Always truncate the DB tables after an integration test (#259)
- Adapt the system tests to possible HTTP errors (#256)


## 4.0.0-alpha1

### Security
- Update PHPMailer to 5.2.23
  ([#56](https://github.com/phpList/phplist4-core/pull/55))
