@php($driveConnection = auth()->user()?->driveConnection)
@if($driveConnection && ! $driveConnection->isConnected())
    <div class="fixed bottom-4 left-1/2 z-[60] flex w-[calc(100%-2rem)] max-w-xl -translate-x-1/2 items-center gap-3 rounded border border-error/30 bg-white px-4 py-3 text-sm shadow-lg no-print" role="alert">
        <span class="material-symbols-outlined text-error" aria-hidden="true">cloud_off</span>
        <span class="flex-1 text-on-surface">Your Google Drive connection needs to be renewed. File uploads are paused.</span>
        <a href="{{ route('google-drive.redirect') }}" class="shrink-0 rounded bg-primary px-3 py-1.5 text-xs font-semibold text-white hover:bg-primary-container">Reconnect Google Drive</a>
    </div>
@endif
