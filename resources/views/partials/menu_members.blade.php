<div class="sidebar-panel bg-white">
    <div class="gull-brand pe-3 text-center mt-4 mb-2 d-flex justify-content-center align-items-center">
        <a href="{{ url('/') }}" style="text-decoration: none;">
            <span style="margin-left: 10px; font-size: 33px; color: rebeccapurple; font-weight: 900; font-family: 'Montserrat', sans-serif; background: linear-gradient(to right, rebeccapurple, indigo); -webkit-background-clip: text; color: transparent; text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.2);">iSacco</span>
        </a>
        <div class="sidebar-compact-switch ms-auto"><span></span></div>
    </div>
    <div class="scroll-nav ps ps--active-y" data-perfect-scrollbar="data-perfect-scrollbar" data-suppress-scroll-x="true">
        <div class="side-nav">
            <div class="main-menu">
                <ul class="metismenu" id="menu">
                    <li class="Ul_li--hover">
                        <a class="has-arrow" href="#">
                            <i class="i-Administrator text-20 me-2 text-muted"></i>
                            <span class="item-name text-15 text-muted">Reports</span>
                        </a>
                        <ul class="mm-collapse">
                            <li class="item-name">
                               
                                <a href="{{ url('members/statement/self') }}">
                                    <span class="text-muted">Statement Report</span>
                                </a>
                            </li>
                            <li class="item-name">
                                <a href="{{ url('members/status/self') }}">
                                    <span class="text-muted">Status Report</span>
                                </a>
                            </li>
                        </ul>
                    </li>
                    <li class="Ul_li--hover">
                        <a class="has-arrow" href="#">
                            <i class="i-File-Clipboard-File--Text text-20 me-2 text-muted"></i>
                            <span class="item-name text-15 text-muted">Loans</span>
                        </a>
                        <ul class="mm-collapse">
                            <li class="item-name">
                                <a href="{{ url('/loans/apply') }}">
                                    <span class="text-muted">Apply for Loan</span>
                                </a>
                            </li>
                            <li class="item-name">
                                <a href="{{ url('/loans/types/list') }}">
                                    <span class="text-muted">Loan Types</span>
                                </a>
                            </li>
                        </ul>
                    </li>
                    <li class="Ul_li--hover">
                        <a class="has-arrow" href="{{url('/downloads')}}">
                            <i class="i-Download text-20 me-2" style="color: #663399;"></i>
                            <span class="item-name text-15 text-muted">Downloads</span>
                        </a>
                    </li>
                    <li class="Ul_li--hover">
                               <li class="Ul_li--hover">
                        <a class="has-arrow" href="#">
                            <i class="i-Administrator text-20 me-2 text-muted"></i>
                            <span class="item-name text-15 text-muted">User Profile</span>
                        </a>
                        <ul class="mm-collapse">
                            
                            <li class="item-name">
                                <a href="{{ url('/profile/password') }}">
                                    <span class="text-muted">Change Password</span>
                                </a>
                            </li>
                            <li class="item-name">
                                <a href="{{ url('/logout') }}" onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                                    <span class="text-muted">Logout</span>
                                </a>
                                <form id="logout-form" action="{{ url('/logout') }}" method="POST" style="display: none;">
                                    @csrf
                                </form>
                            </li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</div>
