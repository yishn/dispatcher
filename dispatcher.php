<?php

/**
 * dispatcher
 * https://github.com/yishn/dispatcher
 *
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

    $split_optional_paths = function($path) {
        $start = strpos($path, '(');
        if ($start === false) return [$path];

        $result = [];
        $depth = 0;

        for ($i = $start; $i < strlen($path); $i++) {
            if ($path[$i] == '(') $depth++;
            else if ($path[$i] == ')') $depth--;
            else continue;
            if ($depth == 0) break;
        }

        $result[] = substr($path, 0, $start);
        $result[] = $result[0]
            . substr($path, $start + 1, $i - 1 - $start)
            . substr($path, $i + 1);
            
        return $result;
    };

    $route_inner = function($verb, $path, $funcs) use($split_optional_paths) {
        if (strpos($path, '(') === false) {
            $context = &context();
            $context[] = [$verb, $path, $funcs];
        } else {
            $paths = $split_optional_paths($path);
            route($verb, $paths, $funcs);
        }
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

        $pattern_segments = $escape_explode('/', trim($pattern, '/'));
        $path_segments = explode('/', trim($path, '/'));
        $args = [];

        if (count($pattern_segments) !== count($path_segments)) return null;

        for ($i = 0; $i < count($path_segments); $i++) {
            $psegment = $pattern_segments[$i];
            $segment = $path_segments[$i];

            if ($psegment[0] !== ':') {
                if ($psegment !== $segment)
                    break;
            } else {
                $name = substr($psegment, 1);
                $regex = '/^[^\/]+$/';
                $index = strpos($psegment, '@');

                if ($index !== false) {
                    $name = substr($psegment, 1, $index - 1);
                    $regex = '/^' . substr($psegment, $index + 1) . '$/';
                }

                if (!preg_match($regex, $segment)) break;
                $args[$name] = $segment;
            }

        }

        if ($i === count($path_segments)) return $args;
        return null;
    };

    if ($match_verb($route[0], $verb)) {
        $args = $match_path($route[1], $path);

        if ($args !== null)
            return [$route, $args];
    }

    return null;
}

function dispatch() {
    $verb = $_SERVER['REQUEST_METHOD'];
    $path = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

    if ($verb == 'POST') {
        if (isset($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE']))
            $verb = $_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'];
        else
            $verb = isset($_POST['_method']) ? $_POST['_method'] : $verb;
    }

    $match = match($verb, $path);
    if ($match === null) return;

    list($route, $args) = $match;
    foreach ($route[2] as $f) $f($args);
}

function redirect($location, $code = 302) {
    http_response_code($code);
    header('Location: ' . $location);
    exit();
}

function render($__PATH, $__VARS) {
    extract($__VARS, EXTR_SKIP);
    include($__PATH);
}

function p($str, $flags = -1, $enc = 'UTF-8', $denc = true) {
    $flags = ($flags < 0 ? ENT_QUOTES : $flags);
    echo htmlentities($str, $flags, $enc, $denc);
}
