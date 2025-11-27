<?php

/**
 * Wrapper Generator for MintyPHP Core Classes
 * 
 * This script generates static wrapper classes for Core classes by:
 * 1. Finding all static variables (public/private/protected) starting with __ in Core classes
 * 2. Creating matching static variables (without __) in wrapper
 * 3. Copying any static functions starting with __ to the wrapper
 * 4. Creating getInstance() that passes static variables to Core constructor
 * 5. Finding public methods in Core classes
 * 6. Creating static wrapper methods that call the instance methods
 */

// Configuration
$coreDir = __DIR__ . '/Core';
$wrapperDir = __DIR__;

// Find all Core classes by listing the Core directory
$classes = [];
$files = scandir($coreDir);
if ($files === false) {
    echo "Error: Could not read Core directory\n";
    exit(1);
}
foreach ($files as $file) {
    if ($file === '.' || $file === '..') {
        continue;
    }
    if (pathinfo($file, PATHINFO_EXTENSION) === 'php') {
        $classes[] = pathinfo($file, PATHINFO_FILENAME);
    }
}

foreach ($classes as $className) {
    $coreFile = "$coreDir/$className.php";
    $wrapperFile = "$wrapperDir/$className.php";

    if (!file_exists($coreFile)) {
        echo "Skipping $className: Core file not found\n";
        continue;
    }

    echo "Processing $className...\n";

    // Parse the Core class
    $coreContent = file_get_contents($coreFile);
    if ($coreContent === false) {
        echo "Skipping $className: Could not read file\n";
        continue;
    }

    // Extract namespace
    preg_match('/namespace\s+([^;]+);/', $coreContent, $namespaceMatch);
    $coreNamespace = $namespaceMatch[1] ?? 'MintyPHP\\Core';
    $wrapperNamespace = 'MintyPHP';

    // Find all static variables starting with __ (public, private, or protected)
    preg_match_all('/(public|private|protected)\s+static\s+(\??[a-zA-Z]+(?:\|[a-zA-Z]+)*)\s+\$__([a-zA-Z_]+)\s*=\s*([^;]+);/', $coreContent, $staticVarMatches, PREG_SET_ORDER);

    $staticVars = [];
    $constructorParams = [];

    foreach ($staticVarMatches as $match) {
        $visibility = $match[1];
        $type = $match[2];
        $name = $match[3];
        $defaultValue = $match[4];

        $staticVars[] = [
            'visibility' => $visibility,
            'type' => $type,
            'name' => $name,
            'default' => $defaultValue
        ];

        $constructorParams[] = [
            'type' => $type,
            'name' => $name,
            'varName' => '$' . $name
        ];
    }

    // Extract the constructor signature from Core class
    preg_match('/public\s+function\s+__construct\s*\(([^)]*)\)/', $coreContent, $constructorMatch);
    $constructorSignature = $constructorMatch[1] ?? '';

    // Parse constructor parameters
    $coreConstructorParams = [];
    if ($constructorSignature) {
        $params = explode(',', $constructorSignature);
        foreach ($params as $param) {
            $param = trim($param);
            if (preg_match('/(\??[a-zA-Z]+(?:\|[a-zA-Z]+)*)\s+\$([a-zA-Z_]+)/', $param, $paramMatch)) {
                $coreConstructorParams[] = [
                    'type' => $paramMatch[1],
                    'name' => $paramMatch[2]
                ];
            }
        }
    }

    // Find static functions starting with __ (any visibility)
    preg_match_all('/(public|private|protected)\s+static\s+function\s+(__[a-zA-Z_][a-zA-Z0-9_]*)\s*\(([^)]*)\)\s*:\s*([^{\n]+)/', $coreContent, $staticFuncMatches, PREG_SET_ORDER);

    $staticFunctions = [];
    foreach ($staticFuncMatches as $match) {
        $visibility = $match[1];
        $funcName = $match[2];
        $paramSignature = trim($match[3]);
        $returnType = trim($match[4]);

        $staticFunctions[] = [
            'visibility' => $visibility,
            'name' => $funcName,
            'paramSignature' => $paramSignature,
            'returnType' => $returnType
        ];
    }

    // Find public methods in Core class (excluding constructor)
    preg_match_all('/public\s+function\s+([a-zA-Z_][a-zA-Z0-9_]*)\s*\(([^)]*)\)\s*:\s*([^{]+)/', $coreContent, $methodMatches, PREG_SET_ORDER);

    $methods = [];
    foreach ($methodMatches as $match) {
        $methodName = $match[1];
        if ($methodName === '__construct') {
            continue;
        }

        $paramSignature = trim($match[2]);
        $returnType = trim($match[3]);

        // Parse parameters
        $params = [];
        $paramNames = [];
        if ($paramSignature) {
            // Handle variadic parameters and complex signatures
            if (preg_match('/\.\.\.\$([a-zA-Z_]+)/', $paramSignature, $variadicMatch)) {
                $params[] = $paramSignature;
                $paramNames[] = '...$' . $variadicMatch[1];
            } else {
                $paramParts = explode(',', $paramSignature);
                foreach ($paramParts as $paramPart) {
                    $paramPart = trim($paramPart);
                    if (preg_match('/\$([a-zA-Z_]+)/', $paramPart, $paramNameMatch)) {
                        $params[] = $paramPart;
                        $paramNames[] = '$' . $paramNameMatch[1];
                    }
                }
            }
        }

        $methods[] = [
            'name' => $methodName,
            'params' => $params,
            'paramNames' => $paramNames,
            'paramSignature' => $paramSignature,
            'returnType' => $returnType
        ];
    }

    // Generate wrapper class
    $wrapperContent = generateWrapperClass(
        $className,
        $wrapperNamespace,
        $coreNamespace,
        $staticVars,
        $staticFunctions,
        $coreConstructorParams,
        $methods
    );

    // Write wrapper file
    file_put_contents($wrapperFile, $wrapperContent);
    echo "Generated $wrapperFile\n";
}

echo "Done!\n";

/**
 * Generate the wrapper class content
 * @param array<int, array{visibility: string, type: string, name: string, default: string}> $staticVars
 * @param array<int, array{visibility: string, name: string, paramSignature: string, returnType: string}> $staticFunctions
 * @param array<int, array{type: string, name: string}> $coreConstructorParams
 * @param array<int, array{name: string, params: array<int, string>, paramNames: array<int, string>, paramSignature: string, returnType: string}> $methods
 */
function generateWrapperClass(
    string $className,
    string $wrapperNamespace,
    string $coreNamespace,
    array $staticVars,
    array $staticFunctions,
    array $coreConstructorParams,
    array $methods
): string {
    $coreClassName = "Core$className";
    $code = "<?php\n\n";
    $code .= "namespace $wrapperNamespace;\n\n";
    $code .= "use $coreNamespace\\$className as $coreClassName;\n\n";
    $code .= "/**\n";
    $code .= " * Static wrapper class for $className operations using a singleton pattern.\n";
    $code .= " */\n";
    $code .= "class $className\n";
    $code .= "{\n";

    // Generate static variables (without __ prefix)
    if (!empty($staticVars)) {
        $code .= "\t/**\n";
        $code .= "\t * Configuration parameters\n";
        $code .= "\t */\n";
        foreach ($staticVars as $var) {
            $code .= "\t{$var['visibility']} static {$var['type']} \${$var['name']} = {$var['default']};\n";
        }
        $code .= "\n";
    }

    // Copy static functions starting with __
    if (!empty($staticFunctions)) {
        $code .= "\t/**\n";
        $code .= "\t * Static functions copied from Core class\n";
        $code .= "\t */\n";
        foreach ($staticFunctions as $func) {
            $code .= "\t{$func['visibility']} static function {$func['name']}({$func['paramSignature']}): {$func['returnType']}\n";
            $code .= "\t{\n";
            $code .= "\t\treturn $coreClassName::{$func['name']}(";
            // Extract parameter names for the call
            if ($func['paramSignature']) {
                preg_match_all('/\$([a-zA-Z_][a-zA-Z0-9_]*)/', $func['paramSignature'], $paramNames);
                $callParams = [];
                foreach ($paramNames[1] as $paramName) {
                    // Check if it's variadic
                    if (preg_match('/\.\.\.\$' . $paramName . '/', $func['paramSignature'])) {
                        $callParams[] = '...$' . $paramName;
                    } else {
                        $callParams[] = '$' . $paramName;
                    }
                }
                $code .= implode(', ', array_unique($callParams));
            }
            $code .= ");\n";
            $code .= "\t}\n\n";
        }
    }

    // Generate instance variable
    $code .= "\t/**\n";
    $code .= "\t * The $className instance\n";
    $code .= "\t * @var ?$coreClassName\n";
    $code .= "\t */\n";
    $code .= "\tprivate static ?$coreClassName \$instance = null;\n\n";

    // Generate getInstance method
    $code .= "\t/**\n";
    $code .= "\t * Get the $className instance\n";
    $code .= "\t * @return $coreClassName\n";
    $code .= "\t */\n";
    $code .= "\tpublic static function getInstance(): $coreClassName\n";
    $code .= "\t{\n";
    $code .= "\t\treturn self::\$instance ??= new $coreClassName(\n";

    // Match constructor parameters with static variables
    $constructorArgs = [];
    foreach ($coreConstructorParams as $param) {
        // Check if we have a matching static variable
        $found = false;
        foreach ($staticVars as $var) {
            if ($var['name'] === $param['name']) {
                $constructorArgs[] = "\t\t\tself::\${$var['name']}";
                $found = true;
                break;
            }
        }
        if (!$found) {
            // No matching static variable, use null or appropriate default
            $constructorArgs[] = "\t\t\tnull";
        }
    }

    $code .= implode(",\n", $constructorArgs);
    $code .= "\n\t\t);\n";
    $code .= "\t}\n\n";

    // Generate setInstance method
    $code .= "\t/**\n";
    $code .= "\t * Set the $className instance to use\n";
    $code .= "\t * @param $coreClassName \$instance\n";
    $code .= "\t * @return void\n";
    $code .= "\t */\n";
    $code .= "\tpublic static function setInstance($coreClassName \$instance): void\n";
    $code .= "\t{\n";
    $code .= "\t\tself::\$instance = \$instance;\n";
    $code .= "\t}\n";

    // Generate wrapper methods
    foreach ($methods as $method) {
        $code .= "\n";
        $code .= "\t/**\n";
        $code .= "\t * {$method['name']}\n";

        // Add param docs
        foreach ($method['params'] as $param) {
            if (preg_match('/^(.*?)(\.\.\.)?\$([a-zA-Z_]+)/', $param, $match)) {
                $paramType = trim($match[1]);
                $isVariadic = !empty($match[2]);
                $paramName = $match[3];
                if ($paramType) {
                    $code .= "\t * @param $paramType " . ($isVariadic ? '...' : '') . "\$$paramName\n";
                }
            }
        }

        $code .= "\t * @return {$method['returnType']}\n";
        $code .= "\t */\n";

        // Method signature
        $paramSig = $method['paramSignature'] ? $method['paramSignature'] : '';
        $code .= "\tpublic static function {$method['name']}($paramSig): {$method['returnType']}\n";
        $code .= "\t{\n";
        $code .= "\t\t\$instance = self::getInstance();\n";

        // Method call
        $paramCall = implode(', ', $method['paramNames']);
        $code .= "\t\treturn \$instance->{$method['name']}($paramCall);\n";
        $code .= "\t}\n";
    }

    $code .= "}\n";

    return $code;
}
