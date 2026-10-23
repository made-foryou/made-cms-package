<fieldset class="cijfers" data-cijfers="{{ $field }}" aria-describedby="made-cms-{{ $field }}-hint made-cms-{{ $field }}-fout">
    <legend>{{ $legend }}</legend>
    <div class="cijfervakken">
        @for ($i = 1; $i <= $length; $i++)
            <input class="cijfer" type="text" inputmode="numeric" pattern="[0-9]*" maxlength="{{ $i === 1 ? $length : 1 }}" autocomplete="{{ $i === 1 ? 'one-time-code' : 'off' }}" aria-label="{{ $t('digit', ['n' => $i, 'total' => $length]) }}">
            @if ($i === intdiv($length, 2))
                <span class="cijfer-slash" aria-hidden="true"></span>
            @endif
        @endfor
    </div>
    <p id="made-cms-{{ $field }}-hint" class="hint">{{ $hint }}</p>
    <p id="made-cms-{{ $field }}-fout" class="melding" data-fout="{{ $field }}" hidden></p>
</fieldset>
