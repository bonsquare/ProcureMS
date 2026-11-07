@if($files->isNotEmpty())
    <ul class="mt-2 space-y-1" aria-label="Attached files">
        @foreach($files as $file)
            <li class="flex min-w-0 items-center gap-1.5 text-xs"><span class="material-symbols-outlined text-[16px] text-primary" aria-hidden="true">attach_file</span><a href="https://drive.google.com/file/d/{{ $file->drive_file_id }}/view" target="_blank" rel="noopener noreferrer" class="min-w-0 break-all font-semibold text-primary underline">{{ $file->name }}</a></li>
        @endforeach
    </ul>
@endif
