# Feature Request: One-to-Many Presence Channel for User Status

## Problem
Laravel Reverb doesn't currently support a one-way presence pattern where a user can be "public" in their channel (e.g., `presence-users.{user_id}.status`), and other users can subscribe to it without revealing themselves.

## Proposal
- The channel owner is marked online when subscribing.
- Subscribers get online/offline events for that user only.
- No presence info about subscribers is revealed to the channel owner or other listeners.

## Use Case
Just like WhatsApp shows if a contact is online/offline, this allows profile-view-based online indicators with privacy preserved.

## Why it's important
This is a common pattern in chat/social apps. Currently, implementing it requires a lot of custom logic.

Thanks for considering!
