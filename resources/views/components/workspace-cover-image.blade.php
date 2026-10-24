@props(['src', 'alt', 'workspaceId', 'positionX' => 50, 'positionY' => 50])
<img {{ $attributes }} src="{{ $src }}" alt="{{ $alt }}"
    style="object-position: {{ $positionX }}% {{ $positionY }}%">
