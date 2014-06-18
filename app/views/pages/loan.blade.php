@extends('layouts.master')

@section('page-title')
@lang('loan.page-title')
@stop

@section('content')
<div class="page-header">
    <h1>{{ $loan->getSummary()}}</h1>
</div>

<div class="row">
    <div class="col-xs-8">
        <h3>My Story</h3>

        <p>{{ $loan->getBorrower()->getProfile()->getAboutMe() }}</p>

        <h3>About My Business</h3>

        <p>{{ $loan->getBorrower()->getProfile()->getAboutBusiness() }}</p>

        <h3>My Loan Proposal</h3>

        <p>{{ $loan->getDescription() }}</p>
        <br/>
        <br/>
        <h4>Comments</h4>
        @include('partials.comments.comments', ['comments' => $comments])
    </div>

    <div class="col-xs-4">
        <img src="{{ $loan->getBorrower()->getUser()->getProfilePictureUrl() }}" >
        <h2>{{ $loan->getBorrower()->getFirstName() }} {{ $loan->getBorrower()->getLastName() }}</h2>
        <h4>{{ $loan->getBorrower()->getCountry()->getName() }}</h4>
        <strong>Amount Requested: </strong> USD {{ $loan->getAmount() }}

        @include('partials/_progress', [ 'raised' => $raised])

        @if($loan->getStatus() == '0')
        <div>
            {{ BootstrapForm::open(array('route' => 'loan:post-bid', 'translationDomain' => 'bid')) }}
            {{ BootstrapForm::populate($form) }}

            {{ BootstrapForm::text('Amount') }}
            {{ BootstrapForm::select('interestRate', $form->getRates()) }}
            {{ BootstrapForm::hidden('loanId', $loan->getId()) }}
            {{ BootstrapForm::submit('save') }}

            {{ BootstrapForm::close() }}
        </div>
        @endif

        <br>
        <strong>FUNDING RAISED </strong>
        <table class="table table-striped">
            <thead>
            <tr>
                <th>Date</th>
                <th>Lender</th>
                <th>Amount (USD)</th>
            </tr>
            </thead>
            <tbody>
            @foreach($bids as $bid)
            <tr>
                <td>{{ $bid->getBidDate()->format('d-m-Y') }}</td>
                <td><a href="{{ route('lender:public-profile', $bid->getLender()->getUser()->getUserName()) }}">{{
                        $bid->getLender()->getUser()->getUserName() }}</a></td>
                <td>{{ $bid->getBidAmount() }}</td>
            </tr>
            @endforeach
            </tbody>
        </table>
        <strong>Raised: </strong> USD {{ $totalRaised }}
        <strong>Still Needed: </strong> USD {{ $stillNeeded }}
    </div>
</div>
@stop
