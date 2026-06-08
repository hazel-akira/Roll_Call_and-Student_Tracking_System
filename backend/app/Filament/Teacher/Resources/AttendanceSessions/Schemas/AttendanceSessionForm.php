<?php

namespace App\Filament\Teacher\Resources\AttendanceSessions\Schemas;

use App\Models\SchoolClass;
use App\Services\TenantService;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class AttendanceSessionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Session')
                    ->columns(2)
                    ->schema([
                        TextInput::make('title')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),
                        Select::make('class_id')
                            ->label('Class')
                            ->options(fn (): array => self::classOptions())
                            ->searchable()
                            ->required(),
                        Select::make('subject_id')
                            ->relationship('subject', 'name')
                            ->searchable()
                            ->preload(),
                        DatePicker::make('session_date')
                            ->required()
                            ->default(now()),
                        Textarea::make('notes')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    /**
     * @return array<int, string>
     */
    private static function classOptions(): array
    {
        $user = Auth::user();
        if (! $user) {
            return [];
        }

        $query = SchoolClass::query()->with('school');

        $tenant = app(TenantService::class);
        if ($tenant->isTenantUser($user)) {
            $schoolIds = $user->schools()->pluck('schools.id');
            if ($schoolIds->isEmpty()) {
                return [];
            }

            $query->whereIn('school_id', $schoolIds);
        }

        return $query
            ->orderBy('grade_level')
            ->orderBy('section')
            ->get()
            ->mapWithKeys(function (SchoolClass $class): array {
                $label = trim(implode(' · ', array_filter([
                    $class->school?->name,
                    $class->grade_level ?? $class->name,
                    $class->section,
                ])));

                return [$class->id => $label !== '' ? $label : "Class #{$class->id}"];
            })
            ->all();
    }
}
