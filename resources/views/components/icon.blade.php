@props(['name'])
<svg {{ $attributes->merge(['width' => 19, 'height' => 19, 'viewBox' => '0 0 24 24', 'fill' => 'none', 'stroke' => 'currentColor', 'stroke-width' => 1.6, 'stroke-linecap' => 'round', 'stroke-linejoin' => 'round']) }}
    aria-hidden="true">
    @switch($name)
        @case('dashboard')
            <rect x="3" y="3" width="7" height="7" rx="1.5" />
            <rect x="14" y="3" width="7" height="7" rx="1.5" />
            <rect x="3" y="14" width="7" height="7" rx="1.5" />
            <rect x="14" y="14" width="7" height="7" rx="1.5" />
        @break

        @case('pencil')
            <path d="m16 3 5 5L9 20l-6 1 1-6Z" />
            <path d="m14 5 5 5" />
        @break

        @case('finance')
            <rect x="3" y="4" width="18" height="16" rx="4" />
            <path d="M3 10h18M8 15h3" />
        @break

        @case('goals')
            <circle cx="12" cy="12" r="9" />
            <circle cx="12" cy="12" r="5" />
            <circle cx="12" cy="12" r="1" />
        @break

        @case('notes')
            <path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9Z" />
            <path d="M14 3v6h6M8 13h8M8 17h5" />
        @break

        @case('folder')
            <path d="M3 7a2 2 0 0 1 2-2h5l2 3h7a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2Z" />
        @break

        @case('routine')
            <rect x="3" y="5" width="18" height="16" rx="2" />
            <path d="M16 3v4M8 3v4M3 11h18m-14 5 2 2 4-4" />
        @break

        @case('members')
            <circle cx="9" cy="8" r="3" />
            <path d="M3 20v-2a6 6 0 0 1 12 0v2M16 5a3 3 0 0 1 0 6M21 20v-2a6 6 0 0 0-4-5" />
        @break

        @case('settings')
            <path d="m9 3-1 3-3 1 1 3-2 2 2 2-1 3 3 1 1 3h6l1-3 3-1-1-3 2-2-2-2 1-3-3-1-1-3Z" />
            <circle cx="12" cy="12" r="3" />
        @break
    @endswitch
</svg>
