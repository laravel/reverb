### Feature Request: One-to-Many Presence Channel Support for User Status Broadcasting

**Problem Description**

Currently, Laravel Reverb supports presence channels where all users in a channel are visible to each other. However, I want to implement a use case similar to WhatsApp where:

- Each user has their own presence channel, e.g., `presence-users.{user_id}.status`.
- When a user subscribes to their own channel, they are considered "online."
- Other users can subscribe to someone else’s presence channel to be notified of their online/offline status (e.g., when viewing a profile).
- The channel owner should **not** receive details about who is subscribed to their presence channel (privacy).
- Subscribers should receive only the channel owner's presence info (user ID, name, avatar, etc.), not the list of other subscribers.

**Proposed Behavior**

1. When a user subscribes to `presence-users.{user_id}.status`:
   - That user is marked as "online."
   - Presence tracking is enabled, but limited to one-way visibility.

2. When other users subscribe to the same channel:
   - They do **not** appear in the presence list.
   - They receive online/offline updates for the channel owner (the original user).
   - They only get the owner’s metadata, not other subscribers’.

3. When the owner disconnects (leaves the channel), a `user_offline` event is fired.

**Use Case Example**

- Alice is viewing Bob's profile.
- Alice subscribes to `presence-users.2.status`.
- If Bob is online, Alice receives a `user_online` event with Bob's details.
- Bob does not know Alice is subscribed.
- If Bob goes offline, Alice receives a `user_offline` event.

**Alternatives Considered**

- Using custom logic with `Echo.join` and separate backend events, but this adds unnecessary complexity.
- Faking presence with timers and heartbeat pings, which is less reliable and more resource-heavy.

**Why This Matters**

This kind of controlled one-to-many presence tracking is a common pattern in chat and social apps (e.g., WhatsApp, Telegram, Instagram). Adding built-in support for this in Laravel Reverb would make it much easier to implement privacy-focused online indicators.

Thanks again for building and maintaining Reverb — it's awesome so far! 🙌
