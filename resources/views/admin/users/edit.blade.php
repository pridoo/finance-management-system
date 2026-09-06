@extends('mswdo.layouts.app')
@section('title', 'Edit User')
@section('page-title', 'Edit User')

@section('breadcrumb')
    <a href="{{ route('admin.users.index') }}">User Management</a>
    <span class="sep" aria-hidden="true">/</span>
    <span class="current">{{ $user->name }}</span>
@endsection

@section('content')

{{-- Back lives in the breadcrumb above the title now, so it is in the same
     place on every page rather than repeated inside each one. --}}
<div class="max-w-5xl">

    <form method="POST" action="{{ route('admin.users.update', $user) }}">
        @csrf
        @method('PUT')
        @include('admin.users._form')
    </form>
</div>

@endsection
