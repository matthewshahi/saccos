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
                {{ $defaultCompanyName }}
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
                                    <span class="text-muted">Available Loans</span>
                                </a>
                            </li>   
                            <li class="item-name">
                                <a href="{{ url('/') }}">
                                    <span class="text-muted">Login (Members Only)</span>
                                </a>
                            </li>              
                </ul>
            </div>
        </div>
    </div>
    
    
</div>
