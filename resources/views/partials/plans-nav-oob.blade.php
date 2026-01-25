{{-- OOB swap for Reading Plans navigation links --}}
{{-- This partial is appended to HTMX responses to update navigation state --}}

<li id="desktop-plans-link" hx-swap-oob="true">
    <x-navigation.nav-link :url="$smartPlansUrl" label="Reading Plans" variant="sidebar">
        <x-slot:icon>
            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2M9 5a2 2 0 0 0 2 2h2a2 2 0 0 0 2-2M9 5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2m-6 9 2 2 4-4" />
        </x-slot:icon>
    </x-navigation.nav-link>
</li>

<div id="mobile-plans-link" hx-swap-oob="true" class="contents">
    <x-navigation.nav-link :url="$smartPlansUrl" label="Plans" variant="mobile">
        <x-slot:icon>
            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2M9 5a2 2 0 0 0 2 2h2a2 2 0 0 0 2-2M9 5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2m-6 9 2 2 4-4" />
        </x-slot:icon>
    </x-navigation.nav-link>
</div>
