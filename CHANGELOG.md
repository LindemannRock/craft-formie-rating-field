# Changelog

## [3.20.0](https://github.com/LindemannRock/craft-formie-rating-field/compare/v3.19.0...v3.20.0) (2026-06-07)


### Added

* add static analysis script for CI workflow ([ccd1261](https://github.com/LindemannRock/craft-formie-rating-field/commit/ccd1261420a7231859e503f3fbedbac375160c98))
* **cli:** add HelpController for cli command assistance ([f92ba20](https://github.com/LindemannRock/craft-formie-rating-field/commit/f92ba2047bcc066d6318b1c5f9f3866033086b68))
* **cp:** rename 'View Settings' link to 'Manage settings' ([3eecb30](https://github.com/LindemannRock/craft-formie-rating-field/commit/3eecb30222f00c2dfa77f0578c82d0ed1c2cc41d))
* **jobs:** add recurring scheduler master job and next run time formatting ([67e6d81](https://github.com/LindemannRock/craft-formie-rating-field/commit/67e6d818b6afe88708093080539156dee53f2c5e))
* **queue:** schedule automatic cache generation based on settings ([7342439](https://github.com/LindemannRock/craft-formie-rating-field/commit/7342439be443102d902b54b96d50c2a7532b19b0))
* **settings:** add cache generation schedule options and effective schedule method ([d5e84f9](https://github.com/LindemannRock/craft-formie-rating-field/commit/d5e84f987af2e13a92ed5cc0957c8e36b68098bf))
* **settings:** handle cache generation schedule changes on save ([134dc62](https://github.com/LindemannRock/craft-formie-rating-field/commit/134dc628477196f88c2c48b8f6ed5a14d788424e))
* **settings:** replace cache generation schedule options with dynamic retrieval ([cb595b0](https://github.com/LindemannRock/craft-formie-rating-field/commit/cb595b0b99512a1a9f14bf9f49a85f70c1be0f37))
* **tests:** add SchedulerPatternTest for cache generation scheduling ([8356138](https://github.com/LindemannRock/craft-formie-rating-field/commit/83561388ff80485f573e6e30e941fffd2096947d))


### Fixed

* **cache:** handle error messages based on dev mode in CacheController ([a32e8db](https://github.com/LindemannRock/craft-formie-rating-field/commit/a32e8dbd82db7c67be2718e75ad8fdb19fcfd1ed))
* **cache:** handle Redis misconfiguration gracefully ([bd49fbe](https://github.com/LindemannRock/craft-formie-rating-field/commit/bd49fbe7358e2f73d15cd281276f03413644e04a))
* change default items per page to 100 in config ([334efd1](https://github.com/LindemannRock/craft-formie-rating-field/commit/334efd1c5fe10c211e88cd3e6641467f732ddd89))
* correct permission error message in site access validation ([f7891a7](https://github.com/LindemannRock/craft-formie-rating-field/commit/f7891a7ef7bec99a5f0e4b7b7c0a1fbfe8e325e9))
* filter out failed jobs and unupdated entries in query ([1826cdc](https://github.com/LindemannRock/craft-formie-rating-field/commit/1826cdc4bf882dd3f28ede2bb4567cb025c041cb))
* handle error messages based on dev mode in StatisticsController ([d645b15](https://github.com/LindemannRock/craft-formie-rating-field/commit/d645b15995d58dc804188f9b1755b608849ac280))
* **i18n:** correct cache generation messages in Spanish translations ([1a4d4f6](https://github.com/LindemannRock/craft-formie-rating-field/commit/1a4d4f626839cf0358e68b295f3ac5d6d7d11c34))
* **i18n:** correct punctuation in Japanese cache messages ([9da5277](https://github.com/LindemannRock/craft-formie-rating-field/commit/9da52779970942dc0eac73ea2cde1d64c8cd12ce))
* **i18n:** correct punctuation in Spanish cache messages ([ecff3a9](https://github.com/LindemannRock/craft-formie-rating-field/commit/ecff3a9a789b7355519875aa4b4b3203cea56273))
* **i18n:** correct translation for submission date in Arabic, Spanish, and Italian ([87b886e](https://github.com/LindemannRock/craft-formie-rating-field/commit/87b886e1b2a5f34675476bd6d6262d7c2bc0c669))
* **settings:** handle empty multi-state select values correctly ([8948b9b](https://github.com/LindemannRock/craft-formie-rating-field/commit/8948b9b7496bcbfcec6982e5717b2239ba349810))
* **statistics:** qualify dateCreated column in query to prevent ambiguity ([922655f](https://github.com/LindemannRock/craft-formie-rating-field/commit/922655ffaed58f4e51d11f79364de21260d4a9bd))
* **statistics:** qualify dateCreated column to prevent ambiguity in PostgreSQL ([59244d3](https://github.com/LindemannRock/craft-formie-rating-field/commit/59244d3de2bf4073ed5e67f6753ce25685e267de))

## [3.19.0](https://github.com/LindemannRock/craft-formie-rating-field/compare/v3.18.0...v3.19.0) - 2026-05-22


### Added

* add pre-commit hook for ECS and PHPStan code quality checks ([cf4d32b](https://github.com/LindemannRock/craft-formie-rating-field/commit/cf4d32ba97ec1e09c3225ca2a69251937c843c80))
* **i18n:** add translation issue template for reporting language problems ([e7213db](https://github.com/LindemannRock/craft-formie-rating-field/commit/e7213db9986cc11a69c03789d0c148a5ea9a8328))
* **tests:** add PHPUnit configuration and integration tests for Rating field ([b254501](https://github.com/LindemannRock/craft-formie-rating-field/commit/b254501d2fbc517cdcac21a6b4203ac6c0a8db4f))

## [3.18.0](https://github.com/LindemannRock/craft-formie-rating-field/compare/v3.17.0...v3.18.0) - 2026-05-09


### Features

* **statistics:** enhance statistics retrieval with site filtering ([0fc8536](https://github.com/LindemannRock/craft-formie-rating-field/commit/0fc8536581f1caaf7a1d6e11c79e0fd4eda236d2))

## [3.17.0](https://github.com/LindemannRock/craft-formie-rating-field/compare/v3.16.0...v3.17.0) - 2026-05-06


### Features

* **cp:** enhance plugin bootstrap with installation experience details ([c9cb5d4](https://github.com/LindemannRock/craft-formie-rating-field/commit/c9cb5d4e9bb2be9bc0a1e7fc19e8287200131952))
* **google-reviews:** enhance Google review handling with URL validation and secure encoding ([8e1c434](https://github.com/LindemannRock/craft-formie-rating-field/commit/8e1c434cde818a00e81fbe370961f4680e88a009))
* **issue-templates:** add bug report, feature request, and question templates ([a35a091](https://github.com/LindemannRock/craft-formie-rating-field/commit/a35a0910fbbb12213be6d356f707f022a2f91964))
* **permissions:** add user permissions for cache management and statistics viewing ([442dae3](https://github.com/LindemannRock/craft-formie-rating-field/commit/442dae3253e2f22f90c630400aa89adfb281443c))
* **rating:** add translation support for user-facing strings in templates and JS ([a1ff5f4](https://github.com/LindemannRock/craft-formie-rating-field/commit/a1ff5f47a405959efc215526b35608b8dc1cb154))
* **settings:** add max export rows setting for raw responses export ([0991b85](https://github.com/LindemannRock/craft-formie-rating-field/commit/0991b85425e3804b7cb75b3ff52911ca9e4e59e2))
* **statistics:** add per-score distribution for NPS fields in statistics calculation ([297cefe](https://github.com/LindemannRock/craft-formie-rating-field/commit/297cefed62e3b3155d88f6616bcc1cce9189963a))
* **statistics:** add site filtering to statistics views and exports ([4978e6f](https://github.com/LindemannRock/craft-formie-rating-field/commit/4978e6ff184dcab43a8024f4f3bc6785dd367a03))
* **statistics:** exclude incomplete and spam submissions from statistics queries ([705d472](https://github.com/LindemannRock/craft-formie-rating-field/commit/705d4727d7c00fc05d4e48d3223edc9c21e3f29d))
* **statistics:** implement maxExportRows limit for group submissions ([6d54ab0](https://github.com/LindemannRock/craft-formie-rating-field/commit/6d54ab02461a5a8d2ad495248145589055571389))
* **statistics:** implement Redis cache clearing for form submissions ([882c60e](https://github.com/LindemannRock/craft-formie-rating-field/commit/882c60e9e35295752dc2c44f6672239ddaf7cb6b))
* **statistics:** implement Redis key tracking for scoped cache deletion ([50626db](https://github.com/LindemannRock/craft-formie-rating-field/commit/50626db62b2f9a20c509d91749b56bac44ecb30f))
* **statistics:** implement trend data caching for improved performance ([67143c1](https://github.com/LindemannRock/craft-formie-rating-field/commit/67143c15ec81aced69ccd93fb0b5ce0f416a5cd8))
* **statistics:** optimize form retrieval with aggregated queries for ratings and submissions ([fdec9b8](https://github.com/LindemannRock/craft-formie-rating-field/commit/fdec9b88312ceaaeee14a61b00e5eacc3f671c8f))
* **statistics:** secure JavaScript variables with URL encoding for date range and sorting ([d73bbde](https://github.com/LindemannRock/craft-formie-rating-field/commit/d73bbde06405bbda81506a5b3aeeb3118790df48))
* **translations:** add new rating settings and feedback messages in multiple languages ([9341dc7](https://github.com/LindemannRock/craft-formie-rating-field/commit/9341dc7c0356e191d540e711ad335708e1d87f0f))
* **translations:** add new translations for multiple languages ([816bdac](https://github.com/LindemannRock/craft-formie-rating-field/commit/816bdac4ea64858a766e80ad570c85318f396967))
* **translations:** add Swedish translation for rating field plugin ([29d8ee4](https://github.com/LindemannRock/craft-formie-rating-field/commit/29d8ee41c0788bce8240e48d54dc463eb2b0f2b1))
* **utilities:** gate utility registration based on user permissions ([f8ca216](https://github.com/LindemannRock/craft-formie-rating-field/commit/f8ca21615b784e6871bb89343c07501c170821e7))


### Bug Fixes

* **statistics:** correct join condition for form fields retrieval ([9586be6](https://github.com/LindemannRock/craft-formie-rating-field/commit/9586be676c6de0486edd38e89b70397c66546383))
* **statistics:** escape JSON output to prevent script injection vulnerabilities ([b3d44bf](https://github.com/LindemannRock/craft-formie-rating-field/commit/b3d44bf9d5ed35248d2eca2fca45032247a799db))
* **statistics:** invalidate statistics cache on submission save/delete ([d386a22](https://github.com/LindemannRock/craft-formie-rating-field/commit/d386a225d4b82bd6af9a9426b4081fbd837452a5))
* **statistics:** resolve table name syntax for submissions in queries ([cd45024](https://github.com/LindemannRock/craft-formie-rating-field/commit/cd450248829911707e0006d97e67a815011ddf76))
* **translations:** correct plugin name description in multiple languages ([42c4099](https://github.com/LindemannRock/craft-formie-rating-field/commit/42c4099f803a46f328f15b369db302dea8e03bdb))
* **translations:** update copyright year in translation files ([4a170b6](https://github.com/LindemannRock/craft-formie-rating-field/commit/4a170b67eb66119f73873f7d2eb28dd0bd423b37))

## [3.16.0](https://github.com/LindemannRock/craft-formie-rating-field/compare/v3.15.1...v3.16.0) - 2026-04-25


### Features

* **settings:** expand defaultDateRange options for improved flexibility ([a73d280](https://github.com/LindemannRock/craft-formie-rating-field/commit/a73d28020ce558221569d53a86aad183f43a5be4))
* **statistics:** add calculateStatsForSubmissions method for external submission analysis ([94053c9](https://github.com/LindemannRock/craft-formie-rating-field/commit/94053c9f2beed161085c79c9dbd3873235db08bb))
* **statistics:** enhance export functionality to support multi-sheet Excel and ZIP of CSVs ([30a7f2a](https://github.com/LindemannRock/craft-formie-rating-field/commit/30a7f2ac71b19b8c1bc7cfa1de997d7b91fd1856))
* **statistics:** enhance NPS calculation and export format validation ([2d09b80](https://github.com/LindemannRock/craft-formie-rating-field/commit/2d09b800ff63ca3349284c21515ed1003cdfd675))
* **statistics:** implement export functionality for grouped and ungrouped statistics ([38ad0e7](https://github.com/LindemannRock/craft-formie-rating-field/commit/38ad0e713cf6c756c9c243330964285e6e80a8f6))
* **statistics:** shorten field labels in tabs and filters for better display ([271ab7b](https://github.com/LindemannRock/craft-formie-rating-field/commit/271ab7bb23380df18658b1443a664077f359d275))


### Bug Fixes

* drop PAT requirement for release-please — use built-in GITHUB_TOKEN ([c5652b2](https://github.com/LindemannRock/craft-formie-rating-field/commit/c5652b216440bfade9119a46fb5991ba6deb7756))
* **statistics:** update card values to handle zero responses gracefully ([1bf1726](https://github.com/LindemannRock/craft-formie-rating-field/commit/1bf17264125e079f3e5b3609e18fe5023fa1b834))

## [3.15.1](https://github.com/LindemannRock/craft-formie-rating-field/compare/v3.15.0...v3.15.1) - 2026-04-05


### Bug Fixes

* read-only settings response ([c12cef9](https://github.com/LindemannRock/craft-formie-rating-field/commit/c12cef95dbf40d01bc66350001573334664f5282))

## [3.15.0](https://github.com/LindemannRock/craft-formie-rating-field/compare/v3.14.3...v3.15.0) - 2026-04-02


### Features

* **rating:** add styles for rating field including stars and emojis ([1a0d9fc](https://github.com/LindemannRock/craft-formie-rating-field/commit/1a0d9fcc9b465bd85de97763defed73fb978254b))
* **rating:** implement rating field functionality with dynamic UI ([5321036](https://github.com/LindemannRock/craft-formie-rating-field/commit/5321036e4659884e50db5efcd7d629e1f6b0ee89))


### Bug Fixes

* **RatingUtility:** update icon path to use new SVG file ([d94f644](https://github.com/LindemannRock/craft-formie-rating-field/commit/d94f644a0abac11cf2b9a4a1c2fdae63f3cbde8e))
* **settings:** remove redundant submit button from settings forms ([a32b584](https://github.com/LindemannRock/craft-formie-rating-field/commit/a32b584c5d08de0140365d103521603cbc60ddd3))


### Miscellaneous Chores

* **gitignore:** update .gitignore and .gitattributes for asset exclusions ([3afbfe6](https://github.com/LindemannRock/craft-formie-rating-field/commit/3afbfe685efea40db22e93a980df36fc94f33590))
* **package:** add package.json for project configuration and scripts ([9d409a2](https://github.com/LindemannRock/craft-formie-rating-field/commit/9d409a294baf0e1df4efd3db4849ec42d215387e))
* **package:** remove unused minify scripts from package.json ([bc2f00e](https://github.com/LindemannRock/craft-formie-rating-field/commit/bc2f00e85c929e87a98ed4aa774b997e2009cd99))

## [3.14.3](https://github.com/LindemannRock/craft-formie-rating-field/compare/v3.14.2...v3.14.3) - 2026-03-04


### Bug Fixes

* **GenerateCacheJob:** implement RetryableJobInterface and update canRetry method ([6e07bb7](https://github.com/LindemannRock/craft-formie-rating-field/commit/6e07bb79a976109e027cd0e5a9707187ce86aae2))
* **SettingsController:** validate settings based on section and add error handling ([bd02aed](https://github.com/LindemannRock/craft-formie-rating-field/commit/bd02aede6357987bd37ead912e76eb9af4dbd79a))
* **StatisticsService:** improve query building with DB-agnostic helpers ([191471e](https://github.com/LindemannRock/craft-formie-rating-field/commit/191471ef6ac8447fa9c2d745007137eb5aba37c5))


### Miscellaneous Chores

* add .gitattributes with export-ignore for Packagist distribution ([f6a2300](https://github.com/LindemannRock/craft-formie-rating-field/commit/f6a230049770f57348a2a35a5a56cf8991123a50))
* switch to Craft License for commercial release ([3e72082](https://github.com/LindemannRock/craft-formie-rating-field/commit/3e7208234381fe4813ac74c082fadb47342a51ab))

## [3.14.2](https://github.com/LindemannRock/craft-formie-rating-field/compare/v3.14.1...v3.14.2) - 2026-02-05


### Miscellaneous Chores

* **package.json:** update package name and add author/company details ([89de567](https://github.com/LindemannRock/craft-formie-rating-field/commit/89de567610f0da041a77bad0a7d0718f912f6afe))
* **statistics:** remove backup of StatisticsController.php ([5bc7ead](https://github.com/LindemannRock/craft-formie-rating-field/commit/5bc7ead6e837f1ff536111b753bbee062ebc8ea7))

## [3.14.1](https://github.com/LindemannRock/craft-formie-rating-field/compare/v3.14.0...v3.14.1) - 2026-01-26


### Bug Fixes

* **jobs:** prevent duplicate scheduling of backup jobs ([0d887ea](https://github.com/LindemannRock/craft-formie-rating-field/commit/0d887ea2f5953f8a216111de1e49ee23f43ff202))

## [3.14.0](https://github.com/LindemannRock/craft-formie-rating-field/compare/v3.13.0...v3.14.0) - 2026-01-16


### Features

* add JSON export functionality for group detail and overall statistics ([d303cb4](https://github.com/LindemannRock/craft-formie-rating-field/commit/d303cb4894474eaefaaeb0c83e6fb71f15516d90))
* add JSON export option for statistics and group detail pages ([4d58bb1](https://github.com/LindemannRock/craft-formie-rating-field/commit/4d58bb10338384aa88099ceb75d2f08135b36edf))


### Bug Fixes

* update cache.twig to use shared info-box component and improve cache location messages ([fb60584](https://github.com/LindemannRock/craft-formie-rating-field/commit/fb6058468c7208847c727a8bd28244b25fb75c7e))
* update hardcoded cache paths with PluginHelper for consistency ([0b8a720](https://github.com/LindemannRock/craft-formie-rating-field/commit/0b8a720fa204ada6d32a6482f9f75a1379b50651))
* update plugin credit component path to use shared base component ([cda5121](https://github.com/LindemannRock/craft-formie-rating-field/commit/cda5121c312297215a63d6e0c3ae58486cc98955))

## [3.13.0](https://github.com/LindemannRock/craft-formie-rating-field/compare/v3.12.4...v3.13.0) - 2026-01-11


### Features

* bootstrap PluginHelper in FormieRatingField initialization ([d6ee9be](https://github.com/LindemannRock/craft-formie-rating-field/commit/d6ee9be1a26ccf3121d217f6c09badbb94a98102))


### Bug Fixes

* update composer.json to include required package and refactor Settings model ([bd9ddf0](https://github.com/LindemannRock/craft-formie-rating-field/commit/bd9ddf0aedf7c95df86a6226e1b36e3636bf7aec))
* update displayName method to return full name from settings ([c26099c](https://github.com/LindemannRock/craft-formie-rating-field/commit/c26099ceb00bbdab17284d92901e9d5bcdfb3ab1))


### Miscellaneous Chores

* update composer.json to include missing dev dependencies ([69ddde8](https://github.com/LindemannRock/craft-formie-rating-field/commit/69ddde82e03162114bda1c64e00e0b01a5243f51))

## [3.12.4](https://github.com/LindemannRock/craft-formie-rating-field/compare/v3.12.3...v3.12.4) - 2025-12-19


### Bug Fixes

* trim whitespace in display name methods and update cache label ([95235f0](https://github.com/LindemannRock/craft-formie-rating-field/commit/95235f00a805c83339d919e9df1e40afbc6bbec1))

## [3.12.3](https://github.com/LindemannRock/craft-formie-rating-field/compare/v3.12.2...v3.12.3) - 2025-12-16


### Bug Fixes

* update icon method to return a star icon ([2d9eab7](https://github.com/LindemannRock/craft-formie-rating-field/commit/2d9eab7aabcab3960f93be7372e876fded3a48f3))

## [3.12.2](https://github.com/LindemannRock/craft-formie-rating-field/compare/v3.12.1...v3.12.2) - 2025-12-16


### Bug Fixes

* read-only mode for settings based on project config ([2b8b864](https://github.com/LindemannRock/craft-formie-rating-field/commit/2b8b86404298a609bcf332e7903cfd541185ed20))

## [3.12.1](https://github.com/LindemannRock/craft-formie-rating-field/compare/v3.12.0...v3.12.1) - 2025-12-16


### Bug Fixes

* update cache job scheduling logic to use dynamic delay calculation ([7032414](https://github.com/LindemannRock/craft-formie-rating-field/commit/7032414cd4017b2fa2d6ee60cb1f99996f5dc189))

## [3.12.0](https://github.com/LindemannRock/craft-formie-rating-field/compare/v3.11.0...v3.12.0) - 2025-12-16


### Features

* add cache storage method configuration and Redis support ([fad55f0](https://github.com/LindemannRock/craft-formie-rating-field/commit/fad55f0e0ab6659fd7786a88ca12dfd5ec66b436))

## [3.11.0](https://github.com/LindemannRock/craft-formie-rating-field/compare/v3.10.0...v3.11.0) - 2025-12-11


### Features

* enhance GenerateCacheJob with batch processing and improved cache management ([736c1b9](https://github.com/LindemannRock/craft-formie-rating-field/commit/736c1b97a3f60c0e0c1c3c6f97c4285ccd92d309))

## [3.10.0](https://github.com/LindemannRock/craft-formie-rating-field/compare/v3.9.0...v3.10.0) - 2025-12-11


### Features

* add cache management features including settings for automatic cache generation and a utility for cache operations ([2604ccb](https://github.com/LindemannRock/craft-formie-rating-field/commit/2604ccb9b81f44bcff5e25f0e642026695ff3584))

## [3.9.0](https://github.com/LindemannRock/craft-formie-rating-field/compare/v3.8.0...v3.9.0) - 2025-12-11


### Features

* add export functionality and improve group detail display in statistics ([dfbf370](https://github.com/LindemannRock/craft-formie-rating-field/commit/dfbf37074c2b22e2ba6c3bdb5572cf13c7edca47))
* enhance README with drill-down view details and Google review integration settings ([3fed2db](https://github.com/LindemannRock/craft-formie-rating-field/commit/3fed2db6c368dd6a290b69e231426ff0d0eb7550))


### Bug Fixes

* enhance group detail template with field handle support and improved URL parameters ([0d2d7af](https://github.com/LindemannRock/craft-formie-rating-field/commit/0d2d7af2595ad8f110d7e9448595b41a2180a19c))

## [3.8.0](https://github.com/LindemannRock/craft-formie-rating-field/compare/v3.7.1...v3.8.0) - 2025-12-10


### Features

* add group detail view and export functionality for statistics ([677a60f](https://github.com/LindemannRock/craft-formie-rating-field/commit/677a60f9a0aa3f18e747e01de0dec61954d29d8a))

## [3.7.1](https://github.com/LindemannRock/craft-formie-rating-field/compare/v3.7.0...v3.7.1) - 2025-12-09


### Bug Fixes

* update Google Review button alignment to use flex classes ([8969b4a](https://github.com/LindemannRock/craft-formie-rating-field/commit/8969b4ad8d5d876ce21d5f5fc53e88e48dcff679))

## [3.7.0](https://github.com/LindemannRock/craft-formie-rating-field/compare/v3.6.4...v3.7.0) - 2025-12-09


### Features

* add button alignment option for Google Review integration ([7d70de1](https://github.com/LindemannRock/craft-formie-rating-field/commit/7d70de14cf78d2af20652ba427671c9f4e643ece))

## [3.6.4](https://github.com/LindemannRock/craft-formie-rating-field/compare/v3.6.3...v3.6.4) - 2025-12-09


### Bug Fixes

* enhance Google Review integration with logging and JS registration ([fd1b97e](https://github.com/LindemannRock/craft-formie-rating-field/commit/fd1b97e09d5f58cb654312dcda114a7514f0ced9))

## [3.6.3](https://github.com/LindemannRock/craft-formie-rating-field/compare/v3.6.2...v3.6.3) - 2025-12-09


### Bug Fixes

* remove emoji from high rating message for consistency ([d6fe53e](https://github.com/LindemannRock/craft-formie-rating-field/commit/d6fe53e677a10d8528623cbd1142314852fabcc4))

## [3.6.2](https://github.com/LindemannRock/craft-formie-rating-field/compare/v3.6.1...v3.6.2) - 2025-12-09


### Bug Fixes

* update comments and default messages for clarity in Rating field ([3c8d9ef](https://github.com/LindemannRock/craft-formie-rating-field/commit/3c8d9ef969aad5a646420a9d6d008e55f62a3a96))
* update default messages to use static strings in Rating field ([012f41b](https://github.com/LindemannRock/craft-formie-rating-field/commit/012f41b98611fd59db1cd7bae704e4555ef89c01))

## [3.6.1](https://github.com/LindemannRock/craft-formie-rating-field/compare/v3.6.0...v3.6.1) - 2025-12-09


### Bug Fixes

* remove dummy PlaceID for testing in Rating field ([1656d24](https://github.com/LindemannRock/craft-formie-rating-field/commit/1656d248a83d7ac58ace203eb76169a98d848d05))

## [3.6.0](https://github.com/LindemannRock/craft-formie-rating-field/compare/v3.5.0...v3.6.0) - 2025-12-09


### Features

* add Google Review integration to Rating field ([2ba47a9](https://github.com/LindemannRock/craft-formie-rating-field/commit/2ba47a9e694bd4c8ab28e64d4c348eb7d6666309))

## [3.5.0](https://github.com/LindemannRock/craft-formie-rating-field/compare/v3.4.0...v3.5.0) - 2025-12-09


### Features

* add 'Show Selected Label' option to Rating field schema ([cb672fd](https://github.com/LindemannRock/craft-formie-rating-field/commit/cb672fd4fcdc8f85d6a5dc70345e5e5ccf8d2e4b))

## [3.4.0](https://github.com/LindemannRock/craft-formie-rating-field/compare/v3.3.0...v3.4.0) - 2025-12-09


### Features

* enhance Formie Rating Field with cache clearing option and loading indicators for charts ([7997bbc](https://github.com/LindemannRock/craft-formie-rating-field/commit/7997bbca8cd8f804e9257bb76ff3a731228d6ac6))

## [3.3.0](https://github.com/LindemannRock/craft-formie-rating-field/compare/v3.2.0...v3.3.0) - 2025-12-08


### Features

* add PHPStan and EasyCodingStandard configurations, update Rating field integration ([b9dcdbd](https://github.com/LindemannRock/craft-formie-rating-field/commit/b9dcdbd6ac32778c7b76c6608355805c8730e4b0))
* add statistics and Twig extension for Formie Rating Field ([a3187f2](https://github.com/LindemannRock/craft-formie-rating-field/commit/a3187f279659a48d4692a323402829f9cb2a59b5))


### Bug Fixes

* improve null checks and default settings handling in Rating field ([0b8b8af](https://github.com/LindemannRock/craft-formie-rating-field/commit/0b8b8af973802f94c51615bc2b4833a9658daafc))


### Miscellaneous Chores

* update config.php documentation for clarity ([0479024](https://github.com/LindemannRock/craft-formie-rating-field/commit/0479024c7984218b1d13ce03c8fd48ab3facad89))

## [3.2.0](https://github.com/LindemannRock/craft-formie-rating-field/compare/v3.1.2...v3.2.0) - 2025-11-25


### Features

* add Feed Me integration for Rating field and update composer suggestions ([81b10ba](https://github.com/LindemannRock/craft-formie-rating-field/commit/81b10ba5c1f0eef7fb0803cf47c5c2617187b94b))


### Bug Fixes

* update plugin property descriptions for clarity and add missing newline ([605a6ab](https://github.com/LindemannRock/craft-formie-rating-field/commit/605a6ab62e538034593615627fe5481b450aa6bc))

## [3.1.2](https://github.com/LindemannRock/craft-formie-rating-field/compare/v3.1.1...v3.1.2) - 2025-11-01


### Bug Fixes

* update validation method names for consistency and improve formatting in settings ([40f0ae5](https://github.com/LindemannRock/craft-formie-rating-field/commit/40f0ae5bddc60df7fcb23954c3cfd91b0094b8c2))

## [3.1.1](https://github.com/LindemannRock/craft-formie-rating-field/compare/v3.1.0...v3.1.1) - 2025-10-27


### Bug Fixes

* update README and configuration documentation for clarity and accuracy ([b68d573](https://github.com/LindemannRock/craft-formie-rating-field/commit/b68d573539f8064b304accf8e189e5ea97b291e5))

## [3.1.0](https://github.com/LindemannRock/craft-formie-rating-field/compare/v3.0.1...v3.1.0) - 2025-10-20


### Features

* add default emoji render mode setting and update related configurations ([1e6d686](https://github.com/LindemannRock/craft-formie-rating-field/commit/1e6d6860e8e4530142bdf110803c6ee3f32cadc3))
* add single emoji selection mode with custom labels ([1332dbd](https://github.com/LindemannRock/craft-formie-rating-field/commit/1332dbdcab5df0bf0840af2c29c43335226d11ba))

## [3.0.1](https://github.com/LindemannRock/craft-formie-rating-field/compare/v3.0.0...v3.0.1) - 2025-10-20


### Miscellaneous Chores

* update README with additional badges ([81fc2b8](https://github.com/LindemannRock/craft-formie-rating-field/commit/81fc2b843d265de1c3a256a8760e349c7d0bd8e2))

## [3.0.0](https://github.com/LindemannRock/craft-formie-rating-field/compare/v1.4.1...v3.0.0) - 2025-10-20


### Miscellaneous Chores

* bump version scheme to match Formie 3 ([b1eb1b0](https://github.com/LindemannRock/craft-formie-rating-field/commit/b1eb1b0771c27d6749f0697af345c51c85fe7763))

## [1.4.1](https://github.com/LindemannRock/craft-formie-rating-field/compare/v1.4.0...v1.4.1) - 2025-10-16


### Bug Fixes

* update CSS variable naming for rating fields to remove 'fui-' prefix ([7f481c6](https://github.com/LindemannRock/craft-formie-rating-field/commit/7f481c67c875792301d39399d1ced703fb32817d))

## [1.4.0](https://github.com/LindemannRock/craft-formie-rating-field/compare/v1.3.4...v1.4.0) - 2025-10-16


### Features

* add sentiment-based gradient colors for emoji ratings ([a7f6385](https://github.com/LindemannRock/craft-formie-rating-field/commit/a7f6385433bdf5218a324a9ebd9eb55222aef32a))

## [1.3.4](https://github.com/LindemannRock/craft-formie-rating-field/compare/v1.3.3...v1.3.4) - 2025-10-16


### Bug Fixes

* prevent numeric rating labels from being translated ([70a9974](https://github.com/LindemannRock/craft-formie-rating-field/commit/70a99742cfbc5d6b8ee6af799247fb1fc07171c9))

## [1.3.3](https://github.com/LindemannRock/craft-formie-rating-field/compare/v1.3.2...v1.3.3) - 2025-10-16


### Bug Fixes

* update installation instructions for Composer and DDEV ([1c1f172](https://github.com/LindemannRock/craft-formie-rating-field/commit/1c1f172f37335889af8dc25b6942d764102a21be))

## [1.3.2](https://github.com/LindemannRock/craft-formie-rating-field/compare/v1.3.1...v1.3.2) - 2025-10-16


### Bug Fixes

* change license from proprietary to MIT in composer.json ([9bf312c](https://github.com/LindemannRock/craft-formie-rating-field/commit/9bf312c5cccb6bd3280eb9d2799bce9a85b777a1))

## [1.3.1](https://github.com/LindemannRock/craft-formie-rating-field/compare/v1.3.0...v1.3.1) - 2025-10-16


### Bug Fixes

* update author information and add RSS feed to support section in composer.json ([db2bffb](https://github.com/LindemannRock/craft-formie-rating-field/commit/db2bffb8ee9b85994687e3f82d30cd2a8d94c9e3))

## [1.3.0](https://github.com/LindemannRock/craft-formie-rating-field/compare/v1.2.2...v1.3.0) - 2025-10-14


### Features

* add emoji web font support with Noto Color Emoji option ([ae7100b](https://github.com/LindemannRock/craft-formie-rating-field/commit/ae7100b044d312a336fb365339542b7bf395f409))

## [1.2.2](https://github.com/LindemannRock/craft-formie-rating-field/compare/v1.2.1...v1.2.2) - 2025-10-09


### Bug Fixes

* smart emoji selection based on rating range size ([6ae1891](https://github.com/LindemannRock/craft-formie-rating-field/commit/6ae1891e0913c632fd9a1e194f4106e7caf883be))

## [1.2.1](https://github.com/LindemannRock/craft-formie-rating-field/compare/v1.2.0...v1.2.1) - 2025-10-09


### Bug Fixes

* emoji rating to support full 0-10 range with 11 unique emojis ([077ae99](https://github.com/LindemannRock/craft-formie-rating-field/commit/077ae99910c5cc1142ad0971e720f914177bfe22))

## [1.2.0](https://github.com/LindemannRock/craft-formie-rating-field/compare/v1.1.0...v1.2.0) - 2025-10-07


### Features

* add font-family CSS variable for NPS rating buttons ([212c2de](https://github.com/LindemannRock/craft-formie-rating-field/commit/212c2de0eb13818792fe1ff24884f63c5d79f7e2))

## [1.1.0](https://github.com/LindemannRock/craft-formie-rating-field/compare/v1.0.2...v1.1.0) - 2025-10-06


### Features

* add conditions support to rating field ([044dcc9](https://github.com/LindemannRock/craft-formie-rating-field/commit/044dcc944ff0954e2d627c56ca6915387abda907))
* add CSS custom properties for scaling and border-radius, add conditions support ([48b1234](https://github.com/LindemannRock/craft-formie-rating-field/commit/48b12346877b2214a9c52fe1e8b43db545dd5333))


### Bug Fixes

* remove getFrontEndInputHtml override and fix CSS to prevent error display issues ([3d489a0](https://github.com/LindemannRock/craft-formie-rating-field/commit/3d489a0bb6b8fedf2951fc677815b88271d88493))
* update PHP requirement to ^8.2 in composer.json ([91bb729](https://github.com/LindemannRock/craft-formie-rating-field/commit/91bb729229384dd37dec76f5f11a90cfdfc7fd22))

## [1.0.2](https://github.com/LindemannRock/craft-formie-rating-field/compare/v1.0.1...v1.0.2) - 2025-09-26


### Bug Fixes

* corrupted minified JS file causing syntax errors in production ([a567a64](https://github.com/LindemannRock/craft-formie-rating-field/commit/a567a64791259b730a67228878a901f7423623bd))

## [1.0.1](https://github.com/LindemannRock/craft-formie-rating-field/compare/v1.0.0...v1.0.1) - 2025-09-24


### Bug Fixes

* update repository references and improve .gitignore entries ([65acf0e](https://github.com/LindemannRock/craft-formie-rating-field/commit/65acf0eafa28c6e4e1d0729ce8fb2328d02a49f4))

## 1.0.0 - 2025-09-15


### Features

* initial Formie Rating Field plugin implementation ([ae0bda9](https://github.com/LindemannRock/formie-rating-field/commit/ae0bda901b95b7adef3c28efc29a7e65c3ec3734))
