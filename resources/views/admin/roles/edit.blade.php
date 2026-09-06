@extends('mswdo.layouts.app')
@section('title', 'Edit Role')
@section('page-title', 'Edit Role')

@section('breadcrumb')
    <a href="{{ route('admin.users.index') }}">User Management</a>
    <span class="sep" aria-hidden="true">/</span>
    <a href="{{ route('admin.roles.index') }}">Roles</a>
    <span class="sep" aria-hidden="true">/</span>
    <span class="current">{{ $role->label }}</span>
@endsection

@section('content')

<form method="POST" action="{{ route('admin.roles.update', $role) }}">
    @csrf
    @method('PUT')
    @include('admin.roles._form')
</form>

@endsection
