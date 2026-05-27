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
        <div class="sidebar-compact-switch ms-auto"><span></span></div>
    </div>
    <div class="scroll-nav ps ps--active-y" data-perfect-scrollbar="data-perfect-scrollbar" data-suppress-scroll-x="true">
        <div class="side-nav">
            <div class="main-menu">
                <ul class="metismenu" id="menu">


                    <li class="Ul_li--hover">
                    <li class="Ul_li--hover">
                        <a href="{{ url('/register') }}">

                            <span class=" text-muted">Register (Non Members)</span>
                        </a>
                    </li>
                    <li class="item-name">
                        <a href="{{ url('/loans/types/list') }}">
                            <span class="text-muted">Loans & Loan Calculator</span>
                        </a>
                    </li>
                    <li class="item-name">
                        <a href="{{ url('/login') }}">
                            <span class="text-muted">Login (Members Only)</span>
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </div>

    <div class="support-contact p-2"
    style="position: absolute; bottom: 0; left: 0; width: 100%; background: linear-gradient(to right, rgba(255, 255, 255, 0.94), rgba(250, 248, 255, 0.94)); 
        font-size: 13px; border-top: 1px solid rgba(102, 51, 153, 0.18);">
    <div style="color: rgba(102, 51, 153, 1); line-height: 1.45;">
        <div style="font-size: 11px; font-weight: 800; letter-spacing: .06em; text-transform: uppercase; color: rgba(102, 51, 153, 0.68);">
            Portal support
        </div>

        <div style="font-size: 13px; color: #4b3f5c; font-weight: 600;">
            Managed on <strong style="color: rgba(102, 51, 153, 1);">iSacco</strong>
            by
            <a href="https://shahi.co.ke"
               target="_blank"
               rel="noopener"
               style="text-decoration: none; color: rgba(102, 51, 153, 1); font-weight: 800;">
                Shahi Services
            </a>
        </div>

        <a href="https://shahi.co.ke"
           target="_blank"
           rel="noopener"
           style="display: inline-block; margin-top: 3px; text-decoration: none; color: rgba(102, 51, 153, 0.85); font-size: 12px; font-weight: 700;">
            shahi.co.ke
        </a>
    </div>
</div>
</div>