<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\ResolveMicrosoftUser;
use App\Http\Controllers\Controller;
use App\Services\Audit\AuditLogger;
use App\Services\Auth\MicrosoftOAuthService;
use App\Services\Auth\MicrosoftTokenValidator;
use App\Services\Auth\UserAccessService;
use Filament\Facades\Filament;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Throwable;

class MicrosoftOAuthController extends Controller
{
    public function __construct(
        private readonly MicrosoftOAuthService $oauth,
        private readonly MicrosoftTokenValidator $tokenValidator,
        private readonly ResolveMicrosoftUser $resolveMicrosoftUser,
        private readonly UserAccessService $userAccessService,
        private readonly AuditLogger $auditLogger,
    ) {
    }

    public function redirect(Request $request): RedirectResponse
    {
        abort_unless($this->oauth->isEnabled(), 404);

        $panel = (string) $request->query('panel', 'admin');

        abort_unless(in_array($panel, ['admin', 'teacher'], true), 404);

        return redirect()->away($this->oauth->authorizationUrl($panel));
    }

    public function callback(Request $request): RedirectResponse
    {
        abort_unless($this->oauth->isEnabled(), 404);

        $panelId = 'admin';

        try {
            if ($request->filled('error')) {
                throw new \RuntimeException((string) $request->query('error_description', $request->query('error', 'Microsoft sign-in was cancelled.')));
            }

            $tokenResponse = $this->oauth->exchangeAuthorizationCode(
                (string) $request->query('code'),
                (string) $request->query('state'),
            );
            $panelId = (string) ($tokenResponse['panel'] ?? 'admin');
            $idToken = (string) ($tokenResponse['id_token'] ?? '');

            if ($idToken === '') {
                throw new \RuntimeException('Microsoft did not return an identity token.');
            }

            $claims = $this->tokenValidator->validate($idToken);
            $user = $this->resolveMicrosoftUser->execute($claims);

            if (! $this->userAccessService->canSignIn($user)) {
                return $this->redirectToPanelLogin(
                    $panelId,
                    $this->userAccessService->signInBlockedMessage($user),
                );
            }

            $panel = Filament::getPanel($panelId);

            if (! $user->canAccessPanel($panel)) {
                foreach (['admin', 'teacher'] as $alternatePanelId) {
                    if ($alternatePanelId === $panelId) {
                        continue;
                    }

                    $alternatePanel = Filament::getPanel($alternatePanelId);

                    if ($user->canAccessPanel($alternatePanel)) {
                        Auth::login($user, true);

                        $this->auditLogger->log(
                            $user,
                            'auth.microsoft.filament',
                            'User authenticated via Microsoft SSO and was redirected to the '.$alternatePanelId.' panel.',
                            $user,
                            ['requested_panel' => $panelId, 'panel' => $alternatePanelId],
                            ['tenant_id' => $claims['tid'] ?? null],
                            $request,
                        );

                        return redirect()
                            ->intended($alternatePanel->getUrl())
                            ->with('microsoft_sso_notice', 'You were signed in to the '.strtolower($alternatePanel->getBrandName()).' panel for your role.');
                    }
                }

                return $this->redirectToPanelLogin(
                    $panelId,
                    $this->userAccessService->panelAccessDeniedMessage($user, $panelId),
                );
            }

            Auth::login($user, true);

            $this->auditLogger->log(
                $user,
                'auth.microsoft.filament',
                'User authenticated via Microsoft SSO for the '.$panelId.' panel.',
                $user,
                ['panel' => $panelId],
                ['tenant_id' => $claims['tid'] ?? null],
                $request,
            );

            return redirect()->intended($panel->getUrl());
        } catch (Throwable $exception) {
            report($exception);

            $message = config('app.debug')
                ? $exception->getMessage()
                : 'Microsoft sign-in failed. Try again or use email and password.';

            return $this->redirectToPanelLogin($panelId, $message);
        }
    }

    private function redirectToPanelLogin(string $panelId, string $message): RedirectResponse
    {
        $panelId = in_array($panelId, ['admin', 'teacher'], true) ? $panelId : 'admin';

        return redirect()
            ->to(Filament::getPanel($panelId)->getLoginUrl())
            ->with('microsoft_sso_error', $message);
    }
}
