<div class="sidebar-panel bg-white">
    <div class="gull-brand pe-3 text-center mt-4 mb-2 d-flex justify-content-center align-items-center">
    <a href="{{ url('/') }}" style="text-decoration: none;">
            @php
                $currentDomain = parse_url(url('/'), PHP_URL_HOST); // Extract domain name
                $logoPath = url('/image/' . $currentDomain . '.jpg'); // Construct logo URL
               
            @endphp
            @if (@getimagesize($logoPath)) <!-- Check if the logo exists -->
                <img src="{{ $logoPath }}" alt="Logo" style="height: 50px;">
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
                        <a class="has-arrow" href="#">
                            <i class="i-Library text-20 me-2" style="color: #663399;"></i>
                            <span class="item-name text-15 text-muted">Members</span>
                        </a>
                        <ul class="mm-collapse">
                            <li class="item-name">
                                <a href="{{ url('/members/list') }}">
                                    <span class="text-muted">All members</span>
                                </a>
                            </li>
                            <li class="item-name">
                                <a href="{{ url('/members/active/y') }}">
                                    <span class="text-muted">Active members</span>
                                </a>
                            </li>
                            <li class="item-name">
                                <a href="{{ url('/members/active/n') }}">
                                    <span class="text-muted">Inactive Members</span>
                                </a>
                            </li>
                            <li class="item-name">
                                <a href="{{ url('/institutions/list') }}">
                                    <span class="text-muted">Institutions</span>
                                </a>
                            </li>
                            <li class="item-name">
                                <a href="{{ url('/institutions/add') }}">
                                    <span class="text-muted">Add Institutions</span>
                                </a>
                            </li>
                            <li class="item-name">
                                <a href="{{ url('/members/add') }}">
                                    <span class="text-muted">Add New Member</span>
                                </a>
                            </li>
                            <li class="item-name">
                                <a href="{{ url('new_members/list') }}">
                                    <span class="text-muted">New Membership Requests</span>
                                </a>
                            </li>
 
                        </ul>
                    </li>
                    <li class="Ul_li--hover">
                        <a class="has-arrow" href="#">
                            <i class="i-Money-Bag text-20 me-2" style="color: #663399;"></i>
                            <span class="item-name text-15 text-muted">Shares/deposits</span>
                        </a>
                        <ul class="mm-collapse">
                            <li class="item-name">
                                <a href="{{ route('modify.member.shares') }}">
                                    <span class="text-muted">Add/reduce</span>
                                </a>
                            </li>
                            <li class="item-name">
                                <a href="{{ route('transfer.member.shares') }}">
                                    <span class="text-muted">Transfer between members</span>
                                </a>
                            </li>
                            <li class="item-name">
                                <a href="{{ route('proc.end.month.shares') }}">
                                    <span class="text-muted">End month proc.</span>
                                </a>
                            </li>
                        </ul>
                    </li>
                    <li class="Ul_li--hover">
                        <a class="has-arrow" href="#">
                            <i class="i-Business-Mens text-20 me-2" style="color: #663399;"></i>
                            <span class="item-name text-15 text-muted">Capital</span>
                        </a>
                        <ul class="mm-collapse">
                            <li class="item-name">
                                <a href="{{ route('modify.member.share.capital') }}">
                                    <span class="text-muted">Add/reduce </span>
                                </a>
                            </li>
                            <li class="item-name">
                                <a href="{{ route('transfer.member.capital.shares') }}">
                                    <span class="text-muted">Transfer between members</span>
                                </a>
                            </li>
                            <li class="item-name">
                                <a href="{{ route('transfer.share.to.capital.shares') }}">
                                    <span class="text-muted">Transfer from deposits capital</span>
                                </a>
                            </li>
                        </ul>
                    </li>
                    <li class="Ul_li--hover">
                        <a class="has-arrow" href="#">
                            <i class="i-Bank text-20 me-2" style="color: #663399;"></i>
                            <span class="item-name text-15 text-muted">FOSA</span>
                        </a>
                        <ul class="mm-collapse">
                            <li class="item-name">
                                <a href="{{ route('modify.member.fosas') }}">
                                    <span class="text-muted">Add/reduce</span>
                                </a>
                            </li>
                        </ul>
                    </li>
                    <li class="Ul_li--hover">
                        <a class="has-arrow" href="#">
                            <i class="i-Calendar-4 text-20 me-2" style="color: #663399;"></i>
                            <span class="item-name text-15 text-muted">End month proc.</span>
                        </a>
                        <ul class="mm-collapse">
                            <li class="item-name">
                                <a href="{{ route('list.contribution') }}">
                                    <span class="text-muted">Monthly contributions</span>
                                </a>
                            </li>
                            <li class="item-name">
                                <a href="{{ route('proc.end.month.shares') }}">
                                    <span class="text-muted">Shares</span>
                                </a>
                            </li>
                            <li class="item-name">
                                <a href="{{ route('proc.end.month.loans') }}">
                                    <span class="text-muted">Loans</span>
                                </a>
                            </li>
                        </ul>
                    </li>
                    <li class="Ul_li--hover">
                        <a class="has-arrow" href="#">
                            <i class="i-File-Clipboard-File--Text text-20 me-2" style="color: #663399;"></i>
                            <span class="item-name text-15 text-muted">Loans</span>
                        </a>
                        <ul class="mm-collapse">
                        <li class="item-name">
                                <a href="{{ url('/loans/batch') }}">
                                    <span class="text-muted">Batches</span>
                                </a>
                            </li>
                            <!-- <li class="item-name">
                                <a href="{{ url('/loans/issued') }}">
                                    <span class="text-muted">Loans issued</span>
                                </a>
                            </li> -->
                            <li class="item-name">
                                <a href="{{ url('/loans/apply') }}">
                                    <span class="text-muted">Apply for Loan (Self)</span>
                                </a>
                            </li>
                            <li class="item-name">
                                <a href="{{ url('/loans/types') }}">
                                    <span class="text-muted">Loan types</span>
                                </a>
                            </li>
                            <li class="item-name">
                                <a href="{{ url('/loans/categories') }}">
                                    <span class="text-muted">Loan categories</span>
                                </a>
                            </li>
                        </ul>
                    </li>
                    <li class="Ul_li--hover">
                        <a class="has-arrow" href="#">
                            <i class="i-Financial text-20 me-2" style="color: #663399;"></i>
                            <span class="item-name text-15 text-muted">Journals</span>
                        </a>
                        <ul class="mm-collapse">
                            <li class="item-name">
                                <a href="{{ url('/accounts/main') }}">
                                    <span class="text-muted">Main Accounts</span>
                                </a>
                            </li>
                        
                            <li class="item-name">
                                <a href="{{ url('/accounts/sub') }}">
                                    <span class="text-muted">Sub Accounts</span>
                                </a>
                            </li>
                            <li class="item-name">
                                <a href="{{ url('/admin/budget') }}">
                                    <span class="text-muted">Set Budget</span>
                                </a>
                            </li>
                            
                            <li class="item-name">
                                <a href="{{ url('/accounts/transfer') }}">
                                    <span class="text-muted">Transfer Ledgers</span>
                                </a>
                            </li>

                            <li class="item-name">
                                <a href="{{ url('/admin/end-of-year-processing') }}">
                                    <span class="text-muted">Close the year</span>
                                </a>
                            </li>


                        </ul>
                    </li>
                    <li class="Ul_li--hover">
                        <a class="has-arrow" href="#">
                            <i class="i-Administrator text-20 me-2" style="color: #663399;"></i>
                            <span class="item-name text-15 text-muted">Reports</span>
                        </a>
                        <ul class="mm-collapse">
                            <li class="item-name">
                                <a href="{{ url('/reports/members/status') }}">
                                    <span class="text-muted">Full Consolidated report</span>
                                </a>
                            </li>
                            <li class="Ul_li--hover">
                                <a class="has-arrow" href="#">
                                    <i class="i-Bar-Chart text-20 me-2" style="color: #663399;"></i>
                                    <span class="item-name text-15 text-muted">Loans</span>
                                </a>
                                <ul class="mm-collapse">
                                    <li class="item-name">
                                        <a href="{{url('/reports/loans/issued')}}">Loans Given</a>
                                    </li>
                                    <li class="item-name">
                                        <a href="{{url('/reports/loans/repayments')}}">Loan Repayments</a>
                                    </li>
                                    <!-- <li class="item-name">
                                        <a href="{{url('/reports/loans/repayments/data')}}">Loans Given</a>
                                    </li>
                                    <li class="item-name">
                                        <a href="{{url('/reports/loans/repayments/download')}}">Loans Given</a>
                                    </li> -->
                                    
                                </ul> 

                            </li>

                            <li class="item-name has-arrow">
                                <a href="#">
                                    <i class="i-Pie-Chart text-20 me-2" style="color: #663399;"></i>
                                    <span class="item-name text-15 text-muted">Final accounts</span>
                                </a>
                                <ul class="mm-collapse">
                                    <li class="item-name">
                                        <a href="{{ url('/reports/accounts/ledger') }}">
                                            <span class="text-muted">Ledgers</span>
                                        </a>
                                    </li>
                                    <li class="item-name">
                                        <a href="{{ url('/reports/accounts/trial-balance') }}">
                                            <span class="text-muted">Trial balance</span>
                                        </a>
                                    </li>
                                    <li class="item-name">
                                        <a href="{{ url('/reports/accounts/profit-loss') }}">
                                            <span class="text-muted">Profit and Loss </span>
                                        </a>
                                    </li>
                                    <li class="item-name">
                                        <a href="{{ url('/reports/accounts/balance-sheet') }}">
                                            <span class="text-muted">Balance sheet</span>
                                        </a>
                                    </li>
                                   
                                    <li class="item-name">
                                        <a href="{{ url('/reports/accounts/balance-sheet-horizontal') }}">
                                            <span class="text-muted">Balance sheet - V2</span>
                                        </a>
                                    </li>

                                    <li class="item-name">
                                        <a href="{{ url('/reports/accounts/accounts/alltimetrialbalance') }}">
                                            <span class="text-muted">Trial Balance - V3</span>
                                        </a>
                                    </li>

                                    <li class="item-name">
                                        <a href="{{ url('/reports/accounts/accounts/alltimetprofitandloss') }}">
                                            <span class="text-muted">Profit and Loss - V3</span>
                                        </a>
                                    </li>

                                    <li class="item-name">
                                        <a href="{{ url('/reports/accounts/accounts/alltimetbalancesheet') }}">
                                            <span class="text-muted">Balance sheet - V3</span>
                                        </a>
                                    </li>

                                </ul>
                            </li>
                            <li class="Ul_li--hover">
                                <a class="has-arrow" href="#">
                                    <i class="i-Bar-Chart text-20 me-2" style="color: #663399;"></i>
                                    <span class="item-name text-15 text-muted">SASRA</span>
                                </a>
                                <ul class="mm-collapse">
                                    <li class="item-name">
                                        <a href="{{url('/reports/sasra/outstandingloans/n/active')}}">OutStanding Loans - Inactive Members</a>
                                    </li>
                                    <li class="item-name">
                                        <a href="{{url('/reports/sasra/outstandingloans/y/active')}}">OutStanding Loans - Active Members</a>
                                    </li>
                                    <li class="item-name">
                                        <a href="{{url('/reports/sasra/outstandingloans/n')}}">Fully Paid Loans</a>
                                    </li>
                                    <li class="item-name">
                                        <a href="{{url('/reports/sasra/share')}}">Balances - Shares  </a>
                                    </li>
                                    <li class="item-name">
                                        <a href="{{url('/reports/profit_and_loss')}}">ROI</a>
                                    </li>
                                    <li class="item-name">
                                        <a href="{{url('/reports/sasra/loan_performance')}}">Loans Performance / RISK classifications</a>
                                    </li>
                                    <li class="item-name">
                                        <a href="{{url('/reports/sasra/loan_performance/1')}}">Loans Performance / RISK classifications / Officia;s</a>
                                    </li>
                                </ul>
                            </li>
                        </ul>
                    </li>
                    
                    <li class="Ul_li--hover">
                        <a class="has-arrow" href="{{url('/file-upload')}}">
                            <i class="i-Download text-20 me-2" style="color: #663399;"></i>
                            <span class="item-name text-15 text-muted">Downloads</span>
                        </a>
                    </li>
                    <li class="Ul_li--hover">
                        <a class="has-arrow" href="#">
                            <i class="i-Administrator text-20 me-2" style="color: #663399;"></i>
                            <span class="item-name text-15 text-muted">Sacco Admin</span>
                        </a>
                        <ul class="mm-collapse">
                            <li class="item-name has-arrow">
                                <a href="#">
                                    <span class="text-muted">Rights & Modules</span>
                                </a>
                                <ul class="mm-collapse">
                                    <li class="item-name">
                                        <a href="{{ url('/admin/access-rights') }}">
                                            <span class="text-muted">Modify access rights</span>
                                        </a>
                                    </li>
                                </ul>
                            </li>
                            <li class="item-name has-arrow">
                                <a href="{{ url('/admin/defaults') }}">
                                    <span class="text-muted">Sacco defaults</span>
                                </a>
                            </li>
                            <li class="item-name">
                                <a href="{{ url('/admin/periods') }}">
                                    <span class="text-muted">Accounting periods</span>
                                </a>
                            </li>
                            <li class="item-name">
                                <a href="{{ url('/admin/mpesa') }}">
                                    <span class="text-muted">MPESA configs</span>
                                </a>
                            </li>
                        </ul>
                    </li>
                    <li class="Ul_li--hover">
                        <a class="has-arrow" href="#">
                            <i class="i-Administrator text-20 me-2" style="color: #663399;"></i>
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
