/* ============================================================
   SocPlay — Detail components
   StandingsTable, GroupCard, Bracket, TimelineEvent, Pitch,
   StatComparisonBar, H2H, GoalToast, DateNavigator
   ============================================================ */

/* ---------- StandingsTable ---------- */
function StandingsTable({ rows, highlight, zones, compact = false, onTeam }) {
  return (
    <div className="pp-standings-wrap">
      <table className={'pp-standings' + (compact ? ' cmp' : '')}>
        <thead>
          <tr>
            <th className="c-pos">#</th>
            <th className="c-team">Team</th>
            <th>P</th>
            {!compact && <><th>W</th><th>D</th><th>L</th><th>GF</th><th>GA</th></>}
            <th>GD</th>
            <th className="c-pts">Pts</th>
            {!compact && <th className="c-form">Form</th>}
          </tr>
        </thead>
        <tbody>
          {rows.map((r, i) => {
            const pos = i + 1;
            const zone = zones && zones(pos);
            return (
              <tr key={r.team} className={(highlight === r.team ? 'hl ' : '') + (zone ? 'z-' + zone : '')}
                onClick={() => onTeam && onTeam(r.team)} style={{ cursor: onTeam ? 'pointer' : 'default' }}>
                <td className="c-pos"><span className="pos-mark">{pos}</span></td>
                <td className="c-team"><TeamChip id={r.team} size={22} code={compact} /></td>
                <td className="tnum">{r.p}</td>
                {!compact && <><td className="tnum">{r.w}</td><td className="tnum">{r.d}</td><td className="tnum">{r.l}</td><td className="tnum">{r.gf}</td><td className="tnum">{r.ga}</td></>}
                <td className="tnum">{r.gf - r.ga > 0 ? '+' : ''}{r.gf - r.ga}</td>
                <td className="c-pts tnum">{r.pts}</td>
                {!compact && <td className="c-form"><FormGuide form={r.form} size={18} /></td>}
              </tr>
            );
          })}
        </tbody>
      </table>
    </div>
  );
}

/* ---------- GroupCard (World Cup compact group) ---------- */
function GroupCard({ letter, rows, onTeam }) {
  return (
    <div className="pp-groupcard">
      <div className="gc-head"><span className="gc-letter display">{letter}</span><span className="gc-title">Group {letter}</span></div>
      <table className="pp-groupmini">
        <thead><tr><th></th><th className="l">Team</th><th>P</th><th>GD</th><th>Pts</th></tr></thead>
        <tbody>
          {rows.map((r, i) => (
            <tr key={r.team} className={i < 2 ? 'qual' : ''} onClick={() => onTeam && onTeam(r.team)}>
              <td className="pos"><span className={'qdot' + (i < 2 ? ' on' : '')}>{i + 1}</span></td>
              <td className="l"><TeamChip id={r.team} size={18} code /></td>
              <td className="tnum">{r.p}</td>
              <td className="tnum">{r.gd > 0 ? '+' : ''}{r.gd}</td>
              <td className="tnum pts">{r.pts}</td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}

/* ---------- BracketNode + Bracket ---------- */
function BracketNode({ tie, onOpen }) {
  const win = tie.hs != null && (tie.hs > tie.as ? 'home' : tie.as > tie.hs ? 'away' : null);
  return (
    <div className={'pp-bnode' + (tie.live ? ' live' : '')} onClick={() => onOpen && tie.home && onOpen(tie)} tabIndex={tie.home ? 0 : -1}>
      <div className={'bn-row' + (win === 'home' ? ' w' : win === 'away' ? ' l' : '')}>
        {tie.home ? <><Crest id={tie.home} size={18} /><span className="bn-name">{T(tie.home).short}</span></> : <span className="bn-name tbd">TBD</span>}
        <span className="bn-score tnum">{tie.hs ?? '–'}</span>
      </div>
      <div className={'bn-row' + (win === 'away' ? ' w' : win === 'home' ? ' l' : '')}>
        {tie.away ? <><Crest id={tie.away} size={18} /><span className="bn-name">{T(tie.away).short}</span></> : <span className="bn-name tbd">TBD</span>}
        <span className="bn-score tnum">{tie.as ?? '–'}</span>
      </div>
      {tie.live && <span className="bn-live"><span className="d" />LIVE</span>}
    </div>
  );
}
function Bracket({ rounds, onOpen }) {
  return (
    <div className="pp-bracket">
      {rounds.map((rd, ri) => (
        <div className="b-col" key={ri} style={{ '--gap': rd.gap + 'px' }}>
          <div className="b-col-title">{rd.title}</div>
          <div className="b-col-body">
            {rd.ties.map((t, i) => <BracketNode key={i} tie={t} onOpen={onOpen} />)}
          </div>
        </div>
      ))}
    </div>
  );
}

/* ---------- TimelineEvent (goal / card / sub / whistle) ---------- */
function TimelineEvent({ ev }) {
  if (ev.type === 'whistle') {
    return (
      <div className="pp-tl-mid">
        <span className="tl-line" /><span className="tl-chip"><IcWhistle size={14} />{ev.player} · {ev.detail}</span><span className="tl-line" />
      </div>
    );
  }
  const side = ev.team === 'home' ? 'left' : 'right';
  const icon = ev.type === 'goal' ? <IcBall size={17} /> :
    ev.type === 'yellow' ? <IcCard variant="yellow" size={15} /> :
    ev.type === 'red' ? <IcCard variant="red" size={15} /> :
    ev.type === 'sub' ? <IcSub size={17} /> : null;
  return (
    <div className={'pp-tl-row ' + side + (ev.type === 'goal' ? ' goal' : '')}>
      <div className="tl-side">
        <div className="tl-card">
          <span className={'tl-ic ' + ev.type}>{icon}</span>
          <span className="tl-txt">
            <span className="tl-player">{ev.player}{ev.type === 'goal' && <IcBall size={13} style={{ marginLeft: 5, verticalAlign: '-2px' }} />}</span>
            <span className="tl-detail">{ev.type === 'sub' ? <>↓ {ev.detail}</> : ev.assist ? `${ev.detail} · assist ${ev.assist}` : ev.detail}</span>
          </span>
        </div>
      </div>
      <div className="tl-min"><span className="mono">{ev.min}'</span></div>
      <div className="tl-side empty" />
    </div>
  );
}

/* ---------- Pitch / Formation ---------- */
function Pitch({ lineup, side = 'home', color = 'var(--accent)' }) {
  return (
    <div className="pp-pitch-half" style={{ '--jersey': color }}>
      <svg className="pitch-lines" viewBox="0 0 100 100" preserveAspectRatio="none" aria-hidden="true">
        <rect x="1" y="1" width="98" height="98" rx="1.5" fill="none" stroke="rgba(255,255,255,.16)" strokeWidth="0.5" />
        <line x1="1" y1="50" x2="99" y2="50" stroke="rgba(255,255,255,.12)" strokeWidth="0.4" strokeDasharray="2 2" />
        <rect x="28" y="0" width="44" height="16" fill="none" stroke="rgba(255,255,255,.12)" strokeWidth="0.4" />
        <rect x="40" y="0" width="20" height="7" fill="none" stroke="rgba(255,255,255,.12)" strokeWidth="0.4" />
        <circle cx="50" cy="50" r="9" fill="none" stroke="rgba(255,255,255,.1)" strokeWidth="0.4" />
      </svg>
      {lineup.xi.map(p => (
        <div className="player-dot" key={p.n} style={{ left: p.x + '%', bottom: (p.y) + '%' }} title={`${p.n} ${p.name}`}>
          <span className="pd-shirt"><span className="num tnum">{p.n}</span></span>
          <span className="pd-name">{p.name.split(' ').slice(-1)[0]}</span>
        </div>
      ))}
    </div>
  );
}
function FormationView({ home, away }) {
  return (
    <div className="pp-formation">
      <div className="form-head">
        <span><TeamChip id={home.id} size={22} /> <b className="display">{home.lineup.formation}</b></span>
        <span className="coach">Coaches</span>
        <span className="rev"><b className="display">{away.lineup.formation}</b> <TeamChip id={away.id} size={22} reverse /></span>
      </div>
      <div className="pitch-full" style={{ background: 'var(--grad-pitch)' }}>
        <div className="pitch-grass" aria-hidden="true" />
        <div className="half top"><Pitch lineup={away.lineup} side="away" color={T(away.id).color} /></div>
        <div className="half bottom"><Pitch lineup={home.lineup} side="home" color={T(home.id).color} /></div>
      </div>
      <div className="bench-row">
        <div className="bench"><span className="bl">Bench</span>{home.lineup.bench.map((b, i) => <span key={i} className="bp">{b}</span>)}</div>
      </div>
    </div>
  );
}

/* ---------- StatComparisonBar ---------- */
function StatComparisonBar({ label, home, away, suffix = '', accentLeft = 'var(--accent)', accentRight = 'var(--cyan)' }) {
  const total = home + away || 1;
  const lp = Math.round((home / total) * 100);
  const hi = home >= away;
  return (
    <div className="pp-statbar">
      <div className="sb-vals">
        <span className={'v' + (hi ? ' hi' : '')}>{home}{suffix}</span>
        <span className="sb-label">{label}</span>
        <span className={'v' + (!hi ? ' hi' : '')}>{away}{suffix}</span>
      </div>
      <div className="sb-track">
        <span className="sb-fill l" style={{ width: lp + '%', background: accentLeft }} />
        <span className="sb-fill r" style={{ width: (100 - lp) + '%', background: accentRight }} />
      </div>
    </div>
  );
}
function PossessionBar({ home, away }) {
  return (
    <div className="pp-possession">
      <div className="pos-ring l" style={{ '--p': home }}><span className="display tnum">{home}<small>%</small></span></div>
      <div className="pos-mid"><IcChart size={16} /><span>Possession</span></div>
      <div className="pos-ring r" style={{ '--p': away, '--c': 'var(--cyan)' }}><span className="display tnum">{away}<small>%</small></span></div>
    </div>
  );
}

/* ---------- H2H widget ---------- */
function H2HWidget({ records, home, away }) {
  const tally = records.reduce((a, r) => {
    const hWin = r.hs > r.as, aWin = r.as > r.hs;
    const homeIsHome = r.home === home;
    if (r.hs === r.as) a.d++;
    else if ((hWin && homeIsHome) || (aWin && !homeIsHome)) a.h++;
    else a.aw++;
    return a;
  }, { h: 0, d: 0, aw: 0 });
  return (
    <div className="pp-h2h">
      <div className="h2h-tally">
        <div className="ht"><Crest id={home} size={26} /><span className="display tnum">{tally.h}</span><small>Wins</small></div>
        <div className="ht mid"><span className="display tnum">{tally.d}</span><small>Draws</small></div>
        <div className="ht"><Crest id={away} size={26} /><span className="display tnum">{tally.aw}</span><small>Wins</small></div>
      </div>
      <div className="h2h-list">
        {records.map((r, i) => (
          <div className="h2h-row" key={i}>
            <span className="hr-date mono">{r.date}</span>
            <span className="hr-comp">{r.comp}</span>
            <span className="hr-match"><TeamChip id={r.home} size={18} code /> <b className="display tnum">{r.hs}–{r.as}</b> <TeamChip id={r.away} size={18} code reverse /></span>
          </div>
        ))}
      </div>
    </div>
  );
}

/* ---------- GoalToast ---------- */
function GoalToast({ goal, onDone }) {
  useEffect(() => { if (!goal) return; const t = setTimeout(onDone, 4200); return () => clearTimeout(t); }, [goal]);
  if (!goal) return null;
  const t = T(goal.team);
  return (
    <div className="pp-goaltoast" role="alert" style={{ '--tc': t.color }}>
      <div className="gt-burst" aria-hidden="true">{[...Array(8)].map((_, i) => <span key={i} style={{ '--i': i }} />)}</div>
      <span className="gt-word display">GOAL!</span>
      <div className="gt-info">
        <Crest id={goal.team} size={30} ring />
        <div className="gt-text"><b>{goal.scorer || T(goal.team).name}</b><span className="mono">{goal.minute}' · {goal.scoreline}</span></div>
      </div>
      <IcBall size={22} />
    </div>
  );
}

/* ---------- DateNavigator ---------- */
function DateNavigator({ days, index, onIndex }) {
  const [calOpen, setCalOpen] = useState(false);
  const wrapRef = useRef(null);
  useEffect(() => {
    if (!calOpen) return;
    const h = (e) => { if (wrapRef.current && !wrapRef.current.contains(e.target)) setCalOpen(false); };
    const k = (e) => { if (e.key === 'Escape') setCalOpen(false); };
    document.addEventListener('mousedown', h); document.addEventListener('keydown', k);
    return () => { document.removeEventListener('mousedown', h); document.removeEventListener('keydown', k); };
  }, [calOpen]);

  return (
    <div className="pp-datenav">
      <button className="dn-arrow" onClick={() => onIndex(Math.max(0, index - 1))} aria-label="Previous day" disabled={index === 0}><IcChevL size={18} /></button>
      <div className="dn-days">
        {days.map((d, i) => (
          <button key={i} className={'dn-day' + (i === index ? ' on' : '')} onClick={() => onIndex(i)}>
            <span className="dn-dow">{d.dow}</span>
            <span className="dn-date display">{d.label}</span>
            {d.live > 0 && <span className="dn-live"><span className="d" />{d.live}</span>}
          </button>
        ))}
      </div>
      <button className="dn-arrow" onClick={() => onIndex(Math.min(days.length - 1, index + 1))} aria-label="Next day" disabled={index === days.length - 1}><IcChevR size={18} /></button>
      <div className="dn-cal-wrap" ref={wrapRef}>
        <button className={'dn-cal' + (calOpen ? ' on' : '')} aria-label="Open calendar" aria-expanded={calOpen} onClick={() => setCalOpen(o => !o)}><IcCalendar size={18} /></button>
        {calOpen && <CalendarPopover days={days} index={index} onPick={(i) => { onIndex(i); setCalOpen(false); }} />}
      </div>
    </div>
  );
}

function CalendarPopover({ days, index, onPick }) {
  const MON = ['January','February','March','April','May','June','July','August','September','October','November','December'];
  const DOW = ['Mo','Tu','We','Th','Fr','Sa','Su'];
  // Map each strip day -> a real Date around the demo "today" (14 Jun 2026, today at strip-index 3)
  const todayStripIdx = days.findIndex(d => d.dow === 'TODAY');
  const baseToday = new Date(2026, 5, 14);
  const dateForStrip = (i) => { const d = new Date(baseToday); d.setDate(baseToday.getDate() + (i - (todayStripIdx < 0 ? 3 : todayStripIdx))); return d; };
  const selectedDate = dateForStrip(index);
  const [view, setView] = useState({ y: selectedDate.getFullYear(), m: selectedDate.getMonth() });

  // strip date -> index lookup (by yyyy-mm-dd)
  const key = (d) => `${d.getFullYear()}-${d.getMonth()}-${d.getDate()}`;
  const stripMap = {}; days.forEach((_, i) => { stripMap[key(dateForStrip(i))] = i; });
  const liveMap = {}; days.forEach((d, i) => { if (d.live > 0) liveMap[key(dateForStrip(i))] = d.live; });

  const first = new Date(view.y, view.m, 1);
  const startPad = (first.getDay() + 6) % 7; // Monday-first
  const daysInMonth = new Date(view.y, view.m + 1, 0).getDate();
  const cells = [];
  for (let i = 0; i < startPad; i++) cells.push(null);
  for (let d = 1; d <= daysInMonth; d++) cells.push(new Date(view.y, view.m, d));

  const stepMonth = (delta) => setView(v => { const nm = v.m + delta; return { y: v.y + Math.floor(nm / 12), m: ((nm % 12) + 12) % 12 }; });
  const selKey = key(selectedDate), todayKey = key(dateForStrip(todayStripIdx < 0 ? 3 : todayStripIdx));

  return (
    <div className="pp-cal" role="dialog" aria-label="Choose date">
      <div className="cal-head">
        <button className="cal-nav" onClick={() => stepMonth(-1)} aria-label="Previous month"><IcChevL size={16} /></button>
        <span className="cal-title display">{MON[view.m]} {view.y}</span>
        <button className="cal-nav" onClick={() => stepMonth(1)} aria-label="Next month"><IcChevR size={16} /></button>
      </div>
      <div className="cal-dow">{DOW.map(d => <span key={d}>{d}</span>)}</div>
      <div className="cal-grid">
        {cells.map((d, i) => {
          if (!d) return <span key={i} className="cal-cell empty" />;
          const k = key(d), inStrip = stripMap[k] != null;
          const cls = ['cal-cell'];
          if (inStrip) cls.push('sel-able');
          if (k === selKey) cls.push('on');
          if (k === todayKey) cls.push('today');
          return (
            <button key={i} className={cls.join(' ')} disabled={!inStrip}
              onClick={() => inStrip && onPick(stripMap[k])} aria-current={k === selKey ? 'date' : undefined}>
              {d.getDate()}
              {liveMap[k] && <span className="cal-livedot" />}
            </button>
          );
        })}
      </div>
      <div className="cal-foot">
        <button className="cal-today-btn" onClick={() => onPick(todayStripIdx < 0 ? 3 : todayStripIdx)}><IcCalendar size={13} /> Jump to Today</button>
        <span className="cal-hint"><span className="cal-livedot static" /> has live matches</span>
      </div>
    </div>
  );
}

Object.assign(window, {
  StandingsTable, GroupCard, BracketNode, Bracket, TimelineEvent,
  Pitch, FormationView, StatComparisonBar, PossessionBar, H2HWidget, GoalToast, DateNavigator,
});
