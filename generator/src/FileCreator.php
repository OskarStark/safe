<?php

namespace Safe;

use function array_merge;
use function file_exists;

class FileCreator
{

    /**
     * This function generate an improved php lib function in a php file
     *
     * @param Method[] $functions
     * @param string $path
     */
    public function generatePhpFile(array $functions, string $path): void
    {
        $path = rtrim($path, '/').'/';
        $phpFunctionsByModule = [];
        foreach ($functions as $function) {
            $writePhpFunction = new WritePhpFunction($function);
            $phpFunctionsByModule[$function->getModuleName()][] = $writePhpFunction->getPhpFunctionalFunction();
        }

        foreach ($phpFunctionsByModule as $module => $phpFunctions) {
            $lcModule = \lcfirst($module);
            $stream = \fopen($path.$lcModule.'.php', 'w');
            if ($stream === false) {
                throw new \RuntimeException('Unable to write to '.$path);
            }
            \fwrite($stream, "<?php\n
namespace Safe;

use Safe\\Exceptions\\".self::toExceptionName($module). ';

');
            foreach ($phpFunctions as $phpFunction) {
                \fwrite($stream, $phpFunction."\n");
            }
            \fclose($stream);
        }
    }

    /**
     * @param Method[] $functions
     * @return string[]
     */
    private function getFunctionsNameList(array $functions): array
    {
        $functionNames = array_map(function (Method $function) {
            return $function->getFunctionName();
        }, $functions);
        $specialCases = require __DIR__.'/../config/specialCasesFunctions.php';
        $functionNames = array_merge($functionNames, $specialCases);
        natcasesort($functionNames);
        $excludeCases = require __DIR__.'/../config/ignoredFunctions.php';
        return array_diff($functionNames, $excludeCases);
    }


    /**
     * This function generate a PHP file containing the list of functions we can handle.
     *
     * @param Method[] $functions
     * @param string $path
     */
    public function generateFunctionsList(array $functions, string $path): void
    {
        $functionNames = $this->getFunctionsNameList($functions);
        $stream = fopen($path, 'w');
        if ($stream === false) {
            throw new \RuntimeException('Unable to write to '.$path);
        }
        fwrite($stream, "<?php\n
return [\n");
        foreach ($functionNames as $functionName) {
            fwrite($stream, '    '.\var_export($functionName, true).",\n");
        }
        fwrite($stream, "];\n");
        fclose($stream);
    }

    /**
     * @deprecated Official support for rector/rector:~0.5 is not advertised anymore.
     *
     * This function generate a rector yml file containing a replacer for all functions
     *
     * @param Method[] $functions
     * @param string $path
     */
    public function generateRectorFile(array $functions, string $path): void
    {
        $functionNames = $this->getFunctionsNameList($functions);
        $stream = fopen($path, 'w');
        if ($stream === false) {
            throw new \RuntimeException('Unable to write to '.$path);
        }
        fwrite($stream, "# This rector file is replacing all core PHP functions with the equivalent \"safe\" functions
# It is targetting Rector 0.5.x versions.
# If you are using Rector 0.4, please upgrade your Rector version
services:
  Rector\Renaming\Rector\Function_\RenameFunctionRector:
");
        foreach ($functionNames as $functionName) {
            fwrite($stream, '    '.$functionName.": 'Safe\\".$functionName."'\n");
        }
        fclose($stream);
    }

    /**
     * Generates a configuration file for replacing all functions when using rector/rector:~0.6.
     *
     * @param Method[] $functions
     * @param string $path
     */
    public function generateRectorFileForZeroPointSix(array $functions, string $path): void
    {
        $functionNames = $this->getFunctionsNameList($functions);

        $stream = fopen($path, 'w');

        if ($stream === false) {
            throw new \RuntimeException('Unable to write to '.$path);
        }

        $header = <<<'TXT'
# This file configures rector/rector:~0.6.0 to replace all PHP functions with their equivalent "safe" functions

services:
  Rector\Renaming\Rector\Function_\RenameFunctionRector:
    $oldFunctionToNewFunction:

TXT;

        fwrite($stream, $header);

        foreach ($functionNames as $functionName) {
            fwrite($stream, '      '.$functionName.": 'Safe\\".$functionName."'\n");
        }

        fclose($stream);
    }

    public function createExceptionFile(string $moduleName): void
    {
        $exceptionName = self::toExceptionName($moduleName);
        if (!file_exists(__DIR__.'/../../lib/Exceptions/'.$exceptionName.'.php')) {
            \file_put_contents(
                __DIR__.'/../../generated/Exceptions/'.$exceptionName.'.php',
                <<<EOF
<?php
namespace Safe\Exceptions;

class {$exceptionName} extends \ErrorException implements SafeExceptionInterface
{
    public static function createFromPhpError(): self
    {
        \$error = error_get_last();
        return new self(\$error['message'] ?? 'An error occured', 0, \$error['type'] ?? 1);
    }
}

EOF
            );
        }
    }

    /**
     * Generates the name of the exception class
     *
     * @param string $moduleName
     * @return string
     */
    public static function toExceptionName(string $moduleName): string
    {
        return str_replace('-', '', \ucfirst($moduleName)).'Exception';
    }
}
