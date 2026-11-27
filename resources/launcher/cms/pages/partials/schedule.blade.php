{{-- The switch "Publiceren op een moment" of the basics with its moment, shown by css while the switch is on. "Toch nu
     publiceren" submits a form of its own after the form of the basics (#made-cms-publish-now): forms never nest. --}}
@php
    $described = trim(($schedule['zone'] !== null ? 'made-cms-publish_at-hint' : '').($errors->has('publish_at') ? ' made-cms-publish_at-fout' : ''));
@endphp
<div class="planning-blok">
    @include('made-cms-package-launcher::cms.pages.partials.switch', [
        'name' => 'scheduled',
        'label' => $t('pages.show.schedule.label'),
        'hint' => $schedule['hint'],
        'checked' => $schedule['on'],
    ])
    <div class="planning">
        <div @class(['veld', 'fout' => $errors->has('publish_at')])>
            <label for="made-cms-publish_at">{{ $t('pages.show.schedule.moment') }}</label>
            <input id="made-cms-publish_at" type="datetime-local" name="publish_at" step="60" value="{{ old('publish_at', $schedule['value']) }}"@if ($described !== '') aria-describedby="{{ $described }}"@endif @error('publish_at') aria-invalid="true" @enderror>
            @error('publish_at')
                <p class="veld-fout" id="made-cms-publish_at-fout">{{ $message }}</p>
            @enderror
        </div>
        @if ($schedule['on'])
            <button type="submit" class="knop stil" form="made-cms-publish-now">{{ $t('pages.show.schedule.now') }}</button>
        @endif
        @if ($schedule['zone'] !== null)
            <p class="veld-hint" id="made-cms-publish_at-hint">{{ $schedule['zone'] }}</p>
        @endif
    </div>
    @error('publish')
        <p class="veld-fout" role="alert">{{ $message }}</p>
    @enderror
</div>
