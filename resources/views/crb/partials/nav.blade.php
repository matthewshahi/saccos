<div class="mb-4">
    <div class="btn-group flex-wrap" role="group" aria-label="CRB report navigation">
        <a href="{{ route('reports.crb.index') }}"
           class="btn btn-sm {{ request()->routeIs('reports.crb.index') ? 'btn-primary' : 'btn-outline-primary' }}">
            CRB Dashboard
        </a>
        <a href="{{ route('reports.crb.history') }}"
           class="btn btn-sm {{ request()->routeIs('reports.crb.history') || request()->routeIs('reports.crb.show') ? 'btn-primary' : 'btn-outline-primary' }}">
            Report History
        </a>
        @if (Route::has('admin.defaults'))
            <a href="{{ route('admin.defaults') }}" class="btn btn-sm btn-outline-secondary">
                CRB Defaults
            </a>
        @else
            <a href="{{ url('/admin/defaults') }}" class="btn btn-sm btn-outline-secondary">
                CRB Defaults
            </a>
        @endif
    </div>
</div>
