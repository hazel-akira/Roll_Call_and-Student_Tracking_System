<?php

$dynamicsCredential = static function (string ...$keys): ?string {
    foreach ($keys as $key) {
        $value = env($key);

        if (! is_string($value) || $value === '' || str_starts_with($value, '${')) {
            continue;
        }

        return $value;
    }

    return null;
};

return [
    'enabled' => filter_var(env('DYNAMICS_ENABLED', false), FILTER_VALIDATE_BOOL),
    'url' => env('DYNAMICS_URL', env('DYNAMICS_BASE_URL')),
    'api_version' => env('DYNAMICS_API_VERSION', 'v9.2'),
    'timeout' => (int) env('DYNAMICS_TIMEOUT', 30),
    'connect_timeout' => (int) env('DYNAMICS_CONNECT_TIMEOUT', 10),
    'azure' => [
        'tenant_id' => $dynamicsCredential('DYNAMICS_TENANT_ID', 'DYNAMICS_AZURE_TENANT_ID', 'AZURE_AD_TENANT_ID'),
        'client_id' => $dynamicsCredential('DYNAMICS_CLIENT_ID', 'DYNAMICS_AZURE_CLIENT_ID', 'AZURE_AD_CLIENT_ID'),
        'client_secret' => $dynamicsCredential('DYNAMICS_CLIENT_SECRET', 'DYNAMICS_AZURE_CLIENT_SECRET', 'AZURE_AD_CLIENT_SECRET'),
    ],
    'entities' => [
        'student' => env('DYNAMICS_STUDENT_ENTITY', 'ses_students'),
        'rooms' => env('DYNAMICS_ROOM_ENTITY', 'ses_rooms'),
        'parent' => env('DYNAMICS_PARENT_ENTITY', 'contacts'),
        'guardian_relationship' => env('DYNAMICS_GUARDIAN_RELATIONSHIP_ENTITY'),
    ],
    /*
     * ses_room (stream) 1 — * ses_student via custom lookup cr0dc_classstream (Class Stream).
     * See docs/dynamics-relationships.md.
     */
    'relationships' => [
        'student_class_stream_attribute' => 'cr0dc_classstream',
        'student_class_stream_lookup' => '_cr0dc_classstream_value',
        'student_class_stream_navigation' => 'cr0dc_ClassStream',
        'room_students_navigation' => 'cr0dc_ses_student_ClassStream_ses_room',
    ],
    'student_columns' => [
        'id' => env('DYNAMICS_STUDENT_ID_COLUMN', 'ses_studentid'),
        'first_name' => env('DYNAMICS_STUDENT_FIRST_NAME_COLUMN', 'firstname'),
        'last_name' => env('DYNAMICS_STUDENT_LAST_NAME_COLUMN', 'lastname'),
        'admission_no' => env('DYNAMICS_STUDENT_ADMISSION_COLUMN', 'piu_admissionnumber'),
        'gender' => env('DYNAMICS_STUDENT_GENDER_COLUMN', 'gendercode'),
        'email' => env('DYNAMICS_STUDENT_EMAIL_COLUMN', 'ses_emailaddress'),
        'phone' => env('DYNAMICS_STUDENT_PHONE_COLUMN', 'mobilephone'),
        'dob' => env('DYNAMICS_STUDENT_DOB_COLUMN', 'birthdate'),
        'school' => env('DYNAMICS_STUDENT_SCHOOL_COLUMN', '_ses_schoolid_value'),
        'stream' => env('DYNAMICS_STUDENT_STREAM_COLUMN', '_cr0dc_classstream_value'),
    ],
    'staff_entity' => env('DYNAMICS_STAFF_ENTITY', 'ses_staffs'),
    'staff_columns' => [
        'name' => env('DYNAMICS_STAFF_NAME_COLUMN', 'ses_staffname'),
        'staff_no' => env('DYNAMICS_STAFF_NO_COLUMN', 'ses_staff'),
        'id_number' => env('DYNAMICS_STAFF_ID_NUMBER_COLUMN', 'cr0dc_nationalidpassport'),
        'email' => env('DYNAMICS_STAFF_EMAIL_COLUMN', 'emailaddress1'),
        'school' => env('DYNAMICS_STAFF_SCHOOL_COLUMN', '_ses_schoolid_value'),
    ],
    'faculty_entity' => env('DYNAMICS_FACULTY_ENTITY', 'ses_faculties'),
    'faculty_columns' => [
        'name' => env('DYNAMICS_FACULTY_NAME_COLUMN', 'ses_facultyname'),
        'faculty_no' => env('DYNAMICS_FACULTY_NO_COLUMN', 'ses_faculty'),
        'email' => env('DYNAMICS_FACULTY_EMAIL_COLUMN', 'emailaddress1'),
        'school' => env('DYNAMICS_FACULTY_SCHOOL_COLUMN', '_ses_schoolid_value'),
    ],
    'school_entity' => env('DYNAMICS_SCHOOL_ENTITY', 'accounts'),
    'school_entity_id' => env('DYNAMICS_SCHOOL_ENTITY_ID_COLUMN', 'accountid'),
    'school_name_column' => env('DYNAMICS_SCHOOL_NAME_COLUMN', 'name'),
    'student_admission_no_column' => env('DYNAMICS_STUDENT_ADMISSION_NO_COLUMN', 'piu_admissionnumber'),
    'student_school_name_column' => env('DYNAMICS_STUDENT_SCHOOL_NAME_COLUMN', 'ses_schoolname'),
    'student_full_name_column' => env('DYNAMICS_STUDENT_FULL_NAME_COLUMN', 'ses_studentname'),
    'student_class_name_column' => env('DYNAMICS_STUDENT_CLASS_NAME_COLUMN', 'ses_classname'),
    'student_grade_level_column' => env('DYNAMICS_STUDENT_GRADE_LEVEL_COLUMN', 'ses_gradelevel'),
    /*
     * Optional manual map when metadata cannot be loaded: grade label => option set integer.
     * Example: 'grade 4' => 284210004
     */
    'grade_level_option_map' => [],
];
