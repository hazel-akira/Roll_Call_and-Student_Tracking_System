<?php

namespace App\Support;

final class RoleSlugs
{
    public const ADMIN = 'admin';

    public const TEACHER = 'teacher';

    public const ICT_STAFF = 'ict_staff';

    public const DEAN_OF_STUDENTS = 'dean_of_students';

    public const DEPUTY_DEAN = 'deputy_dean';

    /**
     * @return list<string>
     */
    public static function deanSlugs(): array
    {
        return [
            self::DEAN_OF_STUDENTS,
            self::DEPUTY_DEAN,
        ];
    }

    /**
     * @return list<string>
     */
    public static function schoolScopedSlugs(): array
    {
        return [
            self::TEACHER,
            ...self::deanSlugs(),
        ];
    }

    /**
     * @return list<string>
     */
    public static function allSchoolAccessSlugs(): array
    {
        return [
            self::ADMIN,
            self::ICT_STAFF,
        ];
    }

    public static function isDean(?string $slug): bool
    {
        return in_array($slug, self::deanSlugs(), true);
    }

    /**
     * @return list<string>
     */
    public static function reportViewerSlugs(): array
    {
        return [
            ...self::allSchoolAccessSlugs(),
            ...self::deanSlugs(),
        ];
    }

    public static function requiresSchoolAssignment(?string $slug): bool
    {
        return in_array($slug, self::schoolScopedSlugs(), true);
    }
}
