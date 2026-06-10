<?php

/*
|--------------------------------------------------------------------------
| Football glossary — one indexable page per term
|--------------------------------------------------------------------------
| Each term gets its own keyword URL (/guides/what-is-offside) so it can rank
| for the specific question, instead of being buried in a single glossary page.
| Served by App\Http\Controllers\ContentController; listed on the glossary hub.
| Bodies are plain text (rendered as paragraphs by the term template).
*/

return [
    'what-is-offside' => [
        'term' => 'Offside',
        'title' => 'What is offside in football? The rule explained',
        'question' => 'What is offside in football?',
        'description' => 'Offside explained simply: when an attacker is offside, why it gives a free-kick to the other team, and when a player is not penalised.',
        'definition' => 'An attacker is offside if, at the moment a team-mate plays the ball, they are nearer the opponents\' goal line than both the ball and the second-last defender (usually the last outfield defender plus the goalkeeper) — and then become involved in the play.',
        'detail' => 'Being in an offside position is not an offence by itself; it is only penalised if the player then plays the ball, interferes with an opponent, or gains an advantage. A player cannot be offside in their own half, from a goal kick, throw-in or corner. The punishment is an indirect free-kick to the defending team.',
    ],

    'what-is-goal-difference' => [
        'term' => 'Goal difference',
        'title' => 'What is goal difference (GD) in football?',
        'question' => 'What is goal difference?',
        'description' => 'Goal difference (GD) explained: how it is calculated and why it is the first tiebreaker for teams level on points in a league table or World Cup group.',
        'definition' => 'Goal difference (GD) is the number of goals a team has scored minus the number it has conceded. A team that has scored 20 and conceded 8 has a goal difference of +12.',
        'detail' => 'Goal difference is the most common first tiebreaker when teams finish level on points, in both league tables and World Cup groups. A better (higher) goal difference ranks a team above one with the same points. You can see goal difference in the "GD" column of every table on LiveGoal.',
        'link' => ['label' => 'See live league tables', 'to' => '/competitions'],
    ],

    'what-is-a-clean-sheet' => [
        'term' => 'Clean sheet',
        'title' => 'What is a clean sheet in football?',
        'question' => 'What is a clean sheet?',
        'description' => 'A clean sheet explained: what it means when a team or goalkeeper keeps a clean sheet, and why it matters.',
        'definition' => 'A team keeps a clean sheet when it concedes no goals in a match. The credit usually goes to the goalkeeper and the defence.',
        'detail' => 'Clean sheets are often used to measure defensive and goalkeeping performance over a season. A 0–0 draw or any win or draw without conceding counts as a clean sheet for that team.',
    ],

    'what-is-extra-time' => [
        'term' => 'Extra time',
        'title' => 'What is extra time in football?',
        'question' => 'What is extra time?',
        'description' => 'Extra time explained: when it is played, how long it lasts, and how it leads to a penalty shootout in knockout matches.',
        'definition' => 'Extra time is two additional 15-minute periods played when a knockout match is level after 90 minutes, used to try to find a winner.',
        'detail' => 'Both 15-minute halves are always played in full (there is no "golden goal"). If the score is still level after extra time, the match goes to a penalty shootout. Extra time is only used in knockout matches that must produce a winner — not in the group stage.',
        'link' => ['label' => 'How the World Cup knockout works', 'to' => '/guides/world-cup-2026-knockout-bracket-explained'],
    ],

    'what-is-a-penalty-shootout' => [
        'term' => 'Penalty shootout',
        'title' => 'What is a penalty shootout in football?',
        'question' => 'What is a penalty shootout?',
        'description' => 'Penalty shootout explained: how it decides a knockout match still level after extra time, with five kicks each and then sudden death.',
        'definition' => 'A penalty shootout decides a knockout match that is still level after extra time. Teams take alternate penalty kicks from the penalty spot.',
        'detail' => 'Each team takes five penalties first; whoever scores more wins. If still level after five each, it goes to sudden death — one kick each per round until one team scores and the other misses. Only players on the pitch at the end of extra time may take part.',
    ],

    'what-is-added-time' => [
        'term' => 'Added time',
        'title' => 'What is added time (stoppage time) in football?',
        'question' => 'What is added time?',
        'description' => 'Added time, or stoppage time, explained: why the referee adds minutes at the end of each half and how it is decided.',
        'definition' => 'Added time (also called stoppage or injury time) is the extra time the referee adds at the end of each half to make up for time lost during play.',
        'detail' => 'Stoppages for substitutions, injuries, goal celebrations, VAR checks and time-wasting are added on. The fourth official signals the minimum number of minutes to be played, but the referee can add more.',
    ],

    'what-is-aggregate-score' => [
        'term' => 'Aggregate',
        'title' => 'What is aggregate score in football?',
        'question' => 'What is an aggregate score?',
        'description' => 'Aggregate score explained: how two-legged ties are decided by combining the scores of both matches.',
        'definition' => 'The aggregate score is the combined total of both matches in a two-legged tie (one home, one away). The team with the higher aggregate advances.',
        'detail' => 'For example, a team that loses 1–2 away but wins 3–0 at home wins 4–2 on aggregate. The 2026 World Cup knockout stage is single-leg, so aggregate scores mainly apply to competitions like the Champions League.',
    ],

    'what-is-var' => [
        'term' => 'VAR',
        'title' => 'What is VAR in football?',
        'question' => 'What is VAR?',
        'description' => 'VAR (Video Assistant Referee) explained: what decisions it can review and how it works.',
        'definition' => 'VAR, the Video Assistant Referee, is a team of officials who review the on-field referee\'s decisions using video replays.',
        'detail' => 'VAR can only review four match-changing situations: goals, penalty decisions, direct red cards and mistaken identity. The final decision always rests with the on-field referee, who may consult a pitchside monitor.',
    ],

    'what-is-a-hat-trick' => [
        'term' => 'Hat-trick',
        'title' => 'What is a hat-trick in football?',
        'question' => 'What is a hat-trick?',
        'description' => 'A hat-trick explained: three goals by one player in a match, plus what a "brace" and a "perfect hat-trick" mean.',
        'definition' => 'A hat-trick is three goals scored by the same player in a single match.',
        'detail' => 'Two goals by one player is called a "brace". A "perfect hat-trick" is one goal with the left foot, one with the right and one with the head. Follow the race for the Golden Boot on LiveGoal\'s top scorers.',
        'link' => ['label' => 'See top scorers', 'to' => '/scorers'],
    ],

    'what-is-the-group-stage' => [
        'term' => 'Group stage',
        'title' => 'What is the group stage in football?',
        'question' => 'What is the group stage?',
        'description' => 'The group stage explained: the opening round-robin phase of a tournament and how teams advance from it.',
        'definition' => 'The group stage is the opening phase of a tournament where teams in the same group each play one another, and the top finishers advance to the knockout stage.',
        'detail' => 'At the 2026 World Cup there are 12 groups of four. Each team plays three group matches; the top two in every group, plus the eight best third-placed teams, reach the knockout stage.',
        'link' => ['label' => 'How the World Cup groups work', 'to' => '/guides/world-cup-2026-groups-and-qualification'],
    ],
];
