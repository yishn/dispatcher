<?php

/**
 * yishn/dispatcher
 * MIT License
 */

function &context() {
    static $context = [];
    return $context;
}

function route($verbs, $paths, $funcs) {
    if (!is_array($verbs)) $verbs = [$verbs];
    if (!is_array($paths)) $paths = [$paths];
    if (!is_array($funcs)) $funcs = [$funcs];

    $route_inner = function($verb, $path, $funcs) {
        $context = &context();
        $context[] = [$verb, $path, $funcs];
    };

    foreach ($verbs as $verb) {
        foreach ($paths as $path) {
            $route_inner($verb, $path, $funcs);
        }
    }
}

function match($verb, $path, $route = null) {
    $context = &context();

    if ($route === null) {
        foreach ($context as $route) {
            $result = match($verb, $path, $route);
            if ($result !== null) return $result;
        }

        return null;
    }

    $match_verb = function($v1, $v2) {
        if ($v1 === '*' || $v2 === '*') return true;
        return strtoupper(trim($v1)) === strtoupper(trim($v2));
    };

    $match_path = function($pattern, $path) {
        if ($pattern === '*') return [];

        $escape_explode = function($delimiter, $string) {
            return preg_split('~(?<!\\\)' . preg_quote($delimiter, '~') . '~', $string);
        };

        $pattern = trim($pattern, '/');
        $path = trim($path, '/');
        $pattern_segments = $escape_explode('/', $pattern);
        $path_segments = explode('/', $path);
        $args = [];

        if (count($pattern_segments) !== count($path_segments)) return null;

        for ($i = 0; $i < count($path_segments); $i++) {
            $pattern_segment = $pattern_segments[$i];
            $path_segment = $path_segments[$i];

            if ($pattern_segment[0] !== ':') {
                if ($pattern_segment !== $path_segment)
                    break;
            } else {
                $name = substr($pattern_segment, 1);
                $regex = '/^[^\/]+$/';
                $index = strpos($pattern_segment, '@');

                if ($index !== false) {
                    $name = substr($pattern_segment, 1, $index - 1);
                    $regex = '/^' . substr($pattern_segment, $index + 1) . '$/';
                }

                if (!preg_match($regex, $path_segment)) break;
                $args[$name] = $path_segment;
            }

        }

        if ($i === count($path_segments)) return $args;
        return null;
    };

    if ($match_verb($route[0], $verb)) {
        $args = $match_path($route[1], $path);

        if ($args !== null) {
            return [$route, $args];
        }
    }

    return null;
}

function dispatch() {
    $verb = $_SERVER['REQUEST_METHOD'];
    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

    $match = match($verb, $path);

    if ($match === null) return;

    list($route, $args) = $match;

    foreach ($route[2] as $f) { $f($args); }
}
