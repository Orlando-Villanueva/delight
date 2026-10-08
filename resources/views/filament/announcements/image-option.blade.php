<span>
    <img src="{{ asset($path) }}" alt="" width="160" loading="lazy">
    <br>
    {{ $path }}
    <br>
    {{ $used ? 'Used by an announcement' : 'Unused by announcements' }}
</span>
