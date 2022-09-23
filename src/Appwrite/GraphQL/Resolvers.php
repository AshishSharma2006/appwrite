<?php

namespace Appwrite\GraphQL;

use Appwrite\GraphQL\Promises\CoroutinePromise;
use Appwrite\Utopia\Request;
use Appwrite\Utopia\Response;
use Utopia\App;
use Utopia\Database\Database;
use Utopia\Database\ID;
use Utopia\Exception;
use Utopia\Route;

class Resolvers
{
    /**
     * Create a resolver for a given {@see Route}.
     *
     * @param App $utopia
     * @param ?Route $route
     * @return callable
     */
    public static function resolveAPIRequest(
        App $utopia,
        string $path,
        string $method
    ): callable {
        return static fn($type, $args, $context, $info) => new CoroutinePromise(
            function (callable $resolve, callable $reject) use ($utopia, $path, $method, $args, $context, $info) {
                $utopia = $utopia->getResource('current', true);
                $request = $utopia->getResource('request', true);
                $response = $utopia->getResource('response', true);
                $swoole = $request->getSwoole();

                foreach ($args as $key => $value) {
                    if (\str_contains($path, '/:' . $key)) {
                        $path = \str_replace(':' . $key, $value, $path);
                    }
                }

                $swoole->server['request_method'] = $method;
                $swoole->server['request_uri'] = $path;
                $swoole->server['path_info'] = $path;

                switch ($method) {
                    case 'GET':
                        $swoole->get = $args;
                        break;
                    default:
                        $swoole->post = $args;
                        break;
                }

                self::resolve($utopia, $request, $response, $resolve, $reject);
            }
        );
    }

    /**
     * Create a resolver for getting a document in a specified database and collection.
     *
     * @param App $utopia
     * @param Database $dbForProject
     * @param string $databaseId
     * @param string $collectionId
     * @return callable
     */
    public static function resolveDocument(
        App $utopia,
        Database $dbForProject,
        string $databaseId,
        string $collectionId,
        string $methodType,
    ): callable {
        return [self::class, 'resolveDocument' . \ucfirst($methodType)](
            $utopia,
            $dbForProject,
            $databaseId,
            $collectionId
        );
    }

    /**
     * Create a resolver for getting a document in a specified database and collection.
     *
     * @param App $utopia
     * @param Database $dbForProject
     * @param string $databaseId
     * @param string $collectionId
     * @return callable
     */
    public static function resolveDocumentGet(
        App $utopia,
        Database $dbForProject,
        string $databaseId,
        string $collectionId
    ): callable {
        return static fn($type, $args, $context, $info) => new CoroutinePromise(
            function (callable $resolve, callable $reject) use ($utopia, $dbForProject, $databaseId, $collectionId, $type, $args) {
                $utopia = $utopia->getResource('current', true);
                $request = $utopia->getResource('request', true);
                $response = $utopia->getResource('response', true);
                $swoole = $request->getSwoole();

                $swoole->server['request_method'] = 'GET';
                $swoole->server['request_uri'] = "/v1/databases/$databaseId/collections/$collectionId/documents/{$args['id']}";
                $swoole->server['path_info'] = "/v1/databases/$databaseId/collections/$collectionId/documents/{$args['id']}";

                self::resolve($utopia, $request, $response, $resolve, $reject);
            }
        );
    }

    /**
     * Create a resolver for listing documents in a specified database and collection.
     *
     * @param App $utopia
     * @param Database $dbForProject
     * @param string $databaseId
     * @param string $collectionId
     * @return callable
     */
    public static function resolveDocumentList(
        App $utopia,
        Database $dbForProject,
        string $databaseId,
        string $collectionId,
    ): callable {
        return static fn($type, $args, $context, $info) => new CoroutinePromise(
            function (callable $resolve, callable $reject) use ($utopia, $dbForProject, $databaseId, $collectionId, $type, $args) {
                $utopia = $utopia->getResource('current', true);
                $request = $utopia->getResource('request', true);
                $response = $utopia->getResource('response', true);
                $swoole = $request->getSwoole();
                $swoole->post = [
                    'databaseId' => $databaseId,
                    'collectionId' => $collectionId,
                    'queries' => $args['queries'],
                ];
                $swoole->server['request_method'] = 'GET';
                $swoole->server['request_uri'] = "/v1/databases/$databaseId/collections/$collectionId/documents";
                $swoole->server['path_info'] = "/v1/databases/$databaseId/collections/$collectionId/documents";

                $beforeResolve = function ($payload) {
                    return $payload['documents'];
                };

                self::resolve($utopia, $request, $response, $resolve, $reject, $beforeResolve);
            }
        );
    }

    /**
     * Create a resolver for creating a document in a specified database and collection.
     *
     * @param App $utopia
     * @param Database $dbForProject
     * @param string $databaseId
     * @param string $collectionId
     * @return callable
     */
    public static function resolveDocumentCreate(
        App $utopia,
        Database $dbForProject,
        string $databaseId,
        string $collectionId,
    ): callable {
        return static fn($type, $args, $context, $info) => new CoroutinePromise(
            function (callable $resolve, callable $reject) use ($utopia, $dbForProject, $databaseId, $collectionId, $type, $args) {
                $utopia = $utopia->getResource('current', true);
                $request = $utopia->getResource('request', true);
                $response = $utopia->getResource('response', true);
                $swoole = $request->getSwoole();

                $id = $args['id'] ?? ID::unique();
                $permissions = $args['permissions'] ?? null;

                unset($args['id']);
                unset($args['permissions']);

                // Order must be the same as the route params
                $swoole->post = [
                    'databaseId' => $databaseId,
                    'documentId' => $id,
                    'collectionId' => $collectionId,
                    'data' => $args,
                    'permissions' => $permissions,
                ];
                $swoole->server['request_method'] = 'POST';
                $swoole->server['request_uri'] = "/v1/databases/$databaseId/collections/$collectionId/documents";
                $swoole->server['path_info'] = "/v1/databases/$databaseId/collections/$collectionId/documents";

                self::resolve($utopia, $request, $response, $resolve, $reject);
            }
        );
    }

    /**
     * Create a resolver for updating a document in a specified database and collection.
     *
     * @param App $utopia
     * @param Database $dbForProject
     * @param string $databaseId
     * @param string $collectionId
     * @return callable
     */
    public static function resolveDocumentUpdate(
        App $utopia,
        Database $dbForProject,
        string $databaseId,
        string $collectionId,
    ): callable {
        return static fn($type, $args, $context, $info) => new CoroutinePromise(
            function (callable $resolve, callable $reject) use ($utopia, $dbForProject, $databaseId, $collectionId, $type, $args) {
                $utopia = $utopia->getResource('current', true);
                $request = $utopia->getResource('request', true);
                $response = $utopia->getResource('response', true);
                $swoole = $request->getSwoole();

                $documentId = $args['id'];
                $permissions = $args['permissions'] ?? null;

                unset($args['id']);
                unset($args['permissions']);

                // Order must be the same as the route params
                $swoole->post = [
                    'databaseId' => $databaseId,
                    'collectionId' => $collectionId,
                    'documentId' => $documentId,
                    'data' => $args,
                    'permissions' => $permissions,
                ];
                $swoole->server['request_method'] = 'PATCH';
                $swoole->server['request_uri'] = "/v1/databases/$databaseId/collections/$collectionId/documents/$documentId";
                $swoole->server['path_info'] = "/v1/databases/$databaseId/collections/$collectionId/documents/$documentId";

                self::resolve($utopia, $request, $response, $resolve, $reject);
            }
        );
    }

    /**
     * Create a resolver for deleting a document in a specified database and collection.
     *
     * @param App $utopia
     * @param Database $dbForProject
     * @param string $databaseId
     * @param string $collectionId
     * @return callable
     */
    public static function resolveDocumentDelete(
        App $utopia,
        Database $dbForProject,
        string $databaseId,
        string $collectionId
    ): callable {
        return static fn($type, $args, $context, $info) => new CoroutinePromise(
            function (callable $resolve, callable $reject) use ($utopia, $dbForProject, $databaseId, $collectionId, $type, $args) {
                $utopia = $utopia->getResource('current', true);
                $request = $utopia->getResource('request', true);
                $response = $utopia->getResource('response', true);
                $swoole = $request->getSwoole();

                $documentId = $args['id'];

                $swoole->server['request_method'] = 'DELETE';
                $swoole->server['request_uri'] = "/v1/databases/$databaseId/collections/$collectionId/documents/$documentId";
                $swoole->server['path_info'] = "/v1/databases/$databaseId/collections/$collectionId/documents/$documentId";

                self::resolve($utopia, $request, $response, $resolve, $reject);
            }
        );
    }

    /**
     * @param App $utopia
     * @param Request $request
     * @param Response $response
     * @param callable $resolve
     * @param callable $reject
     * @return void
     * @throws Exception
     */
    public static function resolve(
        App $utopia,
        Request $request,
        Response $response,
        callable $resolve,
        callable $reject,
        ?callable $beforeResolve = null,
        ?callable $beforeReject = null,
    ): void {
        // Drop json content type so post args are used directly
        if ($request->getHeader('content-type') === 'application/json') {
            unset($request->getSwoole()->header['content-type']);
        }

        $request = new Request($request->getSwoole());
        $utopia->setResource('request', static fn() => $request);
        $response->setContentType(Response::CONTENT_TYPE_NULL);

        try {
            $route = $utopia->match($request, fresh: true);

            $utopia->execute($route, $request);
        } catch (\Throwable $e) {
            if ($beforeReject) {
                $e = $beforeReject($e);
            }
            $reject($e);
            return;
        }

        $payload = $response->getPayload();

        if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 400) {
            if ($beforeReject) {
                $payload = $beforeReject($payload);
            }
            $reject(new GQLException(
                message: $payload['message'],
                code: $response->getStatusCode()
            ));
            return;
        }

        foreach ($payload as $key => $value) {
            if (\str_starts_with($key, '$')) {
                $escapedKey = \str_replace('$', '_', $key);
                $payload[$escapedKey] = $value;
                unset($payload[$key]);
            }
        }

        if ($beforeResolve) {
            $payload = $beforeResolve($payload);
        }

        $resolve($payload);
    }
}
