<?php

namespace App\Http\Controllers;

use App\Models\AdminRecord;
use App\Services\Access;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class RecordSupportController extends Controller
{
    public const TYPES = ['vendors' => ['Contract', 'Performance evaluation', 'Compliance review'], 'vehicles' => ['Fuel purchase', 'Maintenance', 'Insurance', 'Roadworthiness', 'Incident'], 'trips' => ['Fuel purchase', 'Incident'], 'assets' => ['Maintenance', 'Physical verification', 'Warranty']];

    private function record(int $id): AdminRecord
    {
        return Access::scope(AdminRecord::query(), Auth::user())->findOrFail($id);
    }

    public function upload(Request $r, int $id)
    {
        $record = $this->record($id);
        abort_unless(Access::canUploadRecord(Auth::user(), $record), 403);
        if ($record->module === 'vehicles') {
            abort_unless(Access::allows(Auth::user(), 'vehicles.write'), 403);
        }
        $v = $r->validate(['file' => 'required|file|mimes:pdf,jpg,jpeg,png,docx,xlsx|max:10240', 'type' => 'required|string|max:80', 'expires_at' => 'nullable|date']);
        if ($record->module === 'trips') {
            $v['expires_at'] = null;
        }
        $path = $r->file('file')->store('documents', 'local');
        try {
            DB::transaction(function () use ($r, $record, $v, $path) {
                DB::table('documents')->insert(['record_id' => $record->id, 'uploaded_by' => Auth::id(), 'name' => basename($r->file('file')->getClientOriginalName()), 'path' => $path, 'type' => $v['type'], 'expires_at' => $v['expires_at'] ?? null, 'created_at' => now(), 'updated_at' => now()]);
                PortalController::audit('Uploaded document', $record->reference);
            });
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($path);
            throw $e;
        }

        return back()->with('success', 'Document stored securely.');
    }

    public function download(int $id)
    {
        $doc = DB::table('documents')->find($id);
        abort_unless($doc, 404);
        $record = $this->record($doc->record_id);
        abort_if($record->module !== 'trips' && in_array(Auth::user()->role, ['staff', 'driver']) && ! in_array($doc->type, ['Handover', 'Trip authorization']), 403);
        PortalController::audit('Downloaded document', $record->reference);

        return Storage::disk('local')->download($doc->path, $doc->name, ['X-Content-Type-Options' => 'nosniff']);
    }

    public function entry(Request $r, int $id)
    {
        $record = $this->record($id);
        if ($record->module === 'vehicles') {
            abort_unless(Access::allows(Auth::user(), 'vehicles.write'), 403);
        }
        abort_unless(Access::allows(Auth::user(), 'create') || ($record->module === 'trips' && Access::allows(Auth::user(), 'trip')), 403);
        $v = $r->validate(['type' => 'required|in:'.implode(',', self::TYPES[$record->module]), 'title' => 'required|string|max:180', 'date' => 'required|date', 'amount' => 'nullable|numeric|min:0|max:9999999999', 'quantity' => 'nullable|numeric|min:0|max:999999999', 'notes' => 'nullable|string|max:3000', 'due_at' => 'nullable|date|after_or_equal:date']);
        DB::transaction(function () use ($record, $v) {
            DB::table('record_entries')->insert([...$v, 'record_id' => $record->id, 'created_by' => Auth::id(), 'created_at' => now(), 'updated_at' => now()]);
            PortalController::audit('Added '.$v['type'], $record->reference);
        });

        return back()->with('success', 'Register entry recorded.');
    }

    public function notifications()
    {
        $ids = Access::scope(AdminRecord::query(), Auth::user())->select('id');
        $documents = DB::table('documents')->join('records', 'records.id', '=', 'documents.record_id')->whereIn('records.id', $ids)->whereNotNull('expires_at')->where('expires_at', '<=', now()->addDays(30)->toDateString())->select('documents.*', 'records.reference', 'records.module')->get();
        $entries = DB::table('record_entries')->join('records', 'records.id', '=', 'record_entries.record_id')->whereIn('records.id', $ids)->whereNotNull('due_at')->where('due_at', '<=', now()->addDays(30)->toDateString())->select('record_entries.*', 'records.reference', 'records.module')->get();

        return view('notifications', compact('documents', 'entries'));
    }
}
