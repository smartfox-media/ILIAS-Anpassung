<?php declare(strict_types=1);

/**
 * This file is part of ILIAS, a powerful learning management system
 * published by ILIAS open source e-Learning e.V.
 * ILIAS is licensed with the GPL-3.0,
 * see https://www.gnu.org/licenses/gpl-3.0.en.html
 * You should have received a copy of said license along with the
 * source code, too.
 * If this is not the case or you just want to try ILIAS, you'll find
 * us at:
 * https://www.ilias.de
 * https://github.com/ILIAS-eLearning
 */

use PHPUnit\Framework\TestSuite;

require_once 'libs/composer/vendor/autoload.php';

/**
 * Exercise test suite
 * @author Alexander Killing <killing@leifos.de>
 */
class ilServicesFeedsSuite extends TestSuite
{
    public static function suite()
    {
        $suite = new self();

        require_once("./Services/Feeds/test/FeedItemTest.php");
        $suite->addTestSuite("FeedItemTest");

        return $suite;
    }
}