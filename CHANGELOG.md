# Release Notes

## [Unreleased](https://github.com/laravel/reverb/compare/v1.5.1...main)

## [v1.5.1](https://github.com/laravel/reverb/compare/v1.5.0...v1.5.1) - 2025-06-16

* Update logo by [@iamdavidhill](https://github.com/iamdavidhill) in https://github.com/laravel/reverb/pull/330
* Fix encoding of data field in presence channel events to match Pusher protocol by [@alinapotupchik](https://github.com/alinapotupchik) in https://github.com/laravel/reverb/pull/337

## [v1.5.0](https://github.com/laravel/reverb/compare/v1.4.8...v1.5.0) - 2025-03-31

* [1.x] Bump dependencies to fix tests by [@joedixon](https://github.com/joedixon) in https://github.com/laravel/reverb/pull/323
* [1.x] Adds support for reconnecting to Redis if disconnected by server by [@joedixon](https://github.com/joedixon) in https://github.com/laravel/reverb/pull/281
* [1.x] Sets X-Powered-By header by [@joedixon](https://github.com/joedixon) in https://github.com/laravel/reverb/pull/327
* [1.x] Fix issue with quoted string in .env when running install command by [@joedixon](https://github.com/joedixon) in https://github.com/laravel/reverb/pull/328

## [v1.4.8](https://github.com/laravel/reverb/compare/v1.4.7...v1.4.8) - 2025-03-16

* Allow client event without data by [@v-sulzhin](https://github.com/v-sulzhin) in https://github.com/laravel/reverb/pull/315

## [v1.4.7](https://github.com/laravel/reverb/compare/v1.4.6...v1.4.7) - 2025-03-06

* [1.x] Adds support for server path prefix by [@adrum](https://github.com/adrum) in https://github.com/laravel/reverb/pull/311

## [v1.4.6](https://github.com/laravel/reverb/compare/v1.4.5...v1.4.6) - 2025-01-28

* [1.x] Add Message Payload Validation and Improve Channel Data Handling by [@MahdiBagheri71](https://github.com/MahdiBagheri71) in https://github.com/laravel/reverb/pull/303
* Supports Laravel 12 by [@crynobone](https://github.com/crynobone) in https://github.com/laravel/reverb/pull/304

## [v1.4.5](https://github.com/laravel/reverb/compare/v1.4.4...v1.4.5) - 2024-12-27

* [1.x] Fix tests by [@joedixon](https://github.com/joedixon) in https://github.com/laravel/reverb/pull/293
* [1.x] Check if channel variable isn't null when passed as a filter on channels() method in ArrayChannelManager by [@kirills-morozovs](https://github.com/kirills-morozovs) in https://github.com/laravel/reverb/pull/292

## [v1.4.4](https://github.com/laravel/reverb/compare/v1.4.3...v1.4.4) - 2024-12-06

* [1.x] Allow publishing to others only when using Redis by [@larswolff](https://github.com/larswolff) in https://github.com/laravel/reverb/pull/275
* Herd is available for Windows, this update enables certificate checki… by [@iaidan](https://github.com/iaidan) in https://github.com/laravel/reverb/pull/277
* Bugfix for PR 275 - omit socket-id if null by [@larswolff](https://github.com/larswolff) in https://github.com/laravel/reverb/pull/280

## [v1.4.3](https://github.com/laravel/reverb/compare/v1.4.2...v1.4.3) - 2024-10-31

* Test against php 8.4 by [@sergiy-petrov](https://github.com/sergiy-petrov) in https://github.com/laravel/reverb/pull/267

## [v1.4.2](https://github.com/laravel/reverb/compare/v1.4.1...v1.4.2) - 2024-10-24

* [1.x] Optionally uses control frames for handling ping and pong by [@joedixon](https://github.com/joedixon) in https://github.com/laravel/reverb/pull/253

## [v1.4.1](https://github.com/laravel/reverb/compare/v1.4.0...v1.4.1) - 2024-10-04

* [1.x] Re-subscribes to the scaling channel when the underlying connection is lost by [@ashiquzzaman33](https://github.com/ashiquzzaman33) in https://github.com/laravel/reverb/pull/251

## [v1.4.0](https://github.com/laravel/reverb/compare/v1.3.1...v1.4.0) - 2024-10-01

* [1.x] Implements API signature verification by [@joedixon](https://github.com/joedixon) in https://github.com/laravel/reverb/pull/252
* [1.x] Supports Laravel Prompts 0.3 by [@crynobone](https://github.com/crynobone) in https://github.com/laravel/reverb/pull/255

## [v1.3.1](https://github.com/laravel/reverb/compare/v1.3.0...v1.3.1) - 2024-09-19

* [1.x] Supports `laravel/prompts` v0.2 by [@crynobone](https://github.com/crynobone) in https://github.com/laravel/reverb/pull/249

## [v1.3.0](https://github.com/laravel/reverb/compare/v1.2.0...v1.3.0) - 2024-09-03

* Add an activity timeout configuration option by [@zeke0816](https://github.com/zeke0816) in https://github.com/laravel/reverb/pull/241

## [v1.2.0](https://github.com/laravel/reverb/compare/v1.1.0...v1.2.0) - 2024-08-16

* Dispatch an event when channel created or removed. by [@kirills-morozovs](https://github.com/kirills-morozovs) in https://github.com/laravel/reverb/pull/242

## [v1.1.0](https://github.com/laravel/reverb/compare/v1.0.0...v1.1.0) - 2024-08-06

* Dispatch an event when a connection is pruned. by [@joekaram](https://github.com/joekaram) in https://github.com/laravel/reverb/pull/235
* [1.x] Add support for wildcard allowed origins by [@rabrowne85](https://github.com/rabrowne85) in https://github.com/laravel/reverb/pull/233
* Prevent message with data: "" taking server down by [@lee-van-oetz](https://github.com/lee-van-oetz) in https://github.com/laravel/reverb/pull/237
* Remove duplicate code by [@FeBe95](https://github.com/FeBe95) in https://github.com/laravel/reverb/pull/240

## v1.0.0 - 2024-07-02

Initial release.
