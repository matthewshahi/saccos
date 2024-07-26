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
                            <i class="i-Library text-20 me-2 text-muted"></i>
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
                        </ul>
                    </li>

                    <li class="Ul_li--hover">
                        <a class="has-arrow" href="#">
                            <i class="i-Money-Bag text-20 me-2 text-muted"></i>
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
                            <i class="i-Business-Mens text-20 me-2 text-muted"></i>
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
                            <i class="i-Bank text-20 me-2 text-muted"></i>
                            <span class="item-name text-15 text-muted">FOSA</span>
                        </a>
                        <ul class="mm-collapse">
                            <li class="item-name">
                                <a href="{{ route('modify.member.fosas') }}">
                                    <span class="text-muted">Add/reduce</span>
                                </a>
                            </li>
                            <!-- <li class="item-name">
                                <a href="{{ route('transfer.member.fosa') }}">
                                    <span class="text-muted">Transfer between members</span>
                                </a>
                            </li> -->
                            <!-- <li class="item-name">
                                <a href="{{ route('proc.end.month.fosa') }}">
                                    <span class="text-muted">End month proc.</span>
                                </a>
                            </li> -->
                        </ul>
                    </li>
                    <li class="Ul_li--hover">
                        <a class="has-arrow" href="#">
                            <i class="i-Calendar-4 text-20 me-2 text-muted"></i>
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
                                    <span class="text-muted">End month processing - Shares</span>
                                </a>
                            </li>
                            <!-- <li class="item-name">
                                <a href="{{ route('proc.end.month.fosa') }}">
                                    <span class="text-muted">End month processing - FOSA</span>
                                </a>
                            </li> -->
                            <!-- <li class="item-name">
                                <a href="{{ route('proc.end.month.loans') }}">
                                    <span class="text-muted">End month processing - Loans</span>
                                </a>
                            </li> -->
                        </ul>
                    </li>

                    <li class="Ul_li--hover">
                        <a class="has-arrow" href="#">
                            <i class="i-File-Clipboard-File--Text text-20 me-2 text-muted"></i>
                            <span class="item-name text-15 text-muted">Loans</span>
                        </a>
                        <ul class="mm-collapse">
                            <li class="item-name">
                                <a href="{{ url('/loans/issued') }}">
                                    <span class="text-muted">Loans issued</span>
                                </a>
                            </li>
                            <!-- <li class="item-name">
                                <a href="{{ url('/loans/batch') }}">
                                    <span class="text-muted">Loan batches</span>
                                </a>
                            </li> -->
                            <li class="item-name">
                                <a href="{{ url('/loans/apply') }}">
                                    <span class="text-muted">Apply for Loan (Self)</span>
                                </a>
                            </li>
                            <!-- <li class="item-name">
                                <a href="{{ url('/loans/end-month') }}">
                                    <span class="text-muted">End month processing</span>
                                </a>
                            </li>
                            <li class="item-name">
                                <a href="{{ url('/loans/un-finished') }}">
                                    <span class="text-muted">Reduce/Increase loan</span>
                                </a>
                            </li>
                            <li class="item-name">
                                <a href="{{ url('/loans/shares-to-loans') }}">
                                    <span class="text-muted">Clear Loan using shares</span>
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
                            <li class="item-name">
                                <a href="{{ url('/loans/calculator') }}">
                                    <span class="text-muted">Loan Calculator</span>
                                </a>
                            </li> -->
                            <!-- <li class="item-name has-arrow">
                                <a href="#">
                                    <span class="text-muted">Guarantors</span>
                                </a>
                                <ul class="mm-collapse">
                                    <li class="item-name">
                                        <a href="{{ url('/guarantors/deduction') }}">
                                            <span class="text-muted">Recover from guarantors shares</span>
                                        </a>
                                    </li>
                                    <li class="item-name">
                                        <a href="{{ url('/guarantors/reset') }}">
                                            <span class="text-muted">Reprocess all guarantors</span>
                                        </a>
                                    </li>
                                </ul>
                            </li> -->
                        </ul>
                    </li>
                    <li class="Ul_li--hover">
                        <a class="has-arrow" href="#">
                            <i class="i-Administrator text-20 me-2 text-muted"></i>
                            <span class="item-name text-15 text-muted">Reports</span>
                        </a>
                        <ul class="mm-collapse">
                            <li class="item-name">
                                <a href="{{ url('/reports/members/status') }}">
                                    <span class="text-muted">Full Consolidated report</span>
                                </a>
                            </li>
                            <!-- <li class="item-name has-arrow">
                                <a href="#">
                                    <span class="text-muted">Shares/FOSA/Share capital</span>
                                </a>
                                <ul class="mm-collapse">
                                    <li class="item-name">
                                        <a href="{{ url('/reports/shares/contribution') }}">
                                            <span class="text-muted">Share contributions Report</span>
                                        </a>
                                    </li>
                                    <li class="item-name">
                                        <a href="{{ url('/reports/shares/capital') }}">
                                            <span class="text-muted">Share capital contributions Report</span>
                                        </a>
                                    </li>
                                    <li class="item-name">
                                        <a href="{{ url('/reports/fosa/contribution') }}">
                                            <span class="text-muted">FOSA contributions Report</span>
                                        </a>
                                    </li>
                                    <li class="item-name">
                                        <a href="{{ url('/reports/loans/balances') }}">
                                            <span class="text-muted">Loan/Share/Capital balances (as at a period)</span>
                                        </a>
                                    </li>
                                    <li class="item-name">
                                        <a href="{{ url('/reports/shares/period') }}">
                                            <span class="text-muted">Share contributions for the last 12 months</span>
                                        </a>
                                    </li>
                                </ul>
                            </li> -->
                            <!-- <li class="item-name has-arrow">
                                <a href="#">
                                    <span class="text-muted">Loan Reports</span>
                                </a>
                                <ul class="mm-collapse">
                                    <li class="item-name">
                                        <a href="{{ url('/reports/loans/issued') }}">
                                            <span class="text-muted">Loans issued</span>
                                        </a>
                                    </li>
                                    <li class="item-name">
                                        <a href="{{ url('/reports/loans/member') }}">
                                            <span class="text-muted">Member loans Report</span>
                                        </a>
                                    </li>
                                    <li class="item-name">
                                        <a href="{{ url('/reports/guarantors') }}">
                                            <span class="text-muted">Guarantors report</span>
                                        </a>
                                    </li>
                                    <li class="item-name">
                                        <a href="{{ url('/reports/contributions') }}">
                                            <span class="text-muted">Monthly contributions</span>
                                        </a>
                                    </li>
                                    <li class="item-name">
                                        <a href="{{ url('/reports/contributions/principal') }}">
                                            <span class="text-muted">Monthly contributions (principals)</span>
                                        </a>
                                    </li>
                                    <li class="item-name">
                                        <a href="{{ url('/reports/loans/repayments') }}">
                                            <span class="text-muted">Loan payments listing</span>
                                        </a>
                                    </li>
                                    <li class="item-name">
                                        <a href="{{ url('/reports/loans/balances/period') }}">
                                            <span class="text-muted">Loan/Share/Capital balances (as at a period)</span>
                                        </a>
                                    </li>
                                </ul>
                            </li> -->
                            <!-- <li class="item-name has-arrow">
                                <a href="#">
                                    <span class="text-muted">Final accounts</span>
                                </a>
                                <ul class="mm-collapse">
                                    <li class="item-name">
                                        <a href="{{ url('/reports/accounts/ledger') }}">
                                            <span class="text-muted">General ledger listing</span>
                                        </a>
                                    </li>
                                    <li class="item-name">
                                        <a href="{{ url('/reports/accounts/trial-balance') }}">
                                            <span class="text-muted">Trial balance</span>
                                        </a>
                                    </li>
                                    <li class="item-name">
                                        <a href="{{ url('/reports/accounts/profit-loss') }}">
                                            <span class="text-muted">Profit and Loss</span>
                                        </a>
                                    </li>
                                    <li class="item-name">
                                        <a href="{{ url('/reports/accounts/balance-sheet') }}">
                                            <span class="text-muted">Balance sheet</span>
                                        </a>
                                    </li>
                                </ul>
                            </li> -->
                            <!-- SASRA Menu -->
                            <li class="Ul_li--hover">
                                <a class="has-arrow" href="#">
                                    <i class="i-Bar-Chart text-20 me-2 text-muted"></i>
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
                                    <!-- <li class="item-name">
                                        <a href="{{url('/reports/sasra/finance_position_report')}}">Finance Position</a>
                                    </li>
                                    <li class="item-name">
                                        <a href="{{url('/reports/sasra/income_report')}}">Income Report</a>
                                    </li> -->
                                    <li class="item-name">
                                        <a href="{{url('/reports/sasra/loan_performance')}}">Loans Performance / RISK classifications</a>
                                    </li>
                                    <!-- <li class="item-name">
                                        <a href="{{url('/reports/sasra/loan_performance/v2')}}">Loans Performance / RISK classifications - V2</a>
                                    </li> -->
                                    <li class="item-name">
                                        <a href="{{url('/reports/sasra/loan_performance/insider_lending')}}">Insider Lending</a>
                                    </li>
                                    <!-- <li class="item-name">
                                        <a href="{{url('/reports/sasra/outstandingloans/y')}}">Loans - All OutStanding</a>
                                    </li>
                                    <li class="item-name">
                                        <a href="{{url('/reports/sasra/outstandingloans/y/active')}}">Loans - OutStanding & Active members only</a>
                                    </li>
                                    <li class="item-name">
                                        <a href="{{url('/reports/sasra/outstandingloans/n')}}">Loans - Fully Paid</a>
                                    </li>
                                    <li class="item-name">
                                        <a href="{{url('/reports/sasra/deposits')}}">Capital Balances</a>
                                    </li>
                                    <li class="item-name">
                                        <a href="{{url('/reports/sasra/share')}}">Share Balances (beta)</a>
                                    </li>
                                    <li class="item-name">
                                        <a href="{{url('/reports/sasra/return_on_investment_report')}}">Return on Investment Report</a>
                                    </li>
                                    <li class="item-name">
                                        <a href="{{url('/reports/sasra/finance_position_report')}}">Finance Position Report</a>
                                    </li>
                                    <li class="item-name">
                                        <a href="{{url('/reports/sasra/income_report')}}">Income Report</a>
                                    </li>
                                    <li class="item-name">
                                        <a href="{{url('/reports/sasra/loan_performance')}}">Loans Performance / RISK classifications</a>
                                    </li>
                                    <li class="item-name">
                                        <a href="{{url('/reports/sasra/loan_performance/true')}}">Loans Performance / RISK classifications - V2</a>
                                    </li>
                                    <li class="item-name">
                                        <a href="{{url('/reports/sasra/insider_lending')}}">Insider Lending</a>
                                    </li> -->
                                </ul>
                            </li>
                        </ul>
                    </li>
                    <!-- <li class="Ul_li--hover">
                        <a class="has-arrow" href="#">
                            <i class="i-Pie-Chart text-20 me-2 text-muted"></i>
                            <span class="item-name text-15 text-muted">Interest & Dividends</span>
                        </a>
                        <ul class="mm-collapse">
                            <li class="item-name">
                                <a href="{{ url('/interest/shares') }}">
                                    <span class="text-muted">Interest on share deposits</span>
                                </a>
                            </li>
                            <li class="item-name">
                                <a href="{{ url('/dividends/shares') }}">
                                    <span class="text-muted">Dividends on Share capital</span>
                                </a>
                            </li>
                            <li class="item-name">
                                <a href="{{ url('/interest/fosa') }}">
                                    <span class="text-muted">Interest on FOSA</span>
                                </a>
                            </li>
                        </ul>
                    </li> -->
                    <li class="Ul_li--hover">
                        <a class="has-arrow" href="#">
                            <i class="i-Journal text-20 me-2 text-muted"></i>
                            <span class="item-name text-15 text-muted">Journal Accounts</span>
                        </a>
                        <!-- <ul class="mm-collapse">
                            <li class="item-name">
                                <a href="{{ url('/accounts/main') }}">
                                    <span class="text-muted">Main accounts</span>
                                </a>
                            </li>
                            <li class="item-name">
                                <a href="{{ url('/accounts/sub') }}">
                                    <span class="text-muted">Sub accounts</span>
                                </a>
                            </li>
                            <li class="item-name">
                                <a href="{{ url('/accounts/transfer') }}">
                                    <span class="text-muted">Journal accounts transfers</span>
                                </a>
                            </li>
                        </ul> -->
                    </li>
                    <li class="Ul_li--hover">
                        <a class="has-arrow" href="#">
                            <i class="i-Download text-20 me-2 text-muted"></i>
                            <span class="item-name text-15 text-muted">Downloads</span>
                        </a>
                        <!-- <ul class="mm-collapse">
                            <li class="item-name">
                                <a href="{{ url('/downloads/view') }}">
                                    <span class="text-muted">View downloads</span>
                                </a>
                            </li>
                            <li class="item-name">
                                <a href="{{ url('/downloads/add-type') }}">
                                    <span class="text-muted">Add more download types</span>
                                </a>
                            </li>
                        </ul> -->
                    </li>
                    <li class="Ul_li--hover">
                        <a class="has-arrow" href="#">
                            <i class="i-Administrator text-20 me-2 text-muted"></i>
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
                                    <!-- <li class="item-name">
                                        <a href="{{ url('/admin/modules') }}">
                                            <span class="text-muted">Sacco system modules</span>
                                        </a>
                                    </li> -->
                                </ul>
                            </li>
                            <li class="item-name has-arrow">
                                <!-- <a href="#">
                                    <span class="text-muted">Sacco defaults</span>
                                </a> -->
                                <!-- <ul class="mm-collapse">
                                    <li class="item-name">
                                        <a href="{{ url('/admin/defaults') }}">
                                            <span class="text-muted">List sacco defaults</span>
                                        </a>
                                    </li>
                                    <li class="item-name">
                                        <a href="{{ url('/admin/defaults/add') }}">
                                            <span class="text-muted">Add defaults</span>
                                        </a>
                                    </li>
                                    <li class="item-name">
                                        <a href="{{ url('/admin/default-accounts/add') }}">
                                            <span class="text-muted">Add default accounts</span>
                                        </a>
                                    </li>
                                </ul> -->
                            </li>
                            <li class="item-name has-arrow">
                                <!-- <a href="#">
                                    <span class="text-muted">Budget and dividends</span>
                                </a> -->
                                <!-- <ul class="mm-collapse">
                                    <li class="item-name">
                                        <a href="{{ url('/admin/budget') }}">
                                            <span class="text-muted">Set budget</span>
                                        </a>
                                    </li>
                                </ul> -->
                            </li>
                            <li class="item-name">
                                <a href="{{ url('/admin/periods') }}">
                                    <span class="text-muted">Accounting periods</span>
                                </a>
                            </li>
                            <!-- <li class="item-name">
                                <a href="{{ url('/admin/year-end') }}">
                                    <span class="text-muted">End of year processing</span>
                                </a>
                            </li> -->
                        </ul>
                    </li>
                    <li class="Ul_li--hover">
                        <a class="has-arrow" href="#">
                            <i class="i-Administrator text-20 me-2 text-muted"></i>
                            <span class="item-name text-15 text-muted">User Profile</span>
                        </a>
                        <ul class="mm-collapse">
                            <!-- <li class="item-name">
                                <a href="{{ url('/profile') }}">
                                    <span class="text-muted">View Profile</span>
                                </a>
                            </li>
                            <li class="item-name">
                                <a href="{{ url('/profile/edit/' . Auth::id()) }}">
                                    <span class="text-muted">Edit Profile</span>
                                </a>
                            </li> -->
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
