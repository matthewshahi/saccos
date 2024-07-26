@extends('layouts.app')

@section('content')
<div class="container">
    <h4>Modify User Access Rights</h4>
    <!-- Form for selecting a user -->
    <form name="form1" method="get" action="{{ route('admin.access-rights') }}" id="select-user-form">
        <div class="form-group">
            <label for="rights_user">User</label>
            <select name="rights_user" id="rights_user" class="form-control" onchange="this.form.submit()">
                @foreach($members as $member)
                    <option value="{{ $member->member_id }}" {{ $selectedMemberId == $member->member_id ? 'selected' : '' }}>
                        {{ $member->member_name }}
                    </option>
                @endforeach
            </select>
        </div>
    </form>

    <!-- Form for modifying user access rights -->
    <form name="form2" method="post" action="{{ route('admin.access-rights.save') }}">
        @csrf
        <input type="hidden" name="rights_user" value="{{ $selectedMemberId }}">
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Module</th>
                    <th>Grant Access</th>
                </tr>
            </thead>
            <tbody>
                @foreach($modules as $index => $module)
                    <tr>
                        <td>{{ $index + 1 }}</td>
                        <td>{{ $module->module_description }}</td>
                        <td>
                            <label class="switch pe-5 switch-success me-3">
                                <input type="checkbox" name="rights_accessX{{ $index }}" value="Y"
                                    {{ isset($grantedRights[$module->module_id]) && $grantedRights[$module->module_id] == 'Y' ? 'checked' : '' }}>
                                <span class="slider"></span>
                            </label>
                            <input type="hidden" name="rights_appX{{ $index }}" value="{{ $module->module_id }}">
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <div class="form-group text-center">
            <input type="hidden" name="TotalModules" value="{{ count($modules) }}">
            <button type="submit" class="btn btn-primary">Submit</button>
        </div>
    </form>
</div>
@endsection
