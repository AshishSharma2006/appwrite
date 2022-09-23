<?php

namespace Appwrite\GraphQL;

use Appwrite\Utopia\Response;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;
use Redis;
use Swoole\Coroutine\WaitGroup;
use Swoole\Http\Response as SwooleResponse;
use Utopia\App;
use Utopia\Database\Database;
use Utopia\Database\Query;
use Utopia\Database\Validator\Authorization;
use Utopia\Route;

class SchemaBuilder
{
    /**
     * @throws \Exception
     */
    public static function buildSchema(
        App $utopia,
        Redis $cache,
        Database $dbForProject,
        string $projectId,
    ): Schema {
        App::setResource('current', static fn() => $utopia);

        $appVersion = App::getEnv('_APP_VERSION');
        $apiSchemaKey = 'api-schema';
        $apiVersionKey = 'api-schema-version';
        $collectionSchemaKey = $projectId . '-collection-schema';
        $collectionsDirtyKey = $projectId . '-schema-dirty';
        $fullSchemaKey = $projectId . '-full-schema';

        $schemaVersion = $cache->get($apiVersionKey) ?: '';
        $collectionSchemaDirty = $cache->get($collectionsDirtyKey);
        $apiSchemaDirty = \version_compare($appVersion, $schemaVersion, "!=");

        if ($cache->exists($apiSchemaKey) && !$apiSchemaDirty) {
            $apiSchema = \json_decode($cache->get($apiSchemaKey), true);

            foreach ($apiSchema['query'] as $field => $attributes) {
                $attributes['resolve'] = ResolverRegistry::get(
                    type: 'api',
                    field: $field,
                    utopia: $utopia,
                    cache: $cache,
                );
            }
            foreach ($apiSchema['mutation'] as $field => $attributes) {
                $attributes['resolve'] = ResolverRegistry::get(
                    type: 'api',
                    field: $field,
                    utopia: $utopia,
                    cache: $cache,
                );
            }

            \var_dump('API schema loaded from cache');
        } else {
            // Not in cache or API version changed, build schema
            \var_dump('API schema not in cache or API version changed, building schema');

            $apiSchema = &self::buildAPISchema($utopia, $cache);
            $cache->set($apiSchemaKey, \json_encode($apiSchema));
            $cache->set($apiVersionKey, $appVersion);
        }

        if ($cache->exists($collectionSchemaKey) && !$collectionSchemaDirty) {
            $collectionSchema = \json_decode($cache->get($collectionSchemaKey), true);

            foreach ($collectionSchema['query'] as $field => $attributes) {
                $attributes['resolve'] = ResolverRegistry::get(
                    type: 'collection',
                    field: $field,
                    utopia: $utopia,
                    cache: $cache,
                    dbForProject: $dbForProject,
                );
            }
            foreach ($collectionSchema['mutation'] as $field => $attributes) {
                $attributes['resolve'] = ResolverRegistry::get(
                    type: 'collection',
                    field: $field,
                    utopia: $utopia,
                    cache: $cache,
                    dbForProject: $dbForProject
                );
            }

            \var_dump('Collection schema loaded from cache');
        } else {
            // Not in cache or collections changed, build schema
            \var_dump('Collection schema not in cache or collections changed, building schema');

            $collectionSchema = &self::buildCollectionSchema($utopia, $cache, $dbForProject);
            $cache->set($collectionSchemaKey, \json_encode($collectionSchema));
            $cache->del($collectionsDirtyKey);
        }

        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'Query',
                'fields' => \array_merge_recursive(
                    $apiSchema['query'],
                    $collectionSchema['query']
                )
            ]),
            'mutation' => new ObjectType([
                'name' => 'Mutation',
                'fields' => \array_merge_recursive(
                    $apiSchema['mutation'],
                    $collectionSchema['mutation']
                )
            ])
        ]);

        return $schema;
    }

    /**
     * This function iterates all API routes and builds a GraphQL
     * schema defining types and resolvers for all response models.
     *
     * @param App $utopia
     * @return array
     * @throws \Exception
     */
    public static function &buildAPISchema(
        App $utopia,
        Redis $cache,
    ): array {
        $queryFields = [];
        $mutationFields = [];
        $response = new Response(new SwooleResponse());
        $models = $response->getModels();

        TypeRegistry::init($models);

        foreach (App::getRoutes() as $method => $routes) {
            foreach ($routes as $route) {
                /** @var Route $route */

                if (\str_starts_with($route->getPath(), '/v1/mock/')) {
                    continue;
                }
                $namespace = $route->getLabel('sdk.namespace', '');
                $methodName = $namespace . \ucfirst($route->getLabel('sdk.method', ''));
                $responseModelNames = $route->getLabel('sdk.response.model', 'none');
                $responseModels = \is_array($responseModelNames)
                    ? \array_map(static fn($m) => $models[$m], $responseModelNames)
                    : [$models[$responseModelNames]];

                foreach ($responseModels as $responseModel) {
                    $type = TypeRegistry::get($responseModel->getType());
                    $description = $route->getDesc();
                    $params = [];
                    $list = false;

                    foreach ($route->getParams() as $key => $value) {
                        if ($key === 'queries') {
                            $list = true;
                        }
                        $argType = TypeMapper::fromRouteParameter(
                            $utopia,
                            $value['validator'],
                            !$value['optional'],
                            $value['injections']
                        );
                        $params[$key] = [
                            'type' => $argType,
                            'description' => $value['description'],
                            'defaultValue' => $value['default']
                        ];
                    }

                    $field = [
                        'type' => $type,
                        'description' => $description,
                        'args' => $params,
                        'resolve' => ResolverRegistry::get(
                            type: 'api',
                            field: $methodName,
                            utopia: $utopia,
                            cache: $cache,
                            path: $route->getPath(),
                            method: $route->getMethod(),
                        )
                    ];

                    if ($list) {
                        $field['complexity'] = function (int $complexity, array $args) {
                            $queries = Query::parseQueries($args['queries'] ?? []);
                            $query = Query::getByType($queries, Query::TYPE_LIMIT)[0] ?? null;
                            $limit = $query ? $query->getValue() : APP_LIMIT_LIST_DEFAULT;

                            return $complexity * $limit;
                        };
                    }

                    switch ($method) {
                        case 'GET':
                            $queryFields[$methodName] = $field;
                            break;
                        case 'POST':
                        case 'PUT':
                        case 'PATCH':
                        case 'DELETE':
                            $mutationFields[$methodName] = $field;
                            break;
                        default:
                            throw new \Exception("Unsupported method: $method");
                    }
                }
            }
        }

        $schema = [
            'query' => $queryFields,
            'mutation' => $mutationFields
        ];

        return $schema;
    }

    /**
     * Iterates all a projects attributes and builds GraphQL
     * queries and mutations for the collections they make up.
     *
     * @param App $utopia
     * @param Database $dbForProject
     * @return array
     * @throws \Exception
     */
    public static function &buildCollectionSchema(
        App $utopia,
        Redis $cache,
        Database $dbForProject
    ): array {
        $collections = [];
        $queryFields = [];
        $mutationFields = [];
        $limit = 1000;
        $offset = 0;
        $count = 0;

        $wg = new WaitGroup();

        while (
            !empty($attrs = Authorization::skip(fn() => $dbForProject->find(
                collection: 'attributes',
                queries: [
                    Query::limit($limit),
                    Query::offset($offset),
                ]
            )))
        ) {
            $wg->add();
            $count += count($attrs);
            \go(function () use ($utopia, $cache, $dbForProject, &$collections, &$queryFields, &$mutationFields, $limit, &$offset, $attrs, $wg) {
                foreach ($attrs as $attr) {
                    if ($attr->getAttribute('status') !== 'available') {
                        continue;
                    }
                    $databaseId = $attr->getAttribute('databaseId');
                    $collectionId = $attr->getAttribute('collectionId');
                    $key = $attr->getAttribute('key');
                    $type = $attr->getAttribute('type');
                    $array = $attr->getAttribute('array');
                    $required = $attr->getAttribute('required');
                    $default = $attr->getAttribute('default');
                    $escapedKey = str_replace('$', '_', $key);
                    $collections[$collectionId][$escapedKey] = [
                        'type' => TypeMapper::fromCollectionAttribute(
                            $type,
                            $array,
                            $required
                        ),
                        'defaultValue' => $default,
                    ];
                }

                foreach ($collections as $collectionId => $attributes) {
                    $objectType = new ObjectType([
                        'name' => $collectionId,
                        'fields' => \array_merge(
                            ["_id" => ['type' => Type::string()]],
                            $attributes
                        ),
                    ]);
                    $attributes = \array_merge(
                        $attributes,
                        TypeRegistry::argumentsFor('mutate')
                    );

                    $queryFields[$collectionId . 'Get'] = [
                        'type' => $objectType,
                        'args' => TypeRegistry::argumentsFor('id'),
                        'resolve' => ResolverRegistry::get(
                            type: 'collection',
                            field: $collectionId . 'Get',
                            utopia: $utopia,
                            cache: $cache,
                            dbForProject: $dbForProject,
                            method: 'get',
                            databaseId: $databaseId,
                            collectionId: $collectionId,
                        )
                    ];
                    $queryFields[$collectionId . 'List'] = [
                        'type' => Type::listOf($objectType),
                        'args' => TypeRegistry::argumentsFor('list'),
                        'resolve' => ResolverRegistry::get(
                            type: 'collection',
                            field: $collectionId . 'List',
                            utopia: $utopia,
                            cache: $cache,
                            dbForProject: $dbForProject,
                            method: 'list',
                            databaseId: $databaseId,
                            collectionId: $collectionId,
                        ),
                        'complexity' => function (int $complexity, array $args) {
                            $queries = Query::parseQueries($args['queries'] ?? []);
                            $query = Query::getByType($queries, Query::TYPE_LIMIT)[0] ?? null;
                            $limit = $query ? $query->getValue() : APP_LIMIT_LIST_DEFAULT;

                            return $complexity * $limit;
                        },
                    ];

                    $mutationFields[$collectionId . 'Create'] = [
                        'type' => $objectType,
                        'args' => $attributes,
                        'resolve' => ResolverRegistry::get(
                            type: 'collection',
                            field: $collectionId . 'Create',
                            utopia: $utopia,
                            cache: $cache,
                            dbForProject: $dbForProject,
                            method: 'create',
                            databaseId: $databaseId,
                            collectionId: $collectionId,
                        )
                    ];
                    $mutationFields[$collectionId . 'Update'] = [
                        'type' => $objectType,
                        'args' => \array_merge(
                            TypeRegistry::argumentsFor('id'),
                            \array_map(
                                fn($attr) => $attr['type'] = Type::getNullableType($attr['type']),
                                $attributes
                            )
                        ),
                        'resolve' => ResolverRegistry::get(
                            type: 'collection',
                            field: $collectionId . 'Create',
                            utopia: $utopia,
                            cache: $cache,
                            dbForProject: $dbForProject,
                            method: 'create',
                            databaseId: $databaseId,
                            collectionId: $collectionId,
                        )
                    ];
                    $mutationFields[$collectionId . 'Delete'] = [
                        'type' => TypeRegistry::get(Response::MODEL_NONE),
                        'args' => TypeRegistry::argumentsFor('id'),
                        'resolve' => ResolverRegistry::get(
                            type: 'collection',
                            field: $collectionId . 'Delete',
                            utopia: $utopia,
                            cache: $cache,
                            dbForProject: $dbForProject,
                            method: 'delete',
                            databaseId: $databaseId,
                            collectionId: $collectionId,
                        )
                    ];
                }
                $wg->done();
            });
            $offset += $limit;
        }
        $wg->wait();

        $schema = [
            'query' => $queryFields,
            'mutation' => $mutationFields
        ];

        return $schema;
    }
}
