{{-- A text field of a form: $name, $label, $value, optional $hint, $placeholder, $required and $attributes. --}}
@php
    $id = 'made-cms-'.$name;
    $described = trim(($hint ?? null) !== null ? $id.'-hint' : '').($errors->has($name) ? ' '.$id.'-fout' : '');
@endphp
<div @class(['veld', 'fout' => $errors->has($name)]) {!! $wrapper ?? '' !!}>
    <label for="{{ $id }}">{{ $label }}</label>
    <input id="{{ $id }}" type="text" name="{{ $name }}" value="{{ old($name, $value) }}" autocomplete="off"@if ($placeholder ?? null) placeholder="{{ $placeholder }}"@endif @if ($required ?? false) required @endif @if (trim($described) !== '') aria-describedby="{{ trim($described) }}"@endif @error($name) aria-invalid="true" @enderror {!! $attributes ?? '' !!}>
    @if (($hint ?? null) !== null)
        <p class="veld-hint" id="{{ $id }}-hint">{{ $hint }}</p>
    @endif
    @error($name)
        <p class="veld-fout" id="{{ $id }}-fout">{{ $message }}</p>
    @enderror
</div>
