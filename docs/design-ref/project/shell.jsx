/* ============================================================
   SocPlay — App shell, router, live engine, mount
   ============================================================ */

// Plausible scorer names for the goal toast
const SCORERS = {
  fra: ['K. Mbappé', 'O. Dembélé', 'M. Thuram'], mar: ['Y. En-Nesyri', 'H. Ziyech'],
  mex: ['S. Giménez', 'H. Lozano'], pol: ['R. Lewandowski'], arg: ['J. Álvarez', 'L. Martínez'],
  aus: ['M. Duke'], mci: ['E. Haaland', 'P. Foden'], rmd: ['Vinícius Jr', 'J. Bellingham'],
  ars: ['B. Saka', 'K. Havertz'], liv: ['M. Salah', 'D. Núñez'],
};
function scorerFor(teamId) { const p = SCORERS[teamId]; return p ? p[Math.floor(Math.random() * p.length)] : window.PP.teams[teamId].name; }

/* ---------- Live engine hook ---------- */
function useLiveEngine(matchesRef, setMatches, settings, fireGoal) {
  const tick = useCallback(() => {
    setMatches(prev => prev.map(m => {
      if (!['LIVE', 'ET'].includes(m.status)) return m;
      let minute = m.minute + Math.floor(Math.random() * 3) + 1;
      let { hs, as, status, period } = m;
      if (minute >= 90 && m.status === 'LIVE' && Math.random() < 0.25) { status = 'FT'; minute = 90; }
      else if (minute >= 45 && period === '1H') { period = '2H'; }
      // goal chance
      if (status !== 'FT' && Math.random() < 0.14) {
        const homeBias = 0.5 + (T(m.home).color ? 0 : 0);
        const side = Math.random() < homeBias ? 'home' : 'away';
        if (side === 'home') hs++; else as++;
        const team = side === 'home' ? m.home : m.away;
        fireGoal({ team, scorer: scorerFor(team), minute, scoreline: `${hs}–${as}`, matchId: m.id });
      }
      return { ...m, minute, hs, as, status, period };
    }));
  }, [setMatches, fireGoal]);
  return tick;
}

/* ---------- Logo ---------- */
function Logo({ compact }) {
  return (
    <div className="pp-logo">
      <span className="mark"><IcBall size={20} /></span>
      {!compact && <span className="word"><b>Soc<span className="accent">Play</span></b><small>Live Football</small></span>}
    </div>
  );
}

/* ---------- Top nav (broadcast-style horizontal nav, à la Sofascore) ---------- */
const NAV = [
  { id: 'live', label: 'Live', icon: <IcLive size={18} /> },
  { id: 'matches', label: 'Matches', icon: <IcCalendar size={18} /> },
  { id: 'competitions', label: 'Competitions', icon: <IcTrophy size={18} /> },
  { id: 'favorites', label: 'Following', icon: <IcStar size={18} /> },
  { id: 'scorers', label: 'Top Scorers', icon: <IcChart size={18} /> },
];
function TopNav({ route, nav, liveCount }) {
  const active = (id) => route.name === id
    || (id === 'live' && route.name === 'match')
    || (id === 'competitions' && route.name === 'competition')
    || (id === 'favorites' && route.name === 'team');
  return (
    <nav className="pp-topnav" aria-label="Primary">
      <div className="pp-topnav-inner">
        {NAV.map(n => (
          <button key={n.id} className={'pp-navtab' + (active(n.id) ? ' on' : '')} onClick={() => nav(n.id)}>
            <span className="nt-ic">{n.icon}</span>
            <span className="nt-label">{n.label}</span>
            {n.id === 'live' && liveCount > 0 && <span className="nt-count">{liveCount}</span>}
          </button>
        ))}
      </div>
    </nav>
  );
}

/* ---------- Live ticker ---------- */
function LiveTicker({ matches, nav }) {
  const live = matches.filter(m => ['LIVE', 'HT', 'ET', 'PEN'].includes(m.status));
  if (live.length === 0) return null;
  const Item = (m, k) => {
    const c = window.PP.compById[m.comp];
    return (
      <span className="tk-item" key={k} onClick={() => nav('match', { id: m.id })}>
        <span className="tk-comp">{c.short}</span>
        <span className="tk-teams"><Crest id={m.home} size={16} />{T(m.home).short}<span className="tk-sc">{m.hs}–{m.as}</span>{T(m.away).short}<Crest id={m.away} size={16} /></span>
        <span className="tk-min">{m.status === 'HT' ? 'HT' : m.minute + "'"}</span>
      </span>
    );
  };
  const items = [...live, ...live];
  return (
    <div className="pp-ticker">
      <span className="tk-label"><span className="dot" /> LIVE</span>
      <div className="tk-scroll"><div className="tk-track">{items.map((m, i) => Item(m, i))}</div></div>
    </div>
  );
}

/* ---------- Mobile tab bar ---------- */
function TabBar({ route, nav, liveCount }) {
  const tabs = [
    { id: 'live', label: 'Live', icon: <IcLive size={22} /> },
    { id: 'matches', label: 'Matches', icon: <IcCalendar size={22} /> },
    { id: 'competitions', label: 'Comps', icon: <IcTrophy size={22} /> },
    { id: 'favorites', label: 'Following', icon: <IcStar size={22} /> },
    { id: 'settings', label: 'More', icon: <IcMore size={22} /> },
  ];
  return (
    <nav className="pp-tabbar">
      {tabs.map(t => (
        <button key={t.id} className={'tab' + (route.name === t.id ? ' on' : '')} onClick={() => nav(t.id)}>
          <span className="tb-ic">{t.icon}{t.id === 'live' && liveCount > 0 && <span className="tb-livedot" />}</span>
          <span>{t.label}</span>
        </button>
      ))}
    </nav>
  );
}

/* ---------- Persistence ---------- */
function usePersist(key, initial) {
  const [v, setV] = useState(() => { try { const s = localStorage.getItem('pp_' + key); return s ? JSON.parse(s) : initial; } catch { return initial; } });
  useEffect(() => { try { localStorage.setItem('pp_' + key, JSON.stringify(v)); } catch {} }, [key, v]);
  return [v, setV];
}

/* ====================== APP ====================== */
function App() {
  const [theme, setTheme] = usePersist('theme', 'dark');
  const [route, setRoute] = usePersist('route', { name: 'live', params: {} });
  const [favorites, setFavorites] = usePersist('favs', [{ type: 'team', id: 'fra' }, { type: 'team', id: 'arg' }, { type: 'comp', id: 'wc26' }]);
  const [settings, setSettings] = usePersist('settings', { tz: 'Local', refresh: 15, paused: false, notify: true, reduceMotion: false });
  const [matches, setMatches] = useState(() => window.PP.todayMatches.map(m => ({ ...m })));
  const [goal, setGoal] = useState(null);
  const [justScored, setJustScored] = useState(null);
  const [searchOpen, setSearchOpen] = useState(false);
  const matchesRef = useRef(matches); matchesRef.current = matches;

  useEffect(() => { document.documentElement.setAttribute('data-theme', theme); }, [theme]);
  useEffect(() => { document.documentElement.style.scrollBehavior = settings.reduceMotion ? 'auto' : ''; }, [settings.reduceMotion]);

  const nav = useCallback((name, params = {}) => { setRoute({ name, params }); window.scrollTo({ top: 0, behavior: 'instant' }); }, [setRoute]);
  const openSearch = useCallback(() => setSearchOpen(true), []);
  // Global shortcut: "/" or ⌘K opens search
  useEffect(() => {
    const h = (e) => {
      const tag = (e.target.tagName || '').toLowerCase();
      const typing = tag === 'input' || tag === 'textarea' || e.target.isContentEditable;
      if ((e.key === '/' && !typing) || ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k')) { e.preventDefault(); setSearchOpen(true); }
    };
    window.addEventListener('keydown', h);
    return () => window.removeEventListener('keydown', h);
  }, []);
  const isFav = useCallback((type, id) => favorites.some(f => f.type === type && f.id === id), [favorites]);
  const toggleFav = useCallback((type, id) => setFavorites(prev => prev.some(f => f.type === type && f.id === id) ? prev.filter(f => !(f.type === type && f.id === id)) : [...prev, { type, id }]), [setFavorites]);

  const fireGoal = useCallback((g) => {
    setGoal(g); setJustScored(g.matchId);
    setTimeout(() => setJustScored(null), 1400);
  }, []);
  const tick = useLiveEngine(matchesRef, setMatches, settings, fireGoal);
  const onRefreshTick = useCallback(() => { if (!settings.paused) tick(); }, [tick, settings.paused]);

  const liveCount = matches.filter(m => ['LIVE', 'HT', 'ET', 'PEN'].includes(m.status)).length;

  const app = { matches, nav, route, theme, setTheme, favorites, isFav, toggleFav, settings, setSettings,
    refreshSeconds: settings.refresh, paused: settings.paused, onRefreshTick, justScored };

  const Screen = () => {
    switch (route.name) {
      case 'live': return <LiveHub app={app} />;
      case 'matches': return <MatchesScreen app={app} />;
      case 'match': return <MatchDetail app={app} params={route.params} />;
      case 'competitions': return <CompetitionsScreen app={app} />;
      case 'competition': return <CompetitionDetail app={app} params={route.params} />;
      case 'team': return <TeamDetail app={app} params={route.params} />;
      case 'player': return <PlayerDetail app={app} params={route.params} />;
      case 'scorers': return <TopScorersScreen app={app} />;
      case 'search': return <SearchScreen app={app} />;
      case 'favorites': return <FavoritesScreen app={app} />;
      case 'settings': return <SettingsScreen app={app} />;
      default: return <LiveHub app={app} />;
    }
  };

  return (
    <div className="pp-app">
      <div className="pp-shell">
        {/* Desktop utility bar: logo (left) · search + actions (right) */}
        <header className="pp-topbar">
          <div className="pp-topbar-inner">
            <button className="tb-logo" onClick={() => nav('live')} aria-label="SocPlay home"><Logo /></button>
            <span className="tb-spacer" />
            <div className="pp-search" onClick={openSearch}>
              <IcSearch size={16} />
              <input placeholder="Search teams, players…" readOnly />
              <span className="kbd">/</span>
            </div>
            <RefreshIndicator seconds={settings.refresh} paused={settings.paused} onTick={onRefreshTick} compact />
            <button className="pp-iconbtn sm" onClick={() => nav('favorites')} aria-label="Notifications"><IcBell size={17} /><span className="badge-dot" /></button>
            <button className="pp-iconbtn sm" onClick={() => setTheme(theme === 'dark' ? 'light' : 'dark')} aria-label={theme === 'dark' ? 'Switch to light theme' : 'Switch to dark theme'} title="Toggle theme">{theme === 'dark' ? <IcSun size={17} /> : <IcMoon size={17} />}</button>
            <button className={'pp-iconbtn sm' + (route.name === 'settings' ? ' on' : '')} onClick={() => nav('settings')} aria-label="Settings"><IcSettings size={17} /></button>
          </div>
        </header>

        {/* Desktop primary nav (tabs only) */}
        <TopNav route={route} nav={nav} liveCount={liveCount} />

        {/* Mobile top bar */}
        <div className="pp-mobile-top">
          <button className="tb-logo" onClick={() => nav('live')}><Logo /></button>
          <span className="tb-spacer" />
          <button className="pp-iconbtn" onClick={openSearch} aria-label="Search"><IcSearch size={18} /></button>
          <button className="pp-iconbtn" onClick={() => setTheme(theme === 'dark' ? 'light' : 'dark')} aria-label="Toggle theme">{theme === 'dark' ? <IcSun size={18} /> : <IcMoon size={18} />}</button>
        </div>

        <LiveTicker matches={matches} nav={nav} />

        <main className="pp-main">
          {/* ARIA live region for score updates */}
          <div aria-live="polite" className="sr-only" style={{ position: 'absolute', width: 1, height: 1, overflow: 'hidden', clip: 'rect(0 0 0 0)' }}>
            {goal ? `Goal for ${T(goal.team).name}, ${goal.scorer}, ${goal.minute} minutes. Score now ${goal.scoreline}.` : ''}
          </div>
          <Screen />
        </main>
      </div>
      <TabBar route={route} nav={nav} liveCount={liveCount} />
      <SearchModal open={searchOpen} onClose={() => setSearchOpen(false)} nav={nav} />
      {!settings.reduceMotion && <GoalToast goal={goal} onDone={() => setGoal(null)} />}
    </div>
  );
}
function titleFor(route) {
  const m = { live: 'Live Hub', matches: 'Matches', match: 'Match Centre', competitions: 'Competitions', competition: 'Competition', team: 'Team', player: 'Player', scorers: 'Top Scorers', search: 'Search', favorites: 'Following', settings: 'Settings' };
  return m[route.name] || 'SocPlay';
}

ReactDOM.createRoot(document.getElementById('root')).render(<App />);
