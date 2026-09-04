<?php

declare(strict_types=1);

namespace App\Security;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\TooManyLoginAttemptsAuthenticationException;
use Symfony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface;

/**
 * Remplace le failure_handler par défaut de Lexik (qui renvoie 401 pour
 * tout type d'échec, y compris le rate limiting) afin de distinguer :
 * - identifiants invalides -> 401, comme avant ;
 * - throttling dépassé (login_throttling, backé par Symfony RateLimiter,
 *   cf. security.yaml) -> 429 Too Many Requests + en-tête Retry-After,
 *   sémantiquement correct pour un client (et attendu par le CDC).
 */
final class LoginFailureHandler implements AuthenticationFailureHandlerInterface
{
    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        if ($exception instanceof TooManyLoginAttemptsAuthenticationException) {
            $minutes = $exception->getMessageData()['%minutes%'] ?? null;

            $response = new JsonResponse(
                [
                    'code' => Response::HTTP_TOO_MANY_REQUESTS,
                    'message' => $minutes
                        ? sprintf('Trop de tentatives de connexion échouées. Réessayez dans %d minute(s).', $minutes)
                        : 'Trop de tentatives de connexion échouées. Réessayez plus tard.',
                ],
                Response::HTTP_TOO_MANY_REQUESTS
            );

            if ($minutes) {
                $response->headers->set('Retry-After', (string) ((int) $minutes * 60));
            }

            return $response;
        }

        return new JsonResponse(
            ['code' => Response::HTTP_UNAUTHORIZED, 'message' => 'Invalid credentials.'],
            Response::HTTP_UNAUTHORIZED
        );
    }
}
