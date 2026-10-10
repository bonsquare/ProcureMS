<?php

namespace App\Http\Controllers;

use App\Exceptions\DriveNotConnected;
use App\Models\DriveFile;
use App\Services\GoogleDriveService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

class DriveFileController extends Controller
{
    public function __construct(private GoogleDriveService $drive) {}

    public function index(Request $request): Response
    {
        return response()->view('drive-files', [
            'connection' => $request->user()->driveConnection,
            'files' => DriveFile::where('user_id', $request->user()->id)->latest()->get(),
            'drive' => $this->drive,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate(['file' => ['required', 'file', 'max:20480']], [
            'file.required' => 'Choose a file to upload.',
            'file.max' => 'The file is too large. The limit is 20 MB.',
            'file.uploaded' => 'The file could not be uploaded. Try a file under 20 MB.',
        ]);
        $upload = $request->file('file');
        $connection = $request->user()->driveConnection;

        try {
            $stored = $this->drive->upload(
                $connection, 'Files', Str::limit(basename($upload->getClientOriginalName()), 180, ''),
                $upload->get(), $upload->getMimeType() ?: 'application/octet-stream',
            );
        } catch (DriveNotConnected $exception) {
            return ($connection->fresh()?->isConnected() ? redirect()->route('drive-files.index') : redirect()->route('google-drive'))
                ->with('error', $exception->getMessage());
        }

        DriveFile::create([
            'user_id' => $request->user()->id,
            'name' => Str::limit(basename($upload->getClientOriginalName()), 180, ''),
            'drive_file_id' => $stored['id'],
            'mime' => $upload->getMimeType(),
            'size' => $stored['size'],
        ]);

        return redirect()->route('drive-files.index')->with('success', 'The file was saved to your Google Drive.');
    }

    public function destroy(Request $request, int $driveFile): RedirectResponse
    {
        $file = DriveFile::where('user_id', $request->user()->id)->findOrFail($driveFile);

        $connection = $request->user()->driveConnection;
        if (! $connection) {
            return redirect()->route('google-drive')->with('error', 'Connect your Google Drive to delete this file.');
        }

        try {
            $this->drive->delete($connection, $file->drive_file_id);
        } catch (DriveNotConnected $exception) {
            return redirect()->route('drive-files.index')->with('error', $exception->getMessage());
        }
        $file->delete();

        return redirect()->route('drive-files.index')->with('success', 'The file was deleted from your Google Drive.');
    }
}
