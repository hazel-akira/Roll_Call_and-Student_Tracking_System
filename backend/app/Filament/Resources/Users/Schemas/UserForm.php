<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Models\Role;
use App\Services\Auth\UserAccessService;
use App\Support\RoleSlugs;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Hash;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Profile')
                    ->description('Basic account details. Users who sign in with Microsoft appear here automatically when they first try to log in.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('email')
                            ->label('Email address')
                            ->email()
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),
                        TextInput::make('job_title')
                            ->maxLength(255),
                        TextInput::make('department')
                            ->maxLength(255),
                    ]),
                Section::make('System access')
                    ->description('Control whether the user can sign in to the Roll Call app and Filament panels.')
                    ->columns(2)
                    ->schema([
                        Select::make('role_id')
                            ->label('Role')
                            ->relationship('role', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->helperText('Teacher: attendance. Dean / Deputy: duty roster & reports. Admin / ICT: full platform.'),
                        Select::make('status')
                            ->label('Access status')
                            ->options([
                                'pending' => 'Pending — cannot sign in yet',
                                'active' => 'Active — can sign in',
                                'inactive' => 'Inactive — access revoked',
                            ])
                            ->required()
                            ->default('pending')
                            ->native(false),
                        Toggle::make('set_panel_password')
                            ->label('Set Filament panel password')
                            ->dehydrated(false)
                            ->live()
                            ->default(false)
                            ->helperText('Optional. Required only if this user should sign in at /admin, /teacher, or /dean with email and password.'),
                        TextInput::make('password')
                            ->password()
                            ->revealable()
                            ->visible(fn (Get $get): bool => (bool) $get('set_panel_password'))
                            ->dehydrated(fn (Get $get, ?string $state): bool => (bool) $get('set_panel_password') && filled($state))
                            ->dehydrateStateUsing(fn (string $state): string => Hash::make($state))
                            ->required(fn (Get $get, $livewire): bool => (bool) $get('set_panel_password') && $livewire instanceof \Filament\Resources\Pages\CreateRecord),
                    ]),
                Section::make('School access')
                    ->description('Required for teachers and dean staff before they can use the app. Admins and ICT staff can access all schools without assignments.')
                    ->schema([
                        Select::make('schools')
                            ->label('Assigned schools')
                            ->relationship('schools', 'name')
                            ->multiple()
                            ->preload()
                            ->searchable()
                            ->required(fn (Get $get): bool => self::schoolsRequired($get))
                            ->helperText(fn (Get $get): ?string => self::schoolsHelper($get))
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    private static function schoolsRequired(Get $get): bool
    {
        if ($get('status') !== 'active') {
            return false;
        }

        $roleId = $get('role_id');
        if (! $roleId) {
            return false;
        }

        return RoleSlugs::requiresSchoolAssignment(
            Role::query()->whereKey($roleId)->value('slug'),
        );
    }

    private static function schoolsHelper(Get $get): ?string
    {
        if ($get('status') === 'pending') {
            return 'Assign schools before setting status to Active so teachers and dean staff can sign in after approval.';
        }

        if (self::schoolsRequired($get)) {
            return 'At least one school is required for active teachers and dean staff.';
        }

        return null;
    }
}
