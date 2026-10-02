<?php

namespace App\Http\Controllers\Api\Gynecology;

use App\Http\Controllers\Concerns\AuthorizesOwnDoctorRecords;
use App\Http\Controllers\Controller;
use App\Http\Requests\Gynecology\SaveGynecologyUltrasoundExamRequest;
use App\Http\Resources\GynecologyUltrasoundExamResource;
use App\Models\Client;
use App\Models\GynecologyUltrasoundExam;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class UltrasoundExamController extends Controller
{
    use AuthorizesOwnDoctorRecords;

    protected const FILE_FIELDS = ['image'];

    public function index(Request $request, Client $client)
    {
        $this->assertActingDoctorOwnsClient($request, $client);

        return $this->success(GynecologyUltrasoundExamResource::collection($client->gynecologyUltrasoundExams()->get()));
    }

    public function store(SaveGynecologyUltrasoundExamRequest $request, Client $client)
    {
        $this->assertActingDoctorOwnsClient($request, $client);

        $data = $this->withStoredFiles($request, $request->validated());

        $record = $client->gynecologyUltrasoundExams()->create([
            ...$data,
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        return $this->success(GynecologyUltrasoundExamResource::make($record), 'Record saved successfully.', 201);
    }

    public function update(SaveGynecologyUltrasoundExamRequest $request, GynecologyUltrasoundExam $record)
    {
        $this->assertActingDoctorOwnsClient($request, $record->client);

        $data = $this->withStoredFiles($request, $request->validated(), $record);

        $record->update([
            ...$data,
            'updated_by' => $request->user()->id,
        ]);

        return $this->success(GynecologyUltrasoundExamResource::make($record->fresh()), 'Record updated successfully.');
    }

    public function destroy(Request $request, GynecologyUltrasoundExam $record)
    {
        $this->assertActingDoctorOwnsClient($request, $record->client);

        foreach (self::FILE_FIELDS as $field) {
            if ($record->{$field.'_path'}) {
                Storage::disk('local')->delete($record->{$field.'_path'});
            }
        }

        $record->delete();

        return $this->success(null, 'Record deleted successfully.');
    }

    /**
     * Served from the `signed` route group in routes/api.php (a plain <img>
     * can't send a bearer token) -- the URL is only ever minted by
     * GynecologyUltrasoundExamResource for a tenant-scoped, ownership-checked request.
     */
    public function file(GynecologyUltrasoundExam $record, string $field)
    {
        abort_unless(in_array($field, self::FILE_FIELDS, true) && $record->{$field.'_path'}, 404);

        return Storage::disk('local')->response($record->{$field.'_path'}, $record->{$field.'_original_filename'});
    }

    protected function withStoredFiles(Request $request, array $data, ?GynecologyUltrasoundExam $record = null): array
    {
        foreach (self::FILE_FIELDS as $field) {
            unset($data[$field]);
            if ($request->hasFile($field)) {
                if ($record?->{$field.'_path'}) {
                    Storage::disk('local')->delete($record->{$field.'_path'});
                }
                $file = $request->file($field);
                $data[$field.'_path'] = $file->store('gynecology_ultrasound_exams', 'local');
                $data[$field.'_original_filename'] = $file->getClientOriginalName();
            }
        }

        return $data;
    }
}
