<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <a href="{{ route('mpesa_config.index') }}" class="btn btn-secondary btn-sm {{ request()->routeIs('mpesa_config.index') ? 'active' : '' }}">
            View All Configurations
        </a>
        <a href="{{ route('mpesa_config.create') }}" class="btn btn-success btn-sm {{ request()->routeIs('mpesa_config.create') ? 'active' : '' }}">
            Add New Configuration
        </a>
    </div>
    @if(request()->routeIs('mpesa_config.edit'))
        <div>
            <span class="text-muted">Editing Configuration</span>
        </div>
    @endif
</div>