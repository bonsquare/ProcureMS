<?php

namespace App\Http\Controllers;

use App\Exceptions\DriveNotConnected;
use App\Models\GoogleDriveConnection;
use App\Services\GoogleDriveService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class GoogleDriveController extends Controller
{
    public function __construct(private GoogleDriveService $drive) {}

    public function show(Request $request): Response
    {
        return response()->view('google-drive', [
            'connection' => $request->user()->driveConnection,
            'configured' => $this->drive->isConfigured(),
        ]);
    }

    public function redirect(Request $request): RedirectResponse
    {
        if (! $this->drive->isConfigured()) {
            return redirect()->route('google-drive')->with('error', 'Google Drive is not set up on this server yet. Ask the system administrator.');
        }

        return redirect()->away($this->drive->authorizationUrl($request->user()));
    }

    public function callback(Request $request): RedirectResponse
    {
        $expected = $request->session()->pull('google_drive_state');
        if (! $expected || ! hash_equals($expected, (string) $request->query('state'))) {
            return redirect()->route('google-drive')->with('error', 'That Google sign-in could not be verified. Please press Connect again.');
        }
        if ($request->query('error') || ! $request->query('code')) {
            return redirect()->route('google-drive')->with('error', 'Google Drive was not connected because the permission was not given.');
        }

        try {
            $this->drive->connect($request->user(), (string) $request->query('code'));
        } catch (DriveNotConnected $exception) {
            return redirect()->route('google-drive')->with('error', $exception->getMessage());
        }

        return redirect()->route('google-drive')->with('success', 'Google Drive is connected. The ProcMS folder is ready in your Drive.');
    }

    public function disconnect(Request $request): RedirectResponse
    {
        GoogleDriveConnection::where('user_id', $request->user()->id)->delete();

        return redirect()->route('google-drive')->with('success', 'Google Drive was disconnected. Your files stay in your Drive; remove ProcMS under your Google account permissions if you also want to revoke access.');
    }
}
