/* ============================================================
   SocPlay — Derived data helpers
   ============================================================ */
(function () {
  const PP = window.PP;

  // Stable pseudo-random from string seed
  function seed(str) { let h = 2166136261; for (let i = 0; i < str.length; i++) { h ^= str.charCodeAt(i); h = Math.imul(h, 16777619); } return () => { h += 0x6D2B79F5; let t = Math.imul(h ^ h >>> 15, 1 | h); t ^= t + Math.imul(t ^ t >>> 7, 61 | t); return ((t ^ t >>> 14) >>> 0) / 4294967296; }; }

  // Generate plausible, stable group standings for a WC group
  function groupStandings(letter) {
    const ids = PP.groups[letter];
    const rnd = seed('grp' + letter);
    const rows = ids.map((id, i) => {
      const p = 3;
      const strength = 1 - i * 0.18 + (rnd() - 0.5) * 0.2;
      const w = Math.max(0, Math.min(3, Math.round(strength * 2.4)));
      const d = Math.min(3 - w, Math.round(rnd() * 1.4));
      const l = 3 - w - d;
      const gf = w * 2 + d + Math.round(rnd() * 3);
      const ga = l * 2 + Math.round(rnd() * 2);
      const formArr = [];
      [...Array(3)].forEach((_, k) => formArr.push(k < w ? 'W' : k < w + d ? 'D' : 'L'));
      return { team: id, p, w, d, l, gf, ga, pts: w * 3 + d, gd: gf - ga, form: formArr.sort(() => rnd() - 0.5).join('') };
    });
    rows.sort((a, b) => b.pts - a.pts || b.gd - a.gd || b.gf - a.gf);
    return rows;
  }

  // Knockout bracket (Round of 32 → Final), stable
  function knockout() {
    const adv = [];
    PP.groupLetters.forEach(l => { const s = groupStandings(l); adv.push(s[0].team, s[1].team); });
    // 24 here; pad with 8 best (just reuse a few) to reach 32 for R32 visual
    const extra = ['eng','bel','jpn','uru','sui','sen','col','per'];
    const pool = [...adv, ...extra].slice(0, 32);
    const rnd = seed('ko');
    const mkScore = () => Math.floor(rnd() * 4);
    const round = (teams, title, gap, liveOne = false) => {
      const ties = []; const winners = [];
      for (let i = 0; i < teams.length; i += 2) {
        let hs = mkScore(), as = mkScore(); if (hs === as) hs++;
        const live = liveOne && i === 0;
        ties.push({ home: teams[i], away: teams[i + 1], hs: teams[i] ? hs : null, as: teams[i + 1] ? as : null, live });
        winners.push(hs >= as ? teams[i] : teams[i + 1]);
      }
      return { round: { title, gap, ties }, winners };
    };
    const r32 = round(pool, 'Round of 32', 6);
    const r16 = round(r32.winners, 'Round of 16', 18);
    const qf = round(r16.winners, 'Quarter-finals', 46, true);
    const sf = round(qf.winners, 'Semi-finals', 110);
    const f = round(sf.winners, 'Final', 240);
    return [r32.round, r16.round, qf.round, sf.round, f.round];
  }

  // Date strip around "today"
  function dateStrip() {
    const dows = ['SUN', 'MON', 'TUE', 'WED', 'THU', 'FRI', 'SAT'];
    const mons = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    const base = new Date(2026, 5, 14); // mid World Cup
    const days = [];
    for (let i = -3; i <= 3; i++) {
      const d = new Date(base); d.setDate(base.getDate() + i);
      days.push({ dow: i === 0 ? 'TODAY' : dows[d.getDay()], label: `${d.getDate()} ${mons[d.getMonth()]}`, live: i === 0 ? 5 : i === 1 ? 0 : 0, today: i === 0 });
    }
    return { days, todayIndex: 3 };
  }

  window.PPX = { groupStandings, knockout, dateStrip, seed };
})();
