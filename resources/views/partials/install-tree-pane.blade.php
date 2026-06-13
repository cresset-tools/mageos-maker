{{-- The install-tree pane body: type summary, filter, and the package tree.
     Rendered for the server first paint and returned by POST /api/build so the
     client can swap #install-tree-pane wholesale. --}}
@if ($tree['missing'])
    <p class="warn">
        @if ($tree['fallbackVersion'])
            No baked graph for {{ $tree['version'] }} yet — showing {{ $tree['fallbackVersion'] }}.
        @else
            No baked graph available. Run <code>php artisan mageos:catalog:update</code>.
        @endif
    </p>
@endif
@if ($tree['count'] === 0 && ! $tree['missing'])
    <p class="tree-empty">No packages — nothing to show.</p>
@else
    <input type="text" id="install-tree-filter" placeholder="Filter packages…" autocomplete="off"
        class="input tree-filter" oninput="filterInstallTree(this.value)">
    <div class="install-tree-types">
        @foreach ($tree['byType'] as $type => $n)
            <span>{{ $type }}: {{ $n }}</span>
        @endforeach
        <span class="tree-actions">
            <a href="#" onclick="installTreeToggleAll(true);return false;">expand all</a> ·
            <a href="#" onclick="installTreeToggleAll(false);return false;">collapse</a>
        </span>
    </div>
    <div id="install-tree-root" class="install-tree-root">
        @include('partials.install-tree-node', ['nodes' => $tree['tree'], 'depth' => 0])
    </div>
@endif
