<footer>
@section('scripts')
    





<script src="{{ asset('dist-assets/js/plugins/jquery-3.3.1.min.js') }}"></script>
<script src="{{ asset('dist-assets/js/plugins/bootstrap.bundle.min.js') }}"></script>
<script src="{{ asset('dist-assets/js/plugins/perfect-scrollbar.min.js') }}"></script>
<script src="{{ asset('dist-assets/js/scripts/tooltip.script.min.js') }}"></script>
<script src="{{ asset('dist-assets/js/scripts/script.min.js') }}"></script>
<script src="{{ asset('dist-assets/js/scripts/script_2.min.js') }}"></script>
<script src="{{ asset('dist-assets/js/scripts/sidebar.large.script.min.js') }}"></script>
<script src="{{ asset('dist-assets/js/plugins/feather.min.js') }}"></script>
<script src="{{ asset('dist-assets/js/plugins/metisMenu.min.js') }}"></script>
<script src="{{ asset('dist-assets/js/scripts/layout-sidebar-vertical.min.js') }}"></script>
<script src="{{ asset('dist-assets/js/plugins/datatables.min.js') }}"></script>
<script src="{{ asset('dist-assets/js/scripts/datatables.script.min.js') }}"></script>


 
<script>
        $(document).ready(function() {
            $('#ledger_table').DataTable({
                "order": [[ 0, "asc" ]],
                "paging": true,
            "ordering": true,
            "info": true
            });
        });
    </script>
    


@endsection

<script>
    $(document).ready(function() {
        $('#multicolumn_ordering_table').DataTable({
            "paging": true,
            "ordering": true,
            "info": true
        });
    });
</script>

@if(Route::is('institutions.add'))
    @section('scripts')
        <script>
            $(document).ready(function() {
                $('#company_id').on('change', function() {
                    var selectedCompany = $(this).find('option:selected');
                    var companyDetails = selectedCompany.data('details');
                    var companyAccount = selectedCompany.data('account');
                    
                    $('#company_details').val(companyDetails || '');
                    $('#sub_account_id').val(companyAccount || '');
                });
            });
        </script>
    @endsection
@endif

@if(Route::is('list.contribution'))
    @section('scripts')
        <script>
            $(document).ready(function() {
                $('#multicolumn_ordering_table').DataTable({
                    "order": [[ 1, "asc" ]],
                    "columnDefs": [{
                        "targets": 'no-sort',
                        "orderable": false,
                    }]
                });
            });
        </script>
    @endsection
@endif



 
 
@yield('scripts')
</footer>


