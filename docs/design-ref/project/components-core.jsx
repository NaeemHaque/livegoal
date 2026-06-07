/* ============================================================
   SocPlay — Core components
   Crest, Flag, TeamChip, ScoreDisplay, LivePulseBadge,
   MatchStatus, MinuteTicker, MatchCard, FormGuide, FavoriteStar,
   FilterTabs, RefreshIndicator, Skeletons, Empty/Error states
   ============================================================ */
const { useState, useEffect, useRef, useMemo, useCallback } = React;
const T = (id) => window.PP.teams[id];

/* ---------- Crest: real flag for nations, monogram for clubs ---------- */
function Crest({ id, size = 28, ring = false }) {
  const t = T(id);
  if (!t) return null;
  const st = { width: size, height: size };
  if (t.type === 'nation') {
    return (
      <span className={'pp-crest flag' + (ring ? ' ring' : '')} style={st} title={t.name}>
        <img src={t.flag} alt={t.name} loading="lazy"
          onError={(e) => { e.currentTarget.style.display = 'none'; }} />
      </span>
    );
  }
  return (
    <span className={'pp-crest mono' + (ring ? ' ring' : '')} title={t.name}
      style={{ ...st, background: `linear-gradient(135deg, ${t.color}, ${t.color2 || t.color})`, fontSize: size * 0.4 }}>
      <span style={{ color: pickText(t.color) }}>{t.mono}</span>
    </span>
  );
}
function pickText(hex) {
  const c = hex.replace('#', ''); if (c.length < 6) return '#fff';
  const r = parseInt(c.slice(0, 2), 16), g = parseInt(c.slice(2, 4), 16), b = parseInt(c.slice(4, 6), 16);
  return (0.299 * r + 0.587 * g + 0.114 * b) > 150 ? '#10151c' : '#fff';
}

/* ---------- TeamChip ---------- */
function TeamChip({ id, size = 24, reverse = false, code = false, strong = false, className = '' }) {
  const t = T(id);
  if (!t) return null;
  return (
    <span className={`pp-teamchip ${reverse ? 'rev' : ''} ${className}`}>
      <Crest id={id} size={size} />
      <span className={'name ' + (strong ? 'strong' : '')}>{code ? t.short : t.name}</span>
    </span>
  );
}

/* ---------- LivePulseBadge ---------- */
function LivePulseBadge({ label = 'LIVE', small = false }) {
  return (
    <span className={'pp-live-badge' + (small ? ' sm' : '')} role="status">
      <span className="dot" aria-hidden="true" />
      <span className="lbl">{label}</span>
    </span>
  );
}

/* ---------- MatchStatus chip (status distinguished by icon+shape, not color alone) ---------- */
function MatchStatus({ m, small = false }) {
  const s = m.status;
  if (s === 'LIVE' || s === 'ET') return <MinuteTicker m={m} small={small} />;
  if (s === 'HT') return <span className={'pp-status ht' + (small ? ' sm' : '')}><IcClock size={small ? 11 : 13} /> HT</span>;
  if (s === 'PEN') return <span className={'pp-status pen' + (small ? ' sm' : '')}><IcBall size={small ? 11 : 13} /> PENS</span>;
  if (s === 'FT') return <span className={'pp-status ft' + (small ? ' sm' : '')}><IcCheck size={small ? 11 : 13} /> FT</span>;
  if (s === 'POSTPONED') return <span className={'pp-status pp' + (small ? ' sm' : '')}><IcAlert size={small ? 11 : 13} /> PP</span>;
  return <span className={'pp-status sched' + (small ? ' sm' : '')}><IcClock size={small ? 11 : 13} /> {m.kickoff}</span>;
}

/* ---------- MinuteTicker: ticking minute + pulsing live ---------- */
function MinuteTicker({ m, small = false }) {
  const blink = useBlink();
  const label = m.status === 'ET' ? `ET ${m.minute}'` : `${m.minute}'`;
  return (
    <span className={'pp-status live' + (small ? ' sm' : '')}>
      <span className="dot" style={{ opacity: blink ? 1 : 0.35 }} aria-hidden="true" />
      <span className="mono">{label}</span>
    </span>
  );
}
function useBlink(ms = 900) {
  const [on, setOn] = useState(true);
  useEffect(() => { const t = setInterval(() => setOn(o => !o), ms); return () => clearInterval(t); }, [ms]);
  return on;
}

/* ---------- ScoreDisplay: scoreboard with flip on change ---------- */
function ScoreDigit({ value, size }) {
  const [flip, setFlip] = useState(false);
  const prev = useRef(value);
  useEffect(() => {
    if (prev.current !== value) { setFlip(true); const t = setTimeout(() => setFlip(false), 420); prev.current = value; return () => clearTimeout(t); }
  }, [value]);
  return <span className={'pp-digit display tnum' + (flip ? ' flip' : '')} style={{ fontSize: size }}>{value}</span>;
}
function ScoreDisplay({ m, size = 44, started }) {
  const live = m.status === 'LIVE' || m.status === 'HT' || m.status === 'ET' || m.status === 'PEN';
  const show = started ?? (m.status !== 'SCHEDULED' && m.status !== 'POSTPONED');
  if (!show) return <span className="pp-score vs display" style={{ fontSize: size * 0.5 }}>vs</span>;
  return (
    <span className={'pp-score display' + (live ? ' live' : '')} style={{ fontSize: size }}>
      <ScoreDigit value={m.hs} size={size} />
      <span className="sep" style={{ fontSize: size * 0.7 }}>–</span>
      <ScoreDigit value={m.as} size={size} />
    </span>
  );
}

/* ---------- FormGuide pills (W/D/L: color + letter + shape) ---------- */
function FormGuide({ form, size = 20 }) {
  return (
    <span className="pp-form" aria-label={'Form: ' + form.split('').join(' ')}>
      {form.split('').map((r, i) => (
        <span key={i} className={'fp ' + r.toLowerCase()} style={{ width: size, height: size, fontSize: size * 0.55 }} title={{ W: 'Win', D: 'Draw', L: 'Loss' }[r]}>{r}</span>
      ))}
    </span>
  );
}

/* ---------- FavoriteStar ---------- */
function FavoriteStar({ active, onToggle, size = 18, label = 'Favorite' }) {
  return (
    <button className={'pp-fav' + (active ? ' on' : '')} onClick={(e) => { e.stopPropagation(); onToggle && onToggle(); }}
      aria-pressed={active} aria-label={label} title={active ? 'Following' : 'Follow'}>
      <IcStar filled={active} size={size} />
    </button>
  );
}

/* ---------- FilterTabs ---------- */
function FilterTabs({ tabs, value, onChange, counts }) {
  return (
    <div className="pp-filtertabs" role="tablist">
      {tabs.map(t => (
        <button key={t.id} role="tab" aria-selected={value === t.id}
          className={'ft' + (value === t.id ? ' on' : '')} onClick={() => onChange(t.id)}>
          {t.icon}{t.label}
          {counts && counts[t.id] != null && <span className="cnt">{counts[t.id]}</span>}
        </button>
      ))}
    </div>
  );
}

/* ---------- MatchCard (compact + expanded) ---------- */
function MatchCard({ m, expanded = false, onOpen, fav, onFav, justScored }) {
  const comp = window.PP.compById[m.comp];
  const live = m.status === 'LIVE' || m.status === 'HT' || m.status === 'ET' || m.status === 'PEN';
  const home = T(m.home), away = T(m.away);
  const winner = m.status === 'FT' ? (m.hs > m.as ? 'home' : m.as > m.hs ? 'away' : null) : null;
  return (
    <article className={'pp-matchcard' + (expanded ? ' expanded' : '') + (live ? ' is-live' : '') + (justScored ? ' scored' : '')}
      onClick={() => onOpen && onOpen(m)} tabIndex={0} role="button"
      onKeyDown={(e) => { if (e.key === 'Enter' && onOpen) onOpen(m); }}
      aria-label={`${home.name} ${m.hs} ${away.name} ${m.as}, ${m.status}`}>
      <div className="mc-top">
        <span className="mc-comp"><span className="dot" style={{ background: comp.color }} />{comp.short}{m.stage ? ' · ' + m.stage : ''}</span>
        <div className="mc-top-r">
          <MatchStatus m={m} small />
          {onFav && <FavoriteStar active={fav} onToggle={onFav} size={16} />}
        </div>
      </div>
      <div className="mc-body">
        <div className={'mc-team' + (winner === 'home' ? ' win' : winner === 'away' ? ' lose' : '')}>
          <Crest id={m.home} size={expanded ? 28 : 26} />
          <span className="t-name">{expanded ? home.name : home.short}</span>
        </div>
        <div className="mc-score"><ScoreDisplay m={m} size={expanded ? 34 : 30} /></div>
        <div className={'mc-team rev' + (winner === 'away' ? ' win' : winner === 'home' ? ' lose' : '')}>
          <span className="t-name">{expanded ? away.name : away.short}</span>
          <Crest id={m.away} size={expanded ? 28 : 26} />
        </div>
      </div>
      {expanded && (m.venue || m.referee) && (
        <div className="mc-meta">
          {m.venue && <span><IcPin size={13} />{m.venue}{m.city ? ', ' + m.city : ''}</span>}
          {m.referee && <span><IcWhistle size={13} />{m.referee}</span>}
        </div>
      )}
      {live && <span className="mc-liveline" aria-hidden="true" />}
    </article>
  );
}

/* ---------- RefreshIndicator: countdown ring ---------- */
function RefreshIndicator({ seconds = 15, paused, onTick, compact = false }) {
  const [t, setT] = useState(seconds);
  const onTickRef = useRef(onTick);
  onTickRef.current = onTick;
  useEffect(() => {
    if (paused) return;
    const iv = setInterval(() => setT(prev => {
      if (prev <= 1) { setTimeout(() => onTickRef.current && onTickRef.current(), 0); return seconds; }
      return prev - 1;
    }), 1000);
    return () => clearInterval(iv);
  }, [paused, seconds]);
  const r = 9, circ = 2 * Math.PI * r, off = circ * (1 - t / seconds);
  return (
    <span className={'pp-refresh' + (compact ? ' compact' : '')} title={paused ? 'Auto-refresh paused' : `Updating in ${t}s`}>
      <svg width="24" height="24" viewBox="0 0 24 24" className={paused ? '' : 'spin-host'}>
        <circle cx="12" cy="12" r={r} fill="none" stroke="var(--border-strong)" strokeWidth="2.2" />
        <circle cx="12" cy="12" r={r} fill="none" stroke="var(--accent)" strokeWidth="2.2"
          strokeDasharray={circ} strokeDashoffset={off} strokeLinecap="round"
          transform="rotate(-90 12 12)" style={{ transition: 'stroke-dashoffset 1s linear' }} />
      </svg>
      {!compact && <span className="mono">{paused ? 'Paused' : 'LIVE'}</span>}
    </span>
  );
}

/* ---------- Skeletons ---------- */
function Skeleton({ w, h = 14, r = 8, style }) {
  return <span className="pp-skel" style={{ width: w, height: h, borderRadius: r, ...style }} />;
}
function MatchCardSkeleton() {
  return (
    <div className="pp-matchcard skel-card">
      <div className="mc-top"><Skeleton w={90} h={11} /><Skeleton w={42} h={11} /></div>
      <div className="mc-body">
        <div className="mc-team"><Skeleton w={26} h={26} r={6} /><Skeleton w={40} h={13} /></div>
        <Skeleton w={56} h={28} r={8} />
        <div className="mc-team rev"><Skeleton w={40} h={13} /><Skeleton w={26} h={26} r={6} /></div>
      </div>
    </div>
  );
}

/* ---------- Empty / Error / Offline states ---------- */
function StateBlock({ icon, title, text, action, tone = 'neutral' }) {
  return (
    <div className={'pp-state ' + tone}>
      <div className="ic">{icon}</div>
      <div className="st-title display">{title}</div>
      {text && <div className="st-text">{text}</div>}
      {action}
    </div>
  );
}

Object.assign(window, {
  T, Crest, pickText, TeamChip, LivePulseBadge, MatchStatus, MinuteTicker, useBlink,
  ScoreDisplay, ScoreDigit, FormGuide, FavoriteStar, FilterTabs, MatchCard,
  RefreshIndicator, Skeleton, MatchCardSkeleton, StateBlock,
  useState, useEffect, useRef, useMemo, useCallback,
});
