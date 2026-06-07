/* ============================================================
   SocPlay — Competitions, Comp Detail, Team, Player, Scorers
   ============================================================ */

/* ====================== COMPETITIONS GRID ====================== */
function CompetitionsScreen({ app }) {
  const { nav, matches } = app;
  const liveCount = (cid) => matches.filter(m => m.comp === cid && ['LIVE', 'HT', 'ET', 'PEN'].includes(m.status)).length;
  return (
    <div className="pp-page pp-rise">
      <div className="pp-pagehead"><div><h1>Competitions</h1><div className="ph-sub">Leagues, cups &amp; international tournaments</div></div></div>
      <div className="pp-grid cols-3">
        {window.PP.competitions.map(c => {
          const lc = liveCount(c.id);
          return (
            <div key={c.id} className="pp-comptile" onClick={() => nav('competition', { id: c.id })}>
              {lc > 0 && <span className="ct-live"><LivePulseBadge label={`${lc} LIVE`} small /></span>}
              <div className="ct-logo" style={{ background: `linear-gradient(135deg, ${c.color}, ${c.color}cc)` }}>
                {c.kind === 'cup' ? <IcTrophy size={24} /> : <span style={{ fontSize: 18 }}>{c.short[0]}</span>}
              </div>
              <h3>{c.name}</h3>
              <div className="ct-region"><IcGlobe size={12} style={{ verticalAlign: -2, marginRight: 4 }} />{c.region}</div>
              <span className="ct-arrow"><IcArrowR size={18} /></span>
            </div>
          );
        })}
      </div>
    </div>
  );
}

/* ====================== COMPETITION DETAIL ====================== */
function CompetitionDetail({ app, params }) {
  const { nav, matches } = app;
  const c = window.PP.compById[params.id] || window.PP.compById.wc26;
  const isWC = c.id === 'wc26';
  const [tab, setTab] = useState(isWC ? 'groups' : 'standings');
  const compMatches = matches.filter(m => m.comp === c.id);

  const tabs = isWC
    ? [{ id: 'groups', label: 'Groups' }, { id: 'bracket', label: 'Knockout' }, { id: 'fixtures', label: 'Fixtures' }, { id: 'scorers', label: 'Top Scorers' }, { id: 'teams', label: 'Teams' }]
    : [{ id: 'standings', label: 'Standings' }, { id: 'fixtures', label: 'Fixtures' }, { id: 'results', label: 'Results' }, { id: 'scorers', label: 'Top Scorers' }, { id: 'teams', label: 'Teams' }];

  return (
    <div className="pp-page pp-page-wide pp-rise">
      <button className="pp-btn ghost sm" onClick={() => nav('competitions')} style={{ marginBottom: 14 }}><IcChevL size={15} /> Competitions</button>
      <div className="pp-entity-head">
        <div className="eh-glow" style={{ background: `radial-gradient(100% 140% at 0% 0%, ${c.color}, transparent 60%)` }} />
        <div className="eh-main">
          <div className="ct-logo" style={{ width: 64, height: 64, margin: 0, background: `linear-gradient(135deg, ${c.color}, ${c.color}cc)` }}>
            {c.kind === 'cup' ? <IcTrophy size={30} /> : <span style={{ fontSize: 24, fontFamily: 'var(--font-display)' }}>{c.short[0]}</span>}
          </div>
          <div>
            <h1>{c.name}</h1>
            <div className="eh-sub"><span><IcGlobe size={13} /> {c.region}</span>{c.host && <span><IcPin size={13} /> {c.host}</span>}</div>
          </div>
        </div>
      </div>

      <div className="pp-tabs" style={{ marginBottom: 18 }}>
        {tabs.map(t => <button key={t.id} className={'tab' + (tab === t.id ? ' on' : '')} onClick={() => setTab(t.id)}>{t.label}</button>)}
      </div>

      {tab === 'groups' && (
        <div className="pp-grid groups">
          {window.PP.groupLetters.map(l => <GroupCard key={l} letter={l} rows={window.PPX.groupStandings(l)} onTeam={(id) => nav('team', { id })} />)}
        </div>
      )}
      {tab === 'bracket' && (
        <div className="pp-panel" style={{ overflowX: 'auto' }}>
          <Bracket rounds={window.PPX.knockout()} onOpen={() => nav('match', { id: 'm1' })} />
          <div style={{ display: 'flex', gap: 16, marginTop: 12, fontSize: 11.5, color: 'var(--text-muted)' }}>
            <span style={{ display: 'inline-flex', alignItems: 'center', gap: 6 }}><span style={{ width: 10, height: 10, borderRadius: 3, background: 'var(--accent)' }} /> Advances</span>
            <span style={{ display: 'inline-flex', alignItems: 'center', gap: 6 }}><span style={{ width: 7, height: 7, borderRadius: '50%', background: 'var(--live)' }} /> Live tie</span>
          </div>
        </div>
      )}
      {tab === 'standings' && (
        <div className="pp-panel">
          <StandingsTable rows={window.PP.eplTable} zones={(p) => p <= 4 ? 'ucl' : p <= 6 ? 'uel' : p >= 18 ? 'rel' : null} onTeam={(id) => nav('team', { id })} />
          <div style={{ display: 'flex', gap: 18, marginTop: 14, fontSize: 11.5, color: 'var(--text-muted)', flexWrap: 'wrap' }}>
            <Legend c="var(--pitch)" t="Champions League" />
            <Legend c="var(--info)" t="Europa League" />
            <Legend c="var(--loss)" t="Relegation" />
          </div>
        </div>
      )}
      {(tab === 'fixtures' || tab === 'results') && (
        <div className="pp-grid cols-2">
          {(tab === 'results' ? compMatches.filter(m => m.status === 'FT') : compMatches.filter(m => m.status !== 'FT')).map(m =>
            <MatchCard key={m.id} m={m} onOpen={() => nav('match', { id: m.id })} />)}
          {compMatches.length === 0 && <StateBlock icon={<IcInbox size={26} />} title="No matches" text="Nothing scheduled in this view." />}
        </div>
      )}
      {tab === 'scorers' && <TopScorersList compact onPlayer={() => nav('player', { id: 'mbappe' })} onTeam={(id) => nav('team', { id })} />}
      {tab === 'teams' && (
        <div className="pp-grid cols-4">
          {(isWC ? window.PP.nations : window.PP.clubs).map(t => (
            <div key={t.id} className="pp-playerrow" onClick={() => nav('team', { id: t.id })} style={{ padding: 14 }}>
              <Crest id={t.id} size={32} /><span className="pr-name">{t.name}</span>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}
function Legend({ c, t }) { return <span style={{ display: 'inline-flex', alignItems: 'center', gap: 6 }}><span style={{ width: 12, height: 12, borderRadius: 3, background: c }} /> {t}</span>; }

/* ====================== TEAM DETAIL ====================== */
function TeamDetail({ app, params }) {
  const { nav, matches, isFav, toggleFav } = app;
  const t = T(params.id) || T('fra');
  const [tab, setTab] = useState('overview');
  const teamMatches = matches.filter(m => m.home === t.id || m.away === t.id);
  const next = matches.filter(m => m.status === 'SCHEDULED' && (m.home === t.id || m.away === t.id));
  const last = matches.filter(m => m.status === 'FT' && (m.home === t.id || m.away === t.id));
  const isNation = t.type === 'nation';
  const fav = isFav('team', t.id);

  return (
    <div className="pp-page pp-rise">
      <button className="pp-btn ghost sm" onClick={() => nav('live')} style={{ marginBottom: 14 }}><IcChevL size={15} /> Back</button>
      <div className="pp-entity-head">
        <div className="eh-glow" style={{ background: `radial-gradient(100% 140% at 0% 0%, ${t.color}, transparent 60%)` }} />
        <div className="eh-main">
          <Crest id={t.id} size={72} />
          <div>
            <h1>{t.name}</h1>
            <div className="eh-sub"><span><IcGlobe size={13} /> {isNation ? 'National Team' : 'Club'}</span><span>{t.short}</span></div>
          </div>
        </div>
        <div className="eh-stats">
          <button className={'pp-btn ' + (fav ? 'ghost' : 'primary')} onClick={() => toggleFav('team', t.id)}>
            <IcStar filled={fav} size={16} /> {fav ? 'Following' : 'Follow'}
          </button>
        </div>
      </div>

      <div className="pp-tabs" style={{ marginBottom: 18 }}>
        {[{ id: 'overview', label: 'Overview' }, { id: 'squad', label: 'Squad' }, { id: 'fixtures', label: 'Fixtures' }, { id: 'results', label: 'Results' }, { id: 'table', label: 'Standings' }].map(x =>
          <button key={x.id} className={'tab' + (tab === x.id ? ' on' : '')} onClick={() => setTab(x.id)}>{x.label}</button>)}
      </div>

      {tab === 'overview' && (
        <div className="pp-grid cols-2">
          <div>
            <div className="pp-section-head"><span className="sh-title"><IcClock size={16} /> Next match</span><span className="sh-line" /></div>
            {next[0] ? <MatchCard m={next[0]} expanded onOpen={() => nav('match', { id: next[0].id })} /> : <StateBlock icon={<IcCalendar size={24} />} title="No upcoming match" />}
          </div>
          <div>
            <div className="pp-section-head"><span className="sh-title"><IcList size={16} /> Recent form</span><span className="sh-line" /></div>
            <div className="pp-panel" style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
              <FormGuide form="WWDWL" size={26} /><span style={{ fontSize: 12.5, color: 'var(--text-muted)' }}>Last 5</span>
            </div>
            {last[0] && <div style={{ marginTop: 12 }}><MatchCard m={last[0]} onOpen={() => nav('match', { id: last[0].id })} /></div>}
          </div>
        </div>
      )}
      {tab === 'squad' && (
        <div>
          {t.id === 'fra' ? Object.entries(window.PP.franceSquad).map(([pos, players]) => (
            <div className="pp-posgroup" key={pos}>
              <div className="pg-label">{pos}</div>
              <div className="pp-grid cols-3">
                {players.map(([name, num]) => (
                  <div className="pp-playerrow" key={num} onClick={() => nav('player', { id: 'mbappe' })}>
                    <span className="pr-num tnum">{num}</span><span className="pr-name">{name}</span>
                  </div>
                ))}
              </div>
            </div>
          )) : <StateBlock icon={<IcUsers size={26} />} title="Squad unavailable" text="Full squad data isn't provided for this team in the demo dataset." />}
        </div>
      )}
      {(tab === 'fixtures' || tab === 'results') && (
        <div className="pp-grid cols-2">
          {(tab === 'results' ? last : teamMatches.filter(m => m.status !== 'FT')).map(m => <MatchCard key={m.id} m={m} onOpen={() => nav('match', { id: m.id })} />)}
          {(tab === 'results' ? last : teamMatches.filter(m => m.status !== 'FT')).length === 0 && <StateBlock icon={<IcInbox size={24} />} title="Nothing here" />}
        </div>
      )}
      {tab === 'table' && (
        <div className="pp-panel">
          {isNation ? <StandingsTable rows={window.PPX.groupStandings('F')} highlight={t.id} zones={(p) => p <= 2 ? 'ucl' : null} onTeam={(id) => nav('team', { id })} />
            : <StandingsTable rows={window.PP.eplTable} highlight={t.id} zones={(p) => p <= 4 ? 'ucl' : p >= 18 ? 'rel' : null} onTeam={(id) => nav('team', { id })} />}
        </div>
      )}
    </div>
  );
}

/* ====================== PLAYER DETAIL ====================== */
function PlayerDetail({ app }) {
  const { nav } = app;
  const p = window.PP.playerDetail;
  const s = p.season;
  const statCards = [
    { k: 'Appearances', v: s.apps }, { k: 'Goals', v: s.goals }, { k: 'Assists', v: s.assists },
    { k: 'Minutes', v: s.mins }, { k: 'Shots', v: s.shots }, { k: 'On target', v: s.shotsOnTarget },
    { k: 'xG', v: s.xg }, { k: 'Pass acc.', v: s.passAcc + '%' }, { k: 'Avg rating', v: s.rating },
  ];
  return (
    <div className="pp-page pp-rise">
      <button className="pp-btn ghost sm" onClick={() => nav('team', { id: 'fra' })} style={{ marginBottom: 14 }}><IcChevL size={15} /> Back</button>
      <div className="pp-entity-head">
        <div className="eh-glow" style={{ background: 'radial-gradient(100% 140% at 0% 0%, var(--accent), transparent 60%)' }} />
        <div className="eh-main">
          <div style={{ width: 72, height: 72, borderRadius: 18, background: 'var(--surface-3)', display: 'grid', placeItems: 'center', position: 'relative', overflow: 'hidden' }}>
            <IcUsers size={34} style={{ color: 'var(--text-faint)' }} />
            <span style={{ position: 'absolute', bottom: 4, right: 4 }}><Crest id="fra" size={22} /></span>
          </div>
          <div>
            <h1>{p.name}</h1>
            <div className="eh-sub">
              <span style={{ display: 'inline-flex', alignItems: 'center', gap: 6 }}><Crest id="fra" size={14} /> {p.nationality}</span>
              <span><IcUsers size={13} /> {p.pos} · #{p.shirt}</span>
              <span>{p.age} yrs · {p.height} · {p.foot}-footed</span>
              <span style={{ display: 'inline-flex', alignItems: 'center', gap: 6 }}>Club: <Crest id="rmd" size={14} /> {T('rmd').name}</span>
            </div>
          </div>
        </div>
        <div className="eh-stats">
          <div className="eh-stat"><b className="display tnum">{s.goals}</b><small>Goals</small></div>
          <div className="eh-stat"><b className="display tnum">{s.assists}</b><small>Assists</small></div>
          <div className="eh-stat"><b className="display tnum">{s.rating}</b><small>Rating</small></div>
        </div>
      </div>

      <div className="pp-section-head"><span className="sh-title"><IcChart size={16} /> World Cup 2026 — season stats</span><span className="sh-line" /></div>
      <div className="pp-grid cols-3" style={{ marginBottom: 22 }}>
        {statCards.map(c => (
          <div key={c.k} className="pp-panel" style={{ padding: '16px 18px', display: 'flex', flexDirection: 'column', gap: 4 }}>
            <b className="display tnum" style={{ fontSize: 30, fontWeight: 800 }}>{c.v}</b>
            <small style={{ fontSize: 12, color: 'var(--text-muted)', textTransform: 'uppercase', letterSpacing: '.04em' }}>{c.k}</small>
          </div>
        ))}
      </div>

      <div className="pp-section-head"><span className="sh-title">Not available on free tier</span><span className="sh-line" /></div>
      <div style={{ display: 'flex', gap: 10, flexWrap: 'wrap' }}>
        {p.empty.map(e => (
          <div key={e} className="pp-chip" style={{ cursor: 'default', opacity: .7 }}><IcAlert size={13} style={{ color: 'var(--draw)' }} /> {e} <span className="mono" style={{ fontSize: 11, color: 'var(--text-faint)' }}>—</span></div>
        ))}
      </div>
    </div>
  );
}

/* ====================== TOP SCORERS ====================== */
function TopScorersList({ compact, onPlayer, onTeam }) {
  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: 8 }}>
      {window.PP.topScorers.map((s, i) => (
        <div key={i} className={'pp-scorer' + (i === 0 ? ' top' : '')} onClick={() => onPlayer && onPlayer()} style={{ cursor: 'pointer' }}>
          <span className="sc-rank">{i + 1}</span>
          <div className="sc-player">
            <Crest id={s.team} size={26} />
            <div><div className="nm">{s.player}</div><div className="tm">{T(s.team).name}</div></div>
          </div>
          <div className="sc-stats">
            {!compact && <div><div className="sc-sub mono">{s.assists}</div><div className="sc-sub">AST</div></div>}
            {!compact && <div><div className="sc-sub mono">{s.pens}</div><div className="sc-sub">PEN</div></div>}
            <div style={{ textAlign: 'center' }}><span className="sc-goals">{s.goals}</span><div className="sc-sub">GOALS</div></div>
          </div>
        </div>
      ))}
    </div>
  );
}
function TopScorersScreen({ app }) {
  const { nav } = app;
  return (
    <div className="pp-page pp-rise" style={{ maxWidth: 760, width: '100%', marginInline: 'auto' }}>
      <div className="pp-pagehead"><div><h1>Top Scorers</h1><div className="ph-sub">FIFA World Cup 2026 · Golden Boot race</div></div></div>
      <TopScorersList onPlayer={() => nav('player', { id: 'mbappe' })} onTeam={(id) => nav('team', { id })} />
    </div>
  );
}

Object.assign(window, { CompetitionsScreen, CompetitionDetail, TeamDetail, PlayerDetail, TopScorersList, TopScorersScreen, Legend });
