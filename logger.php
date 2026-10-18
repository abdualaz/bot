<?php

if(!function_exists('bot_logger_file_path')){
function bot_logger_file_path(){
    $configured = isset($GLOBALS['botLogFile']) ? (string)$GLOBALS['botLogFile'] : '';
    if($configured !== ''){
        return $configured;
    }
    return __DIR__ . '/bot-runtime.log';
}
}

if(!function_exists('bot_logger_redact_value')){
function bot_logger_redact_value($value, $depth = 0){
    if($depth > 4){
        return '[depth-limit]';
    }

    if(is_array($value)){
        $out = [];
        foreach($value as $k => $v){
            $key = strtolower((string)$k);
            if(in_array($key, ['api_key','apikey','token','bot_token','authorization','password','secret'], true)){
                $out[$k] = '***';
                continue;
            }
            $out[$k] = bot_logger_redact_value($v, $depth + 1);
        }
        return $out;
    }

    if(is_object($value)){
        $arr = json_decode(json_encode($value), true);
        if(!is_array($arr)){
            return '[object]';
        }
        return bot_logger_redact_value($arr, $depth + 1);
    }

    if(is_string($value)){
        $str = $value;
        $apiKey = defined('API_KEY') ? (string)API_KEY : ((string)($GLOBALS['botToken'] ?? ''));
        if($apiKey !== ''){
            $str = str_replace($apiKey, '***', $str);
        }

        // Remove common query-style secrets in logs.
        $str = preg_replace('/(api_key|apikey|token|secret|password)=([^&\s]+)/i', '$1=***', $str);

        if(strlen($str) > 3500){
            $str = substr($str, 0, 3500) . '...[truncated]';
        }
        return $str;
    }

    return $value;
}
}

if(!function_exists('bot_logger_write')){
function bot_logger_write($event, $context = []){
    $line = [
        'time' => date('Y-m-d H:i:s'),
        'event' => (string)$event,
        'context' => bot_logger_redact_value($context),
    ];

    $file = bot_logger_file_path();

    if(@file_exists($file) && @filesize($file) > 5 * 1024 * 1024){
        @rename($file, $file . '.1');
    }

    $json = json_encode($line, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if($json === false){
        $json = '{"time":"' . date('Y-m-d H:i:s') . '","event":"json_encode_failed"}';
    }

    @file_put_contents($file, $json . PHP_EOL, FILE_APPEND | LOCK_EX);
}
}

if(!function_exists('bot_logger_register_handlers')){
function bot_logger_register_handlers($scope = 'runtime'){
    static $registered = false;
    if($registered){
        return;
    }
    $registered = true;

    set_error_handler(function($severity, $message, $file, $line) use ($scope){
        bot_logger_write('php.error', [
            'scope' => $scope,
            'severity' => $severity,
            'message' => $message,
            'file' => $file,
            'line' => $line,
        ]);
        return false;
    });

    set_exception_handler(function($e) use ($scope){
        bot_logger_write('php.exception', [
            'scope' => $scope,
            'class' => get_class($e),
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
        ]);
    });

    register_shutdown_function(function() use ($scope){
        $last = error_get_last();
        if(!$last){
            return;
        }
        $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR];
        if(in_array($last['type'], $fatalTypes, true)){
            bot_logger_write('php.fatal', [
                'scope' => $scope,
                'type' => $last['type'],
                'message' => $last['message'],
                'file' => $last['file'],
                'line' => $last['line'],
            ]);
        }
    });
}
}
