@if (config('sacco.transport_sacco') === 'Y')
    <li class="Ul_li--hover">
        <a class="has-arrow" href="#">
            <i class="i-Car-Items text-20 me-2" style="color: #663399;"></i>
            <span class="item-name text-15 text-muted">Matatu SACCO</span>
        </a>
        <ul class="mm-collapse">
            <li class="item-name">
                <a href="{{ url('/transport/fleet') }}">
                    <span class="text-muted">Fleet Management</span>
                </a>
            </li>
            <li class="item-name">
                <a href="{{ url('/transport/operators') }}">
                    <span class="text-muted">Drivers & Conductors</span>
                </a>
            </li>
            <li class="item-name">
                <a href="{{ url('/transport/collections') }}">
                    <span class="text-muted">Daily Collections</span>
                </a>
            </li>
            <li class="item-name">
                <a href="{{ url('/transport/routes') }}">
                    <span class="text-muted">Routes & Timetables</span>
                </a>
            </li>
            <li class="item-name">
                <a href="{{ url('/transport/penalties') }}">
                    <span class="text-muted">Fines & Infractions</span>
                </a>
            </li>
            <li class="item-name">
                <a href="{{ url('/transport/targets') }}">
                    <span class="text-muted">Targets & Commissions</span>
                </a>
            </li>
            <li class="item-name">
                <a href="{{ url('/transport/maintenance') }}">
                    <span class="text-muted">Fuel & Maintenance Logs</span>
                </a>
            </li>
        </ul>
    </li>
@endif