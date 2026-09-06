@extends('mswdo.layouts.app')
@section('title', 'New User')
@section('page-title', 'New User')

@section('breadcrumb')
    <a href="{{ route('admin.users.index') }}">User Management</a>
    <span class="sep" aria-hidden="true">/</span>
    <span class="current">New User</span>
@endsection

@section('content')

{{-- Back lives in the breadcrumb above the title now. --}}
<div class="max-w-5xl">
    <form method="POST" action="{{ route('admin.users.store') }}">
        @csrf
        @include('admin.users._form')
    </form>
</div>

@endsection
