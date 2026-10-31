<?php

namespace Hemilrajput\TypeGen\Generators;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Route as RouteFacade;
use Illuminate\Support\Str;

class RoutesGenerator
{
    public function __construct(protected array $config) {}

    public function generate(): string
    {
        $routes = RouteFacade::getRoutes()->getRoutes();
        $routeMap = [];

        foreach ($routes as $route) {
            $name = $route->getName();
            if (! $name) {
                continue;
            }

            $params = [];
            $uri = $route->uri();

            // Laravel returns an array of ReflectionParameter for closure or controller
            $signatureParameters = [];
            try {
                $signatureParameters = $route->signatureParameters();
            } catch (\Throwable) {
                // Controller method might not exist
            }

            foreach ($route->parameterNames() as $param) {
                // Check if parameter is optional in the URI pattern (e.g. {user?})
                $isOptional = str_contains($uri, '{'.$param.'?}');
                $constraint = $route->wheres[$param] ?? null;
                $type = 'string | number';

                if ($constraint === '[0-9]+' || $constraint === '\d+') {
                    $type = 'number';
                } else {
                    foreach ($signatureParameters as $signatureParameter) {
                        if ($signatureParameter->getName() === $param || $signatureParameter->getName() === Str::camel($param)) {
                            $paramType = $signatureParameter->getType();
                            if ($paramType instanceof \ReflectionNamedType && ! $paramType->isBuiltin()) {
                                $className = $paramType->getName();
                                if (is_subclass_of($className, Model::class)) {
                                    try {
                                        $instance = new $className;
                                        $type = $instance->getKeyType() === 'int' ? 'number' : 'string';
                                    } catch (\Throwable) {
                                        // ignore
                                    }
                                }
                            }
                            break;
                        }
                    }
                }

                $params[$param] = [
                    'optional' => $isOptional,
                    'type' => $type,
                ];
            }

            $routeMap[$name] = $params;
        }

        ksort($routeMap);

        // Render TypeScript RouteName
        $names = array_keys($routeMap);
        if ($names === []) {
            return "export type RouteName = never;\nexport type RouteParams<T extends RouteName> = never;\n";
        }

        $routeNameUnion = implode("\n  | ", array_map(fn (string $n): string => "'{$n}'", $names));

        // Render RouteParams
        $paramLines = [];
        $mapLines = [];
        $ziggyLines = [];
        foreach ($routeMap as $name => $params) {
            if ($params === []) {
                $paramLines[] = "  T extends '{$name}' ? {} :";
                $mapLines[] = "  '{$name}': {};";
                $ziggyLines[] = "    '{$name}': [];";
            } else {
                $fields = [];
                $ziggyParams = [];
                foreach ($params as $paramName => $meta) {
                    $opt = $meta['optional'] ? '?' : '';
                    $type = $meta['type'];
                    $fields[] = "{$paramName}{$opt}: {$type}";
                    $reqStr = $meta['optional'] ? 'false' : 'true';
                    $ziggyParams[] = "{ name: '{$paramName}', required: {$reqStr} }";
                }
                $fieldsStr = implode('; ', $fields);
                $ziggyParamsStr = implode(', ', $ziggyParams);
                $paramLines[] = "  T extends '{$name}' ? { {$fieldsStr} } :";
                $mapLines[] = "  '{$name}': { {$fieldsStr} };";
                $ziggyLines[] = "    '{$name}': [{$ziggyParamsStr}];";
            }
        }

        $routeParamsBody = implode("\n", $paramLines);
        $routeParamsMapBody = implode("\n", $mapLines);
        $ziggyRoutesBody = implode("\n", $ziggyLines);
        $banner = $this->config['output']['banner'] ?? '';

        $ziggyBlock = ($this->config['routes']['ziggy'] ?? true)
            ? "\n\nexport type RouteParamsMap = {\n{$routeParamsMapBody}\n};\n\ndeclare module 'ziggy-js' {\n  interface RouteList {\n{$ziggyRoutesBody}\n  }\n}"
            : '';

        return <<<TS
{$banner}
export type RouteName =
  | {$routeNameUnion};

export type RouteParams<T extends RouteName> =
{$routeParamsBody}
  never;{$ziggyBlock}

TS;
    }
}
