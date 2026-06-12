/* ============================================================
   SocPlay — Core screens: Live Hub, Matches, Match Detail
   ============================================================ */

function groupByComp(matches) {
  const map = {};
  matches.forEach(m => { (map[m.comp] = map[m.comp] || []).push(m); });
  return Object.entries(map).sort((a, b) => window.PP.compById[a[0]].tier - window.PP.compById[b[0]].tier);
}
function SectionHead({ comp, count, action }) {
  const c = window.PP.compById[comp];
  return (
    <div className="pp-section-head">
      <span className="sh-title"><span className="sh-comp-dot" style={{ background: c.color }} />{c.name}</span>
      {count != null && <span className="sh-count">{count} {count === 1 ? 'match' : 'matches'}</span>}
      <span className="sh-line" />
      {action}
    </div>
  );
}

/* ====================== LIVE HUB ====================== */
function LiveHub({ app }) {
  const { matches, nav, isFav, toggleFav, refreshSeconds, paused, onRefreshTick } = app;
  const live = matches.filter(m => ['LIVE', 'HT', 'ET', 'PEN'].includes(m.status));
  const upcoming = matches.filter(m => m.status === 'SCHEDULED');
  const wc = window.PP.compById.wc26;
  const liveWc = live.filter(m => m.comp === 'wc26').length;

  return (
    <div className="pp-page pp-rise">
      <div className="pp-pagehead">
        <div>
          <h1>Live Hub</h1>
          <div className="ph-sub">Saturday, 14 June 2026 · {live.length} live now</div>
        </div>
        <div className="ph-actions">
          <RefreshIndicator seconds={refreshSeconds} paused={paused} onTick={onRefreshTick} />
        </div>
      </div>

      {/* World Cup spotlight */}
      <div className="pp-spotlight">
        <div className="sp-inner">
          <div>
            <div className="sp-tag"><IcTrophy size={14} /> Featured Competition</div>
            <h2>FIFA World Cup 2026</h2>
            <div className="sp-meta">{wc.host} · Group Stage · Matchday 2</div>
          </div>
          <div className="sp-stats">
            <div className="sp-stat"><b className="display tnum">{liveWc}</b><small>Live now</small></div>
            <div className="sp-stat"><b className="display tnum">48</b><small>Teams</small></div>
            <div className="sp-stat"><b className="display tnum">12</b><small>Groups</small></div>
            <div className="sp-stat" style={{ justifyContent: 'flex-end' }}>
              <button className="pp-btn primary" onClick={() => nav('competition', { id: 'wc26' })}>Explore <IcArrowR size={16} /></button>
            </div>
          </div>
        </div>
      </div>

      <div className="pp-hub">
        <div>
          {/* Live now hero cards */}
          <div className="pp-section">
            <div className="pp-section-head">
              <span className="sh-title"><LivePulseBadge /> Live now</span>
              <span className="sh-count">{live.length} matches</span>
              <span className="sh-line" />
            </div>
            <div className="pp-grid" style={{ gridTemplateColumns: 'repeat(auto-fit, minmax(440px, 1fr))' }}>
              {live.map(m => (
                <MatchCard key={m.id} m={m} expanded onOpen={() => nav('match', { id: m.id })}
                  fav={isFav('team', m.home) || isFav('team', m.away)} onFav={() => toggleFav('team', m.home)}
                  justScored={app.justScored === m.id} />
              ))}
            </div>
          </div>

          {/* Upcoming grouped by competition */}
          <div className="pp-section">
            <div className="pp-section-head">
              <span className="sh-title"><IcClock size={17} /> Upcoming today</span>
              <span className="sh-line" />
              <button className="pp-btn ghost sm" onClick={() => nav('matches')}>All fixtures <IcArrowR size={14} /></button>
            </div>
            {groupByComp(upcoming).map(([comp, ms]) => (
              <div key={comp} style={{ marginBottom: 18 }}>
                <SectionHead comp={comp} count={ms.length} />
                <div className="pp-grid cols-2">
                  {ms.map(m => <MatchCard key={m.id} m={m} onOpen={() => nav('match', { id: m.id })}
                    fav={isFav('team', m.home)} onFav={() => toggleFav('team', m.home)} />)}
                </div>
              </div>
            ))}
          </div>
        </div>

        {/* Right rail */}
        <aside className="pp-rail">
          <div className="pp-rail-card">
            <div className="rc-head"><span>Top of the table</span><span className="more" onClick={() => nav('competition', { id: 'epl' })}>Premier League</span></div>
            <div className="rc-body"><StandingsTable rows={window.PP.eplTable.slice(0, 6)} compact zones={(p) => p <= 4 ? 'ucl' : null} onTeam={(id) => nav('team', { id })} /></div>
          </div>
          <div className="pp-rail-card">
            <div className="rc-head"><span>World Cup top scorers</span><span className="more" onClick={() => nav('scorers')}>All</span></div>
            <div className="rc-body" style={{ padding: '4px 6px' }}>
              {window.PP.topScorers.slice(0, 5).map((s, i) => (
                <div key={i} style={{ display: 'flex', alignItems: 'center', gap: 10, padding: '8px 8px' }}>
                  <span className="display tnum" style={{ width: 18, color: i === 0 ? 'var(--accent)' : 'var(--text-muted)', fontWeight: 800 }}>{i + 1}</span>
                  <Crest id={s.team} size={20} />
                  <span style={{ flex: 1, fontSize: 13, fontWeight: 600 }}>{s.player}</span>
                  <span className="display tnum" style={{ fontWeight: 800, fontSize: 16 }}>{s.goals}</span>
                </div>
              ))}
            </div>
          </div>
        </aside>
      </div>
    </div>
  );
}

/* ====================== MATCHES / FIXTURES ====================== */
function MatchesScreen({ app }) {
  const { matches, nav, isFav, toggleFav } = app;
  const { days, todayIndex } = useMemo(() => window.PPX.dateStrip(), []);
  const [dayIdx, setDayIdx] = useState(todayIndex);
  const [filter, setFilter] = useState('all');

  const counts = {
    all: matches.length,
    live: matches.filter(m => ['LIVE', 'HT', 'ET', 'PEN'].includes(m.status)).length,
    upcoming: matches.filter(m => m.status === 'SCHEDULED').length,
    finished: matches.filter(m => m.status === 'FT').length,
  };
  const filtered = matches.filter(m => {
    if (filter === 'live') return ['LIVE', 'HT', 'ET', 'PEN'].includes(m.status);
    if (filter === 'upcoming') return m.status === 'SCHEDULED';
    if (filter === 'finished') return m.status === 'FT';
    return true;
  });
  const favMatches = filtered.filter(m => isFav('team', m.home) || isFav('team', m.away));
  const rest = filtered.filter(m => !favMatches.includes(m));
  const isToday = dayIdx === todayIndex;

  return (
    <div className="pp-page pp-rise">
      <div className="pp-pagehead">
        <div><h1>Matches</h1><div className="ph-sub">Browse fixtures &amp; results by date</div></div>
      </div>
      <div style={{ marginBottom: 16 }}><DateNavigator days={days} index={dayIdx} onIndex={setDayIdx} /></div>
      <div style={{ marginBottom: 20 }}>
        <FilterTabs value={filter} onChange={setFilter} counts={counts}
          tabs={[{ id: 'all', label: 'All' }, { id: 'live', label: 'Live', icon: <IcLive size={14} /> }, { id: 'upcoming', label: 'Upcoming' }, { id: 'finished', label: 'Finished' }]} />
      </div>

      {!isToday ? (
        <StateBlock icon={<IcCalendar size={26} />} title="No fixtures loaded" text={`Schedule for ${days[dayIdx].label} isn't available in this demo. Use Today to see live data.`}
          action={<button className="pp-btn primary" onClick={() => setDayIdx(todayIndex)} style={{ marginTop: 8 }}>Jump to Today</button>} />
      ) : filtered.length === 0 ? (
        <StateBlock icon={<IcInbox size={26} />} title="Nothing here yet" text="No matches match this filter right now." />
      ) : (
        <>
          {favMatches.length > 0 && (
            <div className="pp-section">
              <div className="pp-section-head"><span className="sh-title"><IcStar filled size={16} style={{ color: 'var(--draw)' }} /> Following</span><span className="sh-line" /></div>
              <div className="pp-grid cols-2">
                {favMatches.map(m => <MatchCard key={m.id} m={m} onOpen={() => nav('match', { id: m.id })} fav onFav={() => toggleFav('team', m.home)} />)}
              </div>
            </div>
          )}
          {groupByComp(rest).map(([comp, ms]) => (
            <div className="pp-section" key={comp}>
              <SectionHead comp={comp} count={ms.length} />
              <div className="pp-grid cols-2">
                {ms.map(m => <MatchCard key={m.id} m={m} onOpen={() => nav('match', { id: m.id })}
                  fav={isFav('team', m.home) || isFav('team', m.away)} onFav={() => toggleFav('team', m.home)} justScored={app.justScored === m.id} />)}
              </div>
            </div>
          ))}
        </>
      )}
    </div>
  );
}

/* ====================== MATCH DETAIL ====================== */
function MatchDetail({ app, params }) {
  const { matches, nav } = app;
  const hero = window.PP.hero;
  // Use hero match for the showcase; fall back to a found match
  const found = matches.find(m => m.id === params.id);
  const useHero = !found || (found.home === 'fra' && found.away === 'mar') || found.comp === 'wc26' && found.home === 'fra';
  const m = found && found.events ? found : (found && found.home === 'fra' && found.away === 'mar' ? { ...found, events: hero.events } : found || hero.matchSeed);
  const live = ['LIVE', 'HT', 'ET', 'PEN'].includes(m.status);
  const comp = window.PP.compById[m.comp];
  const [tab, setTab] = useState('summary');
  const showFull = m.home === 'fra' && m.away === 'mar';

  const tabs = [
    { id: 'summary', label: 'Summary', icon: <IcList size={15} /> },
    { id: 'lineups', label: 'Lineups', icon: <IcPitch size={15} /> },
    { id: 'stats', label: 'Stats', icon: <IcChart size={15} /> },
    { id: 'h2h', label: 'H2H', icon: <IcSwords size={15} /> },
    { id: 'table', label: 'Standings', icon: <IcList size={15} /> },
  ];

  return (
    <div className="pp-page pp-rise">
      <button className="pp-btn ghost sm" onClick={() => nav('live')} style={{ marginBottom: 14 }}><IcChevL size={15} /> Back</button>

      {/* Header */}
      <div className="pp-mh">
        <div className="mh-bg" style={{ background: `radial-gradient(120% 120% at 50% -20%, ${T(m.home).color}22, transparent 60%)` }} />
        <div className="mh-top">
          <span>{comp.name} · {m.stage}</span>
          {live ? <LivePulseBadge /> : <MatchStatus m={m} />}
        </div>
        <div className="mh-body">
          <div className="mh-team" onClick={() => nav('team', { id: m.home })} style={{ cursor: 'pointer' }}>
            <Crest id={m.home} size={64} ring={live} /><span className="nm">{T(m.home).name}</span>
          </div>
          <div className="mh-center">
            {m.status === 'SCHEDULED' ? (
              <><span className="display tnum" style={{ fontSize: 40, fontWeight: 800 }}>{m.kickoff}</span><MatchStatus m={m} /></>
            ) : (
              <>
                <span className="mh-score"><ScoreDigit value={m.hs} size={60} /><span className="sep">–</span><ScoreDigit value={m.as} size={60} /></span>
                {live ? <MinuteTicker m={m} /> : <MatchStatus m={m} />}
              </>
            )}
          </div>
          <div className="mh-team" onClick={() => nav('team', { id: m.away })} style={{ cursor: 'pointer' }}>
            <Crest id={m.away} size={64} ring={live} /><span className="nm">{T(m.away).name}</span>
          </div>
        </div>
        <div className="mh-meta">
          {m.venue && <span><IcPin size={14} />{m.venue}{m.city ? ', ' + m.city : ''}</span>}
          <span><IcCalendar size={14} />14 Jun 2026 · {m.kickoff || '20:00'}</span>
          {m.referee && <span><IcWhistle size={14} />{m.referee}</span>}
        </div>
      </div>

      {/* Tabs */}
      <div className="pp-tabs" style={{ marginBottom: 18 }}>
        {tabs.map(t => <button key={t.id} className={'tab' + (tab === t.id ? ' on' : '')} onClick={() => setTab(t.id)}>{t.icon}{t.label}</button>)}
      </div>

      {tab === 'summary' && <MatchSummary m={m} showFull={showFull} />}
      {tab === 'lineups' && (showFull
        ? <FormationView home={{ id: m.home, lineup: hero.lineups.home }} away={{ id: m.away, lineup: hero.lineups.away }} />
        : <StateBlock icon={<IcPitch size={26} />} title="Lineups not yet confirmed" text="Starting XI is published ~1 hour before kick-off." />)}
      {tab === 'stats' && (showFull
        ? <MatchStats stats={hero.stats} home={m.home} away={m.away} />
        : <StateBlock icon={<IcChart size={26} />} title="No stats yet" text="Match statistics appear once the match kicks off." />)}
      {tab === 'h2h' && <div className="pp-panel"><h3 className="panel-title"><IcSwords size={16} /> Head-to-Head</h3><H2HWidget records={hero.h2h} home={m.home} away={m.away} /></div>}
      {tab === 'table' && <MatchMiniTable m={m} nav={nav} />}
    </div>
  );
}

function MatchSummary({ m, showFull }) {
  const events = m.events || window.PP.hero.events;
  if (!showFull && (!m.events || m.events.length === 0)) {
    if (m.status === 'SCHEDULED') return <StateBlock icon={<IcClock size={26} />} title="Match hasn't started" text="Live events — goals, cards, substitutions — will stream here in real time once the match kicks off." />;
  }
  return (
    <div className="pp-panel">
      <h3 className="panel-title"><IcList size={16} /> Match events {['LIVE','HT','ET'].includes(m.status) && <span style={{ marginLeft: 'auto' }}><LivePulseBadge small /></span>}</h3>
      <div className="pp-timeline">
        {[...events].reverse().map((ev, i) => <TimelineEvent key={i} ev={ev} />)}
        <div className="pp-tl-mid"><span className="tl-line" /><span className="tl-chip"><IcWhistle size={13} /> Kick-off</span><span className="tl-line" /></div>
      </div>
    </div>
  );
}
function MatchStats({ stats, home, away }) {
  return (
    <div className="pp-panel">
      <PossessionBar home={stats.possession[0]} away={stats.possession[1]} />
      <StatComparisonBar label="Shots" home={stats.shots[0]} away={stats.shots[1]} />
      <StatComparisonBar label="Shots on target" home={stats.shotsOnTarget[0]} away={stats.shotsOnTarget[1]} />
      <StatComparisonBar label="Expected goals (xG)" home={stats.xg[0]} away={stats.xg[1]} />
      <StatComparisonBar label="Corners" home={stats.corners[0]} away={stats.corners[1]} />
      <StatComparisonBar label="Fouls" home={stats.fouls[0]} away={stats.fouls[1]} />
      <StatComparisonBar label="Yellow cards" home={stats.yellow[0]} away={stats.yellow[1]} />
      <StatComparisonBar label="Offsides" home={stats.offsides[0]} away={stats.offsides[1]} />
      <StatComparisonBar label="Passes" home={stats.passes[0]} away={stats.passes[1]} />
      <StatComparisonBar label="Pass accuracy" home={stats.passAcc[0]} away={stats.passAcc[1]} suffix="%" />
    </div>
  );
}
function MatchMiniTable({ m, nav }) {
  if (m.comp === 'wc26') {
    const letter = m.stage.replace('Group ', '');
    if (window.PP.groups[letter]) {
      const rows = window.PPX.groupStandings(letter);
      return <div className="pp-panel"><h3 className="panel-title"><IcList size={16} /> Group {letter}</h3>
        <StandingsTable rows={rows} highlight={m.home} zones={(p) => p <= 2 ? 'ucl' : null} onTeam={(id) => nav('team', { id })} /></div>;
    }
  }
  return <div className="pp-panel"><h3 className="panel-title"><IcList size={16} /> Premier League</h3>
    <StandingsTable rows={window.PP.eplTable} highlight={m.home} zones={(p) => p <= 4 ? 'ucl' : p <= 6 ? 'uel' : p >= 18 ? 'rel' : null} onTeam={(id) => nav('team', { id })} /></div>;
}

Object.assign(window, { LiveHub, MatchesScreen, MatchDetail, groupByComp, SectionHead });
