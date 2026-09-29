# Market milestone notifications

Backoffice → Market milestones, or the bell action on an instrument.
Rules start disabled. Set an interval in the instrument's quote currency (IRT means
toman), direction, confirmation count and repeat cooldown. Both **best price to buy**
(lowest eligible ask) and **best price to sell** (highest eligible bid) are followed
automatically in the background. There is no exchange or price-side selector. The evaluator uses the canonical aggregator's
filtered best_ask/best_bid, the same prices used for market comparisons, and records
the winning exchange, price side and observed price on every event. A change in the
winning exchange does not reset the rule. Reference, stale, invalid and inactive
sources cannot trigger. No last-price or opposite-side fallback is used.
Aggregate timestamps order evaluations; replaying one cached winning quote cannot
supply an additional confirmation simply because another exchange updated.

## Crossing semantics

At 253,678 with a 1,000 interval, next levels are 254,000 and 253,000.
Two distinct, fresh source quotes must confirm sustained movement beyond a level by default.
Further jumps in the same direction count toward confirmation; the notification
reports the furthest level crossed on the confirming quote. Duplicate
and older timestamps cannot confirm. After 254,000 is confirmed, the new targets
are 255,000 and 253,000; wobbling around 254,000 does not notify repeatedly.
A jump to 256,200 produces a single 256,000 milestone after confirmation.
Buy and sell keep independent baselines and confirmations. Simultaneous confirmations
of the same level and direction produce one notification containing both source prices.
Different levels produce separate events. The cooldown is shared across both sides,
per level and direction; suppressed milestones still advance the
anchor, so a different level remains eligible. Disabled directions also advance
the anchor. Quote gaps over 60 seconds reset the baseline without a catch-up push.
Configuration changes reset the baseline and invalidate queued older events.
Quotes predating activation are ignored. Arithmetic uses the already-locked
Brick Math decimal library at eight decimal places.

## Delivery and operational behavior

An event and updated rule state commit in one transaction under a row lock.
`market:dispatch-milestones` runs every ten seconds and recovers pending fanout
and deliveries. Recipients are expanded in resumable 100-user batches, bounded
by the user IDs existing when the event was created. One delivery per event,
provider and target is enforced by a database unique key. Queued jobs are unique
until processing; per-delivery leases prevent simultaneous sends.

Delivery retries temporary exceptions up to three attempts with 10/60-second
backoff. Five-minute expiration is checked before sending and passed through to
FCM/APNs and Pushe. Invalid iOS tokens are disabled only if the token still matches.
Eligibility and rule revision are checked again before sending. Pausing cancels
pending sends; a request already in flight may complete. `sent` means provider
accepted, not device display or read. A crash after provider acceptance but before
recording success can still cause a duplicate on retry: external push delivery
cannot promise exactly once. Event IDs are included in iOS data payloads.

Audience uses the existing authenticated device registry: enabled registered
users' devices whose account has `market_milestones_enabled = true`, with no legacy fallback for users lacking devices. Android uses
existing Pushe user grouping; iOS uses individual FCM tokens. Persian device locales
receive Persian copy; other locales receive English. No push is sent merely by
saving a rule. Broadcasts use their own queue to avoid blocking market ingestion.

## Deploy

1. Back up the database and deploy the code; run `php artisan migrate --force`
   before restarting workers with the new code.
2. Docker builds now include `docker/milestone-worker.conf`. For a bind-mounted
   deployment without rebuilding, install that file in Supervisor's conf.d,
   then reread/update Supervisor. Match its database queue connection and
   `notifications` queue to your environment. The configured worker timeout
   must remain below queue retry_after and the 60-second delivery lease.
3. Restart application/market workers, ensure the scheduler is running and
   `notifications` workers are RUNNING. Use a shared cache for job uniqueness.
4. Verify backoffice settings/history and queue health while all rules are paused.
   Enable the desired instrument only after inspecting the tracked prices and interval.

## Application notification settings

Market milestone alerts are **on by default** for existing and new accounts.
Authenticated `GET /api/notification-preferences` and `PATCH /api/notification-preferences`
read/write `market_milestones_enabled` for the caller only. Registration and token
refresh never overwrite this preference. Both fanout and sending check it, including
already queued notifications. Existing personal price alerts use their original
preferences and are unaffected. An in-flight provider request cannot be recalled.

The Flutter app exposes Notification settings from the menu, Profile and notification
inbox. Changes are saved to the server for all devices on the account. Failed or
uncertain saves reload the server value and show a retry message. Signed-out users
are prompted to sign in, since anonymous devices are not registered for delivery.
The account default does not grant OS notification permission or enable an admin rule.

## Scope and next steps

This release covers backend, backoffice and mobile notification preferences. No migration automatically enables
rules. Existing mobile versions open the app on a milestone push; instrument-chart
routing, an in-app milestone inbox, per-instrument subscriptions/quiet hours, anonymous
device registration and additional translations require mobile/API follow-up.
There is no test-send-to-all button. Simulate prices through the automated tests
or an isolated database, never by modifying production market quotes.

`php artisan test --filter=MarketMilestoneTest` exercises round crossings,
confirmation, reversals, gaps, decimals, permissions, rule resets, fanout,
retry/expiry, device eligibility and outbox recovery without real push sends.
