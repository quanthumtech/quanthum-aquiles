<div class="flex items-center gap-3 rounded-md border border-gray-200 px-4 py-3" x-data>
    <button
        type="button"
        wire:click="decrement"
        class="rounded border border-gray-300 px-3 py-1 text-sm font-medium hover:bg-gray-50"
    >
        −
    </button>
    <span class="min-w-8 text-center text-lg font-semibold tabular-nums">{{ $count }}</span>
    <button
        type="button"
        wire:click="increment"
        class="rounded border border-gray-300 px-3 py-1 text-sm font-medium hover:bg-gray-50"
    >
        +
    </button>
    <span class="text-xs text-gray-500">Livewire + Alpine, sem kit de componentes (TALL puro)</span>
</div>
