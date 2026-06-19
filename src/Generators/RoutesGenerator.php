<?php

namespace Hemilrajput\TypeGen\Generators;

use Illuminate\Support\Facades\Route as RouteFacade;

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
            foreach ($route->parameterNames() as $param) {
                // Check if parameter is optional in the URI pattern (e.g. {user?})
                $isOptional = str_contains($uri, '{'.$param.'?}');
                $params[$param] = [
                    'optional' => $isOptional,
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
        foreach ($routeMap as $name => $params) {
            if ($params === []) {
                $paramLines[] = "  T extends '{$name}' ? {} :";
            } else {
                $fields = [];
                foreach ($params as $paramName => $meta) {
                    $opt = $meta['optional'] ? '?' : '';
                    $fields[] = "{$paramName}{$opt}: string | number";
                }
                $fieldsStr = implode('; ', $fields);
                $paramLines[] = "  T extends '{$name}' ? { {$fieldsStr} } :";
            }
        }

        $routeParamsBody = implode("\n", $paramLines);
        $banner = $this->config['output']['banner'] ?? '';

        return <<<TS
{$banner}
export type RouteName =
  | {$routeNameUnion};

export type RouteParams<T extends RouteName> =
{$routeParamsBody}
  never;

TS;
    }
}
