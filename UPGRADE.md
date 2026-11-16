# Upgrade Guide

## Client Events Default To Channel Members

When an application in your published `config/reverb.php` file does not define the `accept_client_events_from` option, which is the case for configuration files published before Reverb 1.8.0, Reverb now defaults to `members` instead of `all`. A client must be subscribed to a channel before it may send client events (whispers) to it; other clients receive a `4009` error.

To keep the previous behavior, add the option to each application in your `config/reverb.php` file:

```php
'accept_client_events_from' => env('REVERB_APP_ACCEPT_CLIENT_EVENTS_FROM', 'all'),
```
