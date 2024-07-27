@extends('layouts.app')

@section('content')
<div class="breadcrumb d-flex justify-content-between align-items-center">
    <h1>Increase Capital  from  shares</h1>
    <div class="header-part-right">
            <ul>
                @if(isset($currentPeriod))
                    <li><a href="{{ route('admin.periods') }}">{{ $currentPeriod->period_name }}</a></li>
                @endif
                @if(Auth::check())
                    <li>{{ Auth::user()->member_name }}</li>
                @endif
                <li><i class="i-Full-Screen header-icon d-none d-sm-inline-block" data-fullscreen=""></i></li>
            </ul>
        </div>
</div>
<div class="separator-breadcrumb border-top"></div>

<div class="row mb-4">
    @if(session('success'))
        <div class="alert alert-success">
            {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger">
            {{ session('error') }}
        </div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger">
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="col-md-12 mb-4">
        <div class="card text-start">
            <div class="card-body">
                <form action="{{ url()->current() }}" method="post" autocomplete="off">
                    @csrf
                    <div class="table-responsive">
                        <table class="display table table-striped table-bordered" id="shares_transfer_table" style="width: 100%">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Transfer shares deposits from Member name*</th>
                                    <th>Transfer CAPITAL shares to Member name*</th>
                                    <th>Amount*</th>
                                    <th>Doc. No.</th>
                                    <th>Description</th>
                                    <th>Date*</th>
                                </tr>
                            </thead>
                            <tbody>
                                @for($i = 0; $i < $modifyMemberSharesJournalEntries; $i++)
                                    <tr>
                                        <td>{{ $i + 1 }}</td>
                                        <td>
                                            <input type="text" id="member_name{{ $i }}" name="member_name[]" class="form-control member-name" value="{{ old('member_name.' . $i) }}" onkeyup="showHintMembers(this.value, 'member_name{{ $i }}', 'suggestions-box-member{{ $i }}')" autocomplete="off">
                                            <div id="suggestions-box-member{{ $i }}" class="suggestions-box"></div>
                                        </td>
                                        <td>
                                            <input type="text" id="member_namex1{{ $i }}" name="member_namex1[]" class="form-control member-name" value="{{ old('member_namex1.' . $i) }}" onkeyup="showHintMembers(this.value, 'member_namex1{{ $i }}', 'suggestions-box-memberx1{{ $i }}')" autocomplete="off">
                                            <div id="suggestions-box-memberx1{{ $i }}" class="suggestions-box"></div>
                                        </td>
                                        <td><input type="text" name="amount[]" class="form-control" value="{{ old('amount.' . $i) }}" maxlength="20"></td>
                                        <td><input type="text" name="share_doc_no[]" class="form-control" value="{{ old('share_doc_no.' . $i) }}" maxlength="20"></td>
                                        <td><input type="text" name="share_description[]" class="form-control" value="{{ old('share_description.' . $i) }}" maxlength="100"></td>
                                        <td><input type="date" name="share_date_paid[]" class="form-control" value="{{ old('share_date_paid.' . $i, date('Y-m-d')) }}"></td>
                                    </tr>
                                @endfor
                            </tbody>
                        </table>
                    </div>
                    <div class="text-center">
                        <button type="submit" class="btn btn-primary">Submit</button>
                    </div>
                    <input type="hidden" name="submitted" value="{{ $modifyMemberSharesJournalEntries }}">
                </form>
            </div>
        </div>
    </div>
</div>



<style>
    .suggestions-box {
        background-color: #fff;
        max-height: 150px;
        overflow-y: auto;
        position: absolute;
        z-index: 1000;
        width: 300px;
        box-shadow: 0px 8px 16px 0px rgba(0,0,0,0.2);
    }

    .suggestion-item {
        padding: 10px;
        cursor: pointer;
    }

    .suggestion-item:last-child {
        border-bottom: none;
    }

    .suggestion-item:hover {
        background-color: #f0f0f0;
    }
</style>

<script>
    function showHintMembers(str, inputName, suggestionsBox) {
        if (str.length == 0) {
            document.getElementById(suggestionsBox).innerHTML = "";
            return;
        }
        let xmlhttp;
        if (window.XMLHttpRequest) {
            xmlhttp = new XMLHttpRequest();
        } else {
            xmlhttp = new ActiveXObject("Microsoft.XMLHTTP");
        }
        xmlhttp.onreadystatechange = function () {
            if (xmlhttp.readyState == 4 && xmlhttp.status == 200) {
                let response = JSON.parse(xmlhttp.responseText);
                let suggestions = '';
                response.forEach(member => {
                    suggestions += `<div class="suggestion-item" onclick="selectMember('${member.value}', '${inputName}', '${suggestionsBox}')">${member.label}</div>`;
                });
                document.getElementById(suggestionsBox).innerHTML = suggestions;
            }
        };
        // xmlhttp.open("GET", "/search/members?query=" + str, true);
        xmlhttp.open("GET", "{{ url('/search/members') }}?query=" + str, true);
        xmlhttp.send();
    }

    function selectMember(value, inputName, suggestionsBox) {
        document.getElementById(inputName).value = value;
        document.getElementById(suggestionsBox).innerHTML = ''; // Clear suggestions
    }
</script>
@endsection
