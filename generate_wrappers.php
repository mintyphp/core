<?php

/**
 * Wrapper Generator for MintyPHP Core Classes
 * 
 * This script automatically generates static wrapper classes that provide a facade pattern
 * for instance-based Core classes. For each Core class (e.g., Core/Template.php), it creates
 * a corresponding wrapper class (e.g., Template.php) that uses a singleton pattern to provide
 * a convenient static API.
 * 
 * The generator performs the following operations:
 * 1. Scans Core classes for static variables starting with __ (configuration parameters)
 * 2. Creates public static variables in the wrapper (without __ prefix) for configuration
 * 3. Preserves PHPDoc comments from Core static variables in the generated wrappers
 * 4. Copies static utility functions starting with __ from Core to wrapper
 * 5. Copies the class docblock from the Core class to the wrapper
 * 6. Generates getInstance() method that instantiates Core class with static variables
 * 7. Generates setInstance() method for dependency injection and testing
 * 8. Creates static wrapper methods for all public Core instance methods
 * 9. Preserves all PHPDoc comments from Core methods in the generated wrappers
 * 
 * This allows users to call Template::render() instead of Template::getInstance()->render()
 * while maintaining proper dependency injection and testability through setInstance().
 */

// Configuration
$coreDir = __DIR__ . '/src/Core';
$wrapperDir = __DIR__ . '/src';

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

$generatedCount = 0;

foreach ($classes as $className) {
    $coreFile = "$coreDir/$className.php";
    $wrapperFile = "$wrapperDir/$className.php";

    if (!file_exists($coreFile)) {
        echo "Error: Skipping $className - Core file not found\n";
        continue;
    }

    // Parse the Core class
    $coreContent = file_get_contents($coreFile);
    if ($coreContent === false) {
        echo "Error: Skipping $className - Could not read file\n";
        continue;
    }

    // Extract namespace
    preg_match('/namespace\s+([^;]+);/', $coreContent, $namespaceMatch);
    $coreNamespace = $namespaceMatch[1] ?? 'MintyPHP\\Core';
    $wrapperNamespace = 'MintyPHP';

    // Extract class docblock
    $classDocblock = '';
    preg_match('/(\/\*\*(?:(?!\*\/).)*?\*\/)\s*class\s+' . preg_quote($className, '/') . '\s/s', $coreContent, $classDocMatch);
    if (isset($classDocMatch[1])) {
        $classDocblock = trim($classDocMatch[1]);
    } else {
        echo "Warning: $className class has no docblock\n";
    }

    // Find all static variables starting with __ (public, private, or protected) with optional docblocks
    preg_match_all('/(\/\*\*(?:(?!\*\/).)*?\*\/\s+)?(public|private|protected)\s+static\s+(\??[a-zA-Z]+(?:\|[a-zA-Z]+)*)\s+\$__([a-zA-Z_]+)\s*=\s*([^;]+);/s', $coreContent, $staticVarMatches, PREG_SET_ORDER);

    $staticVars = [];
    $constructorParams = [];

    foreach ($staticVarMatches as $match) {
        $docblock = isset($match[1]) && trim($match[1]) ? trim($match[1]) : '';
        $visibility = $match[2];
        $type = $match[3];
        $name = $match[4];
        $defaultValue = $match[5];

        $staticVars[] = [
            'visibility' => $visibility,
            'type' => $type,
            'name' => $name,
            'default' => $defaultValue,
            'docblock' => $docblock
        ];

        $constructorParams[] = [
            'type' => $type,
            'name' => $name,
            'varName' => '$' . $name
        ];
    }

    // Extract the constructor signature from Core class
    // Use a more specific pattern to find the constructor of the main class (not nested classes)
    // Match: class ClassName { ... public function __construct(...) }
    $classPattern = '/class\s+' . preg_quote($className, '/') . '\s*\{([^}]*(?:\{[^}]*\}[^}]*)*?)public\s+function\s+__construct\s*\(([^)]*)\)/s';
    preg_match($classPattern, $coreContent, $constructorMatch);
    $constructorSignature = $constructorMatch[2] ?? '';

    // Parse constructor parameters
    $coreConstructorParams = [];
    if ($constructorSignature) {
        $params = explode(',', $constructorSignature);
        foreach ($params as $param) {
            $param = trim($param);
            if (preg_match('/(\??[a-zA-Z]+(?:\|[a-zA-Z]+)*)\s+\$([a-zA-Z_]+)(\s*=\s*[^,]+)?/', $param, $paramMatch)) {
                $coreConstructorParams[] = [
                    'type' => $paramMatch[1],
                    'name' => $paramMatch[2],
                    'hasDefault' => isset($paramMatch[3]) && !empty(trim($paramMatch[3]))
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

    // Find public methods in Core class (excluding constructor) with their docblocks
    // Match docblock followed by public function, where docblock doesn't contain another docblock
    preg_match_all('/(\/\*\*(?:(?!\*\/).)*?\*\/)\s*public\s+function\s+([a-zA-Z_][a-zA-Z0-9_]*)\s*\(([^)]*)\)\s*:\s*([^{]+)|public\s+function\s+([a-zA-Z_][a-zA-Z0-9_]*)\s*\(([^)]*)\)\s*:\s*([^{]+)/s', $coreContent, $methodMatches, PREG_SET_ORDER);

    $methods = [];
    foreach ($methodMatches as $match) {
        // Handle two cases: with docblock and without docblock
        if (isset($match[2]) && $match[2]) {
            // Has docblock
            $docblock = trim($match[1]);
            $methodName = $match[2];
            $paramSignature = trim($match[3]);
            $returnType = trim($match[4]);
        } else {
            // No docblock
            $docblock = '';
            $methodName = $match[5];
            $paramSignature = trim($match[6]);
            $returnType = trim($match[7]);
        }

        if ($methodName === '__construct') {
            continue;
        }

        // Warn if method has no docblock
        if (empty($docblock)) {
            echo "Warning: $className::$methodName() has no docblock\n";
        }

        // Parse parameters
        $params = [];
        $paramNames = [];
        if ($paramSignature) {
            // Split by comma and process each parameter
            $paramParts = explode(',', $paramSignature);
            foreach ($paramParts as $paramPart) {
                $paramPart = trim($paramPart);
                if (preg_match('/\$([a-zA-Z_]+)/', $paramPart, $paramNameMatch)) {
                    $params[] = $paramPart;
                    // Check if this parameter is variadic
                    if (preg_match('/\.\.\.\$' . $paramNameMatch[1] . '/', $paramPart)) {
                        $paramNames[] = '...$' . $paramNameMatch[1];
                    } else {
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
            'returnType' => $returnType,
            'docblock' => $docblock
        ];
    }

    // Check for constructor parameters without matching static variables
    foreach ($coreConstructorParams as $param) {
        $found = false;
        foreach ($staticVars as $var) {
            if ($var['name'] === $param['name']) {
                $found = true;
                break;
            }
        }
        if (!$found && !$param['hasDefault']) {
            // Check if the parameter type is a Core class
            $paramType = $param['type'];
            $baseType = str_replace('?', '', $paramType);
            // Don't warn if it's a Core class (will be autowired via getInstance())
            if (!preg_match('/^[A-Z][a-zA-Z]*$/', $baseType)) {
                echo "Warning: $className - Constructor parameter \${$param['name']} has no matching static variable \$__{$param['name']}\n";
            }
        }
    }

    // Generate wrapper class
    $wrapperContent = generateWrapperClass(
        $className,
        $wrapperNamespace,
        $coreNamespace,
        $staticVars,
        $staticFunctions,
        $coreConstructorParams,
        $methods,
        $classDocblock,
        $classes
    );

    // Write wrapper file
    file_put_contents($wrapperFile, $wrapperContent);
    $generatedCount++;
}

echo "Done: $generatedCount wrappers written\n";

/**
 * Generate the wrapper class content
 * @param array<int, array{visibility: string, type: string, name: string, default: string, docblock: string}> $staticVars
 * @param array<int, array{visibility: string, name: string, paramSignature: string, returnType: string}> $staticFunctions
 * @param array<int, array{type: string, name: string}> $coreConstructorParams
 * @param array<int, array{name: string, params: array<int, string>, paramNames: array<int, string>, paramSignature: string, returnType: string, docblock: string}> $methods
 * @param array<int, string> $classes
 */
function generateWrapperClass(
    string $className,
    string $wrapperNamespace,
    string $coreNamespace,
    array $staticVars,
    array $staticFunctions,
    array $coreConstructorParams,
    array $methods,
    string $classDocblock,
    array $classes
): string {
    $coreClassName = "Core$className";
    $code = "<?php\n\n";
    $code .= "/**\n";
    $code .= " * WARNING: This is a generated wrapper file.\n";
    $code .= " * Do not edit this file manually as changes will be overwritten\n";
    $code .= " * by the wrapper generator (generate_wrappers.php).\n";
    $code .= " * \n";
    $code .= " * To modify this class, edit the corresponding Core class in src/Core/\n";
    $code .= " * and regenerate the wrappers.\n";
    $code .= " */\n\n";
    $code .= "namespace $wrapperNamespace;\n\n";
    $code .= "use $coreNamespace\\$className as $coreClassName;\n\n";

    // Use copied class docblock from Core class if available, otherwise generate default
    if (!empty($classDocblock)) {
        $docblockLines = explode("\n", $classDocblock);
        foreach ($docblockLines as $line) {
            $code .= $line . "\n";
        }
    } else {
        $code .= "/**\n";
        $code .= " * Static wrapper class for $className operations using a singleton pattern.\n";
        $code .= " */\n";
    }

    $code .= "class $className\n";
    $code .= "{\n";

    // Generate static variables (without __ prefix)
    if (!empty($staticVars)) {
        foreach ($staticVars as $var) {
            // Add docblock if available
            if (!empty($var['docblock'])) {
                $docblockLines = explode("\n", $var['docblock']);
                foreach ($docblockLines as $line) {
                    if (trim($line) === '') {
                        $code .= "\n";
                    } else {
                        // Remove first 4 spaces or a tab if present, then add 4 spaces
                        $contentPart = preg_replace('/^(\t|    )/', '', $line);
                        $code .= "    " . $contentPart . "\n";
                    }
                }
            }
            $code .= "    {$var['visibility']} static {$var['type']} \${$var['name']} = {$var['default']};\n";
        }
        $code .= "\n";
    }

    // Copy static functions starting with __
    if (!empty($staticFunctions)) {
        $code .= "    /**\n";
        $code .= "     * Static functions copied from Core class\n";
        $code .= "     */\n";
        foreach ($staticFunctions as $func) {
            $code .= "    {$func['visibility']} static function {$func['name']}({$func['paramSignature']}): {$func['returnType']}\n";
            $code .= "    {\n";
            $code .= "        return $coreClassName::{$func['name']}(";
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
            $code .= "    }\n\n";
        }
    }

    // Generate instance variable
    $code .= "    /**\n";
    $code .= "     * The $className instance\n";
    $code .= "     * @var ?$coreClassName\n";
    $code .= "     */\n";
    $code .= "    private static ?$coreClassName \$instance = null;\n\n";

    // Generate getInstance method
    $code .= "    /**\n";
    $code .= "     * Get the $className instance\n";
    $code .= "     * @return $coreClassName\n";
    $code .= "     */\n";
    $code .= "    public static function getInstance(): $coreClassName\n";
    $code .= "    {\n";
    $code .= "        return self::\$instance ??= new $coreClassName(\n";

    // Match constructor parameters with static variables
    $constructorArgs = [];
    foreach ($coreConstructorParams as $param) {
        // Check if we have a matching static variable
        $found = false;
        foreach ($staticVars as $var) {
            if ($var['name'] === $param['name']) {
                $constructorArgs[] = "            self::\${$var['name']}";
                $found = true;
                break;
            }
        }
        if (!$found) {
            // No matching static variable
            // Check if the parameter type is a Core class
            $paramType = $param['type'];
            // Remove optional marker and extract base type
            $baseType = str_replace('?', '', $paramType);
            $isOptional = str_starts_with($paramType, '?');

            // Check if it's a known Core class by checking if the file exists in Core directory
            if (in_array($baseType, $classes)) {
                // It's a Core class
                if ($isOptional) {
                    // For optional Core class parameters, check the $enabled property
                    $constructorArgs[] = "            $baseType::\$enabled ? $baseType::getInstance() : null";
                } else {
                    // For required Core class parameters, call getInstance()
                    $constructorArgs[] = "            $baseType::getInstance()";
                }
            } else {
                // Not a Core class, use null
                $constructorArgs[] = "            null";
            }
        }
    }

    $code .= implode(",\n", $constructorArgs);
    $code .= "\n        );\n";
    $code .= "    }\n\n";

    // Generate setInstance method
    $code .= "    /**\n";
    $code .= "     * Set the $className instance to use\n";
    $code .= "     * @param $coreClassName \$instance\n";
    $code .= "     * @return void\n";
    $code .= "     */\n";
    $code .= "    public static function setInstance($coreClassName \$instance): void\n";
    $code .= "    {\n";
    $code .= "        self::\$instance = \$instance;\n";
    $code .= "    }\n";

    // Generate wrapper methods
    foreach ($methods as $method) {
        $code .= "\n";

        // Use the original docblock from Core class if available
        if (!empty($method['docblock'])) {
            // Assume docblocks are indented with 4 spaces, remove that and add 4 spaces
            $docblockLines = explode("\n", $method['docblock']);
            foreach ($docblockLines as $line) {
                if (trim($line) === '') {
                    $code .= "\n";
                } else {
                    // Remove first 4 spaces if present, then add 4 spaces
                    $contentPart = preg_replace('/^    /', '', $line);
                    $code .= "    " . $contentPart . "\n";
                }
            }
        }

        // Method signature
        $paramSig = $method['paramSignature'] ? $method['paramSignature'] : '';
        $code .= "    public static function {$method['name']}($paramSig): {$method['returnType']}\n";
        $code .= "    {\n";
        $code .= "        \$instance = self::getInstance();\n";

        // Method call - handle void return type differently
        $paramCall = implode(', ', $method['paramNames']);
        $returnType = trim($method['returnType']);
        if ($returnType === 'void') {
            $code .= "        \$instance->{$method['name']}($paramCall);\n";
        } else {
            $code .= "        return \$instance->{$method['name']}($paramCall);\n";
        }
        $code .= "    }\n";
    }

    $code .= "}\n";

    return $code;
}
