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
namespace ILIAS\GlobalScreen\Scope\Notification\Factory;

use ILIAS\GlobalScreen\Identification\IdentificationInterface;
use ILIAS\GlobalScreen\Scope\Notification\Collector\Renderer\AdministrativeNotificationRenderer;
use ILIAS\GlobalScreen\Scope\Notification\Collector\Renderer\NotificationRenderer;
use ILIAS\UI\Factory as UIFactory;
use Closure;

/**
 * Class AdministrativeNotification
 * @package ILIAS\GlobalScreen\Scope\Notification\Factory
 * @author  Fabian Schmid <fs@studer-raimann.ch>
 */
class AdministrativeNotification extends AbstractBaseNotification implements isItem, hasTitle
{
    public const DENOTATION_NEUTRAL = 'neutral';
    public const DENOTATION_IMPORTANT = 'important';
    public const DENOTATION_BREAKING = 'breaking';

    /**
     * @var bool
     */
    private $is_visible_static;

    /**
     * @var \ILIAS\GlobalScreen\Identification\IdentificationInterface
     */
    protected $provider_identification;

    /**
     * @var string
     */
    protected $title;

    /**
     * @var string
     */
    protected $summary;

    /**
     * @var \Closure|null
     */
    protected $available_callable;
    /**
     * @var \Closure|null
     */
    protected $visiblility_callable;
    /**
     * @var bool
     */
    protected $is_always_available = false;
    /**
     * @var string
     */
    protected $denotation = self::DENOTATION_NEUTRAL;

    /**
     * @inheritDoc
     */
    public function getRenderer(UIFactory $factory) : NotificationRenderer
    {
        return new AdministrativeNotificationRenderer($factory);
    }

    /**
     * @inheritDoc
     */
    public function withTitle(string $title) : hasTitle
    {
        $clone = clone $this;
        $clone->title = $title;

        return $clone;
    }

    /**
     * @inheritDoc
     */
    public function getTitle() : string
    {
        return $this->title;
    }

    /**
     * @inheritDoc
     */
    public function withSummary(string $summary) : isItem
    {
        $clone = clone $this;
        $clone->summary = $summary;

        return $clone;
    }

    /**
     * @inheritDoc
     */
    public function getSummary() : string
    {
        return $this->summary;
    }

    public function withVisibilityCallable(callable $is_visible) : self
    {
        $clone = clone($this);
        $clone->visiblility_callable = $is_visible;

        return $clone;
    }

    public function isVisible() : bool
    {
        if (isset($this->is_visible_static)) {
            return $this->is_visible_static;
        }
        if (!$this->isAvailable()) {
            return $this->is_visible_static = false;
        }
        if (is_callable($this->visiblility_callable)) {
            $callable = $this->visiblility_callable;

            $value = $callable();

            return $this->is_visible_static = $value;
        }

        return $this->is_visible_static = true;
    }

    public function isAvailable() : bool
    {
        if (is_callable($this->available_callable)) {
            $callable = $this->available_callable;

            return $callable();
        }

        return true;
    }

    public function withAvailableCallable(callable $is_available) : self
    {
        $clone = clone($this);
        $clone->available_callable = $is_available;

        return $clone;
    }

    public function withNeutralDenotation() : self
    {
        $clone = clone($this);
        $clone->denotation = self::DENOTATION_NEUTRAL;

        return $clone;
    }

    public function withImportantDenotation() : self
    {
        $clone = clone($this);
        $clone->denotation = self::DENOTATION_IMPORTANT;

        return $clone;
    }

    public function withBreakingDenotation() : self
    {
        $clone = clone($this);
        $clone->denotation = self::DENOTATION_BREAKING;

        return $clone;
    }

    public function getDenotation() : string
    {
        return $this->denotation ?? self::DENOTATION_NEUTRAL;
    }
}
