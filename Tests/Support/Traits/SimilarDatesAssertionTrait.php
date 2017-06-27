<?php
declare(strict_types=1);

namespace PhpList\PhpList4\Tests\Support\Traits;

/**
 * This trait provides the assertSimilarDates method.
 *
 * @author Oliver Klee <oliver@phplist.com>
 */
trait SimilarDatesAssertionTrait
{
    /**
     * Asserts that $expected and $actual are similar, i.e., less than than 2 seconds apart.
     *
     * @param \DateTime $expected
     * @param \DateTime $actual
     *
     * @return void
     */
    private static function assertSimilarDates(\DateTime $expected, \DateTime $actual)
    {
        $maximumAllowedDifference = 2;

        $difference = $actual->diff($expected, true);
        $differenceInSeconds = $difference->s + $difference->i * 60 + $difference->h * 3600;
        self::assertLessThan($maximumAllowedDifference, $differenceInSeconds);
    }
}
