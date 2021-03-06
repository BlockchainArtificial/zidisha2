@extends('layouts.master')

@section('page-title')
Lending Groups
@stop

@section('content')
<h2>Lending Groups</h2>
<p>
    Lending Groups maximize their impact by combining forces. A Lending Group's Total Group Impact is the sum of the Total Lender
    Impact of its members. The Total Lender Impact equals the combined sum of the dollar volume of loans made by each member, plus
    the loans made by each lender that was recruited by that member to join Zidisha.
</p>
@if(Auth::check() && Auth::getUser()->isLender())
<a href="{{ route('lender:groups:create') }}" class="btn btn-primary">
   Start a new Group
</a>
@endif
<br><br>
@if($paginator != null)
<table class="table table-striped">
    <thead>
    <tr>
        <th>Group Name</th>
        <th>Total Impact This month</th>
        <th>Total Impact last month</th>
        <th>Total impact all time</th>
        <th>About This group</th>
        <th></th>
    </tr>
    </thead>
    <tbody>
    @foreach($paginator as $group)
    <tr>
        <td>{{ $group->getName() }}
            @if($group->getGroupProfilePicture())
                <img src="{{ $group->getGroupProfilePicture()->getImageUrl('small-profile-picture') }}" alt=""/>
            @endif
        </td>
        <td></td>
        <td></td>
        <td></td>
        <td>{{ $group->getAbout() }}</td>
        <td><a href="{{ route('lender:group', $group->getId()) }}">View Profile</a></td>
    </tr>
    @endforeach
    </tbody>
</table>
@endif
{{ BootstrapHtml::paginator($paginator)->links() }}
@stop
