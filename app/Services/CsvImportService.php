<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;

class CsvImportService
{
    protected array $studentHeaders  = ['name', 'email', 'department_id', 'study_type', 'entry_year'];
    protected array $lecturerHeaders = ['name', 'email', 'department_id'];

    public function parse(UploadedFile $file, string $role): array
    {
        $expectedHeaders = $role === 'student'
            ? $this->studentHeaders
            : $this->lecturerHeaders;

        $handle = fopen($file->getRealPath(), 'r');
        $header = array_map('trim', fgetcsv($handle));

        // Validate headers
        if ($header !== $expectedHeaders) {
            fclose($handle);
            return [
                'valid'   => [],
                'invalid' => [],
                'error'   => 'Invalid CSV format. Expected headers: ' . implode(', ', $expectedHeaders),
            ];
        }

        $valid   = [];
        $invalid = [];
        $row     = 2; // Start at row 2 (after header)

        while (($line = fgetcsv($handle)) !== false) {
            $data = array_combine($header, array_map('trim', $line));
            $data['role'] = $role;

            $validator = $this->validateRow($data, $role);

            if ($validator->fails()) {
                $invalid[] = [
                    'row'    => $row,
                    'data'   => $data,
                    'errors' => $validator->errors()->all(),
                ];
            } else {
                $valid[] = $data;
            }

            $row++;
        }

        fclose($handle);

        return compact('valid', 'invalid');
    }

    protected function validateRow(array $data, string $role): \Illuminate\Validation\Validator
    {
        $rules = [
            'name'          => ['required', 'string', 'max:255'],
            'email'         => ['required', 'email', 'unique:users,email'],
            'department_id' => ['required', 'integer', 'exists:departments,id'],
        ];

        if ($role === 'student') {
            $rules['study_type'] = ['required', 'in:Undergraduate,Postgraduate'];
            $rules['entry_year'] = ['required', 'digits:4', 'integer', 'min:2000', 'max:' . now()->year];
        }

        return Validator::make($data, $rules);
    }
}