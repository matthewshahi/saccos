<div class="sidebar-panel bg-white">
    <div class="gull-brand pe-3 text-center mt-4 mb-2 d-flex justify-content-center align-items-center">
    <a href="{{ url('/') }}" style="text-decoration: none;">
            @php
                $currentDomain = parse_url(url('/'), PHP_URL_HOST); // Extract domain name
                $logoPath = url('/images/' . $currentDomain . '.jpg'); // Construct logo URL
                
            @endphp
            @if (@getimagesize($logoPath)) <!-- Check if the logo exists -->
                <img src="{{ $logoPath }}" alt="Logo" style="height: 50px;">
            @else
                <span style="margin-left: 10px; font-size: 33px; color: rebeccapurple; font-weight: 900; font-family: 'Montserrat', sans-serif; background: linear-gradient(to right, rebeccapurple, indigo); -webkit-background-clip: text; color: transparent; text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.2);">
                    {{ $defaultCompanyName }}
                </span>
            @endif
        </a>
    </div>
    <div class="scroll-nav ps ps--active-y" data-perfect-scrollbar="data-perfect-scrollbar" data-suppress-scroll-x="true">
        <div class="side-nav">
            <div class="main-menu">
                <ul class="metismenu" id="menu">

                    <!-- Dashboard Section -->
                    <li class="menu-section-title text-muted mt-3 mb-1">Dashboard</li>
                    <li class="Ul_li--hover">
                        <a href="{{ url('/') }}">
                            <i class="i-Dashboard text-20 me-2 text-muted"></i>
                            <span class="item-name text-15 text-muted">Home</span>
                        </a>
                    </li>

                    <!-- Reports Section -->
                    <li class="menu-section-title text-muted mt-3 mb-1">Reports</li>
                    
                    <li class="Ul_li--hover">
                        <a href="{{ url('members/status/self') }}">
                            <i class="i-Files text-20 me-2 text-muted"></i>
                            <span class="item-name text-15 text-muted">Status Report</span>
                        </a>
                    </li>
 
                    <li class="Ul_li--hover">
                        <a href="{{ url('/contributions/member-shares') }}">
                            <i class="i-Money-Bag text-20 me-2 text-muted"></i>
                            <span class="item-name text-15 text-muted">Member Shares</span>
                        </a>
                    </li>
                    <li class="Ul_li--hover">
                        <a href="{{ url('/contributions/member-capital') }}">
                            <i class="i-Money-Bag text-20 me-2 text-muted"></i>
                            <span class="item-name text-15 text-muted">Member Capital</span>
                        </a>
                    </li>
                    <li class="Ul_li--hover">
                        <a href="{{ url('/contributions/fosa') }}">
                            <i class="i-Bank text-20 me-2 text-muted"></i>
                            <span class="item-name text-15 text-muted">FOSA</span>
                        </a>
                    </li>
                    <li class="Ul_li--hover">
                        <a href="{{ url('/loans/taken') }}">
                            <i class="i-Money-2 text-20 me-2 text-muted"></i>
                            <span class="item-name text-15 text-muted">Outstanding Loans</span>
                        </a>
                    </li>

                    <!-- Loans Section -->
                    <li class="menu-section-title text-muted mt-3 mb-1">Loans</li>
                    <li class="Ul_li--hover">
                        <a href="{{ url('/loans/apply') }}">
                            <i class="i-File-Clipboard-File--Text text-20 me-2 text-muted"></i>
                            <span class="item-name text-15 text-muted">Apply for Loan</span>
                        </a>
                    </li>
                    <li class="Ul_li--hover">
                        <a href="{{ url('/loans/types/list') }}">
                            <i class="i-File-Horizontal-Text text-20 me-2 text-muted"></i>
                            <span class="item-name text-15 text-muted">Loan Types</span>
                        </a>
                    </li>
                    

                    <!-- Resources Section -->
                    <li class="menu-section-title text-muted mt-3 mb-1">Resources</li>
                    <li class="Ul_li--hover">
                        <a href="{{ url('/downloads') }}">
                            <i class="i-Download text-20 me-2 text-muted"></i>
                            <span class="item-name text-15 text-muted">Downloads</span>
                        </a>
                    </li>

                    <!-- User Profile Section -->
                    <li class="menu-section-title text-muted mt-3 mb-1">User Profile</li>
                    <li class="Ul_li--hover">
                        <a href="{{ url('/profile/password') }}">
                            <i class="i-Lock text-20 me-2 text-muted"></i>
                            <span class="item-name text-15 text-muted">Change Password</span>
                        </a>
                    </li>
                    <li class="Ul_li--hover">
                        <a href="{{ url('/logout') }}" onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                            <i class="i-Arrow-Back text-20 me-2 text-muted"></i>
                            <span class="item-name text-15 text-muted">Logout</span>
                        </a>
                        <form id="logout-form" action="{{ url('/logout') }}" method="POST" style="display: none;">
                            @csrf
                        </form>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</div>