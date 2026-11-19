{{-- A switch of a form: $name, $label, $hint, $checked. The hidden field sends "0" when the switch is off. --}}
@php($id = 'made-cms-'.$name)
<div class="schakelrij">
    <span class="schakel-tekst">
        <label class="schakel-label" for="{{ $id }}">{{ $label }}</label>
        <span class="veld-hint" id="{{ $id }}-hint">{{ $hint }}</span>
    </span>
    <input type="hidden" name="{{ $name }}" value="0">
    <input class="schakel" type="checkbox" role="switch" id="{{ $id }}" name="{{ $name }}" value="1"{!! (bool) old($name, $checked) ? ' checked' : '' !!} aria-describedby="{{ $id }}-hint">
</div>
