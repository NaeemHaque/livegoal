/* ============================================================
   SocPlay — Global search command palette (modal)
   Opens on "/" or ⌘K, click search, or mobile search button.
   ============================================================ */
function SearchModal({ open, onClose, nav }) {
  const [q, setQ] = useState('');
  const [sel, setSel] = useState(0);
  const inputRef = useRef(null);

  const all = useMemo(() => ([
    ...window.PP.competitions.map(c => ({ kind: 'Competition', id: c.id, name: c.name, route: 'competition', color: c.color, icon: 'trophy' })),
    ...window.PP.nations.map(t => ({ kind: 'Team', id: t.id, name: t.name, route: 'team', crest: t.id })),
    ...window.PP.clubs.map(t => ({ kind: 'Team', id: t.id, name: t.name, route: 'team', crest: t.id })),
    { kind: 'Player', id: 'mbappe', name: 'Kylian Mbappé', route: 'player', crest: null, icon: 'user' },
  ]), []);

  const results = q.trim() ? all.filter(r => r.name.toLowerCase().includes(q.toLowerCase())).slice(0, 10) : [];

  useEffect(() => {
    if (open) { setQ(''); setSel(0); const t = setTimeout(() => inputRef.current && inputRef.current.focus(), 30); 
      document.body.style.overflow = 'hidden'; return () => { clearTimeout(t); document.body.style.overflow = ''; }; }
  }, [open]);
  useEffect(() => { setSel(0); }, [q]);

  const go = (r) => { if (!r) return; nav(r.route, { id: r.id }); onClose(); };
  const onKey = (e) => {
    if (e.key === 'Escape') { e.preventDefault(); onClose(); }
    else if (e.key === 'ArrowDown') { e.preventDefault(); setSel(s => Math.min(Math.max(results.length - 1, 0), s + 1)); }
    else if (e.key === 'ArrowUp') { e.preventDefault(); setSel(s => Math.max(0, s - 1)); }
    else if (e.key === 'Enter') { e.preventDefault(); go(results[sel]); }
  };

  if (!open) return null;
  const iconFor = (r) => r.crest ? <Crest id={r.crest} size={28} />
    : <span className="sm-ic" style={r.color ? { background: r.color, color: '#fff' } : {}}>{r.icon === 'trophy' ? <IcTrophy size={15} /> : <IcUsers size={15} />}</span>;

  return (
    <div className="pp-modal-overlay" onMouseDown={onClose}>
      <div className="pp-searchmodal" onMouseDown={e => e.stopPropagation()} role="dialog" aria-modal="true" aria-label="Search">
        <div className="sm-input">
          <IcSearch size={20} style={{ color: 'var(--text-muted)', flex: 'none' }} />
          <input ref={inputRef} value={q} onChange={e => setQ(e.target.value)} onKeyDown={onKey}
            placeholder="Search teams, competitions, players…" aria-label="Search query" />
          {q && <button className="sm-clear" onClick={() => { setQ(''); inputRef.current.focus(); }} aria-label="Clear"><IcClose size={16} /></button>}
          <button className="sm-esc" onClick={onClose}>esc</button>
        </div>

        <div className="sm-body">
          {!q && (
            <>
              <div className="sm-sec-label"><IcClock size={13} /> Recent searches</div>
              <div className="sm-chips">{window.PP.recentSearches.map(r => <button key={r} className="pp-chip" onClick={() => setQ(r)}>{r}</button>)}</div>
              <div className="sm-sec-label" style={{ marginTop: 18 }}><IcTrophy size={13} /> Jump to competition</div>
              <div className="sm-comp-grid">
                {window.PP.competitions.slice(0, 6).map(c => (
                  <button key={c.id} className="sm-comp" onClick={() => go({ route: 'competition', id: c.id })}>
                    <span className="sm-ic" style={{ background: c.color, color: '#fff' }}><IcTrophy size={14} /></span>
                    <span>{c.short}</span>
                  </button>
                ))}
              </div>
            </>
          )}

          {q && results.length === 0 && (
            <div className="sm-empty"><IcSearch size={24} style={{ color: 'var(--text-faint)' }} /><div>No results for "<b>{q}</b>"</div><span>Try a team, competition, or player name.</span></div>
          )}

          {q && results.length > 0 && (
            <div className="sm-results" role="listbox">
              {results.map((r, i) => (
                <button key={r.kind + r.id} role="option" aria-selected={i === sel}
                  className={'sm-row' + (i === sel ? ' on' : '')} onMouseEnter={() => setSel(i)} onClick={() => go(r)}>
                  {iconFor(r)}
                  <span className="sm-name">{r.name}</span>
                  <span className="sm-kind">{r.kind}</span>
                  <span className="sm-enter"><IcArrowR size={15} /></span>
                </button>
              ))}
            </div>
          )}
        </div>

        <div className="sm-foot">
          <span><kbd>↑</kbd><kbd>↓</kbd> navigate</span>
          <span><kbd>↵</kbd> open</span>
          <span><kbd>esc</kbd> close</span>
        </div>
      </div>
    </div>
  );
}
Object.assign(window, { SearchModal });
