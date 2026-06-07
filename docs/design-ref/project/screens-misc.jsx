/* ============================================================
   SocPlay — Search, Favorites, Settings, States gallery
   ============================================================ */

/* ====================== SEARCH ====================== */
function SearchScreen({ app }) {
  const { nav } = app;
  const [q, setQ] = useState('');
  const inputRef = useRef(null);
  useEffect(() => { inputRef.current && inputRef.current.focus(); }, []);

  const all = [
    ...window.PP.competitions.map(c => ({ kind: 'Competition', id: c.id, name: c.name, route: 'competition', icon: <IcTrophy size={16} /> })),
    ...window.PP.nations.map(t => ({ kind: 'Team', id: t.id, name: t.name, route: 'team', crest: t.id })),
    ...window.PP.clubs.map(t => ({ kind: 'Team', id: t.id, name: t.name, route: 'team', crest: t.id })),
    { kind: 'Player', id: 'mbappe', name: 'Kylian Mbappé', route: 'player', icon: <IcUsers size={16} /> },
  ];
  const results = q.trim() ? all.filter(r => r.name.toLowerCase().includes(q.toLowerCase())).slice(0, 12) : [];
  const grouped = results.reduce((a, r) => { (a[r.kind] = a[r.kind] || []).push(r); return a; }, {});

  return (
    <div className="pp-page pp-searchpage pp-rise" style={{ maxWidth: 720, width: '100%', marginInline: 'auto' }}>
      <div className="pp-pagehead"><div><h1>Search</h1></div></div>
      <div className="sp-input">
        <IcSearch size={22} style={{ color: 'var(--text-muted)' }} />
        <input ref={inputRef} value={q} onChange={e => setQ(e.target.value)} placeholder="Teams, competitions, players…" />
        {q && <button className="pp-iconbtn" style={{ width: 34, height: 34 }} onClick={() => setQ('')}><IcClose size={16} /></button>}
      </div>

      {!q && (
        <div style={{ marginTop: 22 }}>
          <div className="pp-section-head"><span className="sh-title"><IcClock size={15} /> Recent searches</span><span className="sh-line" /></div>
          <div style={{ display: 'flex', flexWrap: 'wrap', gap: 8, marginBottom: 24 }}>
            {window.PP.recentSearches.map(r => <span key={r} className="pp-chip" onClick={() => setQ(r)}>{r}</span>)}
          </div>
          <div className="pp-section-head"><span className="sh-title"><IcTrophy size={15} /> Browse competitions</span><span className="sh-line" /></div>
          <div className="pp-grid cols-3">
            {window.PP.competitions.slice(0, 6).map(c => (
              <div key={c.id} className="pp-playerrow" onClick={() => nav('competition', { id: c.id })}>
                <span style={{ width: 26, height: 26, borderRadius: 7, background: c.color, display: 'grid', placeItems: 'center', color: '#fff' }}><IcTrophy size={14} /></span>
                <span className="pr-name" style={{ fontSize: 13 }}>{c.short}</span>
              </div>
            ))}
          </div>
        </div>
      )}

      {q && results.length === 0 && <StateBlock icon={<IcSearch size={26} />} title="No results" text={`Nothing matches “${q}”. Try a team, competition, or player name.`} />}
      {q && results.length > 0 && (
        <div style={{ marginTop: 20 }}>
          {Object.entries(grouped).map(([kind, items]) => (
            <div key={kind} className="pp-section">
              <div className="pp-section-head"><span className="sh-title" style={{ fontSize: 14 }}>{kind}s</span><span className="sh-count">{items.length}</span><span className="sh-line" /></div>
              <div style={{ display: 'flex', flexDirection: 'column', gap: 6 }}>
                {items.map(r => (
                  <div key={r.id} className="pp-playerrow" onClick={() => nav(r.route, { id: r.id })}>
                    {r.crest ? <Crest id={r.crest} size={28} /> : <span style={{ width: 28, height: 28, borderRadius: 8, background: 'var(--surface-3)', display: 'grid', placeItems: 'center', color: 'var(--text-muted)' }}>{r.icon}</span>}
                    <span className="pr-name">{r.name}</span>
                    <span style={{ marginLeft: 'auto', color: 'var(--text-faint)' }}><IcArrowR size={16} /></span>
                  </div>
                ))}
              </div>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}

/* ====================== FAVORITES ====================== */
function FavoritesScreen({ app }) {
  const { nav, favorites, matches, toggleFav, isFav } = app;
  const favTeams = favorites.filter(f => f.type === 'team').map(f => f.id);
  const favComps = favorites.filter(f => f.type === 'comp').map(f => f.id);
  const favMatches = matches.filter(m => favTeams.includes(m.home) || favTeams.includes(m.away));

  if (favTeams.length === 0 && favComps.length === 0) {
    return (
      <div className="pp-page pp-rise">
        <div className="pp-pagehead"><div><h1>Following</h1></div></div>
        <StateBlock icon={<IcStar size={26} />} title="Nothing followed yet"
          text="Tap the star on any team or competition to pin its matches here for quick access."
          action={<button className="pp-btn primary" onClick={() => nav('competitions')} style={{ marginTop: 8 }}>Browse competitions</button>} />
      </div>
    );
  }
  return (
    <div className="pp-page pp-rise">
      <div className="pp-pagehead"><div><h1>Following</h1><div className="ph-sub">{favTeams.length} teams · {favComps.length} competitions</div></div></div>

      {favMatches.length > 0 && (
        <div className="pp-section">
          <div className="pp-section-head"><span className="sh-title"><IcLive size={16} /> Their matches</span><span className="sh-line" /></div>
          <div className="pp-grid cols-2">
            {favMatches.map(m => <MatchCard key={m.id} m={m} onOpen={() => nav('match', { id: m.id })} fav onFav={() => toggleFav('team', favTeams.includes(m.home) ? m.home : m.away)} justScored={app.justScored === m.id} />)}
          </div>
        </div>
      )}
      <div className="pp-section">
        <div className="pp-section-head"><span className="sh-title"><IcStar filled size={15} style={{ color: 'var(--draw)' }} /> Teams</span><span className="sh-line" /></div>
        <div className="pp-grid cols-3">
          {favTeams.map(id => (
            <div key={id} className="pp-playerrow" onClick={() => nav('team', { id })}>
              <Crest id={id} size={32} /><span className="pr-name">{T(id).name}</span>
              <span style={{ marginLeft: 'auto' }}><FavoriteStar active onToggle={() => toggleFav('team', id)} /></span>
            </div>
          ))}
        </div>
      </div>
      {favComps.length > 0 && (
        <div className="pp-section">
          <div className="pp-section-head"><span className="sh-title"><IcTrophy size={15} /> Competitions</span><span className="sh-line" /></div>
          <div className="pp-grid cols-3">
            {favComps.map(id => { const c = window.PP.compById[id]; return (
              <div key={id} className="pp-playerrow" onClick={() => nav('competition', { id })}>
                <span style={{ width: 30, height: 30, borderRadius: 8, background: c.color, display: 'grid', placeItems: 'center', color: '#fff' }}><IcTrophy size={15} /></span>
                <span className="pr-name">{c.name}</span>
                <span style={{ marginLeft: 'auto' }}><FavoriteStar active onToggle={() => toggleFav('comp', id)} /></span>
              </div>
            ); })}
          </div>
        </div>
      )}
    </div>
  );
}

/* ====================== SETTINGS ====================== */
function SettingsScreen({ app }) {
  const { theme, setTheme, settings, setSettings } = app;
  return (
    <div className="pp-page pp-rise" style={{ maxWidth: 680, width: '100%', marginInline: 'auto' }}>
      <div className="pp-pagehead"><div><h1>Settings</h1><div className="ph-sub">Personalise SocPlay</div></div></div>

      <div className="pp-panel" style={{ padding: 0, marginBottom: 18 }}>
        <div className="pp-setrow">
          <div><div className="sr-label">Theme</div><div className="sr-desc">Stadium Night or Daylight</div></div>
          <div className="pp-segmented">
            <button className={theme === 'dark' ? 'on' : ''} onClick={() => setTheme('dark')}><IcMoon size={14} style={{ verticalAlign: -2, marginRight: 4 }} />Dark</button>
            <button className={theme === 'light' ? 'on' : ''} onClick={() => setTheme('light')}><IcSun size={14} style={{ verticalAlign: -2, marginRight: 4 }} />Light</button>
          </div>
        </div>
        <div className="pp-setrow">
          <div><div className="sr-label">Time zone</div><div className="sr-desc">Match kick-off times shown in this zone</div></div>
          <div className="pp-segmented">
            {['Local', 'UTC', 'ET'].map(tz => <button key={tz} className={settings.tz === tz ? 'on' : ''} onClick={() => setSettings(s => ({ ...s, tz }))}>{tz}</button>)}
          </div>
        </div>
        <div className="pp-setrow">
          <div><div className="sr-label">Auto-refresh interval</div><div className="sr-desc">How often live scores update</div></div>
          <div className="pp-segmented">
            {[10, 15, 30].map(n => <button key={n} className={settings.refresh === n ? 'on' : ''} onClick={() => setSettings(s => ({ ...s, refresh: n }))}>{n}s</button>)}
          </div>
        </div>
        <div className="pp-setrow">
          <div><div className="sr-label">Auto-refresh</div><div className="sr-desc">Pause to save data / battery</div></div>
          <button className={'pp-switch' + (!settings.paused ? ' on' : '')} role="switch" aria-checked={!settings.paused} onClick={() => setSettings(s => ({ ...s, paused: !s.paused }))} />
        </div>
        <div className="pp-setrow">
          <div><div className="sr-label">Match notifications</div><div className="sr-desc">Goal &amp; full-time alerts for followed teams</div></div>
          <button className={'pp-switch' + (settings.notify ? ' on' : '')} role="switch" aria-checked={settings.notify} onClick={() => setSettings(s => ({ ...s, notify: !s.notify }))} />
        </div>
        <div className="pp-setrow">
          <div><div className="sr-label">Reduced motion</div><div className="sr-desc">Minimise animations (also follows your OS setting)</div></div>
          <button className={'pp-switch' + (settings.reduceMotion ? ' on' : '')} role="switch" aria-checked={settings.reduceMotion} onClick={() => setSettings(s => ({ ...s, reduceMotion: !s.reduceMotion }))} />
        </div>
      </div>

    </div>
  );
}

Object.assign(window, { SearchScreen, FavoritesScreen, SettingsScreen });
