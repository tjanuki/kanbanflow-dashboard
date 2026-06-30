@php
    // Swatches already claimed by the *other* colours — the swatch doubles as the
    // task ⇄ project join key, so each must be unique.
    $takenSwatches = $this->colorRows
        ->reject(fn ($p) => $p->id === $editColorId)
        ->pluck('color')
        ->all();
@endphp

<div style="padding: 10px 16px; background-color: #f9fafb; border-top: 1px solid #eef0f2; border-bottom: 1px solid #eef0f2;">
    {{-- Label --}}
    <input
        type="text"
        wire:model="editColorName"
        wire:keydown.enter.prevent="saveColorRow"
        placeholder="Color name"
        data-testid="color-name-input"
        style="width: 100%; border: 1px solid #d1d5db; border-radius: 4px; padding: 7px 8px; font-size: 13px; color: #1f2937; background-color: #ffffff; margin-bottom: 8px;"
        autofocus
    />

    {{-- Swatch picker --}}
    <div class="flex flex-wrap gap-1.5" style="margin-bottom: 8px;">
        @foreach (\App\Support\Palette::all() as $key => $hex)
            @php
                $isSelected = $editColorSwatch === $key;
                $isTaken = in_array($key, $takenSwatches, true);
            @endphp
            <button
                type="button"
                @if (! $isTaken) wire:click="$set('editColorSwatch', '{{ $key }}')" @endif
                @disabled($isTaken)
                title="{{ ucfirst($key) }}{{ $isTaken ? ' (in use)' : '' }}"
                style="
                    width: 24px; height: 24px; border-radius: 9999px; flex: none;
                    background-color: {{ $hex['dot'] }};
                    border: 2px solid {{ $isSelected ? '#1f2937' : 'transparent' }};
                    box-shadow: 0 0 0 1px rgba(0,0,0,0.10);
                    opacity: {{ $isTaken && ! $isSelected ? '0.30' : '1' }};
                    cursor: {{ $isTaken ? 'not-allowed' : 'pointer' }};
                "
            ></button>
        @endforeach
    </div>

    @if ($colorEditorError)
        <p data-testid="color-editor-error" style="font-size: 12px; color: #dc2626; margin-bottom: 8px;">{{ $colorEditorError }}</p>
    @endif

    {{-- Actions --}}
    <div class="flex justify-end gap-2">
        <button type="button" wire:click="cancelColorRow" style="font-size: 13px; color: #6b7280; padding: 6px 12px;" class="hover:text-gray-800">Cancel</button>
        <button
            type="button"
            wire:click="saveColorRow"
            data-testid="color-save"
            style="font-size: 13px; font-weight: 600; color: #ffffff; background-color: #4ac26b; border-radius: 4px; padding: 6px 16px;"
            class="hover:opacity-90"
        >Save</button>
    </div>
</div>
