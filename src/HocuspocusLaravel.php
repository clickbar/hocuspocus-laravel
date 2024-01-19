<?php

namespace Hocuspocus;

use Exception;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use ReflectionClass;
use ReflectionException;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Hocuspocus\Contracts\Collaborative;
use Hocuspocus\Jobs\Change;

class HocuspocusLaravel
{
    const EVENT_ON_CHANGE = 'change';
    const EVENT_ON_CREATE_DOCUMENT = 'create';

    /**
     * Handle an incoming webhook.
     * @param  Request  $request
     * @return Application|ResponseFactory|\Illuminate\Foundation\Application|Response|void
     * @throws ReflectionException
     */
    public function handleWebhook(Request $request)
    {
        if (! $this->verifySignature($request)) {
            throw new BadRequestException('Invalid signature');
        }

        $json = json_decode($request->getContent() ?: '{}', true);

        if (
            ! isset($json['event'])
            || ! isset($json['payload']['requestParameters'])
            || ! isset($json['payload']['documentName'])
        ) {
            throw new BadRequestException('Invalid payload');
        }

        if (! in_array($json['event'], config('hocuspocus-laravel.events'))) {
            return response();
        }

        $user = $request->user();

        $document = $this->getDocument($json['payload']['documentName']);

        if (! $user->can(config('hocuspocus-laravel.policy_method_name'), $document)) {
            throw new AuthorizationException("User is not allowed to access this document");
        }

        $handler = "handleOn{$json['event']}";

        if (method_exists($this, $handler)) {
            return $this->$handler($json['payload'], $document, $user);
        }
    }

    /**
     * Handle onCreate webhook
     * @param  array  $payload
     * @param  Collaborative  $document
     * @param  Authenticatable  $user
     * @return JsonResponse
     */
    protected function handleOnCreate(array $payload, Collaborative $document, Authenticatable $user): JsonResponse
    {
        $data = collect($document->getCollaborativeAttributes())->mapWithKeys(function ($attribute) use ($document) {
            return [$attribute => $document->{$attribute}];
        });

        return response()->json(
            $data->toJson()
        );
    }

    /**
     * Handle onChange webhook
     * @param  array  $payload
     * @param  Collaborative  $document
     * @param  Authenticatable  $user
     * @return Application|ResponseFactory|\Illuminate\Foundation\Application|Response
     */
    protected function handleOnChange(array $payload, Collaborative $document, Authenticatable $user)
    {
        dispatch(new Change($user, $document, $payload['document']));

        return response('handled');
    }


    /**
     * Get the document by the given name.
     * @param  string  $name
     * @return mixed
     * @throws ReflectionException|Exception|ModelNotFoundException
     */
    protected function getDocument(string $name)
    {
        // class name colon id e.g. "App\Models\TextDocument:1"
        $parts = explode(':', urldecode($name));

        if (count($parts) != 2) {
            throw new Exception("Invalid document name format \"{$name}\"");
        }

        $interface = Collaborative::class;
        $reflection = new ReflectionClass($parts[0]);

        if (! $reflection->implementsInterface($interface)) {
            throw new Exception("\"{$parts[0]}\" doesn't implement \"{$interface}\"");
        }

        if (! $reflection->isSubclassOf(Model::class)) {
            throw new Exception("\"{$parts[0]}\" is not an Eloquent Model");
        }

        return call_user_func([$parts[0], 'findOrFail'], $parts[1]);
    }

    /**
     * Verify the signature of the given request.
     * @param  Request  $request
     * @return bool
     */
    protected function verifySignature(Request $request): bool
    {
        if (($signature = $request->headers->get('X-Hocuspocus-Signature-256')) == null) {
            throw new BadRequestException('Header not set');
        }

        $parts = explode('=', $signature);

        if (count($parts) != 2) {
            throw new BadRequestException('Invalid signature format');
        }

        $digest = hash_hmac('sha256', $request->getContent(), config('hocuspocus-laravel.secret'));

        return hash_equals($digest, $parts[1]);
    }
}
