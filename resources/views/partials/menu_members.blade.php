<div class="sidebar-panel bg-white">
    <div class="gull-brand pe-3 text-center mt-4 mb-2 d-flex justify-content-center align-items-center">
    <a href="{{ url('/') }}" style="text-decoration: none;">
        @php
            // Extract the domain name
            $currentDomain = parse_url(url('/'), PHP_URL_HOST);

            // Check if it's localhost or 127.0.0.1
            if ($currentDomain === '127.0.0.1' || $currentDomain === 'localhost') {
                $currentDomain = 'default'; // Use 'default' as a placeholder
            }

            // Construct logo paths
            $domainLogoPath = '/image/' . $currentDomain . '.jpg'; // Domain-specific logo
            $defaultLogoPath = '/image/logo.jpg'; // Default logo
        @endphp

        @if (file_exists(public_path($domainLogoPath))) <!-- Check if the domain-specific logo exists -->
            <img src="{{ asset($domainLogoPath) }}" alt="Logo" style="height: 50px;">
        @else
            <span style="margin-left: 10px; font-size: 33px; color: rebeccapurple; font-weight: 900; font-family: 'Montserrat', sans-serif; background: linear-gradient(to right, rebeccapurple, indigo); -webkit-background-clip: text; color: transparent; text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.2);">
                @php
                $nameParts = explode(' ', $defaultCompanyName);
                $first = $nameParts[0] ?? '';
                $second = $nameParts[1] ?? '';
                @endphp

                <div class="logo-container">
                    <span class="adom">{{ $first }}</span>
                    <span class="sacco">{{ $second }}</span>
                </div>
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

                    <li class="menu-section-title text-muted mt-3 mb-1">Junior Accounts</li>
                     
                    <li class="Ul_li--hover">
                        <a href="{{ url('/members/juniors/create') }}">
                            <i class="i-Files text-20 me-2 text-muted"></i>
                            <span class="item-name text-15 text-muted">Add Junior Account</span>
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
                            <span class="item-name text-15 text-muted">Member Deposits / Contributions</span>
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
    <div class="support-contact text-white p-2 d-flex justify-content-between align-items-center" style="position: absolute; bottom: 0; left: 0; width: 100%; background: linear-gradient(to right, rgba(102, 51, 153, 0.8), rgba(75, 0, 130, 0.8)); 
            font-size: 14px; border-top: 1px solid rgba(255, 255, 255, 0.1);">
    <div style="font-size: 13px;">
        <strong>ERP provided by:</strong> <br>Shahi Services, +254722400737
    </div>
     
</div>
</div>