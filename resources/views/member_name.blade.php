<div class="d-flex justify-content-end align-items-center py-3">
    @if(Auth::check())
        <div class="dropdown">
            <button class="btn fw-semibold px-3 py-2 dropdown-toggle" 
                    type="button" 
                    id="userMenu" 
                    data-bs-toggle="dropdown" 
                    aria-expanded="false"
                    style="font-size: 0.95rem; min-width: 180px; text-align: left; background-color: #f9f9f9; border: 1px solid #ddd; border-radius: 0;">
                {{ Auth::user()->member_name }}
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow-sm" aria-labelledby="userMenu" style="border-radius: 0;">
                <li>
                    <a class="dropdown-item text-secondary" href="{{ url('/profile/password') }}">
                        Change Password
                    </a>
                </li>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <a class="dropdown-item text-danger" href="{{ url('/logout') }}"
                       onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                        Logout
                    </a>
                </li>
            </ul>
            <form id="logout-form" action="{{ url('/logout') }}" method="POST" style="display: none;">
                @csrf
            </form>
        </div>
    @endif
</div>

<div class="separator-breadcrumb border-top mb-3"></div>