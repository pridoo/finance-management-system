@extends('mswdo.layouts.app')
@section('title', 'Backup')
@section('page-title', 'Database Backup')

@section('content')

@php
    $latest = $backups[0] ?? null;
    $fmtSize = function (int $bytes) {
        if ($bytes >= 1048576) return number_format($bytes / 1048576, 2).' MB';
        return number_format($bytes / 1024, 1).' KB';
    };
@endphp

{{-- Summary + "Back up now" --}}
<div class="panel mb-4">
    <div class="panel-head">
        <div>
            <p class="text-[11px] uppercase tracking-wider text-teal-700 font-bold">Database Backup</p>
            <h3 class="font-bold text-gray-900 mt-0.5 text-base">Automatic backup every day at 5:00 PM</h3>
            <p class="text-xs text-gray-500 mt-0.5">
                Each backup is a <b>.sql</b> file of the whole database, saved in the
                <b>backup</b> folder and named with the date and time it was made.
            </p>
        </div>
        @if(auth()->user()->canModify())
            <form method="POST" action="{{ route('mswdo.backup.store') }}" class="shrink-0"
                  data-swal-confirm
                  data-swal-title="Back up the database now?"
                  data-swal-text="A new .sql backup file will be saved in the backup folder."
                  data-swal-confirm-text="Yes, back up now">
                @csrf
                <button type="submit" class="btn-primary px-5 py-2.5 inline-flex items-center gap-2 whitespace-nowrap">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                    </svg>
                    Back up now
                </button>
            </form>
        @endif
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 p-5">
        <div>
            <p class="text-xs text-gray-500">Last backup</p>
            <p class="font-semibold text-gray-900 mt-0.5">
                {{ $latest ? $latest['created_at']->format('M d, Y · g:i A') : 'No backups yet' }}
            </p>
        </div>
        <div>
            <p class="text-xs text-gray-500">Saved backups</p>
            <p class="font-semibold text-gray-900 mt-0.5">{{ count($backups) }}</p>
        </div>
        <div class="min-w-0">
            <p class="text-xs text-gray-500">Backup folder</p>
            <p class="font-mono text-xs text-gray-800 mt-1 break-all">{{ $folder }}</p>
        </div>
    </div>
</div>

{{-- List of backups --}}
<div class="panel">
    <div class="panel-head">
        <h3 class="font-bold text-gray-900 text-base">Backup files</h3>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-xs text-gray-700 font-semibold uppercase tracking-wide">
                <tr>
                    <th class="px-5 py-3 text-left whitespace-nowrap">File name</th>
                    <th class="px-5 py-3 text-left whitespace-nowrap">Date backed up</th>
                    <th class="px-5 py-3 text-left whitespace-nowrap">Type</th>
                    <th class="px-5 py-3 text-left whitespace-nowrap">Size</th>
                    <th class="px-5 py-3 text-right whitespace-nowrap">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($backups as $b)
                    <tr class="hover:bg-gray-50 transition-colors">
                        <td class="px-5 py-3.5 font-mono text-xs text-gray-800 whitespace-nowrap">{{ $b['name'] }}</td>
                        <td class="px-5 py-3.5 text-gray-800 whitespace-nowrap">{{ $b['created_at']->format('M d, Y · g:i:s A') }}</td>
                        <td class="px-5 py-3.5 whitespace-nowrap">
                            @if($b['type'] === 'auto')
                                <span class="rounded-full bg-teal-50 px-2.5 py-0.5 text-xs font-semibold text-teal-700 ring-1 ring-inset ring-teal-200">Automatic (5 PM)</span>
                            @else
                                <span class="rounded-full bg-yellow-50 px-2.5 py-0.5 text-xs font-semibold text-yellow-800 ring-1 ring-inset ring-yellow-300">Manual</span>
                            @endif
                        </td>
                        <td class="px-5 py-3.5 text-gray-800 tabular-nums whitespace-nowrap">{{ $fmtSize($b['size']) }}</td>
                        <td class="px-5 py-3.5 whitespace-nowrap">
                            <div class="flex items-center justify-end gap-2">
                                <a href="{{ route('mswdo.backup.download', $b['name']) }}"
                                   class="btn-secondary px-3 py-1.5 text-xs">Download</a>
                                @if(auth()->user()->canModify())
                                    <form method="POST" action="{{ route('mswdo.backup.destroy', $b['name']) }}"
                                          data-swal-confirm
                                          data-swal-title="Delete this backup?"
                                          data-swal-text="<b>{{ $b['name'] }}</b> will be permanently removed."
                                          data-swal-confirm-text="Yes, delete it">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn-danger px-3 py-1.5 text-xs">Delete</button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-5 py-12 text-center text-gray-500 text-sm">
                            No backups yet. Click <b>Back up now</b>, or wait for the automatic 5:00 PM backup.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@endsection
