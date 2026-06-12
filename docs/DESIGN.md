# LiveGoal — Design System

Source of truth: [`design-ref/project/`](./design-ref/project/) — `tokens.css` (tokens), `components.css`
(component styles), `app.css` (layout), the `*.jsx` prototypes (React → port to Vue), and
`screenshots/` (visual reference, gitignored). Recreate **pixel-perfectly** in Vue + Tailwind; match the
output, not the prototype's internal structure (design README).

## Theming

- Two themes via `data-theme` on `<html>`: **`dark` = "Stadium Night" (default)** and **`light` = "Daylight"**.
- Persisted in `localStorage`; first run seeded by `usePreferredDark`.
- All colors are CSS custom properties that swap per theme — components reference variables, never hex.
- Port `tokens.css` verbatim into `resources/css/tokens.css`; expose the same variables to Tailwind v4 via
  `@theme` so utilities (`bg-[var(--surface)]`, etc.) and raw variables coexist.

## Tokens (from `tokens.css`)

**Fonts** (Google Fonts): `Saira Condensed` → `--font-display` (scores/headings), `Hanken Grotesk` →
`--font-body` (UI/body), `Geist Mono` → `--font-mono` (minute/clock; tabular nums).

**Type scale (px):** score 56 · display-xl 44 · display-lg 32 · display-md 24 · h1 22 · h2 18 · h3 15 ·
body 14 · sm 13 · xs 11.

**Spacing (4px scale):** `--s-1`=4 … `--s-10`=64. **Radius:** xs 8 · sm 12 · md 14 · lg 16 · xl 22 · pill 999.

**Brand accents (hue is theme-independent; contrast handled per theme):** volt `#C6FF3A` (primary energy),
cyan `#00E5FF`, pitch `#21C17A`. In **light**, `--accent` darkens to `#2FA300` for AA on white.

**Semantic status:** live `#FF3D3D` · win `#21C17A` · draw `#F5A524` · loss `#F4434A` · info `#3B82F6`
(light overrides: live `#E5121F`, win `#128A53`, draw `#C97B05`, loss `#D32430`).

**Dark surfaces:** bg-base `#0A0D12` · bg-deep `#070A0E` · surface `#131820` · surface-2 `#1A212C` ·
surface-3 `#222B38` · border `#232B36`. **Text:** `#E6EAF0` / `#B6BEC9` / `#8A93A3` / `#5E6675`.
**Light surfaces:** bg-base `#EEF1F6` · surface `#FFFFFF` · border `#DFE4EC`. Text `#0C1118` → `#97A0AE`.
(Full set incl. shadows, skeleton, gradients, scrims in `tokens.css`.)

**Layout:** shell-max 1400 · sidebar-w 248 · rail-w 320 · topbar-h 56 · ticker-h 46 · tabbar-h 64.

**Motion:** `--ease-out` cubic-bezier(0.16,1,0.3,1) · `--ease-in-out` cubic-bezier(0.65,0,0.35,1) ·
durations fast 140ms / 240ms / slow 420ms. Keyframes (in `tokens.css`): `pp-pulse` (live dot),
`pp-ring` (live ping), `pp-shimmer` (skeleton), `pp-flip-in` (score digit), `pp-goal-in` (GOAL toast),
`pp-rise`, `pp-spin`, `pp-glow-breathe`, `pp-flash`.

> **Motion principle (keep it):** the visible end-state is the BASE; animation is *additive* so content
> never hides if the animation can't play. Honor `prefers-reduced-motion` (global override already in
> `tokens.css`) and the in-app "reduce motion" setting.

## Layout & navigation

- **Desktop (≥1024px):** top utility bar (logo · search · refresh ring · bell · theme · settings) →
  **top horizontal nav** (Live / Matches / Competitions / Following / Top Scorers) → slim live ticker →
  main content (LiveHub uses a right rail for standings/ticker). **No left sidebar** — superseded per the
  chat transcript (Sofascore/FotMob genre).
- **Mobile:** compact top bar (logo · search · theme) + live ticker + **bottom tab bar**
  (Live / Matches / Comps / Following / More).
- Global search opens as a modal on `/` or `⌘K`.

## Component inventory (design → Vue)

Port each to a `.vue` SFC under `resources/js/components/` (states under `components/states/`). Keep the
`pp-` CSS classes so `components.css` applies unchanged.

**Core** (`components-core.jsx`): `Crest`, `TeamChip`, `CountryFlag`, `ScoreDisplay`, `ScoreDigit`,
`MatchStatus`, `MinuteTicker`, `LivePulseBadge`, `MatchCard`, `MatchCardSkeleton`, `FormGuide`,
`FilterTabs`, `FavoriteStar`, `RefreshIndicator`, `Skeleton`, `StateBlock`.

**Detail** (`components-detail.jsx`): `StandingsTable`, `GroupCard`, `Bracket`, `BracketNode`,
`H2HWidget`, `TimelineEvent`, `StatComparisonBar`, `PossessionBar`, `DateNavigator`, `CalendarPopover`,
`GoalToast`, `Pitch`, `FormationView`.

**Shell** (`shell.jsx`): `Logo`, `TopNav`, `LiveTicker`, `TabBar`, `RefreshIndicator`, ARIA-live region.
**Search** (`search-modal.jsx`): `SearchModal`.

**Icons** (`icons.jsx`): inline SVG set (`IcBall`, `IcLive`, `IcCalendar`, `IcTrophy`, `IcStar`,
`IcChart`, `IcSearch`, `IcBell`, `IcSun`, `IcMoon`, `IcSettings`, `IcMore`, …). Spec also allows
`lucide-vue-next`; prefer porting the design's icon set for fidelity, fall back to lucide where it matches.

## Screen inventory (design → Vue pages)

**Primary (fully populated):** `LiveHub`, `Matches`, Results (within Matches/by competition),
`Standings` + World Cup `GroupCard`s (12 groups), `Bracket`, `CompetitionDetail` (tabbed:
Standings/Groups · Bracket · Fixtures · Results · Top Scorers · Teams), `TeamDetail`
(overview/fixtures/results/standings), `MatchDetail` (score header + H2H + mini-standings),
`TopScorersScreen` (`TopScorersList`, `Legend`).

**Secondary (complete-when-empty):** MatchDetail extra tabs (`MatchSummary`/events, `FormationView`/
lineups, `MatchStats`/`StatComparisonBar`), `PlayerDetail`, Team squad, `FavoritesScreen`,
`SearchScreen`, `SettingsScreen`, `NotFound`.

**States** (`components/states/`): `Skeleton` (shimmer), `EmptyState` (intentional "Not available on this
data plan"), `ErrorState` (+ retry), `OfflineState`.

## Accessibility

WCAG AA contrast (tokens already tuned, esp. light `--accent`), visible focus ring (`:focus-visible`),
keyboard nav, ARIA-live region announcing score changes (in `shell.jsx`), `prefers-reduced-motion`
respected, tabular-nums for scores/clocks.
