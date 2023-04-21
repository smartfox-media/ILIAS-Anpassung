<?php

/**
 * This file is part of ILIAS, a powerful learning management system
 * published by ILIAS open source e-Learning e.V.
 *
 * ILIAS is licensed with the GPL-3.0,
 * see https://www.gnu.org/licenses/gpl-3.0.en.html
 * You should have received a copy of said license along with the
 * source code, too.
 *
 * If this is not the case or you just want to try ILIAS, you'll find
 * us at:
 * https://www.ilias.de
 * https://github.com/ILIAS-eLearning
 *
 *********************************************************************/

declare(strict_types=1);

namespace ILIAS\GlobalScreen\Scope\MetaBar\Factory;

use ILIAS\GlobalScreen\Identification\IdentificationInterface;

/**
 * Class AbstractChildItem
 * @author Fabian Schmid <fs@studer-raimann.ch>
 */
abstract class AbstractChildItem extends AbstractBaseItem implements isItem, isChild
{
    /**
     * @var \ILIAS\GlobalScreen\Identification\IdentificationInterface|null
     */
    protected $parent;

    /**
     * @inheritDoc
     */
    public function withParent(IdentificationInterface $identification) : isItem
    {
        $clone = clone $this;
        $clone->parent = $identification;

        return $clone;
    }

    /**
     * @inheritDoc
     */
    public function hasParent() : bool
    {
        return ($this->parent instanceof IdentificationInterface);
    }

    /**
     * @inheritDoc
     */
    public function getParent() : IdentificationInterface
    {
        return $this->parent;
    }

    /**
     * @inheritDoc
     */
    public function overrideParent(IdentificationInterface $identification) : isChild
    {
        $this->parent = $identification;

        return $this;
    }
}
