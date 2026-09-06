@extends('mswdo.layouts.app')
@section('title', 'New Role')
@section('page-title', 'New Role')

@section('breadcrumb')
    <a href="{{ route('admin.users.index') }}">User Management</a>
    <span class="sep" aria-hidden="true">/</span>
    <a href="{{ route('admin.roles.index') }}">Roles</a>
    <span class="sep" aria-hidden="true">/</span>
    <span class="current">New Role</span>
@endsection

@section('content')

<form method="POST" action="{{ route('admin.roles.store') }}">
    @csrf
    @include('admin.roles._form')
</form>

@endsection
