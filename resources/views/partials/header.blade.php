<header class="main-header bg-white d-flex justify-content-between p-2 d-block d-md-none">
  <div class="header-toggle">
    <div class="menu-toggle mobile-menu-icon">
      <div></div> 
      <div></div>
      <div></div>
    </div>
  </div>
  <div class="header-part-right">
    <a href="{{ url('/') }}" style="text-decoration: none;">
      <span style="margin-right: 10px; font-size: 33px; color: rebeccapurple; font-weight: 900; font-family: 'Montserrat', sans-serif; background: linear-gradient(to right, rebeccapurple, indigo); -webkit-background-clip: text; color: transparent; text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.2);">  @php
                $nameParts = explode(' ', $defaultCompanyName);
                $first = $nameParts[0] ?? '';
                $second = $nameParts[1] ?? '';
                @endphp

                <div class="logo-container">
                    <span class="adom">{{ $first }}</span>
                    <span class="sacco">{{ $second }}</span>
                </div> </span>
    </a>
  </div>
</header>
