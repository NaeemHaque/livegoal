/* ============================================================
   SocPlay — Data layer (mock, realtime-shaped)
   Nations use real flags via flagcdn (free, non-trademarked).
   Clubs use colored monogram badges (logos are trademarked).
   ============================================================ */
(function () {
  const flag = (iso) => `https://flagcdn.com/w160/${iso}.png`;

  // ---- 48 nations (World Cup 2026), real ISO codes for flags ----
  const N = (id, name, short, iso, c1, c2) => ({
    id, name, short, iso, type: 'nation', color: c1, color2: c2, flag: flag(iso)
  });
  const nations = [
    N('mex','Mexico','MEX','mx','#0E7A3B','#C8102E'),
    N('usa','United States','USA','us','#1A3A6B','#C8102E'),
    N('can','Canada','CAN','ca','#D52B1E','#ffffff'),
    N('arg','Argentina','ARG','ar','#75AADB','#ffffff'),
    N('bra','Brazil','BRA','br','#FCD116','#009739'),
    N('fra','France','FRA','fr','#1B3A7A','#EF4135'),
    N('eng','England','ENG','gb-eng','#E0102B','#ffffff'),
    N('esp','Spain','ESP','es','#C60B1E','#FFC400'),
    N('ger','Germany','GER','de','#111111','#DD0000'),
    N('por','Portugal','POR','pt','#006600','#DA020E'),
    N('ned','Netherlands','NED','nl','#F36C21','#21468B'),
    N('bel','Belgium','BEL','be','#111111','#FDDA24'),
    N('ita','Italy','ITA','it','#0B7A40','#1A3A7A'),
    N('cro','Croatia','CRO','hr','#C8102E','#1B3A7A'),
    N('uru','Uruguay','URU','uy','#5FA3DC','#FCD116'),
    N('col','Colombia','COL','co','#FCD116','#C8102E'),
    N('jpn','Japan','JPN','jp','#BC002D','#ffffff'),
    N('kor','South Korea','KOR','kr','#13357B','#C8102E'),
    N('mar','Morocco','MAR','ma','#C1272D','#006233'),
    N('sen','Senegal','SEN','sn','#00853F','#FDEF42'),
    N('sui','Switzerland','SUI','ch','#D52B1E','#ffffff'),
    N('den','Denmark','DEN','dk','#C8102E','#ffffff'),
    N('pol','Poland','POL','pl','#DC143C','#ffffff'),
    N('srb','Serbia','SRB','rs','#C6363C','#0C4076'),
    N('swe','Sweden','SWE','se','#005293','#FECB00'),
    N('ukr','Ukraine','UKR','ua','#0057B7','#FFD700'),
    N('aut','Austria','AUT','at','#ED2939','#ffffff'),
    N('nor','Norway','NOR','no','#BA0C2F','#00205B'),
    N('tur','Turkey','TUR','tr','#E30A17','#ffffff'),
    N('nga','Nigeria','NGA','ng','#008751','#ffffff'),
    N('gha','Ghana','GHA','gh','#006B3F','#FCD116'),
    N('civ','Ivory Coast','CIV','ci','#F77F00','#009E60'),
    N('cmr','Cameroon','CMR','cm','#007A5E','#CE1126'),
    N('egy','Egypt','EGY','eg','#C8102E','#000000'),
    N('tun','Tunisia','TUN','tn','#E70013','#ffffff'),
    N('alg','Algeria','ALG','dz','#006233','#D21034'),
    N('ecu','Ecuador','ECU','ec','#FCD116','#0072CE'),
    N('per','Peru','PER','pe','#D91023','#ffffff'),
    N('chi','Chile','CHI','cl','#0039A6','#D52B1E'),
    N('par','Paraguay','PAR','py','#D52B1E','#0038A8'),
    N('aus','Australia','AUS','au','#00843D','#FFCD00'),
    N('irn','Iran','IRN','ir','#239F40','#DA0000'),
    N('ksa','Saudi Arabia','KSA','sa','#006C35','#ffffff'),
    N('qat','Qatar','QAT','qa','#8A1538','#ffffff'),
    N('crc','Costa Rica','CRC','cr','#002B7F','#CE1126'),
    N('pan','Panama','PAN','pa','#005293','#D21034'),
    N('gre','Greece','GRE','gr','#0D5EAF','#ffffff'),
    N('sco','Scotland','SCO','gb-sct','#0065BF','#ffffff'),
  ];

  // ---- World Cup 2026 — 12 groups of 4 ----
  const groupLetters = ['A','B','C','D','E','F','G','H','I','J','K','L'];
  const groups = {};
  groupLetters.forEach((g, gi) => {
    groups[g] = nations.slice(gi * 4, gi * 4 + 4).map(n => n.id);
  });

  // ---- Clubs (colored monogram badges) ----
  const C = (id, name, short, mono, c1, c2) => ({
    id, name, short, mono, type: 'club', color: c1, color2: c2
  });
  const clubs = [
    C('mci','Manchester City','MCI','MC','#6CABDD','#1C2C5B'),
    C('ars','Arsenal','ARS','AR','#EF0107','#ffffff'),
    C('liv','Liverpool','LIV','LFC','#C8102E','#00B2A9'),
    C('che','Chelsea','CHE','CH','#034694','#ffffff'),
    C('tot','Tottenham','TOT','TH','#132257','#ffffff'),
    C('mun','Man United','MUN','MU','#DA291C','#FBE122'),
    C('new','Newcastle','NEW','NU','#241F20','#ffffff'),
    C('avl','Aston Villa','AVL','AV','#670E36','#95BFE5'),
    C('bha','Brighton','BHA','BR','#0057B8','#ffffff'),
    C('whu','West Ham','WHU','WH','#7A263A','#1BB1E7'),
    C('rmd','Real Madrid','RMA','RM','#FEBE10','#00529F'),
    C('fcb','Barcelona','BAR','FCB','#A50044','#004D98'),
    C('atm','Atlético Madrid','ATM','AT','#CB3524','#272E61'),
    C('bay','Bayern München','BAY','FCB','#DC052D','#0066B2'),
    C('bvb','Dortmund','DOR','BVB','#FDE100','#000000'),
    C('psg','Paris SG','PSG','PSG','#004170','#DA291C'),
    C('int','Inter','INT','IM','#0068A8','#000000'),
    C('juv','Juventus','JUV','JV','#111111','#ffffff'),
    C('mil','AC Milan','MIL','AC','#FB090B','#000000'),
    C('nap','Napoli','NAP','SSC','#12A0D7','#003D7C'),
  ];

  const teams = {};
  [...nations, ...clubs].forEach(t => { teams[t.id] = t; });

  // ---- Competitions ----
  const competitions = [
    { id: 'wc26', name: 'FIFA World Cup 2026', short: 'World Cup', region: 'International', host: 'USA · Canada · Mexico', color: '#C6FF3A', kind: 'cup', tier: 1, featured: true },
    { id: 'ucl', name: 'UEFA Champions League', short: 'Champions Lg', region: 'Europe', color: '#0B1B6F', kind: 'cup', tier: 1 },
    { id: 'epl', name: 'Premier League', short: 'Premier Lg', region: 'England', color: '#37003C', kind: 'league', tier: 1 },
    { id: 'lal', name: 'LaLiga', short: 'LaLiga', region: 'Spain', color: '#E30613', kind: 'league', tier: 1 },
    { id: 'sea', name: 'Serie A', short: 'Serie A', region: 'Italy', color: '#0067B1', kind: 'league', tier: 1 },
    { id: 'bun', name: 'Bundesliga', short: 'Bundesliga', region: 'Germany', color: '#D20515', kind: 'league', tier: 1 },
    { id: 'lig', name: 'Ligue 1', short: 'Ligue 1', region: 'France', color: '#091C3E', kind: 'league', tier: 1 },
  ];
  const compById = {};
  competitions.forEach(c => { compById[c.id] = c; });

  // ---- Match builder ----
  let _mid = 0;
  const M = (o) => ({
    id: 'm' + (++_mid),
    comp: o.comp,
    stage: o.stage || '',
    home: o.home, away: o.away,
    hs: o.hs ?? 0, as: o.as ?? 0,
    status: o.status,           // SCHEDULED|LIVE|HT|FT|ET|PEN|POSTPONED
    minute: o.minute ?? null,
    kickoff: o.kickoff,         // ISO-ish display string / hh:mm
    venue: o.venue || '',
    city: o.city || '',
    referee: o.referee || '',
    period: o.period || '',     // 1H|2H|ET etc for live label
    events: o.events || null,
    favored: o.favored || false,
  });

  // Today's headline fixtures across competitions (mixed states)
  const todayMatches = [
    M({ comp:'wc26', stage:'Group F', home:'fra', away:'mar', hs:2, as:1, status:'LIVE', minute:74, period:'2H',
        venue:'MetLife Stadium', city:'New York', referee:'C. Ramos', favored:true }),
    M({ comp:'wc26', stage:'Group A', home:'mex', away:'pol', hs:1, as:1, status:'LIVE', minute:38, period:'1H',
        venue:'Estadio Azteca', city:'Mexico City', referee:'S. Marciniak' }),
    M({ comp:'wc26', stage:'Group D', home:'arg', away:'aus', hs:3, as:0, status:'HT', minute:45, period:'HT',
        venue:'AT&T Stadium', city:'Dallas', referee:'A. Taylor' }),
    M({ comp:'ucl', stage:'Quarter-final', home:'mci', away:'rmd', hs:1, as:2, status:'LIVE', minute:61, period:'2H',
        venue:'Etihad Stadium', city:'Manchester', referee:'D. Orsato' }),
    M({ comp:'epl', stage:'Matchday 33', home:'ars', away:'liv', hs:0, as:0, status:'LIVE', minute:12, period:'1H',
        venue:'Emirates Stadium', city:'London', referee:'M. Oliver' }),

    M({ comp:'wc26', stage:'Group B', home:'usa', away:'bel', status:'SCHEDULED', kickoff:'19:00',
        venue:'SoFi Stadium', city:'Los Angeles', referee:'F. Zwayer' }),
    M({ comp:'wc26', stage:'Group C', home:'bra', away:'jpn', status:'SCHEDULED', kickoff:'21:00',
        venue:'Hard Rock Stadium', city:'Miami', referee:'W. Faghani' }),
    M({ comp:'epl', stage:'Matchday 33', home:'che', away:'tot', status:'SCHEDULED', kickoff:'17:30',
        venue:'Stamford Bridge', city:'London', referee:'A. Madley' }),
    M({ comp:'lal', stage:'Jornada 31', home:'rmd', away:'fcb', status:'SCHEDULED', kickoff:'20:00',
        venue:'Santiago Bernabéu', city:'Madrid', referee:'J. Sánchez' }),

    M({ comp:'wc26', stage:'Group E', home:'esp', away:'cro', hs:2, as:0, status:'FT',
        venue:'Lumen Field', city:'Seattle', referee:'I. Kovács' }),
    M({ comp:'wc26', stage:'Group H', home:'por', away:'uru', hs:1, as:1, status:'FT',
        venue:'Arrowhead Stadium', city:'Kansas City', referee:'D. Makkelie' }),
    M({ comp:'bun', stage:'Spieltag 28', home:'bay', away:'bvb', hs:3, as:2, status:'FT',
        venue:'Allianz Arena', city:'Munich', referee:'F. Brych' }),
    M({ comp:'sea', stage:'Giornata 31', home:'int', away:'juv', status:'POSTPONED', kickoff:'—',
        venue:'San Siro', city:'Milan', referee:'—' }),
  ];

  // ---- HERO live match: France 2–1 Morocco, full detail ----
  const heroEvents = [
    { min: 18, type: 'goal', team: 'home', player: 'K. Mbappé', detail: 'Right-foot finish', assist: 'A. Griezmann' },
    { min: 29, type: 'yellow', team: 'away', player: 'S. Amrabat', detail: 'Tactical foul' },
    { min: 45, type: 'goal', team: 'away', player: 'Y. En-Nesyri', detail: 'Header', assist: 'H. Ziyech' },
    { min: '45+2', type: 'whistle', team: null, player: 'Half time', detail: '1 – 1' },
    { min: 52, type: 'sub', team: 'home', player: 'O. Dembélé', detail: 'A. Griezmann' },
    { min: 66, type: 'goal', team: 'home', player: 'O. Dembélé', detail: 'Low drive', assist: 'A. Tchouaméni' },
    { min: 71, type: 'yellow', team: 'home', player: 'A. Tchouaméni', detail: 'Holding' },
  ];
  const heroStats = {
    possession: [58, 42],
    shots: [14, 9], shotsOnTarget: [6, 4], corners: [7, 3],
    fouls: [9, 13], yellow: [1, 1], red: [0, 0], offsides: [2, 4],
    passes: [612, 441], passAcc: [89, 84], xg: [2.31, 1.04],
  };
  // 4-3-3 vs 4-2-3-1 — coords are % of pitch (x along width, y along length 0=own goal)
  const heroLineups = {
    home: { formation: '4-3-3', coach: 'D. Deschamps',
      xi: [
        { n:1, name:'M. Maignan', pos:'GK', x:50, y:6 },
        { n:22, name:'T. Hernández', pos:'LB', x:18, y:24 },
        { n:4, name:'D. Upamecano', pos:'CB', x:38, y:20 },
        { n:5, name:'J. Koundé', pos:'CB', x:62, y:20 },
        { n:2, name:'B. Pavard', pos:'RB', x:82, y:24 },
        { n:8, name:'A. Tchouaméni', pos:'CM', x:50, y:40 },
        { n:14, name:'A. Rabiot', pos:'CM', x:30, y:46 },
        { n:6, name:'E. Camavinga', pos:'CM', x:70, y:46 },
        { n:7, name:'A. Griezmann', pos:'LW', x:24, y:68 },
        { n:10, name:'K. Mbappé', pos:'ST', x:50, y:78 },
        { n:11, name:'O. Dembélé', pos:'RW', x:76, y:68 },
      ],
      bench:['B. Samba (GK)','I. Konaté','W. Saliba','Y. Fofana','M. Thuram','K. Coman','R. Kolo Muani'] },
    away: { formation: '4-2-3-1', coach: 'W. Regragui',
      xi: [
        { n:1, name:'Y. Bounou', pos:'GK', x:50, y:6 },
        { n:3, name:'N. Mazraoui', pos:'RB', x:80, y:22 },
        { n:5, name:'N. Aguerd', pos:'CB', x:60, y:18 },
        { n:6, name:'R. Saïss', pos:'CB', x:40, y:18 },
        { n:2, name:'A. Hakimi', pos:'LB', x:20, y:22 },
        { n:4, name:'S. Amrabat', pos:'DM', x:42, y:38 },
        { n:8, name:'A. Ounahi', pos:'DM', x:58, y:38 },
        { n:7, name:'H. Ziyech', pos:'RW', x:78, y:58 },
        { n:10, name:'A. Harit', pos:'AM', x:50, y:56 },
        { n:11, name:'S. Boufal', pos:'LW', x:22, y:58 },
        { n:19, name:'Y. En-Nesyri', pos:'ST', x:50, y:76 },
      ],
      bench:['M. Aboukhlal','A. Sabiri','B. Dari','Z. Aboukhlal','W. Cheddira','I. Chair'] },
  };
  const heroH2H = [
    { date:'2022', comp:'World Cup SF', home:'fra', away:'mar', hs:2, as:0 },
    { date:'2018', comp:'Friendly', home:'mar', away:'fra', hs:1, as:1 },
    { date:'2007', comp:'Friendly', home:'fra', away:'mar', hs:2, as:2 },
    { date:'2000', comp:'Hassan II', home:'mar', away:'fra', hs:1, as:5 },
  ];

  // ---- Premier League standings ----
  const form = (s) => s.split('');
  const eplTable = [
    { team:'liv', p:32, w:24, d:5, l:3, gf:74, ga:28, pts:77, form:'WWWDW' },
    { team:'ars', p:32, w:23, d:6, l:3, gf:69, ga:26, pts:75, form:'WWDWW' },
    { team:'mci', p:32, w:22, d:7, l:3, gf:71, ga:31, pts:73, form:'WDWWL' },
    { team:'che', p:32, w:18, d:8, l:6, gf:58, ga:38, pts:62, form:'WLWDW' },
    { team:'tot', p:32, w:17, d:6, l:9, gf:62, ga:45, pts:57, form:'LWWDL' },
    { team:'avl', p:32, w:16, d:8, l:8, gf:57, ga:49, pts:56, form:'DWLWD' },
    { team:'new', p:32, w:16, d:6, l:10, gf:60, ga:48, pts:54, form:'WWLWL' },
    { team:'mun', p:32, w:15, d:7, l:10, gf:51, ga:44, pts:52, form:'DLWWD' },
    { team:'bha', p:32, w:13, d:11, l:8, gf:50, ga:47, pts:50, form:'DDWLD' },
    { team:'whu', p:32, w:13, d:7, l:12, gf:48, ga:53, pts:46, form:'LWLDW' },
  ];

  // ---- World Cup top scorers ----
  const topScorers = [
    { player:'K. Mbappé', team:'fra', goals:6, assists:2, pens:1, mins:540 },
    { player:'J. Álvarez', team:'arg', goals:5, assists:1, pens:0, mins:498 },
    { player:'H. Kane', team:'eng', goals:4, assists:3, pens:2, mins:521 },
    { player:'Vinícius Jr', team:'bra', goals:4, assists:2, pens:0, mins:470 },
    { player:'Y. En-Nesyri', team:'mar', goals:4, assists:0, pens:0, mins:512 },
    { player:'L. Lautaro Martínez', team:'arg', goals:3, assists:2, pens:0, mins:402 },
    { player:'O. Dembélé', team:'fra', goals:3, assists:4, pens:0, mins:418 },
    { player:'C. Pulisic', team:'usa', goals:3, assists:1, pens:0, mins:455 },
  ];

  // ---- Player detail (Mbappé) ----
  const playerDetail = {
    id:'mbappe', name:'Kylian Mbappé', team:'fra', club:'rmd', pos:'Forward', shirt:10,
    nationality:'France', natIso:'fr', age:27, height:'1.78 m', foot:'Right',
    photo:null,
    season:{ apps:5, goals:6, assists:2, mins:540, shots:21, shotsOnTarget:12, xg:5.4, passAcc:81, dribbles:18, rating:8.4 },
    empty:['Tackles','Interceptions','Clean sheets'], // free-tier may not provide
  };

  // ---- Squad (France) grouped by position ----
  const franceSquad = {
    Goalkeepers:[['M. Maignan',1],['B. Samba',16],['A. Areola',23]],
    Defenders:[['T. Hernández',22],['D. Upamecano',4],['J. Koundé',5],['B. Pavard',2],['I. Konaté',18],['W. Saliba',17],['F. Mendy',3]],
    Midfielders:[['A. Tchouaméni',8],['A. Rabiot',14],['E. Camavinga',6],['Y. Fofana',13],['W. Zaïre-Emery',24]],
    Forwards:[['K. Mbappé',10],['A. Griezmann',7],['O. Dembélé',11],['M. Thuram',9],['K. Coman',20],['R. Kolo Muani',12]],
  };

  // ---- Recent searches ----
  const recentSearches = ['Mbappé','Champions League','Brazil','Real Madrid','Group F'];

  window.PP = {
    flag, teams, nations, clubs, groups, groupLetters,
    competitions, compById,
    todayMatches,
    hero: { matchSeed: { comp:'wc26', stage:'Group F', home:'fra', away:'mar', hs:2, as:1, status:'LIVE', minute:74, period:'2H',
            venue:'MetLife Stadium', city:'New York / New Jersey', referee:'César Arturo Ramos', kickoff:'20:00' },
            events: heroEvents, stats: heroStats, lineups: heroLineups, h2h: heroH2H },
    eplTable, topScorers, playerDetail, franceSquad, recentSearches,
  };
})();
