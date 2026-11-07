<?php

namespace App\Http\Controllers;

use App\Services\Access;
use App\Services\AssetFields;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AssetCsvController extends Controller
{
    private function type(Request $request): string
    {
        abort_unless(Access::allows($request->user(), 'create'), 403);

        return $request->validate(['asset_type' => ['required', Rule::in(array_keys(AssetFields::TYPES))]])['asset_type'];
    }

    public function template(Request $request)
    {
        $type = $this->type($request);

        return response()->streamDownload(function () use ($type) {
            $stream = fopen('php://output', 'w');
            fwrite($stream, "\xEF\xBB\xBF");
            fputcsv($stream, AssetFields::csvColumns($type), ',', '"', '');
            fclose($stream);
        }, $type.'-template.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function upload(Request $request)
    {
        $type = $this->type($request);
        $request->validate(['csv' => 'required|file|max:5120|extensions:csv']);
        $stream = fopen($request->file('csv')->getRealPath(), 'r');
        $rows = [];
        $errors = [];
        try {
            $headers = fgetcsv($stream, 0, ',', '"', '');
            if ($headers === false) {
                throw ValidationException::withMessages(['csv' => 'The CSV file is empty.']);
            }
            $headers = array_map(fn ($value) => trim((string) $value), $headers);
            $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', $headers[0]);
            $columns = AssetFields::csvColumns($type);
            if (count(array_unique($headers)) !== count($headers) || array_diff($headers, $columns) || array_diff(['name', 'year', 'location', 'price', 'project', 'serial', 'office_id', 'purchase_date'], $headers)) {
                throw ValidationException::withMessages(['csv' => 'Invalid CSV headers. Use the template for the selected asset type, including name, year, location, price, project, serial, office_id and purchase_date.']);
            }
            $line = 1;
            while (($values = fgetcsv($stream, 0, ',', '"', '')) !== false) {
                $line++;
                if (count($values) === 1 && trim((string) $values[0]) === '') {
                    continue;
                }
                if (count($rows) >= 1000 || $line > 1100) {
                    throw ValidationException::withMessages(['csv' => 'Upload no more than 1,000 assets per file.']);
                }
                if (count($values) !== count($headers)) {
                    $errors[] = "Row {$line}: column count does not match the headers.";

                    continue;
                }
                $data = array_combine($headers, array_map(fn ($value) => trim((string) $value) === '' ? null : trim($value), $values));
                $validator = Validator::make([...$data, 'asset_type' => $type], AssetFields::rules());
                if ($validator->fails()) {
                    foreach ($validator->errors()->all() as $error) {
                        $errors[] = "Row {$line}: {$error}";
                    }
                } else {
                    $rows[] = $validator->validated();
                }
                if (count($errors) >= 30) {
                    break;
                }
            }
        } finally {
            fclose($stream);
        }
        if ($errors) {
            throw ValidationException::withMessages(['csv' => [...$errors, 'No assets were saved. Correct the file and upload it again.']]);
        }
        if (! $rows) {
            throw ValidationException::withMessages(['csv' => 'The CSV must contain at least one asset row below the headers.']);
        }
        DB::transaction(function () use ($rows, $request) {
            foreach ($rows as $data) {
                $rowRequest = Request::create('/records/assets', 'POST', $data);
                $rowRequest->setUserResolver(fn () => $request->user());
                app(AssetRegisterController::class)->store($rowRequest);
            }
            PortalController::audit('Bulk uploaded assets', count($rows).' assets', 'asset');
        });

        return redirect('/records/assets')->with('success', count($rows).' assets uploaded successfully.');
    }
}
