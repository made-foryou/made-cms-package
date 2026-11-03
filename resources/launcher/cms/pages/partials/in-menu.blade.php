{{-- The switch for the menu flag. The hidden field sends "0" when the switch is off. --}}
<div class="schakelrij">
    <span class="schakel-tekst">
        <label class="schakel-label" for="made-cms-in_menu">{{ $label }}</label>
        <span class="veld-hint" id="made-cms-in_menu-hint">{{ $hint }}</span>
    </span>
    <input type="hidden" name="in_menu" value="0">
    <input class="schakel" type="checkbox" role="switch" id="made-cms-in_menu" name="in_menu" value="1"{!! (bool) old('in_menu', $checked) ? ' checked' : '' !!} aria-describedby="made-cms-in_menu-hint">
</div>
