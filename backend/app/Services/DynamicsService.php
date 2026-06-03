<?php

namespace App\Services;

use App\Models\School;
use App\Models\SchoolClass;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class DynamicsService
{
    protected ?string $accessToken = null;

    protected string $baseUrl;

    protected string $apiVersion;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('dynamics.url', ''), '/');
        $this->apiVersion = config('dynamics.api_version', 'v9.2');
    }

    public function isEnabled(): bool
    {
        return config('dynamics.enabled', false)
            && filled(config('dynamics.url'))
            && filled(config('dynamics.azure.client_id'))
            && filled(config('dynamics.azure.client_secret'));
    }

    public function getAccessToken(): ?string
    {
        if ($this->accessToken) {
            return $this->accessToken;
        }

        $cacheKey = 'dynamics_access_token';
        $this->accessToken = Cache::remember($cacheKey, 55 * 60, function () {
            return $this->requestAccessToken()['access_token'] ?? null;
        });

        return $this->accessToken;
    }

    /**
     * @return array{success: bool, access_token?: string, expires_in?: int, error?: string, status?: int}
     */
    public function requestAccessToken(bool $fresh = false): array
    {
        if ($fresh) {
            $this->clearTokenCache();
        }

        $tenant = config('dynamics.azure.tenant_id');
        $clientId = config('dynamics.azure.client_id');
        $clientSecret = config('dynamics.azure.client_secret');

        if (! $tenant || ! $clientId || ! $clientSecret) {
            return [
                'success' => false,
                'error' => 'Dynamics Azure credentials are incomplete. Check DYNAMICS_TENANT_ID, DYNAMICS_CLIENT_ID, and DYNAMICS_CLIENT_SECRET.',
            ];
        }

        $url = "https://login.microsoftonline.com/{$tenant}/oauth2/v2.0/token";
        $scope = $this->baseUrl ? "{$this->baseUrl}/.default" : 'https://org.crm.dynamics.com/.default';

        try {
            /** @var Response $response */
            $response = Http::asForm()
                ->timeout((int) config('dynamics.timeout', 30))
                ->connectTimeout((int) config('dynamics.connect_timeout', 10))
                ->post($url, [
                    'grant_type' => 'client_credentials',
                    'client_id' => $clientId,
                    'client_secret' => $clientSecret,
                    'scope' => $scope,
                ]);
        } catch (ConnectionException $exception) {
            Log::warning('Dynamics OAuth connection failed', [
                'message' => $exception->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => 'Unable to reach Microsoft login to obtain a Dynamics access token.',
            ];
        }

        if (! $response->successful()) {
            $body = $response->json();
            $error = is_array($body)
                ? ($body['error_description'] ?? $body['error'] ?? $response->body())
                : $response->body();

            Log::warning('Dynamics OAuth token request failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return [
                'success' => false,
                'status' => $response->status(),
                'error' => is_string($error) ? $error : 'Unable to obtain a Dynamics access token.',
            ];
        }

        $data = $response->json();
        $accessToken = $data['access_token'] ?? null;

        if (! $accessToken) {
            return [
                'success' => false,
                'error' => 'Token response did not include access_token.',
            ];
        }

        Cache::put('dynamics_access_token', $accessToken, 55 * 60);
        $this->accessToken = $accessToken;

        return [
            'success' => true,
            'access_token' => $accessToken,
            'expires_in' => isset($data['expires_in']) ? (int) $data['expires_in'] : null,
        ];
    }

    public function get(string $entity, array $query = []): array
    {
        $token = $this->getAccessToken();
        if (! $token) {
            return [];
        }

        $path = "/api/data/{$this->apiVersion}/{$entity}";
        $url = $this->baseUrl . $path;

        try {
            /** @var Response $response */
            $response = $this->httpClient($token)->get($url, $query);
        } catch (ConnectionException $exception) {
            Log::warning('Dynamics API connection failed', [
                'entity' => $entity,
                'message' => $exception->getMessage(),
            ]);

            return [];
        } catch (Throwable $exception) {
            Log::warning('Dynamics API request error', [
                'entity' => $entity,
                'message' => $exception->getMessage(),
            ]);

            return [];
        }

        if (! $response->successful()) {
            Log::warning('Dynamics API request failed', [
                'entity' => $entity,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return [];
        }

        $json = $response->json();

        return $json['value'] ?? [];
    }

    private function httpClient(?string $token = null): PendingRequest
    {
        $client = Http::timeout((int) config('dynamics.timeout', 30))
            ->connectTimeout((int) config('dynamics.connect_timeout', 10))
            ->accept('application/json')
            ->withHeaders(['OData-MaxVersion' => '4.0', 'OData-Version' => '4.0']);

        if ($token) {
            $client = $client->withToken($token);
        }

        return $client;
    }

    /**
     * Read a navigation property collection, e.g. ses_rooms({id})/cr0dc_ses_student_ClassStream_ses_room.
     *
     * @return list<array<string, mixed>>
     */
    public function getRelated(string $relativePath, array $query = []): array
    {
        $token = $this->getAccessToken();
        if (! $token) {
            return [];
        }

        $relativePath = ltrim($relativePath, '/');
        $url = $this->baseUrl."/api/data/{$this->apiVersion}/{$relativePath}";

        try {
            /** @var Response $response */
            $response = $this->httpClient($token)->get($url, $query);
        } catch (Throwable $exception) {
            Log::warning('Dynamics related collection request failed', [
                'path' => $relativePath,
                'message' => $exception->getMessage(),
            ]);

            return [];
        }

        if (! $response->successful()) {
            Log::warning('Dynamics related collection request failed', [
                'path' => $relativePath,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return [];
        }

        $json = $response->json();

        return $json['value'] ?? [];
    }

    public function getMetadata(string $path, array $query = []): array
    {
        $token = $this->getAccessToken();
        if (! $token) {
            return [];
        }

        $path = ltrim($path, '/');
        $fullPath = "/api/data/{$this->apiVersion}/{$path}";
        $url = $this->baseUrl . $fullPath;

        try {
            /** @var Response $response */
            $response = $this->httpClient($token)->get($url, $query);
        } catch (Throwable $exception) {
            Log::warning('Dynamics metadata connection failed', [
                'path' => $path,
                'message' => $exception->getMessage(),
            ]);

            return [];
        }

        if (! $response->successful()) {
            Log::warning('Dynamics metadata request failed', [
                'path' => $path,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return [];
        }

        $json = $response->json();

        if (isset($json['value'])) {
            return $json['value'];
        }

        return is_array($json) ? $json : [];
    }

    public function getEntityDefinitions(?string $logicalName = null, array $select = [], bool $expandAttributes = false): array
    {
        $defaultSelect = ['LogicalName', 'SchemaName', 'EntitySetName', 'PrimaryIdAttribute', 'PrimaryNameAttribute'];
        $select = $select ?: $defaultSelect;
        $query = ['$select' => implode(',', $select)];

        if ($logicalName !== null) {
            $path = "EntityDefinitions(LogicalName='{$logicalName}')";
            if ($expandAttributes) {
                $query['$expand'] = "Attributes(\$select=LogicalName,AttributeType,SchemaName)";
            }
        } else {
            $path = 'EntityDefinitions';
        }

        return $this->getMetadata($path, $query);
    }

    public function getRecordById(string $entity, string $id): ?array
    {
        $token = $this->getAccessToken();
        if (! $token) {
            return null;
        }

        $path = "/api/data/{$this->apiVersion}/{$entity}({$id})";
        $url = $this->baseUrl . $path;

        /** @var Response $response */
        $response = Http::withToken($token)
            ->accept('application/json')
            ->withHeaders(['OData-MaxVersion' => '4.0', 'OData-Version' => '4.0'])
            ->get($url);

        if (! $response->successful()) {
            Log::debug('Dynamics getRecordById failed', [
                'entity' => $entity,
                'id' => $id,
                'status' => $response->status(),
            ]);

            return null;
        }

        return $response->json();
    }

    public function getStudentByAdmissionNo(string $admNo, ?string $localSchoolId = null): ?array
    {
        $admNo = trim($admNo);
        if ($admNo === '') {
            return null;
        }

        $studentEntity = config('dynamics.entities.student', 'ses_students');
        $admNoCol = config('dynamics.student_admission_no_column', 'piu_admissionnumber');
        $schoolNameCol = config('dynamics.student_school_name_column', 'ses_schoolname');

        $filters = [$admNoCol . " eq '" . str_replace("'", "''", $admNo) . "'"];

        if ($localSchoolId !== null && $localSchoolId !== '') {
            $school = School::query()->find($localSchoolId);
            if ($school && $school->name) {
                $filters[] = $schoolNameCol . " eq '" . str_replace("'", "''", $school->name) . "'";
            }
        }

        $studentQuery = [
            '$select' => '_ses_contactid_value,_ses_schoolid_value,' . $admNoCol,
            '$filter' => implode(' and ', $filters),
            '$top' => 1,
        ];

        $studentRows = $this->get($studentEntity, $studentQuery);
        if (empty($studentRows)) {
            return null;
        }

        $contactId = $studentRows[0]['_ses_contactid_value'] ?? null;
        $schoolId = $studentRows[0]['_ses_schoolid_value'] ?? null;
        if (! $contactId || ! $schoolId) {
            return null;
        }

        $cols = config('dynamics.student_columns', []);

        $selectCols = [
            $cols['id'] ?? 'contactid',
            $cols['first_name'] ?? 'firstname',
            $cols['last_name'] ?? 'lastname',
            $cols['gender'] ?? 'gendercode',
            $cols['email'] ?? 'emailaddress1',
            $cols['phone'] ?? 'mobilephone',
            $cols['dob'] ?? 'birthdate',
        ];

        $query = [
            '$select' => implode(',', array_unique($selectCols)),
            '$filter' => 'contactid' . " eq '" . str_replace("'", "''", $contactId) . "'",
            '$top' => 1,
        ];

        $entity = 'contacts';
        $rows = $this->get($entity, $query);

        if (empty($rows)) {
            return null;
        }

        $contactRow = $rows[0];
        // Patch in school + admission number from student entity row.
        $contactRow['_ses_schoolid_value'] = $schoolId;
        $admCol = $cols['admission_no'] ?? 'ses_accountnumber';
        $contactRow[$admCol] = $studentRows[0][$admNoCol] ?? $contactRow[$admCol] ?? null;

        $mapped = $this->mapSingleStudentToApp($contactRow);
        if (! $mapped) {
            return null;
        }

        $dynamicsSchoolId = $mapped['dynamics_school_id'] ?? null;
        if ($dynamicsSchoolId) {
            $mapped['school_id'] = $this->resolveDynamicsSchoolToLocalId($dynamicsSchoolId);
            unset($mapped['dynamics_school_id']);
        }

        return $mapped;
    }

    public function getContactByName(string $firstName, string $lastName): ?array
    {
        $firstName = trim($firstName);
        $lastName = trim($lastName);
        if ($firstName === '' || $lastName === '') {
            return null;
        }

        $cols = config('dynamics.student_columns', []);
        $firstCol = $cols['first_name'] ?? 'firstname';
        $lastCol = $cols['last_name'] ?? 'lastname';
        $selectCols = [
            $cols['id'] ?? 'contactid',
            $cols['first_name'] ?? 'firstname',
            $cols['last_name'] ?? 'lastname',
            $cols['gender'] ?? 'gendercode',
            $cols['email'] ?? 'emailaddress',
            $cols['phone'] ?? 'mobilephone',
            $cols['dob'] ?? 'birthdate',
        ];
        if (! empty($cols['school'])) {
            $selectCols[] = $cols['school'];
        }

        $entity = config('dynamics.entities.student', 'contacts');
        $filterFirst = $firstCol . " eq '" . str_replace("'", "''", $firstName) . "'";
        $filterLast = $lastCol . " eq '" . str_replace("'", "''", $lastName) . "'";
        $query = [
            '$select' => implode(',', array_unique($selectCols)),
            '$filter' => $filterFirst . ' and ' . $filterLast,
            '$top' => 1,
        ];

        $rows = $this->get($entity, $query);
        if (empty($rows)) {
            return null;
        }

        $mapped = $this->mapSingleStudentToApp($rows[0]);
        if (! $mapped) {
            return null;
        }

        $dynamicsSchoolId = $mapped['dynamics_school_id'] ?? null;
        if ($dynamicsSchoolId) {
            $mapped['school_id'] = $this->resolveDynamicsSchoolToLocalId($dynamicsSchoolId);
            unset($mapped['dynamics_school_id']);
        }

        return $mapped;
    }

    public function getStudentByName(string $fullName, ?string $localSchoolId = null): ?array
    {
        $fullName = trim($fullName);
        if ($fullName === '') {
            return null;
        }

        $studentEntity = config('dynamics.entities.student', 'ses_students');
        $fullNameCol = config('dynamics.student_full_name_column', 'ses_studentname');

        $nameFilter = "contains($fullNameCol, '" . str_replace("'", "''", $fullName) . "')";

        $filters = [$nameFilter];

        if ($localSchoolId !== null && $localSchoolId !== '') {
            $school = School::query()->find($localSchoolId);
            if ($school && $school->name) {
                $schoolNameCol = config('dynamics.student_school_name_column', 'ses_schoolname');
                $filters[] = $schoolNameCol . " eq '" . str_replace("'", "''", $school->name) . "'";
            }
        }

        $admNoCol = config('dynamics.student_admission_no_column', 'piu_admissionnumber');
        $Studentquery = [
            '$select' => '_ses_contactid_value,_ses_schoolid_value,' . $admNoCol . ',' . $fullNameCol,
            '$filter' => implode(' and ', $filters),
            '$top' => 1,
        ];

        $Studentrows = $this->get($studentEntity, $Studentquery);
        if (empty($Studentrows)) {
            return null;
        }

        $studentRow = $Studentrows[0];
        $contactId = $studentRow['_ses_contactid_value'] ?? null;
        $dynamicsSchoolId = $studentRow['_ses_schoolid_value'] ?? null;
        if (! $contactId || ! $dynamicsSchoolId) {
            return null;
        }

        $cols = config('dynamics.student_columns', []);
        $selectCols = [
            $cols['id'] ?? 'contactid',
            $cols['first_name'] ?? 'firstname',
            $cols['last_name'] ?? 'lastname',
            $cols['gender'] ?? 'gendercode',
            $cols['email'] ?? 'emailaddress1',
            $cols['phone'] ?? 'mobilephone',
            $cols['dob'] ?? 'birthdate',
        ];

        $query = [
            '$select' => implode(',', array_unique($selectCols)),
            '$filter' => 'contactid' . " eq '" . str_replace("'", "''", $contactId) . "'",
            '$top' => 1,
        ];

        $rows = $this->get('contacts', $query);
        if (empty($rows)) {
            return null;
        }

        $contactRow = $rows[0];
        $contactRow['_ses_schoolid_value'] = $dynamicsSchoolId;
        $admCol = $cols['admission_no'] ?? 'ses_accountnumber';
        $admNoFromStudent = config('dynamics.student_admission_no_column', 'piu_admissionnumber');
        $contactRow[$admCol] = $studentRow[$admNoFromStudent] ?? $contactRow[$admCol] ?? null;

        $mapped = $this->mapSingleStudentToApp($contactRow);
        if (! $mapped) {
            return null;
        }

        $mappedDynamicsSchoolId = $mapped['dynamics_school_id'] ?? $dynamicsSchoolId;
        if ($mappedDynamicsSchoolId) {
            $mapped['school_id'] = $this->resolveDynamicsSchoolToLocalId($mappedDynamicsSchoolId);
            unset($mapped['dynamics_school_id']);
        }

        if ($localSchoolId !== null && $localSchoolId !== '') {
            $tenantSchool = School::query()->find($localSchoolId);
            $dynamicsSchoolName = $this->getDynamicsSchoolName($mappedDynamicsSchoolId ?? '');
            if (! $tenantSchool || ! $dynamicsSchoolName) {
                return null;
            }
            if (trim(strtolower((string) $dynamicsSchoolName)) !== trim(strtolower($tenantSchool->name))) {
                return null;
            }
        }

        return $mapped;
    }

    public function getStaffByName(string $name): ?array
    {
        $name = trim($name);
        if ($name === '') {
            return null;
        }

        $cols    = config('dynamics.staff_columns', []);
        $nameCol = $cols['name']  ?? 'ses_staffname';
        $entity  = config('dynamics.staff_entity', 'ses_staffs');

        $rows = $this->get($entity, [
            '$filter' => "contains($nameCol, '" . str_replace("'", "''", $name) . "')",
            '$top'    => 1,
        ]);

        if (empty($rows)) {
            return null;
        }

        $mapped = $this->mapSingleStaffToApp($rows[0]);

        $contactId = $rows[0]['_ses_contactid_value'] ?? null;
        if ($mapped && $contactId) {
            $mapped = $this->enrichStaffFromContact($mapped, $contactId);
        }

        return $mapped;
    }

    /**
     * Search staff by name — returns up to 10 matches.
     * Splits input into words so "EVALYNE KARIUKI" matches "EVALYNE NYAKIO KARIUKI".
     *
     * NOTE: School scoping is intentionally disabled. Searches across all schools in Dynamics.
     * TODO: Re-enable once School.dynamics_id is reliably populated.
     * When re-enabling use: "_ses_schoolid_value eq (guid'{$school->dynamics_id}')"
     */
    public function searchStaffByName(string $name, ?string $localSchoolId = null): array
    {
        $name = trim($name);
        if ($name === '') {
            return [];
        }

        $cols    = config('dynamics.staff_columns', []);
        $nameCol = $cols['name'] ?? 'ses_staffname';
        $entity  = config('dynamics.staff_entity', 'ses_staffs');

        $words   = preg_split('/\s+/', $name, -1, PREG_SPLIT_NO_EMPTY);
        $filters = array_map(
            fn ($word) => "contains($nameCol, '" . str_replace("'", "''", $word) . "')",
            $words
        );

        // TODO: re-enable school scoping once dynamics_id is reliably populated on School records.
        // Correct OData syntax for lookup field GUID filter:
        // $filters[] = "_ses_schoolid_value eq (guid'{$school->dynamics_id}')";

        // Log::info('[searchStaffByName] query', [
        //     'filter' => implode(' and ', $filters),
        // ]);

        $rows = $this->get($entity, [
            '$filter' => implode(' and ', $filters),
            '$top'    => 10,
        ]);

        //Log::info('[searchStaffByName] result', ['count' => count($rows)]);

        if (empty($rows)) {
            return [];
        }

        $results = [];
        foreach ($rows as $row) {
            $mapped = $this->mapSingleStaffToApp($row);
            if (! $mapped) {
                continue;
            }
            $contactId = $row['_ses_contactid_value'] ?? null;
            if ($contactId) {
                $mapped = $this->enrichStaffFromContact($mapped, $contactId);
            }
            $dynamicsSchoolId = $mapped['dynamics_school_id'] ?? null;
            if ($dynamicsSchoolId) {
                $mapped['school_id'] = $this->resolveDynamicsSchoolToLocalId($dynamicsSchoolId);
                unset($mapped['dynamics_school_id']);
            }
            $results[] = $mapped;
        }

        return $results;
    }

    public function getStaffByIdNumber(string $idNumber): ?array
    {
        $idNumber = trim($idNumber);
        if ($idNumber === '') {
            return null;
        }

        $cols     = config('dynamics.staff_columns', []);
        $idNumCol = $cols['id_number'] ?? 'cr0dc_nationalidpassport';
        $entity   = config('dynamics.staff_entity', 'ses_staffs');

        $rows = $this->get($entity, [
            '$filter' => $idNumCol . " eq '" . str_replace("'", "''", $idNumber) . "'",
            '$top'    => 1,
        ]);

        if (empty($rows)) {
            return null;
        }

        $mapped = $this->mapSingleStaffToApp($rows[0]);
        if (! $mapped) {
            return null;
        }

        $contactId = $rows[0]['_ses_contactid_value'] ?? null;
        if ($contactId) {
            $mapped = $this->enrichStaffFromContact($mapped, $contactId);
        }

        $dynamicsSchoolId = $mapped['dynamics_school_id'] ?? null;
        if ($dynamicsSchoolId) {
            $mapped['school_id'] = $this->resolveDynamicsSchoolToLocalId($dynamicsSchoolId);
            unset($mapped['dynamics_school_id']);
        }

        return $mapped;
    }

    /**
     * Search faculty by name — returns up to 10 matches.
     * Splits input into words so "SAMY NJORG" matches "SAMY KAMA NJORG".
     *
     * NOTE: School scoping is intentionally disabled. Searches across all schools in Dynamics.
     * TODO: Re-enable once School.dynamics_id is reliably populated.
     * When re-enabling use: "_ses_schoolid_value eq (guid'{$school->dynamics_id}')"
     */
    public function searchFacultyByName(string $name, ?string $localSchoolId = null): array
    {
        $name = trim($name);
        if ($name === '') {
            return [];
        }

        $cols    = config('dynamics.faculty_columns', []);
        $nameCol = $cols['name'] ?? 'ses_facultyname';
        $entity  = config('dynamics.faculty_entity', 'ses_faculties');

        $words   = preg_split('/\s+/', $name, -1, PREG_SPLIT_NO_EMPTY);
        $filters = array_map(
            fn ($word) => "contains($nameCol, '" . str_replace("'", "''", $word) . "')",
            $words
        );

        // TODO: re-enable school scoping once dynamics_id is reliably populated on School records.
        // Correct OData syntax for lookup field GUID filter:
        // $filters[] = "_ses_schoolid_value eq (guid'{$school->dynamics_id}')";

        // Log::info('[searchFacultyByName] query', [
        //     'entity'        => $entity,
        //     'filter'        => implode(' and ', $filters),
        //     'localSchoolId' => $localSchoolId,
        // ]);

        $rows = $this->get($entity, [
            '$filter' => implode(' and ', $filters),
            '$top'    => 10,
        ]);

        // Log::info('[searchFacultyByName] result', ['count' => count($rows)]);

        if (empty($rows)) {
            return [];
        }

        $results = [];
        foreach ($rows as $row) {
            $mapped = $this->mapSingleFacultyToApp($row);
            if (! $mapped) {
                continue;
            }
            $contactId = $row['_ses_contactid_value'] ?? null;
            if ($contactId) {
                $mapped = $this->enrichStaffFromContact($mapped, $contactId);
            }
            $dynamicsSchoolId = $mapped['dynamics_school_id'] ?? null;
            if ($dynamicsSchoolId) {
                $mapped['school_id'] = $this->resolveDynamicsSchoolToLocalId($dynamicsSchoolId);
                unset($mapped['dynamics_school_id']);
            }
            $results[] = $mapped;
        }

        return $results;
    }

    protected function mapSingleFacultyToApp(array $row): ?array
    {
        $cols       = config('dynamics.faculty_columns', []);
        $nameCol    = $cols['name']       ?? 'ses_facultyname';
        $facultyNo  = $cols['faculty_no'] ?? 'ses_faculty';
        $schoolCol  = $cols['school']     ?? '_ses_schoolid_value';

        $fullName = trim($row[$nameCol] ?? '');
        $parts    = preg_split('/\s+/', $fullName, 2, PREG_SPLIT_NO_EMPTY);
        $first    = $parts[0] ?? '';
        $last     = $parts[1] ?? '';

        $result = [
            'first_name'      => $first,
            'last_name'       => $last,
            'adm_or_staff_no' => $row[$facultyNo] ?? null,
            'gender'          => null,
            'email'           => $row[$cols['email'] ?? 'emailaddress'] ?? null,
            'phone'           => null,
            'dob'             => null,
            'person_type'     => 'STAFF',
            'status'          => 'ACTIVE',
        ];

        $dynamicsSchoolId = $row[$schoolCol] ?? $row['_ses_schoolid_value'] ?? null;
        if ($dynamicsSchoolId) {
            $result['dynamics_school_id'] = $dynamicsSchoolId;
        }

        return $result;
    }

    protected function enrichStaffFromContact(array $mapped, string $contactId): array
    {
        $contact = $this->get('contacts', [
            '$select' => 'gendercode,mobilephone,birthdate',
            '$filter' => "contactid eq '" . str_replace("'", "''", $contactId) . "'",
            '$top'    => 1,
        ]);

        if (!empty($contact[0])) {
            $c = $contact[0];
            $gendercode = $c['gendercode'] ?? null;
            $mapped['gender'] = $gendercode == 2 ? 'female' : ($gendercode == 1 ? 'male' : null);
            $mapped['phone']  = $c['mobilephone'] ?? null;
            $mapped['dob']    = $c['birthdate']   ?? null;
        }

        return $mapped;
    }

    protected function mapSingleStaffToApp(array $row): ?array
    {
        $cols       = config('dynamics.staff_columns', []);
        $nameCol    = $cols['name']     ?? 'ses_staffname';
        $staffNoCol = $cols['staff_no'] ?? 'ses_staff';
        $schoolCol  = $cols['school']   ?? '_ses_schoolid_value';

        $fullName = trim($row[$nameCol] ?? '');
        $parts    = preg_split('/\s+/', $fullName, 2, PREG_SPLIT_NO_EMPTY);
        $first    = $parts[0] ?? '';
        $last     = $parts[1] ?? '';

        $result = [
            'first_name'      => $first,
            'last_name'       => $last,
            'adm_or_staff_no' => $row[$staffNoCol] ?? null,
            'gender'          => null,
            'email'           => $row[$cols['email'] ?? 'emailaddress'] ?? null,
            'phone'           => null,
            'dob'             => null,
            'person_type'     => 'STAFF',
            'status'          => 'ACTIVE',
        ];

        $dynamicsSchoolId = $row[$schoolCol] ?? $row['_ses_schoolid_value'] ?? null;
        if ($dynamicsSchoolId) {
            $result['dynamics_school_id'] = $dynamicsSchoolId;
        }

        return $result;
    }

    public function resolveDynamicsSchoolToLocalId(string $dynamicsSchoolId): ?string
    {
        $dynamicsSchoolId = trim($dynamicsSchoolId);
        if ($dynamicsSchoolId === '') {
            return null;
        }

        $schoolEntity = config('dynamics.school_entity', 'accounts');
        $record = $this->getRecordById($schoolEntity, $dynamicsSchoolId);
        if (! $record) {
            return null;
        }

        $nameCol = config('dynamics.school_name_column', 'name');
        $name = $record[$nameCol] ?? null;
        if (! $name) {
            return null;
        }

        $school = School::query()->where('name', $name)->first();

        return $school ? (string) $school->id : null;
    }

    public function getDynamicsSchoolName(string $dynamicsSchoolId): ?string
    {
        $dynamicsSchoolId = trim($dynamicsSchoolId);
        if ($dynamicsSchoolId === '') {
            return null;
        }

        $schoolEntity = config('dynamics.school_entity', 'accounts');
        $record = $this->getRecordById($schoolEntity, $dynamicsSchoolId);
        if (! $record) {
            return null;
        }

        $nameCol = config('dynamics.school_name_column', 'name');

        return $record[$nameCol] ?? null;
    }

    public function resolveLocalSchoolToDynamicsId(?string $localSchoolId): ?string
    {
        if ($localSchoolId === null || $localSchoolId === '') {
            return null;
        }

        $school = School::query()->find($localSchoolId);
        if (! $school) {
            return null;
        }

        $nameCol = config('dynamics.school_name_column', 'name');
        $entity = config('dynamics.school_entity', 'accounts');
        $idCol = config('dynamics.school_entity_id', 'accountid');

        $filter = $nameCol . " eq '" . str_replace("'", "''", $school->name) . "'";
        $query = [
            '$select' => $idCol,
            '$filter' => $filter,
            '$top' => 1,
        ];

        $rows = $this->get($entity, $query);
        if (empty($rows)) {
            return null;
        }

        return $rows[0][$idCol] ?? null;
    }

    protected function mapSingleStudentToApp(array $row): ?array
    {
        $cols = config('dynamics.student_columns', []);
        $idCol = $cols['id'] ?? 'contactid';
        $first = $cols['first_name'] ?? 'firstname';
        $last = $cols['last_name'] ?? 'lastname';
        $adm = $cols['admission_no'] ?? 'piu_admissionnumber';
        $genderCol = $cols['gender'] ?? 'gendercode';
        $schoolCol = $cols['school'] ?? null;
        $streamCol = $cols['stream'] ?? null;

        $gendercode = $row[$genderCol] ?? null;
        $gender = $gendercode == 2 ? 'female' : ($gendercode == 1 ? 'male' : null);

        $result = [
            'first_name' => trim($row[$first] ?? ''),
            'last_name' => trim($row[$last] ?? ''),
            'adm_or_staff_no' => $row[$adm] ?? null,
            'gender' => $gender,
            'email' => $row['emailaddress'] ?? $row[$cols['email'] ?? 'emailaddress'] ?? null,
            'phone' => $row['mobilephone'] ?? $row[$cols['phone'] ?? 'mobilephone'] ?? null,
            'dob' => $row['birthdate'] ?? $row[$cols['dob'] ?? 'birthdate'] ?? null,
            'person_type' => 'STUDENT',
            'status' => 'ACTIVE',
        ];

        if ($schoolCol) {
            $dynamicsSchoolId = $row[$schoolCol] ?? $row['_' . $schoolCol . '_value'] ?? null;
            if ($dynamicsSchoolId) {
                $result['dynamics_school_id'] = $dynamicsSchoolId;
            }
        }
        if ($streamCol && isset($row[$streamCol])) {
            $result['enrolment.stream'] = $row[$streamCol];
        }

        return $result;
    }

    public function getStudents(?string $schoolId = null, ?string $gender = null): array
    {
        $entity = config('dynamics.entities.student', 'contacts');
        $query = [
            '$select' => 'contactid,firstname,lastname,fullname,emailaddress1,mobilephone,birthdate,gendercode',
            '$top' => 500,
        ];

        $filters = [];
        if ($gender) {
            $code = $gender === 'female' ? 2 : 1;
            $filters[] = "gendercode eq {$code}";
        }
        if ($filters !== []) {
            $query['$filter'] = implode(' and ', $filters);
        }

        $rows = $this->get($entity, $query);

        return $this->mapStudentsToApp($rows);
    }

    public function getStudentsForClass(SchoolClass $class): array
    {
        $entity = config('dynamics.entities.student', 'ses_students');
        $cols = config('dynamics.student_columns', []);

        $byRoom = $this->getStudentsByRoom(
            roomName: $class->section ?: null,
            schoolName: $this->resolveDataverseSchoolName((string) $class->school_id),
        );

        if ($byRoom !== []) {
            return $byRoom;
        }

        return $this->getStudentsByClassNameMatch(
            entity: $entity,
            cols: $cols,
            schoolName: $this->resolveDataverseSchoolName((string) $class->school_id),
            gradeLevel: $class->grade_level,
            stream: $class->section,
        );
    }

    public function getRoomsBySchool(string $schoolName): array
    {
        $schoolName = trim($schoolName);
        if ($schoolName === '') {
            return [];
        }

        $entity = config('dynamics.entities.rooms', 'ses_rooms');
        $escaped = str_replace("'", "''", $schoolName);
        $filters = [
            "ses_schoolname eq '{$escaped}' and statecode eq 0",
            "ses_schoolname eq '{$escaped}'",
        ];

        foreach ($filters as $filter) {
            $rows = $this->get($entity, [
                '$select' => 'ses_roomid,ses_room,ses_roomname,ses_schoolname,ses_locationname,ses_roomcapacity',
                '$filter' => $filter,
                '$top' => 500,
            ]);
            if ($rows !== []) {
                usort($rows, fn ($a, $b) => strcmp((string) ($a['ses_room'] ?? ''), (string) ($b['ses_room'] ?? '')));
                return $rows;
            }
        }

        return [];
    }

    public function getStudentsByRoom(?string $roomId = null, ?string $roomName = null, ?string $schoolName = null): array
    {
        $roomId = $roomId ? trim($roomId) : null;
        $roomName = $roomName ? trim($roomName) : null;
        $schoolName = $schoolName ? trim($schoolName) : null;

        if (! $roomId && ! $roomName) {
            return [];
        }

        $entity = config('dynamics.entities.student', 'ses_students');
        $cols = config('dynamics.student_columns', []);
        $selectFields = $this->studentSelectFields($cols);

        if ($roomId && ! $roomName) {
            $roomRecord = $this->getRoomRecordById($roomId);
            if ($roomRecord) {
                $roomName = trim((string) ($roomRecord['ses_roomname'] ?? $roomRecord['ses_room'] ?? '')) ?: $roomName;
                $schoolName = $schoolName ?: trim((string) ($roomRecord['ses_schoolname'] ?? '')) ?: null;
            }
        }

        if (! $roomId && $roomName) {
            $roomId = $this->resolveRoomIdByName($roomName, $schoolName);
        }

        if ($roomId) {
            $rows = $this->fetchStudentsViaRoomNavigation($selectFields, $roomId);
            if ($rows === []) {
                $rows = $this->fetchStudentsByRoomGuid($entity, $selectFields, $roomId, $cols);
            }
            if ($rows !== []) {
                return $this->mapStudentsToApp($rows);
            }
        }

        if ($roomName) {
            foreach ($this->buildStudentStreamMatchFilters($roomName, $schoolName) as $filter) {
                $rows = $this->get($entity, [
                    '$select' => implode(',', array_unique($selectFields)),
                    '$filter' => $filter,
                    '$top' => 500,
                ]);

                if ($rows !== []) {
                    return $this->mapStudentsToApp($rows);
                }
            }
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $cols
     * @return list<string>
     */
    private function studentSelectFields(array $cols): array
    {
        $candidates = [
            $cols['id'] ?? 'ses_studentid',
            $cols['admission_no'] ?? 'piu_admissionnumber',
            config('dynamics.student_full_name_column', 'ses_studentname'),
            $cols['first_name'] ?? 'firstname',
            $cols['last_name'] ?? 'lastname',
            $cols['email'] ?? 'ses_emailaddress',
            $cols['phone'] ?? 'mobilephone',
            $cols['dob'] ?? 'birthdate',
            config('dynamics.student_school_name_column', 'ses_schoolname'),
            config('dynamics.student_grade_level_column', 'ses_gradelevel'),
            config('dynamics.relationships.student_class_stream_lookup', '_cr0dc_classstream_value'),
            '_ses_schoolid_value',
            config('dynamics.student_class_name_column', 'ses_classname'),
            $cols['gender'] ?? 'gendercode',
        ];

        $fields = [];
        foreach ($candidates as $field) {
            if ($this->studentEntityHasAttribute($field)) {
                $fields[] = $field;
            }
        }

        if ($fields === []) {
            return ['ses_studentid', 'ses_studentname', 'piu_admissionnumber'];
        }

        return array_values(array_unique($fields));
    }

    private function studentEntityHasAttribute(string $logicalName): bool
    {
        return in_array($logicalName, $this->getStudentEntityAttributeNames(), true);
    }

    /**
     * @return list<string>
     */
    private function getStudentEntityAttributeNames(): array
    {
        return Cache::remember('dynamics_ses_student_attribute_names', 86_400, function (): array {
            $definition = $this->getEntityDefinitions('ses_student', ['LogicalName'], true);
            $attributes = $definition['Attributes'] ?? $definition['attributes'] ?? [];

            if (! is_array($attributes)) {
                return [];
            }

            return array_values(array_filter(array_map(
                fn (array $attribute): ?string => $attribute['LogicalName'] ?? $attribute['logicalname'] ?? null,
                $attributes,
            )));
        });
    }

    /**
     * @return array<int, string> option value => localized label
     */
    public function getGradeLevelOptionLabels(): array
    {
        return Cache::remember('dynamics_ses_gradelevel_labels', 86_400, function (): array {
            $manual = config('dynamics.grade_level_option_map', []);
            if (is_array($manual) && $manual !== []) {
                $normalized = [];
                foreach ($manual as $label => $value) {
                    if (is_int($value) || (is_string($value) && is_numeric($value))) {
                        $normalized[(int) $value] = (string) $label;
                    }
                }

                if ($normalized !== []) {
                    return $normalized;
                }
            }

            $gradeColumn = config('dynamics.student_grade_level_column', 'ses_gradelevel');
            $metadata = $this->getMetadata(
                "EntityDefinitions(LogicalName='ses_student')/Attributes(LogicalName='{$gradeColumn}')/Microsoft.Dynamics.CRM.PicklistAttributeMetadata",
                [
                    '$select' => 'LogicalName',
                    '$expand' => 'OptionSet($expand=Options($select=Value,Label))',
                ],
            );

            $options = $metadata['OptionSet']['Options'] ?? [];
            if (! is_array($options)) {
                return [];
            }

            $labels = [];
            foreach ($options as $option) {
                $value = $option['Value'] ?? null;
                $label = $option['Label']['UserLocalizedLabel']['Label']
                    ?? $option['Label']['LocalizedLabels'][0]['Label']
                    ?? null;

                if ($value === null || ! is_string($label) || $label === '') {
                    continue;
                }

                $labels[(int) $value] = $label;
            }

            return $labels;
        });
    }

    private function resolveGradeLevelOptionCode(?string $gradeLevel, ?string $streamOrRoomName = null): ?int
    {
        $candidates = array_filter([
            $gradeLevel,
            $streamOrRoomName ? $this->normalizeGradeLevelLabel($streamOrRoomName) : null,
            $streamOrRoomName,
        ]);

        $labels = $this->getGradeLevelOptionLabels();
        if ($labels === []) {
            return null;
        }

        foreach ($candidates as $candidate) {
            $code = $this->matchGradeLevelOptionCode((string) $candidate, $labels);
            if ($code !== null) {
                return $code;
            }
        }

        return null;
    }

    /**
     * @param  array<int, string>  $optionLabels
     */
    private function matchGradeLevelOptionCode(string $gradeLevel, array $optionLabels): ?int
    {
        $normalized = strtolower(trim($gradeLevel));
        if ($normalized === '') {
            return null;
        }

        foreach ($optionLabels as $value => $label) {
            $labelNormalized = strtolower(trim($label));
            if ($labelNormalized === $normalized) {
                return (int) $value;
            }
        }

        preg_match('/\d+/', $normalized, $matches);
        $number = $matches[0] ?? null;
        if (! $number) {
            $wordToNumber = [
                'one' => '1', 'two' => '2', 'three' => '3', 'four' => '4', 'five' => '5',
                'six' => '6', 'seven' => '7', 'eight' => '8', 'nine' => '9', 'ten' => '10',
            ];
            foreach ($wordToNumber as $word => $value) {
                if (str_contains($normalized, $word)) {
                    $number = $value;
                    break;
                }
            }
        }

        if (! $number) {
            return null;
        }

        foreach ($optionLabels as $value => $label) {
            $labelNormalized = strtolower($label);
            if (! preg_match('/\b'.preg_quote($number, '/').'\b/', $labelNormalized)) {
                continue;
            }

            $expectsForm = str_contains($normalized, 'form') || str_contains($labelNormalized, 'form');
            $expectsGrade = str_contains($normalized, 'grade') || str_contains($labelNormalized, 'grade');

            if ($expectsForm && ! str_contains($labelNormalized, 'form')) {
                continue;
            }

            if ($expectsGrade && ! str_contains($labelNormalized, 'grade') && ! str_contains($labelNormalized, 'form')) {
                continue;
            }

            return (int) $value;
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function buildSchoolFilters(?string $schoolName): array
    {
        if (! $schoolName) {
            return [];
        }

        $schoolNameColumn = config('dynamics.student_school_name_column', 'ses_schoolname');
        $filters = [];
        foreach ($this->schoolNameVariants($schoolName) as $variant) {
            $escapedSchool = str_replace("'", "''", $variant);
            $filters[] = "{$schoolNameColumn} eq '{$escapedSchool}'";
        }

        return $filters;
    }

    /**
     * @return list<string>
     */
    private function buildActiveStudentFilters(
        ?string $schoolName,
        ?string $gradeLevel,
        ?string $streamOrRoomName = null,
    ): array {
        $gradeColumn = config('dynamics.student_grade_level_column', 'ses_gradelevel');
        $clauses = ['statecode eq 0'];

        $schoolFilters = $this->buildSchoolFilters($schoolName);
        if ($schoolFilters !== []) {
            $clauses[] = '('.implode(' or ', $schoolFilters).')';
        }

        $gradeCode = $this->resolveGradeLevelOptionCode($gradeLevel, $streamOrRoomName);
        if ($gradeCode !== null) {
            $clauses[] = "{$gradeColumn} eq {$gradeCode}";

            return [implode(' and ', $clauses)];
        }

        if ($gradeLevel || $streamOrRoomName) {
            return [];
        }

        return [implode(' and ', $clauses)];
    }

    /**
     * @param  array<string, mixed>  $cols
     * @return list<array<string, mixed>>
     */
    /**
     * @param  list<string>  $selectFields
     * @return list<array<string, mixed>>
     */
    private function fetchStudentsViaRoomNavigation(array $selectFields, string $roomId): array
    {
        $cleanRoomId = trim($roomId, " '\"");
        if ($cleanRoomId === '') {
            return [];
        }

        $entitySet = config('dynamics.entities.rooms', 'ses_rooms');
        $navigation = config(
            'dynamics.relationships.room_students_navigation',
            'cr0dc_ses_student_ClassStream_ses_room',
        );

        return $this->getRelated(
            "{$entitySet}({$cleanRoomId})/{$navigation}",
            [
                '$select' => implode(',', array_unique($selectFields)),
                '$top' => 500,
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $cols
     * @param  list<string>  $selectFields
     * @return list<array<string, mixed>>
     */
    private function fetchStudentsByRoomGuid(string $entity, array $selectFields, string $roomId, array $cols): array
    {
        $cleanRoomId = trim($roomId, " '\"");
        $streamLookup = config(
            'dynamics.relationships.student_class_stream_lookup',
            $cols['stream'] ?? '_cr0dc_classstream_value',
        );

        return $this->get($entity, [
            '$select' => implode(',', array_unique($selectFields)),
            '$filter' => "{$streamLookup} eq {$cleanRoomId}",
            '$top' => 500,
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function getRoomRecordById(string $roomId): ?array
    {
        $cleanRoomId = trim($roomId, " '\"");
        if ($cleanRoomId === '') {
            return null;
        }

        $entity = config('dynamics.entities.rooms', 'ses_rooms');
        $rows = $this->get($entity, [
            '$select' => 'ses_roomid,ses_room,ses_roomname,ses_schoolname',
            '$filter' => "ses_roomid eq guid'{$cleanRoomId}'",
            '$top' => 1,
        ]);

        return $rows[0] ?? null;
    }

    /**
     * @return list<string>
     */
    private function buildStudentStreamMatchFilters(string $streamName, ?string $schoolName = null): array
    {
        $filters = $this->buildActiveStudentFilters(
            schoolName: $schoolName,
            gradeLevel: $this->normalizeGradeLevelLabel($streamName),
            streamOrRoomName: $streamName,
        );

        $classNameColumn = config('dynamics.student_class_name_column', 'ses_classname');
        if ($this->studentEntityHasAttribute($classNameColumn)) {
            $escapedStream = str_replace("'", "''", $streamName);
            $streamMatchers = [
                "{$classNameColumn} eq '{$escapedStream}'",
                "contains({$classNameColumn},'{$escapedStream}')",
            ];
            $suffix = $this->extractStreamSuffix($streamName);
            if ($suffix !== null && $suffix !== $streamName) {
                $escapedSuffix = str_replace("'", "''", $suffix);
                $streamMatchers[] = "contains({$classNameColumn},'{$escapedSuffix}')";
            }

            $schoolClause = $this->buildSchoolFilters($schoolName);
            $classClause = '('.implode(' or ', $streamMatchers).')';
            $combined = $schoolClause !== []
                ? "{$classClause} and (".implode(' or ', $schoolClause).') and statecode eq 0'
                : "{$classClause} and statecode eq 0";
            $filters[] = $combined;
        }

        return array_values(array_unique($filters));
    }

    /**
     * @return list<string>
     */
    private function schoolNameVariants(string $schoolName): array
    {
        $variants = [trim($schoolName)];
        $withoutPeriods = str_replace('.', '', $variants[0]);
        if ($withoutPeriods !== $variants[0]) {
            $variants[] = $withoutPeriods;
        }

        $withPeriod = preg_replace('/\bSt\s+/', 'St. ', $variants[0]);
        if (is_string($withPeriod) && $withPeriod !== $variants[0]) {
            $variants[] = $withPeriod;
        }

        return array_values(array_unique(array_filter($variants)));
    }

    private function extractStreamSuffix(string $streamName): ?string
    {
        if (preg_match('/grade\s+(?:one|two|three|four|five|six|seven|eight|nine|ten|\d+)\s+(.+)/i', $streamName, $matches) === 1) {
            return trim($matches[1]);
        }

        if (preg_match('/form\s+(?:one|two|three|four|five|six|seven|eight|nine|ten|\d+)\s+(.+)/i', $streamName, $matches) === 1) {
            return trim($matches[1]);
        }

        return null;
    }

    public function getAttendanceFormStreams(?string $schoolName = null): array
    {
        $rows = $schoolName
            ? $this->getRoomsBySchool($schoolName)
            : $this->getActiveRooms();

        $pairs = [];
        foreach ($rows as $room) {
            $parsed = $this->parseFormStreamFromRoom($room);
            if ($parsed['grade_level'] === null && $parsed['stream'] === null) {
                continue;
            }

            $key = strtolower(($parsed['grade_level'] ?? '').'|'.($parsed['stream'] ?? ''));
            $pairs[$key] = $parsed;
        }

        $values = array_values($pairs);
        usort($values, function (array $a, array $b): int {
            $gradeCmp = strcmp((string) ($a['grade_level'] ?? ''), (string) ($b['grade_level'] ?? ''));
            if ($gradeCmp !== 0) {
                return $gradeCmp;
            }

            return strcmp((string) ($a['stream'] ?? ''), (string) ($b['stream'] ?? ''));
        });

        return $values;
    }

    public function getActiveRooms(): array
    {
        $entity = config('dynamics.entities.rooms', 'ses_rooms');

        $rows = $this->get($entity, [
            '$select' => 'ses_roomid,ses_room,ses_roomname,ses_schoolname,ses_locationname,ses_roomcapacity',
            '$filter' => 'statecode eq 0',
            '$top' => 500,
        ]);

        usort($rows, fn ($a, $b) => strcmp((string) ($a['ses_room'] ?? ''), (string) ($b['ses_room'] ?? '')));

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $room
     * @return array{grade_level: string|null, stream: string|null}
     */
    private function parseFormStreamFromRoom(array $room): array
    {
        $roomName = trim((string) ($room['ses_roomname'] ?? $room['ses_room'] ?? ''));
        $roomId = trim((string) ($room['ses_roomid'] ?? $room['ses_roomsid'] ?? ''));

        if ($roomName === '') {
            return [
                'grade_level' => null,
                'stream' => null,
                'room_id' => null,
                'label' => null,
            ];
        }

        $gradeLevel = $this->normalizeGradeLevelLabel($roomName);

        return [
            'grade_level' => $gradeLevel,
            'stream' => $roomName,
            'room_id' => $roomId !== '' ? $roomId : null,
            'label' => $roomName,
        ];
    }

    private function normalizeGradeLevelLabel(string $label): ?string
    {
        $normalized = strtolower(trim($label));

        preg_match('/\d+/', $normalized, $matches);
        $number = $matches[0] ?? null;

        if (! $number) {
            $wordToNumber = [
                'one' => '1',
                'two' => '2',
                'three' => '3',
                'four' => '4',
                'five' => '5',
                'six' => '6',
                'seven' => '7',
                'eight' => '8',
                'nine' => '9',
                'ten' => '10',
            ];

            foreach ($wordToNumber as $word => $value) {
                if (str_contains($normalized, $word)) {
                    $number = $value;
                    break;
                }
            }
        }

        if (! $number) {
            return null;
        }

        if ($number === '3' && str_contains($normalized, 'form')) {
            return 'Form 3';
        }

        if ($number === '4' && str_contains($normalized, 'form')) {
            return 'Form 4';
        }

        return 'Grade '.$number;
    }

    public function getStudentsByFormStream(
        ?string $gradeLevel = null,
        ?string $stream = null,
        ?string $schoolName = null,
        ?string $roomId = null,
    ): array {
        $gradeLevel = $gradeLevel !== null ? trim($gradeLevel) : null;
        $stream = $stream !== null ? trim($stream) : null;
        $schoolName = $schoolName !== null ? trim($schoolName) : null;
        $roomId = $roomId !== null ? trim($roomId) : null;

        $entity = config('dynamics.entities.student', 'ses_students');
        $cols = config('dynamics.student_columns', []);

        if ($stream !== null && $stream !== '') {
            $byRoom = $this->getStudentsByRoom(
                roomId: $roomId,
                roomName: $stream,
                schoolName: $schoolName,
            );
            if ($byRoom !== []) {
                return $byRoom;
            }
        }

        return $this->getStudentsByClassNameMatch(
            entity: $entity,
            cols: $cols,
            schoolName: $schoolName,
            gradeLevel: $gradeLevel,
            stream: $stream,
        );
    }

    /**
     * @param  array<string, mixed>  $cols
     * @return list<array<string, mixed>>
     */
    private function getStudentsByClassNameMatch(
        string $entity,
        array $cols,
        ?string $schoolName,
        ?string $gradeLevel,
        ?string $stream,
    ): array {
        $selectFields = $this->studentSelectFields($cols);
        $filters = $this->buildActiveStudentFilters($schoolName, $gradeLevel, $stream);

        foreach ($filters as $filter) {
            $rows = $this->get($entity, [
                '$select' => implode(',', array_unique($selectFields)),
                '$filter' => $filter,
                '$top' => 500,
            ]);

            if ($rows !== []) {
                return $this->mapStudentsToApp($rows);
            }
        }

        return [];
    }

    private function resolveRoomIdByName(string $roomName, ?string $schoolName = null): ?string
    {
        $roomName = trim($roomName);
        if ($roomName === '') {
            return null;
        }

        $entity = config('dynamics.entities.rooms', 'ses_rooms');
        $escapedRoomName = str_replace("'", "''", $roomName);
        $filters = [
            "ses_room eq '{$escapedRoomName}'",
            "ses_roomname eq '{$escapedRoomName}'",
        ];

        if ($schoolName) {
            $escapedSchool = str_replace("'", "''", trim($schoolName));
            $filters[] = "ses_room eq '{$escapedRoomName}' and ses_schoolname eq '{$escapedSchool}'";
            $filters[] = "ses_roomname eq '{$escapedRoomName}' and ses_schoolname eq '{$escapedSchool}'";
        }

        $containsEscaped = str_replace("'", "''", $roomName);
        $filters[] = "contains(ses_roomname,'{$containsEscaped}')";
        $filters[] = "contains(ses_room,'{$containsEscaped}')";

        if ($schoolName) {
            $escapedSchool = str_replace("'", "''", trim($schoolName));
            $filters[] = "contains(ses_roomname,'{$containsEscaped}') and ses_schoolname eq '{$escapedSchool}'";
        }

        foreach ($filters as $filter) {
            $rows = $this->get($entity, [
                '$select' => 'ses_roomid,ses_roomsid,ses_room,ses_roomname',
                '$filter' => $filter,
                '$top' => 1,
            ]);

            if ($rows !== []) {
                return (string) ($rows[0]['ses_roomid'] ?? $rows[0]['ses_roomsid'] ?? '');
            }
        }

        return null;
    }

    /**
     * Resolve the school name as stored in Dataverse (ses_schoolname on rooms/students).
     */
    public function resolveDataverseSchoolName(?string $localSchoolId): ?string
    {
        if ($localSchoolId === null || $localSchoolId === '') {
            return null;
        }

        $school = School::query()->find($localSchoolId);
        if (! $school) {
            return null;
        }

        $aliases = config('schools.dynamics_names', []);
        if (isset($aliases[$school->code]) && $aliases[$school->code] !== '') {
            return $aliases[$school->code];
        }

        if ($school->dynamics_id) {
            $dynamicsName = $this->getDynamicsSchoolName($school->dynamics_id);
            if ($dynamicsName) {
                return $dynamicsName;
            }
        }

        $rooms = $this->getRoomsBySchool($school->name);
        if ($rooms !== []) {
            $fromRoom = trim((string) ($rooms[0]['ses_schoolname'] ?? ''));
            if ($fromRoom !== '') {
                return $fromRoom;
            }
        }

        return $school->name;
    }

    public function getParentsForStudent(string $dynamicsStudentId): array
    {
        $entity = config('dynamics.entities.parent', 'contacts');
        $relEntity = config('dynamics.entities.guardian_relationship');

        if (filled($relEntity)) {
            $links = $this->get($relEntity, [
                '$filter' => "pgs_studentid eq " . $dynamicsStudentId,
                '$top' => 50,
            ]);
            $parentIds = array_column($links, 'pgs_parentid');
            if ($parentIds === []) {
                return [];
            }
            $filterParts = array_map(fn ($id) => "contactid eq guid'" . $id . "'", $parentIds);
            $rows = $this->get($entity, ['$filter' => implode(' or ', $filterParts), '$top' => 50]);
        } else {
            $rows = $this->get($entity, ['$select' => 'contactid,fullname,firstname,lastname,emailaddress1,mobilephone', '$top' => 50]);
        }

        return $this->mapParentsToApp($rows);
    }

    protected function mapStudentsToApp(array $rows): array
    {
        $cols = config('dynamics.student_columns', []);
        $idCol = $cols['id'] ?? 'contactid';
        $first = $cols['first_name'] ?? 'firstname';
        $last = $cols['last_name'] ?? 'lastname';
        $adm = $cols['admission_no'] ?? 'piu_admissionnumber';
        $genderCol = $cols['gender'] ?? 'gendercode';

        $fullNameColumn = config('dynamics.student_full_name_column', 'ses_studentname');

        return array_map(function ($row) use ($idCol, $first, $last, $adm, $genderCol, $fullNameColumn) {
            $gendercode = $row[$genderCol] ?? null;
            $gender = $gendercode == 2 ? 'female' : ($gendercode == 1 ? 'male' : null);

            $firstName = trim((string) ($row[$first] ?? ''));
            $lastName = trim((string) ($row[$last] ?? ''));
            if ($firstName === '' && $lastName === '') {
                [$firstName, $lastName] = $this->splitStudentFullName((string) ($row[$fullNameColumn] ?? ''));
            }

            return [
                'id' => $row[$idCol] ?? null,
                'external_reference' => $row[$idCol] ?? null,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'adm_or_staff_no' => $row[$adm] ?? null,
                'admission_number' => $row[$adm] ?? null,
                'gender' => $gender,
                'email' => $row['ses_emailaddress'] ?? $row['emailaddress1'] ?? null,
                'phone' => $row['mobilephone'] ?? null,
                'dob' => $row['birthdate'] ?? null,
            ];
        }, $rows);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function splitStudentFullName(string $fullName): array
    {
        $fullName = trim($fullName);
        if ($fullName === '') {
            return ['Student', 'Unknown'];
        }

        $parts = preg_split('/\s+/', $fullName) ?: [];
        if (count($parts) === 1) {
            return [$parts[0], 'Unknown'];
        }

        $lastName = (string) array_pop($parts);
        $firstName = trim(implode(' ', $parts));

        return [$firstName !== '' ? $firstName : $fullName, $lastName];
    }

    protected function mapParentsToApp(array $rows): array
    {
        $cols = config('dynamics.parent_columns', []);
        $idCol = $cols['id'] ?? 'contactid';
        $fullName = $cols['full_name'] ?? 'fullname';

        return array_map(function ($row) use ($idCol, $fullName) {
            return [
                'id' => $row[$idCol] ?? null,
                'full_name' => $row[$fullName] ?? ($row['firstname'] . ' ' . $row['lastname']),
                'email' => $row['emailaddress1'] ?? null,
                'phone' => $row['mobilephone'] ?? null,
            ];
        }, $rows);
    }

    public function clearTokenCache(): void
    {
        Cache::forget('dynamics_access_token');
        $this->accessToken = null;
    }
}