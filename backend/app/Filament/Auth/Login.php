<?php

namespace App\Filament\Auth;

use App\Services\Auth\MicrosoftOAuthService;
use Filament\Actions\Action;
use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Facades\Filament;
use Filament\Support\Colors\Color;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;

class Login extends BaseLogin
{
    protected function getFormActions(): array
    {
        $actions = parent::getFormActions();

        if (! app(MicrosoftOAuthService::class)->isEnabled()) {
            return $actions;
        }

        return [
            Action::make('microsoft')
                ->label('Continue with Microsoft')
                ->color(Color::hex('#2f2f88'))
                ->url(route('auth.microsoft.redirect', ['panel' => Filament::getCurrentPanel()->getId()]))
                ->extraAttributes(['class' => 'w-full']),
            ...$actions,
        ];
    }

    public function getSubheading(): string | Htmlable | null
    {
        $messages = [];

        if (session()->has('microsoft_sso_error')) {
            $messages[] = '<p class="text-sm text-danger-600 dark:text-danger-400">'.e((string) session('microsoft_sso_error')).'</p>';
        }

        if (session()->has('microsoft_sso_notice')) {
            $messages[] = '<p class="text-sm text-success-600 dark:text-success-400">'.e((string) session('microsoft_sso_notice')).'</p>';
        }

        $parent = parent::getSubheading();

        if ($parent instanceof Htmlable) {
            $messages[] = $parent->toHtml();
        } elseif (is_string($parent) && $parent !== '') {
            $messages[] = e($parent);
        }

        if ($messages === []) {
            return null;
        }

        return new HtmlString(implode('', $messages));
    }
}
