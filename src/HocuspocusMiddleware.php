<?php

namespace Hocuspocus;

use Closure;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;


class HocuspocusMiddleware
{
    public function handle($request, Closure $next)
    {
        $json = json_decode($request->getContent() ?: '{}', true);

        if (!isset($json['payload']['requestParameters']) ||
            !isset($json['payload']['requestHeaders']['cookie'])) {
            throw new BadRequestException('Invalid payload');
        }

        $cookies = collect(explode('; ', $json['payload']['requestHeaders']['cookie']))->map(function ($item) {
            return explode('=', $item);
        })->mapWithKeys(function ($item) {
            return [$item[0] => urldecode($item[1])];
        })->toArray();

        $request->cookies->add($cookies);
        $request->headers->set('X-CSRF-TOKEN', $json['payload']['requestParameters']['csrf_token']);

        return $next($request);
    }
}
