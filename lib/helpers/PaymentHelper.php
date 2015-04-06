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
class PaymentHelper
{
    /**
     * Generate a standard reference number
     * Code borrowed from http://trac.midgard-project.org/browser/branches/branch-2_6/src/net.nemein.payment/handler/nordea.php?rev=14963
     *
     * @author The Midgard Project, http://www.midgard-project.org
     *
     * @param int $transactionId
     * @return string
     */
    public static function generateReference($transactionId)
    {
        $multipliers = array(7, 3, 1);
        $sum = 0;
        $multiplier = 0;
        $reference = (string)$transactionId;
        for ($digitPosition = strlen($reference) - 1; $digitPosition >= 0; $digitPosition--) {
            $digit = $reference{$digitPosition};
            if (!is_numeric($digit)) {
                continue;
            }
            $digit = (int)$digit;
            $sum += $digit * $multipliers[$multiplier];
            $multiplier = ($multiplier == 2) ? 0 : $multiplier + 1;
        }
        // Get the difference to the next highest ten
        $nextTen = (((int)($sum / 10)) + 1) * 10;
        $checkDigit = $nextTen - $sum;
        if ($checkDigit == 10) {
            $checkDigit = 0;
        }
        return (string)$transactionId . $checkDigit;
    }
}
