<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator as ValidatorFactory;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use RuntimeException;

class CsvImportService
{
    /** @var array<int, string> */
    private const STUDENT_HEADERS = ['name', 'email', 'department_id', 'study_type', 'entry_year'];

    /** @var array<int, string> */
    private const LECTURER_HEADERS = ['name', 'email', 'department_id', 'prefix', 'highest_qualification', 'specialization'];

    /**
     * Parse and validate an import file.
     *
     * @return array{valid: array<int, array<string, mixed>>, invalid: array<int, array<string, mixed>>, error?: string}
     */
    public function parse(UploadedFile $file, string $role): array
    {
        $expectedHeaders = $role === 'student'
            ? self::STUDENT_HEADERS
            : self::LECTURER_HEADERS;

        $handle = @fopen($file->getRealPath(), 'r');

        if ($handle === false) {
            throw new RuntimeException('The uploaded file could not be opened.');
        }

        try {
            $header = fgetcsv($handle);

            if ($header === false) {
                return ['valid' => [], 'invalid' => [], 'error' => 'The CSV file is empty.'];
            }

            // Strip a UTF-8 BOM, which Excel writes and which otherwise stops
            // the first header from ever matching.
            $header = array_map(
                fn ($column) => trim((string) preg_replace('/^\x{FEFF}/u', '', (string) $column)),
                $header
            );

            if ($header !== $expectedHeaders) {
                return [
                    'valid' => [],
                    'invalid' => [],
                    'error' => 'Invalid CSV format. Expected headers: '.implode(', ', $expectedHeaders),
                ];
            }

            return $this->parseRows($handle, $header, $role);
        } finally {
            fclose($handle);
        }
    }

    /**
     * @param  resource  $handle
     * @param  array<int, string>  $header
     * @return array{valid: array<int, array<string, mixed>>, invalid: array<int, array<string, mixed>>}
     */
    private function parseRows($handle, array $header, string $role): array
    {
        $valid = [];
        $invalid = [];
        $rowNumber = 2; // row 1 is the header

        // Emails already claimed by an earlier row in *this file*. The `unique`
        // rule only consults the database, so a file containing the same
        // address twice previously passed validation and then threw a raw
        // QueryException part-way through the import.
        $seenEmails = [];

        while (($line = fgetcsv($handle)) !== false) {
            // Skip blank lines rather than reporting them as errors.
            if ($line === [null] || $line === ['']) {
                $rowNumber++;

                continue;
            }

            if (count($line) !== count($header)) {
                $invalid[] = [
                    'row' => $rowNumber,
                    'data' => $line,
                    'errors' => ['Expected '.count($header).' columns, found '.count($line).'.'],
                ];
                $rowNumber++;

                continue;
            }

            $data = array_combine($header, array_map(fn ($value) => trim((string) $value), $line));
            $data['role'] = $role;

            $validator = $this->validateRow($data, $role, $seenEmails);

            if ($validator->fails()) {
                $invalid[] = [
                    'row' => $rowNumber,
                    'data' => $data,
                    'errors' => $validator->errors()->all(),
                ];
            } else {
                $seenEmails[] = strtolower((string) $data['email']);
                $data['__row'] = $rowNumber;
                $valid[] = $data;
            }

            $rowNumber++;
        }

        return ['valid' => $valid, 'invalid' => $invalid];
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, string>  $seenEmails
     */
    private function validateRow(array $data, string $role, array $seenEmails): Validator
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email:rfc',
                'unique:users,email',
                Rule::notIn($seenEmails),
            ],
            'department_id' => ['required', 'integer', 'exists:departments,id'],
        ];

        if ($role === 'student') {
            $rules['study_type'] = ['required', 'in:Undergraduate,Postgraduate'];
            $rules['entry_year'] = ['required', 'digits:4', 'integer', 'min:2000', 'max:'.now()->year];
        }

        if ($role === 'lecturer') {
            $rules['prefix'] = ['required', 'in:Dr.,Prof.,Mr.,Mrs.,Ms.,Engr.,Rev.'];
            $rules['highest_qualification'] = ['required', 'string', 'max:100'];
            $rules['specialization'] = ['nullable', 'string', 'max:100'];
        }

        return ValidatorFactory::make($data, $rules, [
            'email.not_in' => 'This email address appears more than once in the file.',
        ]);
    }
}
