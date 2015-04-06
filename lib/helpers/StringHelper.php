<?php
/**
 *
 * @author Ivo Kund <ivo@opus.ee>
 * @date 21.01.14
 */

namespace dlds\payment\helpers;

/**
 * Class StringHelper
 *
 * @author Ivo Kund <ivo@opus.ee>
 * @package dlds\payment\helpers
 */
class StringHelper
{
    /**
     * Multi-byte str-pad
     * @param string $input
     * @param integer $pad_length
     * @param string $pad_string
     * @param int $pad_type
     * @param null $encoding
     * @return string
     */
    public static function mbStringPad($input, $pad_length, $pad_string = ' ', $pad_type = STR_PAD_RIGHT, $encoding = NULL)
    {
        if (!$encoding)
        {
            $diff = strlen($input) - mb_strlen($input);
        }
        else
        {
            $diff = strlen($input) - mb_strlen($input, $encoding);
        }

        return str_pad($input, $pad_length + $diff, $pad_string, $pad_type);
    }
} 