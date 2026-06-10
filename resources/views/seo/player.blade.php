@php
    /** @var array<string, mixed> $player */
    $name = data_get($player, 'name');
    $position = data_get($player, 'position');
    $nationality = data_get($player, 'nationality');
    $team = data_get($player, 'team.name');
    $shirt = data_get($player, 'shirtNumber');

    $summary = $name;
    if ($position) {
        $summary .= " is a {$position}";
    }
    if ($team) {
        $summary .= " for {$team}";
    }
    if ($nationality) {
        $summary .= ", and plays internationally for {$nationality}";
    }
    $summary .= '. Profile, team and career info on LiveGoal.';
@endphp
<article data-seo-prerender>
    <h1>{{ $name }}</h1>
    <p>{{ $summary }}</p>
    <dl>
        @if ($position)<dt>Position</dt><dd>{{ $position }}</dd>@endif
        @if ($team)<dt>Team</dt><dd>{{ $team }}</dd>@endif
        @if ($nationality)<dt>Nationality</dt><dd>{{ $nationality }}</dd>@endif
        @if ($shirt)<dt>Shirt number</dt><dd>{{ $shirt }}</dd>@endif
    </dl>

    @isset($updatedAt)
        <p>Last updated {{ \Illuminate\Support\Carbon::parse($updatedAt)->format('H:i, j M Y') }} UTC.</p>
    @endisset
</article>
