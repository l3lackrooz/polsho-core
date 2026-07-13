# MVP Task List — Live Market Prices (Laravel + Reverb + Flutter)

Goal: prices from Iranian exchanges → aggregated → broadcast over Reverb → shown live in Flutter.

## 1. Backend core (done ✅)

- [x] Fix broadcast path: `MarketDataUpdated` implements `ShouldBroadcastNow` (event: `market.quote.updated`, channels: `market.quotes`, `market.quotes.{instrument}`)
- [x] Fix `MarketDataService` constructor bug
- [x] Remove dead `BroadcastMarketDataListener`
- [x] Wallex provider (Client/Mapper/Driver + factory registration)
- [x] Bitpin provider (tickers + top-of-orderbook for bid/ask)
- [x] OMPFinex provider (defensive mapper, seeded inactive)
- [x] `NewProvidersSeeder` + registered in `DatabaseSeeder`

## 2. Backend — remaining for MVP

- [ ] Run `composer test` / boot app once (no PHP was available in the sandbox, so files are unverified by an interpreter)
- [ ] Seed new providers: `php artisan db:seed --class="App\Domain\Market\Infrastructure\Persistence\Seeders\NewProvidersSeeder"`
- [ ] `php artisan cache:clear` (provider mappings cached 1 hour)
- [ ] Verify Wallex + Bitpin live: `php artisan market:sync wallex --now`, `php artisan market:sync bitpin --now`
- [ ] Verify OMPFinex from Iran: `market:sync ompfinex --now`, fill real market ids in seeder, fix `OmpfinexMapper::FIELD_CANDIDATES` if needed, set provider active
- [ ] Switch `QUEUE_CONNECTION=database` → `redis` in `.env` (database queue will bottleneck at 10s cadence × 6 providers)
- [ ] Snapshot REST endpoint: `GET /api/market/quotes` returning current `AggregateStore` values (Flutter paints initial screen before first websocket tick; also used on reconnect)
- [ ] Clean up duplicate `LatestQuoteStore` (`Infrastructure/Cache` vs `Infrastructure/Stores`) — keep one
- [ ] Make sure processes run together: `php artisan reverb:start`, `php artisan queue:work redis --queue=market`, `php artisan schedule:work` (or supervisor/docker services)
- [ ] Snapshot table growth: add a daily prune command for `market_snapshots` (10s cadence ≈ 500k+ rows/month per provider)

## 3. Flutter — MVP client

- [ ] Add `pusher_channels_flutter` package, point at Reverb host + port `8081`, key = `REVERB_APP_KEY`
- [ ] On app start: fetch snapshot from REST endpoint → render list
- [ ] Subscribe to `market.quotes` (all symbols) or `market.quotes.{instrument}` (per detail screen)
- [ ] Bind event name exactly `market.quote.updated` (no `App\Events\` prefix — `broadcastAs` is used)
- [ ] Payload shape: `{instrument, best_bid{...}, best_ask{...}, providers[...], timestamp}` — `QuoteDTO` fields: bid, ask, last, mid, spread, volume, provider
- [ ] On disconnect/reconnect: re-fetch snapshot, then resume stream
- [ ] Basic UI: instrument list with price + up/down tick coloring

## 4. Deployment sanity (minimum)

- [ ] Reverb behind TLS in production (`REVERB_SCHEME=https`), change `REVERB_APP_KEY/SECRET` from `local`
- [ ] Expose Reverb port publicly (already mapped 8081 in docker-compose)
- [ ] Health checks: `/up` endpoint + `healthCheck()` per provider (already implemented, wire to monitoring later)

## Out of scope for MVP (later)

- Websocket streaming from exchanges (`market:stream`) instead of 10s polling
- Global reference feed (Binance/OKX) for outlier sanity-checking
- Second-tier exchanges (Exir, Excoino, Bit24, Sarmayex, Aban Tether)
- Auth / private channels, user accounts, alerts (Bale notifications already exist)
- Reverb horizontal scaling (`REVERB_SCALING_ENABLED` + Redis pub/sub)
