<?php declare(strict_types=1);

/* Copyright (c) 2020 Daniel Weise <daniel.weise@concepts-and-training.de> Extended GPL, see docs/LICENSE */

use ILIAS\Setup;
use ILIAS\Refinery;
use ILIAS\Data;
use ILIAS\UI;

class ilHttpSetupAgent implements Setup\Agent
{
    use Setup\Agent\HasNoNamedObjective;

    /**
     * @var Refinery\Factory
     */
    protected $refinery;

    public function __construct(
        Refinery\Factory $refinery
    ) {
        $this->refinery = $refinery;
    }

    /**
     * @inheritdoc
     */
    public function hasConfig() : bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public function getArrayToConfigTransformation() : Refinery\Transformation
    {
        return $this->refinery->custom()->transformation(function ($data) {
            return new \ilHttpSetupConfig(
                $data["path"],
                (isset($data["https_autodetection"]) && $data["https_autodetection"])
                    ? true
                    : false,
                (isset($data["https_autodetection"]) && $data["https_autodetection"])
                    ? $data["https_autodetection"]["header_name"]
                    : null,
                (isset($data["https_autodetection"]) && $data["https_autodetection"])
                    ? $data["https_autodetection"]["header_value"]
                    : null,
                (isset($data["proxy"]) && $data["proxy"])
                    ? true
                    : false,
                (isset($data["proxy"]) && $data["proxy"])
                    ? $data["proxy"]["host"]
                    : null,
                (isset($data["proxy"]) && $data["proxy"])
                    ? $data["proxy"]["port"]
                    : null,
            );
        });
    }

    /**
     * @inheritdoc
     */
    public function getInstallObjective(Setup\Config $config = null) : Setup\Objective
    {
        $http_config_stored = new ilHttpConfigStoredObjective($config);

        if (!$config->isProxyEnabled()) {
            return $http_config_stored;
        }

        return new Setup\Objective\ObjectiveWithPreconditions(
            $http_config_stored,
            new ProxyConnectableCondition($config)
        );
    }

    /**
     * @inheritdoc
     */
    public function getUpdateObjective(Setup\Config $config = null) : Setup\Objective
    {
        if ($config !== null) {
            return new ilHttpConfigStoredObjective($config);
        }
        return new Setup\Objective\NullObjective();
    }

    /**
     * @inheritdoc
     */
    public function getBuildArtifactObjective() : Setup\Objective
    {
        return new Setup\Objective\NullObjective();
    }

    /**
     * @inheritdoc
     */
    public function getStatusObjective(Setup\Metrics\Storage $storage) : Setup\Objective
    {
        return new ilHttpMetricsCollectedObjective($storage);
    }

    /**
     * @inheritDoc
     */
    public function getMigrations() : array
    {
        return [];
    }
}
