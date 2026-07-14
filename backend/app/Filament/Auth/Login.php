<?php

namespace App\Filament\Auth;

use App\Services\Auth\MicrosoftOAuthService;
use Filament\Actions\Action;
use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Facades\Filament;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\RenderHook;
use Filament\Schemas\Schema;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;

class Login extends BaseLogin
{
    public function content(Schema $schema): Schema
    {
        $components = [];

        if (app(MicrosoftOAuthService::class)->isEnabled()) {
            $components[] = Actions::make([
                $this->microsoftSignInAction(),
            ])
                ->fullWidth()
                ->key('microsoft-sso');
        }

        return $schema->components([
            ...$components,
            RenderHook::make(PanelsRenderHook::AUTH_LOGIN_FORM_BEFORE),
            $this->getFormContentComponent(),
            $this->getMultiFactorChallengeFormContentComponent(),
            RenderHook::make(PanelsRenderHook::AUTH_LOGIN_FORM_AFTER),
        ]);
    }

    protected function microsoftSignInAction(): Action
    {
        return Action::make('microsoft')
            ->label('Continue with Microsoft')
            ->color(Color::hex('#df8811'))
            ->url(route('auth.microsoft.redirect', ['panel' => Filament::getCurrentPanel()->getId()]));
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
