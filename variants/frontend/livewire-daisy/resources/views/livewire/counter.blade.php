<div class="flex items-center gap-3 rounded-box border border-base-300 bg-base-100 px-4 py-3">
    <button type="button" wire:click="decrement" class="btn btn-sm btn-outline">−</button>
    <span class="min-w-8 text-center text-lg font-semibold tabular-nums">{{ $count }}</span>
    <button type="button" wire:click="increment" class="btn btn-sm btn-primary">+</button>
    <span class="badge badge-ghost text-xs">Livewire + DaisyUI</span>
</div>
