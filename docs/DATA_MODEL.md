# LiveGoal — Data Model

Normalized DTO shapes the API returns and the SPA consumes. Shapes mirror the design's mock
`design-ref/project/data.js` so the prototype maps 1:1; the `Normalizer` converts football-data.org v4
JSON into these.

## Conventions

- IDs are strings. Where the design uses short slugs (`fra`, `mci`, `wc26`) and football-data uses
  numeric ids/codes, the `Normalizer` keeps the **upstream numeric id** as the canonical `id` and adds a
  `code`/`short`. A static map (`config/football.php`) links free competition codes ↔ display meta
  (name, short, color, kind, region) per the design's competition list.
- All times are stored/sent as ISO 8601 UTC; the SPA formats per the user's timezone setting.
- Crest/flag URLs are pass-through (browser `<img>`). Clubs may lack a crest → SPA renders a colored
  monogram badge (design behavior); nations use the upstream/flagcdn flag.

## Competition

```jsonc
{
  "id": "2000",            // upstream id
  "code": "WC",            // football-data code
  "name": "FIFA World Cup",
  "short": "World Cup",
  "region": "International",
  "kind": "cup",           // "cup" | "league"
  "color": "#C6FF3A",      // from design competition meta
  "tier": 1,
  "featured": true,        // WC spotlight
  "emblem": "https://…"    // pass-through, optional
}
```

## Team

```jsonc
{
  "id": "773",
  "name": "France",
  "short": "FRA",
  "type": "nation",        // "nation" | "club"
  "crest": "https://…",    // pass-through; null for trademarked club logos
  "mono": "MC",            // monogram fallback for clubs (design)
  "color": "#1B3A7A",      // primary (design meta / derived)
  "color2": "#EF4135",
  "flag": "https://flagcdn.com/w160/fr.png" // nations only
}
```

## Match

```jsonc
{
  "id": "419001",
  "competition": "2000",
  "stage": "GROUP_STAGE",  // upstream stage; display label derived
  "group": "Group F",      // when applicable
  "status": "LIVE",        // normalized — see mapping below
  "minute": 74,            // null unless live
  "period": "2H",          // 1H|HT|2H|ET|PEN (display)
  "home": "773", "away": "758",
  "homeScore": 2, "awayScore": 1,   // current/full-time
  "kickoff": "2026-06-07T18:00:00Z",
  "venue": "MetLife Stadium",
  "city": "New York",
  "referee": "C. Ramos",
  "lastUpdated": "2026-06-07T18:00:00Z"
}
```

### Status mapping (football-data.org → LiveGoal)

| Upstream | LiveGoal | Notes |
|---|---|---|
| `SCHEDULED`, `TIMED` | `SCHEDULED` | show kickoff time |
| `IN_PLAY` | `LIVE` | derive `period`/`minute` from `utcDate` + score; HT inferred when paused at 45 |
| `PAUSED` | `HT` | half-time |
| `FINISHED` | `FT` | |
| `POSTPONED` | `POSTPONED` | |
| `SUSPENDED` | `LIVE`→suspended badge | treat as live-interrupted |
| `CANCELLED` | `POSTPONED` | with cancelled label |

> Extra-time/penalties (`ET`/`PEN`) aren't distinct upstream statuses on the free tier; infer from
> score/duration where possible, else fall back to `LIVE`/`FT`. Minute is approximate (free-tier delay).

## StandingGroup + StandingRow

`/competitions/{id}/standings` may return multiple standings (tournaments → groups A–L). Parse into:

```jsonc
{
  "groups": [
    {
      "key": "GROUP_A",
      "label": "Group A",
      "rows": [
        {
          "position": 1, "team": "<Team>",
          "played": 3, "won": 2, "draw": 1, "lost": 0,
          "goalsFor": 6, "goalsAgainst": 2, "goalDifference": 4, "points": 7,
          "form": ["W","D","W"],          // recent results for FormGuide pills
          "zone": "qualify"               // qualify|playoff|relegation|null -> row coloring
        }
      ]
    }
  ]
}
```

Leagues return a single group (`label` empty / "Table"). Qualification-zone coloring is derived from
position + competition kind (config-driven thresholds).

## Scorer

```jsonc
{ "rank": 1, "player": "<Person>", "team": "<Team>", "goals": 8, "assists": 3, "penalties": 1 }
```

## Person (player)

```jsonc
{
  "id": "44", "name": "Kylian Mbappé", "position": "Offence",
  "dateOfBirth": "1998-12-20", "nationality": "France", "team": "<Team>"
}
```

Free tier has **no detailed player stats** → PlayerDetail is graceful-empty beyond this header.

## Live payload (`/api/live`)

```jsonc
{
  "data": {
    "matches": [ <Match>, … ],   // only LIVE/HT/ET/PEN
    "count": 3
  },
  "meta": { "lastUpdated": "…", "stale": false, "cached": true }
}
```

Each live `Match` also carries the **prior score** (server-side) so the client can detect goals; the
client additionally diffs against its own previous poll to fire the animation (see
[LIVE_POLLING.md](./LIVE_POLLING.md)).
