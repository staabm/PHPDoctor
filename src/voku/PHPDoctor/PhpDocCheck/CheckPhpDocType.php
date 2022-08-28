<?php declare(strict_types=1);

namespace voku\PHPDoctor\PhpDocCheck;

use voku\SimplePhpParser\Parsers\Helper\Utils;

/**
 * @internal
 */
final class CheckPhpDocType
{
    /**
     * @param array       $types
     * @param array       $fileInfo
     * @param string[][]  $error
     * @param string      $name
     * @param string|null $className
     * @param string|null $paramName
     * @param string|null $propertyName
     *
     * @psalm-param array{type: null|string, typeFromPhpDoc: null|string, typeFromPhpDocExtended: null|string, typeFromPhpDocSimple: null|string, typeFromPhpDocMaybeWithComment: string|null}|array{type: null|string, typeFromPhpDoc: null|string, typeFromPhpDocExtended: null|string, typeFromPhpDocSimple: null|string, typeFromPhpDocMaybeWithComment: string|null, typeFromDefaultValue: null|string} $types
     * @psalm-param array{line: null|int, file: null|string} $fileInfo
     *
     * @return string[][]
     */
    public static function checkPhpDocType(
        array $types,
        array $fileInfo,
        string $name,
        array $error,
        string $className = null,
        string $paramName = null,
        string $propertyName = null
    ): array {
        // init
        $typeFromPhpWithoutNull = null;
        $typeFromPhpDocInput = $types['typeFromPhpDocSimple'];
        $typeFromPhpInput = $types['type'];

        if (
            isset($types['typeFromDefaultValue'])
            &&
            $types['typeFromDefaultValue'] === 'null'
        ) {
            if ($typeFromPhpInput) {
                $typeFromPhpInput .= '|null';
            } else {
                $typeFromPhpInput = 'null';
            }
        }

        $removeEmptyStringFunc = static function (?string $tmp): bool {
            return $tmp !== '';
        };
        $typeFromPhpDoc = \array_unique(
            \array_filter(
                \explode('|', $typeFromPhpDocInput ?? ''),
                $removeEmptyStringFunc
            )
        );
        /** @noinspection AlterInForeachInspection */
        foreach ($typeFromPhpDoc as $keyTmp => $typeFromPhpDocSingle) {
            /** @noinspection InArrayCanBeUsedInspection */
            if (
                $typeFromPhpDocSingle === '$this'
                ||
                $typeFromPhpDocSingle === 'static'
                ||
                $typeFromPhpDocSingle === 'self'
            ) {
                $typeFromPhpDoc[$keyTmp] = $className;
            }

            if (\is_string($typeFromPhpDoc[$keyTmp])) {
                $typeFromPhpDoc[$keyTmp] = \ltrim($typeFromPhpDoc[$keyTmp], '\\');
            }
        }
        $typeFromPhp = \array_unique(
            \array_filter(
                \explode('|', $typeFromPhpInput ?? ''),
                $removeEmptyStringFunc
            )
        );
        /** @noinspection AlterInForeachInspection */
        foreach ($typeFromPhp as $keyTmp => $typeFromPhpSingle) {
            /** @noinspection InArrayCanBeUsedInspection */
            if (
                $typeFromPhpSingle === '$this'
                ||
                $typeFromPhpSingle === 'static'
                ||
                $typeFromPhpSingle === 'self'
            ) {
                $typeFromPhp[$keyTmp] = $className;
            }

            if (\is_string($typeFromPhp[$keyTmp])) {
                $typeFromPhp[$keyTmp] = \ltrim($typeFromPhp[$keyTmp], '\\');
            }

            if ($typeFromPhpSingle && \strtolower($typeFromPhpSingle) !== 'null') {
                $typeFromPhpWithoutNull = $typeFromPhp[$keyTmp];
            }
        }

        if (
            \count($typeFromPhpDoc) > 0
            &&
            \count($typeFromPhp) > 0
        ) {
            foreach ($typeFromPhp as $typeFromPhpSingle) {
                // reset
                $checked = null;

                /** @noinspection SuspiciousBinaryOperationInspection */
                if (
                    $typeFromPhpSingle
                    &&
                    $typeFromPhpDocInput
                    &&
                    !\in_array($typeFromPhpSingle, $typeFromPhpDoc, true)
                    &&
                    (
                        $typeFromPhpSingle === 'array' && \strpos($typeFromPhpDocInput, '[]') === false
                        ||
                        $typeFromPhpSingle !== 'array'
                    )
                ) {
                    $checked = false;

                    if (
                        $typeFromPhpSingle === 'string'
                        &&
                        \strpos($typeFromPhpDocInput, 'class-string') === 0
                    ) {
                        $checked = true;
                    }

                    if (
                        $checked === false
                        &&
                        (
                            \class_exists($typeFromPhpSingle, true)
                            ||
                            \interface_exists($typeFromPhpSingle, true)
                        )
                    ) {
                        foreach ($typeFromPhpDoc as $typeFromPhpDocTmp) {
                            // prevent false-positive results if the namespace is only imported party etc.
                            if (
                                $typeFromPhpDocTmp
                                &&
                                (
                                    $typeFromPhpDocTmp === $typeFromPhpSingle
                                    ||
                                    \strpos($typeFromPhpSingle, $typeFromPhpDocTmp) !== false
                                )
                            ) {
                                $checked = true;

                                break;
                            }

                            if (
                                $typeFromPhpDocTmp
                                &&
                                (
                                    \class_exists($typeFromPhpDocTmp, true)
                                    ||
                                    \interface_exists($typeFromPhpDocTmp, true)
                                )
                                &&
                                (
                                    /** @phpstan-ignore-next-line */
                                    ($typeFromPhpDocReflectionClass = Utils::createClassReflectionInstance($typeFromPhpDocTmp))
                                    &&
                                    (
                                        $typeFromPhpDocReflectionClass->isSubclassOf($typeFromPhpSingle)
                                        ||
                                        $typeFromPhpDocReflectionClass->implementsInterface($typeFromPhpSingle)
                                    )
                                )
                            ) {
                                $checked = true;

                                break;
                            }
                        }
                    }

                    if (!$checked) {
                        if ($propertyName) {
                            $error[$fileInfo['file'] ?? ''][] = '[' . ($fileInfo['line'] ?? '?') . ']: missing property type "' . $typeFromPhpSingle . '" in phpdoc from ' . $name . ' | property:' . $propertyName;
                        } elseif ($paramName) {
                            $error[$fileInfo['file'] ?? ''][] = '[' . ($fileInfo['line'] ?? '?') . ']: missing parameter type "' . $typeFromPhpSingle . '" in phpdoc from ' . $name . ' | parameter:' . $paramName;
                        } else {
                            $error[$fileInfo['file'] ?? ''][] = '[' . ($fileInfo['line'] ?? '?') . ']: missing return type "' . $typeFromPhpSingle . '" in phpdoc from ' . $name;
                        }
                    }
                }
            }

            foreach ($typeFromPhpDoc as $typeFromPhpDocSingle) {
                /** @noinspection SuspiciousBinaryOperationInspection */
                /** @noinspection NotOptimalIfConditionsInspection */
                if (
                    !\in_array($typeFromPhpDocSingle, $typeFromPhp, true)
                    &&
                    (
                        $typeFromPhpDocSingle === 'null'
                        ||
                        (
                            $typeFromPhpWithoutNull
                            &&
                            $typeFromPhpDocSingle !== $typeFromPhpWithoutNull
                        )
                    )
                ) {
                    // reset
                    $checked = null;

                    if (
                        $typeFromPhpWithoutNull === 'bool'
                        &&
                        (
                            $typeFromPhpDocSingle === 'true'
                            ||
                            $typeFromPhpDocSingle === 'false'
                        )
                    ) {
                        $checked = true;
                    }

                    if (
                        $typeFromPhpWithoutNull
                        &&
                        $typeFromPhpDocSingle
                        &&
                        $typeFromPhpWithoutNull === 'string'
                        &&
                        \strpos($typeFromPhpDocSingle, 'class-string') === 0
                    ) {
                        $checked = true;
                    }

                    if (
                        $typeFromPhpDocSingle
                        &&
                        $typeFromPhpWithoutNull
                        &&
                        (
                            $typeFromPhpWithoutNull === 'array'
                            ||
                            \ltrim($typeFromPhpWithoutNull, '\\') === 'Generator'
                        )
                        &&
                        \strpos($typeFromPhpDocSingle, '[]') !== false
                    ) {
                        $checked = true;
                    }

                    if (
                        !$checked
                        &&
                        $typeFromPhpWithoutNull
                    ) {
                        $checked = false;

                        // prevent false-positive results if the namespace is only imported party etc.
                        if (
                            $typeFromPhpDocSingle
                            &&
                            (
                                $typeFromPhpDocSingle === $typeFromPhpWithoutNull
                                ||
                                \strpos($typeFromPhpWithoutNull, $typeFromPhpDocSingle) !== false
                            )
                        ) {
                            $checked = true;
                        }

                        if (
                            $checked === false
                            &&
                            $typeFromPhpDocSingle
                            &&
                            (
                                \class_exists($typeFromPhpWithoutNull, true)
                                ||
                                \interface_exists($typeFromPhpWithoutNull, true)
                            )
                            &&
                            (
                                \class_exists($typeFromPhpDocSingle, true)
                                ||
                                \interface_exists($typeFromPhpDocSingle, true)
                            )
                        ) {
                            $typeFromPhpDocReflectionClass = Utils::createClassReflectionInstance($typeFromPhpDocSingle);
                            if (
                                $typeFromPhpDocReflectionClass->isSubclassOf($typeFromPhpWithoutNull)
                                ||
                                $typeFromPhpDocReflectionClass->implementsInterface($typeFromPhpWithoutNull)
                            ) {
                                $checked = true;
                            }
                        }
                    }

                    if (!$checked) {
                        if ($propertyName) {
                            $error[$fileInfo['file'] ?? ''][] = '[' . ($fileInfo['line'] ?? '?') . ']: wrong property type "' . ($typeFromPhpDocSingle ?? '?') . '" in phpdoc from ' . $name . '  | property:' . $propertyName;
                        } elseif ($paramName) {
                            $error[$fileInfo['file'] ?? ''][] = '[' . ($fileInfo['line'] ?? '?') . ']: wrong parameter type "' . ($typeFromPhpDocSingle ?? '?') . '" in phpdoc from ' . $name . '  | parameter:' . $paramName;
                        } else {
                            $error[$fileInfo['file'] ?? ''][] = '[' . ($fileInfo['line'] ?? '?') . ']: wrong return type "' . ($typeFromPhpDocSingle ?? '?') . '" in phpdoc from ' . $name;
                        }
                    }
                }
            }
        }

        return $error;
    }
}
