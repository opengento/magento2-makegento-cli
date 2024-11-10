<?php

declare(strict_types=1);

namespace Opengento\MakegentoCli\Utils;

/**
 * Copyright © OpenGento, All rights reserved.
 * See LICENSE bundled with this library for license details.
 */
class StringTransformationTools
{
    /**
     * Return camel case formatted string
     *
     * @param string $string
     * @return string
     */
    public function getCamelCase(string $string): string
    {
        $words = explode('_', str_replace('-', '_', $string));

        $camelCased = array_shift($words);
        $camelCased .= implode('', array_map('mb_ucfirst', $words));

        return $camelCased;
    }

    /**
     * Return snake case formatted string
     *
     * @param string $string
     * @return string
     */
    public function getSnakeCase(string $string): string
    {
        $result = '';

        for ($i = 0, $iMax = strlen($string); $i < $iMax; $i++) {
            $char = $string[$i];

            if (ctype_upper($char)) {
                $result .= '_' . strtolower($char);
            } elseif ($char === '-') {
                $result .= '_';
            } else {
                $result .= $char;
            }
        }

        return ltrim($result, '_');
    }

    /**
     * Return snake case formatted string
     *
     * @param string $string
     * @return string
     */
    public function getPascalCase(string $string): string
    {
        return mb_ucfirst($this->getCamelCase($string), 'UTF-8');
    }

    /**
     * Return kebab case formatted string
     *
     * @param string $string
     * @return string
     */
    public function getKebabCase(string $string): string
    {
        $string = mb_ucfirst($string, 'UTF-8');

        $result = '';

        for ($i = 0, $iMax = strlen($string); $i < $iMax; $i++) {
            $char = $string[$i];

            if (ctype_upper($char)) {
                $result .= '-' . strtolower($char);
            } elseif ($char === '_') {
                $result .= '-';
            } else {
                $result .= $char;
            }
        }

        return ltrim($result, '-');
    }

    public function sanitizeString(string $string): string
    {
        $string = str_replace(' ', '-', $string);

        // Remplace all accuented letters by non accuented equivalents
        $string = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $string);

        // Delete all non-alphanumeric characters except dash and underscore
        return preg_replace('/[^a-zA-Z0-9-_]/', '', $string);
    }
}
